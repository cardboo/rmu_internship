<?php
require 'db.php';

// Access Control: HOD and Secretary only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['hod', 'secretary'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Get the Staff's Department
$deptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
$deptStmt->execute([$user_id]);
$myDept = $deptStmt->fetchColumn();

// 2. Handle Feedback (With a security check to ensure the log belongs to their dept)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    $log_id = $_POST['log_id'];
    
    // Security Check: Does this log entry belong to a student in MY department?
    $checkStmt = $pdo->prepare("SELECT u.department FROM logbooks l JOIN users u ON l.student_id = u.id WHERE l.id = ?");
    $checkStmt->execute([$log_id]);
    $logDept = $checkStmt->fetchColumn();

    if ($logDept === $myDept) {
        $stmt = $pdo->prepare("UPDATE logbooks SET staff_comment = ?, is_reviewed = 1 WHERE id = ?");
        $stmt->execute([$_POST['staff_comment'], $log_id]);
    } else {
        die("Security Error: Unauthorized departmental access.");
    }
}

// 3. Fetch ONLY logs from students in this department
$stmt = $pdo->prepare("
    SELECT l.*, u.full_name, u.program 
    FROM logbooks l 
    JOIN users u ON l.student_id = u.id 
    WHERE u.department = ? 
    ORDER BY l.submission_date DESC
");
$stmt->execute([$myDept]);
$all_logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Departmental Reviews | RMU</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1><?php echo htmlspecialchars($myDept); ?> Logbook Reviews</h1>
        <p>You are viewing records exclusively for the <strong><?php echo htmlspecialchars($myDept); ?></strong> department.</p>

        <?php if(empty($all_logs)): ?>
            <div class="info-note">No logbooks submitted in your department yet.</div>
        <?php endif; ?>

        <?php foreach($all_logs as $log): ?>
            <div class="form-card" style="margin-bottom: 20px; border-top: 4px solid #0D8ABC;">
                <strong><?php echo htmlspecialchars($log['full_name']); ?></strong> 
                <small>(<?php echo htmlspecialchars($log['program']); ?>)</small>
                <p><?php echo nl2br(htmlspecialchars($log['activities'])); ?></p>
                </div>
        <?php endforeach; ?>
    </div>
</body>
</html>