<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

$flash    = ['type' => '', 'msg' => ''];
$preserve = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $role = trim($_POST['role'] ?? '');

    // -------- STUDENT path: must come from an unclaimed registry row --------
    if ($role === 'student') {
        $idx   = strtoupper(trim($_POST['index_number'] ?? ''));
        $email = trim($_POST['email'] ?? '');

        $err = null;
        if ($idx === '') {
            $err = 'Pick a student from the registry before creating an account.';
        } elseif ($email === '') {
            $err = 'Student email is required.';
        } elseif (($emailErr = rmu_email_error($email, 'student')) !== null) {
            $err = $emailErr;
        }

        $reg = null;
        if (!$err) {
            $regStmt = $pdo->prepare("
                SELECT r.*, d.name AS dept_name, p.name AS program_name
                FROM student_registry r
                JOIN departments d ON d.id = r.department_id
                JOIN programs    p ON p.id = r.program_id
                WHERE r.index_number = ?
            ");
            $regStmt->execute([$idx]);
            $reg = $regStmt->fetch(PDO::FETCH_ASSOC);

            if (!$reg)                           $err = "No registry entry for $idx.";
            elseif ((int)$reg['is_claimed'] === 1) $err = "$idx already has a portal account.";
        }

        if (!$err) {
            $emailCheck = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            $emailCheck->execute([$email]);
            if ($emailCheck->fetchColumn()) $err = "Email $email is already in use.";
        }

        if ($err) {
            $flash    = ['type' => 'error', 'msg' => $err];
            $preserve = ['role' => 'student', 'index_number' => $idx, 'email' => $email];
        } else {
            try {
                $pdo->beginTransaction();
                // Insert with a placeholder hash; reset_user_to_temp_password
                // will overwrite immediately with a fresh temp + email it.
                $ins = $pdo->prepare("
                    INSERT INTO users
                        (full_name, email, password, role, department, program,
                         level, gender, index_number, must_change_password)
                    VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, 1)
                ");
                $ins->execute([
                    $reg['full_name'], $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    $reg['dept_name'], $reg['program_name'],
                    $reg['level'], $reg['gender'], $idx,
                ]);
                $new_id = (int)$pdo->lastInsertId();

                $pdo->prepare("
                    UPDATE student_registry
                    SET is_claimed = 1, claimed_user_id = ?
                    WHERE index_number = ?
                ")->execute([$new_id, $idx]);

                $pdo->commit();

                $sent = reset_user_to_temp_password($pdo, $new_id);
                audit_log($pdo, 'user.created', 'user', $new_id, [
                    'role' => 'student', 'index' => $idx, 'email' => $email,
                    'invitation_sent' => $sent['ok'],
                ]);

                $_SESSION['flash_success'] = $sent['ok']
                    ? "Account created for {$reg['full_name']}. Login credentials emailed to $email."
                    : "Account created for {$reg['full_name']}, but the invitation email to $email failed to send. Use Resend Invitation on the users list.";
                header("Location: users.php");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $flash    = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
                $preserve = ['role' => 'student', 'index_number' => $idx, 'email' => $email];
            }
        }
    } else {
        // -------- STAFF path: admin/hod/secretary - freeform fields --------
        $full_name  = trim($_POST['full_name']  ?? '');
        $email      = trim($_POST['email']      ?? '');
        $department = trim($_POST['department'] ?? '');
        $gender     = trim($_POST['gender']     ?? '');
        $job_title  = trim($_POST['job_title']  ?? '');

        $err = null;
        if ($full_name === '' || $email === '' || $role === '' || $department === '') {
            $err = 'Full name, email, role and department are required.';
        } elseif (!in_array($role, ['admin','hod','secretary'], true)) {
            $err = 'Invalid role.';
        } elseif (($emailErr = rmu_email_error($email, $role)) !== null) {
            $err = $emailErr;
        }

        if (!$err) {
            $check = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetchColumn()) $err = "A user with email $email already exists.";
        }

        if ($err) {
            $flash    = ['type' => 'error', 'msg' => $err];
            $preserve = compact('full_name','email','role','department','gender','job_title');
        } else {
            $ins = $pdo->prepare("
                INSERT INTO users
                    (full_name, email, password, role, department,
                     job_title, gender, must_change_password)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $ins->execute([
                $full_name, $email,
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                $role, $department,
                $job_title !== '' ? $job_title : null,
                $gender    !== '' ? $gender    : null,
            ]);
            $new_id = (int)$pdo->lastInsertId();

            $sent = reset_user_to_temp_password($pdo, $new_id);
            audit_log($pdo, 'user.created', 'user', $new_id, [
                'role' => $role, 'email' => $email, 'invitation_sent' => $sent['ok'],
            ]);

            $_SESSION['flash_success'] = $sent['ok']
                ? "Account created for $full_name. Login credentials emailed to $email."
                : "Account created for $full_name, but the invitation email to $email failed to send. Use Resend Invitation on the users list.";
            header("Location: users.php");
            exit;
        }
    }
}

// ----------------------------------------------------------------------
// View data
// ----------------------------------------------------------------------
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$job_titles  = $pdo->query("SELECT id, name FROM job_titles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Unclaimed registry roster — feeds the searchable student dropdown.
// Only entries with valid dept + programme FKs are included; a broken
// row would create a user account pointing at a department that doesn't
// exist, which the rest of the system can't render. Admin sees broken
// rows on the registry page and can fix them there.
$unclaimed = $pdo->query("
    SELECT r.index_number, r.full_name, r.email, r.level, r.gender,
           d.name AS dept_name, p.name AS program_name
    FROM student_registry r
    JOIN departments d ON d.id = r.department_id
    JOIN programs    p ON p.id = r.program_id
    WHERE r.is_claimed = 0
    ORDER BY r.full_name
")->fetchAll(PDO::FETCH_ASSOC);

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
    <style>
        .reg-search-wrap { position: relative; }
        .reg-search-results {
            position: absolute; top: 100%; left: 0; right: 0;
            background: #fff; border: 1px solid #d1d5db; border-top: 0;
            max-height: 260px; overflow-y: auto; z-index: 30;
            border-radius: 0 0 6px 6px; box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }
        .reg-search-results .item {
            padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f3f4f6;
        }
        .reg-search-results .item:hover, .reg-search-results .item.focus {
            background: #f3f4f6;
        }
        .reg-search-results .item code { color: #1f2937; font-size: 0.82rem; }
        .reg-search-results .item .meta { font-size: 0.78rem; color: #6b7280; }
        .reg-pick-box {
            background: #f9fafb; border: 1px solid #e5e7eb;
            border-radius: 8px; padding: 14px 16px; margin-top: 12px;
        }
        .reg-pick-box dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 14px; margin: 0; }
        .reg-pick-box dt { color: #6b7280; font-size: 0.8rem; }
        .reg-pick-box dd { margin: 0; font-weight: 600; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Add New User</h1>
            <p>Create an RMU portal account. A temporary password is generated automatically and emailed to the user; they're forced to change it at first login.</p>
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

            <!-- ============ STUDENT BRANCH (registry-driven) ============ -->
            <div id="student_branch" style="display:none;">
                <div class="field">
                    <label>Pick student from registry <span class="req">*</span></label>
                    <div class="reg-search-wrap">
                        <input type="text" id="reg_search" autocomplete="off"
                               placeholder="Type index number, name, department or programme…">
                        <div id="reg_results" class="reg-search-results" style="display:none;"></div>
                    </div>
                    <small class="muted small">
                        Only <strong><?php echo count($unclaimed); ?></strong> unclaimed registry entries shown.
                        To add a student who isn't here, first add them on the
                        <a href="registry.php">Student Registry</a> page.
                    </small>

                    <input type="hidden" name="index_number" id="picked_index">

                    <div id="reg_pick_box" class="reg-pick-box" style="display:none;">
                        <dl>
                            <dt>Name</dt>       <dd id="pk_name"></dd>
                            <dt>Index No.</dt>  <dd><code id="pk_index"></code></dd>
                            <dt>Department</dt> <dd id="pk_dept"></dd>
                            <dt>Programme</dt>  <dd id="pk_prog"></dd>
                            <dt>Level</dt>      <dd id="pk_level"></dd>
                            <dt>Gender</dt>     <dd id="pk_gender"></dd>
                        </dl>
                    </div>
                </div>

                <div class="field">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" id="student_email"
                           value="<?php echo htmlspecialchars($preserve['email'] ?? ''); ?>"
                           placeholder="e.g. j.doe@<?php echo $STUDENT_DOMAIN; ?>">
                    <small class="muted small">Must end in <code>@<?php echo $STUDENT_DOMAIN; ?></code>. Auto-filled from the registry if available.</small>
                </div>
            </div>

            <!-- ============ STAFF BRANCH (freeform) ============ -->
            <div id="staff_branch" style="display:none;">
                <div class="grid-2">
                    <div class="field">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($preserve['full_name'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email"
                               value="<?php echo htmlspecialchars($preserve['email'] ?? ''); ?>"
                               placeholder="e.g. j.doe@<?php echo $STAFF_DOMAIN; ?>">
                        <small class="muted small">Must end in <code>@<?php echo $STAFF_DOMAIN; ?></code></small>
                    </div>
                    <div class="field">
                        <label>Department <span class="req">*</span></label>
                        <select name="department">
                            <option value="">-- Select department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo htmlspecialchars($d['name']); ?>"
                                        <?php echo (($preserve['department'] ?? '') === $d['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
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
            </div>

            <div class="form-actions">
                <a href="users.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>&nbsp; Create Account &amp; Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const UNCLAIMED = <?php echo json_encode($unclaimed, JSON_UNESCAPED_UNICODE); ?>;
const PRESERVED_IDX = <?php echo json_encode($preserve['index_number'] ?? ''); ?>;

const roleSel    = document.getElementById('role_select');
const studentBox = document.getElementById('student_branch');
const staffBox   = document.getElementById('staff_branch');

const regSearch  = document.getElementById('reg_search');
const regResults = document.getElementById('reg_results');
const pickedIdx  = document.getElementById('picked_index');
const pickBox    = document.getElementById('reg_pick_box');
const emailInput = document.getElementById('student_email');

function showBranch() {
    const r = roleSel.value;
    const isStudent = r === 'student';
    const isStaff   = r && !isStudent;
    studentBox.style.display = isStudent ? '' : 'none';
    staffBox.style.display   = isStaff   ? '' : 'none';

    // Disable the inactive branch's controls entirely. Disabled fields
    // are NOT submitted, which avoids the two name="email" inputs (one
    // per branch) colliding — previously the hidden, empty staff email
    // overwrote the student's email on the server, triggering a spurious
    // "email is required". Disabling also keeps HTML5 validation off
    // hidden controls.
    studentBox.querySelectorAll('input').forEach(el => el.disabled = !isStudent);
    staffBox.querySelectorAll('input, select').forEach(el => el.disabled = !isStaff);

    // Required flags only apply to the visible branch.
    document.getElementById('student_email').required = isStudent;
    staffBox.querySelector('input[name="full_name"]').required  = isStaff;
    staffBox.querySelector('input[name="email"]').required      = isStaff;
    staffBox.querySelector('select[name="department"]').required = isStaff;
}
roleSel.addEventListener('change', showBranch);
showBranch();

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
}

function renderResults(rows) {
    if (rows.length === 0) {
        regResults.innerHTML = '<div class="item muted">No matches.</div>';
        regResults.style.display = '';
        return;
    }
    regResults.innerHTML = rows.slice(0, 30).map(r => `
        <div class="item" data-idx="${escapeHtml(r.index_number)}">
            <strong>${escapeHtml(r.full_name)}</strong>
            &nbsp;<code>${escapeHtml(r.index_number)}</code>
            <div class="meta">${escapeHtml(r.dept_name)} · ${escapeHtml(r.program_name)}${r.level ? ' · L' + escapeHtml(r.level) : ''}</div>
        </div>
    `).join('');
    regResults.style.display = '';
    regResults.querySelectorAll('.item').forEach(div => {
        div.addEventListener('mousedown', e => {
            e.preventDefault();
            pickStudent(div.dataset.idx);
        });
    });
}

function pickStudent(idx) {
    const row = UNCLAIMED.find(r => r.index_number === idx);
    if (!row) return;
    pickedIdx.value = idx;
    document.getElementById('pk_name').textContent   = row.full_name;
    document.getElementById('pk_index').textContent  = row.index_number;
    document.getElementById('pk_dept').textContent   = row.dept_name;
    document.getElementById('pk_prog').textContent   = row.program_name;
    document.getElementById('pk_level').textContent  = row.level  || '—';
    document.getElementById('pk_gender').textContent = row.gender || '—';
    pickBox.style.display = '';
    regSearch.value = `${row.full_name} (${row.index_number})`;
    regResults.style.display = 'none';
    if (row.email && !emailInput.value) emailInput.value = row.email;
}

regSearch.addEventListener('input', () => {
    const q = regSearch.value.trim().toLowerCase();
    if (q.length < 2) { regResults.style.display = 'none'; return; }
    const hits = UNCLAIMED.filter(r =>
        r.index_number.toLowerCase().includes(q) ||
        (r.full_name   || '').toLowerCase().includes(q) ||
        (r.dept_name   || '').toLowerCase().includes(q) ||
        (r.program_name|| '').toLowerCase().includes(q)
    );
    renderResults(hits);
});
regSearch.addEventListener('focus', () => {
    if (regSearch.value.trim().length >= 2) regSearch.dispatchEvent(new Event('input'));
});
regSearch.addEventListener('blur', () => {
    setTimeout(() => regResults.style.display = 'none', 150);
});

// Re-select preserved pick after a validation bounce
if (PRESERVED_IDX) pickStudent(PRESERVED_IDX);
</script>
</body>
</html>
