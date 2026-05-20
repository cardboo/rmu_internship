<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

// ---------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------
$year_filter   = isset($_GET['year']) ? (int)$_GET['year']   : 0;
$dept_filter   = trim($_GET['dept']   ?? '');
$status_filter = trim($_GET['status'] ?? '');
$q             = trim($_GET['q']      ?? '');

$years = $pdo->query("SELECT id, name, is_current FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
// Default = current academic year if no explicit filter.
if ($year_filter === 0) {
    foreach ($years as $y) if ((int)$y['is_current'] === 1) { $year_filter = (int)$y['id']; break; }
}
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE role='student' AND department IS NOT NULL AND department <> '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// ---------------------------------------------------------------------
// Per-student pipeline view: one row per active student.
// ---------------------------------------------------------------------
$sql = "
    SELECT
        u.id, u.full_name, u.index_number, u.department, u.program,
        latest_req.id        AS req_id,
        latest_req.status    AS req_status,
        latest_req.start_date AS req_start,
        latest_req.end_date   AS req_end,
        p.id            AS placement_id,
        p.company_name,
        p.start_date    AS placement_start,
        p.end_date      AS placement_end,
        (SELECT COUNT(*) FROM logbooks l WHERE l.placement_id = p.id) AS log_count,
        (SELECT COUNT(*) FROM logbooks l WHERE l.placement_id = p.id AND l.is_submitted = 1) AS submitted_count,
        e.total_score
    FROM users u
    LEFT JOIN (
        SELECT r.* FROM requests r
        JOIN (SELECT student_id, MAX(id) AS mid FROM requests GROUP BY student_id) m
          ON m.mid = r.id
    ) latest_req ON latest_req.student_id = u.id
    LEFT JOIN placements p ON p.id = (
        SELECT MAX(p2.id) FROM placements p2
        WHERE p2.student_id = u.id
        " . ($year_filter ? " AND p2.academic_year_id = " . (int)$year_filter : "") . "
    )
    LEFT JOIN evaluations e ON e.placement_id = p.id
    WHERE u.role = 'student' AND COALESCE(u.is_archived, 0) = 0
";
$params = [];
if ($dept_filter !== '') {
    $sql .= " AND u.department = ?";
    $params[] = $dept_filter;
}
if ($q !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.index_number LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like);
}
$sql .= " ORDER BY u.department, u.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Classify into pipeline stages.
function pipeline_stage(array $r): string {
    if (!empty($r['total_score']))         return 'completed';
    if (!empty($r['placement_id'])) {
        return ((int)$r['submitted_count'] > 0) ? 'logging' : 'placed';
    }
    if (!empty($r['req_status'])) {
        if ($r['req_status'] === 'approved') return 'approved';
        if ($r['req_status'] === 'rejected') return 'rejected';
        return 'requested';
    }
    return 'none';
}
foreach ($rows as &$r) $r['_stage'] = pipeline_stage($r);
unset($r);

if ($status_filter !== '') {
    $rows = array_values(array_filter($rows, fn($r) => $r['_stage'] === $status_filter));
}

// KPIs
$kpi = ['total' => 0, 'requested' => 0, 'approved' => 0, 'placed' => 0, 'logging' => 0, 'completed' => 0, 'rejected' => 0, 'none' => 0];
foreach ($rows as $r) {
    $kpi['total']++;
    $kpi[$r['_stage']]++;
}

$PIPELINE_LABELS = [
    'none'      => 'No request yet',
    'requested' => 'Letter pending',
    'approved'  => 'Letter approved',
    'placed'    => 'Placement registered',
    'logging'   => 'Logging weekly',
    'completed' => 'Completed (evaluated)',
    'rejected'  => 'Rejected',
];

// ---------------------------------------------------------------------
// CSV export — preserves the same filters as the on-screen table.
// ---------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    audit_log($pdo, 'reports.exported', 'report', 'admin_pipeline', [
        'year' => $year_filter, 'dept' => $dept_filter, 'stage' => $status_filter,
        'rows' => count($rows),
    ]);
    $filename = 'rmu_internship_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=$filename");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Index No.', 'Full Name', 'Department', 'Programme', 'Pipeline Stage',
                   'Company', 'Placement Start', 'Placement End',
                   'Logbooks Submitted', 'Evaluation Score (out of 50)']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['index_number'] ?? '',
            $r['full_name'],
            $r['department'] ?? '',
            $r['program'] ?? '',
            $PIPELINE_LABELS[$r['_stage']] ?? $r['_stage'],
            $r['company_name'] ?? '',
            $r['placement_start'] ?? '',
            $r['placement_end'] ?? '',
            (int)$r['submitted_count'],
            $r['total_score'] !== null ? (int)$r['total_score'] : '',
        ]);
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------------
// Chart 1: pipeline stages × department (stacked bar). Uses the SAME
// filtered $rows so the chart reflects whatever filter the user applied.
// ---------------------------------------------------------------------
$STAGES_ORDER = ['none','requested','approved','placed','logging','completed','rejected'];
$STAGE_COLORS = [
    'none'      => '#94a3b8',
    'requested' => '#f59e0b',
    'approved'  => '#3b82f6',
    'placed'    => '#6366f1',
    'logging'   => '#0ea5e9',
    'completed' => '#10b981',
    'rejected'  => '#ef4444',
];

