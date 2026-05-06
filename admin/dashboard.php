<?php
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . BASE_URL . "index.php");
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
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/dashboards.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

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
            <a href="dashboard.php" class="btn-reset">Clear</a>
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
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($req['full_name'] ?? ''); ?></div>
                            <small style="color: #64748b;"><?php echo htmlspecialchars($req['index_number'] ?? ''); ?></small>
                        </td>
                        <td><span class="dept-badge"><?php echo htmlspecialchars($req['department'] ?? ''); ?></span></td>
                        <td><?php echo htmlspecialchars($req['company_name'] ?? ''); ?></td>
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
            <div class="modal-header">
                <h3><i class="fas fa-file-signature"></i>&nbsp; Master Record</h3>
                <button class="modal-close" onclick="closeModal()" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button class="btn-action" style="background:#e2e8f0;color:#475569;" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
    function escapeHtml(s) {
        if (s === null || s === undefined) return '—';
        return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
    }
    function fmtDate(s) {
        if (!s) return '—';
        const d = new Date(s);
        return isNaN(d) ? s : d.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
    }
    function statusBadge(status) {
        const map = {
            pending:  ['#fef3c7','#92400e','Pending'],
            approved: ['#dcfce7','#166534','Approved'],
            rejected: ['#fee2e2','#991b1b','Rejected']
        };
        const [bg,fg,label] = map[status] || ['#e2e8f0','#475569',status];
        return `<span style="background:${bg};color:${fg};padding:4px 12px;border-radius:999px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;">${label}</span>`;
    }
    function viewDetails(data) {
        document.getElementById('modalBody').innerHTML = `
            <div class="det-status-row">
                <div>
                    <div class="student-name">${escapeHtml(data.full_name)}</div>
                    <div class="student-meta">${escapeHtml(data.department)} &middot; ${escapeHtml(data.index_number)}</div>
                </div>
                ${statusBadge(data.status)}
            </div>
            <div class="det-section">
                <h4>Host Organisation</h4>
                <div class="det-grid">
                    <span class="det-label">Company</span><span class="det-value">${escapeHtml(data.company_name)}</span>
                </div>
            </div>
            <div class="det-section">
                <h4>Submission</h4>
                <div class="det-grid">
                    <span class="det-label">Requested</span><span class="det-value">${fmtDate(data.request_date)}</span>
                </div>
            </div>
        `;
        document.getElementById('detailsModal').classList.add('open');
    }
    function closeModal() { document.getElementById('detailsModal').classList.remove('open'); }
    window.onclick = e => { if (e.target === document.getElementById('detailsModal')) closeModal(); };
    </script>
</body>
</html>