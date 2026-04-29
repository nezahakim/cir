<?php
/**
 * pages/citizen/dashboard.php
 * Citizen dashboard showing summary stats and recent issues.
 */

$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../../includes/header.php';

// Ensure only logged-in citizens can access this page
requireLogin('citizen');

$db     = getDB();
$userId = $_SESSION['user_id'];

// ── Fetch summary counts for the logged-in citizen ─────────────
$stmt = $db->prepare(
    "SELECT
        COUNT(*)                                        AS total,
        SUM(status = 'Pending')                        AS pending,
        SUM(status = 'In Progress')                    AS in_progress,
        SUM(status = 'Resolved')                       AS resolved
     FROM issues
     WHERE user_id = ?"
);
$stmt->execute([$userId]);
$stats = $stmt->fetch();

// ── Fetch 5 most recent issues by this citizen ──────────────────
$recent = $db->prepare(
    "SELECT i.id, i.title, c.category_name, i.severity, i.status, i.priority_score, i.created_at
     FROM issues i
     JOIN categories c ON i.category_id = c.id
     WHERE i.user_id = ?
     ORDER BY i.created_at DESC
     LIMIT 5"
);
$recent->execute([$userId]);
$recentIssues = $recent->fetchAll();

/**
 * Returns the appropriate CSS class for a status badge.
 */
function statusBadgeClass(string $status): string {
    return match($status) {
        'Pending'     => 'badge-pending',
        'In Progress' => 'badge-in-progress',
        'Resolved'    => 'badge-resolved',
        default       => 'bg-secondary',
    };
}

/**
 * Returns the CSS class for a priority score pill.
 */
function priorityClass(float $score): string {
    if ($score >= 4.0) return 'priority-high';
    if ($score >= 2.5) return 'priority-medium';
    return 'priority-low';
}
?>

<!-- Navbar + Sidebar -->
<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <h1>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?> 👋</h1>
        <p>Here's an overview of your reported community issues.</p>
    </div>

    <!-- Stat Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-clipboard2-data"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['total'] ?></div>
                    <div class="stat-label">Total Submitted</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-arrow-repeat"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['in_progress'] ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['resolved'] ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Issues Table -->
        <div class="col-lg-8">
            <div class="cir-card">
                <div class="cir-card-header">
                    <h5><i class="bi bi-clock-history me-2 text-green"></i>Recent Issues</h5>
                    <a href="<?= BASE_URL ?>/pages/citizen/my_issues.php" class="btn-cir-outline" style="padding:6px 14px;font-size:0.82rem;">
                        View All
                    </a>
                </div>

                <?php if (empty($recentIssues)): ?>
                    <div class="cir-card-body text-center py-5">
                        <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                        <p class="mt-3 text-muted">No issues reported yet.</p>
                        <a href="<?= BASE_URL ?>/pages/citizen/report_issue.php" class="btn-cir-primary mt-2">
                            <i class="bi bi-plus-circle"></i> Report Your First Issue
                        </a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="cir-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentIssues as $issue): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($issue['title']) ?></td>
                                    <td><?= htmlspecialchars($issue['category_name']) ?></td>
                                    <td>
                                        <span class="badge-severity badge-<?= strtolower($issue['severity']) ?>">
                                            <?= $issue['severity'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= statusBadgeClass($issue['status']) ?>">
                                            <?= $issue['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-pill <?= priorityClass((float)$issue['priority_score']) ?>">
                                            <?= number_format($issue['priority_score'], 2) ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;color:#64748b;font-size:0.83rem;">
                                        <?= date('d M Y', strtotime($issue['created_at'])) ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/pages/citizen/view_issue.php?id=<?= $issue['id'] ?>"
                                           class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-lightning-fill me-2 text-green"></i>Quick Actions</h5></div>
                <div class="cir-card-body d-flex flex-column gap-3">
                    <a href="<?= BASE_URL ?>/pages/citizen/report_issue.php" class="btn-cir-primary justify-content-center" style="padding:13px;">
                        <i class="bi bi-plus-circle-fill"></i> Report a New Issue
                    </a>
                    <a href="<?= BASE_URL ?>/pages/citizen/my_issues.php" class="btn-cir-outline justify-content-center" style="padding:12px;">
                        <i class="bi bi-clipboard2-check"></i> View My Issues
                    </a>
                    <a href="<?= BASE_URL ?>/pages/map/map_view.php" class="btn-cir-outline justify-content-center" style="padding:12px;">
                        <i class="bi bi-map"></i> Open GIS Map
                    </a>
                    <a href="<?= BASE_URL ?>/pages/citizen/notifications.php" class="btn-cir-outline justify-content-center" style="padding:12px;position:relative;">
                        <i class="bi bi-bell"></i> Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-2"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
