<?php
/**
 * pages/map/map_view.php
 * Interactive GIS map using Leaflet.js.
 * - Admins see ALL reported issues across Rwanda.
 * - Citizens see ONLY their own issues.
 * Markers are color-coded by status:
 *   Red = Pending, Orange = In Progress, Green = Resolved
 */

$pageTitle = 'GIS Map';
require_once __DIR__ . '/../../includes/header.php';

requireLogin(); // only logged-in can view

$db   = getDB();
$role = $_SESSION['role'];

// ── Fetch issues with location data ──────────────────────────
if ($role === 'admin') {
    // Admin sees all issues
    $stmt = $db->query(
        "SELECT i.id, i.title, c.category_name, i.status,
                i.severity, i.priority_score,
                i.latitude, i.longitude, i.location_name,
                u.full_name AS citizen_name,
                i.created_at
         FROM issues i
         JOIN categories c ON i.category_id = c.id
         JOIN users u ON i.user_id = u.id
         WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
         ORDER BY i.priority_score DESC"
    );
} else {
    // Citizens see only their own issues
    $stmt = $db->prepare(
        "SELECT i.id, i.title, c.category_name, i.status,
                i.severity, i.priority_score,
                i.latitude, i.longitude, i.location_name,
                u.full_name AS citizen_name,
                i.created_at
         FROM issues i
         JOIN categories c ON i.category_id = c.id
         JOIN users u ON i.user_id = u.id
         WHERE i.user_id = ?
           AND i.latitude IS NOT NULL
           AND i.longitude IS NOT NULL
         ORDER BY i.created_at DESC"
    );
    $stmt->execute([$_SESSION['user_id']]);
}

$issues = $stmt->fetchAll();

// ── Summary counts for the legend ────────────────────────────
$pending    = count(array_filter($issues, fn($i) => $i['status'] === 'Pending'));
$inProgress = count(array_filter($issues, fn($i) => $i['status'] === 'In Progress'));
$resolved   = count(array_filter($issues, fn($i) => $i['status'] === 'Resolved'));

