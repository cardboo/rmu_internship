<?php
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$success_count = 0;
$error_count = 0;
$skipped_count = 0;
$messages = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['student_csv'])) {
    $file = $_FILES['student_csv']['tmp_name'];

    if ($_FILES['student_csv']['size'] > 0) {
        $handle = fopen($file, "r");
        
        // Skip the header row
        fgetcsv($handle);

        // Prepare the insert statement once for speed
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, department, level, program) VALUES (?, ?, ?, 'student', ?, ?, ?)");
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        
        // Default hashed password (123456)
        $default_pass = password_hash('123456', PASSWORD_DEFAULT);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Mapping: 0=Name, 1=Email, 2=Dept, 3=Level, 4=Program
            $name = trim($data[0]);
            $email = trim($data[1]);
            $dept = trim($data[2]);
            $level = trim($data[3]);
            $program = trim($data[4]);

            // Basic Validation
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_count++;
                continue;
            }

            // Check if user exists
            $checkStmt->execute([$email]);
            if ($checkStmt->rowCount() > 0) {
                $skipped_count++;
                continue;
            }

            // Execute Insert
            if ($stmt->execute([$name, $email, $default_pass, $dept, $level, $program])) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        fclose($handle);
        $messages[] = "<div class='success-banner'>Import Finished: $success_count added, $skipped_count skipped (duplicates), $error_count errors.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Upload | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .upload-box { background: white; padding: 40px; border-radius: 12px; text-align: center; border: 2px dashed #0D8ABC; max-width: 600px; margin: 20px auto; }
        .success-banner { background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .template-link { display: inline-block; margin-top: 15px; color: #0D8ABC; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>Bulk Student Import</h1>
        <p>Upload a CSV file containing student details to create accounts in bulk.</p>

        <?php foreach($messages as $m) echo $m; ?>

        <div class="upload-box">
            <i class="fas fa-file-csv" style="font-size: 3rem; color: #0D8ABC; margin-bottom: 20px;"></i>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="file" name="student_csv" accept=".csv" required style="display: block; margin: 0 auto 20px;">
                <button type="submit" class="btn-login" style="background: #0D8ABC; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-size: 1rem;">
                    Start Import
                </button>
            </form>
            <a href="<?php echo BASE_URL; ?>templates/student_import_template.csv" class="template-link" download>
             <i class="fas fa-download"></i> Download CSV Template
            </a>
        </div>

        <div style="margin-top: 40px; background: #f8fafc; padding: 20px; border-radius: 8px;">
            <h3>Instructions:</h3>
            <ul style="line-height: 1.8; color: #475569;">
                <li>Ensure the file is in <strong>.csv</strong> format.</li>
                <li>The first row should be headers (Full Name, Email, etc.).</li>
                <li><strong>All imported students</strong> will have the default password: <code>123456</code>.</li>
                <li>Duplicates based on email will be automatically ignored.</li>
            </ul>
        </div>
    </div>
</body>
</html>