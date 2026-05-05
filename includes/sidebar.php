<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Don't render the sidebar at all for unauthenticated users.
// Pages already gate on session, but this is a defensive belt-and-braces.
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    return;
}

// "role/file.php" relative to project root, used to highlight the active link.
$script_path  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$current_path = ltrim(preg_replace('#^' . preg_quote(rtrim(BASE_URL, '/'), '#') . '#', '', $script_path), '/');

$role = $_SESSION['role'];

$display_name     = $_SESSION['name'] ?? 'User';
$profile_filename = $_SESSION['profile_pic'] ?? '';
$user_image       = !empty($profile_filename)
    ? asset('images/profiles/' . $profile_filename)
    : asset('images/logo.jpg');

$dash_routes = [
    'admin'     => 'admin/dashboard.php',
    'hod'       => 'hod/dashboard.php',
    'secretary' => 'secretary/dashboard.php',
    'student'   => 'student/dashboard.php',
];
$dash_link = $dash_routes[$role] ?? 'index.php';

// Roles without a dedicated profile page have the link hidden entirely
// (rather than dumping the user back at the login screen).
$profile_routes = [
    'admin'   => 'admin/profile.php',
    'hod'     => 'hod/profile.php',
    'student' => 'student/profile.php',
];
$profile_link = $profile_routes[$role] ?? null;

if (!function_exists('nav_active')) {
    function nav_active(string $route, string $current): string {
        return $route === $current ? 'active' : '';
    }
}
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
        <a href="<?php echo url($dash_link); ?>" class="<?php echo nav_active($dash_link, $current_path); ?>">
            <i class="fas fa-th-large"></i>&nbsp;&nbsp;Main Dashboard
        </a>

        <?php if ($role === 'admin'): ?>
            <div class="nav-section">SYSTEM ADMIN</div>
            <a href="<?php echo url('admin/users.php'); ?>"
               class="<?php echo (in_array($current_path, ['admin/users.php','admin/edit_user.php','admin/add_user.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>&nbsp;&nbsp;Manage Users
            </a>
            <a href="<?php echo url('admin/registry.php'); ?>" class="<?php echo nav_active('admin/registry.php', $current_path); ?>">
                <i class="fas fa-database"></i>&nbsp;&nbsp;Student Registry
            </a>
            <a href="<?php echo url('admin/bulk_upload.php'); ?>" class="<?php echo nav_active('admin/bulk_upload.php', $current_path); ?>">
                <i class="fas fa-upload"></i>&nbsp;&nbsp;Bulk Upload
            </a>
            <a href="<?php echo url('admin/settings.php'); ?>" class="<?php echo nav_active('admin/settings.php', $current_path); ?>">
                <i class="fas fa-cogs"></i>&nbsp;&nbsp;System Settings
            </a>
        <?php endif; ?>

        <?php if ($role === 'hod'): ?>
            <div class="nav-section">ACADEMIC OVERSIGHT</div>
            <a href="<?php echo url('hod/logbook_review.php'); ?>" class="<?php echo nav_active('hod/logbook_review.php', $current_path); ?>">
                <i class="fas fa-book-open"></i>&nbsp;&nbsp;Logbook Reviews
            </a>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
            <div class="nav-section">MY INTERNSHIP</div>
            <a href="<?php echo url('student/docs.php'); ?>" class="<?php echo nav_active('student/docs.php', $current_path); ?>">
                <i class="fas fa-folder-open"></i>&nbsp;&nbsp;Documents
            </a>
            <a href="<?php echo url('student/logbook.php'); ?>" class="<?php echo nav_active('student/logbook.php', $current_path); ?>">
                <i class="fas fa-book"></i>&nbsp;&nbsp;My Logbook
            </a>
            <a href="<?php echo url('student/submit_evidence.php'); ?>" class="<?php echo nav_active('student/submit_evidence.php', $current_path); ?>">
                <i class="fas fa-file-upload"></i>&nbsp;&nbsp;Submit Evidence
            </a>
        <?php endif; ?>

        <div class="nav-section">ACCOUNT</div>
        <?php if ($profile_link): ?>
            <a href="<?php echo url($profile_link); ?>" class="<?php echo nav_active($profile_link, $current_path); ?>">
                <i class="fas fa-user-circle"></i>&nbsp;&nbsp;My Profile
            </a>
        <?php endif; ?>
        <a href="<?php echo url('logout.php'); ?>" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>&nbsp;&nbsp;Logout
        </a>
    </nav>
</div>
