<?php
/**
 * pages/admin/update_issue.php
 * Admin updates the status of an issue and sends a notification to the citizen.
 * Logs the change in issue_updates table.
 */

$pageTitle = 'Update Issue Status';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('admin');

$db      = getDB();
$adminId = $_SESSION['user_id'];
$issueId = (int) ($_GET['id'] ?? 0);

if (!$issueId) {
    header('Location: ' . BASE_URL . '/pages/admin/manage_issues.php');
    exit;
}

// Fetch the issue
$stmt = $db->prepare(
    "SELECT i.*, c.category_name, u.full_name AS citizen_name, u.id AS citizen_id
     FROM issues i
     JOIN categories c ON i.category_id = c.id
     JOIN users u ON i.user_id = u.id
     WHERE i.id = ?"
);
$stmt->execute([$issueId]);
$issue = $stmt->fetch();

if (!$issue) {
    header('Location: ' . BASE_URL . '/pages/admin/manage_issues.php');
    exit;
}

$error   = '';
$success = '';

// ── Handle form submission ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus     = trim($_POST['status']         ?? '');
    $updateMessage = trim($_POST['update_message'] ?? '');

    $validStatuses = ['Pending', 'In Progress', 'Resolved'];

    if (!in_array($newStatus, $validStatuses)) {
        $error = 'Invalid status selected.';
    } else {
        $oldStatus = $issue['status'];

        // 1. Update the issue status in the issues table
        $updateIssue = $db->prepare(
            "UPDATE issues SET status = ?, updated_at = NOW() WHERE id = ?"
        );
        $updateIssue->execute([$newStatus, $issueId]);

        // 2. Log the status change in issue_updates
        $logUpdate = $db->prepare(
            "INSERT INTO issue_updates (issue_id, admin_id, update_message, old_status, new_status)
             VALUES (?, ?, ?, ?, ?)"
        );
        $logUpdate->execute([$issueId, $adminId, $updateMessage, $oldStatus, $newStatus]);

        // 3. Create a notification for the citizen
        $notifMessage = "Your issue \"" . $issue['title'] . "\" has been updated to: $newStatus.";
        if ($updateMessage) {
            $notifMessage .= " Admin note: $updateMessage";
        }

        $notif = $db->prepare(
            "INSERT INTO notifications (user_id, issue_id, message) VALUES (?, ?, ?)"
        );
        $notif->execute([$issue['citizen_id'], $issueId, $notifMessage]);

        $success = 'Issue status updated successfully and citizen has been notified.';

        // Refresh issue data
        $stmt->execute([$issueId]);
        $issue = $stmt->fetch();
    }
}

