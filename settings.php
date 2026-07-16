<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['api'])) {
        $stmt = $pdo->prepare("UPDATE api_keys SET api_key = ? WHERE service_name = ?");
        foreach ($_POST['api'] as $service => $key) {
            if (!empty(trim($key)) && strpos($key, '****') === false) {
                $stmt->execute([trim($key), $service]);
            }
        }
        $message = "API Keys updated successfully!";
    } elseif (isset($_POST['materials'])) {
        $stmt = $pdo->prepare("UPDATE materials SET is_active = ?, target_weight = ?, credit_weight = ?, overall_weight = ? WHERE id = ?");
        foreach ($_POST['materials'] as $id => $data) {
            $isActive = isset($data['is_active']) ? 1 : 0;
            $targetWeight = (float)$data['target_weight'];
            $creditWeight = (float)$data['credit_weight'];
            $overallWeight = (float)$data['overall_weight'];
            $stmt->execute([$isActive, $targetWeight, $creditWeight, $overallWeight, $id]);
        }
        $message = "Material Settings updated successfully!";
    } elseif (isset($_POST['new_material'])) {
        $name = trim($_POST['new_material_name']);
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO materials (name, is_active, target_weight, credit_weight, overall_weight) VALUES (?, 1, 1.0, 0.5, 1.0)");
            $stmt->execute([$name]);
            $message = "New material added successfully!";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM api_keys");
$apiKeys = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $apiKeys[$row['service_name']] = $row;
}

$stmt = $pdo->query("SELECT * FROM materials ORDER BY id ASC");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Settings – Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'inc/sidebar.php'; ?>
        <div class="main-content">
            <h2 class="fw-bold mb-4">API Key Management</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success border-0 border-start border-4 border-success shadow-sm">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white pt-4 pb-3 border-bottom">
                    <h5 class="mb-0 text-primary"><i class="bi bi-key me-2"></i>Configure Intelligence Engines</h5>
                    <p class="text-muted small mt-1 mb-0">Update the API credentials used by the AI Sourcing Agent. Keys are masked for security.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row g-4">
                            <?php foreach ($apiKeys as $service => $data): ?>
                                <?php
                                    $maskedKey = '';
                                    if (!empty($data['api_key'])) {
                                        $len = strlen($data['api_key']);
                                        $prefix = substr($data['api_key'], 0, 4);
                                        $suffix = substr($data['api_key'], -4);
                                        $maskedKey = $prefix . str_repeat('*', max(0, $len - 8)) . $suffix;
                                    }
                                    $lastUsed = $data['last_used_at'] ? date('M d, Y h:i A', strtotime($data['last_used_at'])) : 'Never';
                                ?>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="fw-semibold mb-0"><?= htmlspecialchars($service) ?> API</label>
                                            <span class="badge bg-secondary">Last Used: <?= $lastUsed ?></span>
                                        </div>
                                        <input type="text" name="api[<?= htmlspecialchars($service) ?>]" class="form-control" value="<?= $maskedKey ?>" placeholder="Enter new <?= htmlspecialchars($service) ?> key...">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary shadow-sm px-4"><i class="bi bi-save me-2"></i>Save Configuration</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white pt-4 pb-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 text-primary"><i class="bi bi-box me-2"></i>Material Management & Priority Weights</h5>
                        <p class="text-muted small mt-1 mb-0">Configure which materials are tracked and their priority calculation weights.</p>
                        <div class="alert alert-info py-2 px-3 mt-2 mb-0 small">
                            <strong>Priority Score Formula (Scaled 1-100):</strong> <code>SUM( [Z-Score(Target) × Target Weight + Z-Score(Credits) × Credit Weight] × Overall Weight )</code>
                            <br><small class="fst-italic text-muted mt-1 d-block">* Tip: For mathematical purity, Target Weight + Credit Weight should equal 1.0.</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                        <i class="bi bi-plus-circle me-1"></i> Add Material
                    </button>
                </div>
                <div class="card-body p-0">
                    <form method="POST" id="materialsForm">
                        <div id="materialsError" class="alert alert-danger mx-4 mt-3 d-none"></div>
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Material Name</th>
                                    <th>Active</th>
                                    <th>Overall Weight</th>
                                    <th>Target Weight</th>
                                    <th>Credit Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $mat): ?>
                                <tr>
                                    <td class="ps-4 align-middle fw-semibold"><?= htmlspecialchars($mat['name']) ?></td>
                                    <td class="align-middle">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="materials[<?= $mat['id'] ?>][is_active]" <?= $mat['is_active'] ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" step="0.01" min="0" max="1" class="form-control form-control-sm w-75 fw-bold text-primary weight-overall" name="materials[<?= $mat['id'] ?>][overall_weight]" value="<?= htmlspecialchars($mat['overall_weight']) ?>">
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" step="0.01" min="0" max="1" class="form-control form-control-sm w-75 weight-target" name="materials[<?= $mat['id'] ?>][target_weight]" value="<?= htmlspecialchars($mat['target_weight']) ?>">
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" step="0.01" min="0" max="1" class="form-control form-control-sm w-75 weight-credit" name="materials[<?= $mat['id'] ?>][credit_weight]" value="<?= htmlspecialchars($mat['credit_weight']) ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="p-4 text-end border-top bg-light">
                            <button type="submit" class="btn btn-primary shadow-sm px-4"><i class="bi bi-save me-2"></i>Save Materials</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Material Name</label>
                            <input type="text" class="form-control" name="new_material_name" required placeholder="e.g., Manganese">
                        </div>
                        <input type="hidden" name="new_material" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('materialsForm').addEventListener('submit', function(e) {
            let overallSum = 0;
            let errorMsg = '';
            
            // Calculate sum of active overall weights
            document.querySelectorAll('.form-switch input[type="checkbox"]').forEach(function(checkbox) {
                if (checkbox.checked) {
                    const row = checkbox.closest('tr');
                    const overallInput = row.querySelector('.weight-overall');
                    const targetInput = row.querySelector('.weight-target');
                    const creditInput = row.querySelector('.weight-credit');
                    
                    overallSum += parseFloat(overallInput.value || 0);
                    
                    const rowSum = parseFloat(targetInput.value || 0) + parseFloat(creditInput.value || 0);
                    // allow a tiny margin of error for floating point
                    if (Math.abs(rowSum - 1.0) > 0.01) {
                        const matName = row.querySelector('td:first-child').innerText;
                        errorMsg += `Target + Credit weight for ${matName} must equal exactly 1.0.<br>`;
                    }
                }
            });
            
            if (Math.abs(overallSum - 1.0) > 0.01) {
                errorMsg += 'The sum of all active Overall Weights must equal exactly 1.0.';
            }
            
            if (errorMsg !== '') {
                e.preventDefault();
                const errorDiv = document.getElementById('materialsError');
                errorDiv.innerHTML = errorMsg;
                errorDiv.classList.remove('d-none');
            }
        });
    </script>
</body>
</html>
