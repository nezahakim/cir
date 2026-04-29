<?php
/**
 * Public landing page for the Community Issue Reporter (CIR) system.
 */

$pageTitle = 'Welcome';
require_once __DIR__ . '/includes/header.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $dest = hasRole('admin')
        ? BASE_URL . '/pages/admin/dashboard.php'
        : BASE_URL . '/pages/citizen/dashboard.php';
    header("Location: $dest");
    exit;
}
?>

<!-- Landing Page — no sidebar, full screen -->
<div class="landing-hero">

    <!-- Nav Bar -->
    <div class="landing-nav">
        <div class="d-flex align-items-center gap-2">
            <div class="auth-logo-icon" style="width:40px;height:40px;font-size:1.1rem;">
                <i class="bi bi-geo-alt-fill"></i>
            </div>
            <span style="font-family:'Sora',sans-serif;font-weight:800;font-size:1.1rem;color:#fff;">
                CIR <span style="opacity:.6;font-weight:400;">Rwanda</span>
            </span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/pages/auth/login.php" class="btn-hero-outline" style="padding:9px 22px;font-size:0.88rem;">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </a>
            <a href="<?= BASE_URL ?>/pages/auth/register.php" class="btn-hero-primary" style="padding:9px 22px;font-size:0.88rem;">
                Get Started
            </a>
        </div>
    </div>

    <!-- Hero Content -->
    <div class="landing-hero-content">
        <div class="hero-badge">
            <i class="bi bi-geo-alt-fill"></i>
            Community Issue Reporter — Rwanda
        </div>

        <h1 class="hero-title">
            Report. Track.<br/>
            <span>Resolve Together.</span>
        </h1>

        <p class="hero-subtitle">
            Empowering Rwanda's communities to report local infrastructure issues,
            track their resolution on an interactive GIS map, and hold local
            authorities accountable.
        </p>

        <div class="hero-actions">
            <a href="<?= BASE_URL ?>/pages/auth/register.php" class="btn-hero-primary">
                <i class="bi bi-person-plus-fill"></i>
                Register as Citizen
            </a>
            <a href="<?= BASE_URL ?>/pages/auth/login.php" class="btn-hero-outline">
                <i class="bi bi-shield-lock"></i>
                Admin Login
            </a>
        </div>
    </div>

    <!-- Wave divider -->
    <div style="position:relative;z-index:5;line-height:0;">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <path d="M0,60 C360,0 1080,60 1440,20 L1440,60 Z" fill="#f4f7f5"/>
        </svg>
    </div>
</div>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-sora fw-bold text-navy" style="font-size:2rem;">How It Works</h2>
            <p class="text-muted mt-2">A smart, map-driven platform for community infrastructure management.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="bi bi-person-plus"></i></div>
                    <h5>Register & Login</h5>
                    <p>Create your citizen account in seconds and get access to the full reporting platform.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="bi bi-camera"></i></div>
                    <h5>Report Issues</h5>
                    <p>Submit issues with photos, GPS location, category, and severity. Your report is automatically prioritized.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="bi bi-map"></i></div>
                    <h5>GIS Map View</h5>
                    <p>See all reported issues on an interactive Leaflet.js map with color-coded status markers.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="bi bi-bell"></i></div>
                    <h5>Real-time Updates</h5>
                    <p>Receive instant notifications when admins update the status of your reported issues.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer style="background:var(--cir-navy);color:rgba(255,255,255,.5);padding:24px;text-align:center;font-size:0.85rem;">
    <p class="mb-0">
        &copy; <?= date('Y') ?> Community Issue Reporter (CIR) &mdash; Rwanda.
        Academic Project.
    </p>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
