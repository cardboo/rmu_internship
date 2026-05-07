<?php
// Public — token-gated evaluation form for the on-the-job supervisor.
define('SKIP_PASSWORD_GATE', true);
require __DIR__ . '/includes/db.php';

$token     = trim($_GET['t'] ?? '');
$placement = $token !== '' ? placement_by_supervisor_token($pdo, $token) : null;

// Existing evaluation, if any.
$evaluation = null;
if ($placement) {
    $evStmt = $pdo->prepare("SELECT * FROM evaluations WHERE placement_id = ?");
    $evStmt->execute([(int)$placement['id']]);
    $evaluation = $evStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Definition of the eight criteria + their max marks.
$CRITERIA = [
    ['key' => 'responsibility',    'label' => 'Acceptance of responsibility',         'desc' => 'Seeks and accepts responsibility at all times', 'max' => 5],
    ['key' => 'reliability',       'label' => 'Reliability under pressure',           'desc' => 'Performs competently under pressure',           'max' => 5],
    ['key' => 'knowledge',         'label' => 'Application of professional knowledge','desc' => 'Highly proficient in the practical application of professional/technical knowledge', 'max' => 5],
    ['key' => 'output',            'label' => 'Output of work',                       'desc' => 'Gets a great deal done within a set time frame','max' => 5],
    ['key' => 'quality',           'label' => 'Quality of work',                      'desc' => 'Maintains very high standard, work is virtually error proof', 'max' => 5],
    ['key' => 'punctuality',       'label' => 'Punctuality',                          'desc' => 'Regular, punctual at work',                     'max' => 5],
    ['key' => 'overall_perf',      'label' => 'Overall performance',                  'desc' => '',                                              'max' => 10],
    ['key' => 'overall_conduct',   'label' => 'Overall conduct (ethics)',             'desc' => '',                                              'max' => 10],
];

$flash = ['type' => '', 'msg' => ''];

// ----------------------------------------------------------------------
// POST: submit evaluation (only if not already submitted).
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $placement && !$evaluation && isset($_POST['submit_eval'])) {
    $sup_name = trim($_POST['supervisor_name'] ?? '');
    $org      = trim($_POST['organization']    ?? '');
    $sup_ttl  = trim($_POST['supervisor_title'] ?? '');

    $err = null;
    if ($sup_name === '' || $org === '') {
        $err = 'Training officer name and organisation are required.';
    }

    $scores = [];
    foreach ($CRITERIA as $c) {
        $v = (int)($_POST['score_' . $c['key']] ?? -1);
        if ($v < 0 || $v > $c['max']) {
            $err = 'Each score must be between 0 and ' . $c['max'] . ' (' . $c['label'] . ').';
            break;
        }
        $scores[$c['key']] = $v;
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
            // Mark placement as completed when the eval is submitted.
            $pdo->prepare("UPDATE placements SET status = 'completed' WHERE id = ?")
                ->execute([(int)$placement['id']]);

            header("Location: supervisor_evaluation.php?t=" . urlencode($token) . "&msg=submitted");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $err = 'An evaluation has already been submitted for this student.';
            } else {
                $err = 'Database error: ' . $e->getMessage();
            }
        }
    }

    $flash = ['type' => 'error', 'msg' => $err];
}

if (($_GET['msg'] ?? '') === 'submitted') {
    $flash = ['type' => 'success', 'msg' => 'Evaluation submitted. Thank you!'];
    // Re-fetch
    $evStmt->execute([(int)$placement['id']]);
    $evaluation = $evStmt->fetch(PDO::FETCH_ASSOC) ?: $evaluation;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Evaluation — Supervisor Portal | RMU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/supervisor.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/evaluation.css'); ?>">
</head>
<body class="auth-body no-sidebar sup-body">
    <div class="sup-shell">
        <div class="sup-banner">
            <img src="<?php echo asset('images/logo.jpg'); ?>" alt="RMU Logo">
            <div>
                <strong>Industrial Attachment Evaluation Form</strong>
                <div class="muted small">Regional Maritime University</div>
            </div>
        </div>

        <?php if (!$placement): ?>
            <div class="card">
                <div class="banner banner-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    This link is invalid or has expired.
                </div>
            </div>
        <?php else: ?>

            <?php if ($flash['msg']): ?>
                <div class="banner banner-<?php echo $flash['type']; ?>">
                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
                </div>
            <?php endif; ?>

            <!-- Trainee context -->
            <div class="card sup-context">
                <div class="grid-2">
                    <div><span class="muted small">Name of Trainee</span><br><strong><?php echo htmlspecialchars($placement['student_name']); ?></strong></div>
                    <div><span class="muted small">Course of Study</span><br><strong><?php echo htmlspecialchars($placement['program'] ?? '—'); ?></strong></div>
                    <div><span class="muted small">Period</span><br>
                        <strong>
                            <?php echo htmlspecialchars(date('d M Y', strtotime($placement['start_date']))); ?>
                            – <?php echo htmlspecialchars(date('d M Y', strtotime($placement['end_date']))); ?>
                        </strong>
                    </div>
                    <div><span class="muted small">Organisation</span><br><strong><?php echo htmlspecialchars($placement['company_name']); ?></strong></div>
                </div>
            </div>

            <?php if ($evaluation): ?>
                <!-- Read-only result view -->
                <div class="card eval-result" style="margin-top: 18px;">
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
                        Once submitted, an evaluation cannot be edited. If you spotted an error,
                        contact the student's department secretary so they can flag it for review.
                    </p>
                </div>

            <?php else: ?>
                <!-- Form -->
                <div class="card" style="margin-top: 18px;">
                    <h3><i class="fas fa-clipboard-check"></i>&nbsp; Assessment Scheme</h3>
                    <p class="muted">Score each criterion within its allowed range. Total marks add up to 50.</p>

                    <form method="POST" autocomplete="off">
                        <table class="eval-table">
                            <thead>
                                <tr>
                                    <th>Criterion</th>
                                    <th class="num" style="width: 120px;">Achieved</th>
                                    <th class="num" style="width: 90px;">Max</th>
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
                                <input type="text" name="supervisor_name" required
                                       value="<?php echo htmlspecialchars($placement['supervisor_name']); ?>">
                            </div>
                            <div class="field">
                                <label>Title</label>
                                <input type="text" name="supervisor_title"
                                       value="<?php echo htmlspecialchars($placement['supervisor_title'] ?? ''); ?>">
                            </div>
                            <div class="field">
                                <label>Organisation <span class="req">*</span></label>
                                <input type="text" name="organization" required
                                       value="<?php echo htmlspecialchars($placement['company_name']); ?>">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit_eval" class="btn btn-primary"
                                    onclick="return confirm('Submit this evaluation? You will not be able to edit it after submission.');">
                                <i class="fas fa-check-circle"></i>&nbsp; Submit Evaluation
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <p class="muted small" style="text-align: center; margin-top: 24px;">
                <a href="supervisor.php?t=<?php echo urlencode($token); ?>"><i class="fas fa-arrow-left"></i>&nbsp; Back to weekly logs</a>
            </p>
        <?php endif; ?>
    </div>

    <script>
    // Live total
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
    </script>
</body>
</html>
