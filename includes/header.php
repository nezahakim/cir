<?php
/**
 * includes/header.php
 * Common header included on every page.
 * Starts the session, defines helper functions, and outputs HTML <head>.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

// ─── Auth Helper Functions ───────────────────────────────────────────────────

/**
 * Checks if the current user is logged in.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user has a specific role.
 */
function hasRole(string $role): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Redirects to login if not authenticated.
 * Optionally enforces a specific role.
 */
function requireLogin(string $role = ''): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit;
    }
    if ($role && !hasRole($role)) {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit;
    }
}

/**
 * Returns the count of unread notifications for the logged-in citizen.
 */
function getUnreadNotificationCount(): int {
    if (!isLoggedIn() || !hasRole('citizen')) return 0;
    $db  = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}

// ─── Base URL (adjust if project folder name differs) ────────────────────────
define('BASE_URL', '');

// ─── Page title — can be overridden before including this file ───────────────
$pageTitle = $pageTitle ?? 'Community Issue Reporter';
$unreadCount = isLoggedIn() ? getUnreadNotificationCount() : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?> — CIR Rwanda</title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <!-- Leaflet.js CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Google Font: Sora + DM Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />
    <!-- Custom CIR styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css" />
</head>
<body>