function statusBadgeClass(string $s): string {
    return match($s) {
        'Pending'     => 'badge-pending',
        'In Progress' => 'badge-in-progress',
        'Resolved'    => 'badge-resolved',
        default       => 'bg-secondary',
    };
}
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="text-green">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/manage_issues.php" class="text-green">Manage Issues</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/view_issue.php?id=<?= $issueId ?>" class="text-green">#<?= $issueId ?></a></li>
            <li class="breadcrumb-item active">Update Status</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1><i class="bi bi-pencil-square me-2 text-green"></i>Update Issue Status</h1>
        <p>Change the status of this issue and notify the reporting citizen.</p>
    </div>

    <?php if ($error): ?>
        <div class="cir-alert cir-alert-danger auto-dismiss"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="cir-alert cir-alert-success auto-dismiss">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            <a href="<?= BASE_URL ?>/pages/admin/manage_issues.php" class="fw-bold ms-2">Back to Issues →</a>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Update Form -->
        <div class="col-lg-7">
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-arrow-repeat me-2 text-green"></i>Status Update Form</h5></div>
                <div class="cir-card-body">
                    <form method="POST" action="">

                        <!-- Current status display -->
                        <div class="form-row">
                            <label class="cir-form-label">Current Status</label>
                            <div class="d-flex align-items-center gap-3" style="padding:10px 0;">
                                <span class="badge-status <?= statusBadgeClass($issue['status']) ?>" style="font-size:0.9rem;padding:8px 18px;">
                                    <?= $issue['status'] ?>
                                </span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span style="font-size:0.85rem;color:#94a3b8;">Select new status below</span>
                            </div>
                        </div>

                        <!-- New status selector as styled cards -->
                        <div class="form-row">
                            <label class="cir-form-label">New Status <span class="text-danger">*</span></label>
                            <div class="row g-2">

                                <?php
                                $statusOptions = [
                                    ['value' => 'Pending',     'icon' => 'bi-hourglass-split', 'color' => '#d97706', 'bg' => '#fef9c3', 'desc' => 'Issue not yet acted upon'],
                                    ['value' => 'In Progress', 'icon' => 'bi-arrow-repeat',    'color' => '#1d4ed8', 'bg' => '#dbeafe', 'desc' => 'Being actively addressed'],
                                    ['value' => 'Resolved',    'icon' => 'bi-check-circle',    'color' => '#16a34a', 'bg' => '#dcfce7', 'desc' => 'Issue has been resolved'],
                                ];
                                foreach ($statusOptions as $opt):
                                    $isSelected = $issue['status'] === $opt['value'];
                                ?>
                                <div class="col-12">
                                    <label class="status-radio-label" style="
                                        display:flex;align-items:center;gap:14px;
                                        padding:14px 16px;border-radius:var(--radius-sm);
                                        border:2px solid <?= $isSelected ? $opt['color'] : '#e2e8f0' ?>;
                                        background:<?= $isSelected ? $opt['bg'] : '#fff' ?>;
                                        cursor:pointer;transition:all .15s ease;">
                                        <input type="radio" name="status" value="<?= $opt['value'] ?>"
                                               <?= $isSelected ? 'checked' : '' ?>
                                               style="accent-color:<?= $opt['color'] ?>;width:18px;height:18px;" />
                                        <i class="<?= $opt['icon'] ?>" style="font-size:1.2rem;color:<?= $opt['color'] ?>;"></i>
                                        <div>
                                            <p class="mb-0 fw-semibold" style="font-size:0.9rem;color:#1e293b;"><?= $opt['value'] ?></p>
                                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;"><?= $opt['desc'] ?></p>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-row mt-3">
                            <label class="cir-form-label">Update Message / Note (optional)</label>
                            <textarea name="update_message" class="cir-form-control" rows="4"
                                      placeholder="Add a message to inform the citizen about what is being done or what was done…"></textarea>
                            <p style="font-size:0.78rem;color:#94a3b8;margin-top:6px;">
                                <i class="bi bi-info-circle me-1"></i>
                                This message will be included in the citizen's notification.
                            </p>
                        </div>

                        <div class="d-flex gap-3 mt-2">
                            <button type="submit" class="btn-cir-primary" style="padding:13px 28px;">
                                <i class="bi bi-save"></i> Save Update & Notify Citizen
                            </button>
                            <a href="<?= BASE_URL ?>/pages/admin/view_issue.php?id=<?= $issueId ?>"
                               class="btn-cir-outline" style="padding:12px 22px;">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- Issue Summary -->
        <div class="col-lg-5">
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-info-circle me-2 text-green"></i>Issue Summary</h5></div>
                <div class="cir-card-body">
                    <h5 class="fw-bold text-navy mb-2"><?= htmlspecialchars($issue['title']) ?></h5>
                    <p style="font-size:0.88rem;color:#64748b;line-height:1.7;">
                        <?= htmlspecialchars(substr($issue['description'], 0, 200)) ?>
                        <?= strlen($issue['description']) > 200 ? '…' : '' ?>
                    </p>
                    <hr style="border-color:#f1f5f9;" />
                    <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
                        <?php
                        $rows = [
                            ['Category',    $issue['category_name']],
                            ['Severity',    $issue['severity']],
                            ['Priority',    number_format($issue['priority_score'], 2)],
                            ['Reported by', $issue['citizen_name']],
                            ['Date',        date('d M Y', strtotime($issue['created_at']))],
                        ];
                        foreach ($rows as [$label, $value]):
                        ?>
                        <tr>
                            <td style="padding:7px 0;color:#94a3b8;font-weight:600;"><?= $label ?></td>
                            <td style="padding:7px 0 7px 12px;color:#334155;font-weight:500;"><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- Notification preview -->
            <div class="cir-card mt-4">
                <div class="cir-card-header"><h5><i class="bi bi-bell me-2 text-green"></i>Notification Preview</h5></div>
                <div class="cir-card-body">
                    <div class="notif-item" style="margin:0;border:none;padding:12px 0;">
                        <div class="notif-icon"><i class="bi bi-bell-fill"></i></div>
                        <div>
                            <p class="mb-1 fw-semibold" style="font-size:0.88rem;color:var(--cir-navy);">
                                <?= htmlspecialchars($issue['title']) ?>
                            </p>
                            <p class="mb-0" style="font-size:0.83rem;color:#64748b;">
                                "Your issue has been updated to: <strong>[selected status]</strong>."
                            </p>
                        </div>
                    </div>
                    <p style="font-size:0.78rem;color:#94a3b8;margin-top:8px;">
                        <i class="bi bi-person-check me-1"></i>
                        Will be sent to: <?= htmlspecialchars($issue['citizen_name']) ?>
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>
</div>

<!-- Highlight radio labels on selection -->
<script>
document.querySelectorAll('input[name="status"]').forEach(function(radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.status-radio-label').forEach(function(label) {
            label.style.borderColor = '#e2e8f0';
            label.style.background  = '#fff';
        });
        const selectedLabel = this.closest('.status-radio-label');
        const colors = { 'Pending': ['#d97706','#fef9c3'], 'In Progress': ['#1d4ed8','#dbeafe'], 'Resolved': ['#16a34a','#dcfce7'] };
        const [borderColor, bg] = colors[this.value] || ['#20a558','#e8f7f0'];
        selectedLabel.style.borderColor = borderColor;
        selectedLabel.style.background  = bg;
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
