<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/../includes/db.php';

$success = ""; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['performance_sheet'])) {
    $target_dir = "uploads/evidence/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

    // Only handling the performance sheet now
    $perf_file = $target_dir . time() . "_perf_" . basename($_FILES["performance_sheet"]["name"]);

    if (move_uploaded_file($_FILES["performance_sheet"]["tmp_name"], $perf_file)) {
        
        // Updated SQL to only insert the performance scan
        $stmt = $pdo->prepare("INSERT INTO internship_submissions (user_id, performance_scan) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $perf_file]);
        $success = "Performance sheet uploaded successfully! Awaiting faculty review.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Evidence | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="header-panel">
            <h1>Internship Evidence</h1>
            <p>Upload your final scanned performance sheet for academic grading.</p>
        </div>

        <?php if ($success): ?>
            <div class="status-badge active" style="margin-bottom: 20px; width: 100%; text-align: center; padding: 15px;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="table-container" style="max-width: 600px; margin: 20px auto; padding: 30px;">
            <h3 style="margin-bottom: 25px;"><i class="fas fa-upload" style="color: var(--primary);"></i> Upload Performance Sheet</h3>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="metric-box">
                    <label style="font-weight: 600; font-size: 0.9rem; color: var(--text-main);">Scanned Performance Sheet (with Supervisor Signature)</label>
                    <input type="file" name="performance_sheet" required class="date-chip" style="width: 100%; margin-top: 10px; border: 1px solid var(--border);">
                </div>
                
                <button type="submit" class="btn-save" style="width: 100%; margin-top: 30px; display: flex; justify-content: center; padding: 12px;">
                    <i class="fas fa-paper-plane" style="margin-right: 8px;"></i> Submit for Grading
                </button>
            </form>
        </div>

    </div> 
</body>
</html>