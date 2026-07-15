<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        if (!empty($username) && !empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
                $stmt->execute([$username, $hash]);
                $message = "Employee account created successfully.";
            } catch (PDOException $e) {
                $message = "Error: Username might already exist.";
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($id !== $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $message = "User deleted successfully.";
        }
    }
}

$stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users – Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'inc/sidebar.php'; ?>
        <div class="main-content">
            <h2 class="fw-bold mb-4">Employee Management</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-info shadow-sm border-0 border-start border-4 border-info">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white pt-4 pb-3 border-bottom">
                            <h5 class="mb-0 text-primary"><i class="bi bi-person-plus me-2"></i>Add Employee</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Temporary Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 shadow-sm">Create Account</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white pt-4 pb-3 border-bottom">
                            <h5 class="mb-0 text-primary"><i class="bi bi-people me-2"></i>Active Accounts</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Employee</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <td>
                                            <?php if ($u['id'] !== $_SESSION['user_id'] && $u['role'] !== 'admin'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this employee?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Remove</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
