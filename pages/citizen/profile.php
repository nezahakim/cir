<?php

$pageTitle = 'My Profile';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/profile_helper.php';

requireLogin('citizen');

$db     = getDB();
$userId = $_SESSION['user_id'];

// Fetch current user record
$stmt = $db->prepare("SELECT id, full_name, email, phone, profile_image, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name']        ?? '');
    $phone      = trim($_POST['phone']             ?? '');
    $newPass    = trim($_POST['new_password']      ?? '');
    $confirmPass= trim($_POST['confirm_password']  ?? '');
    $currentPass= trim($_POST['current_password']  ?? '');

    // Basic validation
    if (!$fullName) {
        $error = 'Full name is required.';
    } elseif ($newPass && strlen($newPass) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPass && $newPass !== $confirmPass) {
        $error = 'New passwords do not match.';
    } elseif ($newPass) {
        // Verify current password before allowing change
        $pwRow = $db->prepare("SELECT password FROM users WHERE id = ?");
        $pwRow->execute([$userId]);
        $hash  = $pwRow->fetchColumn();
        if (!password_verify($currentPass, $hash)) {
            $error = 'Current password is incorrect.';
        }
    }

    if (!$error) {
        // Handle profile image upload
        $upload = handleProfileImageUpload($_FILES['profile_image'] ?? [], $user['profile_image']);
        if ($upload['error']) {
            $error = $upload['error'];
        } else {
            $newImagePath = $upload['path'];

            // Build update query
            if ($newPass) {
                $db->prepare("UPDATE users SET full_name=?, phone=?, profile_image=?, password=? WHERE id=?")
                   ->execute([$fullName, $phone, $newImagePath, password_hash($newPass, PASSWORD_DEFAULT), $userId]);
            } else {
                $db->prepare("UPDATE users SET full_name=?, phone=?, profile_image=? WHERE id=?")
                   ->execute([$fullName, $phone, $newImagePath, $userId]);
            }

            // Keep session name in sync
            $_SESSION['full_name'] = $fullName;
            $success = 'Profile updated successfully.';

            // Refresh user data
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
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
            <li class="breadcrumb-item active">My Profile</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1><i class="bi bi-person-circle me-2 text-green"></i>My Profile</h1>
        <p>Manage your account information and profile photo.</p>
    </div>

    <?php if ($error): ?>
        <div class="cir-alert cir-alert-danger auto-dismiss"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="cir-alert cir-alert-success auto-dismiss"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Avatar preview card -->
        <div class="col-lg-4">
            <div class="cir-card text-center">
                <div class="cir-card-body" style="padding:32px 24px;">
                    <div style="display:flex;justify-content:center;margin-bottom:16px;" id="avatarWrap">
                        <?= avatarHtml($user['full_name'], $user['profile_image'], '100px', '2.2rem') ?>
                    </div>
                    <h5 class="fw-bold" style="color:var(--cir-navy);"><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p style="font-size:0.82rem;color:#64748b;"><?= htmlspecialchars($user['email']) ?></p>
                    <span class="sidebar-role-badge citizen-badge">Citizen</span>
                    <hr style="border-color:#f1f5f9;margin:20px 0;" />
                    <p style="font-size:0.8rem;color:#94a3b8;">
                        <i class="bi bi-calendar3 me-1"></i>
                        Member since <?= date('d M Y', strtotime($user['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Edit form -->
        <div class="col-lg-8">
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-pencil-square me-2 text-green"></i>Edit Profile</h5></div>
                <div class="cir-card-body">
                    <form method="POST" enctype="multipart/form-data">

                        <!-- Profile photo upload -->
                        <div class="form-row">
                            <label class="cir-form-label">Profile Photo <span style="color:#94a3b8;font-weight:400;">(optional · JPG/PNG/WEBP · max 2 MB)</span></label>
                            <input type="file" name="profile_image" id="profileImageInput"
                                   class="cir-form-control" accept="image/jpeg,image/png,image/webp" />
                            <!-- Live preview -->
                            <div id="photoPreviewWrap" style="display:none;margin-top:10px;">
                                <img id="photoPreviewImg" src="" alt="Preview"
                                     style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--cir-green);" />
                                <span style="font-size:0.8rem;color:#64748b;margin-left:10px;">New photo preview</span>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="cir-form-control"
                                       value="<?= htmlspecialchars($user['full_name']) ?>" required />
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">Phone Number</label>
                                <input type="tel" name="phone" class="cir-form-control"
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                       placeholder="078 xxxxxxx" />
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="cir-form-label">Email Address</label>
                            <!-- Email is read-only; change requires admin action on gov platforms -->
                            <input type="email" class="cir-form-control"
                                   value="<?= htmlspecialchars($user['email']) ?>" disabled />
                            <p style="font-size:0.78rem;color:#94a3b8;margin-top:5px;">
                                <i class="bi bi-lock me-1"></i>Email cannot be changed. Contact an administrator if needed.
                            </p>
                        </div>

                        <hr style="border-color:#f1f5f9;margin:8px 0 20px;" />
                        <p class="fw-semibold" style="font-size:0.88rem;color:var(--cir-navy);margin-bottom:14px;">
                            <i class="bi bi-shield-lock me-1 text-green"></i>Change Password <span style="font-weight:400;color:#94a3b8;">(leave blank to keep current)</span>
                        </p>

                        <div class="form-row">
                            <label class="cir-form-label">Current Password</label>
                            <input type="password" name="current_password" class="cir-form-control"
                                   placeholder="Required only when changing password" />
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">New Password</label>
                                <input type="password" name="new_password" class="cir-form-control"
                                       placeholder="Min. 6 characters" />
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="cir-form-control"
                                       placeholder="Repeat new password" />
                            </div>
                        </div>

                        <div class="d-flex gap-3 mt-2">
                            <button type="submit" class="btn-cir-primary" style="padding:12px 28px;">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <a href="<?= BASE_URL ?>/pages/citizen/dashboard.php" class="btn-cir-outline" style="padding:11px 22px;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<script>
/* Live photo preview */
document.getElementById('profileImageInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('photoPreviewImg').src = e.target.result;
        document.getElementById('photoPreviewWrap').style.display = 'block';
    };
    reader.readAsDataURL(file);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>