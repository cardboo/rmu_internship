<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

$flash    = ['type' => '', 'msg' => ''];
$preserve = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name    = trim($_POST['full_name']    ?? '');
    $email        = trim($_POST['email']        ?? '');
    $role         = trim($_POST['role']         ?? '');
    $department   = trim($_POST['department']   ?? '');
    $program      = trim($_POST['program']      ?? '');
    $level        = trim($_POST['level']        ?? '');
    $gender       = trim($_POST['gender']       ?? '');
    $job_title    = trim($_POST['job_title']    ?? '');
    $index_number = strtoupper(trim($_POST['index_number'] ?? ''));
    $password     = (string)($_POST['password'] ?? '');

    $err = null;
    if ($full_name === '' || $email === '' || $role === '' || $department === '' || $password === '') {
        $err = 'Full name, email, role, department and password are required.';
    } elseif (!in_array($role, ['admin','hod','secretary','student'], true)) {
        $err = 'Invalid role.';
    } elseif (($emailErr = rmu_email_error($email, $role)) !== null) {
        $err = $emailErr;
    } elseif (strlen($password) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($role === 'student' && $program === '') {
        $err = 'Program of study is required for students.';
    }

    if (!$err) {
        $check = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetchColumn()) {
            $err = "A user with email $email already exists.";
        }
    }

    if ($err) {
        $flash    = ['type' => 'error', 'msg' => $err];
        $preserve = compact('full_name','email','role','department','program','level','gender','job_title','index_number');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users
                (full_name, email, password, role, department, level, program,
                 job_title, gender, index_number, must_change_password)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $full_name, $email, $hash, $role, $department,
            $role === 'student' ? ($level ?: null) : null,
            $role === 'student' ? ($program ?: null) : null,
            $role !== 'student' ? ($job_title ?: null) : null,
            $gender !== '' ? $gender : null,
            $role === 'student' && $index_number !== '' ? $index_number : null,
        ]);
        $new_user_id = (int)$pdo->lastInsertId();

        // ------------------------------------------------------------
        // Mirror the new student into student_registry so they show up
        // in the registry view alongside CSV-imported students.
        //
        // Two cases:
        //   - A registry row already exists for this index_number
        //     (registry office uploaded them earlier) — just flip
        //     is_claimed = 1 and link the user.
        //   - No registry row yet — insert one, but only if the
        //     dept + programme map cleanly to canonical tables.
        //     Otherwise we surface a warning (drift list on
        //     admin/registry.php will pick it up too).
        // ------------------------------------------------------------
        $registry_warning = null;
        if ($role === 'student' && $index_number !== '') {
            // PK of student_registry is index_number, not id.
            $exists = $pdo->prepare("SELECT 1 FROM student_registry WHERE index_number = ?");
            $exists->execute([$index_number]);
            if ($exists->fetchColumn()) {
                $pdo->prepare("
                    UPDATE student_registry
                    SET is_claimed = 1, claimed_user_id = ?
                    WHERE index_number = ?
                ")->execute([$new_user_id, $index_number]);
            } else {
                $deptStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
                $deptStmt->execute([$department]);
                $dept_id = $deptStmt->fetchColumn();

                $prog_id = null;
                if ($dept_id) {
                    $progStmt = $pdo->prepare("SELECT id FROM programs WHERE name = ? AND department_id = ?");
                    $progStmt->execute([$program, $dept_id]);
                    $prog_id = $progStmt->fetchColumn();
                }

                if ($dept_id && $prog_id) {
                    $pdo->prepare("
                        INSERT IGNORE INTO student_registry
                            (index_number, full_name, email, department_id, program_id,
                             level, gender, is_claimed, claimed_user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                    ")->execute([
                        $index_number, $full_name,
                        $email,
                        (int)$dept_id, (int)$prog_id,
                        $level !== '' ? $level : null,
                        $gender !== '' ? $gender : null,
                        $new_user_id,
                    ]);
                } else {
                    $registry_warning = "Note: this student wasn't added to the Student Registry because "
                        . (!$dept_id ? "department '$department' doesn't match a canonical record"
                                     : "programme '$program' isn't in the '$department' department")
                        . ". Fix the user's record or add the missing programme, then run migration 013 to reconcile.";
                }
            }
        } elseif ($role === 'student' && $index_number === '') {
            $registry_warning = "Note: this student wasn't added to the Student Registry because no index number was provided.";
        }

        // Stash the temp password so users.php can show it once.
        $_SESSION['temp_pw_notice'] = ['name' => $full_name, 'password' => $password];
        if ($registry_warning) {
            $_SESSION['flash_warning'] = $registry_warning;
        }

        // Email the new user the temp password (best-effort).
        require_once __DIR__ . '/../includes/email.php';
        $body = "Hello " . htmlspecialchars($full_name) . ",\n\n"
              . "An account has been created for you on the RMU Internship Portal.\n\n"
              . "  Email:    $email\n"
              . "  Password: $password  (temporary — you'll be asked to change it on first login)\n\n"
              . "Log in: " . BASE_URL . "index.php\n\n"
              . "— RMU Internship Portal";
        try_send_email($pdo, $email, 'Your RMU Internship Portal account', $body, false);

        header("Location: users.php?msg=created");
        exit;
    }
}

