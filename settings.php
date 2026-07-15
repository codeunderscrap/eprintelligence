<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE api_keys SET api_key = ? WHERE service_name = ?");
    foreach ($_POST['api'] as $service => $key) {
        if (!empty(trim($key)) && strpos($key, '****') === false) {
            $stmt->execute([trim($key), $service]);
        }
    }
    $message = "API Keys updated successfully!";
}

$stmt = $pdo->query("SELECT * FROM api_keys");
$apiKeys = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $apiKeys[$row['service_name']] = $row;
}
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
        </div>
    </div>
</body>
</html>
