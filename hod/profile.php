<?php
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hod') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$SIG_DIR = __DIR__ . '/../assets/images/signatures/';

$user_id = $_SESSION['user_id'];
$message = "";

// 1. Fetch current user details first to get their department
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$myDept = $user['department']; // Get the department for the notification query

// 2. UPDATED: Notification Count (Locked to HOD's Department)
$notifStmt = $pdo->prepare("SELECT COUNT(*) 
                            FROM requests r 
                            JOIN users u ON r.student_id = u.id 
                            WHERE r.status = 'pending' AND u.department = ?");
$notifStmt->execute([$myDept]);
$pendingCount = $notifStmt->fetchColumn();

// 3. Handle Signature Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['signature'])) {
    $file = $_FILES['signature'];
    
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
    if (!in_array($file['type'], $allowed_types)) {
        $message = "Error: Only PNG and JPG files are allowed.";
    } else {
        // Fetch old signature to delete it and save space
        $oldSig = $user['signature_path'];

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = "sig_" . $user_id . "_" . time() . "." . $extension;
        $upload_path = $SIG_DIR . $new_filename;

        if (!is_dir($SIG_DIR)) {
            mkdir($SIG_DIR, 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Delete old file if it exists
            if ($oldSig && file_exists($SIG_DIR . $oldSig)) {
                unlink($SIG_DIR . $oldSig);
            }

            $upd = $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
            if ($upd->execute([$new_filename, $user_id])) {
                $message = "Signature updated successfully!";
                // Refresh local user data to show the new signature immediately
                $user['signature_path'] = $new_filename;
            }
        } else {
            $message = "Error: Upload failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HOD Profile | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <style>
        .notif-badge {
            background: #ef4444; color: white; padding: 2px 7px;
            border-radius: 50%; font-size: 0.7rem; position: absolute;
            top: -8px; right: -8px; border: 2px solid white;
        }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .notif-bell { position: relative; font-size: 1.4rem; color: #64748b; cursor: pointer; transition: 0.3s; }
        .notif-bell:hover { color: #0D8ABC; }
        .dept-tag { background: #e2e8f0; color: #475569; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-top">
            <div>
                <h1>Profile Settings</h1>
                <span class="dept-tag"><?php echo htmlspecialchars($myDept); ?></span>
            </div>
            
            <div class="notif-bell" onclick="location.href='dashboard.php'" title="Pending Requests for <?php echo $myDept; ?>">
                <i class="fas fa-bell"></i>
                <?php if ($pendingCount > 0): ?>
                    <span class="notif-badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container" style="padding: 30px; max-width: 600px; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <?php if ($message): ?>
                <div style="padding: 12px; margin-bottom: 20px; background: #dcfce7; color: #166534; border-radius: 8px; border-left: 5px solid #22c55e;">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Full Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['full_name']); ?>" disabled style="width: 100%; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Current Signature Image</label>
                    <div style="border: 2px dashed #e2e8f0; padding: 20px; border-radius: 8px; text-align: center; background: #fcfcfc;">
                        <?php if ($user['signature_path']): ?>
                            <img src="<?php echo asset('images/signatures/' . $user['signature_path']); ?>" alt="Signature" style="max-height: 80px;">
                        <?php else: ?>
                            <p style="color: #94a3b8; font-style: italic;">No signature uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Upload New Signature</label>
                    <input type="file" name="signature" required style="font-size: 0.9rem;">
                    <p style="font-size: 0.75rem; color: #64748b; margin-top: 5px;">Must be a PNG or JPG. Transparent PNG is best for PDFs.</p>
                </div>

                <button type="submit" class="btn-login" style="width: 100%; padding: 12px; background: #0D8ABC; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.3s;">
                    Update Signature
                </button>
            </form>
        </div>
    </div>
</body>
</html>