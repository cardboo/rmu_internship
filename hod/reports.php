<?php
require __DIR__ . '/../includes/db.php';
require_role('hod', 'secretary');

$user_id = (int)$_SESSION['user_id'];
$deptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
$deptStmt->execute([$user_id]);
$myDept = $deptStmt->fetchColumn();

// Filters (year + stage + free-text search; dept is fixed to the user's own)
$year_filter   = isset($_GET['year']) ? (int)$_GET['year']   : 0;
$status_filter = trim($_GET['status'] ?? '');
$q             = trim($_GET['q']      ?? '');

$years = $pdo->query("SELECT id, name, is_current FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
if ($year_filter === 0) {
    foreach ($years as $y) if ((int)$y['is_current'] === 1) { $year_filter = (int)$y['id']; break; }
}

$sql = "
    SELECT
        u.id, u.full_name, u.index_number, u.program,
        latest_req.id        AS req_id,
        latest_req.status    AS req_status,
        p.id            AS placement_id,
        p.company_name,
        p.start_date    AS placement_start,
        p.end_date      AS placement_end,
        (SELECT COUNT(*) FROM logbooks l WHERE l.placement_id = p.id) AS log_count,
        (SELECT COUNT(*) FROM logbooks l WHERE l.placement_id = p.id AND l.is_submitted = 1) AS submitted_count,
        (SELECT COUNT(*) FROM logbooks l WHERE l.placement_id = p.id AND l.is_reviewed = 1)  AS reviewed_count,
        e.total_score,
        e.submitted_at AS eval_at
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
    WHERE u.role = 'student' AND u.department = ? AND COALESCE(u.is_archived, 0) = 0
";
$params = [$myDept];
if ($q !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.index_number LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like);
}
$sql .= " ORDER BY u.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$kpi = ['total'=>0,'requested'=>0,'approved'=>0,'placed'=>0,'logging'=>0,'completed'=>0,'rejected'=>0,'none'=>0,'avg_score'=>null];
$score_count = 0; $score_sum = 0;
foreach ($rows as $r) {
    $kpi['total']++;
    $kpi[$r['_stage']]++;
    if (!empty($r['total_score'])) { $score_sum += (int)$r['total_score']; $score_count++; }
}
if ($score_count) $kpi['avg_score'] = round($score_sum / $score_count, 1);

$PIPELINE_LABELS = [
    'none'      => 'No request',
    'requested' => 'Letter pending',
    'approved'  => 'Letter approved',
    'placed'    => 'Placement registered',
    'logging'   => 'Logging weekly',
    'completed' => 'Completed',
    'rejected'  => 'Rejected',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($myDept); ?> Reports | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/users.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboards.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/reports.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1><?php echo htmlspecialchars($myDept); ?> Internship Report</h1>
            <p>Pipeline view of every student in your department. Filter, then Print.</p>
        </div>
        <div class="modal-print-hide" style="display:flex; gap:8px;">
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
        <div class="kpi-card"><div class="kpi-label">Placed</div><div class="kpi-value"><?php echo $kpi['placed'] + $kpi['logging']; ?></div></div>
        <div class="kpi-card ok"><div class="kpi-label">Completed</div><div class="kpi-value"><?php echo $kpi['completed']; ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Avg score</div><div class="kpi-value"><?php echo $kpi['avg_score'] !== null ? $kpi['avg_score'] : '—'; ?></div></div>
    </div>

    <div class="card" style="padding: 0;">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Index No.</th>
                    <th>Name</th>
                    <th>Programme</th>
                    <th>Stage</th>
                    <th>Company / Period</th>
                    <th class="num">Logs (subm.)</th>
                    <th class="num">Reviewed</th>
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
                        <td class="num"><?php echo (int)$r['submitted_count']; ?> / <?php echo (int)$r['log_count']; ?></td>
                        <td class="num"><?php echo (int)$r['reviewed_count']; ?></td>
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
</body>
</html>
