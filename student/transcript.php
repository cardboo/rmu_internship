<?php
require __DIR__ . '/../includes/db.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$cur_year   = current_academic_year($pdo);

// Identity
$meStmt = $pdo->prepare("SELECT full_name, index_number, program, department, email FROM users WHERE id = ?");
$meStmt->execute([$student_id]);
$me = $meStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Most recent request (any status)
$reqStmt = $pdo->prepare("SELECT * FROM requests WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$reqStmt->execute([$student_id]);
$request = $reqStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Current placement
$plStmt = $pdo->prepare("
    SELECT * FROM placements WHERE student_id = ?
    " . ($cur_year ? " AND academic_year_id = " . (int)$cur_year['id'] : "") . "
    ORDER BY created_at DESC LIMIT 1
");
$plStmt->execute([$student_id]);
$placement = $plStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Weekly logs (for this placement)
$logs = [];
if ($placement) {
    $lStmt = $pdo->prepare("SELECT * FROM logbooks WHERE placement_id = ? ORDER BY week_number ASC");
    $lStmt->execute([(int)$placement['id']]);
    $logs = $lStmt->fetchAll(PDO::FETCH_ASSOC);
}
$logs_submitted = count(array_filter($logs, fn($l) => (int)$l['is_submitted'] === 1));
$logs_signed    = count(array_filter($logs, fn($l) => !empty($l['supervisor_signed_at'])));

// Final evaluation (if any)
$evaluation = null;
if ($placement) {
    $evStmt = $pdo->prepare("SELECT * FROM evaluations WHERE placement_id = ?");
    $evStmt->execute([(int)$placement['id']]);
    $evaluation = $evStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

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
    <title>My Internship Transcript | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/evaluation.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/reports.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>My Internship Transcript</h1>
            <p>One-page summary of your industrial attachment record. Print and share if you need a copy for files.</p>
        </div>
        <div class="modal-print-hide" style="display:flex; gap:8px;">
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print"></i>&nbsp; Print
            </button>
        </div>
    </div>

    <div class="card">
        <h3><i class="fas fa-user"></i>&nbsp; Student Information</h3>
        <div class="grid-2">
            <div><span class="muted small">Name</span><br><strong><?php echo htmlspecialchars($me['full_name'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Index No.</span><br><strong><?php echo htmlspecialchars($me['index_number'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Programme</span><br><strong><?php echo htmlspecialchars($me['program'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Department</span><br><strong><?php echo htmlspecialchars($me['department'] ?? '—'); ?></strong></div>
            <?php if ($cur_year): ?>
                <div><span class="muted small">Academic Year</span><br><strong><?php echo htmlspecialchars($cur_year['name']); ?></strong></div>
            <?php endif; ?>
            <div><span class="muted small">Generated</span><br><strong><?php echo date('d M Y'); ?></strong></div>
        </div>
    </div>

    <div class="card" style="margin-top: 18px;">
        <h3><i class="fas fa-envelope-open-text"></i>&nbsp; Attachment Letter Request</h3>
        <?php if (!$request): ?>
            <p class="muted">No letter request submitted yet.</p>
        <?php else: ?>
            <div class="grid-2">
                <div><span class="muted small">Status</span><br><strong><?php echo ucfirst($request['status']); ?></strong></div>
                <div><span class="muted small">Requested on</span><br><strong><?php echo htmlspecialchars(date('d M Y', strtotime($request['request_date']))); ?></strong></div>
                <div><span class="muted small">Target company</span><br><strong><?php echo htmlspecialchars($request['company_name'] ?? '—'); ?></strong></div>
                <div><span class="muted small">Proposed dates</span><br><strong>
                    <?php echo htmlspecialchars(date('d M Y', strtotime($request['start_date']))); ?>
                    – <?php echo htmlspecialchars(date('d M Y', strtotime($request['end_date']))); ?>
                </strong></div>
                <?php if (!empty($request['rejection_reason'])): ?>
                    <div style="grid-column: 1 / -1;"><span class="muted small">Rejection reason</span><br><?php echo nl2br(htmlspecialchars($request['rejection_reason'])); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top: 18px;">
        <h3><i class="fas fa-building"></i>&nbsp; Placement</h3>
        <?php if (!$placement): ?>
            <p class="muted">No placement registered yet.</p>
        <?php else: ?>
            <div class="grid-2">
                <div><span class="muted small">Company</span><br><strong><?php echo htmlspecialchars($placement['company_name']); ?></strong></div>
                <div><span class="muted small">Period</span><br><strong>
                    <?php echo htmlspecialchars(date('d M Y', strtotime($placement['start_date']))); ?>
                    – <?php echo htmlspecialchars(date('d M Y', strtotime($placement['end_date']))); ?>
                </strong></div>
                <div><span class="muted small">Department / Office</span><br><strong><?php echo htmlspecialchars($placement['company_department'] ?? '—'); ?></strong></div>
                <div><span class="muted small">Supervisor</span><br>
                    <strong><?php echo htmlspecialchars($placement['supervisor_name']); ?></strong>
                    <span class="muted small">&middot; <?php echo htmlspecialchars($placement['supervisor_email']); ?></span>
                </div>
                <div><span class="muted small">Status</span><br><strong><?php echo ucfirst($placement['status']); ?></strong></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-top: 18px;">
        <h3><i class="fas fa-book"></i>&nbsp; Weekly Logbooks</h3>
        <?php if (empty($logs)): ?>
            <p class="muted">No weekly logs filed yet.</p>
        <?php else: ?>
            <div class="kpi-row" style="margin-bottom: 12px;">
                <div class="kpi-card"><div class="kpi-label">Filed</div><div class="kpi-value"><?php echo count($logs); ?></div></div>
                <div class="kpi-card ok"><div class="kpi-label">Submitted</div><div class="kpi-value"><?php echo $logs_submitted; ?></div></div>
                <div class="kpi-card accent"><div class="kpi-label">Signed by supervisor</div><div class="kpi-value"><?php echo $logs_signed; ?></div></div>
            </div>
            <table class="report-table">
                <thead>
                    <tr><th>Week</th><th>Period</th><th>Status</th><th>Supervisor</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><strong>Week <?php echo (int)$l['week_number']; ?></strong></td>
                            <td><?php echo htmlspecialchars(date('d M', strtotime($l['start_date']))); ?>
                              – <?php echo htmlspecialchars(date('d M Y', strtotime($l['end_date']))); ?></td>
                            <td>
                                <?php if ((int)$l['is_submitted'] === 1): ?>
                                    <span class="role-badge role-secretary">Submitted</span>
                                <?php else: ?>
                                    <span class="role-badge role-archived">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['supervisor_signed_at'])): ?>
                                    <?php echo htmlspecialchars($l['supervisor_signed_by_name']); ?>
                                    <span class="muted small">&middot; <?php echo htmlspecialchars(date('d M', strtotime($l['supervisor_signed_at']))); ?></span>
                                <?php else: ?>
                                    <span class="muted small">— pending —</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card eval-result" style="margin-top: 18px;">
        <h3><i class="fas fa-clipboard-check"></i>&nbsp; Final Evaluation</h3>
        <?php if (!$evaluation): ?>
            <p class="muted">Not yet submitted by your supervisor.</p>
        <?php else: ?>
            <div class="eval-total">
                <div class="muted small">FINAL SCORE</div>
                <div class="eval-total-num"><?php echo (int)$evaluation['total_score']; ?> / 50</div>
                <div class="muted small">
                    Submitted <?php echo htmlspecialchars(date('d M Y', strtotime($evaluation['submitted_at']))); ?>
                    by <?php echo htmlspecialchars($evaluation['supervisor_name']); ?>
                    (<?php echo htmlspecialchars($evaluation['organization']); ?>)
                </div>
            </div>
            <table class="eval-table">
                <thead><tr><th>Criterion</th><th class="num">Achieved</th><th class="num">Max</th></tr></thead>
                <tbody>
                    <?php foreach ($CRITERIA as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['label']); ?></td>
                            <td class="num"><strong><?php echo (int)$evaluation['score_' . $c['key']]; ?></strong></td>
                            <td class="num muted"><?php echo (int)$c['max']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total">
                        <td><strong>TOTAL</strong></td>
                        <td class="num"><strong><?php echo (int)$evaluation['total_score']; ?></strong></td>
                        <td class="num"><strong>50</strong></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
