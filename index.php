<?php
// CRITICAL: Ensure session starts before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Trim inputs to remove invisible spaces
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // 2. Prepare the query
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 3. Verify User & Password
    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID for security
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];
        $_SESSION['dept'] = $user['department'];
        $_SESSION['profile_pic'] = $user['profile_path'];

        // 4. Role-Based Redirection
        switch ($user['role']) {
            case 'admin':
                header("Location: " . BASE_URL . "admin/dashboard.php");
                break;
            case 'hod':
                header("Location: " . BASE_URL . "hod/dashboard.php");
                break;
            case 'secretary':
                header("Location: " . BASE_URL . "secretary/dashboard.php");
                break;
            case 'student':
                header("Location: " . BASE_URL . "student/dashboard.php");
                break;
            default:
                $error = "User role not recognized.";
                break;
        }
        if (!isset($error)) exit;
        
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMU Internship Portal | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/login.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
</head>
<body class="login-body no-sidebar">
    <div class="login-card">
        <div style="text-align: center;">
            <img src="<?php echo asset('images/logo.jpg'); ?>" alt="RMU Logo" style="width: 100px; margin-bottom: 1rem; border-radius: 8px;">
            <h2 style="color: #0D8ABC; margin-bottom: 5px;">RMU</h2>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">Internship & Attachment Portal</p>
        </div>
        
        <?php if(isset($error)): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; border: 1px solid #ef4444;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group" style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Email Address</label>
                <input type="email" name="email" placeholder="e.g. name@rmu.edu.gh" required 
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;">
            </div>
            
            <div class="input-group" style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Password</label>
                <input type="password" name="password" placeholder="••••••••" required 
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;">
            </div>
            
            <button type="submit" class="btn-login" 
                    style="width: 100%; background: #0D8ABC; color: white; border: none; padding: 14px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 1rem; transition: 0.3s;">
                Sign In
            </button>
        </form>
        
        <div style="margin-top: 20px; text-align: center; font-size: 0.85rem; color: #888;">
            &copy; <?php echo date('Y'); ?> Regional Maritime University
        </div>
    </div>
</body>
</html>