<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$message = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_calendar'])) {
    $year_name = trim($_POST['academic_year_name']);
    $sem1_start = $_POST['sem1_start'];
    $sem1_end   = $_POST['sem1_end'];
    $sem2_start = $_POST['sem2_start'];
    $sem2_end   = $_POST['sem2_end'];

    // Validation: Start must be before end for both semesters
    if (strtotime($sem1_start) >= strtotime($sem1_end) || strtotime($sem2_start) >= strtotime($sem2_end)) {
        $message = "<div class='status-badge' style='background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                        <i class='fas fa-exclamation-triangle'></i> Error: Start dates must be before end dates.
                    </div>";
    } else {
        // Update keys in the settings table
        $updates = [
            'academic_year_name' => $year_name,
            'sem1_start' => $sem1_start,
            'sem1_end'   => $sem1_end,
            'sem2_start' => $sem2_start,
            'sem2_end'   => $sem2_end
        ];

        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($updates as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        
        $message = "<div class='status-badge active' style='margin-bottom: 20px; width: 100%; text-align: center; padding: 15px;'>
                        <i class='fas fa-check-circle'></i> Academic Calendar for $year_name updated successfully!
                    </div>";
    }
}

// Fetch Current Settings
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('academic_year_name', 'sem1_start', 'sem1_end', 'sem2_start', 'sem2_end')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .settings-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .form-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-main); }
        .input-field { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; }
        .sem-header { border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px; color: var(--primary); display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-panel">
            <h1>Academic Calendar Settings</h1>
            <p>Configure the specific start and end dates for both semesters.</p>
        </div>

        <?php echo $message; ?>

        <form action="" method="POST">
            <div class="form-card" style="max-width: 100%; margin-bottom: 20px;">
                <div class="input-group" style="max-width: 400px;">
                    <label>Current Academic Year (e.g., 2025/2026)</label>
                    <input type="text" name="academic_year_name" value="<?php echo htmlspecialchars($settings['academic_year_name'] ?? ''); ?>" required class="input-field">
                </div>
            </div>

            <div class="settings-container">
                <div class="form-card">
                    <h3 class="sem-header"><i class="fas fa-filter"></i> Semester 1</h3>
                    <div class="input-group">
                        <label>Start Date</label>
                        <input type="date" name="sem1_start" value="<?php echo htmlspecialchars($settings['sem1_start'] ?? ''); ?>" required class="input-field">
                    </div>
                    <div class="input-group">
                        <label>End Date</label>
                        <input type="date" name="sem1_end" value="<?php echo htmlspecialchars($settings['sem1_end'] ?? ''); ?>" required class="input-field">
                    </div>
                </div>

                <div class="form-card">
                    <h3 class="sem-header"><i class="fas fa-filter"></i> Semester 2</h3>
                    <div class="input-group">
                        <label>Start Date</label>
                        <input type="date" name="sem2_start" value="<?php echo htmlspecialchars($settings['sem2_start'] ?? ''); ?>" required class="input-field">
                    </div>
                    <div class="input-group">
                        <label>End Date</label>
                        <input type="date" name="sem2_end" value="<?php echo htmlspecialchars($settings['sem2_end'] ?? ''); ?>" required class="input-field">
                    </div>
                </div>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" name="update_calendar" class="btn-save" style="width: 250px; padding: 15px;">
                    <i class="fas fa-save"></i> Save Global Calendar
                </button>
            </div>
        </form>
    </div>
</body>
</html>