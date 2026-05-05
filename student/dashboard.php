<?php
require __DIR__ . '/../includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$message = "";

// Fetch Semester Dates
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('semester_start', 'semester_end')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$sem_start = $settings['semester_start'] ?? date('Y-m-d');
$sem_end = $settings['semester_end'] ?? date('Y-m-d');

// Handle New Request Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    $is_open = isset($_POST['is_open']) ? 1 : 0;
    $company = $is_open ? "TO WHOM IT MAY CONCERN" : $_POST['company_name'];
    $address = $is_open ? "GENERAL SEARCH" : $_POST['company_address'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];

    // We removed the PHP hard block here. The system accepts the request regardless of dates.
    $stmt = $pdo->prepare("INSERT INTO requests (student_id, company_name, company_address, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    
    if ($stmt->execute([$student_id, $company, $address, $start, $end])) {
        // Check if it overlapped just to customize the success message
        $overlap = ($start <= $sem_end && $end >= $sem_start);
        if ($overlap) {
            $message = "<div class='success-banner' style='background: #fef3c7; color: #92400e;'><i class='fas fa-exclamation-circle'></i> Request submitted, but it has been flagged for HOD review because your dates overlap with the active semester.</div>";
        } else {
            $message = "<div class='success-banner'><i class='fas fa-check-circle'></i> Request submitted successfully!</div>";
        }
    }
}

// Fetch Student Requests
$stmt = $pdo->prepare("SELECT * FROM requests WHERE student_id = ? ORDER BY request_date DESC");
$stmt->execute([$student_id]);
$my_requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard | RMU Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <style>
        .form-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .toggle-container { display: flex; align-items: center; margin-bottom: 20px; background: #f1f5f9; padding: 10px; border-radius: 8px; }
        .hidden-fields { display: block; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .approved { background: #dcfce7; color: #166534; }
        .pending { background: #fef3c7; color: #92400e; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .success-banner { background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .info-note { background: #e0f2fe; color: #0369a1; padding: 10px; border-radius: 5px; font-size: 0.85rem; margin-bottom: 15px; border-left: 4px solid #0ea5e9; }
        #dateWarning { display: none; background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 5px; font-size: 0.85rem; margin-bottom: 15px; border-left: 4px solid #ef4444; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>Industrial Attachment Request</h1>

        <?php echo $message; ?>

        <div class="form-card">
            <div class="info-note">
                <i class="fas fa-info-circle"></i> Active Academic Semester: <strong><?php echo date('M d, Y', strtotime($sem_start)); ?> to <?php echo date('M d, Y', strtotime($sem_end)); ?></strong>. Standard attachments should fall outside these dates.
            </div>

            <div id="dateWarning">
                <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> You have selected dates during the active semester. Your request will be flagged for special review by your HOD.
            </div>

            <form action="" method="POST" id="requestForm" onsubmit="return validateForm()">
                <div class="toggle-container">
                    <input type="checkbox" id="is_open" name="is_open" onchange="toggleCompanyFields(this)" style="margin-right: 10px; width: 20px; height: 20px;">
                    <label for="is_open" style="font-weight: 600; color: #1e293b;">Request an "Open Letter" (To Whom It May Concern)</label>
                </div>

                <div id="company_info" class="hidden-fields">
                    <div style="margin-bottom: 15px;">
                        <label>Company Name</label>
                        <input type="text" name="company_name" id="c_name" class="input-field" placeholder="e.g. Google Ghana" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Company Address</label>
                        <textarea name="company_address" id="c_addr" class="input-field" rows="2" required></textarea>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label>Proposed Start Date</label>
                        <input type="date" name="start_date" id="start_date" required class="input-field" onchange="checkDateOverlap()">
                    </div>
                    <div>
                        <label>Proposed End Date</label>
                        <input type="date" name="end_date" id="end_date" required class="input-field" onchange="checkDateOverlap()">
                    </div>
                </div>

                <button type="submit" name="submit_request" class="btn-login" style="width: 200px; background: #0D8ABC; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer;">
                    Submit Request
                </button>
            </form>
        </div>

        <div class="table-container">
            <h2>My Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date Requested</th>
                        <th>Target Company</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($my_requests as $r): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($r['request_date'])); ?></td>
                        <td><?php echo htmlspecialchars($r['company_name']); ?></td>
                        <td><span class="status-badge <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                        <td>
                            <?php if($r['status'] == 'approved'): ?>
                                <a href="<?php echo BASE_URL; ?>api/generate_letter.php?id=<?php echo $r['id']; ?>" target="_blank" style="color: #0D8ABC;"><i class="fas fa-file-pdf"></i> Download</a>
                            <?php else: ?>
                                <span style="color: #94a3b8;">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    const semStart = new Date("<?php echo $sem_start; ?>");
    const semEnd = new Date("<?php echo $sem_end; ?>");

    function toggleCompanyFields(checkbox) {
        const fields = document.getElementById('company_info');
        const nameInput = document.getElementById('c_name');
        const addrInput = document.getElementById('c_addr');
        
        if(checkbox.checked) {
            fields.style.display = 'none';
            nameInput.required = false;
            addrInput.required = false;
        } else {
            fields.style.display = 'block';
            nameInput.required = true;
            addrInput.required = true;
        }
    }

    function checkDateOverlap() {
        const startVal = document.getElementById('start_date').value;
        const endVal = document.getElementById('end_date').value;
        const warningBox = document.getElementById('dateWarning');

        if (!startVal || !endVal) return;

        const reqStart = new Date(startVal);
        const reqEnd = new Date(endVal);

        if (reqStart <= semEnd && reqEnd >= semStart) {
            warningBox.style.display = 'block';
        } else {
            warningBox.style.display = 'none';
        }
    }

    function validateForm() {
        const startInput = document.getElementById('start_date').value;
        const endInput = document.getElementById('end_date').value;
        
        if (new Date(endInput) < new Date(startInput)) {
            alert("End date cannot be before the start date.");
            return false; // Still hard block impossible time travel
        }
        return true; 
    }
    </script>
</body>
</html>