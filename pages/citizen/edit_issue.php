<?php
/**
 * pages/citizen/edit_issue.php
 * Allows a citizen to edit their issue ONLY when:
 *   1. The issue is still 'Pending' (admin has not started acting on it), AND
 *   2. An admin has flagged it as requiring correction.
 *
 * On successful save:
 *   - The flag is cleared (flag_reason = NULL).
 *   - The edit is recorded in issue_edit_history for audit purposes.
 *   - The admin receives no automatic notification (the issue simply returns to queue).
 *
 * Issues on this government platform cannot be deleted.
 */

$pageTitle = 'Edit Issue';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('citizen');

$db      = getDB();
$userId  = $_SESSION['user_id'];
$issueId = (int) ($_GET['id'] ?? 0);

if (!$issueId) {
    header('Location: ' . BASE_URL . '/pages/citizen/my_issues.php');
    exit;
}

// Fetch issue — must belong to this citizen
$stmt = $db->prepare(
    "SELECT i.*, c.category_name
     FROM issues i
     JOIN categories c ON i.category_id = c.id
     WHERE i.id = ? AND i.user_id = ?"
);
$stmt->execute([$issueId, $userId]);
$issue = $stmt->fetch();

if (!$issue) {
    header('Location: ' . BASE_URL . '/pages/citizen/my_issues.php');
    exit;
}

// ── Eligibility gate ──────────────────────────────────────────
// Rule 1: Must still be Pending
if ($issue['status'] !== 'Pending') {
    // Redirect with a message; the view_issue page will show it
    header('Location: ' . BASE_URL . '/pages/citizen/view_issue.php?id=' . $issueId . '&err=locked');
    exit;
}

// Rule 2: Must be flagged by an admin
if (!$issue['flag_reason']) {
    header('Location: ' . BASE_URL . '/pages/citizen/view_issue.php?id=' . $issueId . '&err=not_flagged');
    exit;
}

