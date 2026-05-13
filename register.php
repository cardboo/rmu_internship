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
    } elseif ($password !== $confirm) {
        $err = 'Passwords do not match.';
    } elseif (($pwErr = password_strength_error($password)) !== null) {
        $err = $pwErr;
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
        } elseif (empty($reg['email'])) {
            $err = "Your registry record has no email on file. Contact your department secretary to add one before registering.";
        } elseif (strcasecmp($reg['email'], $email) !== 0) {
            // Form field is read-only; this catches anyone who bypassed it.
            $err = "The email on file in the registry doesn't match what was submitted. Please reload the page.";
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
    <link rel="stylesheet" href="<?php echo asset('css/components.css'); ?>">
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
                <input type="email" name="email" id="reg_email" required readonly
                       placeholder="will populate from your registry record"
                       value="<?php echo htmlspecialchars($preserve['email'] ?? ''); ?>"
                       style="background:#f1f5f9;cursor:not-allowed;">
                <small class="muted small">Auto-filled from the registry record once your index number is recognised.</small>
            </div>
            <div class="auth-field">
                <label>Password</label>
                <input type="password" name="password" id="reg_pw" required minlength="8"
                       placeholder="Minimum 8 chars, mix of cases + digit + symbol">
                <ul id="pw_checklist" class="pw-checklist">
                    <li data-rule="len">At least 8 characters</li>
                    <li data-rule="upper">One uppercase letter (A–Z)</li>
                    <li data-rule="lower">One lowercase letter (a–z)</li>
                    <li data-rule="digit">One number (0–9)</li>
                    <li data-rule="symbol">One symbol (!@#$%…)</li>
                </ul>
            </div>
            <div class="auth-field">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="reg_pw_confirm" required minlength="8">
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
        // Reset the email field while the index is being typed.
        emailIn.value = '';
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
                    if (j.data.email) {
                        emailIn.value = j.data.email;
                        hint.textContent = '✓ Found: ' + j.data.full_name + ' — ' + j.data.dept_name;
                        hint.style.color = '#166534';
                    } else {
                        hint.textContent = '⚠ Registry record has no email on file. Ask your department secretary to add one.';
                        hint.style.color = '#991b1b';
                    }
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

    // Live password-strength checklist.
    const pwIn = document.getElementById('reg_pw');
    const checks = document.querySelectorAll('#pw_checklist li');
    pwIn.addEventListener('input', () => {
        const v = pwIn.value;
        const tests = {
            len:    v.length >= 8,
            upper:  /[A-Z]/.test(v),
            lower:  /[a-z]/.test(v),
            digit:  /\d/.test(v),
            symbol: /[^A-Za-z0-9]/.test(v),
        };
        checks.forEach(li => {
            li.classList.toggle('ok', !!tests[li.dataset.rule]);
        });
    });
    </script>
</body>
</html>
