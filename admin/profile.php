<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/../includes/db.php';

// Security: Ensure only admins access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$PROFILE_DIR = __DIR__ . '/../assets/images/profiles/';

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// --- HANDLE PROFILE UPDATES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Handle Password Change
    if (!empty($_POST['new_password'])) {
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if ($new_pass === $confirm_pass) {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_pass, $user_id]);
            $success = "Password updated successfully!";
        } else {
            $error = "Passwords do not match.";
        }
    }

    // 2. Handle Profile Image Upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $target_dir = $PROFILE_DIR;
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

        $file_ext = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed)) {
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                // Update Database
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->execute([$new_filename, $user_id]);

                // Update Session so the sidebar reflects changes immediately
                $_SESSION['profile_pic'] = $new_filename;
                $success = "Profile image updated successfully!";
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type. Please upload JPG, PNG, or WEBP.";
        }
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT full_name, email, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <style>
        .profile-card { background: white; border-radius: 15px; border: 1px solid var(--border); overflow: hidden; max-width: 800px; margin: 20px auto; display: flex; }
        .profile-sidebar { background: #f8fafc; padding: 40px; text-align: center; border-right: 1px solid var(--border); width: 300px; }
        .profile-form-area { padding: 40px; flex: 1; }
        .current-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 15px; }
        .form-section { margin-bottom: 30px; }
        .form-section h4 { margin-bottom: 15px; color: var(--primary); font-size: 1rem; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: #64748b; }
        .input-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-panel">
            <h1>Admin Profile</h1>
            <p>Update your personal information and security credentials.</p>
        </div>

        <?php if ($success): ?>
            <div class="status-badge active" style="margin-bottom: 20px; width: 100%; text-align: center; padding: 15px;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="status-badge" style="margin-bottom: 20px; width: 100%; text-align: center; padding: 15px; background: #fee2e2; color: #991b1b;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-sidebar">
                <?php
                    $pic = !empty($user['profile_pic'])
                        ? asset('images/profiles/' . $user['profile_pic'])
                        : asset('images/logo.jpg');
                ?>
                <img src="<?php echo $pic; ?>" class="current-avatar" alt="Admin Avatar">
                <h3 style="font-size: 1.1rem;"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                <p style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($user['email']); ?></p>
                <div style="margin-top: 20px; padding: 10px; background: #e0f2fe; border-radius: 8px; color: #0369a1; font-size: 0.75rem; font-weight: 700;">
                    SYSTEM ADMINISTRATOR
                </div>
            </div>

            <div class="profile-form-area">
                <form action="" method="POST" enctype="multipart/form-data">
                    
                    <div class="form-section">
                        <h4><i class="fas fa-camera"></i> Profile Picture</h4>
                        <div class="input-group">
                            <label>Upload New Photo (JPG, PNG, WEBP)</label>
                            <input type="file" name="profile_image" class="input-control">
                        </div>
                    </div>

                    <div class="form-section">
                        <h4><i class="fas fa-lock"></i> Security & Password</h4>
                        <div class="input-group">
                            <label>New Password (Leave blank to keep current)</label>
                            <input type="password" name="new_password" class="input-control" placeholder="••••••••">
                        </div>
                        <div class="input-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="input-control" placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" class="btn-save" style="width: 100%; padding: 12px; font-weight: 700;">
                        <i class="fas fa-sync-alt"></i> Update Profile Information
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>