// Fetch categories for dropdown
$categories = $db->query("SELECT id, category_name FROM categories ORDER BY category_name")->fetchAll();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $severity    = trim($_POST['severity']    ?? '');

    if (!$title || !$categoryId || !$description || !$severity) {
        $error = 'Please fill in all required fields.';
    } else {
        // Handle optional new photo
        $imagePath = $issue['image']; // default: keep existing
        if (!empty($_FILES['photo']['name'])) {
            $file    = $_FILES['photo'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Image must be under 5 MB.';
            } else {
                $uploadDir = __DIR__ . '/../../assets/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName = uniqid('issue_edit_') . '_' . preg_replace('/[^a-z0-9.]/', '', strtolower($file['name']));
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                    $imagePath = 'assets/uploads/' . $fileName;
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        }

        if (!$error) {
            // Archive current state before overwriting
            $db->prepare(
                "INSERT INTO issue_edit_history
                    (issue_id, edited_by, old_title, old_description, old_severity, old_image, edit_note)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $issueId, $userId,
                $issue['title'], $issue['description'], $issue['severity'], $issue['image'],
                'Citizen correction in response to admin flag: ' . $issue['flag_reason'],
            ]);

            // Apply the edit and clear the flag
            $db->prepare(
                "UPDATE issues
                 SET title=?, category_id=?, description=?, severity=?, image=?,
                     flag_reason=NULL, flagged_at=NULL, flagged_by=NULL,
                     updated_at=NOW()
                 WHERE id=? AND user_id=?"
            )->execute([$title, $categoryId, $description, $severity, $imagePath, $issueId, $userId]);

            $success = 'Your issue has been updated and returned to the review queue.';

            // Refresh issue data
            $stmt->execute([$issueId, $userId]);
            $issue = $stmt->fetch();
        }
    }
}
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/citizen/dashboard.php" class="text-green">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/citizen/my_issues.php" class="text-green">My Issues</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/citizen/view_issue.php?id=<?= $issueId ?>" class="text-green"><?= htmlspecialchars($issue['title']) ?></a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1><i class="bi bi-pencil-square me-2 text-green"></i>Edit Issue</h1>
        <p>Correct your issue submission as requested by the administrator.</p>
    </div>

    <!-- Admin flag notice — always visible during editing -->
    <div class="cir-alert cir-alert-danger mb-4" style="align-items:flex-start;gap:14px;">
        <i class="bi bi-flag-fill fs-5 mt-1" style="flex-shrink:0;"></i>
        <div>
            <strong>Correction Required by Administrator:</strong><br />
            <?= htmlspecialchars($issue['flag_reason']) ?>
            <br />
            <span style="font-size:0.78rem;opacity:.75;">
                Please address the above, then save. The flag will be removed and your issue will re-enter the review queue.
            </span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="cir-alert cir-alert-danger auto-dismiss"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="cir-alert cir-alert-success auto-dismiss">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            <a href="<?= BASE_URL ?>/pages/citizen/view_issue.php?id=<?= $issueId ?>" class="fw-bold ms-2">View Issue →</a>
        </div>
    <?php endif; ?>

    <!-- Platform policy reminder -->
    <div class="cir-alert cir-alert-info mb-4">
        <i class="bi bi-shield-lock-fill"></i>
        <span>
            <strong>Platform Note:</strong> Issues on this government platform cannot be deleted. You may only correct the
            content as specified above. Location data cannot be changed after submission.
        </span>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">

            <div class="col-lg-7">
                <div class="cir-card">
                    <div class="cir-card-header"><h5><i class="bi bi-info-circle me-2 text-green"></i>Issue Details</h5></div>
                    <div class="cir-card-body">

                        <div class="form-row">
                            <label class="cir-form-label">Issue Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="cir-form-control"
                                   value="<?= htmlspecialchars($issue['title']) ?>" required />
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="cir-form-control" required>
                                    <option value="">— Select category —</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"
                                            <?= $issue['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">Severity <span class="text-danger">*</span></label>
                                <select name="severity" class="cir-form-control" required>
                                    <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $issue['severity'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="cir-form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="cir-form-control" rows="6"
                                      required><?= htmlspecialchars($issue['description']) ?></textarea>
                        </div>

                        <div class="form-row">
                            <label class="cir-form-label">Replace Photo <span style="color:#94a3b8;font-weight:400;">(optional — leave blank to keep existing)</span></label>
                            <?php if ($issue['image']): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="<?= BASE_URL . '/' . htmlspecialchars($issue['image']) ?>"
                                         alt="Current photo"
                                         style="height:90px;border-radius:var(--radius-sm);object-fit:cover;border:2px solid #e2e8f0;" />
                                    <span style="font-size:0.78rem;color:#64748b;display:block;margin-top:4px;">Current photo — will be replaced if you upload a new one.</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="photoInput" name="photo"
                                   class="cir-form-control" accept="image/*" />
                            <div id="photoPreview" class="upload-preview">
                                <img src="" alt="Preview" />
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Read-only location + audit note -->
            <div class="col-lg-5">
                <div class="cir-card mb-4">
                    <div class="cir-card-header"><h5><i class="bi bi-geo-alt-fill me-2 text-green"></i>Location (read-only)</h5></div>
                    <div class="cir-card-body">
                        <p style="font-size:0.85rem;color:#64748b;">
                            Location data is locked after submission and cannot be changed.
                        </p>
                        <table style="font-size:0.85rem;width:100%;border-collapse:collapse;">
                            <tr><td style="color:#94a3b8;font-weight:600;padding:5px 0;">Location</td>
                                <td style="padding:5px 0 5px 12px;"><?= $issue['location_name'] ? htmlspecialchars($issue['location_name']) : '—' ?></td></tr>
                            <tr><td style="color:#94a3b8;font-weight:600;padding:5px 0;">Latitude</td>
                                <td style="padding:5px 0 5px 12px;"><?= $issue['latitude'] ?? '—' ?></td></tr>
                            <tr><td style="color:#94a3b8;font-weight:600;padding:5px 0;">Longitude</td>
                                <td style="padding:5px 0 5px 12px;"><?= $issue['longitude'] ?? '—' ?></td></tr>
                        </table>
                    </div>
                </div>

                <div class="cir-card">
                    <div class="cir-card-header"><h5><i class="bi bi-clock-history me-2 text-green"></i>Edit Policy</h5></div>
                    <div class="cir-card-body" style="font-size:0.85rem;color:#64748b;line-height:1.8;">
                        <ul style="padding-left:18px;margin:0;">
                            <li>Editing is only available while status is <strong>Pending</strong>.</li>
                            <li>Editing is only available when an admin has flagged the issue.</li>
                            <li>Once an admin moves the issue to <em>In Progress</em>, no further edits are possible.</li>
                            <li>All previous versions are securely archived for audit purposes.</li>
                            <li>Issues cannot be deleted on this government platform.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-4 d-flex gap-3">
            <button type="submit" class="btn-cir-primary" style="padding:13px 32px;">
                <i class="bi bi-send-fill"></i> Submit Correction
            </button>
            <a href="<?= BASE_URL ?>/pages/citizen/view_issue.php?id=<?= $issueId ?>"
               class="btn-cir-outline" style="padding:12px 24px;">
                Cancel
            </a>
        </div>
    </form>

</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>