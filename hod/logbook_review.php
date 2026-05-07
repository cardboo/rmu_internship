<?php
require __DIR__ . '/../includes/db.php';
require_role('hod', 'secretary');

$user_id = (int)$_SESSION['user_id'];

// Reviewer's department (HOD/secretary are scoped to their dept).
$deptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
$deptStmt->execute([$user_id]);
$myDept = $deptStmt->fetchColumn();

$flash = ['type' => '', 'msg' => ''];

// ----------------------------------------------------------------------
// POST: department comment + mark reviewed
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $log_id  = (int)($_POST['log_id'] ?? 0);
    $comment = trim($_POST['staff_comment'] ?? '');

    // Verify the log belongs to a student in this dept.
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
        $flash = ['type' => 'success', 'msg' => 'Comment saved.'];
    }
}

// ----------------------------------------------------------------------
// Fetch this department's submitted logs, with student + placement info.
// ----------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT l.*,
           u.full_name, u.index_number, u.program,
           p.company_name, p.company_department
    FROM logbooks l
    JOIN users      u ON u.id = l.student_id
    LEFT JOIN placements p ON p.id = l.placement_id
    WHERE u.department = ?
    ORDER BY l.is_submitted DESC, l.submission_date DESC, l.week_number DESC
");
$stmt->execute([$myDept]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Days, indexed by logbook_id for easy render
$days_by_log = [];
if (!empty($logs)) {
    $log_ids = array_column($logs, 'id');
    $place   = implode(',', array_fill(0, count($log_ids), '?'));
    $dStmt = $pdo->prepare("SELECT * FROM logbook_days WHERE logbook_id IN ($place) ORDER BY logbook_id, sort_order, day_date");
    $dStmt->execute($log_ids);
    foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $days_by_log[$d['logbook_id']][] = $d;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($myDept); ?> Logbooks | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/logbook.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1><?php echo htmlspecialchars($myDept); ?> Logbook Reviews</h1>
            <p>All weekly entries submitted by students in your department.</p>
        </div>
        <div class="date-chip">
            <i class="fas fa-book"></i>&nbsp; <?php echo count($logs); ?> entries
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo $flash['type']; ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
        <div class="card">
            <p class="empty">No logbooks in your department yet.</p>
        </div>
    <?php else: foreach ($logs as $log): ?>
        <div class="review-week">
            <div class="review-week-head">
                <div>
                    <h4>
                        <?php echo htmlspecialchars($log['full_name']); ?>
                        <span class="muted small">&middot; <?php echo htmlspecialchars($log['index_number'] ?? '—'); ?></span>
                    </h4>
                    <div class="muted small" style="margin-top: 2px;">
                        Week <?php echo (int)$log['week_number']; ?>
                        <?php if ($log['start_date']): ?>
                            &middot; <?php echo htmlspecialchars(date('d M', strtotime($log['start_date']))); ?>
                            – <?php echo htmlspecialchars(date('d M Y', strtotime($log['end_date']))); ?>
                        <?php endif; ?>
                        <?php if ($log['company_name']): ?>
                            &middot; <?php echo htmlspecialchars($log['company_name']); ?>
                            <?php if ($log['company_department']): ?>
                                (<?php echo htmlspecialchars($log['company_department']); ?>)
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <?php if ((int)$log['is_submitted'] === 1): ?>
                        <span class="role-badge role-secretary">Submitted</span>
                    <?php else: ?>
                        <span class="role-badge role-archived">Draft</span>
                    <?php endif; ?>
                    <?php if (!empty($log['supervisor_signed_at'])): ?>
                        <span class="role-badge role-student" title="Supervisor signed off">Signed</span>
                    <?php endif; ?>
                    <?php if ((int)$log['is_reviewed'] === 1): ?>
                        <span class="role-badge role-admin" title="Reviewed by department">Reviewed</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($days_by_log[$log['id']])): ?>
                <?php foreach ($days_by_log[$log['id']] as $d): ?>
                    <div class="review-day">
                        <strong><?php echo htmlspecialchars($d['day_label']); ?></strong>
                        <span class="muted"><?php echo htmlspecialchars(date('d M Y', strtotime($d['day_date']))); ?></span>
                        <span><?php echo nl2br(htmlspecialchars($d['activities'] ?? '')); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted small">No daily activities recorded.</p>
            <?php endif; ?>

            <?php if (!empty($log['student_remarks'])): ?>
                <div class="remark-block remark-student">
                    <div class="who">Student's Remarks</div>
                    <?php echo nl2br(htmlspecialchars($log['student_remarks'])); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($log['supervisor_remarks'])): ?>
                <div class="remark-block remark-supervisor">
                    <div class="who">
                        Supervisor's Remarks
                        <?php if (!empty($log['supervisor_signed_by_name'])): ?>
                            — <?php echo htmlspecialchars($log['supervisor_signed_by_name']); ?>
                            <?php if (!empty($log['supervisor_signed_by_status'])): ?>
                                (<?php echo htmlspecialchars($log['supervisor_signed_by_status']); ?>)
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php echo nl2br(htmlspecialchars($log['supervisor_remarks'])); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($log['staff_comment'])): ?>
                <div class="remark-block remark-department">
                    <div class="who">Department Comment</div>
                    <?php echo nl2br(htmlspecialchars($log['staff_comment'])); ?>
                </div>
            <?php endif; ?>

            <!-- Comment form -->
            <details style="margin-top: 12px;">
                <summary class="muted small" style="cursor: pointer;">
                    <i class="fas fa-comment-dots"></i>&nbsp; Add / update department comment
                </summary>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="log_id" value="<?php echo (int)$log['id']; ?>">
                    <textarea name="staff_comment" rows="2"
                              placeholder="Internal note for this student's week..."
                              style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:6px;"><?php echo htmlspecialchars($log['staff_comment'] ?? ''); ?></textarea>
                    <div class="form-actions" style="margin-top: 8px;">
                        <button type="submit" name="add_comment" class="btn btn-primary btn-sm">
                            <i class="fas fa-save"></i>&nbsp; Save comment &amp; mark reviewed
                        </button>
                    </div>
                </form>
            </details>
        </div>
    <?php endforeach; endif; ?>
</div>
</body>
</html>
