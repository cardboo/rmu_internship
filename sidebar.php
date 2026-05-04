<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$current_file = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'guest';

// Logic to get the profile image
// If the session has a picture, use it. Otherwise, use the default logo.
$user_image = $_SESSION['profile_pic'] ?? 'images/logo.jpg'; 

// Ensure we have a display name
$display_name = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'User';
$profile_filename = $_SESSION['profile_pic'] ?? ''; 
$user_image = !empty($profile_filename) ? "images/profiles/" . $profile_filename : "images/logo.jpg";
?>
<div class="sidebar">
    <div class="profile" style="text-align: center; padding: 20px 10px;">
        <div class="profile-img-container" style="margin-bottom: 15px;">
            <img src="<?php echo htmlspecialchars($user_image); ?>" 
                 alt="Profile Picture" 
                 style="width: 85px; height: 85px; border-radius: 50%; object-fit: cover; border: 3px solid #ffffff; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        </div>

        <h3 style="color: #f5f1f1; font-size: 1.1rem; margin-bottom: 5px;">
            <?php echo htmlspecialchars($display_name); ?>
        </h3>
        <p class="role-label" style="opacity: 0.8; font-size: 0.85rem; color: #fff;">
            <?php echo ucfirst($role); ?> Portal
        </p>
    </div>
    
    <nav>
        <?php 
            $dash_link = 'index.php'; 
            if ($role === 'admin') $dash_link = 'admin_dash.php';
            if ($role === 'student') $dash_link = 'student_dash.php';
            if ($role === 'hod') $dash_link = 'hod_dash.php';
            if ($role === 'secretary') $dash_link = 'secretary_dash.php';
        ?>
        <a href="<?php echo $dash_link; ?>" class="<?php echo ($current_file == $dash_link) ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>&nbsp;&nbsp;Main Dashboard
        </a>

        <?php if ($role === 'admin'): ?>
            <div class="nav-section">SYSTEM ADMIN</div>
            <a href="admin_users.php" class="<?php echo ($current_file == 'admin_users.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>&nbsp;&nbsp;Admin Users
            </a>
            <a href="manage_users.php" class="<?php echo ($current_file == 'manage_users.php' || $current_file == 'edit_user.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>&nbsp;&nbsp;Manage All Users
            </a>
            <a href="admin_registry.php" class="<?php echo ($current_file == 'admin_registry.php') ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>&nbsp;&nbsp;Student Registry
            </a>
            <a href="admin_bulk_upload.php" class="<?php echo ($current_file == 'admin_bulk_upload.php') ? 'active' : ''; ?>">
                <i class="fas fa-upload"></i>&nbsp;&nbsp;Bulk Upload
            </a>
            <a href="admin_settings.php" class="<?php echo ($current_file == 'admin_settings.php') ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>&nbsp;&nbsp;System Settings
            </a>
        <?php endif; ?>

        <?php if ($role === 'hod'): ?>
            <div class="nav-section">ACADEMIC OVERSIGHT</div>
            <a href="staff_logbook_review.php" class="<?php echo ($current_file == 'staff_logbook_review.php') ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>&nbsp;&nbsp;Logbook Reviews
            </a>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
            <div class="nav-section">MY INTERNSHIP</div>
            <a href="student_docs.php" class="<?php echo ($current_file == 'student_docs.php') ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i>&nbsp;&nbsp;Documents
            </a>
            <a href="student_logbook.php" class="<?php echo ($current_file == 'student_logbook.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>&nbsp;&nbsp;My Logbook
            </a>
            <a href="submit_evidence.php" class="<?php echo ($current_file == 'submit_evidence.php') ? 'active' : ''; ?>">
                <i class="fas fa-file-upload"></i>&nbsp;&nbsp;Submit Evidence
            </a>
        <?php endif; ?>

        <div class="nav-section">ACCOUNT</div>
        <?php 
            $profile_link = 'index.php';
            if ($role === 'student') $profile_link = 'student_profile.php';
            if ($role === 'hod') $profile_link = 'hod_profile.php';
            if ($role === 'admin') $profile_link = 'admin_profile.php';
        ?>
        <a href="<?php echo $profile_link; ?>" class="<?php echo ($current_file == $profile_link) ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>&nbsp;&nbsp;My Profile
        </a>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>&nbsp;&nbsp;Logout
        </a>
    </nav>
</div>