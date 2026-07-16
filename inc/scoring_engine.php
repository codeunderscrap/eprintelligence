<?php
function getScoringData($pdo) {
    // 1. Fetch all active materials
    $stmtMat = $pdo->query("SELECT id, name, target_weight, credit_weight FROM materials WHERE is_active = 1 ORDER BY id ASC");
    $activeMaterials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch specific material targets/credits for all companies
    $stmtData = $pdo->query("
        SELECT cm.company_id, cm.material_id, m.name, m.target_weight, m.credit_weight, cm.target_tons, cm.credits
        FROM company_materials cm
        JOIN materials m ON cm.material_id = m.id
        WHERE m.is_active = 1
    ");
    $rawMatData = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // 3. Calculate Global Averages per active material
    $materialAverages = [];
    foreach ($activeMaterials as $mat) {
        $mId = $mat['id'];
        $sumT = 0; $countT = 0;
        $sumC = 0; $countC = 0;
        
        foreach ($rawMatData as $row) {
            if ($row['material_id'] == $mId) {
                if ($row['target_tons'] > 0) { $sumT += $row['target_tons']; $countT++; }
                if ($row['credits'] > 0) { $sumC += $row['credits']; $countC++; }
            }
        }
        $materialAverages[$mId]['avg_target'] = $countT > 0 ? ($sumT / $countT) : 1; 
        $materialAverages[$mId]['avg_credits'] = $countC > 0 ? ($sumC / $countC) : 1;
    }

    // 4. Calculate Raw Normalized Score for each company
    $companyRawScores = [];
    $companyMatData = [];
    $maxRawScore = 0;

    foreach ($rawMatData as $row) {
        $cId = $row['company_id'];
        $mId = $row['material_id'];
        $mName = $row['name'];
        
        $normalizedTarget = $row['target_tons'] / $materialAverages[$mId]['avg_target'];
        $normalizedCredits = $row['credits'] / $materialAverages[$mId]['avg_credits'];
        
        $matScore = ($normalizedTarget * $row['target_weight']) + ($normalizedCredits * $row['credit_weight']);
        
        if (!isset($companyRawScores[$cId])) {
            $companyRawScores[$cId] = 0;
        }
        $companyRawScores[$cId] += $matScore;
        
        if ($companyRawScores[$cId] > $maxRawScore) {
            $maxRawScore = $companyRawScores[$cId];
        }
        
        $companyMatData[$cId][$mName] = [
            'target' => $row['target_tons'],
            'credits' => $row['credits'],
            'normalized_score' => $matScore
        ];
    }

    return [
        'active_materials' => $activeMaterials,
        'material_averages' => $materialAverages,
        'company_raw_scores' => $companyRawScores,
        'company_mat_data' => $companyMatData,
        'max_raw_score' => $maxRawScore
    ];
}
?>
