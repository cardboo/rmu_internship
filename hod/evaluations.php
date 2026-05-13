<?php
require __DIR__ . '/../includes/db.php';
require_role('hod', 'secretary');

$user_id = (int)$_SESSION['user_id'];

// Department-isolation: HOD/secretary only see students in their own dept.
$deptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
$deptStmt->execute([$user_id]);
$myDept = $deptStmt->fetchColumn();

// Filters
$q             = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? '');   // 'submitted' | 'pending' | ''

// Pull every student in the dept, with their (most-recent) placement
// and (single) evaluation if any. LEFT JOINs so students without a
// placement / evaluation still surface as "pending".
$sql = "
    SELECT
        u.id            AS student_id,
        u.full_name,
        u.index_number,
        u.program,
        u.email         AS student_email,
        p.id            AS placement_id,
        p.company_name,
        p.start_date    AS placement_start,
        p.end_date      AS placement_end,
        e.id            AS eval_id,
        e.score_responsibility, e.score_reliability, e.score_knowledge, e.score_output,
        e.score_quality, e.score_punctuality, e.score_overall_perf, e.score_overall_conduct,
        e.total_score,
        e.supervisor_name AS eval_supervisor,
        e.organization    AS eval_org,
        e.supervisor_title AS eval_title,
        e.submitted_at    AS eval_submitted_at
    FROM users u
    LEFT JOIN placements p
        ON p.student_id = u.id
       AND p.id = (
            SELECT MAX(p2.id) FROM placements p2 WHERE p2.student_id = u.id
       )
    LEFT JOIN evaluations e ON e.placement_id = p.id
    WHERE u.role = 'student'
      AND u.department = ?
      AND COALESCE(u.is_archived, 0) = 0
