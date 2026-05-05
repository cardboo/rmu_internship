<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_log'])) {
    $week = $_POST['week_number'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $activities = $_POST['activities'];
    
    $file_path = null;

    // Handle File Upload - Better Folder Organization
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
        $target_dir = "uploads/logbooks/"; 
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
        $new_filename = "log_" . $student_id . "_w" . $week . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $target_file)) {
            $file_path = $target_file;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO logbooks (student_id, week_number, start_date, end_date, activities, file_path) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$student_id, $week, $start, $end, $activities, $file_path])) {
        // Updated to match your portal's design system
        $message = "<div class='status-badge active' style='margin-bottom: 20px; width: 100%; text-align: center; padding: 15px;'><i class='fas fa-check-circle'></i> Week $week logbook and proof submitted successfully!</div>";
    }
}

// Fetch all previous logs
$stmt = $pdo->prepare("SELECT * FROM logbooks WHERE student_id = ? ORDER BY week_number DESC");
$stmt->execute([$student_id]);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Logbook | RMU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .log-card { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid var(--primary, #0D8ABC); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .proof-link { display: inline-block; margin-top: 10px; color: var(--primary, #0D8ABC); font-weight: 600; text-decoration: none; padding: 5px 10px; background: #f0f4ff; border-radius: 5px; transition: 0.3s; }
        .proof-link:hover { background: #e0e7ff; }
        .form-card { background: white; padding: 25px; border-radius: 10px; border: 1px solid var(--border); }
    </style>
</head>
<body>
    
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="header-panel">
            <h1>Weekly Work Log</h1>
            <p>Document your daily activities and upload weekly proof signed by your supervisor.</p>
        </div>

        <?php echo $message; ?>

        <div class="form-card" style="margin-bottom: 40px;">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-edit"></i> Submit New Weekly Entry</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="metric-box">
                        <label style="font-weight: 600; font-size: 0.85rem;">Week #</label>
                        <input type="number" name="week_number" required class="date-chip" min="1" style="width: 100%; margin-top: 5px;">
                    </div>
                    <div class="metric-box">
                        <label style="font-weight: 600; font-size: 0.85rem;">From Date</label>
                        <input type="date" name="start_date" required class="date-chip" style="width: 100%; margin-top: 5px;">
                    </div>
                    <div class="metric-box">
                        <label style="font-weight: 600; font-size: 0.85rem;">To Date</label>
                        <input type="date" name="end_date" required class="date-chip" style="width: 100%; margin-top: 5px;">
                    </div>
                </div>

                <div class="metric-box" style="margin-bottom: 15px;">
                    <label style="font-weight: 600; font-size: 0.85rem;">Activities Overview</label>
                    <textarea name="activities" required class="date-chip" rows="4" style="width: 100%; margin-top: 5px; border: 1px solid var(--border); resize: vertical;"></textarea>
                </div>

                <div class="metric-box" style="margin-bottom: 20px;">
                    <label style="font-weight: 600; font-size: 0.85rem;">Upload Signed Proof (Photo/PDF)</label>
                    <input type="file" name="proof_file" accept="image/*,.pdf" required class="date-chip" style="width: 100%; margin-top: 5px; border: 1px solid var(--border);">
                </div>

                <div style="text-align: right;">
                    <button type="submit" name="submit_log" class="btn-save">
                        <i class="fas fa-cloud-upload-alt"></i> Submit Week Log
                    </button>
                </div>
            </form>
        </div>

        <h2 style="margin-bottom: 15px;"><i class="fas fa-history"></i> Previous Submissions</h2>
        
        <?php if (empty($logs)): ?>
            <div style="padding: 20px; background: #fff; text-align: center; border-radius: 10px; color: #64748b;">
                No logbooks submitted yet.
            </div>
        <?php else: ?>
            <?php foreach($logs as $log): ?>
                <div class="log-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 10px;">
                        <strong style="font-size: 1.1rem; color: #1e293b;">
                            Week <?php echo htmlspecialchars($log['week_number']); ?> 
                            <span style="font-size: 0.8rem; color: #64748b; font-weight: normal; margin-left: 10px;">
                                (<?php echo htmlspecialchars($log['start_date']); ?> to <?php echo htmlspecialchars($log['end_date']); ?>)
                            </span>
                        </strong>
                        
                        <?php if($log['file_path']): ?>
                            <a href="<?php echo htmlspecialchars($log['file_path']); ?>" target="_blank" class="proof-link">
                                <i class="fas fa-paperclip"></i> View Proof
                            </a>
                        <?php endif; ?>
                    </div>
                    <p style="color: #475569; line-height: 1.6; font-size: 0.95rem;">
                        <?php echo nl2br(htmlspecialchars($log['activities'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</body>
</html>