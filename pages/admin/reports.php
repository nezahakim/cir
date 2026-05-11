<?php
$pageTitle = 'Generate Report';
require_once __DIR__ . '/../../includes/header.php';

requireLogin('admin');

$db = getDB();

// ── Report period selection ───────────────────────────────────
$period    = $_GET['period']     ?? 'monthly';      // weekly | monthly | yearly
$yearSel   = (int) ($_GET['year']   ?? date('Y'));
$monthSel  = (int) ($_GET['month']  ?? date('n'));
$weekSel   = (int) ($_GET['week']   ?? date('W'));

$validPeriods = ['weekly', 'monthly', 'yearly'];
if (!in_array($period, $validPeriods)) $period = 'monthly';

// Compute date range based on selected period
switch ($period) {
    case 'weekly':
        // ISO week — Monday to Sunday
        $dto      = new DateTime();
        $dto->setISODate($yearSel, $weekSel);
        $dateFrom = $dto->format('Y-m-d') . ' 00:00:00';
        $dto->modify('+6 days');
        $dateTo   = $dto->format('Y-m-d') . ' 23:59:59';
        $periodLabel = "Week {$weekSel}, {$yearSel} ({$dateFrom} – {$dateTo})";
        break;

    case 'yearly':
        $dateFrom    = "{$yearSel}-01-01 00:00:00";
        $dateTo      = "{$yearSel}-12-31 23:59:59";
        $periodLabel = "Full Year {$yearSel}";
        break;

    default: // monthly
        $dateFrom    = sprintf('%04d-%02d-01 00:00:00', $yearSel, $monthSel);
        $lastDay     = date('t', mktime(0, 0, 0, $monthSel, 1, $yearSel));
        $dateTo      = sprintf('%04d-%02d-%02d 23:59:59', $yearSel, $monthSel, $lastDay);
        $periodLabel = date('F Y', mktime(0, 0, 0, $monthSel, 1, $yearSel));
        break;
}

// ── Fetch report data ─────────────────────────────────────────

// Overall counts for this period
$overview = $db->prepare(
    "SELECT
        COUNT(*)                                      AS total,
        SUM(status = 'Pending')                      AS pending,
        SUM(status = 'In Progress')                  AS in_progress,
        SUM(status = 'Resolved')                     AS resolved,
        SUM(severity = 'Critical')                   AS critical,
        SUM(severity = 'High')                       AS high,
        AVG(priority_score)                          AS avg_priority,
        SUM(flag_reason IS NOT NULL AND status='Pending') AS flagged
     FROM issues
     WHERE created_at BETWEEN ? AND ?"
);
$overview->execute([$dateFrom, $dateTo]);
$ov = $overview->fetch();

// Resolution rate
$resRate = $ov['total'] > 0 ? round($ov['resolved'] / $ov['total'] * 100, 1) : 0;

// By category
$byCategory = $db->prepare(
    "SELECT c.category_name, COUNT(*) AS total,
            SUM(i.status='Resolved') AS resolved
     FROM issues i
     JOIN categories c ON i.category_id = c.id
     WHERE i.created_at BETWEEN ? AND ?
     GROUP BY c.id
     ORDER BY total DESC"
);
$byCategory->execute([$dateFrom, $dateTo]);
$catRows = $byCategory->fetchAll();

// By severity
$bySeverity = $db->prepare(
    "SELECT severity, COUNT(*) AS total
     FROM issues
     WHERE created_at BETWEEN ? AND ?
     GROUP BY severity
     ORDER BY FIELD(severity,'Critical','High','Medium','Low')"
);
$bySeverity->execute([$dateFrom, $dateTo]);
$sevRows = $bySeverity->fetchAll();

