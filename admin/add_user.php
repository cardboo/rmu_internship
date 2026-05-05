<?php
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$message = "";

// Handle User Deletion
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    
    // Prevent admin from deleting themselves
    if ($del_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$del_id])) {
            $message = "<div class='success-banner'>User deleted successfully!</div>";
        }
    } else {
        $message = "<div class='error-banner'>You cannot delete your own admin account.</div>";
    }
}

// Handle Add New User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $department = $_POST['department'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $level = ($role == 'student') ? $_POST['level'] : null;
    $program = ($role == 'student') ? $_POST['program'] : null;
    $job_title = ($role != 'student') ? $_POST['job_title'] : null;

    // Check if email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        $message = "<div class='error-banner'>Error: A user with this email already exists.</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, department, level, program, job_title) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $password, $role, $department, $level, $program, $job_title])) {
            $message = "<div class='success-banner'>New $role added successfully!</div>";
        } else {
            $message = "<div class='error-banner'>Error adding user.</div>";
        }
    }
}

// Fetch all users except the current admin
$stmt = $pdo->prepare("SELECT * FROM users WHERE id != ? ORDER BY role ASC, full_name ASC");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .form-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .success-banner { background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .error-banner { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .hidden { display: none; }
        .btn-delete { color: #dc2626; cursor: pointer; text-decoration: none; font-weight: bold; }
        .btn-delete:hover { text-decoration: underline; }
        .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .role-student { background: #e0f2fe; color: #0369a1; }
        .role-hod { background: #fef3c7; color: #92400e; }
        .role-secretary { background: #f3e8ff; color: #7e22ce; }
        .role-admin { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>User Management</h1>
        
        <?php echo $message; ?>

        <div class="form-card">
            <h3 style="margin-bottom: 20px; border-bottom: 2px solid #0D8ABC; padding-bottom: 10px;">Add New User</h3>
            <form action="" method="POST">
                <div class="input-grid" style="margin-bottom: 15px;">
                    <div>
                        <label>Full Name</label>
                        <input type="text" name="full_name" required class="input-field">
                    </div>
                    <div>
                        <label>Email Address</label>
                        <input type="email" name="email" required class="input-field">
                    </div>
                </div>

                <div class="input-grid" style="margin-bottom: 15px;">
                    <div>
                        <label>Role</label>
                        <select name="role" id="role_select" class="input-field" required onchange="toggleFields()">
                            <option value="student">Student</option>
                            <option value="secretary">Department Secretary</option>
                            <option value="hod">Head of Department (HOD)</option>
                            <option value="admin">System Admin</option>
                        </select>
                    </div>
                    <div>
                        <label>Department</label>
                        <input type="text" name="department" placeholder="e.g. Nautical Science" required class="input-field">
                    </div>
                </div>

                <div id="student_fields" class="input-grid" style="margin-bottom: 15px;">
                    <div>
                        <label>Level</label>
                        <select name="level" class="input-field">
                            <option value="100">Level 100</option>
                            <option value="200">Level 200</option>
                            <option value="300">Level 300</option>
                            <option value="400">Level 400</option>
                        </select>
                    </div>
                    <div>
                        <label>Program of Study</label>
                        <input type="text" name="program" placeholder="e.g. BSc. Nautical Science" class="input-field">
                    </div>
                </div>

                <div id="staff_fields" class="hidden" style="margin-bottom: 15px;">
                    <label>Job Title (For Staff)</label>
                    <input type="text" name="job_title" placeholder="e.g. Senior Lecturer" class="input-field" style="width: 100%;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label>Temporary Password</label>
                    <input type="password" name="password" required class="input-field" style="width: 100%;">
                </div>

                <button type="submit" name="add_user" class="btn-login" style="width: 200px; background: #0D8ABC; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-plus-circle"></i> Create User
                </button>
            </form>
        </div>

        <div class="table-container">
            <h2>System Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['department']); ?></td>
                        <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                        <td>
                            <a href="?delete_id=<?php echo $u['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function toggleFields() {
        const role = document.getElementById('role_select').value;
        const studentFields = document.getElementById('student_fields');
        const staffFields = document.getElementById('staff_fields');

        if (role === 'student') {
            studentFields.classList.remove('hidden');
            staffFields.classList.add('hidden');
        } else {
            studentFields.classList.add('hidden');
            staffFields.classList.remove('hidden');
        }
    }
    </script>
</body>
</html>