// Encode issues as JSON for JS
$issuesJson = json_encode($issues);
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1><i class="bi bi-map me-2 text-green"></i>GIS Issue Map</h1>
            <p>
                <?= $role === 'admin'
                    ? 'All reported community issues across Rwanda.'
                    : 'Your reported issues plotted on the map.' ?>
            </p>
        </div>
        <!-- Legend -->
        <div class="d-flex gap-3 flex-wrap">
            <span style="display:flex;align-items:center;gap:6px;font-size:0.82rem;font-weight:600;">
                <span style="width:12px;height:12px;border-radius:50%;background:#ef4444;display:inline-block;"></span>
                Pending (<?= $pending ?>)
            </span>
            <span style="display:flex;align-items:center;gap:6px;font-size:0.82rem;font-weight:600;">
                <span style="width:12px;height:12px;border-radius:50%;background:#f59e0b;display:inline-block;"></span>
                In Progress (<?= $inProgress ?>)
            </span>
            <span style="display:flex;align-items:center;gap:6px;font-size:0.82rem;font-weight:600;">
                <span style="width:12px;height:12px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
                Resolved (<?= $resolved ?>)
            </span>
        </div>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-pin-map"></i></div>
                <div>
                    <div class="stat-value"><?= count($issues) ?></div>
                    <div class="stat-label">Mapped Issues</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="stat-value"><?= $pending ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-arrow-repeat"></i></div>
                <div>
                    <div class="stat-value"><?= $inProgress ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $resolved ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Card -->
    <div class="cir-card">
        <div class="cir-card-header">
            <h5><i class="bi bi-geo-alt-fill me-2 text-green"></i>Map</h5>
            <!-- Filter by status -->
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="filterAll">All</button>
                <button class="btn btn-sm" style="background:#fee2e2;color:#991b1b;border:none;" id="filterPending">Pending</button>
                <button class="btn btn-sm" style="background:#dbeafe;color:#1d4ed8;border:none;" id="filterInProgress">In Progress</button>
                <button class="btn btn-sm" style="background:#dcfce7;color:#16a34a;border:none;" id="filterResolved">Resolved</button>
            </div>
        </div>
        <div id="fullMap" style="height:560px;border-radius:0 0 var(--radius) var(--radius);"></div>
    </div>

    <!-- Issue List below map -->
    <?php if (!empty($issues)): ?>
    <div class="cir-card mt-4">
        <div class="cir-card-header">
            <h5><i class="bi bi-list-ul me-2 text-green"></i>Issues on Map</h5>
        </div>
        <div style="overflow-x:auto;">
            <table class="cir-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <?php if ($role === 'admin'): ?><th>Citizen</th><?php endif; ?>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $issue): ?>
                    <tr class="map-list-row" data-lat="<?= $issue['latitude'] ?>" data-lng="<?= $issue['longitude'] ?>" style="cursor:pointer;">
                        <td class="fw-semibold"><?= htmlspecialchars($issue['title']) ?></td>
                        <td><?= htmlspecialchars($issue['category_name']) ?></td>
                        <?php if ($role === 'admin'): ?>
                            <td><?= htmlspecialchars($issue['citizen_name']) ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge-severity badge-<?= strtolower($issue['severity']) ?>">
                                <?= $issue['severity'] ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $badgeClass = match($issue['status']) {
                                'Pending'     => 'badge-pending',
                                'In Progress' => 'badge-in-progress',
                                'Resolved'    => 'badge-resolved',
                                default       => ''
                            };
                            ?>
                            <span class="badge-status <?= $badgeClass ?>"><?= $issue['status'] ?></span>
                        </td>
                        <td style="color:#64748b;font-size:0.83rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= $issue['location_name'] ? htmlspecialchars($issue['location_name']) : round($issue['latitude'],4) . ', ' . round($issue['longitude'],4) ?>
                        </td>
                        <td>
                            <?php
                            $viewUrl = $role === 'admin'
                                ? BASE_URL . '/pages/admin/view_issue.php?id=' . $issue['id']
                                : BASE_URL . '/pages/citizen/view_issue.php?id=' . $issue['id'];
                            ?>
                            <a href="<?= $viewUrl ?>" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Leaflet Map Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // All issues passed from PHP
    const issues = <?= $issuesJson ?>;
    const isAdmin = <?= $role === 'admin' ? 'true' : 'false' ?>;

    // Default center: Kigali, Rwanda
    const map = L.map('fullMap').setView([-1.9441, 30.0619], 12);

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    // Color map for markers by status
    const colorByStatus = {
        'Pending':     'red',
        'In Progress': 'orange',
        'Resolved':    'green'
    };

    // Track all markers for filtering
    let allMarkers = [];

    /**
     * Creates a Leaflet icon for a given status color.
     */
    function makeIcon(color) {
        return L.icon({
            iconUrl:    `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
            shadowUrl:  'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize:   [25, 41],
            iconAnchor: [12, 41],
            popupAnchor:[1, -34],
            shadowSize: [41, 41]
        });
    }

    // Add a marker for each issue
    issues.forEach(function (issue) {
        if (!issue.latitude || !issue.longitude) return;

        const color  = colorByStatus[issue.status] || 'blue';
        const icon   = makeIcon(color);
        const marker = L.marker([parseFloat(issue.latitude), parseFloat(issue.longitude)], { icon });

        // Build popup HTML
        const viewPath = isAdmin
            ? `<?= BASE_URL ?>/pages/admin/view_issue.php?id=${issue.id}`
            : `<?= BASE_URL ?>/pages/citizen/view_issue.php?id=${issue.id}`;

        const popupHtml = `
            <div style="min-width:200px;font-family:'DM Sans',sans-serif;">
                <strong style="font-family:'Sora',sans-serif;font-size:0.9rem;color:#0f2744;">${issue.title}</strong>
                <hr style="margin:6px 0;border-color:#e2e8f0;" />
                <p style="margin:4px 0;font-size:0.8rem;color:#64748b;">
                    <strong>Category:</strong> ${issue.category_name}
                </p>
                <p style="margin:4px 0;font-size:0.8rem;color:#64748b;">
                    <strong>Severity:</strong> ${issue.severity}
                </p>
                <p style="margin:4px 0;font-size:0.8rem;color:#64748b;">
                    <strong>Status:</strong> ${issue.status}
                </p>
                ${isAdmin ? `<p style="margin:4px 0;font-size:0.8rem;color:#64748b;"><strong>Citizen:</strong> ${issue.citizen_name}</p>` : ''}
                <p style="margin:4px 0;font-size:0.78rem;color:#94a3b8;">
                    ${new Date(issue.created_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'})}
                </p>
                <a href="${viewPath}" style="display:inline-block;margin-top:8px;padding:5px 14px;background:var(--cir-green);color:#fff;border-radius:6px;font-size:0.78rem;font-weight:600;text-decoration:none;">
                    View Details →
                </a>
            </div>`;

        marker.bindPopup(popupHtml);
        marker.addTo(map);

        allMarkers.push({ marker, status: issue.status });
    });

    // If we have issues, fit map bounds to show all markers
    if (issues.length > 0) {
        const validIssues = issues.filter(i => i.latitude && i.longitude);
        if (validIssues.length > 0) {
            const bounds = L.latLngBounds(validIssues.map(i => [parseFloat(i.latitude), parseFloat(i.longitude)]));
            map.fitBounds(bounds, { padding: [40, 40] });
        }
    }

    // ── Filter buttons ────────────────────────────────────────
    function filterMarkers(status) {
        allMarkers.forEach(function ({ marker, status: markerStatus }) {
            if (!status || markerStatus === status) {
                if (!map.hasLayer(marker)) marker.addTo(map);
            } else {
                if (map.hasLayer(marker)) map.removeLayer(marker);
            }
        });
    }

    document.getElementById('filterAll').addEventListener('click',        () => filterMarkers(''));
    document.getElementById('filterPending').addEventListener('click',    () => filterMarkers('Pending'));
    document.getElementById('filterInProgress').addEventListener('click', () => filterMarkers('In Progress'));
    document.getElementById('filterResolved').addEventListener('click',   () => filterMarkers('Resolved'));

    // ── Click table row to pan to marker ─────────────────────
    document.querySelectorAll('.map-list-row').forEach(function (row) {
        row.addEventListener('click', function () {
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);
            if (!isNaN(lat) && !isNaN(lng)) {
                map.setView([lat, lng], 16, { animate: true });
                // Find and open the corresponding marker popup
                allMarkers.forEach(function ({ marker }) {
                    const pos = marker.getLatLng();
                    if (Math.abs(pos.lat - lat) < 0.0001 && Math.abs(pos.lng - lng) < 0.0001) {
                        marker.openPopup();
                    }
                });
                // Scroll to map
                document.getElementById('fullMap').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Hover highlight
        row.addEventListener('mouseenter', function () {
            this.style.background = '#f0fdf4';
        });
        row.addEventListener('mouseleave', function () {
            this.style.background = '';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
