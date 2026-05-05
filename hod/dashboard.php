<?php
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'hod') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$hod_id = $_SESSION['user_id'];

// Fetch Semester Dates for checking overlaps
$stmtSem = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('semester_start', 'semester_end')");
$settings = [];
while ($row = $stmtSem->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$sem_start = $settings['semester_start'] ?? date('Y-m-d');
$sem_end = $settings['semester_end'] ?? date('Y-m-d');

// 1. Fetch HOD's specific info
$hodStmt = $pdo->prepare("SELECT department, job_title, signature_path FROM users WHERE id = ?");
$hodStmt->execute([$hod_id]);
$hodInfo = $hodStmt->fetch();

$myDept = $hodInfo['department'];
$displayTitle = $hodInfo['job_title'];
$hasSignature = !empty($hodInfo['signature_path']); 

// 2. Fetch Statistics (LOCKED TO HOD'S DEPARTMENT)
$stmtStats = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM requests r
    JOIN users u ON r.student_id = u.id
    WHERE u.department = ?");
$stmtStats->execute([$myDept]);
$counts = $stmtStats->fetch(PDO::FETCH_ASSOC);

// 3. Fetch Requests (LOCKED TO HOD'S DEPARTMENT)
$stmtReq = $pdo->prepare("SELECT r.*, u.full_name, u.level, u.program 
        FROM requests r 
        JOIN users u ON r.student_id = u.id 
        WHERE u.department = ?
        ORDER BY r.request_date DESC");
$stmtReq->execute([$myDept]);
$requests = $stmtReq->fetchAll();

// --- Handle Status Updates (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_id'])) {
    $id = $_POST['update_id'];
    $status = $_POST['new_status'];
    $reason = $_POST['comment'] ?? null;

    if ($status === 'approved' && !$hasSignature) {
        echo json_encode(['success' => false, 'message' => 'Upload signature first!']);
        exit;
    }

    // 4. Security Wall: Verify the request belongs to a student in this HOD's department
    $check = $pdo->prepare("SELECT u.department FROM requests r JOIN users u ON r.student_id = u.id WHERE r.id = ?");
    $check->execute([$id]);
    $reqDept = $check->fetchColumn();

    if ($reqDept === $myDept) {
        if ($status === 'rejected') {
            $stmt = $pdo->prepare("UPDATE requests SET status = ?, rejection_reason = ? WHERE id = ?");
            $stmt->execute([$status, $reason, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE requests SET status = ?, rejection_reason = NULL WHERE id = ?");
            $stmt->execute([$status, $id]);
        }
        echo json_encode(['success' => true]);
    } else {
        // Reject the action if the departments don't match
        echo json_encode(['success' => false, 'message' => 'Security Error: Student is not in your department.']);
    }
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HOD Dashboard | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 25px; width: 450px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .details-row { display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
        .details-label { color: #64748b; font-size: 0.85rem; }
        .details-value { font-weight: 600; color: #1e293b; }
        
        .notif-bell { position: relative; font-size: 1.4rem; color: #64748b; margin-right: 15px; cursor: pointer; }
        .notif-badge { background: #ef4444; color: white; padding: 2px 6px; border-radius: 50%; font-size: 0.7rem; position: absolute; top: -5px; right: -5px; border: 2px solid #f8fafc; }
        .sig-warning { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .btn-disabled { opacity: 0.5; cursor: not-allowed !important; filter: grayscale(1); }
        .overlap-warning { font-size: 0.75rem; background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; display: block; margin-top: 4px; text-align: center; }
        .btn-download { color: #2563eb; font-size: 1.2rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-panel" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1><?php echo htmlspecialchars($myDept); ?> Dashboard</h1>
                <p style="color: #64748b;">Managing student industrial attachment requests.</p>
            </div>
            
            <div class="notif-bell" onclick="location.reload()" title="Refresh Dashboard">
                <i class="fas fa-bell"></i>
                <?php if ($counts['pending'] > 0): ?>
                    <span class="notif-badge"><?php echo $counts['pending']; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$hasSignature): ?>
            <div class="sig-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>You must <strong><a href="profile.php" style="color: #991b1b; text-decoration: underline;">upload your digital signature</a></strong> before you can approve any requests.</span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(37,99,235,0.1); color:#2563eb;"><i class="fas fa-file-alt"></i></div>
                <div><p>Total</p><h2><?php echo (int)$counts['total']; ?></h2></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color:#f59e0b;"><i class="fas fa-clock"></i></div>
                <div><p>Pending</p><h2><?php echo (int)$counts['pending']; ?></h2></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color:#10b981;"><i class="fas fa-check-circle"></i></div>
                <div><p>Approved</p><h2><?php echo (int)$counts['approved']; ?></h2></div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Company</th>
                        <th>Duration</th> 
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($requests as $req): 
                        $d1 = new DateTime($req['start_date']);
                        $d2 = new DateTime($req['end_date']);
                        $weeks = floor($d1->diff($d2)->days / 7);
                        
                        // Check for overlap
                        $is_overlap = ($req['start_date'] <= $sem_end && $req['end_date'] >= $sem_start);
                    ?>
                    <tr>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($req['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['company_name']); ?></td>
                        <td>
                            <span style="font-weight:600;"><?php echo $weeks; ?> Weeks</span>
                            <?php if($is_overlap): ?>
                                <span class="overlap-warning" title="Dates fall inside active semester"><i class="fas fa-flag"></i> Overlaps Semester</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-badge <?php echo $req['status']; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                        <td>
                            <button onclick='viewDetails(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, "UTF-8"); ?>)' class="btn-action" style="background: #f1f5f9; color: #475569;" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <?php if($req['status'] == 'pending'): ?>
                                <button onclick="<?php echo $hasSignature ? "updateStatus({$req['id']}, 'approved')" : "alert('Please upload your signature in Profile Settings first!')"; ?>" 
                                        class="btn-action btn-approve <?php echo !$hasSignature ? 'btn-disabled' : ''; ?>" 
                                        title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                
                                <button onclick="rejectWithComment(<?php echo $req['id']; ?>)" class="btn-action btn-reject" title="Reject">
                                    <i class="fas fa-comment-slash"></i>
                                </button>
                            <?php elseif($req['status'] == 'approved'): ?>
                                <a href="<?php echo BASE_URL; ?>api/generate_letter.php?id=<?php echo $req['id']; ?>" target="_blank" class="btn-download" title="Download Letter">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; border-bottom: 2px solid #0D8ABC; padding-bottom: 10px;">Request Details</h3>
            <div id="modalBody"></div>
            <button onclick="closeModal()" class="btn-login" style="margin-top: 15px; background: #64748b; width: 100%;">Close</button>
        </div>
    </div>

    <script>
    // 1. Show Student Details
    function viewDetails(data) {
        const body = document.getElementById('modalBody');
        body.innerHTML = `
            <div class="details-row"><span class="details-label">Student:</span> <span class="details-value">${data.full_name}</span></div>
            <div class="details-row"><span class="details-label">Level / Program:</span> <span class="details-value">${data.level} - ${data.program}</span></div>
            <div class="details-row"><span class="details-label">Company:</span> <span class="details-value">${data.company_name}</span></div>
            <div class="details-row"><span class="details-label">Address:</span> <span class="details-value">${data.company_address}</span></div>
            <div class="details-row"><span class="details-label">Dates:</span> <span class="details-value">${data.start_date} to ${data.end_date}</span></div>
        `;
        document.getElementById('detailsModal').style.display = 'block';
    }

    // 2. Close Modal
    function closeModal() { 
        document.getElementById('detailsModal').style.display = 'none'; 
    }

    // 3. Rejection Logic
    function rejectWithComment(id) {
        const reason = prompt("Enter rejection reason for the student:");
        if (reason && reason.trim() !== "") {
            updateStatus(id, 'rejected', reason);
        } else if (reason === "") {
            alert("You must provide a reason for rejection.");
        }
    }

    // 4. Update Status AJAX
    function updateStatus(id, status, comment = "") {
        const formData = new URLSearchParams();
        formData.append('update_id', id);
        formData.append('new_status', status);
        formData.append('comment', comment);

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert(data.message || "Error updating status");
            }
        })
        .catch(err => alert("Communication error with server."));
    }

    // Close modal on outside click
    window.onclick = function(event) {
        let modal = document.getElementById('detailsModal');
        if (event.target == modal) closeModal();
    }
    </script>
</body>
</html>