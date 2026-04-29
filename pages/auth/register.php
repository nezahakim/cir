<?php
/**
 * pages/auth/register.php
 * Citizen registration form.
 * Passwords are hashed using PHP password_hash() before storing.
 */

$pageTitle = 'Register';
require_once __DIR__ . '/../../includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/citizen/dashboard.php');
    exit;
}

$error   = '';
$success = '';

// ── Process registration form ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name']        ?? '');
    $email      = trim($_POST['email']             ?? '');
    $phone      = trim($_POST['phone']             ?? '');
    $password   = trim($_POST['password']          ?? '');
    $confirmPwd = trim($_POST['confirm_password']  ?? '');

    // Basic validation
    if (!$fullName || !$email || !$password || !$confirmPwd) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPwd) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check if email already exists
        $check = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'An account with this email already exists. Please login.';
        } else {
            // Hash password securely
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert new citizen user
            $insert = $db->prepare(
                "INSERT INTO users (full_name, email, password, phone, role)
                 VALUES (?, ?, ?, ?, 'citizen')"
            );
            $insert->execute([$fullName, $email, $hashedPassword, $phone]);

            $success = 'Account created successfully! You can now login.';
        }
    }
}
?>

<div class="auth-wrapper">

    <!-- Left decorative panel -->
    <div class="auth-left">
        <div class="auth-logo">
            <div class="auth-logo-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <span class="auth-logo-text">CIR Rwanda</span>
        </div>
        <p class="auth-tagline">Join thousands of active citizens shaping Rwanda's future.</p>
        <p class="auth-desc">
            Register to report community issues, pin them on a live map,
            upload photos, and get notified when your report is resolved.
        </p>
    </div>

    <!-- Right form panel -->
    <div class="auth-right" style="overflow-y:auto;">
        <div style="width:100%;max-width:380px;">

            <h2 class="auth-form-title">Create Account</h2>
            <p class="auth-form-sub">Join the CIR community today</p>

            <?php if ($error): ?>
                <div class="cir-alert cir-alert-danger auto-dismiss">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="cir-alert cir-alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= htmlspecialchars($success) ?>
                    <a href="<?= BASE_URL ?>/pages/auth/login.php" class="fw-bold ms-2">Login →</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-row">
                    <label class="cir-form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           class="cir-form-control"
                           placeholder="Jean Baptiste Uwimana"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           required />
                </div>

                <div class="form-row">
                    <label class="cir-form-label" for="email">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email"
                           class="cir-form-control"
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required />
                </div>

                <div class="form-row">
                    <label class="cir-form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone"
                           class="cir-form-control"
                           placeholder="078 xxxxxxx"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" />
                </div>

                <div class="form-row">
                    <label class="cir-form-label" for="password">Password <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="password" id="password" name="password"
                               class="cir-form-control"
                               placeholder="At least 6 characters"
                               required />
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-3"
                                onclick="togglePassword('password', this)" style="color:#94a3b8;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="cir-form-label" for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="cir-form-control"
                           placeholder="Repeat your password"
                           required />
                </div>

                <button type="submit" class="btn-cir-primary w-100 justify-content-center mt-2" style="padding:13px;">
                    <i class="bi bi-person-check-fill"></i>
                    Create My Account
                </button>
            </form>

            <p class="text-center mt-4 mb-0" style="font-size:0.88rem;color:#64748b;">
                Already have an account?
                <a href="<?= BASE_URL ?>/pages/auth/login.php" class="text-green fw-semibold">Sign in here</a>
            </p>
        </div>
    </div>
</div>

<script>
function togglePassword(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
