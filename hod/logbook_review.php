<?php
require __DIR__ . '/../includes/db.php';
require_role('hod', 'secretary');

$user_id = (int)$_SESSION['user_id'];

// Dept isolation.
$deptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
$deptStmt->execute([$user_id]);
$myDept = $deptStmt->fetchColumn();

$flash = ['type' => '', 'msg' => ''];

// ----------------------------------------------------------------------
// POST: dept comment + mark reviewed (from inside the modal form).
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $log_id  = (int)($_POST['log_id'] ?? 0);
    $comment = trim($_POST['staff_comment'] ?? '');

    $check = $pdo->prepare("
        SELECT u.department FROM logbooks l
        JOIN users u ON u.id = l.student_id
        WHERE l.id = ?
    ");
    $check->execute([$log_id]);
    $logDept = $check->fetchColumn();

    if (!$log_id || $logDept !== $myDept) {
        $flash = ['type' => 'error', 'msg' => 'Unauthorized or invalid log.'];
    } else {
        $pdo->prepare("UPDATE logbooks SET staff_comment = ?, is_reviewed = 1 WHERE id = ?")
            ->execute([$comment !== '' ? $comment : null, $log_id]);
        // Redirect so the modal re-opens fresh.
        header("Location: logbook_review.php?student=" . (int)($_POST['student_id'] ?? 0) . "&saved=1");
        exit;
    }
}
if (($_GET['saved'] ?? '') === '1') {
    $flash = ['type' => 'success', 'msg' => 'Comment saved.'];
}

// Filters
$q = trim($_GET['q'] ?? '');

// One row per student (with submission counts).
$sql = "
    SELECT
        u.id          AS student_id,
        u.full_name,
        u.index_number,
        u.program,
        COUNT(l.id)                                                     AS total_logs,
        SUM(l.is_submitted = 1)                                         AS submitted,
        SUM(l.supervisor_signed_at IS NOT NULL)                         AS signed,
        SUM(l.is_reviewed = 1)                                          AS reviewed,
        MAX(l.week_number)                                              AS latest_week,
        MAX(l.submission_date)                                          AS latest_submit
    FROM users u
    LEFT JOIN logbooks l ON l.student_id = u.id
    WHERE u.role = 'student' AND u.department = ?
      AND COALESCE(u.is_archived, 0) = 0
";
$params = [$myDept];
if ($q !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.index_number LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like);
}
$sql .= " GROUP BY u.id HAVING total_logs > 0 ORDER BY latest_submit DESC, u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// All weekly entries for these students (used when modal is opened).
$logs_by_student = [];
$days_by_log     = [];
if (!empty($students)) {
    $sids  = array_column($students, 'student_id');
    $place = implode(',', array_fill(0, count($sids), '?'));

    $lStmt = $pdo->prepare("
        SELECT * FROM logbooks
        WHERE student_id IN ($place)
        ORDER BY student_id, week_number ASC
    ");
    $lStmt->execute($sids);
    $all_logs = $lStmt->fetchAll(PDO::FETCH_ASSOC);

    $log_ids = [];
    foreach ($all_logs as $l) {
        $logs_by_student[(int)$l['student_id']][] = $l;
        $log_ids[] = (int)$l['id'];
    }
    if (!empty($log_ids)) {
        $place2 = implode(',', array_fill(0, count($log_ids), '?'));
        $dStmt = $pdo->prepare("SELECT * FROM logbook_days WHERE logbook_id IN ($place2) ORDER BY logbook_id, sort_order");
        $dStmt->execute($log_ids);
        foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $days_by_log[(int)$d['logbook_id']][] = $d;
        }
    }
}

