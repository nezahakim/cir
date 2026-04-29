<?php
/**
 * pages/citizen/report_issue.php
 * Citizens submit new community issues here.
 * Supports GPS auto-detection and manual map pin.
 * Calculates priority score automatically upon submission.
 */

$pageTitle = 'Report an Issue';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('citizen');

$db     = getDB();
$userId = $_SESSION['user_id'];

// ── Fetch categories for dropdown ─────────────────────────────
$categories = $db->query("SELECT id, category_name, severity_weight FROM categories ORDER BY category_name")->fetchAll();

$error   = '';
$success = '';

// ── Handle form submission ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']         ?? '');
    $categoryId   = (int) ($_POST['category_id'] ?? 0);
    $description  = trim($_POST['description']   ?? '');
    $severity     = trim($_POST['severity']       ?? '');
    $latitude     = trim($_POST['latitude']       ?? '');
    $longitude    = trim($_POST['longitude']      ?? '');
    $locationName = trim($_POST['location_name']  ?? '');
    $areaType     = trim($_POST['area_type']      ?? 'urban');

    // Validate required fields
    if (!$title || !$categoryId || !$description || !$severity) {
        $error = 'Please fill in all required fields.';
    } else {
        // ── Handle image upload ───────────────────────────────
        $imagePath = null;
        if (!empty($_FILES['photo']['name'])) {
            $file     = $_FILES['photo'];
            $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize  = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed)) {
                $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $error = 'Image file size must be under 5MB.';
            } else {
                $uploadDir = __DIR__ . '/../../assets/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName  = uniqid('issue_') . '_' . preg_replace('/[^a-z0-9.]/', '', strtolower($file['name']));
                $destPath  = $uploadDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $imagePath = 'assets/uploads/' . $fileName;
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        }

        if (!$error) {
            // ── Calculate Priority Score ──────────────────────
            // Formula: (CategoryWeight × 0.4) + (Frequency × 0.3) + (SeverityScore × 0.2) + (LocationRisk × 0.1)

            // 1. Category severity weight
            $catStmt = $db->prepare("SELECT severity_weight FROM categories WHERE id = ?");
            $catStmt->execute([$categoryId]);
            $catWeight = (float) ($catStmt->fetchColumn() ?? 1.0);

            // 2. Reporting frequency — same category in same approximate area (±0.5 degrees)
            $freqStmt = $db->prepare(
                "SELECT COUNT(*) FROM issues
                 WHERE category_id = ?
                   AND ABS(latitude  - ?) < 0.5
                   AND ABS(longitude - ?) < 0.5"
            );
            $latVal = $latitude  ? (float) $latitude  : -1.9441;
            $lngVal = $longitude ? (float) $longitude : 30.0619;
            $freqStmt->execute([$categoryId, $latVal, $lngVal]);
            $frequency = min((int) $freqStmt->fetchColumn() + 1, 10); // cap at 10

            // 3. Severity level score
            $severityScore = match($severity) {
                'Low'      => 1,
                'Medium'   => 2,
                'High'     => 3,
                'Critical' => 4,
                default    => 2,
            };

            // 4. Location risk factor
            $locationRisk = match($areaType) {
                'urban'     => 1.0,
                'peri-urban'=> 0.8,
                'rural'     => 0.6,
                default     => 1.0,
            };

            // Final priority score
            $priorityScore = ($catWeight * 0.4)
                           + ($frequency  * 0.3)
                           + ($severityScore * 0.2)
                           + ($locationRisk  * 0.1);

            // Round to 2 decimal places
            $priorityScore = round($priorityScore, 2);

            // ── Insert issue into database ────────────────────
            $insert = $db->prepare(
                "INSERT INTO issues
                    (user_id, category_id, title, description, image, latitude, longitude,
                     location_name, severity, status, priority_score)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)"
            );
            $insert->execute([
                $userId,
                $categoryId,
                $title,
                $description,
                $imagePath,
                $latitude  ?: null,
                $longitude ?: null,
                $locationName ?: null,
                $severity,
                $priorityScore,
            ]);

            $success = 'Issue reported successfully! Priority Score: ' . $priorityScore;

            // Reset POST data so form clears
            $_POST = [];
        }
    }
}
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <div class="page-header">
        <h1><i class="bi bi-plus-circle-fill me-2 text-green"></i>Report an Issue</h1>
        <p>Fill in the form below to report a community issue. Location can be auto-detected or manually pinned.</p>
    </div>

    <?php if ($error): ?>
        <div class="cir-alert cir-alert-danger auto-dismiss"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="cir-alert cir-alert-success auto-dismiss">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            <a href="<?= BASE_URL ?>/pages/citizen/my_issues.php" class="fw-bold ms-2">View My Issues →</a>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">

            <!-- Left column: Issue Details -->
            <div class="col-lg-7">
                <div class="cir-card">
                    <div class="cir-card-header"><h5><i class="bi bi-info-circle me-2 text-green"></i>Issue Details</h5></div>
                    <div class="cir-card-body">

                        <div class="form-row">
                            <label class="cir-form-label">Issue Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="cir-form-control"
                                   placeholder="e.g. Large pothole on KN 5 Road"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required />
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="cir-form-control" required>
                                    <option value="">— Select category —</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"
                                            <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="cir-form-label">Severity Level <span class="text-danger">*</span></label>
                                <select name="severity" class="cir-form-control" required>
                                    <option value="">— Select severity —</option>
                                    <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $s): ?>
                                        <option value="<?= $s ?>" <?= ($_POST['severity'] ?? '') === $s ? 'selected' : '' ?>>
                                            <?= $s ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="cir-form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="cir-form-control" rows="5"
                                      placeholder="Describe the issue in detail — what you see, how long it's been there, who it affects…"
                                      required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-row">
                            <label class="cir-form-label">Area Type</label>
                            <select name="area_type" class="cir-form-control">
                                <option value="urban"      <?= ($_POST['area_type'] ?? '') === 'urban'      ? 'selected' : '' ?>>Urban (Kigali, Butare)</option>
                                <option value="peri-urban" <?= ($_POST['area_type'] ?? '') === 'peri-urban' ? 'selected' : '' ?>>Peri-Urban</option>
                                <option value="rural"      <?= ($_POST['area_type'] ?? '') === 'rural'      ? 'selected' : '' ?>>Rural</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <label class="cir-form-label">Photo (optional)</label>
                            <input type="file" id="photoInput" name="photo"
                                   class="cir-form-control" accept="image/*" />
                            <div id="photoPreview" class="upload-preview">
                                <img src="" alt="Preview" />
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Right column: Location -->
            <div class="col-lg-5">
                <div class="cir-card">
                    <div class="cir-card-header">
                        <h5><i class="bi bi-geo-alt-fill me-2 text-green"></i>Issue Location</h5>
                        <button type="button" class="btn-cir-outline" id="gpsBtn" style="padding:6px 14px;font-size:0.8rem;">
                            <i class="bi bi-crosshair"></i> Use GPS
                        </button>
                    </div>
                    <div class="cir-card-body">
                        <p style="font-size:0.85rem;color:#64748b;margin-bottom:12px;">
                            Click the map to pin the issue location, or use GPS auto-detect.
                        </p>

                        <!-- Leaflet Map -->
                        <div id="reportMap" style="height:280px;"></div>

                        <div class="row g-2 mt-3">
                            <div class="col-6">
                                <label class="cir-form-label">Latitude</label>
                                <input type="text" id="latInput" name="latitude"
                                       class="cir-form-control" readonly placeholder="Auto-filled" />
                            </div>
                            <div class="col-6">
                                <label class="cir-form-label">Longitude</label>
                                <input type="text" id="lngInput" name="longitude"
                                       class="cir-form-control" readonly placeholder="Auto-filled" />
                            </div>
                        </div>

                        <div class="form-row mt-3">
                            <label class="cir-form-label">Location Name / Description</label>
                            <input type="text" name="location_name" id="locationName"
                                   class="cir-form-control"
                                   placeholder="e.g. KN 5 Road, near Kigali bus station"
                                   value="<?= htmlspecialchars($_POST['location_name'] ?? '') ?>" />
                        </div>

                    </div>
                </div>

                <!-- Priority score preview card -->
                <div class="cir-card mt-4">
                    <div class="cir-card-header"><h5><i class="bi bi-bar-chart-fill me-2 text-green"></i>Priority Info</h5></div>
                    <div class="cir-card-body" style="font-size:0.85rem;color:#64748b;">
                        <p>Priority is automatically calculated based on:</p>
                        <ul style="padding-left:18px;line-height:2;">
                            <li>Category severity weight × 0.4</li>
                            <li>Reporting frequency in area × 0.3</li>
                            <li>Severity level score × 0.2</li>
                            <li>Location risk factor × 0.1</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <!-- Submit -->
        <div class="mt-4 d-flex gap-3">
            <button type="submit" class="btn-cir-primary" style="padding:13px 32px;">
                <i class="bi bi-send-fill"></i> Submit Issue Report
            </button>
            <a href="<?= BASE_URL ?>/pages/citizen/dashboard.php" class="btn-cir-outline" style="padding:12px 24px;">
                Cancel
            </a>
        </div>
    </form>

