<?php
/**
 * pages/auth/login.php
 * Handles citizen and admin login with role-based redirection.
 * Uses PDO prepared statements and PHP password_verify().
 */

$pageTitle = 'Login';
require_once __DIR__ . '/../../includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . (hasRole('admin') ? '/pages/admin/dashboard.php' : '/pages/citizen/dashboard.php'));
    exit;
}

$error = '';

// ── Process login form ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, full_name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'Your account has been deactivated. Contact the administrator.';
            } else {
                // Store user data in session
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['email']     = $user['email'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: ' . BASE_URL . '/pages/admin/dashboard.php');
                } else {
                    header('Location: ' . BASE_URL . '/pages/citizen/dashboard.php');
                }
                exit;
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
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
        <p class="auth-tagline">Your voice builds a better community.</p>
        <p class="auth-desc">
            Report road damage, flooding, broken street lights and more.
            Track every issue on a live GIS map and receive updates when resolved.
        </p>

        <!-- Mini stats decorative -->
        <div class="d-flex gap-4 mt-4">
            <div class="text-center">
                <div style="font-family:'Sora',sans-serif;font-size:1.8rem;font-weight:800;color:var(--cir-green);">8</div>
                <div style="font-size:0.78rem;color:rgba(255,255,255,.5);">Issue Types</div>
            </div>
            <div style="width:1px;background:rgba(255,255,255,.1);"></div>
            <div class="text-center">
                <div style="font-family:'Sora',sans-serif;font-size:1.8rem;font-weight:800;color:var(--cir-green);">GPS</div>
                <div style="font-size:0.78rem;color:rgba(255,255,255,.5);">Auto Location</div>
            </div>
            <div style="width:1px;background:rgba(255,255,255,.1);"></div>
            <div class="text-center">
                <div style="font-family:'Sora',sans-serif;font-size:1.8rem;font-weight:800;color:var(--cir-green);">🗺</div>
                <div style="font-size:0.78rem;color:rgba(255,255,255,.5);">GIS Mapping</div>
            </div>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-right">
        <div style="width:100%;max-width:380px;">

            <h2 class="auth-form-title">Welcome back</h2>
            <p class="auth-form-sub">Sign in to your CIR account</p>

            <?php if ($error): ?>
                <div class="cir-alert cir-alert-danger auto-dismiss">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-row">
                    <label class="cir-form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           class="cir-form-control"
                           placeholder="you@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required />
                </div>

                <div class="form-row">
                    <label class="cir-form-label" for="password">Password</label>
                    <div class="position-relative">
                        <input type="password" id="password" name="password"
                               class="cir-form-control"
                               placeholder="Enter your password"
                               required />
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y pe-3"
                                onclick="togglePassword('password', this)" style="color:#94a3b8;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-cir-primary w-100 justify-content-center mt-2" style="padding:13px;">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Sign In
                </button>
            </form>

            <p class="text-center mt-4 mb-0" style="font-size:0.88rem;color:#64748b;">
                Don't have an account?
                <a href="<?= BASE_URL ?>/pages/auth/register.php" class="text-green fw-semibold">Register here</a>
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