";
$params = [$myDept];
if ($q !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.index_number LIKE ? OR u.email LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($status_filter === 'submitted') {
    $sql .= " AND e.id IS NOT NULL";
} elseif ($status_filter === 'pending') {
    $sql .= " AND e.id IS NULL";
}
$sql .= " ORDER BY (e.total_score IS NOT NULL) DESC, e.total_score DESC, u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_students  = count($rows);
$total_submitted = 0;
$total_pending   = 0;
foreach ($rows as $r) {
    if ($r['eval_id']) $total_submitted++; else $total_pending++;
}

// Criteria labels for the modal breakdown (matches RMU PDF).
$CRITERIA = [
    ['key' => 'responsibility',  'label' => 'Acceptance of responsibility',          'max' => 5],
    ['key' => 'reliability',     'label' => 'Reliability under pressure',            'max' => 5],
    ['key' => 'knowledge',       'label' => 'Application of professional knowledge', 'max' => 5],
    ['key' => 'output',          'label' => 'Output of work',                        'max' => 5],
    ['key' => 'quality',         'label' => 'Quality of work',                       'max' => 5],
    ['key' => 'punctuality',     'label' => 'Punctuality',                           'max' => 5],
    ['key' => 'overall_perf',    'label' => 'Overall performance',                   'max' => 10],
    ['key' => 'overall_conduct', 'label' => 'Overall conduct (ethics)',              'max' => 10],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($myDept); ?> Evaluations | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/users.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboards.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/evaluation.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/reports.css'); ?>">
    <style>
        @media print {
            .sidebar, .header-panel a, .filter-bar, .actions-col,
            .modal-print-hide { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
        }
        .modal-content.lg { max-width: 720px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <?php $report_title = htmlspecialchars($myDept) . ' — Final Evaluations'; include __DIR__ . '/../includes/print_header.php'; ?>
    <div class="header-panel">
        <div>
            <h1><?php echo htmlspecialchars($myDept); ?> Final Evaluations</h1>
            <p>Supervisor scores for every student in your department. Click <strong>View</strong> for the per-criterion breakdown.</p>
        </div>
        <div class="modal-print-hide" style="display:flex; gap:8px;">
            <button onclick="window.print()" class="btn btn-ghost btn-sm">
                <i class="fas fa-print"></i>&nbsp; Print
            </button>
        </div>
    </div>

    <div class="counts-row modal-print-hide">
        <a href="?" class="count-pill <?php echo $status_filter === '' ? 'active' : ''; ?>">All <span class="count-num"><?php echo $total_students; ?></span></a>
        <a href="?status=submitted" class="count-pill <?php echo $status_filter === 'submitted' ? 'active' : ''; ?>">Submitted <span class="count-num"><?php echo $total_submitted; ?></span></a>
        <a href="?status=pending" class="count-pill <?php echo $status_filter === 'pending' ? 'active warn' : ''; ?>">Pending <span class="count-num"><?php echo $total_pending; ?></span></a>
    </div>

    <form method="GET" class="filter-bar modal-print-hide">
        <?php if ($status_filter !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search by name, index # or email" class="filter-input wide">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        <?php if ($q !== ''): ?>
            <a href="?<?php echo $status_filter ? 'status=' . urlencode($status_filter) : ''; ?>" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <div class="card" style="padding: 0;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Index No.</th>
                    <th>Name</th>
                    <th>Programme</th>
                    <th>Company</th>
                    <th class="num">Score</th>
                    <th>Status</th>
                    <th class="actions-col modal-print-hide">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="empty">No students match your filter.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><span class="ident-chip"><?php echo htmlspecialchars($r['index_number'] ?? '—'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($r['full_name']); ?></strong></td>
                        <td class="muted small"><?php echo htmlspecialchars($r['program'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['company_name'] ?? '—'); ?></td>
                        <td class="num">
                            <?php if ($r['eval_id']): ?>
                                <strong><?php echo (int)$r['total_score']; ?></strong><span class="muted small">/50</span>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['eval_id']): ?>
                                <span class="role-badge role-secretary">Submitted</span>
                            <?php elseif ($r['placement_id']): ?>
                                <span class="role-badge role-archived">Awaiting</span>
                            <?php else: ?>
                                <span class="role-badge role-unknown">No placement</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-col modal-print-hide">
                            <?php if ($r['eval_id']): ?>
                                <button class="btn btn-ghost btn-sm" onclick='openEvalModal(<?php echo (int)$r['student_id']; ?>)'>
                                    <i class="fas fa-eye"></i>&nbsp; View
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden detail panels (one per student with an evaluation) — JS shows them inside the modal. -->
<?php foreach ($rows as $r): ?>
    <?php if (!$r['eval_id']) continue; ?>
    <script type="application/json" id="eval_data_<?php echo (int)$r['student_id']; ?>">
    <?php
        echo json_encode([
            'student'    => $r['full_name'],
            'index'      => $r['index_number'] ?? '—',
            'program'    => $r['program'] ?? '—',
            'company'    => $r['company_name'] ?? '—',
            'period'     => ($r['placement_start'] ?? '—') . ' → ' . ($r['placement_end'] ?? '—'),
            'supervisor' => $r['eval_supervisor'] ?? '',
            'title'      => $r['eval_title'] ?? '',
            'org'        => $r['eval_org'] ?? '',
            'submitted'  => $r['eval_submitted_at'] ?? '',
            'total'      => (int)$r['total_score'],
            'scores'     => array_map(fn($c) => [
                'label'    => $c['label'],
                'max'      => $c['max'],
                'achieved' => (int)$r['score_' . $c['key']],
            ], $CRITERIA),
        ]);
    ?>
    </script>
<?php endforeach; ?>

<div id="evalModal" class="modal">
    <div class="modal-content lg">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i>&nbsp; Final Evaluation</h3>
            <button class="modal-close" onclick="closeEvalModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" id="evalModalBody"></div>
        <div class="modal-footer modal-print-hide">
            <button class="btn-action" style="background:#e2e8f0;color:#475569;" onclick="closeEvalModal()">Close</button>
            <button class="btn-action" style="background:#0D8ABC;color:white;" onclick="window.print()">
                <i class="fas fa-print"></i>&nbsp; Print
            </button>
        </div>
    </div>
</div>

<script>
function openEvalModal(studentId) {
    const tag = document.getElementById('eval_data_' + studentId);
    if (!tag) return;
    const d = JSON.parse(tag.textContent);
    let rows = '';
    d.scores.forEach(s => {
        rows += `<tr><td>${escapeHtml(s.label)}</td>`
              + `<td class="num"><strong>${s.achieved}</strong></td>`
              + `<td class="num muted">${s.max}</td></tr>`;
    });
    document.getElementById('evalModalBody').innerHTML = `
        <div class="grid-2" style="margin-bottom: 14px;">
            <div><span class="muted small">Student</span><br><strong>${escapeHtml(d.student)}</strong>
                 <span class="muted small">&middot; ${escapeHtml(d.index)}</span></div>
            <div><span class="muted small">Programme</span><br><strong>${escapeHtml(d.program)}</strong></div>
            <div><span class="muted small">Company</span><br><strong>${escapeHtml(d.company)}</strong></div>
            <div><span class="muted small">Period</span><br><strong>${escapeHtml(d.period)}</strong></div>
        </div>
        <div class="eval-total" style="text-align:center; padding:12px 0; border-bottom:1px solid #f1f5f9; margin-bottom:12px;">
            <div class="muted small">FINAL SCORE</div>
            <div class="eval-total-num">${d.total} <small>/ 50</small></div>
            <div class="muted small">Submitted ${escapeHtml(d.submitted)} by ${escapeHtml(d.supervisor)}</div>
        </div>
        <table class="eval-table">
            <thead><tr><th>Criterion</th><th class="num">Achieved</th><th class="num">Max</th></tr></thead>
            <tbody>${rows}
                <tr class="total"><td><strong>TOTAL</strong></td>
                    <td class="num"><strong>${d.total}</strong></td>
                    <td class="num"><strong>50</strong></td></tr>
            </tbody>
        </table>
        <div class="grid-2" style="margin-top: 14px;">
            <div><span class="muted small">Training Officer</span><br><strong>${escapeHtml(d.supervisor)}</strong></div>
            <div><span class="muted small">Title</span><br><strong>${escapeHtml(d.title || '—')}</strong></div>
            <div><span class="muted small">Organisation</span><br><strong>${escapeHtml(d.org)}</strong></div>
        </div>
    `;
    document.getElementById('evalModal').classList.add('open');
}
function closeEvalModal() { document.getElementById('evalModal').classList.remove('open'); }
window.onclick = e => { if (e.target === document.getElementById('evalModal')) closeEvalModal(); };

function escapeHtml(s) {
    if (s === null || s === undefined) return '—';
    return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
}
</script>
</body>
</html>
