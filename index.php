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
    <title>Login – MiniMines Sourcing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        .split-layout {
            display: flex;
            min-height: 100vh;
        }
        .left-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            padding: 2rem;
        }
        .right-panel {
            flex: 1;
            background-color: #0690ad; /* MiniMines Primary Color */
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .right-panel::after {
            content: '';
            position: absolute;
            top: -10%;
            right: -10%;
            width: 50%;
            height: 50%;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .right-panel::before {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -10%;
            width: 70%;
            height: 70%;
            background: rgba(0,27,46,0.2); /* MiniMines Secondary Color */
            border-radius: 50%;
        }
        .login-box {
            width: 100%;
            max-width: 400px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-container img {
            max-width: 250px;
            height: auto;
        }
        .form-control {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(6,144,173,0.25);
            border-color: #0690ad;
        }
        .btn-primary {
            background-color: #0690ad;
            border-color: #0690ad;
            padding: 0.8rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #057a94;
            border-color: #057a94;
        }
        .brand-text h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 10;
        }
        .brand-text p {
            font-size: 1.25rem;
            opacity: 0.9;
            position: relative;
            z-index: 10;
        }
        @media (max-width: 768px) {
            .split-layout {
                flex-direction: column;
            }
            .right-panel {
                display: none; /* Hide branding panel on mobile */
            }
        }
    </style>
</head>
<body>
    <div class="split-layout">
        <!-- Left Panel: Login Form -->
        <div class="left-panel">
            <div class="login-box">
                <div class="logo-container">
                    <img src="assets/logo.png" alt="MiniMines Logo">
                </div>
                
                <h4 class="mb-1 fw-bold text-center" style="color: #001b2e;">Welcome Back</h4>
                <p class="text-muted text-center mb-4">Log in to the Sourcing Agent Console</p>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger rounded-3 border-0 border-start border-4 border-danger shadow-sm">
                        <small>Invalid username or password.</small>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Username</label>
                        <input type="text" name="username" class="form-control bg-light" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Password</label>
                        <input type="password" name="password" class="form-control bg-light" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 shadow-sm">Log in</button>
                </form>
            </div>
        </div>

        <!-- Right Panel: Branding -->
        <div class="right-panel">
            <div class="brand-text text-center px-4">
                <h1>Extracting What Matters</h1>
                <p>AI-Powered Sourcing & Closed-Loop Analytics</p>
            </div>
        </div>
    </div>
</body>
</html>
