<?php
/**
 * pages/citizen/notifications.php
 * Shows all notifications for the logged-in citizen.
 * Marks all notifications as read when the page is opened.
 */

$pageTitle = 'Notifications';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('citizen');

$db     = getDB();
$userId = $_SESSION['user_id'];

// Mark all unread notifications as read
$db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
   ->execute([$userId]);

// Reset the unread count in the navbar after marking as read
$unreadCount = 0;

// Fetch all notifications newest first
$stmt = $db->prepare(
    "SELECT n.*, i.title AS issue_title
     FROM notifications n
     JOIN issues i ON n.issue_id = i.id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC"
);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <div class="page-header">
        <h1><i class="bi bi-bell-fill me-2 text-green"></i>Notifications</h1>
        <p>Updates on your reported issues from the CIR administration team.</p>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="cir-card">
            <div class="cir-card-body text-center py-5">
                <i class="bi bi-bell-slash" style="font-size:3.5rem;color:#cbd5e1;"></i>
                <p class="mt-3 text-muted">No notifications yet.</p>
                <p style="font-size:0.85rem;color:#94a3b8;">
                    You'll be notified here when an admin updates the status of your reported issues.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="cir-card">
            <div class="cir-card-header">
                <h5><i class="bi bi-list-ul me-2 text-green"></i>All Notifications
                    <span class="badge bg-secondary ms-2" style="font-size:0.75rem;"><?= count($notifications) ?></span>
                </h5>
            </div>
            <div class="cir-card-body" style="padding:16px;">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>">
                        <div class="notif-icon">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-1 fw-semibold" style="font-size:0.9rem;color:var(--cir-navy);">
                                <?= htmlspecialchars($notif['issue_title']) ?>
                            </p>
                            <p class="mb-1" style="font-size:0.88rem;color:#374151;">
                                <?= htmlspecialchars($notif['message']) ?>
                            </p>
                            <span style="font-size:0.78rem;color:#94a3b8;">
                                <i class="bi bi-clock me-1"></i>
                                <?= date('d M Y, H:i', strtotime($notif['created_at'])) ?>
                            </span>
                        </div>
                        <div>
                            <a href="<?= BASE_URL ?>/pages/citizen/view_issue.php?id=<?= $notif['issue_id'] ?>"
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