// Top 5 citizens by submission count in period
$topCitizens = $db->prepare(
    "SELECT u.full_name, COUNT(*) AS submissions,
            SUM(i.status='Resolved') AS resolved
     FROM issues i
     JOIN users u ON i.user_id = u.id
     WHERE i.created_at BETWEEN ? AND ?
     GROUP BY u.id
     ORDER BY submissions DESC
     LIMIT 5"
);
$topCitizens->execute([$dateFrom, $dateTo]);
$citizenRows = $topCitizens->fetchAll();

// Full issue list for period
$issueList = $db->prepare(
    "SELECT i.id, i.title, c.category_name, u.full_name AS citizen_name,
            i.severity, i.status, i.priority_score, i.created_at
     FROM issues i
     JOIN categories c ON i.category_id = c.id
     JOIN users u ON i.user_id = u.id
     WHERE i.created_at BETWEEN ? AND ?
     ORDER BY i.priority_score DESC"
);
$issueList->execute([$dateFrom, $dateTo]);
$issues = $issueList->fetchAll();

// Build year list for selector (from earliest issue to current year)
$firstYear = (int) ($db->query("SELECT YEAR(MIN(created_at)) FROM issues")->fetchColumn() ?: date('Y'));
$years     = range(date('Y'), $firstYear);

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

    <div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1><i class="bi bi-file-earmark-bar-graph me-2 text-green"></i>Issue Reports</h1>
            <p>Generate summary reports by week, month, or year.</p>
        </div>
        <button onclick="window.print()" class="btn-cir-primary" style="padding:11px 22px;">
            <i class="bi bi-printer"></i> Print / Export PDF
        </button>
    </div>

    <!-- ── Period selector ── -->
    <form method="GET" class="cir-card mb-4 no-print">
        <div class="cir-card-body">
            <div class="row g-3 align-items-end">

                <div class="col-sm-6 col-md-3">
                    <label class="cir-form-label">Report Period</label>
                    <select name="period" id="periodSel" class="cir-form-control" onchange="togglePeriodFields()">
                        <option value="weekly"  <?= $period==='weekly'  ? 'selected':'' ?>>Weekly</option>
                        <option value="monthly" <?= $period==='monthly' ? 'selected':'' ?>>Monthly</option>
                        <option value="yearly"  <?= $period==='yearly'  ? 'selected':'' ?>>Yearly</option>
                    </select>
                </div>

                <div class="col-sm-6 col-md-2" id="fieldMonth" style="<?= $period==='weekly'||$period==='yearly' ? 'display:none' : '' ?>">
                    <label class="cir-form-label">Month</label>
                    <select name="month" class="cir-form-control">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $monthSel===$m ? 'selected':'' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-md-2" id="fieldWeek" style="<?= $period!=='weekly' ? 'display:none' : '' ?>">
                    <label class="cir-form-label">Week #</label>
                    <input type="number" name="week" class="cir-form-control"
                           min="1" max="53" value="<?= $weekSel ?>" />
                </div>

                <div class="col-sm-6 col-md-2">
                    <label class="cir-form-label">Year</label>
                    <select name="year" class="cir-form-control">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $yearSel===$y ? 'selected':'' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-12 col-md-3">
                    <button type="submit" class="btn-cir-primary">
                        <i class="bi bi-bar-chart-line"></i> Generate Report
                    </button>
                </div>

            </div>
        </div>
    </form>

    <!-- ══════════════════════════════════════════════════════════
         REPORT CONTENT — everything below is print-friendly
    ══════════════════════════════════════════════════════════ -->

    <!-- Report header (visible in print) -->
    <div class="report-header-print" style="display:none;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px;">
            <div style="width:40px;height:40px;background:var(--cir-green);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;">
                <i class="bi bi-geo-alt-fill"></i>
            </div>
            <span style="font-family:'Sora',sans-serif;font-weight:800;font-size:1.3rem;color:var(--cir-navy);">
                CIR Rwanda — Official Issue Report
            </span>
        </div>
        <p style="color:#64748b;font-size:0.85rem;">Generated on <?= date('d F Y, H:i') ?> &nbsp;|&nbsp; Period: <?= htmlspecialchars($periodLabel) ?></p>
        <hr style="border-color:#e2e8f0;" />
    </div>

    <!-- Period label -->
    <h4 style="font-family:'Sora',sans-serif;color:var(--cir-navy);margin-bottom:20px;">
        <i class="bi bi-calendar3 me-2 text-green"></i><?= htmlspecialchars($periodLabel) ?>
        <span style="font-size:0.8rem;font-weight:400;color:#94a3b8;margin-left:10px;">
            <?= $ov['total'] ?> issue<?= $ov['total'] != 1 ? 's' : '' ?> in period
        </span>
    </h4>

    <!-- ── Overview stat cards ── -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['icon'=>'bi-collection',          'cls'=>'blue',   'val'=>$ov['total'],       'lbl'=>'Total Issues'],
            ['icon'=>'bi-hourglass-split',      'cls'=>'yellow', 'val'=>$ov['pending'],     'lbl'=>'Pending'],
            ['icon'=>'bi-arrow-repeat',         'cls'=>'blue',   'val'=>$ov['in_progress'], 'lbl'=>'In Progress'],
            ['icon'=>'bi-check-circle',         'cls'=>'green',  'val'=>$ov['resolved'],    'lbl'=>'Resolved'],
            ['icon'=>'bi-graph-up-arrow',       'cls'=>'green',  'val'=>$resRate . '%',     'lbl'=>'Resolution Rate'],
            ['icon'=>'bi-exclamation-triangle', 'cls'=>'red',    'val'=>$ov['critical'],    'lbl'=>'Critical'],
            ['icon'=>'bi-flag',                 'cls'=>'red',    'val'=>$ov['flagged'],     'lbl'=>'Flagged for Correction'],
            ['icon'=>'bi-speedometer2',         'cls'=>'purple', 'val'=>number_format((float)$ov['avg_priority'],2), 'lbl'=>'Avg Priority Score'],
        ];
        foreach ($cards as $c): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon <?= $c['cls'] ?>"><i class="bi <?= $c['icon'] ?>"></i></div>
                <div>
                    <div class="stat-value" style="font-size:1.5rem;"><?= $c['val'] ?></div>
                    <div class="stat-label"><?= $c['lbl'] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">

        <!-- By Category -->
        <div class="col-lg-6">
            <div class="cir-card h-100">
                <div class="cir-card-header"><h5><i class="bi bi-tag me-2 text-green"></i>Issues by Category</h5></div>
                <div class="cir-card-body" style="padding:0;">
                    <?php if (empty($catRows)): ?>
                        <p class="text-muted text-center py-4">No data for this period.</p>
                    <?php else: ?>
                    <table class="cir-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:right;">Resolved</th>
                                <th style="text-align:right;">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($catRows as $row):
                            $rate = $row['total'] > 0 ? round($row['resolved'] / $row['total'] * 100) : 0;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['category_name']) ?></td>
                                <td style="text-align:right;font-weight:700;"><?= $row['total'] ?></td>
                                <td style="text-align:right;color:#16a34a;"><?= $row['resolved'] ?></td>
                                <td style="text-align:right;">
                                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                                        <div style="width:50px;height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                                            <div style="width:<?= $rate ?>%;height:100%;background:var(--cir-green);border-radius:3px;"></div>
                                        </div>
                                        <span style="font-size:0.8rem;"><?= $rate ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- By Severity + Top Citizens -->
        <div class="col-lg-6 d-flex flex-column gap-4">
            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-bar-chart me-2 text-green"></i>Issues by Severity</h5></div>
                <div class="cir-card-body">
                    <?php if (empty($sevRows)): ?>
                        <p class="text-muted text-center py-3">No data for this period.</p>
                    <?php else: ?>
                    <?php foreach ($sevRows as $row):
                        $pct = $ov['total'] > 0 ? round($row['total'] / $ov['total'] * 100) : 0;
                        $barColor = match($row['severity']) {
                            'Critical' => '#991b1b', 'High' => '#d97706',
                            'Medium'   => '#1d4ed8', 'Low'  => '#16a34a',
                            default    => '#64748b'
                        };
                    ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px;">
                            <span class="fw-semibold"><?= $row['severity'] ?></span>
                            <span style="color:#64748b;"><?= $row['total'] ?> (<?= $pct ?>%)</span>
                        </div>
                        <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                            <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:4px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cir-card">
                <div class="cir-card-header"><h5><i class="bi bi-people me-2 text-green"></i>Top Reporting Citizens</h5></div>
                <div class="cir-card-body" style="padding:0;">
                    <?php if (empty($citizenRows)): ?>
                        <p class="text-muted text-center py-3">No data for this period.</p>
                    <?php else: ?>
                    <table class="cir-table">
                        <thead><tr><th>Citizen</th><th style="text-align:right;">Submitted</th><th style="text-align:right;">Resolved</th></tr></thead>
                        <tbody>
                        <?php foreach ($citizenRows as $cr): ?>
                            <tr>
                                <td><?= htmlspecialchars($cr['full_name']) ?></td>
                                <td style="text-align:right;font-weight:700;"><?= $cr['submissions'] ?></td>
                                <td style="text-align:right;color:#16a34a;"><?= $cr['resolved'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Full issue list -->
    <div class="cir-card">
        <div class="cir-card-header">
            <h5><i class="bi bi-list-ul me-2 text-green"></i>All Issues in Period</h5>
            <span style="font-size:0.82rem;color:#94a3b8;"><?= count($issues) ?> records</span>
        </div>

        <?php if (empty($issues)): ?>
            <div class="cir-card-body text-center py-5">
                <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                <p class="mt-3 text-muted">No issues reported in this period.</p>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="cir-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Citizen</th>
                        <th>Severity</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $issue): ?>
                    <tr>
                        <td style="color:#94a3b8;font-size:0.82rem;">#<?= $issue['id'] ?></td>
                        <td class="fw-semibold" style="max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($issue['title']) ?>
                        </td>
                        <td><?= htmlspecialchars($issue['category_name']) ?></td>
                        <td><?= htmlspecialchars($issue['citizen_name']) ?></td>
                        <td>
                            <span class="badge-severity badge-<?= strtolower($issue['severity']) ?>">
                                <?= $issue['severity'] ?>
                            </span>
                        </td>
                        <td style="font-weight:700;font-family:'Sora',sans-serif;">
                            <?= number_format($issue['priority_score'], 2) ?>
                        </td>
                        <td>
                            <span class="badge-status <?= statusBadgeClass($issue['status']) ?>">
                                <?= $issue['status'] ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;color:#64748b;font-size:0.82rem;">
                            <?= date('d M Y', strtotime($issue['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Print footer -->
    <div class="report-footer-print" style="display:none;text-align:center;margin-top:24px;font-size:0.8rem;color:#94a3b8;">
        Community Issue Reporter (CIR) Rwanda &mdash; Confidential Government Document &mdash; Generated <?= date('d F Y, H:i') ?>
    </div>

</div>
</div>

<!-- Period field toggle -->
<script>
function togglePeriodFields() {
    const p = document.getElementById('periodSel').value;
    document.getElementById('fieldMonth').style.display = p === 'monthly' ? '' : 'none';
    document.getElementById('fieldWeek').style.display  = p === 'weekly'  ? '' : 'none';
}
</script>

<!-- Print styles -->
<style>
@media print {
    .no-print, .cir-navbar, .cir-sidebar, .sidebar-overlay { display: none !important; }
    .main-wrapper { margin: 0 !important; }
    .main-content { padding: 20px !important; }
    .report-header-print, .report-footer-print { display: block !important; }
    .cir-card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; break-inside: avoid; }
    .stat-card  { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
    a { text-decoration: none !important; }
    body { background: #fff !important; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>