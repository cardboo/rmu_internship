<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$cur_year   = current_academic_year($pdo);
$flash      = ['type' => '', 'msg' => ''];

// Grace days after a week's last working day during which the student
// can still write up / submit that week (covers the weekend). After
// this window the week locks permanently (Option A — strict gating).
const LOGBOOK_GRACE_DAYS = 2;

$today = new DateTime('today');

/** Helper: render a stored Y-m-d date as dd-mm-yyyy for display. */
function fmt_date(?string $ymd): string {
    if (!$ymd) return '—';
    $t = strtotime($ymd);
    return $t ? date('d-m-Y', $t) : '—';
}

// ----------------------------------------------------------------------
// Pull header info auto-filled at the top of the form.
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

// Once the final evaluation is submitted the placement is marked
// 'completed' (so the WHERE status='active' above misses it). Re-fetch
// the most recent placement regardless of status so we can show the
// "logbook closed" state instead of "no placement".
if (!$placement) {
    $anyStmt = $pdo->prepare("
        SELECT * FROM placements WHERE student_id = ?
        " . ($cur_year ? " AND academic_year_id = " . (int)$cur_year['id'] : "") . "
        ORDER BY created_at DESC LIMIT 1
    ");
    $anyStmt->execute([$student_id]);
    $placement = $anyStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Has the final evaluation been submitted? If so, the logbook is closed
// for good — no new entries, no edits (supervisor recommendation #5).
$eval_exists = false;
if ($placement) {
    $evChk = $pdo->prepare("SELECT 1 FROM evaluations WHERE placement_id = ? LIMIT 1");
    $evChk->execute([(int)$placement['id']]);
    $eval_exists = (bool)$evChk->fetchColumn();
}

// ----------------------------------------------------------------------
// Derive the week / day schedule from the placement dates and tag each
// week with a time-gate state relative to today.
//
//   _state ∈ { 'not_started', 'open', 'closed' }
//     not_started : today < week start
//     open        : week start ≤ today ≤ week end + grace
//     closed      : today > week end + grace
//
// Because each placement week is Mon-Fri (5 days) and the next week
// starts 7 days after the previous, the 2-day grace exactly fills the
// weekend gap — so at most ONE week is 'open' at any moment.
// ----------------------------------------------------------------------
$weeks_info  = [];
$active_week = null;       // the single currently-open week (or null)
$logging_ended = false;
$not_started   = false;

if ($placement) {
    try {
        $p_start = new DateTime($placement['start_date']);
        $p_end   = new DateTime($placement['end_date']);
        $span    = $p_start->diff($p_end)->days + 1;
        $weeks_total = (int)ceil($span / 7);
        for ($w = 1; $w <= $weeks_total; $w++) {
            $ws = (clone $p_start)->modify('+' . (($w - 1) * 7) . ' days');
            $we = (clone $ws)->modify('+4 days');
            if ($we > $p_end) $we = clone $p_end;
            $days = [];
            for ($d = 0; $d <= 4; $d++) {
                $dd = (clone $ws)->modify('+' . $d . ' days');
                if ($dd > $p_end) break;
                $days[] = ['label' => $dd->format('l'), 'date' => $dd->format('Y-m-d')];
            }
            // State
            $we_grace = (clone $we)->modify('+' . LOGBOOK_GRACE_DAYS . ' days');
            if ($today < $ws)        $state = 'not_started';
            elseif ($today > $we_grace) $state = 'closed';
            else                     $state = 'open';

            $info = [
                'n'     => $w,
                'start' => $ws->format('Y-m-d'),
                'end'   => $we->format('Y-m-d'),
                'days'  => $days,
                '_state'=> $state,
            ];
            $weeks_info[] = $info;
            if ($state === 'open') $active_week = $info;
        }
        // Boundary states for the banners.
        if (!empty($weeks_info)) {
            $first_start = new DateTime($weeks_info[0]['start']);
            $last_end    = (new DateTime(end($weeks_info)['end']))->modify('+' . LOGBOOK_GRACE_DAYS . ' days');
            if ($today < $first_start) $not_started   = true;
            if ($today > $last_end)    $logging_ended = true;
        }
    } catch (Exception $e) {
        $weeks_info = [];
    }
}

/** Find a week_info row by its number. */
function week_by_n(array $weeks, int $n): ?array {
    foreach ($weeks as $w) if ((int)$w['n'] === $n) return $w;
    return null;
}

// ----------------------------------------------------------------------
// Editing target. If ?id= present, load it. Otherwise auto-target the
// currently-open week (loading its existing draft if one exists, so
// Save Draft updates that row rather than creating a duplicate —
// per-week independence, supervisor recommendation #2 / Option B).
// ----------------------------------------------------------------------
$editing = null;
$editing_days = [];
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM logbooks WHERE id = ? AND student_id = ?");
    $stmt->execute([(int)$_GET['id'], $student_id]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} elseif ($active_week && $placement && !$eval_exists) {
    $stmt = $pdo->prepare("SELECT * FROM logbooks WHERE student_id = ? AND placement_id = ? AND week_number = ? LIMIT 1");
    $stmt->execute([$student_id, (int)$placement['id'], (int)$active_week['n']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($editing) {
    $dStmt = $pdo->prepare("SELECT * FROM logbook_days WHERE logbook_id = ? ORDER BY sort_order, day_date");
    $dStmt->execute([$editing['id']]);
    $editing_days = $dStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Which week does the form represent?
$form_week_n = $editing ? (int)$editing['week_number'] : ($active_week['n'] ?? 0);
$form_week   = week_by_n($weeks_info, $form_week_n);

// Is the form editable? Only the open week, only when not submitted and
// no evaluation exists.
$is_submitted = $editing && (int)$editing['is_submitted'] === 1;
$editable = $placement && !$eval_exists && !$is_submitted
            && $form_week && $form_week['_state'] === 'open';
$readonly = !$editable;

// ----------------------------------------------------------------------
// POST: supervisor OTP — request a fresh code for THIS logbook week.
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
    } elseif (!empty($editing['supervisor_signed_at'])) {
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

    if ($eval_exists) {
        $flash = ['type' => 'error', 'msg' => 'Your final evaluation has been submitted — the logbook is now closed and cannot be edited.'];
    } elseif (!$placement || empty($weeks_info)) {
        $flash = ['type' => 'error', 'msg' => 'You need an active placement (with valid start and end dates) before logging weekly entries.'];
    } else {
        $week        = (int)($_POST['week_number']    ?? 0);
        $student_rem = trim($_POST['student_remarks'] ?? '');
        $log_id      = (int)($_POST['log_id']         ?? 0);
        $finalise    = isset($_POST['submit_log']);

        // The week is server-resolved and MUST be the currently-open one.
        // The form has no week picker — this guards against tampering.
        $week_def = week_by_n($weeks_info, $week);

        $err = null;
        if (!$week_def) {
            $err = 'Invalid week.';
        } elseif ($week_def['_state'] === 'not_started') {
            $err = 'That week has not started yet — you can only log the current week.';
        } elseif ($week_def['_state'] === 'closed') {
            $err = 'That week is closed for editing. You can only fill the current week within its allowed window.';
        }

        // Duplicate-week guard (skip if editing the same row).
        if (!$err) {
            $dupStmt = $pdo->prepare("SELECT id FROM logbooks WHERE student_id = ? AND placement_id = ? AND week_number = ? AND id <> ? LIMIT 1");
            $dupStmt->execute([$student_id, (int)$placement['id'], $week, $log_id]);
            if ($dupStmt->fetchColumn()) {
                $err = "You already have a logbook entry for Week $week. Open that one to edit.";
            }
        }

        // Build day rows. Reject activities for days whose date is in the
        // future — only days up to today are editable (Option A).
        $days = [];
        if (!$err) {
            $has_any = false;
            foreach ($week_def['days'] as $i => $d) {
                $day_dt    = new DateTime($d['date']);
                $is_future = $day_dt > $today;
                $activity  = $is_future ? '' : trim($_POST['day_activities'][$i] ?? '');
                if ($activity !== '') $has_any = true;
                $days[] = [
                    'label'      => $d['label'],
                    'date'       => $d['date'],
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

// Pre-fill values for the form.
if ($editing) {
    $f_week  = $editing['week_number'];
    $f_start = $editing['start_date'];
    $f_end   = $editing['end_date'];
    $f_rem   = $editing['student_remarks'] ?? '';
} elseif ($active_week) {
    $f_week  = $active_week['n'];
    $f_start = $active_week['start'];
    $f_end   = $active_week['end'];
    $f_rem   = '';
} else {
    $f_week = 0; $f_start = ''; $f_end = ''; $f_rem = '';
}

$by_sort = [];
foreach ($editing_days as $d) $by_sort[(int)$d['sort_order']] = $d;
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
    <style>
        .day-locked { opacity: 0.55; }
        .day-locked textarea { background: #f3f4f6; cursor: not-allowed; }
        .day-future-note { color: #92400e; font-size: 0.72rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Weekly Logbook</h1>
            <p>One entry per week, mirroring the official RMU log sheet. The current week opens automatically — fill each day as you go.</p>
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
    <?php if ($placement): ?>
    <div class="card log-header">
        <div class="grid-2">
            <div><span class="muted small">Name of Student</span><br><strong><?php echo htmlspecialchars($me['full_name'] ?? ''); ?></strong></div>
            <div><span class="muted small">Programme</span><br><strong><?php echo htmlspecialchars($me['program'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Index No.</span><br><strong><?php echo htmlspecialchars($me['index_number'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Name of Organisation</span><br><strong><?php echo htmlspecialchars($placement['company_name'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Department / Office</span><br><strong><?php echo htmlspecialchars($placement['company_department'] ?? '—'); ?></strong></div>
            <div><span class="muted small">Internship Period</span><br><strong><?php echo fmt_date($placement['start_date']); ?> &ndash; <?php echo fmt_date($placement['end_date']); ?></strong></div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ------- State banners that explain why the form is / isn't shown -------
    if ($placement && $eval_exists): ?>
        <div class="banner banner-success" style="margin-top: 14px;">
            <i class="fas fa-lock"></i>
            Your final evaluation has been submitted. The logbook is now <strong>closed</strong> and read-only — you cannot add or edit weekly entries.
        </div>
    <?php elseif ($placement && $not_started): ?>
        <div class="banner banner-warning" style="margin-top: 14px;">
            <i class="fas fa-hourglass-start"></i>
            Your internship hasn't started yet. The logbook opens on <strong><?php echo fmt_date($placement['start_date']); ?></strong>.
        </div>
    <?php elseif ($placement && $logging_ended): ?>
        <div class="banner banner-warning" style="margin-top: 14px;">
            <i class="fas fa-flag-checkered"></i>
            Your internship logging period ended on <strong><?php echo fmt_date($placement['end_date']); ?></strong>.
            All weeks are now locked. If your supervisor evaluation isn't done yet,
            <a href="evaluation.php">complete the final evaluation</a>.
        </div>
    <?php endif; ?>

    <!-- New / edit / view form (only when there's an open week to act on) -->
    <?php if ($placement && !$eval_exists && ($active_week || ($editing && $form_week))): ?>
        <div class="card" style="margin-top: 18px;">
            <h3>
                <?php if ($is_submitted): ?>
                    <i class="fas fa-lock"></i>&nbsp; Week <?php echo (int)$f_week; ?> &mdash; submitted
                <?php elseif ($readonly): ?>
                    <i class="fas fa-eye"></i>&nbsp; Week <?php echo (int)$f_week; ?> &mdash; <?php echo $form_week && $form_week['_state'] === 'closed' ? 'closed (read-only)' : 'view'; ?>
                <?php else: ?>
                    <i class="fas fa-pen"></i>&nbsp; Week <?php echo (int)$f_week; ?> &mdash; current week
                <?php endif; ?>
            </h3>
            <p class="muted small" style="margin-top:-6px;">
                <?php echo fmt_date($f_start); ?> &ndash; <?php echo fmt_date($f_end); ?>
            </p>

            <form method="POST" autocomplete="off" id="logForm">
                <input type="hidden" name="week_number" value="<?php echo (int)$f_week; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="log_id" value="<?php echo (int)$editing['id']; ?>">
                <?php endif; ?>

                <h4 class="section-h">Daily Activities</h4>
                <p class="muted small" style="margin: -4px 0 8px;">
                    Each day unlocks on its own date — you can't fill a day before it arrives.
                    Days from earlier this week stay editable until the week closes.
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
                            $schedule = $form_week['days'] ?? [];
                            for ($i = 0; $i < 5; $i++):
                                $existing = $by_sort[$i + 1] ?? null;
                                $label    = $existing['day_label'] ?? ($schedule[$i]['label'] ?? '');
                                $date     = $existing['day_date']  ?? ($schedule[$i]['date']  ?? '');
                                $activity = $existing['activities'] ?? '';
                                if ($label === '' && $date === '') continue;
                                $day_is_future = $date !== '' && (new DateTime($date)) > $today;
                                $day_disabled  = $readonly || $day_is_future;
                        ?>
                            <tr class="<?php echo $day_is_future ? 'day-locked' : ''; ?>">
                                <th><?php echo htmlspecialchars($label); ?></th>
                                <td>
                                    <span class="day-date readonly-chip"><?php echo fmt_date($date); ?></span>
                                </td>
                                <td>
                                    <textarea name="day_activities[<?php echo $i; ?>]" rows="2"
                                              <?php echo $day_disabled ? 'disabled' : ''; ?>><?php echo htmlspecialchars($activity); ?></textarea>
                                    <?php if ($day_is_future && !$readonly): ?>
                                        <span class="day-future-note"><i class="fas fa-lock"></i> opens <?php echo fmt_date($date); ?></span>
                                    <?php endif; ?>
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
                                on <?php echo fmt_date($editing['supervisor_signed_at']); ?>
                            </p>
                        </div>
                    <?php else: ?>
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

                            <form method="POST" style="margin-top: 16px;" class="otp-gated-form"
                                  data-placement-id="<?php echo (int)$placement['id']; ?>"
                                  data-purpose="logbook:<?php echo (int)$editing['id']; ?>">
                                <div class="field">
                                    <label>6-digit code <span class="req">*</span></label>
                                    <input type="text" name="sup_otp" class="otp-input" inputmode="numeric"
                                           pattern="\d{6}" maxlength="6" required
                                           autocomplete="off"
                                           placeholder="000000">
                                    <small class="otp-status muted small">Other fields unlock once the correct code is verified.</small>
                                </div>

                                <fieldset class="otp-locked" disabled style="border:none; padding:0; margin:0; opacity:0.55;">
                                    <div class="grid-2">
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
                                </fieldset>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($editable): ?>
                    <div class="form-actions">
                        <a href="logbook.php" class="btn btn-ghost">Reset</a>
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
                    <?php foreach ($all_logs as $l):
                        $wn = (int)$l['week_number'];
                        $wstate = ($w = week_by_n($weeks_info, $wn)) ? $w['_state'] : null;
                    ?>
                        <tr>
                            <td><strong>Week <?php echo $wn; ?></strong></td>
                            <td>
                                <?php echo $l['start_date'] ? fmt_date($l['start_date']) . ' – ' . fmt_date($l['end_date']) : '—'; ?>
                            </td>
                            <td><?php echo (int)$l['day_count']; ?> day<?php echo $l['day_count'] == 1 ? '' : 's'; ?></td>
                            <td>
                                <?php if ((int)$l['is_submitted'] === 1): ?>
                                    <span class="role-badge role-secretary">Submitted</span>
                                <?php elseif ($wstate === 'closed'): ?>
                                    <span class="role-badge role-archived">Closed (draft)</span>
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
                                <?php
                                    // Edit icon only when the entry is still actionable
                                    // (open week, not submitted, no eval). Otherwise view.
                                    $row_editable = !$eval_exists && (int)$l['is_submitted'] === 0 && $wstate === 'open';
                                ?>
                                <a class="btn btn-ghost btn-sm" href="logbook.php?id=<?php echo (int)$l['id']; ?>">
                                    <i class="fas <?php echo $row_editable ? 'fa-edit' : 'fa-eye'; ?>"></i>
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
// OTP-gated supervisor sign-off: the rest of the form stays disabled
// until the entered OTP matches a live (unconsumed, unexpired) code on
// the server. Peek via api/check_supervisor_otp.php (doesn't consume).
const BASE_URL_LB = <?php echo json_encode(BASE_URL); ?>;
document.querySelectorAll('.otp-gated-form').forEach(form => {
    const otp         = form.querySelector('.otp-input');
    const lock        = form.querySelector('.otp-locked');
    const statusEl    = form.querySelector('.otp-status');
    const placementId = form.dataset.placementId;
    const purpose     = form.dataset.purpose;
    if (!otp || !lock || !placementId || !purpose) return;

    let timer;
    function lockFields(ok, msg, colour) {
        lock.disabled       = !ok;
        lock.style.opacity  = ok ? '1' : '0.55';
        if (statusEl) {
            statusEl.textContent = msg;
            statusEl.style.color = colour;
        }
    }

    function refresh() {
        const v = (otp.value || '').replace(/\D/g, '').slice(0, 6);
        otp.value = v;
        if (v.length !== 6) {
            lockFields(false, 'Other fields unlock once the correct code is verified.', '#64748b');
            return;
        }
        lockFields(false, 'Checking…', '#64748b');
        clearTimeout(timer);
        timer = setTimeout(async () => {
            try {
                const fd = new FormData();
                fd.append('placement_id', placementId);
                fd.append('purpose',      purpose);
                fd.append('code',         v);
                const r = await fetch(BASE_URL_LB + 'api/check_supervisor_otp.php', { method: 'POST', body: fd });
                const j = await r.json();
                if (j.ok) {
                    lockFields(true, '✓ Code accepted — you can fill in the form below.', '#166534');
                } else {
                    lockFields(false, '✗ ' + (j.error || 'Invalid code.'), '#991b1b');
                }
            } catch (e) {
                lockFields(false, '⚠ Could not verify (network).', '#991b1b');
            }
        }, 250);
    }
    otp.addEventListener('input', refresh);
    refresh();
});
</script>
</body>
</html>
