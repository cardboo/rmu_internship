<?php
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$cur_year   = current_academic_year($pdo);

// Current active placement
$plStmt = $pdo->prepare("
    SELECT * FROM placements
    WHERE student_id = ?
    " . ($cur_year ? " AND academic_year_id = " . (int)$cur_year['id'] : "") . "
    ORDER BY created_at DESC LIMIT 1
");
$plStmt->execute([$student_id]);
$placement = $plStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Existing evaluation (if any) — once it exists, the page is read-only.
$evaluation = null;
if ($placement) {
    $evStmt = $pdo->prepare("SELECT * FROM evaluations WHERE placement_id = ?");
    $evStmt->execute([(int)$placement['id']]);
    $evaluation = $evStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// The eight criteria, mirroring the official RMU PDF.
$CRITERIA = [
    ['key' => 'responsibility',  'label' => 'Acceptance of responsibility',          'desc' => 'Seeks and accepts responsibility at all times', 'max' => 5],
    ['key' => 'reliability',     'label' => 'Reliability under pressure',            'desc' => 'Performs competently under pressure',           'max' => 5],
    ['key' => 'knowledge',       'label' => 'Application of professional knowledge', 'desc' => 'Highly proficient in the practical application of professional/technical knowledge', 'max' => 5],
    ['key' => 'output',          'label' => 'Output of work',                        'desc' => 'Gets a great deal done within a set time frame','max' => 5],
    ['key' => 'quality',         'label' => 'Quality of work',                       'desc' => 'Maintains very high standard, work is virtually error proof', 'max' => 5],
    ['key' => 'punctuality',     'label' => 'Punctuality',                           'desc' => 'Regular, punctual at work',                     'max' => 5],
    ['key' => 'overall_perf',    'label' => 'Overall performance',                   'desc' => '',                                              'max' => 10],
    ['key' => 'overall_conduct', 'label' => 'Overall conduct (ethics)',              'desc' => '',                                              'max' => 10],
];

$flash = ['type' => '', 'msg' => ''];

// ----------------------------------------------------------------------
// POST: request OTP for the evaluation
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sup_otp_request']) && $placement && !$evaluation) {
    $code = issue_supervisor_otp($pdo, (int)$placement['id'], $placement['supervisor_email'], 'evaluation', 15);
    if ($code === null) {
        header("Location: evaluation.php?otp_err=migration");
        exit;
    }
    $body = "Hello " . htmlspecialchars($placement['supervisor_name']) . ",\n\n"
          . "Your verification code to submit the final evaluation for "
          . htmlspecialchars($_SESSION['name'] ?? 'a student') . " is:\n\n"
          . "    $code\n\n"
          . "It expires in 15 minutes.\n\n"
          . "— RMU Internship Portal";
    try_send_email($pdo, $placement['supervisor_email'],
        'RMU final evaluation — verification code', $body, false);
    header("Location: evaluation.php?otp_sent=1");
    exit;
}

// ----------------------------------------------------------------------
// POST: submit evaluation — verifies OTP, writes evaluation row
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_eval']) && $placement && !$evaluation) {
    $code     = trim($_POST['sup_otp']          ?? '');
    $sup_name = trim($_POST['supervisor_name']  ?? '');
    $org      = trim($_POST['organization']     ?? '');
    $sup_ttl  = trim($_POST['supervisor_title'] ?? '');

    $err = null;
    if ($sup_name === '' || $org === '') {
        $err = 'Training officer name and organisation are required.';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $err = 'Enter the 6-digit OTP that was sent to the supervisor\'s email.';
    }

    $scores = [];
    if (!$err) {
        foreach ($CRITERIA as $c) {
            $v = (int)($_POST['score_' . $c['key']] ?? -1);
            if ($v < 0 || $v > $c['max']) {
                $err = 'Each score must be between 0 and ' . $c['max'] . ' (' . $c['label'] . ').';
                break;
            }
            $scores[$c['key']] = $v;
        }
    }

    if (!$err) {
        if (!verify_supervisor_otp($pdo, (int)$placement['id'], 'evaluation', $code)) {
            $err = 'OTP is invalid or expired. Request a new one.';
        }
    }

    if (!$err) {
        $total = array_sum($scores);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO evaluations
                    (placement_id,
                     score_responsibility, score_reliability, score_knowledge, score_output,
                     score_quality, score_punctuality, score_overall_perf, score_overall_conduct,
                     total_score,
                     supervisor_name, organization, supervisor_title, submitted_ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$placement['id'],
                $scores['responsibility'], $scores['reliability'], $scores['knowledge'], $scores['output'],
                $scores['quality'], $scores['punctuality'], $scores['overall_perf'], $scores['overall_conduct'],
                $total,
                $sup_name, $org,
                $sup_ttl !== '' ? $sup_ttl : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            $pdo->prepare("UPDATE placements SET status = 'completed' WHERE id = ?")
                ->execute([(int)$placement['id']]);
            header("Location: evaluation.php?msg=submitted");
            exit;
        } catch (PDOException $e) {
            $err = ($e->getCode() === '23000')
                ? 'An evaluation has already been submitted for this placement.'
                : 'Database error: ' . $e->getMessage();
        }
    }

    $flash = ['type' => 'error', 'msg' => $err];
}

