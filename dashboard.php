<?php
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->query("
    SELECT c.*, 
           IFNULL(SUM(cm.target_tons), 0) AS total_target,
           IFNULL(SUM(cm.credits), 0) AS total_credits,
           IFNULL(SUM((cm.target_tons * m.target_weight) + (cm.credits * m.credit_weight)), 0) AS priority_score
    FROM companies c
    LEFT JOIN company_materials cm ON c.id = cm.company_id
    LEFT JOIN materials m ON cm.material_id = m.id AND m.is_active = 1
    GROUP BY c.id
    ORDER BY priority_score DESC
");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                    <th>Target (Tons)</th>
                                    <th>Credits</th>
                                    <th>Priority Score 
                                        <span class="badge rounded-pill bg-secondary ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Calculated dynamically based on active materials and Admin weights">?</span>
                                    </th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($companies) > 0): ?>
                                    <?php foreach ($companies as $c): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($c['registration_number']) ?></td>
                                            <td><span class="fw-semibold"><?= htmlspecialchars($c['company_name']) ?></span></td>
                                            <td><?= number_format($c['total_target'], 2) ?></td>
                                            <td><?= number_format($c['total_credits'], 2) ?></td>
                                            <td><strong class="text-primary"><?= number_format($c['priority_score'], 2) ?></strong></td>
                                            <td>
                                                <a href="company.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">AI Research & Deals</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
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
