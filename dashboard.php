<?php
include 'config.php';
require_once 'inc/scoring_engine.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$scoring = getScoringData($pdo);
$activeMaterials = $scoring['active_materials'];
$companyRawScores = $scoring['company_raw_scores'];
$companyMatData = $scoring['company_mat_data'];

// 5. Fetch all companies, apply absolute score, and sort
$stmt = $pdo->query("SELECT * FROM companies");
$companiesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$companies = [];
foreach ($companiesRaw as $c) {
    $cId = $c['id'];
    // Only include companies that have active material data
    if (isset($companyRawScores[$cId])) {
        $c['priority_score'] = $companyRawScores[$cId];
        $c['materials'] = $companyMatData[$cId] ?? [];
        $companies[] = $c;
    }
}

// Sort by Priority Score DESC
usort($companies, function($a, $b) {
    return $b['priority_score'] <=> $a['priority_score'];
});

?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard – EPR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'inc/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Dashboard</h2>
                    <p class="text-muted mb-0">Overview of EPR targets and AI intelligence</p>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="m-0">Top Companies by Priority</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 12%;">Reg. No.</th>
                                    <th style="width: 25%;">Company Name</th>
                                    <?php foreach ($activeMaterials as $mat): ?>
                                        <th class="text-center bg-light"><?= htmlspecialchars($mat['name']) ?></th>
                                    <?php endforeach; ?>
                                    <th style="width: 12%;">Priority Score 
                                        <span class="badge rounded-pill bg-secondary ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Absolute Score (1-100) based on Z-Scores and dynamically normalized Overall Weights">?</span>
                                    </th>
                                    <th style="width: 15%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($companies) > 0): ?>
                                    <?php foreach ($companies as $c): ?>
                                        <tr>
                                            <td class="align-middle text-muted"><?= htmlspecialchars($c['registration_number']) ?></td>
                                            <td class="align-middle"><span class="fw-bold text-dark"><?= htmlspecialchars($c['company_name']) ?></span></td>
                                            
                                            <?php foreach ($activeMaterials as $mat): ?>
                                                <?php 
                                                    $mName = $mat['name'];
                                                    if (isset($c['materials'][$mName])) {
                                                        $t = number_format($c['materials'][$mName]['target'], 2);
                                                        $cr = number_format($c['materials'][$mName]['credits'], 2);
                                                        echo "<td class='text-center align-middle bg-light bg-opacity-50'>
                                                                <div class='d-inline-flex flex-column gap-1 text-start' style='min-width: 90px;'>
                                                                    <span class='badge bg-white text-dark border shadow-sm w-100 text-start'><span class='text-primary fw-bold me-1'>T:</span> {$t}</span>
                                                                    <span class='badge bg-white text-dark border shadow-sm w-100 text-start'><span class='text-success fw-bold me-1'>C:</span> {$cr}</span>
                                                                </div>
                                                              </td>";
                                                    } else {
                                                        echo "<td class='text-center align-middle bg-light bg-opacity-50 text-muted small fst-italic'>-</td>";
                                                    }
                                                ?>
                                            <?php endforeach; ?>

                                            <td class="align-middle"><strong class="text-primary"><?= number_format($c['priority_score'], 2) ?></strong></td>
                                            <td class="align-middle">
                                                <a href="company.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">AI Research & Deals</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= 4 + count($activeMaterials) ?>" class="text-center py-5">
                                            <i class="bi bi-inbox text-muted fs-1 d-block mb-3"></i>
                                            <p class="text-muted mb-3">No companies found. Please upload a dataset to get started.</p>
                                            <a href="upload.php" class="btn btn-primary">Upload Dataset</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>
</html>
