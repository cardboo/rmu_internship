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

        // Notify the student. Non-fatal if email is misconfigured.
        require_once __DIR__ . '/../includes/email.php';
        $studStmt = $pdo->prepare("
            SELECT u.email, u.full_name
            FROM requests r JOIN users u ON u.id = r.student_id
            WHERE r.id = ?
        ");
        $studStmt->execute([$id]);
        if ($s = $studStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($status === 'approved') {
                $body = "Hello " . htmlspecialchars($s['full_name']) . ",\n\n"
                      . "Your industrial attachment letter request has been approved by your HOD.\n"
                      . "Log into the RMU Internship Portal to download the official PDF letter.\n\n"
                      . BASE_URL . "index.php\n\n"
                      . "— RMU Internship Portal";
                try_send_email($pdo, $s['email'], 'Attachment letter approved', $body, false);
            } elseif ($status === 'rejected') {
                $body = "Hello " . htmlspecialchars($s['full_name']) . ",\n\n"
                      . "Your industrial attachment letter request has been rejected.\n"
                      . "Reason: " . htmlspecialchars($reason ?? '(no reason given)') . "\n\n"
                      . "Log in to the portal to submit a revised request.\n\n"
                      . BASE_URL . "index.php\n\n"
                      . "— RMU Internship Portal";
                try_send_email($pdo, $s['email'], 'Attachment letter rejected', $body, false);
            }
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
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboards.css'); ?>">
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
            <div class="modal-header">
                <h3><i class="fas fa-file-signature"></i>&nbsp; Attachment Request Details</h3>
                <button class="modal-close" onclick="closeModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button class="btn-action" style="background:#e2e8f0; color:#475569;" onclick="closeModal()">Close</button>
                <button id="modalReject" style="display:none; background:#ef4444; color:white; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600;" onclick="modalReject()">
                    <i class="fas fa-times"></i>&nbsp; Reject
                </button>
                <button id="modalApprove" style="display:none; background:#16a34a; color:white; border:none; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600;" onclick="modalApprove()">
                    <i class="fas fa-check"></i>&nbsp; Approve
                </button>
                <a id="modalDownload" href="#" target="_blank" class="btn-action" style="background:#0D8ABC; color:white; display:none;">
                    <i class="fas fa-file-pdf"></i>&nbsp; Download Letter
                </a>
            </div>
        </div>
    </div>

    <script>
    const BASE_URL      = <?php echo json_encode(BASE_URL); ?>;
    const HAS_SIGNATURE = <?php echo json_encode((bool)$hasSignature); ?>;

    function escapeHtml(s) {
        if (s === null || s === undefined) return '—';
        return String(s)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }
    function fmtDate(s) {
        if (!s) return '—';
        const d = new Date(s);
        return isNaN(d) ? s : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }
    function statusBadge(status) {
        const map = {
            pending:  ['#fef3c7', '#92400e', 'Pending'],
            approved: ['#dcfce7', '#166534', 'Approved'],
            rejected: ['#fee2e2', '#991b1b', 'Rejected']
        };
        const [bg, fg, label] = map[status] || ['#e2e8f0', '#475569', status];
        return `<span style="background:${bg};color:${fg};padding:4px 12px;border-radius:999px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;">${label}</span>`;
    }

    // 1. Show Student Details (richer modal)
    function viewDetails(data) {
        const body = document.getElementById('modalBody');
        const dl   = document.getElementById('modalDownload');

        const start = fmtDate(data.start_date);
        const end   = fmtDate(data.end_date);

        let weeks = '';
        if (data.start_date && data.end_date) {
            const ms = new Date(data.end_date) - new Date(data.start_date);
            if (!isNaN(ms) && ms >= 0) weeks = Math.floor(ms / (1000*60*60*24*7)) + ' weeks';
        }

        const rejection = (data.status === 'rejected' && data.rejection_reason)
            ? `<div class="det-section"><h4>Rejection Reason</h4>
                 <div class="det-rejection">${escapeHtml(data.rejection_reason)}</div></div>`
            : '';

        body.innerHTML = `
            <div class="det-status-row">
                <div>
                    <div class="student-name">${escapeHtml(data.full_name)}</div>
                    <div class="student-meta">Level ${escapeHtml(data.level)} &middot; ${escapeHtml(data.program)}</div>
                </div>
                ${statusBadge(data.status)}
            </div>

            <div class="det-section">
                <h4>Host Organisation</h4>
                <div class="det-grid">
                    <span class="det-label">Company</span>
                    <span class="det-value">${escapeHtml(data.company_name)}</span>
                    <span class="det-label">Address</span>
                    <span class="det-value">${escapeHtml(data.company_address)}</span>
                </div>
            </div>

            <div class="det-section">
                <h4>Attachment Period</h4>
                <div class="det-grid">
                    <span class="det-label">Start</span><span class="det-value">${start}</span>
                    <span class="det-label">End</span><span class="det-value">${end}</span>
                    ${weeks ? `<span class="det-label">Duration</span><span class="det-value">${weeks}</span>` : ''}
                </div>
            </div>

            <div class="det-section">
                <h4>Submission</h4>
                <div class="det-grid">
                    <span class="det-label">Requested</span>
                    <span class="det-value">${fmtDate(data.request_date)}</span>
                </div>
            </div>

            ${rejection}
        `;

        // Show download letter only if approved
        if (data.status === 'approved') {
            dl.href = BASE_URL + 'api/generate_letter.php?id=' + encodeURIComponent(data.id);
            dl.style.display = '';
        } else {
            dl.style.display = 'none';
        }

        // Approve / Reject buttons in the modal, only for pending requests.
        // Approve only enabled when HOD has a signature on file.
        const approveBtn = document.getElementById('modalApprove');
        const rejectBtn  = document.getElementById('modalReject');
        if (data.status === 'pending') {
            approveBtn.style.display = '';
            rejectBtn.style.display  = '';
            approveBtn.dataset.reqId = data.id;
            rejectBtn.dataset.reqId  = data.id;
            if (!HAS_SIGNATURE) {
                approveBtn.disabled = true;
                approveBtn.title    = 'Upload your signature first';
                approveBtn.style.opacity = '0.5';
                approveBtn.style.cursor  = 'not-allowed';
            } else {
                approveBtn.disabled = false;
                approveBtn.title    = '';
                approveBtn.style.opacity = '1';
                approveBtn.style.cursor  = 'pointer';
            }
        } else {
            approveBtn.style.display = 'none';
            rejectBtn.style.display  = 'none';
        }

        document.getElementById('detailsModal').classList.add('open');
    }

    function modalApprove() {
        const id = document.getElementById('modalApprove').dataset.reqId;
        if (!id) return;
        if (!HAS_SIGNATURE) { alert('Upload your signature in Profile Settings first.'); return; }
        updateStatus(id, 'approved');
    }
    function modalReject() {
        const id = document.getElementById('modalReject').dataset.reqId;
        if (!id) return;
        rejectWithComment(id);
    }

    // 2. Close Modal
    function closeModal() {
        document.getElementById('detailsModal').classList.remove('open');
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