</div>
</div>

<!-- Leaflet Map Initialization for report form -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Default center: Kigali, Rwanda
    const defaultLat = -1.9441;
    const defaultLng =  30.0619;

    const map = L.map('reportMap').setView([defaultLat, defaultLng], 13);

    // OpenStreetMap tile layer (free, no API key needed)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    let marker = null;

    // Custom green marker icon
    const greenIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    // Click on map to set location
    map.on('click', function (e) {
        placeMarker(e.latlng.lat, e.latlng.lng);
    });

    function placeMarker(lat, lng) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng], { icon: greenIcon, draggable: true }).addTo(map);
        marker.bindPopup('📍 Issue Location').openPopup();

        document.getElementById('latInput').value = lat.toFixed(6);
        document.getElementById('lngInput').value = lng.toFixed(6);

        // Drag to reposition
        marker.on('dragend', function (ev) {
            const pos = ev.target.getLatLng();
            document.getElementById('latInput').value = pos.lat.toFixed(6);
            document.getElementById('lngInput').value = pos.lng.toFixed(6);
        });
    }

    // GPS button
    document.getElementById('gpsBtn').addEventListener('click', function () {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }
        this.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Detecting…';
        const btn = this;
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                map.setView([lat, lng], 16);
                placeMarker(lat, lng);
                btn.innerHTML = '<i class="bi bi-check-circle"></i> Located!';
                setTimeout(() => { btn.innerHTML = '<i class="bi bi-crosshair"></i> Use GPS'; }, 2000);
            },
            function () {
                alert('Unable to get your location. Please pin it manually on the map.');
                btn.innerHTML = '<i class="bi bi-crosshair"></i> Use GPS';
            }
        );
    });
});
</script>

<style>
.spin { animation: spin 1s linear infinite; display:inline-block; }
@keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
