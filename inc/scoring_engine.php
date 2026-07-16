<?php
function getScoringData($pdo) {
    // 1. Fetch all active materials including overall_weight
    $stmtMat = $pdo->query("SELECT id, name, target_weight, credit_weight, overall_weight FROM materials WHERE is_active = 1 ORDER BY id ASC");
    $activeMaterials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch specific material targets/credits for all companies
    $stmtData = $pdo->query("
        SELECT cm.company_id, cm.material_id, m.name, m.target_weight, m.credit_weight, m.overall_weight, cm.target_tons, cm.credits
        FROM company_materials cm
        JOIN materials m ON cm.material_id = m.id
        WHERE m.is_active = 1
    ");
    $rawMatData = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // 3. Calculate Global Averages and Standard Deviations per active material
    $materialStats = [];
    foreach ($activeMaterials as $mat) {
        $mId = $mat['id'];
        
        // Pass 1: Calculate Mean
        $sumT = 0; $countT = 0;
        $sumC = 0; $countC = 0;
        foreach ($rawMatData as $row) {
            if ($row['material_id'] == $mId) {
                if ($row['target_tons'] > 0) { $sumT += $row['target_tons']; $countT++; }
                if ($row['credits'] > 0) { $sumC += $row['credits']; $countC++; }
            }
        }
        $meanT = $countT > 0 ? ($sumT / $countT) : 0;
        $meanC = $countC > 0 ? ($sumC / $countC) : 0;
        
        // Pass 2: Calculate Variance & Standard Deviation
        $varSumT = 0;
        $varSumC = 0;
        foreach ($rawMatData as $row) {
            if ($row['material_id'] == $mId) {
                if ($row['target_tons'] > 0) { $varSumT += pow($row['target_tons'] - $meanT, 2); }
                if ($row['credits'] > 0) { $varSumC += pow($row['credits'] - $meanC, 2); }
            }
        }
        $stdDevT = $countT > 0 ? sqrt($varSumT / $countT) : 0;
        $stdDevC = $countC > 0 ? sqrt($varSumC / $countC) : 0;
        
        $materialStats[$mId] = [
            'mean_target' => $meanT,
            'stddev_target' => $stdDevT > 0 ? $stdDevT : 1, // Prevent division by zero
            'mean_credits' => $meanC,
            'stddev_credits' => $stdDevC > 0 ? $stdDevC : 1
        ];
    }

    // Helper: Map Z-score to 1-100 scale (Assuming Z=-3 is 1, Z=0 is 50, Z=+3 is 100)
    $zTo100 = function($z) {
        // Cap Z between -3 and +3
        $z = max(-3, min(3, $z));
        // Linear mapping: (Z + 3) / 6 * 99 + 1
        return (($z + 3) / 6) * 99 + 1;
    };

    // 4. Group data by company to determine active materials and calculate scores
    $companyRawScores = [];
    $companyMatData = [];
    
    // Group rows by company first
    $companiesData = [];
    foreach ($rawMatData as $row) {
        $cId = $row['company_id'];
        $companiesData[$cId][] = $row;
    }

    foreach ($companiesData as $cId => $companyRows) {
        // Find sum of overall weights for this specific company's active materials
        $companyTotalWeight = 0;
        foreach ($companyRows as $row) {
            $companyTotalWeight += $row['overall_weight'];
        }
        
        $companyAbsoluteScore = 0;
        
        foreach ($companyRows as $row) {
            $mId = $row['material_id'];
            $mName = $row['name'];
            $stats = $materialStats[$mId];
            
            // Auto-Normalize the Overall Weight so they sum to 1.0 for this company
            $normalizedOverallWeight = $companyTotalWeight > 0 ? ($row['overall_weight'] / $companyTotalWeight) : 0;
            
            // Calculate Z-Score
            $zTarget = ($row['target_tons'] - $stats['mean_target']) / $stats['stddev_target'];
            $zCredits = ($row['credits'] - $stats['mean_credits']) / $stats['stddev_credits'];
            
            // Map to 1-100 Score
            $scoreTarget100 = $zTo100($zTarget);
            $scoreCredits100 = $zTo100($zCredits);
            
            // Material Composite Score: Prioritize the GAP (Target - Credits) 
            $materialCompositeScore = ($scoreTarget100 * $row['target_weight']) + (($scoreTarget100 - $scoreCredits100) * $row['credit_weight']);
            
            // Apply Dynamic Normalized Overall Material Weight
            $contributionToGlobal = $materialCompositeScore * $normalizedOverallWeight;
            
            $companyAbsoluteScore += $contributionToGlobal;
            
            $companyMatData[$cId][$mName] = [
                'target' => $row['target_tons'],
                'credits' => $row['credits'],
                'z_score_scaled_100' => $materialCompositeScore,
                'normalized_overall_weight' => $normalizedOverallWeight,
                'contribution_to_global' => $contributionToGlobal
            ];
        }
        
        // This is now an absolute 0-100 grade
        $companyRawScores[$cId] = $companyAbsoluteScore;
    }

    return [
        'active_materials' => $activeMaterials,
        'material_stats' => $materialStats,
        'company_raw_scores' => $companyRawScores,
        'company_mat_data' => $companyMatData
    ];
}
?>