$default_pw = generate_temp_password();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New User | Admin</title>
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
            <h1>Add New User</h1>
            <p>Create an RMU portal account. The user will be forced to change the temporary password on first login.</p>
        </div>
        <a href="users.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i>&nbsp; Back</a>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" class="single-form" id="addUserForm" autocomplete="off">
            <div class="grid-2">
                <div class="field">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars($preserve['full_name'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>Role <span class="req">*</span></label>
                    <select name="role" id="role_select" required>
                        <option value="">-- Select role --</option>
                        <?php foreach (['student','secretary','hod','admin'] as $r): ?>
                            <option value="<?php echo $r; ?>" <?php echo (($preserve['role'] ?? '') === $r) ? 'selected' : ''; ?>>
                                <?php echo $r === 'hod' ? 'Head of Department (HOD)' : ucfirst($r); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" required
                           value="<?php echo htmlspecialchars($preserve['email'] ?? ''); ?>"
                           placeholder="e.g. john.doe@rmu.edu.gh">
                    <small class="muted small" id="email_hint">Staff: <code>@<?php echo $STAFF_DOMAIN; ?></code> · Students: <code>@<?php echo $STUDENT_DOMAIN; ?></code></small>
                </div>

                <div class="field">
                    <label>Department <span class="req">*</span></label>
                    <select name="department" id="dept_select" required>
                        <option value="">-- Select department --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['name']); ?>"
                                    <?php echo (($preserve['department'] ?? '') === $d['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- STUDENT-ONLY -->
                <div class="field student-field">
                    <label>Program <span class="req">*</span></label>
                    <select name="program" id="prog_select" disabled>
                        <option value="">-- Select department first --</option>
                    </select>
                </div>
                <div class="field student-field">
                    <label>Level</label>
                    <select name="level">
                        <option value="">--</option>
                        <?php foreach (['100','200','300','400'] as $l): ?>
                            <option value="<?php echo $l; ?>" <?php echo (($preserve['level'] ?? '') === $l) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field student-field">
                    <label>Index Number</label>
                    <input type="text" name="index_number"
                           value="<?php echo htmlspecialchars($preserve['index_number'] ?? ''); ?>"
                           placeholder="e.g. BIT0002001">
                </div>

                <!-- STAFF-ONLY -->
                <div class="field staff-field">
                    <label>Job Title</label>
                    <select name="job_title">
                        <option value="">-- Select --</option>
                        <?php foreach ($job_titles as $jt): ?>
                            <option value="<?php echo htmlspecialchars($jt['name']); ?>"
                                    <?php echo (($preserve['job_title'] ?? '') === $jt['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jt['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">--</option>
                        <option value="Male"   <?php echo (($preserve['gender'] ?? '') === 'Male')   ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (($preserve['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
            </div>

            <div class="field temp-pw-field">
                <label>Temporary Password <span class="req">*</span></label>
                <div class="pw-row">
                    <input type="text" name="password" id="pw_input"
                           value="<?php echo htmlspecialchars($default_pw); ?>" required minlength="8">
                    <button type="button" id="regen_btn" class="btn btn-ghost btn-sm" title="Generate a new password">
                        <i class="fas fa-sync-alt"></i>&nbsp; Generate
                    </button>
                </div>
                <small class="muted small">The user must change this on first login. Save it before submitting — it will be shown once on the user list after creation.</small>
            </div>

            <div class="form-actions">
                <a href="users.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>&nbsp; Create User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const PROGS_BY_DEPT  = <?php echo json_encode($prog_by_dept_name, JSON_UNESCAPED_UNICODE); ?>;
const STAFF_DOMAIN   = <?php echo json_encode($STAFF_DOMAIN); ?>;
const STUDENT_DOMAIN = <?php echo json_encode($STUDENT_DOMAIN); ?>;
const PRESERVED_PROG = <?php echo json_encode($preserve['program'] ?? ''); ?>;

const roleSel   = document.getElementById('role_select');
const deptSel   = document.getElementById('dept_select');
const progSel   = document.getElementById('prog_select');
const emailHint = document.getElementById('email_hint');

function updateRoleVisibility() {
    const role = roleSel.value;
    const isStudent = role === 'student';
    document.querySelectorAll('.student-field').forEach(el => el.style.display = isStudent ? '' : 'none');
    document.querySelectorAll('.staff-field').forEach(el  => el.style.display = isStudent ? 'none' : '');

    if (role === 'student') {
        emailHint.innerHTML = 'Students must use <code>@' + STUDENT_DOMAIN + '</code>';
    } else if (role) {
        emailHint.innerHTML = 'Staff must use <code>@' + STAFF_DOMAIN + '</code>';
    } else {
        emailHint.innerHTML = 'Staff: <code>@' + STAFF_DOMAIN + '</code> · Students: <code>@' + STUDENT_DOMAIN + '</code>';
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
        if (name === PRESERVED_PROG) opt.selected = true;
        progSel.appendChild(opt);
    });
}

roleSel.addEventListener('change', updateRoleVisibility);
deptSel.addEventListener('change', updatePrograms);

document.getElementById('regen_btn').addEventListener('click', () => {
    // Mirror PHP generate_temp_password() shape: 10 chars, no I/l/O/0/1.
    const alpha  = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    const digits = '23456789';
    let out = '';
    for (let i = 0; i < 10; i++) {
        out += (i % 5 < 3)
            ? alpha[Math.floor(Math.random() * alpha.length)]
            : digits[Math.floor(Math.random() * digits.length)];
    }
    document.getElementById('pw_input').value =
        out.split('').sort(() => Math.random() - 0.5).join('');
});

updateRoleVisibility();
updatePrograms();
</script>
</body>
</html>
