<?php
require __DIR__ . '/../includes/db.php';

// 1. Security Check: Role must be 'secretary'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'secretary') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$sec_id = $_SESSION['user_id'];

// 2. Fetch Semester Dates
$stmtSem = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('semester_start', 'semester_end')");
$settings = [];
while ($row = $stmtSem->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$sem_start = $settings['semester_start'] ?? date('Y-m-d');
$sem_end = $settings['semester_end'] ?? date('Y-m-d');

// 3. Fetch Secretary's Department and the HOD's Signature for that department
$userStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
$userStmt->execute([$sec_id]);
$myDept = $userStmt->fetchColumn();

// We fetch the HOD's signature for this department so the Secretary can issue letters
$hodSigStmt = $pdo->prepare("SELECT signature_path FROM users WHERE role = 'hod' AND department = ? LIMIT 1");
$hodSigStmt->execute([$myDept]);
$hodSignature = $hodSigStmt->fetchColumn();
$hasSignature = !empty($hodSignature);

// 4. Fetch Statistics (LOCKED TO SECRETARY'S DEPARTMENT)
$stmtStats = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM requests r
    JOIN users u ON r.student_id = u.id
    WHERE u.department = ?");
$stmtStats->execute([$myDept]);
$counts = $stmtStats->fetch(PDO::FETCH_ASSOC);

// 5. Fetch Requests (LOCKED TO SECRETARY'S DEPARTMENT)
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

    // Secretary can only approve if the HOD has a signature uploaded
    if ($status === 'approved' && !$hasSignature) {
        echo json_encode(['success' => false, 'message' => 'HOD signature missing for this department!']);
        exit;
    }

    // Security check: Is this student in the Secretary's department?
    $check = $pdo->prepare("SELECT u.department FROM requests r JOIN users u ON r.student_id = u.id WHERE r.id = ?");
    $check->execute([$id]);
    $reqDept = $check->fetchColumn();

    if ($reqDept === $myDept) {
        $stmt = $pdo->prepare("UPDATE requests SET status = ?, rejection_reason = ? WHERE id = ?");
        $stmt->execute([$status, ($status === 'rejected' ? $reason : null), $id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unauthorized departmental access.']);
    }
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secretary Dashboard | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 25px; width: 450px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .details-row { display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
        .details-label { color: #64748b; font-size: 0.85rem; }
        .details-value { font-weight: 600; color: #1e293b; }
        .sig-status { padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid; }
        .sig-ok { background: #dcfce7; border-color: #bbf7d0; color: #166534; }
        .sig-err { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
        .overlap-warning { font-size: 0.75rem; background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; display: block; margin-top: 4px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1><?php echo htmlspecialchars($myDept); ?> | Secretary Portal</h1>

        <?php if ($hasSignature): ?>
            <div class="sig-status sig-ok"><i class="fas fa-check-circle"></i> HOD Signature is active. You are authorized to approve letters.</div>
        <?php else: ?>
            <div class="sig-status sig-err"><i class="fas fa-times-circle"></i> <strong>Alert:</strong> HOD has not uploaded a signature. Approvals are disabled.</div>
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
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
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
                        $is_overlap = ($req['start_date'] <= $sem_end && $req['end_date'] >= $sem_start);
                    ?>
                    <tr>
                        <td style="font-weight: 500;"><?php echo htmlspecialchars($req['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['company_name']); ?></td>
                        <td>
                            <?php echo $weeks; ?> Weeks
                            <?php if($is_overlap): ?>
                                <span class="overlap-warning"><i class="fas fa-flag"></i> Semester Overlap</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-badge <?php echo $req['status']; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                        <td>
                            <button onclick='viewDetails(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, "UTF-8"); ?>)' class="btn-action"><i class="fas fa-eye"></i></button>
                            
                            <?php if($req['status'] == 'pending'): ?>
                                <button onclick="updateStatus(<?php echo $req['id']; ?>, 'approved')" class="btn-action <?php echo !$hasSignature ? 'btn-disabled' : ''; ?>" style="color: #10b981;">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button onclick="rejectWithComment(<?php echo $req['id']; ?>)" class="btn-action" style="color: #ef4444;">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php elseif($req['status'] == 'approved'): ?>
                                <a href="<?php echo BASE_URL; ?>api/generate_letter.php?id=<?php echo $req['id']; ?>" target="_blank" style="color: #2563eb;"><i class="fas fa-file-pdf"></i></a>
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
            <h3>Request Details</h3>
            <div id="modalBody"></div>
            <button onclick="closeModal()" class="btn-login" style="margin-top: 15px; background: #64748b; width: 100%;">Close</button>
        </div>
    </div>

    <script>
    function viewDetails(data) {
        document.getElementById('modalBody').innerHTML = `
            <div class="details-row"><span class="details-label">Student:</span> <span class="details-value">${data.full_name}</span></div>
            <div class="details-row"><span class="details-label">Program:</span> <span class="details-value">${data.program}</span></div>
            <div class="details-row"><span class="details-label">Company:</span> <span class="details-value">${data.company_name}</span></div>
            <div class="details-row"><span class="details-label">Dates:</span> <span class="details-value">${data.start_date} to ${data.end_date}</span></div>
        `;
        document.getElementById('detailsModal').style.display = 'block';
    }

    function closeModal() { document.getElementById('detailsModal').style.display = 'none'; }

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
            if(data.success) location.reload();
            else alert(data.message);
        });
    }

    function rejectWithComment(id) {
        const reason = prompt("Enter rejection reason:");
        if (reason) updateStatus(id, 'rejected', reason);
    }
    </script>
</body>
</html>