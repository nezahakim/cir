<?php
$pageTitle = 'Flag Issue for Correction';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('admin');

$db      = getDB();
$adminId = $_SESSION['user_id'];
$issueId = (int) ($_GET['id'] ?? 0);

if (!$issueId) {
    header('Location: ' . BASE_URL . '/pages/admin/manage_issues.php');
    exit;
}

// Fetch issue; only Pending issues can be flagged (not already acted on)
$stmt = $db->prepare(
    "SELECT i.*, u.full_name AS citizen_name, u.id AS citizen_id, c.category_name
     FROM issues i
     JOIN users u ON i.user_id = u.id
     JOIN categories c ON i.category_id = c.id
     WHERE i.id = ?"
);
$stmt->execute([$issueId]);
$issue = $stmt->fetch();

if (!$issue) {
    header('Location: ' . BASE_URL . '/pages/admin/manage_issues.php');
    exit;
}

// Only Pending issues may be flagged — once In Progress or Resolved the record is locked
if ($issue['status'] !== 'Pending') {
    header('Location: ' . BASE_URL . '/pages/admin/view_issue.php?id=' . $issueId . '&err=flag_only_pending');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['flag_reason'] ?? '');

    if (!$reason) {
        $error = 'Please provide a reason so the citizen knows what to correct.';
    } else {
        // Set flag on issue
        $db->prepare(
            "UPDATE issues SET flag_reason = ?, flagged_at = NOW(), flagged_by = ? WHERE id = ?"
        )->execute([$reason, $adminId, $issueId]);

        // Notify the citizen
        $msg = "Your issue \"" . $issue['title'] . "\" requires correction before it can be reviewed. "
             . "Admin note: " . $reason . " — Please edit your issue to address this.";

        $db->prepare("INSERT INTO notifications (user_id, issue_id, message) VALUES (?, ?, ?)")
           ->execute([$issue['citizen_id'], $issueId, $msg]);

        $success = 'Issue flagged. The citizen has been notified and can now edit their submission.';
    }
}
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/dashboard.php" class="text-green">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/manage_issues.php" class="text-green">Manage Issues</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/view_issue.php?id=<?= $issueId ?>" class="text-green">#<?= $issueId ?></a></li>
            <li class="breadcrumb-item active">Flag for Correction</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1><i class="bi bi-flag-fill me-2" style="color:#ef4444;"></i>Flag Issue for Correction</h1>
        <p>Send this issue back to the citizen with a note explaining what needs to be corrected.</p>
    </div>

    <!-- Policy notice -->
    <div class="cir-alert cir-alert-info mb-4">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>Government Platform Policy:</strong> Issues cannot be deleted. Flagging sends the issue back to the citizen for correction
            while it remains in <em>Pending</em> status. Once the citizen re-submits, the flag is cleared and the issue returns to the normal review queue.
        </div>
    </div>

    <?php if ($error): ?>
        <div class="cir-alert cir-alert-danger auto-dismiss"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="cir-alert cir-alert-success auto-dismiss">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            <a href="<?= BASE_URL ?>/pages/admin/view_issue.php?id=<?= $issueId ?>" class="fw-bold ms-2">Back to Issue →</a>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-chat-left-text me-2 text-green"></i>Correction Reason</h5></div>
                <div class="cir-card-body">
                    <?php if ($issue['flag_reason']): ?>
                        <!-- Already flagged — show current flag and allow updating it -->
                        <div class="cir-alert cir-alert-danger mb-4" style="align-items:flex-start;">
                            <i class="bi bi-flag-fill mt-1"></i>
                            <div>
                                <strong>Already flagged:</strong><br />
                                <?= htmlspecialchars($issue['flag_reason']) ?>
                                <br /><span style="font-size:0.78rem;color:#991b1b;">Flagged on <?= date('d M Y, H:i', strtotime($issue['flagged_at'])) ?></span>
                            </div>
                        </div>
                        <p style="font-size:0.88rem;color:#64748b;">You can update the reason below if needed:</p>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-row">
                            <label class="cir-form-label">Reason / Correction Instructions <span class="text-danger">*</span></label>
                            <textarea name="flag_reason" class="cir-form-control" rows="5"
                                      placeholder="Explain clearly what the citizen needs to correct. E.g. 'The description does not match the selected category. Please re-describe the issue or choose the correct category.'"
                                      required><?= htmlspecialchars($issue['flag_reason'] ?? '') ?></textarea>
                            <p style="font-size:0.78rem;color:#94a3b8;margin-top:6px;">
                                <i class="bi bi-info-circle me-1"></i>
                                This message will appear in the citizen's notification and on their issue page.
                            </p>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn-cir-primary" style="padding:12px 28px;background:#ef4444;">
                                <i class="bi bi-flag-fill"></i> Send Flag to Citizen
                            </button>
                            <a href="<?= BASE_URL ?>/pages/admin/view_issue.php?id=<?= $issueId ?>"
                               class="btn-cir-outline" style="padding:11px 22px;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Issue summary sidebar -->
        <div class="col-lg-5">
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-info-circle me-2 text-green"></i>Issue Summary</h5></div>
                <div class="cir-card-body">
                    <h6 class="fw-bold text-navy"><?= htmlspecialchars($issue['title']) ?></h6>
                    <p style="font-size:0.85rem;color:#64748b;line-height:1.6;">
                        <?= htmlspecialchars(substr($issue['description'], 0, 220)) ?>
                        <?= strlen($issue['description']) > 220 ? '…' : '' ?>
                    </p>
                    <hr style="border-color:#f1f5f9;" />
                    <table style="width:100%;font-size:0.84rem;border-collapse:collapse;">
                        <?php foreach ([
                            ['Category', $issue['category_name']],
                            ['Severity', $issue['severity']],
                            ['Citizen',  $issue['citizen_name']],
                            ['Status',   $issue['status']],
                        ] as [$l,$v]): ?>
                        <tr>
                            <td style="padding:6px 0;color:#94a3b8;font-weight:600;"><?= $l ?></td>
                            <td style="padding:6px 0 6px 12px;color:#334155;"><?= htmlspecialchars($v) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>