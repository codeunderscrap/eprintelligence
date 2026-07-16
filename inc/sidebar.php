<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="bi bi-box-seam me-2"></i> Mini Mines</h4>
        <small class="text-muted">EPR Intelligence</small>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?= $currentPage == 'dashboard.php' || $currentPage == 'company.php' ? 'active' : '' ?>">
                <i class="bi bi-grid"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="upload.php" class="<?= $currentPage == 'upload.php' ? 'active' : '' ?>">
                <i class="bi bi-cloud-arrow-up"></i> Upload Dataset
            </a>
        </li>
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li>
            <a href="users.php" class="<?= $currentPage == 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Manage Users
            </a>
        </li>
        <li>
            <a href="settings.php" class="<?= $currentPage == 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="btn btn-outline-danger w-100 text-start">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</div>
