<?php
/**
 * pages/citizen/my_issues.php
 * Shows all issues submitted by the logged-in citizen.
 * Supports filtering by status and severity.
 */

$pageTitle = 'My Issues';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('citizen');
 
$db     = getDB();
$userId = $_SESSION['user_id'];

// ── Filter parameters from GET ────────────────────────────────
$filterStatus   = $_GET['status']   ?? '';
$filterSeverity = $_GET['severity'] ?? '';

// ── Build dynamic query ───────────────────────────────────────
$sql    = "SELECT i.id, i.title, c.category_name, i.severity, i.status,
                  i.priority_score, i.created_at, i.location_name
           FROM issues i
           JOIN categories c ON i.category_id = c.id
           WHERE i.user_id = :uid";
$params = [':uid' => $userId];

if ($filterStatus) {
    $sql .= " AND i.status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterSeverity) {
    $sql .= " AND i.severity = :severity";
    $params[':severity'] = $filterSeverity;
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();

// Status badge helper (same as dashboard)
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

    <div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1><i class="bi bi-clipboard2-check me-2 text-green"></i>My Issues</h1>
            <p>All community issues you have submitted.</p>
        </div>
        <a href="<?= BASE_URL ?>/pages/citizen/report_issue.php" class="btn-cir-primary">
            <i class="bi bi-plus-circle"></i> Report New Issue
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="cir-card mb-4">
        <div class="cir-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-sm-4">
                    <label class="cir-form-label">Filter by Status</label>
                    <select name="status" class="cir-form-control">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'In Progress', 'Resolved'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="cir-form-label">Filter by Severity</label>
                    <select name="severity" class="cir-form-control">
                        <option value="">All Severities</option>
                        <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filterSeverity === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-4 d-flex gap-2">
                    <button type="submit" class="btn-cir-primary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="<?= BASE_URL ?>/pages/citizen/my_issues.php" class="btn-cir-outline">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Issues Table -->
    <div class="cir-card">
        <div class="cir-card-header">
            <h5><i class="bi bi-list-ul me-2 text-green"></i>Issues
                <span class="badge bg-secondary ms-2" style="font-size:0.75rem;"><?= count($issues) ?></span>
            </h5>
        </div>

        <?php if (empty($issues)): ?>
            <div class="cir-card-body text-center py-5">
                <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                <p class="mt-3 text-muted">No issues found<?= $filterStatus || $filterSeverity ? ' for selected filters' : '' ?>.</p>
                <?php if (!$filterStatus && !$filterSeverity): ?>
                    <a href="<?= BASE_URL ?>/pages/citizen/report_issue.php" class="btn-cir-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Report Your First Issue
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="cir-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Priority Score</th>
                            <th>Location</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($issues as $i => $issue): ?>
                        <tr>
                            <td style="color:#94a3b8;font-size:0.82rem;"><?= $i + 1 ?></td>
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
                                    <i class="bi bi-graph-up"></i>
                                    <?= number_format($issue['priority_score'], 2) ?>
                                </span>
                            </td>
                            <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#64748b;font-size:0.83rem;">
                                <?= $issue['location_name'] ? htmlspecialchars($issue['location_name']) : '—' ?>
                            </td>
                            <td style="white-space:nowrap;color:#64748b;font-size:0.83rem;">
                                <?= date('d M Y', strtotime($issue['created_at'])) ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/pages/citizen/view_issue.php?id=<?= $issue['id'] ?>"
                                   class="btn btn-sm btn-outline-success" title="View details">
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
