<?php
// CRITICAL: Always start the session first
session_start();
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$PROFILE_DIR = __DIR__ . '/../assets/images/profiles/';

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// 1. Fetch Student Data & Latest Request Status
$stmt = $pdo->prepare("SELECT u.*, 
    (SELECT status FROM requests WHERE student_id = u.id ORDER BY request_date DESC LIMIT 1) as latest_status,
    (SELECT rejection_reason FROM requests WHERE student_id = u.id ORDER BY request_date DESC LIMIT 1) as latest_reason
    FROM users u WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Helper for Profile Image URL
$displayName = urlencode($user['full_name']);
$defaultAvatar = "https://ui-avatars.com/api/?name=$displayName&background=0D8ABC&color=fff";
$currentPhoto = (!empty($user['profile_path']) && file_exists($PROFILE_DIR . $user['profile_path']))
                ? asset('images/profiles/' . $user['profile_path'])
                : $defaultAvatar;

// 2. Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pix'])) {
    $file = $_FILES['profile_pix'];
    $allowed = ['image/png', 'image/jpeg', 'image/jpg'];
    
    if (in_array($file['type'], $allowed)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_name = "profile_" . $user_id . "_" . time() . "." . $ext;
        $folder = $PROFILE_DIR;
        $path = $folder . $new_name;

        if (!is_dir($folder)) mkdir($folder, 0777, true);

        if (move_uploaded_file($file['tmp_name'], $path)) {
            // Delete old pic if exists and it's not empty
            if (!empty($user['profile_path']) && file_exists($folder . $user['profile_path'])) {
                unlink($folder . $user['profile_path']);
            }

            $pdo->prepare("UPDATE users SET profile_path = ? WHERE id = ?")->execute([$new_name, $user_id]);
            $message = "Profile picture updated successfully!";

            // Refresh local data to show new image immediately
            $user['profile_path'] = $new_name;
            $currentPhoto = asset('images/profiles/' . $new_name);
        }
    } else {
        $error = "Invalid file type. Please use PNG or JPG.";
    }
}

// 3. Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_pass === $confirm_pass) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
        $message = "Password updated successfully!";
    } else {
        $error = "Passwords do not match.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .profile-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 700px; margin: 0 auto; }
        .status-banner { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .status-pending { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
        .status-approved { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
        .status-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .avatar-upload { position: relative; width: 150px; margin: 0 auto 20px; }
        .avatar-upload img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #0D8ABC; display: block; }
        .upload-btn { position: absolute; bottom: 5px; right: 5px; background: #0D8ABC; color: white; width: 35px; height: 35px; line-height: 35px; text-align: center; border-radius: 50%; cursor: pointer; transition: 0.3s; }
        .upload-btn:hover { background: #0a6da0; transform: scale(1.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #444; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .btn-update { width: 100%; background: #0D8ABC; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 16px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>Profile Settings</h1>

        <?php if($user['latest_status']): ?>
            <div class="status-banner status-<?php echo $user['latest_status']; ?>">
                <i class="fas fa-bell"></i> 
                Attachment Status: <?php echo ucfirst($user['latest_status']); ?>
                <?php if($user['latest_status'] == 'rejected'): ?>
                    <div style="font-weight: normal; font-size: 0.9em; margin-top: 5px;">
                        <strong>Reason:</strong> <?php echo htmlspecialchars($user['latest_reason']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($message): ?>
            <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <form action="" method="POST" enctype="multipart/form-data" id="pixForm">
                <div class="avatar-upload">
                    <img src="<?php echo $currentPhoto; ?>" id="preview">
                    <label for="profile_pix" class="upload-btn"><i class="fas fa-camera"></i></label>
                    <input type="file" name="profile_pix" id="profile_pix" style="display:none;" onchange="document.getElementById('pixForm').submit();">
                </div>
                <p style="text-align: center; font-size: 0.9em; color: #666;">Click camera to upload new photo</p>
            </form>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

            <h3>Security Settings</h3>
            <form action="" method="POST" style="margin-top:20px;">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
                <button type="submit" class="btn-update">Update Password</button>
            </form>
        </div>
    </div>
</body>
</html>