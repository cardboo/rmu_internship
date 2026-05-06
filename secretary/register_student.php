<?php
require __DIR__ . '/../includes/db.php';
require_role('secretary');

$flash = ['type' => '', 'msg' => ''];

$myDeptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
$myDeptStmt->execute([$_SESSION['user_id']]);
$myDept = $myDeptStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $idx      = strtoupper(trim($_POST['index_number'] ?? ''));
    $email    = trim($_POST['email']    ?? '');
    $password = (string)($_POST['password'] ?? '');

    $err = null;
    if ($idx === '' || $email === '' || $password === '') {
        $err = 'Index number, email, and password are required.';
    } elseif (($emailErr = rmu_email_error($email, 'student')) !== null) {
        $err = $emailErr;
    } elseif (strlen($password) < 8) {
        $err = 'Password must be at least 8 characters.';
    }

    // Re-fetch registry row server-side (don't trust the form).
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

        if (!$reg) {
            $err = "No registry entry for $idx.";
        } elseif (strcasecmp($reg['dept_name'], $myDept) !== 0) {
            $err = "$idx is not in your department.";
        } elseif ((int)$reg['is_claimed'] === 1) {
            $err = "$idx already has a portal account.";
        }
    }

    if (!$err) {
        $emailCheck = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $emailCheck->execute([$email]);
        if ($emailCheck->fetchColumn()) {
            $err = "Email $email is already in use.";
        }
    }

    if (!$err) {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("
                INSERT INTO users
                    (full_name, email, password, role, department, program,
                     level, gender, index_number, must_change_password)
                VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, 1)
            ");
            $ins->execute([
                $reg['full_name'], $email, $hash,
                $reg['dept_name'], $reg['program_name'],
                $reg['level'], $reg['gender'], $idx,
            ]);
            $new_id = $pdo->lastInsertId();

            $pdo->prepare("
                UPDATE student_registry
                SET is_claimed = 1, claimed_user_id = ?
                WHERE index_number = ?
            ")->execute([$new_id, $idx]);

            $pdo->commit();
            $_SESSION['temp_pw_notice'] = [
                'name'     => $reg['full_name'],
                'password' => $password,
                'email'    => $email,
            ];
            header("Location: register_student.php?msg=created");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $err = 'Database error: ' . $e->getMessage();
        }
    }

    $flash = ['type' => 'error', 'msg' => $err];
}

$default_pw      = generate_temp_password();
$temp_pw_notice  = $_SESSION['temp_pw_notice'] ?? null;
unset($_SESSION['temp_pw_notice']);

if (($_GET['msg'] ?? '') === 'created') {
    $flash = ['type' => 'success', 'msg' => 'Account created. Share the password below with the student.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Student | <?php echo htmlspecialchars($myDept); ?> Secretary</title>
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
            <h1>Register Student</h1>
            <p>Look up a student by index number from the <strong><?php echo htmlspecialchars($myDept); ?></strong> registry roster, then create their portal account.</p>
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if ($temp_pw_notice): ?>
        <div class="banner banner-warning temp-pw-banner">
            <i class="fas fa-key"></i>
            <strong><?php echo htmlspecialchars($temp_pw_notice['name']); ?></strong>
            (<?php echo htmlspecialchars($temp_pw_notice['email']); ?>) —
            temporary password: <code><?php echo htmlspecialchars($temp_pw_notice['password']); ?></code>.
            They will be forced to change it on first login.
        </div>
    <?php endif; ?>

    <div class="card">
        <h3><i class="fas fa-search"></i>&nbsp; Step 1 — Look up the student</h3>
        <p class="muted">Enter the student's index number. We'll fetch their record from the registry.</p>

        <div class="grid-2" style="align-items: end; gap: 12px;">
            <div class="field">
                <label>Index Number</label>
                <input type="text" id="lookup_idx" placeholder="e.g. BIT0002001" autocomplete="off">
            </div>
            <div class="field">
                <button type="button" id="lookup_btn" class="btn btn-primary">
                    <i class="fas fa-search"></i>&nbsp; Look up
                </button>
            </div>
        </div>

        <div id="lookup_result"></div>
    </div>

    <!-- The actual create-account form. Hidden until lookup succeeds. -->
    <div class="card" id="register_form_card" style="margin-top: 25px; display: none;">
        <h3><i class="fas fa-user-plus"></i>&nbsp; Step 2 — Create the portal account</h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="register" value="1">
            <input type="hidden" name="index_number" id="form_index_number">

            <div class="grid-2">
                <div class="field">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" required placeholder="e.g. j.doe@st.edu.rmu.gh">
                    <small class="muted small">Must end in <code>@<?php echo RMU_STUDENT_DOMAIN; ?></code></small>
                </div>
                <div class="field">
                    <label>Temporary Password <span class="req">*</span></label>
                    <div class="pw-row">
                        <input type="text" name="password" id="pw_input" required minlength="8"
                               value="<?php echo htmlspecialchars($default_pw); ?>">
                        <button type="button" id="regen_btn" class="btn btn-ghost btn-sm" title="Generate new">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <small class="muted small">Save this — student must change it on first login.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i>&nbsp; Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const BASE_URL = <?php echo json_encode(BASE_URL); ?>;

const lookupBtn   = document.getElementById('lookup_btn');
const lookupIdx   = document.getElementById('lookup_idx');
const lookupRes   = document.getElementById('lookup_result');
const formCard    = document.getElementById('register_form_card');
const formIdx     = document.getElementById('form_index_number');

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
}

async function doLookup() {
    const idx = lookupIdx.value.trim();
    if (!idx) return;
    lookupRes.innerHTML = '<p class="muted small" style="margin-top:14px;"><i class="fas fa-spinner fa-spin"></i>&nbsp; Looking up…</p>';
    formCard.style.display = 'none';

    try {
        const r = await fetch(BASE_URL + 'api/registry_lookup.php?index=' + encodeURIComponent(idx));
        const j = await r.json();
        if (!j.ok) {
            lookupRes.innerHTML = `<div class="banner banner-error" style="margin-top:14px;">
                <i class="fas fa-exclamation-circle"></i>&nbsp; ${escapeHtml(j.error)}
            </div>`;
            return;
        }
        const d = j.data;
        lookupRes.innerHTML = `
            <div class="banner banner-success" style="margin-top:14px;">
                <i class="fas fa-check-circle"></i>&nbsp; Found in registry — review the details below and continue to step 2.
            </div>
            <div class="hint-box" style="margin-top:10px;">
                <div class="grid-2" style="gap: 4px 14px;">
                    <span class="muted small">Name</span>      <strong>${escapeHtml(d.full_name)}</strong>
                    <span class="muted small">Index #</span>   <code>${escapeHtml(d.index_number)}</code>
                    <span class="muted small">Department</span><strong>${escapeHtml(d.dept_name)}</strong>
                    <span class="muted small">Program</span>   <strong>${escapeHtml(d.program_name)}</strong>
                    <span class="muted small">Level</span>     <span>${escapeHtml(d.level || '—')}</span>
                    <span class="muted small">Gender</span>    <span>${escapeHtml(d.gender || '—')}</span>
                </div>
            </div>
        `;
        formIdx.value = d.index_number;
        formCard.style.display = '';
    } catch (e) {
        lookupRes.innerHTML = `<div class="banner banner-error" style="margin-top:14px;">
            <i class="fas fa-exclamation-circle"></i>&nbsp; Lookup failed (network error).
        </div>`;
    }
}

lookupBtn.addEventListener('click', doLookup);
lookupIdx.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
});

document.getElementById('regen_btn').addEventListener('click', () => {
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
</script>
</body>
</html>
