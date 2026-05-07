<?php
// Public supervisor portal — token-gated, no account required.
// Bypass the password-change gate (no logged-in user here).
define('SKIP_PASSWORD_GATE', true);
require __DIR__ . '/includes/db.php';

$token = trim($_GET['t'] ?? '');
$placement = $token !== '' ? placement_by_supervisor_token($pdo, $token) : null;

// Existing evaluation (if any) so the CTA can flip to a "view" button.
$evaluation = null;
if ($placement) {
    $evStmt = $pdo->prepare("SELECT * FROM evaluations WHERE placement_id = ?");
    $evStmt->execute([(int)$placement['id']]);
    $evaluation = $evStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ----------------------------------------------------------------------
// POST: add / update supervisor's remarks for a specific week.
// ----------------------------------------------------------------------
$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $placement && isset($_POST['save_remarks'])) {
    $log_id   = (int)($_POST['log_id']        ?? 0);
    $remarks  = trim($_POST['remarks']        ?? '');
    $sup_name = trim($_POST['supervisor_name'] ?? '');
    $sup_stat = trim($_POST['supervisor_status'] ?? '');

    // Verify the log belongs to THIS placement.
    $check = $pdo->prepare("SELECT id FROM logbooks WHERE id = ? AND placement_id = ?");
    $check->execute([$log_id, (int)$placement['id']]);
    if (!$check->fetchColumn()) {
        $flash = ['type' => 'error', 'msg' => 'Unknown logbook week.'];
    } elseif ($sup_name === '') {
        $flash = ['type' => 'error', 'msg' => 'Please enter your name.'];
    } else {
        $pdo->prepare("
            UPDATE logbooks
            SET supervisor_remarks = ?,
                supervisor_signed_by_name = ?,
                supervisor_signed_by_status = ?,
                supervisor_signed_at = NOW()
            WHERE id = ?
        ")->execute([
            $remarks !== '' ? $remarks : null,
            $sup_name,
            $sup_stat !== '' ? $sup_stat : null,
            $log_id,
        ]);
        $flash = ['type' => 'success', 'msg' => "Remarks saved for week."];
    }
}

