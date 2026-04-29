<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';

// 1. SECURITY: Must be Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// 2. FETCH USER DATA
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_users.php");
    exit;
}
$user_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$target_user = $stmt->fetch();

if (!$target_user) {
    die("User not found.");
}

// 3. HANDLE FORM SUBMISSION (UPDATE)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $department = trim($_POST['department']);
    $index_number = !empty($_POST['index_number']) ? trim($_POST['index_number']) : NULL;
    $job_title = !empty($_POST['job_title']) ? trim($_POST['job_title']) : NULL;

    try {
        $update_stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, role = ?, department = ?, index_number = ?, job_title = ? 
            WHERE id = ?
        ");
        $update_stmt->execute([$full_name, $email, $role, $department, $index_number, $job_title, $user_id]);
        
        $success_msg = "User details updated successfully!";
        // Refresh local array to show updated values in form
        $target_user['full_name'] = $full_name;
        $target_user['email'] = $email;
        $target_user['role'] = $role;
        $target_user['department'] = $department;
        $target_user['index_number'] = $index_number;
        $target_user['job_title'] = $job_title;

    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header-panel">
            <div>
                <h1>Edit User Account</h1>
                <p>Modify credentials for <strong><?php echo htmlspecialchars($target_user['full_name']); ?></strong></p>
            </div>
            <a href="manage_users.php" class="date-chip" style="text-decoration: none; color: inherit;">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="table-container" style="padding: 2rem;">
            <?php if(isset($success_msg)): ?>
                <div class="status-badge approved" style="width: 100%; margin-bottom: 20px; padding: 15px; justify-content: center;">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error_msg)): ?>
                <div class="status-badge rejected" style="width: 100%; margin-bottom: 20px; padding: 15px; justify-content: center;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                    
                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Full Name</label>
                        <input type="text" name="full_name" class="date-chip" style="width: 100%; padding: 12px;" value="<?php echo htmlspecialchars($target_user['full_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email Address</label>
                        <input type="email" name="email" class="date-chip" style="width: 100%; padding: 12px;" value="<?php echo htmlspecialchars($target_user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">System Role</label>
                        <select name="role" class="date-chip" style="width: 100%; padding: 12px; height: 45px;">
                            <option value="student" <?php if($target_user['role'] == 'student') echo 'selected'; ?>>Student</option>
                            <option value="secretary" <?php if($target_user['role'] == 'secretary') echo 'selected'; ?>>Secretary</option>
                            <option value="hod" <?php if($target_user['role'] == 'hod') echo 'selected'; ?>>HOD</option>
                            <option value="admin" <?php if($target_user['role'] == 'admin') echo 'selected'; ?>>System Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Department</label>
                        <select name="department" class="date-chip" style="width: 100%; padding: 12px; height: 45px;">
                            <?php 
                            $depts = ['ICT', 'Marine Engineering', 'Nautical Science', 'Transport', 'Electrical', 'Mechanical', 'Accounting', 'Administration'];
                            foreach($depts as $d) {
                                $selected = ($target_user['department'] == $d) ? 'selected' : '';
                                echo "<option value='$d' $selected>$d</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Student Index Number</label>
                        <input type="text" name="index_number" class="date-chip" style="width: 100%; padding: 12px;" value="<?php echo htmlspecialchars($target_user['index_number'] ?? ''); ?>" placeholder="e.g. 22201000">
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Staff Job Title</label>
                        <input type="text" name="job_title" class="date-chip" style="width: 100%; padding: 12px;" value="<?php echo htmlspecialchars($target_user['job_title'] ?? ''); ?>" placeholder="e.g. Senior Lecturer">
                    </div>

                </div>

                <div style="margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px; text-align: right;">
                    <button type="submit" class="sidebar nav a active" style="border: none; cursor: pointer; display: inline-flex; width: auto; padding: 12px 30px;">
                        <i class="fas fa-save"></i>&nbsp;&nbsp;Update User Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>