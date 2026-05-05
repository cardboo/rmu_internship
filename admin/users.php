<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/../includes/db.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "index.php"); 
    exit;
}

// 2. Action: Delete User
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    // Prevent self-deletion
    if ($delete_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $msg = "deleted";
    } else {
        $msg = "error_self";
    }
    header("Location: users.php?msg=" . $msg); 
    exit;
}

// 3. Fetch Users & Stats
$stmt = $pdo->query("SELECT * FROM users ORDER BY role ASC, full_name ASC");
$users = $stmt->fetchAll();
$total_users = count($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>"> 
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        </div>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>User Management</h1>
            <p>View and manage all RMU portal accounts</p>
        </div>
        <div class="date-chip">
            <i class="far fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #eef2ff; color: var(--primary);">
                <i class="fas fa-user-friends"></i>
            </div>
            <div>
                <p>Total Registered</p>
                <h2><?php echo $total_users; ?></h2>
            </div>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
            <div style="flex: 2;">
                <?php if($_GET['msg'] == 'deleted'): ?>
                    <div class="status-badge approved" style="width: 100%; justify-content: center; padding: 1rem;">
                        <i class="fas fa-check-circle"></i> User deleted successfully.
                    </div>
                <?php elseif($_GET['msg'] == 'error_self'): ?>
                    <div class="status-badge rejected" style="width: 100%; justify-content: center; padding: 1rem;">
                        <i class="fas fa-times-circle"></i> You cannot delete your own account!
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Identifier</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td>
                        <span class="date-chip" style="font-size: 0.75rem;">
                            <?php echo htmlspecialchars($u['index_number'] ?? 'STF-'.$u['id']); ?>
                        </span>
                    </td>
                    <td><div style="font-weight: 600;"><?php echo htmlspecialchars($u['full_name']); ?></div></td>
                    <td><span style="color: var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></span></td>
                    <td><?php echo htmlspecialchars($u['department']); ?></td>
                    <td>
                        <?php 
                            $roleClass = 'pending';
                            if($u['role'] == 'admin') $roleClass = 'approved';
                            if($u['role'] == 'hod') $roleClass = 'pending'; // Indigo look
                            if($u['role'] == 'secretary') $roleClass = 'rejected'; // Rose look
                        ?>
                        <span class="status-badge <?php echo $roleClass; ?>">
                            <?php echo strtoupper($u['role']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn-action" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="users.php?delete_id=<?php echo $u['id']; ?>" 
                               class="btn-action btn-reject" 
                               onclick="return confirm('Permanent action: Are you sure you want to delete <?php echo addslashes($u['full_name']); ?>?')"
                               title="Delete User">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>