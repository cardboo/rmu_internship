<?php
require __DIR__ . '/../includes/db.php';

// ----------------------------------------------------------------------
// Access control: admin only
// ----------------------------------------------------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$flash    = ['type' => '', 'msg' => ''];
$row_errs = [];   // per-row CSV errors to show after upload
$summary  = null; // ['added' => N, 'skipped' => N, 'errors' => N]

// ----------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------
function find_dept_id(PDO $pdo, string $name): ?int {
    static $cache = [];
    $key = strtolower(trim($name));
    if (isset($cache[$key])) return $cache[$key];
    $s = $pdo->prepare("SELECT id FROM departments WHERE LOWER(name) = ? LIMIT 1");
    $s->execute([$key]);
    $id = $s->fetchColumn();
    return $cache[$key] = ($id !== false ? (int)$id : null);
}

function find_program_id(PDO $pdo, string $name, int $dept_id): ?int {
    $s = $pdo->prepare("SELECT id FROM programs WHERE LOWER(name) = ? AND department_id = ? LIMIT 1");
    $s->execute([strtolower(trim($name)), $dept_id]);
    $id = $s->fetchColumn();
    return $id !== false ? (int)$id : null;
}

function valid_level(?string $lvl): bool {
    return in_array($lvl, ['100', '200', '300', '400', ''], true) || $lvl === null;
}

function valid_gender(?string $g): bool {
    return in_array($g, ['Male', 'Female', '', null], true);
}

