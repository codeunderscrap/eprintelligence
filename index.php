<?php 
include 'config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login – EPR Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5" style="max-width: 500px;">
        <h2 class="mb-4 text-center">EPR Dashboard – Login</h2>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">Invalid username or password.</div>
        <?php endif; ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100">Log in</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
