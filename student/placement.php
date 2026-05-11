<?php
require __DIR__ . '/../includes/db.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];

// Current academic year from migration 007's helper.
$cur_year = current_academic_year($pdo);

// Existing placement for this student in the current academic year, if any.
$placementStmt = $pdo->prepare("
    SELECT * FROM placements
    WHERE student_id = ?
    " . ($cur_year ? " AND academic_year_id = " . (int)$cur_year['id'] : "") . "
    ORDER BY created_at DESC
    LIMIT 1
");
$placementStmt->execute([$student_id]);
$placement = $placementStmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Most recent approved letter request — used to link the placement
// and pre-fill suggested dates.
$reqStmt = $pdo->prepare("
    SELECT * FROM requests
    WHERE student_id = ? AND status = 'approved'
    ORDER BY request_date DESC LIMIT 1
");
$reqStmt->execute([$student_id]);
$last_request = $reqStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_placement'])) {
    $company_name       = trim($_POST['company_name']       ?? '');
    $company_address    = trim($_POST['company_address']    ?? '');
    $company_department = trim($_POST['company_department'] ?? '');
    $supervisor_name    = trim($_POST['supervisor_name']    ?? '');
    $supervisor_email   = trim($_POST['supervisor_email']   ?? '');
    $supervisor_title   = trim($_POST['supervisor_title']   ?? '');
    $supervisor_phone   = trim($_POST['supervisor_phone']   ?? '');
    $start_date         = trim($_POST['start_date']         ?? '');
    $end_date           = trim($_POST['end_date']           ?? '');

    $err = null;
    if ($company_name === '' || $company_address === '' || $supervisor_name === ''
        || $supervisor_email === '' || $start_date === '' || $end_date === '') {
        $err = 'Company, address, supervisor name + email, and dates are required.';
    } elseif (!filter_var($supervisor_email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Supervisor email is not a valid address.';
    } elseif ($end_date <= $start_date) {
        $err = 'End date must be after the start date.';
    }

    if (!$err) {
        $semester = semester_for_date($pdo, $start_date);

        try {
            if ($placement) {
                // Update existing
                $upd = $pdo->prepare("
                    UPDATE placements
                    SET company_name = ?, company_address = ?, company_department = ?,
                        supervisor_name = ?, supervisor_email = ?, supervisor_title = ?, supervisor_phone = ?,
                        start_date = ?, end_date = ?, semester_id = ?
                    WHERE id = ? AND student_id = ?
                ");
                $upd->execute([
                    $company_name, $company_address,
                    $company_department !== '' ? $company_department : null,
                    $supervisor_name, $supervisor_email,
                    $supervisor_title !== '' ? $supervisor_title : null,
                    $supervisor_phone !== '' ? $supervisor_phone : null,
                    $start_date, $end_date,
                    $semester ? (int)$semester['id'] : null,
                    (int)$placement['id'], $student_id,
                ]);
                header("Location: placement.php?msg=updated");
                exit;
            } else {
                // Create new
                $ins = $pdo->prepare("
                    INSERT INTO placements
                        (student_id, request_id, company_name, company_address, company_department,
                         supervisor_name, supervisor_email, supervisor_title, supervisor_phone,
                         start_date, end_date, status, academic_year_id, semester_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                ");
                $ins->execute([
                    $student_id,
                    $last_request ? (int)$last_request['id'] : null,
                    $company_name, $company_address,
                    $company_department !== '' ? $company_department : null,
                    $supervisor_name, $supervisor_email,
                    $supervisor_title !== '' ? $supervisor_title : null,
                    $supervisor_phone !== '' ? $supervisor_phone : null,
                    $start_date, $end_date,
                    $cur_year ? (int)$cur_year['id'] : null,
                    $semester ? (int)$semester['id'] : null,
                ]);
                $new_id = (int)$pdo->lastInsertId();

                // Notify the supervisor that they've been named. They'll
                // sign weekly logs + the final evaluation from the
                // student's machine after entering an emailed OTP.
                require_once __DIR__ . '/../includes/email.php';
                $student_name = $_SESSION['name'] ?? 'A student';
                $body = "Hello " . htmlspecialchars($supervisor_name) . ",\n\n"
                      . htmlspecialchars($student_name) . " has named you as their on-the-job supervisor for"
                      . " their RMU industrial attachment at " . htmlspecialchars($company_name) . ".\n\n"
                      . "When it's time to sign off a weekly log or submit the final evaluation,"
                      . " the student will hand you their device. A 6-digit code will be sent to this email"
                      . " to verify it's you — type it in to act on the entry.\n\n"
                      . "— RMU Internship Portal";
                try_send_email($pdo, $supervisor_email,
                    'You\'re the supervisor for ' . $student_name . ' (RMU attachment)',
                    $body, false);

                header("Location: placement.php?msg=created");
                exit;
            }
        } catch (PDOException $e) {
            $err = 'Database error: ' . $e->getMessage();
        }
    }

    if ($err) $flash = ['type' => 'error', 'msg' => $err];
}

if (($_GET['msg'] ?? '') === 'created') $flash = ['type' => 'success', 'msg' => 'Placement registered. Your on-the-job supervisor has been notified by email.'];
if (($_GET['msg'] ?? '') === 'updated') $flash = ['type' => 'success', 'msg' => 'Placement updated.'];

// Pre-fill values: existing placement first, fall back to last approved request's dates.
$pre = [
    'company_name'       => $placement['company_name']       ?? '',
    'company_address'    => $placement['company_address']    ?? '',
    'company_department' => $placement['company_department'] ?? '',
    'supervisor_name'    => $placement['supervisor_name']    ?? '',
    'supervisor_email'   => $placement['supervisor_email']   ?? '',
    'supervisor_title'   => $placement['supervisor_title']   ?? '',
    'supervisor_phone'   => $placement['supervisor_phone']   ?? '',
    'start_date'         => $placement['start_date']         ?? ($last_request['start_date'] ?? ''),
    'end_date'           => $placement['end_date']           ?? ($last_request['end_date']   ?? ''),
];

$has_approved_letter = $last_request && $last_request['status'] === 'approved';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Placement | Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>My Placement</h1>
            <p>Once you've secured a host organisation, register the placement here. Your weekly logs and final evaluation will attach to this record.</p>
        </div>
        <?php if ($cur_year): ?>
            <div class="date-chip">
                <i class="fas fa-calendar-alt"></i>&nbsp; Academic Year <?= htmlspecialchars($cur_year['name']) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$has_approved_letter && !$placement): ?>
        <div class="banner banner-warning">
            <i class="fas fa-exclamation-circle"></i>
            You don't have an approved attachment letter yet. Please request one from your dashboard first — once it's approved you can register your placement here.
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>
            <?php if ($placement): ?>
                <i class="fas fa-edit"></i>&nbsp; Update Placement
            <?php else: ?>
                <i class="fas fa-building"></i>&nbsp; Register Placement
            <?php endif; ?>
        </h3>

        <form method="POST" class="single-form" autocomplete="off">
            <h4 class="section-h">Host Organisation</h4>
            <div class="grid-2">
                <div class="field">
                    <label>Company Name <span class="req">*</span></label>
                    <input type="text" name="company_name" required
                           value="<?php echo htmlspecialchars($pre['company_name']); ?>"
                           placeholder="e.g. Maersk Ghana Ltd.">
                </div>
                <div class="field">
                    <label>Department / Office at the Company</label>
                    <input type="text" name="company_department"
                           value="<?php echo htmlspecialchars($pre['company_department']); ?>"
                           placeholder="e.g. IT Operations">
                </div>
            </div>
            <div class="field">
                <label>Company Address <span class="req">*</span></label>
                <textarea name="company_address" required rows="2"
                          placeholder="e.g. PMB Tema Port, Tema, Ghana"><?php echo htmlspecialchars($pre['company_address']); ?></textarea>
            </div>

            <h4 class="section-h">On-the-Job Supervisor</h4>
            <div class="grid-2">
                <div class="field">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="supervisor_name" required
                           value="<?php echo htmlspecialchars($pre['supervisor_name']); ?>">
                </div>
                <div class="field">
                    <label>Title / Role</label>
                    <input type="text" name="supervisor_title"
                           value="<?php echo htmlspecialchars($pre['supervisor_title']); ?>"
                           placeholder="e.g. IT Manager">
                </div>
                <div class="field">
                    <label>Work Email <span class="req">*</span></label>
                    <input type="email" name="supervisor_email" required
                           value="<?php echo htmlspecialchars($pre['supervisor_email']); ?>"
                           placeholder="e.g. supervisor@company.com">
                    <small class="muted small">Used to send the supervisor a secure link for weekly remarks and the final evaluation.</small>
                </div>
                <div class="field">
                    <label>Phone</label>
                    <input type="tel" name="supervisor_phone"
                           value="<?php echo htmlspecialchars($pre['supervisor_phone']); ?>">
                </div>
            </div>

            <h4 class="section-h">Attachment Period</h4>
            <div class="grid-2">
                <div class="field">
                    <label>Start Date <span class="req">*</span></label>
                    <input type="date" name="start_date" required
                           value="<?php echo htmlspecialchars($pre['start_date']); ?>">
                </div>
                <div class="field">
                    <label>End Date <span class="req">*</span></label>
                    <input type="date" name="end_date" required
                           value="<?php echo htmlspecialchars($pre['end_date']); ?>">
                </div>
            </div>

            <div class="form-actions">
                <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" name="save_placement" class="btn btn-primary">
                    <i class="fas fa-save"></i>&nbsp;
                    <?php echo $placement ? 'Save Changes' : 'Register Placement'; ?>
                </button>
            </div>
        </form>
    </div>

    <?php if ($placement): ?>
        <div class="card placement-summary" style="margin-top: 25px;">
            <h3><i class="fas fa-info-circle"></i>&nbsp; Current Placement Status</h3>
            <div class="grid-2">
                <div>
                    <div class="muted small">Status</div>
                    <strong><?php echo ucfirst($placement['status']); ?></strong>
                </div>
                <div>
                    <div class="muted small">Registered</div>
                    <strong><?php echo htmlspecialchars(date('d M Y', strtotime($placement['created_at']))); ?></strong>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 18px;">
            <h3><i class="fas fa-shield-alt"></i>&nbsp; Supervisor Sign-Off</h3>
            <p class="muted">
                Your on-the-job supervisor (<strong><?php echo htmlspecialchars($placement['supervisor_email']); ?></strong>)
                signs each weekly log and submits the final evaluation directly on this device.
                When they're ready to act, open the relevant page below — a 6-digit code is sent to
                their email to verify identity each time.
            </p>
            <div class="form-actions" style="justify-content: flex-start; gap: 10px;">
                <a href="<?php echo BASE_URL; ?>student/logbook.php" class="btn btn-ghost">
                    <i class="fas fa-book"></i>&nbsp; Weekly logs
                </a>
                <a href="<?php echo BASE_URL; ?>student/evaluation.php" class="btn btn-primary">
                    <i class="fas fa-clipboard-check"></i>&nbsp; Final evaluation
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
