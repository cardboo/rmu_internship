<?php
// Public self-registration (no session needed to GET).
// Bypass the password-change gate (no user is logged in here anyway).
define('SKIP_PASSWORD_GATE', true);
require __DIR__ . '/includes/db.php';

// If the user is already logged in, send them to their dashboard.
if (!empty($_SESSION['user_id'])) {
    $dash = [
        'admin'     => 'admin/dashboard.php',
        'hod'       => 'hod/dashboard.php',
        'secretary' => 'secretary/dashboard.php',
        'student'   => 'student/dashboard.php',
    ][$_SESSION['role'] ?? ''] ?? 'index.php';
    header("Location: " . BASE_URL . $dash);
    exit;
}

$flash = ['type' => '', 'msg' => ''];
$preserve = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idx      = strtoupper(trim($_POST['index_number']    ?? ''));
    $email    = trim($_POST['email']                      ?? '');
    $password = (string)($_POST['password']               ?? '');
    $confirm  = (string)($_POST['confirm_password']       ?? '');

    $err = null;
    if ($idx === '' || $email === '' || $password === '' || $confirm === '') {
        $err = 'All fields are required.';
    } elseif (($emailErr = rmu_email_error($email, 'student')) !== null) {
        $err = $emailErr;
    } elseif (strlen($password) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $err = 'Passwords do not match.';
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

        if (!$reg) {
            $err = "We couldn't find that index number. Contact your department secretary.";
        } elseif ((int)$reg['is_claimed'] === 1) {
            $err = "An account already exists for this index number. Try logging in instead.";
        }
    }

    if (!$err) {
        $emailCheck = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $emailCheck->execute([$email]);
        if ($emailCheck->fetchColumn()) {
            $err = "That email is already in use. Try logging in instead.";
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
                VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, 0)
            ");
            // must_change_password = 0 because the student chose this password
            // themselves; we don't need to force them to change it again.
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

            // Auto-log them in.
            session_regenerate_id(true);
            $_SESSION['user_id']              = $new_id;
            $_SESSION['role']                 = 'student';
            $_SESSION['name']                 = $reg['full_name'];
            $_SESSION['dept']                 = $reg['dept_name'];
            $_SESSION['profile_pic']          = null;
            $_SESSION['must_change_password'] = false;
            header("Location: " . BASE_URL . "student/dashboard.php?msg=welcome");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $err = 'Database error: ' . $e->getMessage();
        }
    }

    $flash    = ['type' => 'error', 'msg' => $err];
    $preserve = compact('idx', 'email');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | RMU Internship Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/auth.css'); ?>">
</head>
<body class="auth-body no-sidebar">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?php echo asset('images/logo.jpg'); ?>" alt="RMU Logo">
            <h2>Create a Student Account</h2>
            <p class="auth-sub">Use the index number issued by the registry. Only RMU student emails (<code>@<?php echo RMU_STUDENT_DOMAIN; ?></code>) are allowed.</p>
        </div>

        <?php if ($flash['msg']): ?>
            <div class="auth-banner banner-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="auth-field">
                <label>Index Number</label>
                <input type="text" name="index_number" required
                       placeholder="e.g. BIT0002001"
                       value="<?php echo htmlspecialchars($preserve['idx'] ?? ''); ?>">
                <small id="lookup_hint" class="muted small">We'll verify this against the registry once you fill in your details.</small>
            </div>
            <div class="auth-field">
                <label>RMU Student Email</label>
                <input type="email" name="email" required
                       placeholder="j.doe@<?php echo RMU_STUDENT_DOMAIN; ?>"
                       value="<?php echo htmlspecialchars($preserve['email'] ?? ''); ?>">
            </div>
            <div class="auth-field">
                <label>Password</label>
                <input type="password" name="password" required minlength="8"
                       placeholder="At least 8 characters">
            </div>
            <div class="auth-field">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>

            <button type="submit" class="auth-btn">
                <i class="fas fa-user-plus"></i>&nbsp; Create Account
            </button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?php echo BASE_URL; ?>index.php">Log in</a>
        </div>
    </div>

    <script>
    // Live registry lookup hint as the index is typed (debounced).
    const BASE_URL = <?php echo json_encode(BASE_URL); ?>;
    const idxIn    = document.querySelector('input[name="index_number"]');
    const emailIn  = document.querySelector('input[name="email"]');
    const hint     = document.getElementById('lookup_hint');
    let timer;

    idxIn.addEventListener('input', () => {
        clearTimeout(timer);
        const v = idxIn.value.trim();
        if (v.length < 4) {
            hint.textContent = "We'll verify this against the registry once you fill in your details.";
            hint.style.color = '';
            return;
        }
        timer = setTimeout(async () => {
            hint.textContent = 'Checking…';
            hint.style.color = '#64748b';
            try {
                const r = await fetch(BASE_URL + 'api/registry_lookup.php?public=1&index=' + encodeURIComponent(v));
                const j = await r.json();
                if (j.ok) {
                    hint.textContent = '✓ Found: ' + j.data.full_name + ' — ' + j.data.dept_name;
                    hint.style.color = '#166534';
                    // If the registry has an email on file and the user hasn't typed
                    // one yet, pre-fill it so they don't have to.
                    if (j.data.email && !emailIn.value) emailIn.value = j.data.email;
                } else {
                    hint.textContent = '⚠ ' + j.error;
                    hint.style.color = '#991b1b';
                }
            } catch (e) {
                hint.textContent = 'Lookup failed (network).';
                hint.style.color = '#991b1b';
            }
        }, 400);
    });
    </script>
</body>
</html>
