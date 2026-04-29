<?php
require 'db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// --- 1. Handle Search & Filter Logic ---
$search = $_GET['search'] ?? '';
$dept_filter = $_GET['dept'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query_parts = [];
$params = [];

if (!empty($search)) {
    $query_parts[] = "(u.full_name LIKE ? OR u.index_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($dept_filter)) {
    $query_parts[] = "u.department = ?";
    $params[] = $dept_filter;
}

if (!empty($status_filter)) {
    $query_parts[] = "r.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($query_parts) ? "WHERE " . implode(" AND ", $query_parts) : "";

// --- 2. Fetch Global Statistics ---
$stmtGlobal = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM requests");
$globalStats = $stmtGlobal->fetch(PDO::FETCH_ASSOC);

// --- 3. Fetch Departments for the Filter Dropdown ---
$depts = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''")->fetchAll(PDO::FETCH_COLUMN);

// --- 4. Fetch Filtered Requests ---
$stmtReq = $pdo->prepare("SELECT r.*, u.full_name, u.department, u.program, u.index_number 
        FROM requests r 
        JOIN users u ON r.student_id = u.id 
        $where_clause
        ORDER BY r.request_date DESC");
$stmtReq->execute($params);
$requests = $stmtReq->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Filter Bar Styling */
        .filter-bar { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 0.8rem; font-weight: 600; color: #64748b; }
        .filter-input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; outline: none; min-width: 200px; }
        .btn-filter { background: #0D8ABC; color: white; border: none; padding: 9px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; height: 38px; }
        .btn-reset { background: #f1f5f9; color: #475569; padding: 9px 15px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; height: 18px; line-height: 18px; }
        
        /* Modal and general styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 25px; width: 450px; border-radius: 12px; }
        .details-row { display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
        .details-label { color: #64748b; font-size: 0.85rem; }
        .details-value { font-weight: 600; color: #1e293b; }
        .dept-badge { background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Global Search</h1>
        <p style="color: #64748b; margin-bottom: 20px;">Filter through all student attachment requests across the university.</p>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Search Student</label>
                <input type="text" name="search" class="filter-input" placeholder="Name or Index No." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Department</label>
                <select name="dept" class="filter-input">
                    <option value="">All Departments</option>
                    <?php foreach($depts as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $dept_filter == $d ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status" class="filter-input">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
            <a href="admin_dash.php" class="btn-reset">Clear</a>
        </form>

        <div class="table-container">
            <h3>Results (<?php echo count($requests); ?> found)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student / Index</th>
                        <th>Department</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: #94a3b8;">No matching records found.</td></tr>
                    <?php endif; ?>
                    <?php foreach($requests as $req): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($req['full_name']); ?></div>
                            <small style="color: #64748b;"><?php echo htmlspecialchars($req['index_number']); ?></small>
                        </td>
                        <td><span class="dept-badge"><?php echo htmlspecialchars($req['department']); ?></span></td>
                        <td><?php echo htmlspecialchars($req['company_name']); ?></td>
                        <td><span class="status-badge <?php echo $req['status']; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                        <td>
                            <button onclick='viewDetails(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, "UTF-8"); ?>)' class="btn-action">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; border-bottom: 2px solid #0D8ABC; padding-bottom: 10px;">Master Record</h3>
            <div id="modalBody"></div>
            <button onclick="closeModal()" class="btn-login" style="margin-top: 15px; background: #64748b; width: 100%; border:none; color:white; padding:10px; border-radius:6px; cursor:pointer;">Close</button>
        </div>
    </div>

    <script>
    function viewDetails(data) {
        document.getElementById('modalBody').innerHTML = `
            <div class="details-row"><span class="details-label">Full Name:</span> <span class="details-value">${data.full_name}</span></div>
            <div class="details-row"><span class="details-label">Index Number:</span> <span class="details-value">${data.index_number}</span></div>
            <div class="details-row"><span class="details-label">Department:</span> <span class="details-value">${data.department}</span></div>
            <div class="details-row"><span class="details-label">Company:</span> <span class="details-value">${data.company_name}</span></div>
            <div class="details-row"><span class="details-label">Request Date:</span> <span class="details-value">${data.request_date}</span></div>
            <div class="details-row"><span class="details-label">Status:</span> <span class="details-value">${data.status}</span></div>
        `;
        document.getElementById('detailsModal').style.display = 'block';
    }
    function closeModal() { document.getElementById('detailsModal').style.display = 'none'; }
    window.onclick = function(e) { if(e.target == document.getElementById('detailsModal')) closeModal(); }
    </script>
</body>
</html>