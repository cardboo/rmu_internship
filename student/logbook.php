<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$cur_year   = current_academic_year($pdo);
$flash      = ['type' => '', 'msg' => ''];

// ----------------------------------------------------------------------
// Pull header info auto-filled at the top of the form
// (matches the "Name of Student / Programme / Index No / Name of
// Organisation / Department/Office" rows on the official PDF).
// ----------------------------------------------------------------------
$userStmt = $pdo->prepare("SELECT full_name, index_number, program FROM users WHERE id = ?");
$userStmt->execute([$student_id]);
$me = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$plStmt = $pdo->prepare("
    SELECT * FROM placements
    WHERE student_id = ? AND status = 'active'
    " . ($cur_year ? " AND academic_year_id = " . (int)$cur_year['id'] : "") . "
    ORDER BY created_at DESC LIMIT 1
");
$plStmt->execute([$student_id]);
$placement = $plStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// ----------------------------------------------------------------------
// Derive the week / day schedule from the placement dates so the
// logbook form doesn't ask the student to retype anything (item #3).
//
//   weeks_info = [
//     ['n'=>1, 'start'=>'YYYY-MM-DD', 'end'=>'YYYY-MM-DD',
//      'days'=>[['label'=>'Monday','date'=>'YYYY-MM-DD'], ...]],
//     ...
//   ]
// ----------------------------------------------------------------------
$weeks_info = [];
if ($placement) {
    try {
        $p_start = new DateTime($placement['start_date']);
        $p_end   = new DateTime($placement['end_date']);
        $span    = $p_start->diff($p_end)->days + 1;
        $weeks_total = (int)ceil($span / 7);
        for ($w = 1; $w <= $weeks_total; $w++) {
            $ws = (clone $p_start)->modify('+' . (($w - 1) * 7) . ' days');
            // Five work days from week-start, clipped to placement end.
            $we = (clone $ws)->modify('+4 days');
            if ($we > $p_end) $we = clone $p_end;
            $days = [];
            for ($d = 0; $d <= 4; $d++) {
                $dd = (clone $ws)->modify('+' . $d . ' days');
                if ($dd > $p_end) break;
                $days[] = ['label' => $dd->format('l'), 'date' => $dd->format('Y-m-d')];
            }
            $weeks_info[] = [
                'n'     => $w,
                'start' => $ws->format('Y-m-d'),
                'end'   => $we->format('Y-m-d'),
                'days'  => $days,
            ];
        }
    } catch (Exception $e) {
        $weeks_info = []; // placement has malformed dates — fall back gracefully
    }
}

// ----------------------------------------------------------------------
// Find the editing target if ?id= present, otherwise we're creating new.
// ----------------------------------------------------------------------
$editing = null;
$editing_days = [];
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM logbooks WHERE id = ? AND student_id = ?");
    $stmt->execute([(int)$_GET['id'], $student_id]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editing) {
        $dStmt = $pdo->prepare("SELECT * FROM logbook_days WHERE logbook_id = ? ORDER BY sort_order, day_date");
        $dStmt->execute([$editing['id']]);
        $editing_days = $dStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
$readonly = $editing && (int)$editing['is_submitted'] === 1;

// ----------------------------------------------------------------------
// POST: supervisor OTP — request a fresh code (emails it to the
// supervisor address on the placement record) for THIS logbook week.
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sup_otp_request']) && $editing && $placement) {
    $purpose = 'logbook:' . (int)$editing['id'];
    $code = issue_supervisor_otp($pdo, (int)$placement['id'], $placement['supervisor_email'], $purpose, 15);
    if ($code === null) {
        header("Location: logbook.php?id=" . (int)$editing['id'] . "&otp_err=migration");
        exit;
    }
    $body = "Hello " . htmlspecialchars($placement['supervisor_name']) . ",\n\n"
          . "Your verification code to sign off Week " . (int)$editing['week_number']
          . " of " . htmlspecialchars($_SESSION['name'] ?? 'a student') . "'s logbook is:\n\n"
          . "    $code\n\n"
          . "It expires in 15 minutes.\n\n"
          . "If you didn't expect this, ignore it.\n\n— RMU Internship Portal";
    try_send_email($pdo, $placement['supervisor_email'],
        'RMU supervisor sign-off code', $body, false);
    header("Location: logbook.php?id=" . (int)$editing['id'] . "&otp_sent=1");
    exit;
}

// ----------------------------------------------------------------------
// POST: supervisor remarks submission — verifies the OTP and writes
// the signed remarks atomically.
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sup_submit_remarks']) && $editing && $placement) {
    $code    = trim($_POST['sup_otp'] ?? '');
    $sup_nm  = trim($_POST['sup_name'] ?? '');
    $sup_st  = trim($_POST['sup_status'] ?? '');
    $remarks = trim($_POST['sup_remarks'] ?? '');

    $err = null;
    if ($sup_nm === '') {
        $err = 'Supervisor name is required.';
    } elseif ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        $err = 'Enter the 6-digit OTP that was sent to your email.';
    } elseif ((int)$editing['supervisor_signed_at'] !== 0 && !empty($editing['supervisor_signed_at'])) {
        $err = 'This week has already been signed off.';
    } else {
        $purpose = 'logbook:' . (int)$editing['id'];
        if (!verify_supervisor_otp($pdo, (int)$placement['id'], $purpose, $code)) {
            $err = 'OTP is invalid or has expired. Request a new one.';
        }
    }

    if ($err) {
        $flash = ['type' => 'error', 'msg' => $err];
    } else {
        $pdo->prepare("
            UPDATE logbooks
            SET supervisor_remarks            = ?,
                supervisor_signed_by_name     = ?,
                supervisor_signed_by_status   = ?,
                supervisor_signed_at          = NOW()
            WHERE id = ? AND student_id = ?
        ")->execute([
            $remarks !== '' ? $remarks : null,
            $sup_nm,
            $sup_st !== '' ? $sup_st : null,
            (int)$editing['id'], $student_id,
        ]);
        header("Location: logbook.php?id=" . (int)$editing['id'] . "&signed=1");
        exit;
    }
}

