<?php
include 'config.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_material_id'])) {
    $matIdToDelete = (int)$_POST['delete_material_id'];
    if ($matIdToDelete > 0) {
        $stmt = $pdo->prepare("DELETE FROM company_materials WHERE material_id = ?");
        $stmt->execute([$matIdToDelete]);
        $success = "Dataset wiped successfully.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['dataset']) && isset($_POST['material_id'])) {
    $fileTmpPath = $_FILES['dataset']['tmp_name'];
    $materialId = (int)$_POST['material_id'];
    
    set_time_limit(300); // Allow up to 5 minutes for parsing large files

    if (!empty($fileTmpPath) && $materialId > 0) {
        try {
            $reader = IOFactory::createReaderForFile($fileTmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();
            
            $stmt = $pdo->prepare("INSERT INTO projects (owner_id, name) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'Upload ' . date('Y-m-d H:i')]);
            $projectId = $pdo->lastInsertId();
            
            // Clean Replace Logic: Wipe existing data for this specific material before import
            $stmtDel = $pdo->prepare("DELETE FROM company_materials WHERE material_id = ?");
            $stmtDel->execute([$materialId]);

            $insertCompany = $pdo->prepare("
                INSERT INTO companies (project_id, registration_number, company_name) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
            ");

            $insertMaterial = $pdo->prepare("
                INSERT INTO company_materials (company_id, material_id, target_tons, credits) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                target_tons = GREATEST(target_tons, VALUES(target_tons)),
                credits = GREATEST(credits, VALUES(credits))
            ");

            $dataStarted = false;
            foreach ($data as $row) {
                if (empty(array_filter($row))) continue;
                if (!$dataStarted) {
                    if (isset($row[1]) && stripos($row[1], 'Producer Name') !== false) {
                        $dataStarted = true;
                    }
                    continue;
                }
                
                $regNo = $row[0] ?? ''; 
                $compName = $row[1] ?? ''; 
                $targetRaw = preg_replace('/[^0-9.]/', '', $row[4] ?? '0');
                $creditsRaw = preg_replace('/[^0-9.]/', '', $row[5] ?? '0');
                
                $target = is_numeric($targetRaw) ? (float)$targetRaw : 0;
                $credits = is_numeric($creditsRaw) ? (float)$creditsRaw : 0;

                if (empty(trim($compName))) continue;
                
                $insertCompany->execute([$projectId, $regNo, $compName]);
                $companyId = $pdo->lastInsertId();
                
                $insertMaterial->execute([$companyId, $materialId, $target, $credits]);
            }
            header('Location: dashboard.php');
            exit;
        } catch (Exception $e) {
            $error = "Error parsing file: " . $e->getMessage();
        }
    }
}

$stmt = $pdo->query("SELECT id, name FROM materials WHERE is_active = 1 ORDER BY name ASC");
$activeMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Dataset – EPR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'inc/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="mb-4">
                <h2 class="mb-1">Upload Dataset</h2>
                <p class="text-muted mb-0">Import your EPR tracking sheet (.xlsx, .csv)</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger shadow-sm rounded-3 border-0 border-start border-4 border-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success shadow-sm rounded-3 border-0 border-start border-4 border-success">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow-sm" style="max-width: 600px;">
                <div class="card-body p-4">
                    <div class="text-center mb-4 text-primary">
                        <i class="bi bi-cloud-arrow-up" style="font-size: 3rem;"></i>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4 text-start">
                            <label class="form-label fw-semibold text-muted">Select Target Material</label>
                            <select name="material_id" class="form-select form-select-lg mb-3" required>
                                <option value="">-- Choose Material --</option>
                                <?php foreach ($activeMaterials as $mat): ?>
                                    <option value="<?= $mat['id'] ?>"><?= htmlspecialchars($mat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label class="form-label fw-semibold text-muted">Select Excel / CSV File</label>
                            <input class="form-control form-control-lg" type="file" name="dataset" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                        </div>
                        <button class="btn btn-primary btn-lg w-100 mb-2">Upload & Process Data</button>
                    </form>
                </div>
            </div>

            <!-- Uploaded Datasets Status -->
            <div class="card shadow-sm mt-4" style="max-width: 600px;">
                <div class="card-header bg-white">
                    <h6 class="mb-0 text-primary"><i class="bi bi-database-check me-2"></i>Dataset Upload Status</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php
                        $statStmt = $pdo->query("SELECT m.id, m.name, COUNT(cm.company_id) as company_count FROM materials m LEFT JOIN company_materials cm ON m.id = cm.material_id GROUP BY m.id ORDER BY m.name ASC");
                        $stats = $statStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($stats as $stat): 
                            $badgeClass = $stat['company_count'] > 0 ? 'bg-success' : 'bg-secondary';
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($stat['name']) ?>
                                <div>
                                    <span class="badge <?= $badgeClass ?> rounded-pill me-2">
                                        <?= $stat['company_count'] > 0 ? $stat['company_count'] . ' Companies' : 'No Data' ?>
                                    </span>
                                    <?php if ($stat['company_count'] > 0): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to completely wipe the <?= htmlspecialchars($stat['name']) ?> dataset?');">
                                        <input type="hidden" name="delete_material_id" value="<?= $stat['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete Dataset"><i class="bi bi-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
