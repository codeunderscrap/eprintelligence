<?php
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 1. Fetch all active materials to create dynamic table columns
$stmtMat = $pdo->query("SELECT id, name FROM materials WHERE is_active = 1 ORDER BY id ASC");
$activeMaterials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch all companies and their calculated priority scores
$stmt = $pdo->query("
    SELECT c.*, 
           IFNULL(SUM((cm.target_tons * m.target_weight) + (cm.credits * m.credit_weight)), 0) AS priority_score
    FROM companies c
    LEFT JOIN company_materials cm ON c.id = cm.company_id
    LEFT JOIN materials m ON cm.material_id = m.id AND m.is_active = 1
    GROUP BY c.id
    ORDER BY priority_score DESC
");
$companiesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch specific material targets/credits for all companies
$stmtData = $pdo->query("
    SELECT cm.company_id, m.name, cm.target_tons, cm.credits
    FROM company_materials cm
    JOIN materials m ON cm.material_id = m.id
    WHERE m.is_active = 1
");
$matData = [];
while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
    $matData[$row['company_id']][$row['name']] = [
        'target' => $row['target_tons'],
        'credits' => $row['credits']
    ];
}

// 4. Combine data
$companies = [];
foreach ($companiesRaw as $c) {
    $c['materials'] = $matData[$c['id']] ?? [];
    $companies[] = $c;
}

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
                                    <th>Registration No.</th>
                                    <th>Company Name</th>
                                    <?php foreach ($activeMaterials as $mat): ?>
                                        <th class="text-center"><?= htmlspecialchars($mat['name']) ?></th>
                                    <?php endforeach; ?>
                                    <th>Priority Score 
                                        <span class="badge rounded-pill bg-secondary ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Formula: SUM((Material Target × Target Weight) + (Material Credits × Credit Weight)) for all active materials">?</span>
                                    </th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($companies) > 0): ?>
                                    <?php foreach ($companies as $c): ?>
                                        <tr>
                                            <td class="align-middle"><?= htmlspecialchars($c['registration_number']) ?></td>
                                            <td class="align-middle"><span class="fw-semibold"><?= htmlspecialchars($c['company_name']) ?></span></td>
                                            
                                            <?php foreach ($activeMaterials as $mat): ?>
                                                <?php 
                                                    $mName = $mat['name'];
                                                    if (isset($c['materials'][$mName])) {
                                                        $t = number_format($c['materials'][$mName]['target'], 2);
                                                        $cr = number_format($c['materials'][$mName]['credits'], 2);
                                                        echo "<td class='text-center align-middle'><span class='d-block small text-muted'>T: <strong>{$t}</strong></span><span class='d-block small text-muted'>C: <strong>{$cr}</strong></span></td>";
                                                    } else {
                                                        echo "<td class='text-center align-middle text-muted small fst-italic'>-</td>";
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