$dept_stage_counts = [];
foreach ($rows as $r) {
    $d = $r['department'] ?: '(unspecified)';
    $dept_stage_counts[$d] = $dept_stage_counts[$d] ?? array_fill_keys($STAGES_ORDER, 0);
    $dept_stage_counts[$d][$r['_stage']]++;
}
ksort($dept_stage_counts);
$chart1_labels   = array_keys($dept_stage_counts);
$chart1_datasets = [];
foreach ($STAGES_ORDER as $stage) {
    $chart1_datasets[] = [
        'label' => $PIPELINE_LABELS[$stage],
        'data'  => array_map(fn($d) => $dept_stage_counts[$d][$stage], $chart1_labels),
        'backgroundColor' => $STAGE_COLORS[$stage],
    ];
}

// ---------------------------------------------------------------------
// Chart 2: distribution of evaluation scores (histogram, bins of 5/50).
// Aggregated across all evaluations matching the year filter (the
// per-row $rows array is already year-scoped via the LEFT JOIN on p2).
// ---------------------------------------------------------------------
$bins         = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]; // 0-4, 5-9, ..., 45-50
$bin_labels   = ['0-4','5-9','10-14','15-19','20-24','25-29','30-34','35-39','40-44','45-50'];
foreach ($rows as $r) {
    if (!empty($r['total_score'])) {
        $idx = min(9, (int)floor($r['total_score'] / 5));
        $bins[$idx]++;
    }
}

// ---------------------------------------------------------------------
// Chart 3: weekly submitted-logbooks trend (last 12 ISO weeks).
// ---------------------------------------------------------------------
$trend_stmt = $pdo->prepare("
    SELECT DATE_FORMAT(week_start, '%x-W%v') AS iso_week,
           COUNT(*) AS n
    FROM logbooks
    WHERE is_submitted = 1
      AND week_start >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
    GROUP BY iso_week
    ORDER BY iso_week
");
$trend_stmt->execute();
$trend_rows = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);
$trend_map  = [];
foreach ($trend_rows as $tr) $trend_map[$tr['iso_week']] = (int)$tr['n'];

