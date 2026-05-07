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
                // Issue the supervisor's secure-link token (60-day expiry).
                issue_supervisor_token($pdo, $new_id, 60);
                header("Location: placement.php?msg=created");
                exit;
            }
        } catch (PDOException $e) {
            $err = 'Database error: ' . $e->getMessage();
        }
    }

    if ($err) $flash = ['type' => 'error', 'msg' => $err];
}

// Regenerate the supervisor token (e.g. if it was lost or expired).
if (isset($_GET['regen_token']) && $placement) {
    issue_supervisor_token($pdo, (int)$placement['id'], 60);
    header("Location: placement.php?msg=token_regenerated");
    exit;
}

if (($_GET['msg'] ?? '') === 'created')          $flash = ['type' => 'success', 'msg' => 'Placement registered. A secure link has been generated for your on-the-job supervisor (see below).'];
if (($_GET['msg'] ?? '') === 'updated')          $flash = ['type' => 'success', 'msg' => 'Placement updated.'];
if (($_GET['msg'] ?? '') === 'token_regenerated') $flash = ['type' => 'success', 'msg' => 'New supervisor link generated. The previous one no longer works.'];

// Refresh placement to pick up the freshly-issued token.
if ($placement) {
    $placementStmt->execute([$student_id]);
    $placement = $placementStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

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

        <?php
            $sup_url = !empty($placement['supervisor_token'])
                ? BASE_URL . 'supervisor.php?t=' . $placement['supervisor_token']
                : null;
            $expires = !empty($placement['supervisor_token_expires_at'])
                ? date('d M Y', strtotime($placement['supervisor_token_expires_at']))
                : null;
        ?>
        <div class="card supervisor-link-card" style="margin-top: 18px;">
            <h3><i class="fas fa-link"></i>&nbsp; Supervisor's Secure Link</h3>
            <p class="muted">
                Send this URL to your on-the-job supervisor (<?php echo htmlspecialchars($placement['supervisor_email']); ?>).
                It lets them review your weekly logs, add their remarks, and complete the final evaluation —
                no account needed.
            </p>
            <?php if ($sup_url): ?>
                <div class="link-row">
                    <input type="text" id="sup_url" value="<?php echo htmlspecialchars($sup_url); ?>" readonly>
                    <button type="button" class="btn btn-primary btn-sm" onclick="copySupervisorLink()">
                        <i class="fas fa-copy"></i>&nbsp; Copy
                    </button>
                </div>
                <div class="muted small" style="margin-top: 8px;">
                    Expires <?php echo htmlspecialchars($expires ?? '—'); ?>.
                    <a href="?regen_token=1" onclick="return confirm('Generate a new link? The old one will stop working.');">Regenerate</a>
                </div>
            <?php else: ?>
                <p>No supervisor link yet.
                    <a href="?regen_token=1" class="btn btn-ghost btn-sm">
                        <i class="fas fa-sync-alt"></i>&nbsp; Generate link
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <script>
        function copySupervisorLink() {
            const el = document.getElementById('sup_url');
            el.select(); el.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(el.value).then(() => {
                el.style.background = '#dcfce7';
                setTimeout(() => el.style.background = '', 1500);
            });
        }
        </script>
    <?php endif; ?>
</div>
</body>
</html>