$flash = $flash ?? ['type' => '', 'msg' => ''];
if (empty($flash['msg'])) $flash = ['type' => '', 'msg' => ''];
if (($_GET['otp_sent'] ?? '') === '1') {
    $flash = ['type' => 'success', 'msg' => 'A 6-digit code was emailed to your supervisor. Ask them for it, then type it in below.'];
}
if (($_GET['signed'] ?? '') === '1') {
    $flash = ['type' => 'success', 'msg' => 'Supervisor sign-off saved. This week is now locked.'];
}
if (($_GET['otp_err'] ?? '') === 'migration') {
    $flash = ['type' => 'error', 'msg' => 'Supervisor sign-off is unavailable until migration 015 (supervisor_otps) is applied. Ask the admin to run it from phpMyAdmin.'];
}

// ----------------------------------------------------------------------
// POST: save draft / submit
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['save_draft']) || isset($_POST['submit_log']))) {

    if (!$placement || empty($weeks_info)) {
        $flash = ['type' => 'error', 'msg' => 'You need an active placement (with valid start and end dates) before logging weekly entries.'];
    } else {
        $week        = (int)($_POST['week_number']    ?? 0);
        $student_rem = trim($_POST['student_remarks'] ?? '');
        $log_id      = (int)($_POST['log_id']         ?? 0);   // 0 = new
        $finalise    = isset($_POST['submit_log']);

        // Resolve the week — dates are derived from the placement, NOT
        // the form. This prevents drift between week_number and the dates.
        $week_def = null;
        foreach ($weeks_info as $w) {
            if ($w['n'] === $week) { $week_def = $w; break; }
        }

        $err = null;
        if ($week < 1 || !$week_def) {
            $err = 'Pick a valid week number from the dropdown.';
        }

        // Duplicate-week guard (skip if we're editing the same row).
        if (!$err) {
            $dupStmt = $pdo->prepare("SELECT id FROM logbooks WHERE student_id = ? AND placement_id = ? AND week_number = ? AND id <> ? LIMIT 1");
            $dupStmt->execute([$student_id, (int)$placement['id'], $week, $log_id]);
            if ($dupStmt->fetchColumn()) {
                $err = "You already have a logbook entry for Week $week. Open that one to edit.";
            }
        }

        // Build the day rows from the placement-derived schedule. Each
        // row pairs the derived date with the student-typed activity
        // for that day's textarea ($_POST['day_activities'][i]).
        $days = [];
        if (!$err) {
            $has_any = false;
            foreach ($week_def['days'] as $i => $d) {
                $activity = trim($_POST['day_activities'][$i] ?? '');
                if ($activity !== '') $has_any = true;
                $days[] = [
                    'label'      => $d['label'],
                    'date'       => $d['date'],   // server-derived, NEVER from form
                    'activities' => $activity,
                    'sort'       => $i + 1,
                ];
            }
            if ($finalise && !$has_any) {
                $err = 'Add at least one day of activities before submitting.';
            }
        }
        $week_start = $week_def['start'] ?? '';
        $week_end   = $week_def['end']   ?? '';

        if (!$err) {
            try {
                $pdo->beginTransaction();
                if ($log_id) {
                    $pdo->prepare("
                        UPDATE logbooks
                        SET week_number = ?, start_date = ?, end_date = ?,
                            student_remarks = ?, is_submitted = ?,
                            placement_id = COALESCE(placement_id, ?),
                            academic_year_id = COALESCE(academic_year_id, ?)
                        WHERE id = ? AND student_id = ?
                    ")->execute([
                        $week, $week_start, $week_end, $student_rem !== '' ? $student_rem : null,
                        $finalise ? 1 : 0,
                        (int)$placement['id'],
                        $cur_year ? (int)$cur_year['id'] : null,
                        $log_id, $student_id,
                    ]);
                    // Replace days
                    $pdo->prepare("DELETE FROM logbook_days WHERE logbook_id = ?")->execute([$log_id]);
                } else {
                    $pdo->prepare("
                        INSERT INTO logbooks
                            (student_id, placement_id, week_number, start_date, end_date,
                             student_remarks, is_submitted, academic_year_id, activities)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, '')
                    ")->execute([
                        $student_id,
                        (int)$placement['id'],
                        $week, $week_start, $week_end,
                        $student_rem !== '' ? $student_rem : null,
                        $finalise ? 1 : 0,
                        $cur_year ? (int)$cur_year['id'] : null,
                    ]);
                    $log_id = (int)$pdo->lastInsertId();
                }

                $insDay = $pdo->prepare("
                    INSERT INTO logbook_days (logbook_id, day_label, day_date, activities, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($days as $d) {
                    if ($d['date'] === '' && $d['activities'] === '') continue;
                    $insDay->execute([
                        $log_id, $d['label'],
                        $d['date'] !== '' ? $d['date'] : $week_start,
                        $d['activities'] !== '' ? $d['activities'] : null,
                        $d['sort'],
                    ]);
                }
                $pdo->commit();

                $msg = $finalise ? 'submitted' : 'saved';
                header("Location: logbook.php?id=$log_id&msg=$msg");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $err = 'Database error: ' . $e->getMessage();
            }
        }

        if ($err) $flash = ['type' => 'error', 'msg' => $err];
    }
}

