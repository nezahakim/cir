<?php
$pageTitle = 'Issue Details';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('citizen');

$db      = getDB();
$userId  = $_SESSION['user_id'];
$issueId = (int) ($_GET['id'] ?? 0);

if (!$issueId) {
    header('Location: ' . BASE_URL . '/pages/citizen/my_issues.php');
    exit;
}

$stmt = $db->prepare(
    "SELECT i.*, c.category_name, c.severity_weight
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

$updates = $db->prepare(
    "SELECT iu.*, u.full_name AS admin_name
     FROM issue_updates iu
     JOIN users u ON iu.admin_id = u.id
     WHERE iu.issue_id = ?
     ORDER BY iu.created_at DESC"
);
$updates->execute([$issueId]);
$updateHistory = $updates->fetchAll();

// Citizen may edit only when: status = Pending AND flag_reason is set
$canEdit = ($issue['status'] === 'Pending' && !empty($issue['flag_reason']));

function statusBadgeClass(string $s): string {
    return match($s) {
        'Pending'     => 'badge-pending',
        'In Progress' => 'badge-in-progress',
        'Resolved'    => 'badge-resolved',
        default       => 'bg-secondary',
    };
}

// Redirect error messages
$errMap = [
    'locked'      => 'This issue can no longer be edited because its status has been changed by an administrator.',
    'not_flagged' => 'This issue is not flagged for correction. No edits are needed at this time.',
];
$redirectErr = $_GET['err'] ?? '';
?>

<?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

<div class="main-wrapper">
<div class="main-content">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/citizen/dashboard.php" class="text-green">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/citizen/my_issues.php" class="text-green">My Issues</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($issue['title']) ?></li>
        </ol>
    </nav>

    <?php if ($redirectErr && isset($errMap[$redirectErr])): ?>
        <div class="cir-alert cir-alert-danger auto-dismiss">
            <i class="bi bi-exclamation-circle-fill"></i> <?= $errMap[$redirectErr] ?>
        </div>
    <?php endif; ?>

    <!-- Flag notice — shown whenever admin has flagged this issue -->
    <?php if ($issue['flag_reason']): ?>
        <div class="cir-alert cir-alert-danger mb-3" style="align-items:flex-start;gap:14px;">
            <i class="bi bi-flag-fill fs-5 mt-1" style="flex-shrink:0;"></i>
            <div>
                <strong>Action Required — Correction Requested by Administrator:</strong><br />
                <?= htmlspecialchars($issue['flag_reason']) ?>
                <br />
                <?php if ($canEdit): ?>
                    <a href="<?= BASE_URL ?>/pages/citizen/edit_issue.php?id=<?= $issueId ?>"
                       class="btn-cir-primary mt-2" style="padding:8px 18px;font-size:0.85rem;display:inline-flex;">
                        <i class="bi bi-pencil-square"></i> Edit &amp; Resubmit Issue
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Issue Header Card -->
    <div class="issue-detail-header">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <h2><?= htmlspecialchars($issue['title']) ?></h2>
                <div class="detail-meta d-flex flex-wrap gap-1">
                    <span><i class="bi bi-tag"></i><?= htmlspecialchars($issue['category_name']) ?></span>
                    <span><i class="bi bi-geo-alt"></i><?= $issue['location_name'] ? htmlspecialchars($issue['location_name']) : 'Location not set' ?></span>
                    <span><i class="bi bi-calendar3"></i><?= date('d F Y, H:i', strtotime($issue['created_at'])) ?></span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <?php if ($canEdit): ?>
                    <a href="<?= BASE_URL ?>/pages/citizen/edit_issue.php?id=<?= $issueId ?>"
                       class="btn-cir-outline" style="padding:9px 18px;border-color:#ef4444;color:#ef4444;">
                        <i class="bi bi-pencil-square"></i> Edit Issue
                    </a>
                <?php endif; ?>
                <span class="badge-status <?= statusBadgeClass($issue['status']) ?>" style="font-size:0.9rem;padding:8px 16px;">
                    <?= $issue['status'] ?>
                </span>
                <span class="badge-severity badge-<?= strtolower($issue['severity']) ?>" style="font-size:0.9rem;padding:8px 16px;">
                    <?= $issue['severity'] ?> Severity
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-file-text me-2 text-green"></i>Description</h5></div>
                <div class="cir-card-body">
                    <p style="line-height:1.8;color:#374151;"><?= nl2br(htmlspecialchars($issue['description'])) ?></p>
                </div>
            </div>

            <?php if ($issue['image']): ?>
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-image me-2 text-green"></i>Photo</h5></div>
                <div class="cir-card-body">
                    <img src="<?= BASE_URL . '/' . htmlspecialchars($issue['image']) ?>"
                         alt="Issue photo"
                         style="max-width:100%;border-radius:var(--radius-sm);max-height:400px;object-fit:cover;" />
                </div>
            </div>
            <?php endif; ?>

            <?php if ($issue['latitude'] && $issue['longitude']): ?>
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-map me-2 text-green"></i>Location on Map</h5></div>
                <div class="cir-card-body">
                    <div id="viewMap" style="height:280px;"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status History -->
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-clock-history me-2 text-green"></i>Status History</h5></div>
                <div class="cir-card-body">
                    <?php if (empty($updateHistory)): ?>
                        <p class="text-muted text-center py-3" style="font-size:0.88rem;">No status updates yet.</p>
                    <?php else: ?>
                        <div class="update-timeline">
                            <?php foreach ($updateHistory as $upd): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <p class="mb-1 fw-semibold" style="font-size:0.9rem;">
                                                Status changed:
                                                <span class="badge-status <?= statusBadgeClass($upd['old_status']) ?>"><?= $upd['old_status'] ?></span>
                                                <i class="bi bi-arrow-right mx-1" style="font-size:0.75rem;"></i>
                                                <span class="badge-status <?= statusBadgeClass($upd['new_status']) ?>"><?= $upd['new_status'] ?></span>
                                            </p>
                                            <?php if ($upd['update_message']): ?>
                                                <p class="mb-0 text-muted" style="font-size:0.85rem;">"<?= htmlspecialchars($upd['update_message']) ?>"</p>
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

        <!-- Right: Meta info -->
        <div class="col-lg-4">
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-info-circle me-2 text-green"></i>Issue Info</h5></div>
                <div class="cir-card-body">
                    <table style="width:100%;font-size:0.88rem;border-collapse:collapse;">
                        <?php
                        $rows = [
                            ['Issue ID',       '#' . $issue['id']],
                            ['Category',       $issue['category_name']],
                            ['Severity',       $issue['severity']],
                            ['Status',         $issue['status']],
                            ['Priority Score', number_format($issue['priority_score'], 2)],
                            ['Submitted',      date('d M Y', strtotime($issue['created_at']))],
                            ['Last Update',    date('d M Y', strtotime($issue['updated_at']))],
                        ];
                        foreach ($rows as [$label, $value]):
                        ?>
                        <tr>
                            <td style="padding:8px 0;color:#64748b;font-weight:600;white-space:nowrap;vertical-align:top;"><?= $label ?></td>
                            <td style="padding:8px 0 8px 12px;color:#1e293b;font-weight:500;"><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- Edit policy info card — always visible so citizens understand the rules -->
            <div class="cir-card mb-4">
                <div class="cir-card-header"><h5><i class="bi bi-shield-lock me-2 text-green"></i>Edit Policy</h5></div>
                <div class="cir-card-body" style="font-size:0.83rem;color:#64748b;line-height:1.8;">
                    <?php if ($canEdit): ?>
                        <p style="color:var(--cir-green);font-weight:600;margin-bottom:8px;">
                            <i class="bi bi-check-circle me-1"></i>You can edit this issue now.
                        </p>
                    <?php elseif ($issue['status'] !== 'Pending'): ?>
                        <p style="color:#ef4444;font-weight:600;margin-bottom:8px;">
                            <i class="bi bi-lock me-1"></i>Editing is no longer available — the issue is <?= $issue['status'] ?>.
                        </p>
                    <?php else: ?>
                        <p style="color:#64748b;margin-bottom:8px;">
                            <i class="bi bi-info-circle me-1"></i>No corrections required at this time.
                        </p>
                    <?php endif; ?>
                    <ul style="padding-left:16px;margin:0;">
                        <li>Editing only when flagged by admin.</li>
                        <li>Only while status is <em>Pending</em>.</li>
                        <li>Location cannot be changed after submission.</li>
                        <li>Issues cannot be deleted on this platform.</li>
                        <li>All edits are permanently archived.</li>
                    </ul>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/pages/citizen/my_issues.php"
               class="btn-cir-outline w-100 justify-content-center" style="padding:12px;">
                <i class="bi bi-arrow-left"></i> Back to My Issues
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
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    const color = { 'Pending': 'red', 'In Progress': 'orange', 'Resolved': 'green' }['<?= $issue['status'] ?>'] || 'blue';
    L.marker([lat, lng], { icon: L.icon({
        iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25,41], iconAnchor: [12,41], popupAnchor: [1,-34], shadowSize: [41,41]
    })}).addTo(map)
      .bindPopup(`<strong><?= htmlspecialchars(addslashes($issue['title'])) ?></strong><br><?= $issue['status'] ?>`)
      .openPopup();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>