function valid_iso_date(?string $d): bool {
    if ($d === null || $d === '') return true;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// ----------------------------------------------------------------------
// POST: single student entry
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single'])) {
    $idx     = strtoupper(trim($_POST['index_number'] ?? ''));
    $name    = trim($_POST['full_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $dept_id = (int)($_POST['department_id'] ?? 0);
    $prog_id = (int)($_POST['program_id'] ?? 0);
    $level   = trim($_POST['level'] ?? '');
    $gender  = trim($_POST['gender'] ?? '');
    $dob     = trim($_POST['date_of_birth'] ?? '');
    $yr      = trim($_POST['year_admitted'] ?? '');

    $err = null;
    if ($idx === '' || $name === '' || $dept_id === 0 || $prog_id === 0) {
        $err = "Index number, full name, department and program are required.";
    } elseif ($email !== '' && ($emailErr = rmu_email_error($email, 'student')) !== null) {
        $err = $emailErr;
    } elseif (!valid_level($level)) {
        $err = "Level must be 100, 200, 300 or 400.";
    } elseif (!valid_gender($gender)) {
        $err = "Gender must be Male or Female.";
    } elseif (!valid_iso_date($dob)) {
        $err = "Date of birth must be in YYYY-MM-DD format.";
    } else {
        // ensure program belongs to the selected department
        $chk = $pdo->prepare("SELECT 1 FROM programs WHERE id = ? AND department_id = ?");
        $chk->execute([$prog_id, $dept_id]);
        if (!$chk->fetchColumn()) {
            $err = "Selected program does not belong to the selected department.";
        }
    }

    if ($err) {
        $flash = ['type' => 'error', 'msg' => $err];
    } else {
        try {
            $ins = $pdo->prepare(
                "INSERT INTO student_registry
                   (index_number, full_name, email, department_id, program_id, level, gender, date_of_birth, year_admitted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $idx, $name,
                $email !== '' ? $email : null,
                $dept_id, $prog_id,
                $level !== '' ? $level : null,
                $gender !== '' ? $gender : null,
                $dob !== ''    ? $dob    : null,
                $yr !== ''     ? $yr     : null,
            ]);
            $flash = ['type' => 'success', 'msg' => "Student $idx added to registry."];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $flash = ['type' => 'error', 'msg' => "Index number $idx already exists in the registry."];
            } else {
                $flash = ['type' => 'error', 'msg' => "Database error: " . $e->getMessage()];
            }
        }
    }
}

// ----------------------------------------------------------------------
// POST: CSV bulk upload
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    if (!isset($_FILES['registry_csv']) || $_FILES['registry_csv']['error'] !== UPLOAD_ERR_OK) {
        $flash = ['type' => 'error', 'msg' => "No file uploaded or upload error."];
    } else {
        $tmp = $_FILES['registry_csv']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['registry_csv']['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $flash = ['type' => 'error', 'msg' => "File must be a .csv"];
        } else {
            $handle = fopen($tmp, 'r');
            if (!$handle) {
                $flash = ['type' => 'error', 'msg' => "Could not read uploaded file."];
            } else {
                $expected = ['index_number','full_name','email','department','program','level','gender','date_of_birth','year_admitted'];
                $header   = fgetcsv($handle);
                if (!$header) {
                    fclose($handle);
                    $flash = ['type' => 'error', 'msg' => "CSV is empty."];
                } else {
                    $header = array_map(fn($h) => strtolower(trim($h)), $header);
                    $missing = array_diff($expected, $header);
                    if (!empty($missing)) {
                        fclose($handle);
                        $flash = [
                            'type' => 'error',
                            'msg'  => "CSV is missing columns: " . implode(', ', $missing)
                                    . ". Please use the provided template."
                        ];
                    } else {
                        $col = array_flip($header);
                        $ins = $pdo->prepare(
                            "INSERT INTO student_registry
                               (index_number, full_name, email, department_id, program_id, level, gender, date_of_birth, year_admitted)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        $exists = $pdo->prepare("SELECT 1 FROM student_registry WHERE index_number = ?");

                        $added = 0; $skipped = 0; $errors = 0;
                        $row_no = 1; // header is row 1

                        while (($r = fgetcsv($handle)) !== false) {
                            $row_no++;
                            // skip blank lines
                            if (count(array_filter($r, fn($v) => trim((string)$v) !== '')) === 0) continue;

                            $idx     = strtoupper(trim($r[$col['index_number']] ?? ''));
                            $name    = trim($r[$col['full_name']] ?? '');
                            $email   = trim($r[$col['email']] ?? '');
                            $dept    = trim($r[$col['department']] ?? '');
                            $prog    = trim($r[$col['program']] ?? '');
                            $level   = trim($r[$col['level']] ?? '');
                            $gender  = trim($r[$col['gender']] ?? '');
                            $dob     = trim($r[$col['date_of_birth']] ?? '');
                            $yr      = trim($r[$col['year_admitted']] ?? '');

                            if ($idx === '' || $name === '' || $dept === '' || $prog === '') {
                                $row_errs[] = "Row $row_no: missing required field (index/name/department/program).";
                                $errors++; continue;
                            }
                            if ($email !== '' && ($emailErr = rmu_email_error($email, 'student')) !== null) {
                                $row_errs[] = "Row $row_no ($idx): $emailErr";
                                $errors++; continue;
                            }
                            $dept_id = find_dept_id($pdo, $dept);
                            if ($dept_id === null) {
                                $row_errs[] = "Row $row_no ($idx): unknown department '$dept'.";
                                $errors++; continue;
                            }
                            $prog_id = find_program_id($pdo, $prog, $dept_id);
                            if ($prog_id === null) {
                                $row_errs[] = "Row $row_no ($idx): program '$prog' not found in department '$dept'.";
                                $errors++; continue;
                            }
                            if (!valid_level($level)) {
                                $row_errs[] = "Row $row_no ($idx): invalid level '$level'.";
                                $errors++; continue;
                            }
                            if (!valid_gender($gender)) {
                                $row_errs[] = "Row $row_no ($idx): gender must be Male or Female.";
                                $errors++; continue;
                            }
                            if (!valid_iso_date($dob)) {
                                $row_errs[] = "Row $row_no ($idx): invalid date_of_birth '$dob' (use YYYY-MM-DD).";
                                $errors++; continue;
                            }

                            $exists->execute([$idx]);
                            if ($exists->fetchColumn()) { $skipped++; continue; }

                            try {
                                $ins->execute([
                                    $idx, $name,
                                    $email  !== '' ? $email  : null,
                                    $dept_id, $prog_id,
                                    $level  !== '' ? $level  : null,
                                    $gender !== '' ? $gender : null,
                                    $dob    !== '' ? $dob    : null,
                                    $yr     !== '' ? $yr     : null,
                                ]);
                                $added++;
                            } catch (PDOException $e) {
                                $row_errs[] = "Row $row_no ($idx): " . $e->getMessage();
                                $errors++;
                            }
                        }
                        fclose($handle);
                        $summary = ['added' => $added, 'skipped' => $skipped, 'errors' => $errors];
                        $flash = [
                            'type' => $errors === 0 ? 'success' : 'warning',
                            'msg'  => "Import complete: $added added, $skipped skipped (duplicates), $errors errors."
                        ];
                    }
                }
            }
        }
    }
}

// ----------------------------------------------------------------------
// GET: search & list
// ----------------------------------------------------------------------
$q = trim($_GET['q'] ?? '');

// LEFT JOINs (not INNER) so rows with missing dept/programme FKs
// still appear — otherwise they vanish silently. The view labels
// such rows with an "FK broken" badge so admin can fix them.
$sql = "SELECT r.*, d.name AS dept_name, p.name AS program_name
        FROM student_registry r
        LEFT JOIN departments d ON d.id = r.department_id
        LEFT JOIN programs    p ON p.id = r.program_id";
$params = [];
if ($q !== '') {
    $sql .= " WHERE r.index_number LIKE ? OR r.full_name LIKE ? OR r.email LIKE ? OR d.name LIKE ? OR p.name LIKE ?";
    $like = "%$q%";
    $params = [$like, $like, $like, $like, $like];
}
$sql .= " ORDER BY r.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registry = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_count = (int)$pdo->query("SELECT COUNT(*) FROM student_registry")->fetchColumn();

// ----------------------------------------------------------------------
// Drift report: student users that don't appear in the registry
// (because of NULL index_number, mismatched dept, or mismatched program).
// Surfaces the data-quality issues admin needs to fix so migration 013
// can finish reconciling.
// ----------------------------------------------------------------------
$drift = [];
try {
    $drift = $pdo->query("
        SELECT u.id, u.full_name, u.email, u.index_number,
               u.department AS user_department,
               u.program    AS user_program,
               CASE
                   WHEN u.index_number IS NULL OR u.index_number = '' THEN 'no_index'
                   WHEN d.id IS NULL THEN 'unknown_dept'
                   WHEN p.id IS NULL THEN 'unknown_program'
                   ELSE 'other'
               END AS reason
        FROM       users u
        LEFT JOIN  departments      d ON d.name = u.department
        LEFT JOIN  programs         p ON p.name = u.program AND p.department_id = d.id
        LEFT JOIN  student_registry r ON r.index_number = u.index_number
        WHERE  u.role = 'student'
          AND  COALESCE(u.is_archived, 0) = 0
          AND  (
                 u.index_number IS NULL
              OR u.index_number = ''
              OR r.index_number IS NULL
               )
        ORDER BY reason, u.full_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $drift = []; // fail soft if migration 001 hasn't run
}

// data for the dependent dropdowns on the single-entry form
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$programs    = $pdo->query("SELECT id, department_id, name FROM programs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$programs_by_dept = [];
foreach ($programs as $p) {
    $programs_by_dept[$p['department_id']][] = ['id' => (int)$p['id'], 'name' => $p['name']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registry | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Student Registry</h1>
            <p>Authoritative roster of admitted students. Secretaries pull from this list when registering accounts.</p>
        </div>
        <div class="date-chip">
            <i class="fas fa-database"></i>&nbsp; <?php echo number_format($total_count); ?> records
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($drift)): ?>
        <details class="row-errors" open>
            <summary>
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo count($drift); ?> student account<?php echo count($drift) === 1 ? '' : 's'; ?>
                in Manage Users <strong>not appearing in this registry</strong>
                — usually a data-quality fix
            </summary>
            <p class="muted small" style="margin: 8px 0 12px;">
                After fixing the records below, run
                <code>migrations/013_reconcile_registry.sql</code> from phpMyAdmin to bring them in.
            </p>
            <table style="width:100%; font-size: 0.88rem;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding: 4px 6px;">Name</th>
                        <th style="text-align:left; padding: 4px 6px;">Index</th>
                        <th style="text-align:left; padding: 4px 6px;">Department</th>
                        <th style="text-align:left; padding: 4px 6px;">Programme</th>
                        <th style="text-align:left; padding: 4px 6px;">Why</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $reason_labels = [
                        'no_index'        => 'no index number',
                        'unknown_dept'    => "department doesn't match a row in Programs &amp; Depts",
                        'unknown_program' => "programme doesn't match the department",
                        'other'           => 'not yet reconciled — run migration 013',
                    ];
                    foreach ($drift as $u):
                    ?>
                        <tr>
                            <td style="padding: 4px 6px;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td style="padding: 4px 6px;"><code><?php echo htmlspecialchars($u['index_number'] ?? '—'); ?></code></td>
                            <td style="padding: 4px 6px;"><?php echo htmlspecialchars($u['user_department'] ?? '—'); ?></td>
                            <td style="padding: 4px 6px;"><?php echo htmlspecialchars($u['user_program'] ?? '—'); ?></td>
                            <td style="padding: 4px 6px;"><?php echo $reason_labels[$u['reason']] ?? htmlspecialchars($u['reason']); ?></td>
                            <td style="padding: 4px 6px;">
                                <a href="<?php echo BASE_URL; ?>admin/edit_user.php?id=<?php echo (int)$u['id']; ?>"
                                   class="btn btn-ghost btn-sm">
                                    <i class="fas fa-edit"></i>&nbsp; Fix
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    <?php endif; ?>

    <?php if (!empty($row_errs)): ?>
        <details class="row-errors" open>
            <summary><i class="fas fa-list"></i> Per-row issues (<?php echo count($row_errs); ?>)</summary>
            <ul>
                <?php foreach ($row_errs as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <div class="reg-tabs">
        <button class="tab-btn active" data-tab="bulk"><i class="fas fa-file-csv"></i>&nbsp; Bulk CSV Upload</button>
        <button class="tab-btn" data-tab="single"><i class="fas fa-user-plus"></i>&nbsp; Add Single Student</button>
    </div>

    <!-- ============== BULK CSV ============== -->
    <section id="tab-bulk" class="tab-panel active">
        <div class="card">
            <h3><i class="fas fa-cloud-upload-alt"></i>&nbsp; Upload Registry CSV</h3>
            <p class="muted">Upload a CSV file matching the template. Each row creates one registry entry.
               Duplicate index numbers are skipped automatically.</p>

            <form method="POST" enctype="multipart/form-data" class="csv-form">
                <label class="file-drop">
                    <i class="fas fa-file-csv"></i>
                    <span>Click to select a CSV file</span>
                    <input type="file" name="registry_csv" accept=".csv" required>
                </label>

                <div class="form-actions">
                    <a href="<?php echo BASE_URL; ?>api/download_registry_template.php" class="btn btn-ghost">
                        <i class="fas fa-download"></i>&nbsp; Download Template
                    </a>
                    <button type="submit" name="upload_csv" class="btn btn-primary">
                        <i class="fas fa-upload"></i>&nbsp; Start Import
                    </button>
                </div>
            </form>

            <div class="hint-box">
                <strong>Required CSV columns (in any order):</strong>
                <code>index_number, full_name, email, department, program, level, gender, date_of_birth, year_admitted</code>
                <ul>
                    <li><code>department</code> and <code>program</code> must already exist in the system.</li>
                    <li><code>email</code> must end in <code>@<?php echo RMU_STUDENT_DOMAIN; ?></code> (optional, but recommended — auto-fills during registration).</li>
                    <li><code>level</code> = 100 / 200 / 300 / 400 (optional).</li>
                    <li><code>gender</code> = Male / Female (optional).</li>
                    <li><code>date_of_birth</code> in <code>YYYY-MM-DD</code> format (optional).</li>
                </ul>
            </div>

            <?php if ($summary): ?>
                <div class="summary-grid">
                    <div class="sum-card sum-ok">
                        <h4><?php echo $summary['added']; ?></h4><p>Added</p>
                    </div>
                    <div class="sum-card sum-skip">
                        <h4><?php echo $summary['skipped']; ?></h4><p>Skipped (duplicates)</p>
                    </div>
                    <div class="sum-card sum-err">
                        <h4><?php echo $summary['errors']; ?></h4><p>Errors</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============== SINGLE ENTRY ============== -->
    <section id="tab-single" class="tab-panel">
        <div class="card">
            <h3><i class="fas fa-user-plus"></i>&nbsp; Add a Single Student</h3>
            <p class="muted">Use this for one-off additions. For bulk loads, use CSV upload above.</p>

            <form method="POST" class="single-form">
                <div class="grid-2">
                    <div class="field">
                        <label>Index Number <span class="req">*</span></label>
                        <input type="text" name="index_number" required placeholder="e.g. BIT0002001">
                    </div>
                    <div class="field">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" required placeholder="e.g. Kwame Mensah">
                    </div>

                    <div class="field">
                        <label>RMU Student Email</label>
                        <input type="email" name="email" placeholder="e.g. k.mensah@<?php echo RMU_STUDENT_DOMAIN; ?>">
                        <small class="muted small">Optional. If set, auto-fills during account registration.</small>
                    </div>
                    <div class="field"><!-- spacer --></div>

                    <div class="field">
                        <label>Department <span class="req">*</span></label>
                        <select name="department_id" id="dept_select" required>
                            <option value="">-- Select department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int)$d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Program <span class="req">*</span></label>
                        <select name="program_id" id="prog_select" required disabled>
                            <option value="">-- Select department first --</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Level</label>
                        <select name="level">
                            <option value="">--</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="300">300</option>
                            <option value="400">400</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">--</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth">
                    </div>
                    <div class="field">
                        <label>Year Admitted</label>
                        <input type="text" name="year_admitted" placeholder="e.g. 2024">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="add_single" class="btn btn-primary">
                        <i class="fas fa-save"></i>&nbsp; Save to Registry
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- ============== LIST + SEARCH ============== -->
    <section class="card" style="margin-top: 25px;">
        <div class="list-header">
            <h3><i class="fas fa-list"></i>&nbsp; Registry Records</h3>
            <form method="GET" class="search-form">
                <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
                       placeholder="Search by index, name, department or program">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($q !== ''): ?>
                    <a class="btn btn-ghost" href="registry.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Index No.</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Program</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registry)): ?>
                        <tr><td colspan="8" class="empty">No records found.</td></tr>
                    <?php else: foreach ($registry as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['index_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td class="muted small">
                                <?php if (!empty($row['email'])): ?>
                                    <?php echo htmlspecialchars($row['email']); ?>
                                <?php else: ?>
                                    <span style="color:#92400e;" title="No email on file in registry">— no email —</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['dept_name']): ?>
                                    <?php echo htmlspecialchars($row['dept_name']); ?>
                                <?php else: ?>
                                    <span style="background:#fee2e2;color:#991b1b;padding:2px 6px;border-radius:4px;font-size:0.7rem;font-weight:700;" title="department_id=<?php echo (int)$row['department_id']; ?> references a department that no longer exists">FK broken</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['program_name']): ?>
                                    <?php echo htmlspecialchars($row['program_name']); ?>
                                <?php else: ?>
                                    <span style="background:#fee2e2;color:#991b1b;padding:2px 6px;border-radius:4px;font-size:0.7rem;font-weight:700;" title="program_id=<?php echo (int)$row['program_id']; ?> references a programme that no longer exists">FK broken</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['level'] ?? '—'); ?></td>
                            <td>
                                <?php if ($row['is_claimed']): ?>
                                    <span class="status-badge approved">Account Created</span>
                                <?php else: ?>
                                    <span class="status-badge pending">Unclaimed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($row['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <?php if (count($registry) === 200): ?>
                <p class="muted small">Showing first 200 results. Use search to narrow down.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});

// Department -> Program dependent dropdown
const PROGRAMS_BY_DEPT = <?php echo json_encode($programs_by_dept, JSON_UNESCAPED_UNICODE); ?>;
const deptSel = document.getElementById('dept_select');
const progSel = document.getElementById('prog_select');

deptSel.addEventListener('change', () => {
    const dept = deptSel.value;
    progSel.innerHTML = '';
    if (!dept || !PROGRAMS_BY_DEPT[dept]) {
        progSel.disabled = true;
        progSel.innerHTML = '<option value="">-- Select department first --</option>';
        return;
    }
    progSel.disabled = false;
    progSel.innerHTML = '<option value="">-- Select program --</option>';
    PROGRAMS_BY_DEPT[dept].forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        progSel.appendChild(opt);
    });
});

// File picker label feedback
document.querySelector('.file-drop input[type=file]').addEventListener('change', (e) => {
    const span = e.target.parentElement.querySelector('span');
    span.textContent = e.target.files.length ? e.target.files[0].name : 'Click to select a CSV file';
});
</script>
</body>
</html>
