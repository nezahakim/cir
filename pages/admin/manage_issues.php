<?php
/**
 * pages/admin/manage_issues.php
 * Full issue management table for admins.
 * Supports filtering by status, severity, and category.
 * Issues sorted by priority score descending.
 */

$pageTitle = 'Manage Issues';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('admin');

$db = getDB();

// ── Filter parameters ─────────────────────────────────────────
$filterStatus   = $_GET['status']      ?? '';
$filterSeverity = $_GET['severity']    ?? '';
$filterCategory = (int)($_GET['category'] ?? 0);
$search         = trim($_GET['search'] ?? '');

// ── Build query with optional filters ────────────────────────
$sql    = "SELECT i.id, i.title, c.category_name, c.id AS category_id,
                  u.full_name AS citizen_name, i.severity,
                  i.priority_score, i.status, i.created_at
           FROM issues i
           JOIN categories c ON i.category_id = c.id
           JOIN users u ON i.user_id = u.id
           WHERE 1=1";
$params = [];

if ($filterStatus) {
    $sql .= " AND i.status = :status";
    $params[':status'] = $filterStatus;
}
if ($filterSeverity) {
    $sql .= " AND i.severity = :severity";
    $params[':severity'] = $filterSeverity;
}
if ($filterCategory) {
    $sql .= " AND i.category_id = :category";
    $params[':category'] = $filterCategory;
}
if ($search) {
    $sql .= " AND (i.title LIKE :search OR u.full_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY i.priority_score DESC, i.created_at ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$issues = $stmt->fetchAll();

// Fetch categories for filter dropdown
$categories = $db->query("SELECT id, category_name FROM categories ORDER BY category_name")->fetchAll();

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
        <h1><i class="bi bi-list-task me-2 text-green"></i>Manage Issues</h1>
        <p>All reported community issues sorted by priority score.</p>
    </div>

    <!-- Filters -->
    <form method="GET" class="cir-card mb-4">
        <div class="cir-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-md-3">
                    <label class="cir-form-label">Search</label>
                    <input type="text" name="search" class="cir-form-control"
                           placeholder="Issue title or citizen name…"
                           value="<?= htmlspecialchars($search) ?>" />
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="cir-form-label">Status</label>
                    <select name="status" class="cir-form-control">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'In Progress', 'Resolved'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="cir-form-label">Severity</label>
                    <select name="severity" class="cir-form-control">
                        <option value="">All</option>
                        <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filterSeverity === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="cir-form-label">Category</label>
                    <select name="category" class="cir-form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn-cir-primary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="<?= BASE_URL ?>/pages/admin/manage_issues.php" class="btn-cir-outline">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Issues Table -->
    <div class="cir-card">
        <div class="cir-card-header">
            <h5><i class="bi bi-table me-2 text-green"></i>All Issues
                <span class="badge bg-secondary ms-2" style="font-size:0.75rem;"><?= count($issues) ?></span>
            </h5>
        </div>

        <?php if (empty($issues)): ?>
            <div class="cir-card-body text-center py-5">
                <i class="bi bi-search" style="font-size:3rem;color:#cbd5e1;"></i>
                <p class="mt-3 text-muted">No issues found for the selected filters.</p>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($issues as $issue): ?>
                        <tr>
                            <td style="color:#94a3b8;font-size:0.82rem;white-space:nowrap;">#<?= $issue['id'] ?></td>
                            <td class="fw-semibold" style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($issue['title']) ?>
                            </td>
                            <td style="white-space:nowrap;"><?= htmlspecialchars($issue['category_name']) ?></td>
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
                            <td style="white-space:nowrap;color:#64748b;font-size:0.82rem;">
                                <?= date('d M Y', strtotime($issue['created_at'])) ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="<?= BASE_URL ?>/pages/admin/view_issue.php?id=<?= $issue['id'] ?>"
                                   class="btn btn-sm btn-outline-success me-1" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/pages/admin/update_issue.php?id=<?= $issue['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Update status">
                                    <i class="bi bi-pencil-square"></i>
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