if (($_GET['msg'] ?? '') === 'saved')     $flash = ['type' => 'success', 'msg' => 'Draft saved.'];
if (($_GET['msg'] ?? '') === 'submitted') $flash = ['type' => 'success', 'msg' => 'Logbook submitted. Your supervisor will be notified to add their remarks.'];

// ----------------------------------------------------------------------
// All my logs (newest first) — for the list at the bottom.
// ----------------------------------------------------------------------
$listStmt = $pdo->prepare("
    SELECT l.*, COUNT(d.id) AS day_count
    FROM logbooks l
    LEFT JOIN logbook_days d ON d.logbook_id = l.id
    WHERE l.student_id = ?
    GROUP BY l.id
    ORDER BY l.week_number DESC, l.start_date DESC
");
$listStmt->execute([$student_id]);
$all_logs = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Suggest the next week number for a fresh entry.
$next_week = 1;
foreach ($all_logs as $l) {
    if ((int)$l['week_number'] >= $next_week) $next_week = (int)$l['week_number'] + 1;
}

// Pre-fill values
if ($editing) {
    $f_week  = $editing['week_number'];
    $f_start = $editing['start_date'];
    $f_end   = $editing['end_date'];
    $f_rem   = $editing['student_remarks'] ?? '';
    // Build a Mon-Fri map from the days that exist
    $by_label = [];
    foreach ($editing_days as $d) $by_label[$d['day_label']] = $d;
} else {
    $f_week  = $next_week;
    $f_start = '';
    $f_end   = '';
    $f_rem   = '';
    $by_label = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Logbook | Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/logbook.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Weekly Logbook</h1>
            <p>One entry per week, mirroring the official RMU log sheet. Save as draft as you go, submit when the week is complete.</p>
        </div>
        <?php if ($placement): ?>
            <div class="date-chip">
                <i class="fas fa-building"></i>&nbsp; <?php echo htmlspecialchars($placement['company_name']); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo $flash['type']; ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$placement): ?>
        <div class="banner banner-warning">
            <i class="fas fa-exclamation-circle"></i>
            You haven't registered a placement yet. <a href="placement.php">Register your placement</a> first — your weekly logs need a host organisation to attach to.
        </div>
    <?php endif; ?>

    <!-- Auto-filled header info -->
    <div class="card log-header">
        <div class="grid-2">
            <div><span class="muted small">Name of Student</span><br><strong><?php echo htmlspecialchars($me['full_name'] ?? ''); ?></strong></div>
            <div><span class="muted small">Programme</span><br><strong><?php echo htmlspecialchars($me['program'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Index No.</span><br><strong><?php echo htmlspecialchars($me['index_number'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Name of Organisation</span><br><strong><?php echo htmlspecialchars($placement['company_name'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Department / Office</span><br><strong><?php echo htmlspecialchars($placement['company_department'] ?? '—'); ?></strong></div>
        </div>
    </div>

    <!-- New / edit / view -->
    <?php if ($placement): ?>
        <div class="card" style="margin-top: 18px;">
            <h3>
                <?php if ($readonly): ?>
                    <i class="fas fa-lock"></i>&nbsp; Week <?php echo (int)$f_week; ?> &mdash; submitted
                <?php elseif ($editing): ?>
                    <i class="fas fa-edit"></i>&nbsp; Edit Week <?php echo (int)$f_week; ?> draft
                <?php else: ?>
                    <i class="fas fa-plus-circle"></i>&nbsp; New Weekly Entry
                <?php endif; ?>
            </h3>

            <form method="POST" autocomplete="off" id="logForm">
                <?php if ($editing): ?>
                    <input type="hidden" name="log_id" value="<?php echo (int)$editing['id']; ?>">
                <?php endif; ?>

                <?php
                    // Build sort-indexed map of existing days for edit mode
                    // (the auto-fill JS fills new rows otherwise).
                    $by_sort = [];
                    foreach ($editing_days as $d) $by_sort[(int)$d['sort_order']] = $d;
                ?>
                <div class="grid-3">
                    <div class="field">
                        <label>Week Number</label>
                        <select name="week_number" id="week_select" required
                                <?php echo $readonly ? 'disabled' : ''; ?>>
                            <?php foreach ($weeks_info as $w): ?>
                                <option value="<?php echo (int)$w['n']; ?>"
                                        data-start="<?php echo htmlspecialchars($w['start']); ?>"
                                        data-end="<?php echo htmlspecialchars($w['end']); ?>"
                                        <?php echo ((int)$f_week === (int)$w['n']) ? 'selected' : ''; ?>>
                                    Week <?php echo (int)$w['n']; ?>
                                    (<?php echo htmlspecialchars(date('d M', strtotime($w['start']))); ?>
                                     – <?php echo htmlspecialchars(date('d M Y', strtotime($w['end']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($readonly): ?>
                            <input type="hidden" name="week_number" value="<?php echo (int)$f_week; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Week Beginning</label>
                        <div class="readonly-chip" id="week_start_disp"><?php echo htmlspecialchars($f_start ?: '—'); ?></div>
                    </div>
                    <div class="field">
                        <label>Week Ending</label>
                        <div class="readonly-chip" id="week_end_disp"><?php echo htmlspecialchars($f_end ?: '—'); ?></div>
                    </div>
                </div>

                <h4 class="section-h">Daily Activities</h4>
                <p class="muted small" style="margin: -4px 0 8px;">
                    The day labels and dates are taken from your placement period. Just type what you did.
                </p>
                <table class="day-table">
                    <thead>
                        <tr>
                            <th style="width: 130px;">Day</th>
                            <th style="width: 150px;">Date</th>
                            <th>Activities Undertaken</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            // Pick the schedule for the currently-selected week (if any).
                            $cur_week_def = null;
                            foreach ($weeks_info as $w) {
                                if ((int)$w['n'] === (int)$f_week) { $cur_week_def = $w; break; }
                            }
                            $schedule = $cur_week_def['days'] ?? [];
                        ?>
                        <?php for ($i = 0; $i < 5; $i++):
                            // Prefer stored data (edit mode), else placement-derived (new mode).
                            $existing = $by_sort[$i + 1] ?? null;
                            $label    = $existing['day_label'] ?? ($schedule[$i]['label'] ?? '');
                            $date     = $existing['day_date']  ?? ($schedule[$i]['date']  ?? '');
                            $activity = $existing['activities'] ?? '';
                            $hidden   = ($label === '' && $date === '') ? 'style="display:none;"' : '';
                        ?>
                            <tr id="day_row_<?php echo $i; ?>" <?php echo $hidden; ?>>
                                <th id="day_label_<?php echo $i; ?>"><?php echo htmlspecialchars($label); ?></th>
                                <td>
                                    <span class="day-date readonly-chip" id="day_date_<?php echo $i; ?>">
                                        <?php echo htmlspecialchars($date ?: '—'); ?>
                                    </span>
                                </td>
                                <td>
                                    <textarea name="day_activities[<?php echo $i; ?>]" rows="2"
                                              <?php echo $readonly ? 'disabled' : ''; ?>><?php echo htmlspecialchars($activity); ?></textarea>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <h4 class="section-h">Student's Remarks</h4>
                <div class="field">
                    <textarea name="student_remarks" rows="3"
                              <?php echo $readonly ? 'disabled' : ''; ?>><?php echo htmlspecialchars($f_rem); ?></textarea>
                </div>

                <?php if ($editing && (int)$editing['is_submitted'] === 1): ?>
                    <h4 class="section-h">Supervisor's Remarks</h4>
                    <?php if (!empty($editing['supervisor_signed_at'])): ?>
                        <!-- Locked: read-only signed entry -->
                        <div class="sup-block">
                            <?php if (!empty($editing['supervisor_remarks'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($editing['supervisor_remarks'])); ?></p>
                            <?php endif; ?>
                            <p class="muted small">
                                <i class="fas fa-lock"></i>&nbsp;
                                Signed by <strong><?php echo htmlspecialchars($editing['supervisor_signed_by_name'] ?? ''); ?></strong>
                                <?php if (!empty($editing['supervisor_signed_by_status'])): ?>
                                    (<?php echo htmlspecialchars($editing['supervisor_signed_by_status']); ?>)
                                <?php endif; ?>
                                on <?php echo htmlspecialchars(date('d M Y', strtotime($editing['supervisor_signed_at']))); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- OTP sign-off panel: supervisor sits at the student's machine -->
                        <div class="sup-otp-panel">
                            <p class="muted small">
                                <i class="fas fa-shield-alt"></i>&nbsp;
                                Supervisor (<strong><?php echo htmlspecialchars($placement['supervisor_email'] ?? ''); ?></strong>):
                                request a 6-digit code to verify your identity, then add your remarks below.
                                Once submitted, this week is locked and cannot be edited.
                            </p>

                            <form method="POST" style="margin-top: 6px;">
                                <button type="submit" name="sup_otp_request" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-paper-plane"></i>&nbsp; Send OTP to supervisor's email
                                </button>
                            </form>

                            <form method="POST" style="margin-top: 16px;">
                                <div class="grid-2">
                                    <div class="field">
                                        <label>6-digit code <span class="req">*</span></label>
                                        <input type="text" name="sup_otp" inputmode="numeric"
                                               pattern="\d{6}" maxlength="6" required
                                               placeholder="000000">
                                    </div>
                                    <div class="field">
                                        <label>Supervisor's name <span class="req">*</span></label>
                                        <input type="text" name="sup_name" required
                                               value="<?php echo htmlspecialchars($placement['supervisor_name'] ?? ''); ?>">
                                    </div>
                                    <div class="field">
                                        <label>Title / status</label>
                                        <input type="text" name="sup_status"
                                               value="<?php echo htmlspecialchars($placement['supervisor_title'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="field">
                                    <label>Remarks</label>
                                    <textarea name="sup_remarks" rows="3"
                                              placeholder="What you observed about the student's work this week..."></textarea>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="sup_submit_remarks" class="btn btn-primary">
                                        <i class="fas fa-lock"></i>&nbsp; Sign &amp; Lock Week
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!$readonly): ?>
                    <div class="form-actions">
                        <a href="logbook.php" class="btn btn-ghost">Cancel</a>
                        <button type="submit" name="save_draft" class="btn btn-ghost">
                            <i class="fas fa-save"></i>&nbsp; Save Draft
                        </button>
                        <button type="submit" name="submit_log" class="btn btn-primary"
                                onclick="return confirm('Submit this week? Once submitted you cannot edit it.');">
                            <i class="fas fa-check-circle"></i>&nbsp; Submit Week
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>

    <!-- All my entries -->
    <div class="card" style="margin-top: 25px;">
        <h3><i class="fas fa-list"></i>&nbsp; All My Weekly Entries</h3>
        <?php if (empty($all_logs)): ?>
            <p class="empty">No entries yet.</p>
        <?php else: ?>
            <table class="prog-table">
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Supervisor</th>
                        <th class="actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_logs as $l): ?>
                        <tr>
                            <td><strong>Week <?php echo (int)$l['week_number']; ?></strong></td>
                            <td>
                                <?php if ($l['start_date']): ?>
                                    <?php echo htmlspecialchars(date('d M', strtotime($l['start_date']))); ?>
                                    – <?php echo htmlspecialchars(date('d M Y', strtotime($l['end_date']))); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int)$l['day_count']; ?> day<?php echo $l['day_count'] == 1 ? '' : 's'; ?></td>
                            <td>
                                <?php if ((int)$l['is_submitted'] === 1): ?>
                                    <span class="role-badge role-secretary">Submitted</span>
                                <?php else: ?>
                                    <span class="role-badge role-archived">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['supervisor_signed_at'])): ?>
                                    <span class="role-badge role-student">Signed</span>
                                <?php else: ?>
                                    <span class="muted small">— pending —</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-col">
                                <a class="btn btn-ghost btn-sm" href="logbook.php?id=<?php echo (int)$l['id']; ?>">
                                    <i class="fas <?php echo (int)$l['is_submitted'] === 1 ? 'fa-eye' : 'fa-edit'; ?>"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
// When the student picks a week from the dropdown, the day labels +
// dates (and the week begin/end display) auto-fill from the schedule
// derived from their placement period (item #3).
const WEEKS = <?php echo json_encode($weeks_info); ?>;
const weekSel = document.getElementById('week_select');

function applyWeek(n) {
    const w = WEEKS.find(x => x.n === Number(n));
    if (!w) return;
    document.getElementById('week_start_disp').textContent = w.start;
    document.getElementById('week_end_disp').textContent   = w.end;
    for (let i = 0; i < 5; i++) {
        const row = document.getElementById('day_row_' + i);
        const lbl = document.getElementById('day_label_' + i);
        const dt  = document.getElementById('day_date_'  + i);
        if (!row || !lbl || !dt) continue;
        if (i < w.days.length) {
            row.style.display = '';
            lbl.textContent = w.days[i].label;
            dt.textContent  = w.days[i].date;
        } else {
            row.style.display = 'none';
        }
    }
}

if (weekSel) {
    weekSel.addEventListener('change', () => applyWeek(weekSel.value));
}
</script>
</body>
</html>
