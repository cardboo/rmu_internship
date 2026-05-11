<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

if (empty($_GET['id'])) {
    header("Location: users.php");
    exit;
}
$user_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) die("User not found.");

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $full_name    = trim($_POST['full_name']    ?? '');
    $email        = trim($_POST['email']        ?? '');
    $role         = trim($_POST['role']         ?? '');
    $department   = trim($_POST['department']   ?? '');
    $program      = trim($_POST['program']      ?? '');
    $level        = trim($_POST['level']        ?? '');
    $gender       = trim($_POST['gender']       ?? '');
    $job_title    = trim($_POST['job_title']    ?? '');
    $index_number = strtoupper(trim($_POST['index_number'] ?? ''));

    $err = null;
    if ($full_name === '' || $email === '' || $role === '' || $department === '') {
        $err = 'Full name, email, role and department are required.';
    } elseif (!in_array($role, ['admin','hod','secretary','student'], true)) {
        $err = 'Invalid role.';
    } elseif (($emailErr = rmu_email_error($email, $role)) !== null) {
        $err = $emailErr;
    } elseif ($role === 'student' && $program === '') {
        $err = 'Program of study is required for students.';
    }

    if (!$err) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $check->execute([$email, $user_id]);
        if ($check->fetchColumn()) {
            $err = "Another user already has the email $email.";
        }
    }

    if ($err) {
        $flash = ['type' => 'error', 'msg' => $err];
        // re-populate $target so the form keeps the user's edits
        $target = array_merge($target, [
            'full_name' => $full_name, 'email' => $email, 'role' => $role,
            'department' => $department, 'program' => $program, 'level' => $level,
            'gender' => $gender, 'job_title' => $job_title, 'index_number' => $index_number,
        ]);
    } else {
        $upd = $pdo->prepare("
            UPDATE users
            SET full_name = ?, email = ?, role = ?, department = ?,
                program   = ?, level = ?, job_title = ?, gender = ?, index_number = ?
            WHERE id = ?
        ");
        $upd->execute([
            $full_name, $email, $role, $department,
            $role === 'student' ? ($program ?: null) : null,
            $role === 'student' ? ($level ?: null) : null,
            $role !== 'student' ? ($job_title ?: null) : null,
            $gender !== '' ? $gender : null,
            $role === 'student' && $index_number !== '' ? $index_number : null,
            $user_id,
        ]);

        // ----------------------------------------------------------------
        // Mirror the student edit into student_registry (matches the
        // logic in admin/add_user.php). Three cases:
        //   1. role just stopped being 'student'  →  nothing to do here.
        //   2. role is student with a valid index AND a registry row exists
        //      for that index  →  refresh editable fields (full_name,
        //      email, level, gender) and link claimed_user_id.
        //   3. role is student with a valid index AND no registry row yet
        //      AND the dept+programme are canonical  →  insert a new
        //      registry row marked claimed.
        // Cases that produce a warning back to admin/users.php:
        //      role is student but index is empty, dept is unknown, or
        //      programme is unknown.
        // ----------------------------------------------------------------
        $registry_warning = null;
        if ($role === 'student' && $index_number !== '') {
            // PK of student_registry is index_number, not id.
            $existsStmt = $pdo->prepare("SELECT 1 FROM student_registry WHERE index_number = ?");
            $existsStmt->execute([$index_number]);
            $reg_exists = (bool)$existsStmt->fetchColumn();

            if ($reg_exists) {
                $pdo->prepare("
                    UPDATE student_registry
                    SET full_name       = ?,
                        email           = COALESCE(NULLIF(?, ''), email),
                        level           = COALESCE(NULLIF(?, ''), level),
                        gender          = COALESCE(NULLIF(?, ''), gender),
                        is_claimed      = 1,
                        claimed_user_id = ?
                    WHERE index_number = ?
                ")->execute([
                    $full_name,
                    $email,
                    $level,
                    $gender,
                    $user_id,
                    $index_number,
                ]);
            } else {
                // Look up canonical dept + programme
                $deptStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
                $deptStmt->execute([$department]);
                $dept_fk = $deptStmt->fetchColumn();

                $prog_fk = null;
                if ($dept_fk) {
                    $progStmt = $pdo->prepare("SELECT id FROM programs WHERE name = ? AND department_id = ?");
                    $progStmt->execute([$program, $dept_fk]);
                    $prog_fk = $progStmt->fetchColumn();
                }

                if ($dept_fk && $prog_fk) {
                    $pdo->prepare("
                        INSERT IGNORE INTO student_registry
                            (index_number, full_name, email, department_id, program_id,
                             level, gender, is_claimed, claimed_user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                    ")->execute([
                        $index_number, $full_name, $email,
                        (int)$dept_fk, (int)$prog_fk,
                        $level !== '' ? $level : null,
                        $gender !== '' ? $gender : null,
                        $user_id,
                    ]);
                } else {
                    $registry_warning = "Saved, but this student isn't in the Student Registry yet because "
                        . (!$dept_fk ? "department '$department' isn't a row in Programs &amp; Depts"
                                     : "programme '$program' isn't in the '$department' department")
                        . ". Fix it on the Programs &amp; Depts page, then save again.";
                }
            }
        } elseif ($role === 'student' && $index_number === '') {
            $registry_warning = "Saved, but this student isn't in the Student Registry because no index number was provided.";
        }

        if ($registry_warning) {
            $_SESSION['flash_warning'] = $registry_warning;
        }

        header("Location: users.php?msg=updated");
        exit;
    }
}

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$programs    = $pdo->query("SELECT id, department_id, name FROM programs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$job_titles  = $pdo->query("SELECT id, name FROM job_titles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$prog_by_dept_name = [];
$dept_by_id        = [];
foreach ($departments as $d) {
    $prog_by_dept_name[$d['name']] = [];
    $dept_by_id[$d['id']]          = $d['name'];
}
foreach ($programs as $p) {
    $name = $dept_by_id[$p['department_id']] ?? null;
    if ($name !== null) $prog_by_dept_name[$name][] = $p['name'];
}

$STAFF_DOMAIN   = RMU_STAFF_DOMAIN;
$STUDENT_DOMAIN = RMU_STUDENT_DOMAIN;
$is_archived    = !empty($target['is_archived']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/users.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Edit User</h1>
            <p>Modify <strong><?php echo htmlspecialchars($target['full_name'] ?? ''); ?></strong>
                <?php if ($is_archived): ?>
                    <span class="role-badge role-archived" style="margin-left:8px;">ARCHIVED</span>
                <?php endif; ?>
            </p>
        </div>
        <a href="users.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i>&nbsp; Back</a>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo $flash['type']; ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" class="single-form" autocomplete="off">
            <div class="grid-2">
                <div class="field">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars($target['full_name'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>Role <span class="req">*</span></label>
                    <select name="role" id="role_select" required>
                        <?php foreach (['student','secretary','hod','admin'] as $r): ?>
                            <option value="<?php echo $r; ?>" <?php echo ($target['role'] === $r) ? 'selected' : ''; ?>>
                                <?php echo $r === 'hod' ? 'Head of Department (HOD)' : ucfirst($r); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($target['email'] ?? ''); ?>">
                    <small class="muted small" id="email_hint">Staff: <code>@<?php echo $STAFF_DOMAIN; ?></code> · Students: <code>@<?php echo $STUDENT_DOMAIN; ?></code></small>
                </div>

                <div class="field">
                    <label>Department <span class="req">*</span></label>
                    <select name="department" id="dept_select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['name']); ?>"
                                    <?php echo ($target['department'] === $d['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field student-field">
                    <label>Program <span class="req">*</span></label>
                    <select name="program" id="prog_select"></select>
                </div>
                <div class="field student-field">
                    <label>Level</label>
                    <select name="level">
                        <option value="">--</option>
                        <?php foreach (['100','200','300','400'] as $l): ?>
                            <option value="<?php echo $l; ?>" <?php echo ($target['level'] === $l) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field student-field">
                    <label>Index Number</label>
                    <input type="text" name="index_number" value="<?php echo htmlspecialchars($target['index_number'] ?? ''); ?>">
                </div>

                <div class="field staff-field">
                    <label>Job Title</label>
                    <select name="job_title">
                        <option value="">-- Select --</option>
                        <?php foreach ($job_titles as $jt): ?>
                            <option value="<?php echo htmlspecialchars($jt['name']); ?>"
                                    <?php echo (($target['job_title'] ?? '') === $jt['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jt['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">--</option>
                        <option value="Male"   <?php echo (($target['gender'] ?? '') === 'Male')   ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (($target['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
            </div>

            <div class="form-actions" style="justify-content: space-between;">
                <div>
                    <?php if ($is_archived): ?>
                        <a href="users.php?restore_id=<?php echo $user_id; ?>" class="btn btn-ghost">
                            <i class="fas fa-undo"></i>&nbsp; Restore from archive
                        </a>
                    <?php elseif ($user_id !== (int)$_SESSION['user_id']): ?>
                        <a href="users.php?archive_id=<?php echo $user_id; ?>" class="btn btn-ghost btn-danger"
                           onclick="return confirm('Archive this user? They will not be able to log in until restored.');">
                            <i class="fas fa-archive"></i>&nbsp; Archive user
                        </a>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="users.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" name="update_user" class="btn btn-primary">
                        <i class="fas fa-save"></i>&nbsp; Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const PROGS_BY_DEPT  = <?php echo json_encode($prog_by_dept_name, JSON_UNESCAPED_UNICODE); ?>;
const STAFF_DOMAIN   = <?php echo json_encode($STAFF_DOMAIN); ?>;
const STUDENT_DOMAIN = <?php echo json_encode($STUDENT_DOMAIN); ?>;
const CURRENT_PROG   = <?php echo json_encode($target['program'] ?? ''); ?>;

const roleSel   = document.getElementById('role_select');
const deptSel   = document.getElementById('dept_select');
const progSel   = document.getElementById('prog_select');
const emailHint = document.getElementById('email_hint');

function updateRoleVisibility() {
    const isStudent = roleSel.value === 'student';
    document.querySelectorAll('.student-field').forEach(el => el.style.display = isStudent ? '' : 'none');
    document.querySelectorAll('.staff-field').forEach(el  => el.style.display = isStudent ? 'none' : '');

    if (roleSel.value === 'student') {
        emailHint.innerHTML = 'Students must use <code>@' + STUDENT_DOMAIN + '</code>';
    } else {
        emailHint.innerHTML = 'Staff must use <code>@' + STAFF_DOMAIN + '</code>';
    }
}

function updatePrograms() {
    const dept = deptSel.value;
    progSel.innerHTML = '';
    const list = PROGS_BY_DEPT[dept] || [];
    if (!dept || list.length === 0) {
        progSel.disabled = true;
        progSel.innerHTML = '<option value="">-- Select department first --</option>';
        return;
    }
    progSel.disabled = false;
    progSel.innerHTML = '<option value="">-- Select program --</option>';
    list.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        if (name === CURRENT_PROG) opt.selected = true;
        progSel.appendChild(opt);
    });
}

roleSel.addEventListener('change', updateRoleVisibility);
deptSel.addEventListener('change', updatePrograms);

updateRoleVisibility();
updatePrograms();
</script>
</body>
</html>
