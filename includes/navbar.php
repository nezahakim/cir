<?php
/**
 * includes/navbar.php
 * Renders the top navbar and sidebar navigation.
 * Adapts links based on the logged-in user's role (citizen vs admin).
 */
$role = $_SESSION['role'] ?? '';
$userName = $_SESSION['full_name'] ?? 'User';
?>

<!-- ── Top Navbar ─────────────────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg cir-navbar" id="mainNavbar">
    <div class="container-fluid px-4">

        <!-- Sidebar toggle (mobile) -->
        <button class="btn btn-link sidebar-toggle me-3 d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-4 text-white"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/">
            <span class="brand-icon"><i class="bi bi-geo-alt-fill"></i></span>
            <span class="brand-text">CIR <span class="brand-sub">Rwanda</span></span>
        </a>

        <div class="ms-auto d-flex align-items-center gap-3">

            <?php if ($role === 'citizen'): ?>
            <!-- Notification Bell -->
            <a href="<?= BASE_URL ?>/pages/citizen/notifications.php" class="btn btn-link position-relative nav-icon-btn" title="Notifications">
                <i class="bi bi-bell-fill text-white fs-5"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                        <?= $unreadCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <!-- User Dropdown -->
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle nav-user-btn d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <div class="user-avatar-sm">
                        <?= strtoupper(substr($userName, 0, 1)) ?>
                    </div>
                    <span class="text-white d-none d-sm-inline"><?= htmlspecialchars(explode(' ', $userName)[0]) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <li><span class="dropdown-item-text fw-semibold"><?= htmlspecialchars($userName) ?></span></li>
                    <li><hr class="dropdown-divider" /></li>
                    <?php if ($role === 'citizen'): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/citizen/dashboard.php"><i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/dashboard.php"><i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/auth/logout.php"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- ── Sidebar ────────────────────────────────────────────────────────────── -->
<div class="cir-sidebar" id="cirSidebar">
    <div class="sidebar-header">
        <div class="user-avatar-lg">
            <?= strtoupper(substr($userName, 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
            <p class="sidebar-username"><?= htmlspecialchars($userName) ?></p>
            <span class="sidebar-role-badge <?= $role ?>-badge">
                <?= ucfirst($role) ?>
            </span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'citizen'): ?>
            <a href="<?= BASE_URL ?>/pages/citizen/dashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/pages/citizen/report_issue.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'report_issue.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i> Report Issue
            </a>
            <a href="<?= BASE_URL ?>/pages/citizen/my_issues.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'my_issues.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-check"></i> My Issues
            </a>
            <a href="<?= BASE_URL ?>/pages/map/map_view.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'map_view.php' ? 'active' : '' ?>">
                <i class="bi bi-map"></i> View Map
            </a>
            <a href="<?= BASE_URL ?>/pages/citizen/notifications.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger ms-auto rounded-pill"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>

        <?php elseif ($role === 'admin'): ?>
            <a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/manage_issues.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'manage_issues.php' ? 'active' : '' ?>">
                <i class="bi bi-list-task"></i> Manage Issues
            </a>
            <a href="<?= BASE_URL ?>/pages/map/map_view.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'map_view.php' ? 'active' : '' ?>">
                <i class="bi bi-map"></i> GIS Map
            </a>
        <?php endif; ?>

        <div class="sidebar-divider"></div>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="sidebar-link logout-link">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>
