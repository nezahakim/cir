<?php
/**
 * pages/admin/dashboard.php
 * Admin dashboard showing all issue stats and a sorted issues table.
 * Issues sorted by priority score (highest first).
 */ 

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('admin');

$db = getDB();

// ── Fetch overall stats ───────────────────────────────────────
$stats = $db->query(
    "SELECT
        COUNT(*)                                         AS total,
        SUM(status = 'Pending')                         AS pending,
        SUM(status = 'In Progress')                     AS in_progress,
        SUM(status = 'Resolved')                        AS resolved,
        SUM(severity IN ('High','Critical'))            AS `high_priority`
     FROM issues"
)->fetch();

// ── Fetch top 10 highest-priority issues ─────────────────────
$topIssues = $db->query(
    "SELECT i.id, i.title, c.category_name, u.full_name AS citizen_name,
            i.severity, i.priority_score, i.status, i.created_at
     FROM issues i
     JOIN categories c ON i.category_id = c.id
     JOIN users u ON i.user_id = u.id
     ORDER BY i.priority_score DESC, i.created_at ASC
     LIMIT 10"
)->fetchAll();

function statusBadgeClass(string $s): string {
    return match($s) {
        'Pending'     => 'badge-pending',
        'In Progress' => 'badge-in-progress',
        'Resolved'    => 'badge-resolved',
        default       => 'bg-secondary',
    };
}
function priorityClass(float $score): string {
    if ($score >= 4.0) return 'priority-high';
    if ($score >= 2.5) return 'priority-medium';
    return 'priority-low';
}
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <div class="page-header">
        <h1>Admin Dashboard</h1>
        <p>Overview of all community issues across Rwanda. Sorted by priority score.</p>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-collection"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['total'] ?></div>
                    <div class="stat-label">Total Issues</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-arrow-repeat"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['in_progress'] ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['resolved'] ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl">
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-value"><?= (int) $stats['high_priority'] ?></div>
                    <div class="stat-label">High Priority</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Priority Issues Table -->
    <div class="cir-card">
        <div class="cir-card-header">
            <h5><i class="bi bi-sort-down me-2 text-green"></i>Top Priority Issues</h5>
            <a href="<?= BASE_URL ?>/pages/admin/manage_issues.php" class="btn-cir-outline" style="padding:6px 14px;font-size:0.82rem;">
                Manage All
            </a>
        </div>

        <?php if (empty($topIssues)): ?>
            <div class="cir-card-body text-center py-5">
                <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                <p class="mt-3 text-muted">No issues reported yet.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="cir-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Citizen</th>
                            <th>Severity</th>
                            <th>Priority Score</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topIssues as $issue): ?>
                        <tr>
                            <td style="color:#94a3b8;font-size:0.82rem;">#<?= $issue['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($issue['title']) ?></td>
                            <td><?= htmlspecialchars($issue['category_name']) ?></td>
                            <td><?= htmlspecialchars($issue['citizen_name']) ?></td>
                            <td>
                                <span class="badge-severity badge-<?= strtolower($issue['severity']) ?>">
                                    <?= $issue['severity'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-pill <?= priorityClass((float)$issue['priority_score']) ?>">
                                    <i class="bi bi-graph-up"></i>
                                    <?= number_format($issue['priority_score'], 2) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?= statusBadgeClass($issue['status']) ?>">
                                    <?= $issue['status'] ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;color:#64748b;font-size:0.83rem;">
                                <?= date('d M Y', strtotime($issue['created_at'])) ?>
                            </td>
                            <td class="d-flex gap-1">
                                <a href="<?= BASE_URL ?>/pages/admin/view_issue.php?id=<?= $issue['id'] ?>"
                                   class="btn btn-sm btn-outline-success" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/pages/admin/update_issue.php?id=<?= $issue['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Update status">
                                    <i class="bi bi-pencil"></i>
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