if (($_GET['otp_sent']  ?? '') === '1') $flash = ['type' => 'success', 'msg' => 'A 6-digit code was emailed to the supervisor. Ask them for it, then fill in the form below.'];
if (($_GET['msg']       ?? '') === 'submitted') $flash = ['type' => 'success', 'msg' => 'Evaluation submitted and locked.'];
if (($_GET['otp_err']   ?? '') === 'migration') $flash = ['type' => 'error', 'msg' => 'Supervisor evaluation is unavailable until migration 015 (supervisor_otps) is applied. Ask the admin to run it from phpMyAdmin.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final Evaluation | Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/logbook.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/evaluation.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Final Supervisor Evaluation</h1>
            <p>The 8-criterion / 50-mark assessment from your on-the-job supervisor. Once submitted, scores are locked.</p>
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
        <div class="card">
            <p class="muted">Register your placement first — your supervisor needs a placement record before they can grade you.</p>
            <p><a href="placement.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i>&nbsp; Go to My Placement</a></p>
        </div>

    <?php elseif ($evaluation): ?>
        <!-- Read-only submitted view -->
        <div class="card eval-result">
            <div class="eval-total">
                <div class="muted small">FINAL SCORE</div>
                <div class="eval-total-num"><?php echo (int)$evaluation['total_score']; ?> / 50</div>
                <div class="muted small">
                    Submitted <?php echo htmlspecialchars(date('d M Y', strtotime($evaluation['submitted_at']))); ?>
                    by <?php echo htmlspecialchars($evaluation['supervisor_name']); ?>
                </div>
            </div>

            <h4 class="section-h">Score Breakdown</h4>
            <table class="eval-table">
                <thead><tr><th>Criterion</th><th class="num">Achieved</th><th class="num">Max</th></tr></thead>
                <tbody>
                    <?php foreach ($CRITERIA as $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($c['label']); ?></strong>
                                <?php if ($c['desc']): ?>
                                    <div class="muted small"><?php echo htmlspecialchars($c['desc']); ?></div>
                                <?php endif; ?>
                            </td>
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

            <h4 class="section-h">Training Officer</h4>
            <div class="grid-2">
                <div><span class="muted small">Name</span><br><strong><?php echo htmlspecialchars($evaluation['supervisor_name']); ?></strong></div>
                <div><span class="muted small">Title</span><br><strong><?php echo htmlspecialchars($evaluation['supervisor_title'] ?? '—'); ?></strong></div>
                <div><span class="muted small">Organisation</span><br><strong><?php echo htmlspecialchars($evaluation['organization']); ?></strong></div>
            </div>

            <p class="muted small" style="margin-top: 16px;">
                <i class="fas fa-lock"></i>&nbsp;
                Locked. Neither you nor the HOD can edit this. If there's an error, contact the secretary.
            </p>
        </div>

    <?php else: ?>
        <!-- OTP + scoring form -->
        <div class="card">
            <h3><i class="fas fa-shield-alt"></i>&nbsp; Supervisor identity check</h3>
            <p class="muted">
                A 6-digit code will be emailed to <strong><?php echo htmlspecialchars($placement['supervisor_email']); ?></strong>.
                Ask your supervisor for it, then have them complete this form on your device.
            </p>
            <form method="POST" style="margin-top: 6px;">
                <button type="submit" name="sup_otp_request" class="btn btn-ghost">
                    <i class="fas fa-paper-plane"></i>&nbsp; Send OTP to supervisor's email
                </button>
            </form>
        </div>

        <div class="card" style="margin-top: 18px;">
            <h3><i class="fas fa-clipboard-check"></i>&nbsp; Assessment Scheme</h3>
            <p class="muted small">Score each criterion within its allowed range. Total marks add up to 50.</p>

            <form method="POST" autocomplete="off" class="otp-gated-form">
                <div class="grid-2" style="margin-bottom: 14px;">
                    <div class="field">
                        <label>6-digit code <span class="req">*</span></label>
                        <input type="text" name="sup_otp" class="otp-input"
                               inputmode="numeric" pattern="\d{6}" maxlength="6" required
                               autocomplete="off" placeholder="000000">
                        <small class="muted small">All score and identity fields unlock once the code is fully entered.</small>
                    </div>
                </div>

                <fieldset class="otp-locked" disabled style="border:none; padding:0; margin:0; opacity:0.55;">
                <table class="eval-table">
                    <thead>
                        <tr>
                            <th>Criterion</th>
                            <th class="num" style="width: 130px;">Achieved</th>
                            <th class="num" style="width: 80px;">Max</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($CRITERIA as $c): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['label']); ?></strong>
                                    <?php if ($c['desc']): ?>
                                        <div class="muted small"><?php echo htmlspecialchars($c['desc']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="num">
                                    <input type="number" name="score_<?php echo $c['key']; ?>" required
                                           min="0" max="<?php echo (int)$c['max']; ?>"
                                           class="score-input" data-max="<?php echo (int)$c['max']; ?>">
                                </td>
                                <td class="num muted"><?php echo (int)$c['max']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total">
                            <td><strong>TOTAL</strong></td>
                            <td class="num"><strong id="total_display">0</strong></td>
                            <td class="num"><strong>50</strong></td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="section-h">Training Officer</h4>
                <div class="grid-2">
                    <div class="field">
                        <label>Name <span class="req">*</span></label>
                        <input type="text" name="supervisor_name" required value="<?php echo htmlspecialchars($placement['supervisor_name']); ?>">
                    </div>
                    <div class="field">
                        <label>Title</label>
                        <input type="text" name="supervisor_title" value="<?php echo htmlspecialchars($placement['supervisor_title'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Organisation <span class="req">*</span></label>
                        <input type="text" name="organization" required value="<?php echo htmlspecialchars($placement['company_name']); ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit_eval" class="btn btn-primary"
                            onclick="return confirm('Submit this evaluation? It will be locked after submission — neither the student nor the HOD can edit it.');">
                        <i class="fas fa-lock"></i>&nbsp; Submit &amp; Lock
                    </button>
                </div>
                </fieldset>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
const inputs = document.querySelectorAll('.score-input');
const total  = document.getElementById('total_display');
function recalc() {
    if (!total) return;
    let sum = 0;
    inputs.forEach(i => {
        const v = parseInt(i.value, 10);
        if (!isNaN(v)) sum += v;
    });
    total.textContent = sum;
}
inputs.forEach(i => i.addEventListener('input', recalc));

// OTP-gated fields: until the 6-digit code has been typed in full,
// the score + identity fieldset stays disabled (item #4). Final
// verification still happens server-side at submit.
document.querySelectorAll('.otp-gated-form').forEach(form => {
    const otp  = form.querySelector('.otp-input');
    const lock = form.querySelector('.otp-locked');
    if (!otp || !lock) return;
    const refresh = () => {
        const v = (otp.value || '').replace(/\D/g, '').slice(0, 6);
        otp.value = v;
        const ok = v.length === 6;
        lock.disabled    = !ok;
        lock.style.opacity = ok ? '1' : '0.55';
    };
    otp.addEventListener('input', refresh);
    refresh();
});
</script>
</body>
</html>
