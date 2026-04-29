<?php
/**
 * pages/admin/view_issue.php
 * Admin view of a single issue with full details, photo, map, and update history.
 */

$pageTitle = 'Issue Details';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('admin');

$db      = getDB();
$issueId = (int) ($_GET['id'] ?? 0);

if (!$issueId) {
    header('Location: ' . BASE_URL . '/pages/admin/manage_issues.php');
    exit;
}

// Fetch issue with citizen info and category
$stmt = $db->prepare(
    "SELECT i.*, c.category_name, c.severity_weight,
            u.full_name AS citizen_name, u.email AS citizen_email, u.phone AS citizen_phone
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

// Fetch update history
$updates = $db->prepare(
    "SELECT iu.*, u.full_name AS admin_name
     FROM issue_updates iu
     JOIN users u ON iu.admin_id = u.id
     WHERE iu.issue_id = ?
     ORDER BY iu.created_at DESC"
);
$updates->execute([$issueId]);
$updateHistory = $updates->fetchAll();

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
            <li class="breadcrumb-item active">#<?= $issueId ?></li>
        </ol>
    </nav>

    <!-- Issue Header -->
    <div class="issue-detail-header">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <h2><?= htmlspecialchars($issue['title']) ?></h2>
                <div class="detail-meta d-flex flex-wrap gap-1">
                    <span><i class="bi bi-tag"></i><?= htmlspecialchars($issue['category_name']) ?></span>
                    <span><i class="bi bi-person"></i><?= htmlspecialchars($issue['citizen_name']) ?></span>
                    <span><i class="bi bi-geo-alt"></i><?= $issue['location_name'] ? htmlspecialchars($issue['location_name']) : 'No location set' ?></span>
                    <span><i class="bi bi-calendar3"></i><?= date('d F Y, H:i', strtotime($issue['created_at'])) ?></span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/pages/admin/update_issue.php?id=<?= $issueId ?>" class="btn-cir-primary" style="padding:10px 20px;">
                    <i class="bi bi-pencil-square"></i> Update Status
                </a>
                <span class="badge-status <?= statusBadgeClass($issue['status']) ?>" style="font-size:0.9rem;padding:10px 18px;display:flex;align-items:center;">
                    <?= $issue['status'] ?>
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Description -->
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-file-text me-2 text-green"></i>Description</h5></div>
                <div class="cir-card-body">
                    <p style="line-height:1.8;color:#374151;"><?= nl2br(htmlspecialchars($issue['description'])) ?></p>
                </div>
            </div>

            <!-- Photo -->
            <?php if ($issue['image']): ?>
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-image me-2 text-green"></i>Attached Photo</h5></div>
                <div class="cir-card-body">
                    <img src="<?= BASE_URL . '/' . htmlspecialchars($issue['image']) ?>"
                         alt="Issue photo"
                         style="max-width:100%;border-radius:var(--radius-sm);max-height:400px;object-fit:cover;" />
                </div>
            </div>
            <?php endif; ?>

            <!-- Map -->
            <?php if ($issue['latitude'] && $issue['longitude']): ?>
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-map me-2 text-green"></i>Location on Map</h5></div>
                <div class="cir-card-body">
                    <div id="viewMap" style="height:280px;"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Update History -->
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-clock-history me-2 text-green"></i>Update History</h5></div>
                <div class="cir-card-body">
                    <?php if (empty($updateHistory)): ?>
                        <p class="text-muted text-center py-3" style="font-size:0.88rem;">No updates recorded yet.</p>
                    <?php else: ?>
                        <div class="update-timeline">
                            <?php foreach ($updateHistory as $upd): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <p class="mb-1 fw-semibold" style="font-size:0.9rem;">
                                                <span class="badge-status <?= statusBadgeClass($upd['old_status']) ?>"><?= $upd['old_status'] ?></span>
                                                <i class="bi bi-arrow-right mx-1" style="font-size:0.75rem;"></i>
                                                <span class="badge-status <?= statusBadgeClass($upd['new_status']) ?>"><?= $upd['new_status'] ?></span>
                                            </p>
                                            <?php if ($upd['update_message']): ?>
                                                <p class="mb-0 text-muted" style="font-size:0.85rem;">
                                                    "<?= htmlspecialchars($upd['update_message']) ?>"
                                                </p>
                                            <?php endif; ?>
                                            <p class="mb-0" style="font-size:0.78rem;color:#94a3b8;">By <?= htmlspecialchars($upd['admin_name']) ?></p>
                                        </div>
                                        <span style="font-size:0.78rem;color:#94a3b8;white-space:nowrap;">
                                            <?= date('d M Y, H:i', strtotime($upd['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right sidebar: meta + citizen info -->
        <div class="col-lg-4">
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-info-circle me-2 text-green"></i>Issue Info</h5></div>
                <div class="cir-card-body">
                    <table style="width:100%;font-size:0.88rem;border-collapse:collapse;">
                        <?php
                        $rows = [
                            ['Issue ID',        '#' . $issue['id']],
                            ['Category',        $issue['category_name']],
                            ['Severity',        $issue['severity']],
                            ['Priority Score',  number_format($issue['priority_score'], 2)],
                            ['Status',          $issue['status']],
                            ['Latitude',        $issue['latitude'] ?: '—'],
                            ['Longitude',       $issue['longitude'] ?: '—'],
                            ['Submitted',       date('d M Y', strtotime($issue['created_at']))],
                        ];
                        foreach ($rows as [$label, $value]):
                        ?>
                        <tr>
                            <td style="padding:8px 0;color:#64748b;font-weight:600;white-space:nowrap;"><?= $label ?></td>
                            <td style="padding:8px 0 8px 12px;color:#1e293b;font-weight:500;"><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- Citizen info -->
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-person-circle me-2 text-green"></i>Reported By</h5></div>
                <div class="cir-card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="user-avatar-lg" style="width:44px;height:44px;font-size:1.1rem;">
                            <?= strtoupper(substr($issue['citizen_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="mb-0 fw-semibold" style="color:var(--cir-navy);"><?= htmlspecialchars($issue['citizen_name']) ?></p>
                            <p class="mb-0" style="font-size:0.82rem;color:#64748b;"><?= htmlspecialchars($issue['citizen_email']) ?></p>
                        </div>
                    </div>
                    <?php if ($issue['citizen_phone']): ?>
                        <p style="font-size:0.85rem;color:#64748b;"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($issue['citizen_phone']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/pages/admin/manage_issues.php" class="btn-cir-outline w-100 justify-content-center" style="padding:12px;">
                <i class="bi bi-arrow-left"></i> Back to Issues
            </a>
        </div>
    </div>

</div>
</div>

<?php if ($issue['latitude'] && $issue['longitude']): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lat = <?= (float) $issue['latitude'] ?>;
    const lng = <?= (float) $issue['longitude'] ?>;
    const map = L.map('viewMap').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors' }).addTo(map);
    const colorMap = { 'Pending': 'red', 'In Progress': 'orange', 'Resolved': 'green' };
    const color = colorMap['<?= $issue['status'] ?>'] || 'blue';
    const icon = L.icon({
        iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    });
    L.marker([lat, lng], { icon }).addTo(map)
     .bindPopup('<strong><?= htmlspecialchars(addslashes($issue['title'])) ?></strong><br><?= $issue['status'] ?>')
     .openPopup();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
