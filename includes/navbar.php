<?php
/**
 * includes/navbar.php
 * Top navbar + sidebar. Adapts to citizen vs admin role.
 * Includes profile and reports navigation.
 */

require_once __DIR__ . '/profile_helper.php'; // avatarHtml()

$role     = $_SESSION['role']      ?? '';
$userName = $_SESSION['full_name'] ?? 'User';

// Fetch current user's profile image for nav avatar
$_navUser = null;
if (isLoggedIn()) {
    $_navDb   = getDB();
    $_navStmt = $_navDb->prepare("SELECT profile_image FROM users WHERE id = ?");
    $_navStmt->execute([$_SESSION['user_id']]);
    $_navUser = $_navStmt->fetch();
}
$_navProfileImage = $_navUser['profile_image'] ?? null;
?>

<!-- ── Top Navbar ──────────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg cir-navbar" id="mainNavbar">
    <div class="container-fluid px-4">

        <button class="btn btn-link sidebar-toggle me-3 d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-4 text-white"></i>
        </button>

        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/">
            <span class="brand-icon"><i class="bi bi-geo-alt-fill"></i></span>
            <span class="brand-text">CIR <span class="brand-sub">Rwanda</span></span>
        </a>

        <div class="ms-auto d-flex align-items-center gap-3">

            <?php if ($role === 'citizen'): ?>
            <a href="<?= BASE_URL ?>/pages/citizen/notifications.php"
               class="btn btn-link position-relative nav-icon-btn" title="Notifications">
                <i class="bi bi-bell-fill text-white fs-5"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                        <?= $unreadCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <!-- User dropdown with profile image -->
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle nav-user-btn d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <?php if ($_navProfileImage): ?>
                        <img src="<?= BASE_URL . '/' . htmlspecialchars($_navProfileImage) ?>"
                             alt="Profile"
                             style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.4);" />
                    <?php else: ?>
                        <div class="user-avatar-sm"><?= strtoupper(substr($userName, 0, 1)) ?></div>
                    <?php endif; ?>
                    <span class="text-white d-none d-sm-inline"><?= htmlspecialchars(explode(' ', $userName)[0]) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <li><span class="dropdown-item-text fw-semibold"><?= htmlspecialchars($userName) ?></span></li>
                    <li><hr class="dropdown-divider" /></li>
                    <?php if ($role === 'citizen'): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/citizen/dashboard.php">
                            <i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/citizen/profile.php">
                            <i class="bi bi-person-circle me-2 text-success"></i>My Profile</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/dashboard.php">
                            <i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/admin/profile.php">
                            <i class="bi bi-person-circle me-2 text-success"></i>My Profile</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/pages/auth/logout.php">
                        <i class="bi bi-box-arrow-right me-2 text-danger"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- ── Sidebar ─────────────────────────────────────────────────────── -->
<div class="cir-sidebar" id="cirSidebar">
    <div class="sidebar-header">
        <!-- Profile image or initials avatar in sidebar -->
        <div style="width:50px;height:50px;flex-shrink:0;">
            <?php if ($_navProfileImage): ?>
                <img src="<?= BASE_URL . '/' . htmlspecialchars($_navProfileImage) ?>"
                     alt="Profile"
                     style="width:50px;height:50px;border-radius:14px;object-fit:cover;border:2px solid rgba(255,255,255,.25);" />
            <?php else: ?>
                <div class="user-avatar-lg"><?= strtoupper(substr($userName, 0, 1)) ?></div>
            <?php endif; ?>
        </div>
        <div class="sidebar-user-info">
            <p class="sidebar-username"><?= htmlspecialchars($userName) ?></p>
            <span class="sidebar-role-badge <?= $role ?>-badge"><?= ucfirst($role) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'citizen'): ?>
            <a href="<?= BASE_URL ?>/pages/citizen/dashboard.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/pages/citizen/report_issue.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'report_issue.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i> Report Issue
            </a>
            <a href="<?= BASE_URL ?>/pages/citizen/my_issues.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'my_issues.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-check"></i> My Issues
            </a>
            <a href="<?= BASE_URL ?>/pages/map/map_view.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'map_view.php' ? 'active' : '' ?>">
                <i class="bi bi-map"></i> View Map
            </a>
            <a href="<?= BASE_URL ?>/pages/citizen/notifications.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger ms-auto rounded-pill"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>

            <div class="sidebar-divider"></div>

            <a href="<?= BASE_URL ?>/pages/citizen/profile.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> My Profile
            </a>

        <?php elseif ($role === 'admin'): ?>
            <a href="<?= BASE_URL ?>/pages/admin/dashboard.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/manage_issues.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'manage_issues.php' ? 'active' : '' ?>">
                <i class="bi bi-list-task"></i> Manage Issues
            </a>
            <a href="<?= BASE_URL ?>/pages/map/map_view.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'map_view.php' ? 'active' : '' ?>">
                <i class="bi bi-map"></i> GIS Map
            </a>
            <a href="<?= BASE_URL ?>/pages/admin/reports.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Reports
            </a>

            <div class="sidebar-divider"></div>

            <a href="<?= BASE_URL ?>/pages/admin/profile.php"
               class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> My Profile
            </a>
        <?php endif; ?>

        <div class="sidebar-divider"></div>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="sidebar-link logout-link">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>