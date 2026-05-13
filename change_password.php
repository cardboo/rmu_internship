<?php
// This page MUST bypass the force-password-change gate (which would
// otherwise redirect to itself in an infinite loop).
define('SKIP_PASSWORD_GATE', true);
require __DIR__ . '/includes/db.php';

// Anyone authenticated can use this page (admin/hod/secretary/student).
if (empty($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$forced  = !empty($_SESSION['must_change_password']);
$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($current, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (($pwErr = password_strength_error($new)) !== null) {
        $error = $pwErr;
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif ($new === $current) {
        $error = 'New password must be different from your current password.';
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare(
            "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?"
        );
        $upd->execute([$new_hash, $user_id]);

        $_SESSION['must_change_password'] = false;

        // Send the user back to wherever they belong.
        $dash = [
            'admin'     => 'admin/dashboard.php',
            'hod'       => 'hod/dashboard.php',
            'secretary' => 'secretary/dashboard.php',
            'student'   => 'student/dashboard.php',
        ][$_SESSION['role'] ?? ''] ?? 'index.php';

        header("Location: " . BASE_URL . $dash . "?msg=password_changed");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | RMU Internship Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/auth.css'); ?>">
</head>
<body class="auth-body no-sidebar">
    <div class="auth-card">
        <div class="auth-header">
            <img src="<?php echo asset('images/logo.jpg'); ?>" alt="RMU Logo">
            <h2>Change Password</h2>
            <?php if ($forced): ?>
                <div class="auth-banner banner-warning">
                    <i class="fas fa-shield-alt"></i>
                    You are using a temporary password. Please choose a new one to continue.
                </div>
            <?php else: ?>
                <p class="auth-sub">Update the password for your RMU portal account.</p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="auth-banner banner-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="auth-field">
                <label>Current / Temporary Password</label>
                <input type="password" name="current_password" required autofocus>
            </div>
            <div class="auth-field">
                <label>New Password</label>
                <input type="password" name="new_password" id="cp_pw" required minlength="8"
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
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>

            <button type="submit" class="auth-btn">
                <i class="fas fa-check-circle"></i>&nbsp; Update Password
            </button>
        </form>

        <script>
        const pwIn = document.getElementById('cp_pw');
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
            checks.forEach(li => li.classList.toggle('ok', !!tests[li.dataset.rule]));
        });
        </script>

        <div class="auth-footer">
            <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout instead</a>
        </div>
    </div>
</body>
</html>