// Active modal student (for direct-link reopens after POST).
$open_student = isset($_GET['student']) ? (int)$_GET['student'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($myDept); ?> Logbook Reviews | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/users.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboards.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/logbook.css'); ?>">
    <style>
        @media print {
            .sidebar, .filter-bar, .actions-col, .modal-print-hide { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .modal { position: relative !important; background: none !important; padding: 0 !important; }
            .modal-content { box-shadow: none !important; max-width: 100% !important; }
        }
        .modal-content.lg { max-width: 820px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1><?php echo htmlspecialchars($myDept); ?> Logbook Reviews</h1>
            <p>One row per student with their submission stats. Click <strong>View Logbooks</strong> to read the weekly entries and leave a department comment.</p>
        </div>
        <div class="modal-print-hide" style="display:flex; gap:8px;">
            <a href="evaluations.php" class="btn btn-ghost btn-sm">
                <i class="fas fa-clipboard-check"></i>&nbsp; Evaluations
            </a>
            <button onclick="window.print()" class="btn btn-ghost btn-sm">
                <i class="fas fa-print"></i>&nbsp; Print
            </button>
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo $flash['type']; ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="filter-bar modal-print-hide">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search by name or index #" class="filter-input wide">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        <?php if ($q !== ''): ?>
            <a href="logbook_review.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <div class="card" style="padding: 0;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Index No.</th>
                    <th>Name</th>
                    <th>Programme</th>
                    <th class="num">Submitted</th>
                    <th class="num">Signed</th>
                    <th class="num">Reviewed</th>
                    <th>Latest</th>
                    <th class="actions-col modal-print-hide">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8" class="empty">No students in your department have submitted logbooks yet.</td></tr>
                <?php else: foreach ($students as $s): ?>
                    <tr>
                        <td><span class="ident-chip"><?php echo htmlspecialchars($s['index_number'] ?? '—'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong></td>
                        <td class="muted small"><?php echo htmlspecialchars($s['program'] ?? '—'); ?></td>
                        <td class="num"><?php echo (int)$s['submitted']; ?></td>
                        <td class="num"><?php echo (int)$s['signed']; ?></td>
                        <td class="num"><?php echo (int)$s['reviewed']; ?></td>
                        <td class="muted small">
                            <?php if ($s['latest_submit']): ?>
                                Week <?php echo (int)$s['latest_week']; ?>
                                · <?php echo htmlspecialchars(date('d M', strtotime($s['latest_submit']))); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="actions-col modal-print-hide">
                            <button class="btn btn-ghost btn-sm" onclick="openLogModal(<?php echo (int)$s['student_id']; ?>)">
                                <i class="fas fa-book-open"></i>&nbsp; View Logbooks
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Per-student log data, serialised for the modal. -->
<?php foreach ($students as $s): ?>
    <?php $sid = (int)$s['student_id']; ?>
    <script type="application/json" id="log_data_<?php echo $sid; ?>">
    <?php
        $entries = [];
        foreach ($logs_by_student[$sid] ?? [] as $l) {
            $entries[] = [
                'id'           => (int)$l['id'],
                'week'         => (int)$l['week_number'],
                'start'        => $l['start_date'],
                'end'          => $l['end_date'],
                'submitted'    => (int)$l['is_submitted'] === 1,
                'reviewed'     => (int)$l['is_reviewed']  === 1,
                'student_rem'  => $l['student_remarks'] ?? '',
                'sup_rem'      => $l['supervisor_remarks'] ?? '',
                'sup_name'     => $l['supervisor_signed_by_name'] ?? '',
                'sup_status'   => $l['supervisor_signed_by_status'] ?? '',
                'sup_at'       => $l['supervisor_signed_at'] ?? '',
                'staff_cmt'    => $l['staff_comment'] ?? '',
                'days'         => array_map(fn($d) => [
                    'label'      => $d['day_label'],
                    'date'       => $d['day_date'],
                    'activities' => $d['activities'] ?? '',
                ], $days_by_log[(int)$l['id']] ?? []),
            ];
        }
        echo json_encode([
            'name'   => $s['full_name'],
            'index'  => $s['index_number'] ?? '—',
            'logs'   => $entries,
        ]);
    ?>
    </script>
<?php endforeach; ?>

<div id="logModal" class="modal">
    <div class="modal-content lg">
        <div class="modal-header">
            <h3 id="logModalTitle"><i class="fas fa-book-open"></i>&nbsp; Weekly Logbook</h3>
            <button class="modal-close" onclick="closeLogModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" id="logModalBody"></div>
        <div class="modal-footer modal-print-hide">
            <button class="btn-action" style="background:#e2e8f0;color:#475569;" onclick="closeLogModal()">Close</button>
            <button class="btn-action" style="background:#0D8ABC;color:white;" onclick="window.print()">
                <i class="fas fa-print"></i>&nbsp; Print
            </button>
        </div>
    </div>
</div>

<script>
function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
}
function nl2br(s) { return escapeHtml(s).replace(/\n/g, '<br>'); }

function openLogModal(sid) {
    const tag = document.getElementById('log_data_' + sid);
    if (!tag) return;
    const d = JSON.parse(tag.textContent);
    document.getElementById('logModalTitle').innerHTML =
        '<i class="fas fa-book-open"></i>&nbsp; ' + escapeHtml(d.name) +
        ' <span class="muted small">&middot; ' + escapeHtml(d.index) + '</span>';

    if (d.logs.length === 0) {
        document.getElementById('logModalBody').innerHTML =
            '<p class="empty">No weekly logs submitted.</p>';
    } else {
        let html = '';
        d.logs.forEach(l => {
            let days = '';
            (l.days || []).forEach(day => {
                days += `<div class="review-day">
                    <strong>${escapeHtml(day.label)}</strong>
                    <span class="muted">${escapeHtml(day.date)}</span>
                    <span>${nl2br(day.activities)}</span>
                </div>`;
            });
            const statusPill = l.submitted
                ? '<span class="role-badge role-secretary">Submitted</span>'
                : '<span class="role-badge role-archived">Draft</span>';
            const signedPill = l.sup_at ? '<span class="role-badge role-student">Signed</span>' : '';
            const reviewedPill = l.reviewed ? '<span class="role-badge role-admin">Reviewed</span>' : '';

            let supBlock = '';
            if (l.sup_rem) {
                supBlock = `<div class="remark-block remark-supervisor">
                    <div class="who">Supervisor's Remarks ${escapeHtml(l.sup_name ? '— ' + l.sup_name : '')}</div>
                    ${nl2br(l.sup_rem)}
                </div>`;
            }
            let stuBlock = '';
            if (l.student_rem) {
                stuBlock = `<div class="remark-block remark-student">
                    <div class="who">Student's Remarks</div>
                    ${nl2br(l.student_rem)}
                </div>`;
            }
            let staffBlock = '';
            if (l.staff_cmt) {
                staffBlock = `<div class="remark-block remark-department">
                    <div class="who">Department Comment</div>
                    ${nl2br(l.staff_cmt)}
                </div>`;
            }

            html += `<div class="review-week">
                <div class="review-week-head">
                    <h4>Week ${l.week} <span class="muted small">&middot; ${escapeHtml(l.start)} – ${escapeHtml(l.end)}</span></h4>
                    <div>${statusPill} ${signedPill} ${reviewedPill}</div>
                </div>
                ${days || '<p class="muted small">No daily activities recorded.</p>'}
                ${stuBlock}
                ${supBlock}
                ${staffBlock}
                <details class="modal-print-hide" style="margin-top: 10px;">
                    <summary class="muted small" style="cursor: pointer;">
                        <i class="fas fa-comment-dots"></i>&nbsp; Add / update department comment
                    </summary>
                    <form method="POST" style="margin-top: 8px;">
                        <input type="hidden" name="log_id"     value="${l.id}">
                        <input type="hidden" name="student_id" value="${sid}">
                        <textarea name="staff_comment" rows="2"
                                  style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:6px;">${escapeHtml(l.staff_cmt)}</textarea>
                        <div class="form-actions" style="margin-top: 8px;">
                            <button type="submit" name="add_comment" class="btn btn-primary btn-sm">
                                <i class="fas fa-save"></i>&nbsp; Save &amp; mark reviewed
                            </button>
                        </div>
                    </form>
                </details>
            </div>`;
        });
        document.getElementById('logModalBody').innerHTML = html;
    }
    document.getElementById('logModal').classList.add('open');
}
function closeLogModal() { document.getElementById('logModal').classList.remove('open'); }
window.onclick = e => { if (e.target === document.getElementById('logModal')) closeLogModal(); };

// If the URL says ?student=N (after saving a comment), auto-reopen the modal.
<?php if ($open_student): ?>
window.addEventListener('DOMContentLoaded', () => openLogModal(<?php echo (int)$open_student; ?>));
<?php endif; ?>
</script>
</body>
</html>