// Render last 12 weeks even if some had zero submissions, so the X-axis
// doesn't have gaps.
$today = new DateTime('today');
$trend_labels = [];
$trend_values = [];
for ($i = 11; $i >= 0; $i--) {
    $d = (clone $today)->modify("-$i weeks");
    $k = $d->format('o-\WW');
    $trend_labels[] = $d->format('\WW (M-d)');
    $trend_values[] = $trend_map[$k] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/users.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboards.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/reports.css'); ?>">
    <style>
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin: 22px 0;
        }
        .chart-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px 18px 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .chart-card.wide { grid-column: 1 / -1; }
        .chart-card h3 {
            margin: 0 0 10px;
            font-size: 0.95rem;
            color: #374151;
            font-weight: 600;
        }
        .chart-card .chart-meta {
            font-size: 0.78rem;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .chart-canvas-wrap { position: relative; height: 280px; }
        @media (max-width: 900px) { .chart-grid { grid-template-columns: 1fr; } }
        @media print {
            .chart-grid { grid-template-columns: 1fr 1fr; }
            .chart-card { break-inside: avoid; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <?php $report_title = 'System-wide Internship Report'; include __DIR__ . '/../includes/print_header.php'; ?>
    <div class="header-panel">
        <div>
            <h1>System-wide Internship Report</h1>
            <p>Pipeline view of every active student across all departments. Filter, then Print or Export to CSV.</p>
        </div>
        <div class="modal-print-hide" style="display:flex; gap:8px;">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-ghost btn-sm">
                <i class="fas fa-file-csv"></i>&nbsp; Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print"></i>&nbsp; Print
            </button>
        </div>
    </div>

    <form method="GET" class="filter-bar modal-print-hide">
        <select name="year" class="filter-input">
            <option value="0">All years</option>
            <?php foreach ($years as $y): ?>
                <option value="<?php echo (int)$y['id']; ?>" <?php echo $year_filter === (int)$y['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($y['name']); ?>
                    <?php echo (int)$y['is_current'] === 1 ? ' (current)' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="dept" class="filter-input">
            <option value="">All departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $dept_filter === $d ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($d); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="filter-input">
            <option value="">All stages</option>
            <?php foreach ($PIPELINE_LABELS as $k => $lbl): ?>
                <option value="<?php echo $k; ?>" <?php echo $status_filter === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lbl); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by name or index #" class="filter-input">
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i>&nbsp; Apply</button>
        <a href="reports.php" class="btn btn-ghost">Clear</a>
    </form>

    <div class="kpi-row">
        <div class="kpi-card accent"><div class="kpi-label">Students</div><div class="kpi-value"><?php echo $kpi['total']; ?></div></div>
        <div class="kpi-card warn"><div class="kpi-label">Letter pending</div><div class="kpi-value"><?php echo $kpi['requested']; ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Letter approved</div><div class="kpi-value"><?php echo $kpi['approved']; ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Placement registered</div><div class="kpi-value"><?php echo $kpi['placed']; ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Logging weekly</div><div class="kpi-value"><?php echo $kpi['logging']; ?></div></div>
        <div class="kpi-card ok"><div class="kpi-label">Completed</div><div class="kpi-value"><?php echo $kpi['completed']; ?></div></div>
        <div class="kpi-card err"><div class="kpi-label">Rejected</div><div class="kpi-value"><?php echo $kpi['rejected']; ?></div></div>
    </div>

    <div class="chart-grid">
        <div class="chart-card wide">
            <h3><i class="fas fa-chart-bar"></i>&nbsp; Pipeline stages by department</h3>
            <p class="chart-meta">Filtered set: <?php echo (int)$kpi['total']; ?> students. Stacked by stage.</p>
            <div class="chart-canvas-wrap"><canvas id="chartStages"></canvas></div>
        </div>
        <div class="chart-card">
            <h3><i class="fas fa-chart-area"></i>&nbsp; Evaluation score distribution</h3>
            <p class="chart-meta">Buckets of 5 points (max 50). Only completed evaluations counted.</p>
            <div class="chart-canvas-wrap"><canvas id="chartScores"></canvas></div>
        </div>
        <div class="chart-card">
            <h3><i class="fas fa-chart-line"></i>&nbsp; Weekly logbooks submitted (last 12 weeks)</h3>
            <p class="chart-meta">System-wide count of submitted weekly logs per ISO week.</p>
            <div class="chart-canvas-wrap"><canvas id="chartTrend"></canvas></div>
        </div>
    </div>

    <div class="card" style="padding: 0;">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Index No.</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Programme</th>
                    <th>Stage</th>
                    <th>Company / Period</th>
                    <th class="num">Logs</th>
                    <th class="num">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">No students match the filter.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><span class="ident-chip"><?php echo htmlspecialchars($r['index_number'] ?? '—'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($r['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['department'] ?? '—'); ?></td>
                        <td class="muted small"><?php echo htmlspecialchars($r['program'] ?? '—'); ?></td>
                        <td><span class="pipeline pipeline-<?php echo $r['_stage']; ?>"><?php echo htmlspecialchars($PIPELINE_LABELS[$r['_stage']]); ?></span></td>
                        <td>
                            <?php if ($r['placement_id']): ?>
                                <strong><?php echo htmlspecialchars($r['company_name']); ?></strong><br>
                                <small class="muted"><?php echo htmlspecialchars(date('d M', strtotime($r['placement_start']))); ?>
                                  – <?php echo htmlspecialchars(date('d M Y', strtotime($r['placement_end']))); ?></small>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?php echo (int)$r['submitted_count']; ?></td>
                        <td class="num">
                            <?php if (!empty($r['total_score'])): ?>
                                <strong><?php echo (int)$r['total_score']; ?></strong><span class="muted small">/50</span>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const CHART1_LABELS   = <?php echo json_encode($chart1_labels, JSON_UNESCAPED_UNICODE); ?>;
const CHART1_DATASETS = <?php echo json_encode($chart1_datasets, JSON_UNESCAPED_UNICODE); ?>;
const CHART2_LABELS   = <?php echo json_encode($bin_labels); ?>;
const CHART2_BINS     = <?php echo json_encode($bins); ?>;
const CHART3_LABELS   = <?php echo json_encode($trend_labels); ?>;
const CHART3_VALUES   = <?php echo json_encode($trend_values); ?>;

function makeStackedBar() {
    new Chart(document.getElementById('chartStages'), {
        type: 'bar',
        data: { labels: CHART1_LABELS, datasets: CHART1_DATASETS },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { position: 'bottom' } },
        },
    });
}
function makeScoreHistogram() {
    new Chart(document.getElementById('chartScores'), {
        type: 'bar',
        data: {
            labels: CHART2_LABELS,
            datasets: [{
                label: 'Students',
                data: CHART2_BINS,
                backgroundColor: '#10b981',
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } },
        },
    });
}
function makeTrendLine() {
    new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: {
            labels: CHART3_LABELS,
            datasets: [{
                label: 'Submitted logs',
                data: CHART3_VALUES,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.18)',
                fill: true, tension: 0.3, pointRadius: 3,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } },
        },
    });
}

if (CHART1_LABELS.length > 0) makeStackedBar();
makeScoreHistogram();
makeTrendLine();
</script>
</body>
</html>