// ----------------------------------------------------------------------
// Fetch all submitted weeks for this placement (drafts hidden).
// ----------------------------------------------------------------------
$weeks       = [];
$days_by_log = [];
if ($placement) {
    $wStmt = $pdo->prepare("
        SELECT * FROM logbooks
        WHERE placement_id = ? AND is_submitted = 1
        ORDER BY week_number ASC, start_date ASC
    ");
    $wStmt->execute([(int)$placement['id']]);
    $weeks = $wStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($weeks) {
        $ids   = array_column($weeks, 'id');
        $place = implode(',', array_fill(0, count($ids), '?'));
        $dStmt = $pdo->prepare("SELECT * FROM logbook_days WHERE logbook_id IN ($place) ORDER BY logbook_id, sort_order, day_date");
        $dStmt->execute($ids);
        foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $days_by_log[$d['logbook_id']][] = $d;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Industrial Attachment — Supervisor Portal | RMU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/logbook.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/supervisor.css'); ?>">
</head>
<body class="auth-body no-sidebar sup-body">
    <div class="sup-shell">
        <div class="sup-banner">
            <img src="<?php echo asset('images/logo.jpg'); ?>" alt="RMU Logo">
            <div>
                <strong>Regional Maritime University</strong>
                <div class="muted small">Industrial Attachment — Supervisor Portal</div>
            </div>
        </div>

        <?php if (!$placement): ?>
            <div class="card">
                <div class="banner banner-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    This link is invalid or has expired. Please ask the student you supervise to send you a fresh one from their portal.
                </div>
            </div>
        <?php else: ?>

            <?php if ($flash['msg']): ?>
                <div class="banner banner-<?php echo $flash['type']; ?>">
                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <!-- Context -->
            <div class="card sup-context">
                <h3><i class="fas fa-user-graduate"></i>&nbsp; Student Under Your Supervision</h3>
                <div class="grid-2">
                    <div><span class="muted small">Name</span><br><strong><?php echo htmlspecialchars($placement['student_name']); ?></strong></div>
                    <div><span class="muted small">Index No.</span><br><strong><?php echo htmlspecialchars($placement['index_number'] ?? '—'); ?></strong></div>
                    <div><span class="muted small">Programme</span><br><strong><?php echo htmlspecialchars($placement['program'] ?? '—'); ?></strong></div>
                    <div><span class="muted small">Department</span><br><strong><?php echo htmlspecialchars($placement['department'] ?? '—'); ?></strong></div>
                    <div><span class="muted small">Period</span><br>
                        <strong>
                            <?php echo htmlspecialchars(date('d M Y', strtotime($placement['start_date']))); ?>
                            – <?php echo htmlspecialchars(date('d M Y', strtotime($placement['end_date']))); ?>
                        </strong>
                    </div>
                    <div><span class="muted small">Academic Year</span><br><strong><?php echo htmlspecialchars($placement['academic_year_name'] ?? '—'); ?></strong></div>
                </div>
            </div>

            <!-- Weekly logs -->
            <div class="card" style="margin-top: 18px;">
                <h3><i class="fas fa-book"></i>&nbsp; Weekly Logs</h3>
                <p class="muted">Review each submitted week and add your remarks. The student cannot edit a submitted week — your remarks are appended.</p>

                <?php if (empty($weeks)): ?>
                    <p class="empty">No submitted weeks yet — the student hasn't filed any logs.</p>
                <?php else: foreach ($weeks as $w): ?>
                    <div class="review-week">
                        <div class="review-week-head">
                            <h4>
                                Week <?php echo (int)$w['week_number']; ?>
                                <span class="muted small">
                                    &middot; <?php echo htmlspecialchars(date('d M', strtotime($w['start_date']))); ?>
                                    – <?php echo htmlspecialchars(date('d M Y', strtotime($w['end_date']))); ?>
                                </span>
                            </h4>
                            <?php if (!empty($w['supervisor_signed_at'])): ?>
                                <span class="role-badge role-student">
                                    Signed <?php echo htmlspecialchars(date('d M', strtotime($w['supervisor_signed_at']))); ?>
                                </span>
                            <?php else: ?>
                                <span class="role-badge role-archived">Awaiting your remarks</span>
                            <?php endif; ?>
                        </div>

                        <?php foreach ($days_by_log[$w['id']] ?? [] as $d): ?>
                            <div class="review-day">
                                <strong><?php echo htmlspecialchars($d['day_label']); ?></strong>
                                <span class="muted"><?php echo htmlspecialchars(date('d M Y', strtotime($d['day_date']))); ?></span>
                                <span><?php echo nl2br(htmlspecialchars($d['activities'] ?? '')); ?></span>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!empty($w['student_remarks'])): ?>
                            <div class="remark-block remark-student">
                                <div class="who">Student's Remarks</div>
                                <?php echo nl2br(htmlspecialchars($w['student_remarks'])); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="sup-remarks-form">
                            <input type="hidden" name="log_id" value="<?php echo (int)$w['id']; ?>">
                            <h5 class="section-h">Your Remarks</h5>
                            <textarea name="remarks" rows="3"
                                      placeholder="What you observed about the student's work this week..."><?php echo htmlspecialchars($w['supervisor_remarks'] ?? ''); ?></textarea>
                            <div class="grid-2" style="margin-top: 10px;">
                                <div class="field">
                                    <label>Your Name <span class="req">*</span></label>
                                    <input type="text" name="supervisor_name" required
                                           value="<?php echo htmlspecialchars($w['supervisor_signed_by_name'] ?? $placement['supervisor_name']); ?>">
                                </div>
                                <div class="field">
                                    <label>Your Title / Status</label>
                                    <input type="text" name="supervisor_status"
                                           value="<?php echo htmlspecialchars($w['supervisor_signed_by_status'] ?? ($placement['supervisor_title'] ?? '')); ?>"
                                           placeholder="e.g. IT Manager">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="save_remarks" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save"></i>&nbsp; Save Remarks
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Final evaluation CTA / status -->
            <div class="card sup-eval-card" style="margin-top: 18px;">
                <h3><i class="fas fa-clipboard-check"></i>&nbsp; Final Evaluation</h3>
                <?php if ($evaluation): ?>
                    <p>You submitted the evaluation on
                        <strong><?php echo htmlspecialchars(date('d M Y', strtotime($evaluation['submitted_at']))); ?></strong>.
                        Final score: <strong><?php echo (int)$evaluation['total_score']; ?> / 50</strong>.</p>
                    <a href="<?php echo BASE_URL; ?>supervisor_evaluation.php?t=<?php echo urlencode($token); ?>"
                       class="btn btn-ghost">
                        <i class="fas fa-eye"></i>&nbsp; View Submitted Evaluation
                    </a>
                <?php else: ?>
                    <p class="muted">At the end of the attachment, complete the final assessment to grade the student's overall performance.</p>
                    <a href="<?php echo BASE_URL; ?>supervisor_evaluation.php?t=<?php echo urlencode($token); ?>"
                       class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i>&nbsp; Open Evaluation Form
                    </a>
                    <p class="muted small" style="margin-top: 10px;">
                        The form mirrors the official RMU evaluation sheet — 8 criteria, 50 marks total.
                    </p>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
