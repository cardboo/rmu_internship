<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

$idx = strtoupper(trim($_GET['index'] ?? ''));
if ($idx === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idx = strtoupper(trim($_POST['_orig_index'] ?? ''));
}
if ($idx === '') {
    header("Location: registry.php");
    exit;
}

// Load the current row (LEFT JOINs so we still render rows with
// broken FKs — admin needs to fix those too).
$stmt = $pdo->prepare("
    SELECT r.*, d.name AS dept_name, p.name AS program_name
    FROM student_registry r
    LEFT JOIN departments d ON d.id = r.department_id
    LEFT JOIN programs    p ON p.id = r.program_id
    WHERE r.index_number = ?
");
$stmt->execute([$idx]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) die("Registry entry $idx not found.");

$flash = ['type' => '', 'msg' => ''];

// ---------------------------------------------------------------------
// Helpers (mirror admin/registry.php — duplicated to keep this page
// self-contained; if this drifts, refactor both into includes/registry.php)
// ---------------------------------------------------------------------
function valid_level_e(?string $lvl): bool {
    return in_array($lvl, ['100','200','300','400','',null], true);
}
function valid_gender_e(?string $g): bool {
    return in_array($g, ['Male','Female','',null], true);
}
function valid_iso_date_e(?string $d): bool {
    if ($d === null || $d === '') return true;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// ---------------------------------------------------------------------
// POST: save edits
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_registry'])) {
    $new_idx = strtoupper(trim($_POST['index_number'] ?? ''));
    $name    = trim($_POST['full_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $dept_id = (int)($_POST['department_id'] ?? 0);
    $prog_id = (int)($_POST['program_id'] ?? 0);
    $level   = trim($_POST['level'] ?? '');
    $gender  = trim($_POST['gender'] ?? '');
    $dob     = trim($_POST['date_of_birth'] ?? '');
    $yr      = trim($_POST['year_admitted'] ?? '');

    $err = null;
    if ($new_idx === '' || $name === '' || $dept_id === 0 || $prog_id === 0) {
        $err = "Index number, full name, department and program are required.";
    } elseif ($email !== '' && ($emailErr = rmu_email_error($email, 'student')) !== null) {
        $err = $emailErr;
    } elseif (!valid_level_e($level))     $err = "Level must be 100, 200, 300 or 400.";
      elseif (!valid_gender_e($gender))   $err = "Gender must be Male or Female.";
      elseif (!valid_iso_date_e($dob))    $err = "Date of birth must be in YYYY-MM-DD format.";

    if (!$err) {
        $chk = $pdo->prepare("SELECT 1 FROM programs WHERE id = ? AND department_id = ?");
        $chk->execute([$prog_id, $dept_id]);
        if (!$chk->fetchColumn()) $err = "Selected program does not belong to the selected department.";
    }

    // If the PK (index_number) changed, make sure the new value isn't
    // already used by a different registry row.
    if (!$err && $new_idx !== $idx) {
        $clash = $pdo->prepare("SELECT 1 FROM student_registry WHERE index_number = ? AND index_number <> ?");
        $clash->execute([$new_idx, $idx]);
        if ($clash->fetchColumn()) $err = "Index number $new_idx already exists.";
    }

    if ($err) {
        $flash = ['type' => 'error', 'msg' => $err];
        // Re-populate $target so the form shows what the user just typed
        $target = array_merge($target, [
            'index_number' => $new_idx, 'full_name' => $name, 'email' => $email,
            'department_id' => $dept_id, 'program_id' => $prog_id,
            'level' => $level, 'gender' => $gender,
            'date_of_birth' => $dob, 'year_admitted' => $yr,
        ]);
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE student_registry
                SET index_number   = ?, full_name = ?, email = ?,
                    department_id  = ?, program_id = ?,
                    level          = ?, gender = ?,
                    date_of_birth  = ?, year_admitted = ?
                WHERE index_number = ?
            ")->execute([
                $new_idx, $name,
                $email !== '' ? $email : null,
                $dept_id, $prog_id,
                $level  !== '' ? $level  : null,
                $gender !== '' ? $gender : null,
                $dob    !== '' ? $dob    : null,
                $yr     !== '' ? $yr     : null,
                $idx,
            ]);

            // If a user has already claimed this registry entry, cascade
            // the editable fields (name, email, dept, program, level,
            // gender, index_number) onto their users row too — otherwise
            // the registry would drift back into 'mismatch' state.
            if ((int)$target['is_claimed'] === 1 && !empty($target['claimed_user_id'])) {
                // Resolve dept + program names (users.department / .program
                // are denormalised text columns, not FKs).
                $dept_name = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                $dept_name->execute([$dept_id]);
                $dept_name = $dept_name->fetchColumn() ?: null;

                $prog_name = $pdo->prepare("SELECT name FROM programs WHERE id = ?");
                $prog_name->execute([$prog_id]);
                $prog_name = $prog_name->fetchColumn() ?: null;

                $pdo->prepare("
                    UPDATE users
                    SET full_name    = ?, email = ?, department = ?, program = ?,
                        level        = ?, gender = ?, index_number = ?
                    WHERE id = ?
                ")->execute([
                    $name,
                    $email !== '' ? $email : null,
                    $dept_name, $prog_name,
                    $level  !== '' ? $level  : null,
                    $gender !== '' ? $gender : null,
                    $new_idx,
                    (int)$target['claimed_user_id'],
                ]);
            }

            $pdo->commit();

            audit_log($pdo, 'registry.updated', 'registry', $new_idx, [
                'previous_index'  => $idx,
                'name'            => $name,
                'cascade_to_user' => (int)$target['is_claimed'] === 1
                    ? (int)$target['claimed_user_id'] : null,
            ]);

            $_SESSION['flash_success'] = "Registry entry $new_idx updated."
                . ((int)$target['is_claimed'] === 1
                    ? " Linked user account was kept in sync."
                    : "");
            header("Location: registry.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $err = "Duplicate index — $new_idx is already used by another row.";
            } else {
                $err = "Database error: " . $e->getMessage();
            }
            $flash = ['type' => 'error', 'msg' => $err];
        }
    }
}

// ---------------------------------------------------------------------
// View data
// ---------------------------------------------------------------------
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$programs    = $pdo->query("SELECT id, department_id, name FROM programs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$programs_by_dept = [];
foreach ($programs as $p) {
    $programs_by_dept[(int)$p['department_id']][] = ['id' => (int)$p['id'], 'name' => $p['name']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Registry Entry | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/users.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Edit Registry Entry</h1>
            <p>
                Fix data-collection errors for <strong><?php echo htmlspecialchars($target['full_name']); ?></strong>
                (<code><?php echo htmlspecialchars($target['index_number']); ?></code>).
                <?php if ((int)$target['is_claimed'] === 1): ?>
                    <span class="status-badge approved" style="margin-left:6px;">Account claimed</span>
                    Changes here will also update the linked user account.
                <?php else: ?>
                    <span class="status-badge pending" style="margin-left:6px;">Unclaimed</span>
                <?php endif; ?>
            </p>
        </div>
        <a href="registry.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i>&nbsp; Back</a>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" class="single-form" autocomplete="off">
            <input type="hidden" name="_orig_index" value="<?php echo htmlspecialchars($idx); ?>">

            <div class="grid-2">
                <div class="field">
                    <label>Index Number <span class="req">*</span></label>
                    <input type="text" name="index_number" required
                           value="<?php echo htmlspecialchars($target['index_number']); ?>">
                    <small class="muted small">Renaming the index will cascade to the linked user account if claimed.</small>
                </div>
                <div class="field">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" required
                           value="<?php echo htmlspecialchars($target['full_name']); ?>">
                </div>

                <div class="field">
                    <label>RMU Student Email</label>
                    <input type="email" name="email"
                           value="<?php echo htmlspecialchars($target['email'] ?? ''); ?>"
                           placeholder="e.g. k.mensah@<?php echo RMU_STUDENT_DOMAIN; ?>">
                    <small class="muted small">Optional. Must end in <code>@<?php echo RMU_STUDENT_DOMAIN; ?></code>.</small>
                </div>
                <div class="field"><!-- spacer --></div>

                <div class="field">
                    <label>Department <span class="req">*</span></label>
                    <select name="department_id" id="dept_select" required>
                        <option value="">-- Select department --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>"
                                    <?php echo ((int)$target['department_id'] === (int)$d['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Program <span class="req">*</span></label>
                    <select name="program_id" id="prog_select" required>
                        <option value="">-- Select department first --</option>
                    </select>
                </div>

                <div class="field">
                    <label>Level</label>
                    <select name="level">
                        <?php foreach (['' => '--','100' => '100','200' => '200','300' => '300','400' => '400'] as $v => $lbl): ?>
                            <option value="<?php echo $v; ?>" <?php echo (string)$target['level'] === $v ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">--</option>
                        <option value="Male"   <?php echo ($target['gender'] ?? '') === 'Male'   ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($target['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>

                <div class="field">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth"
                           value="<?php echo htmlspecialchars($target['date_of_birth'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>Year Admitted</label>
                    <input type="text" name="year_admitted"
                           value="<?php echo htmlspecialchars($target['year_admitted'] ?? ''); ?>"
                           placeholder="e.g. 2024">
                </div>
            </div>

            <div class="form-actions">
                <a href="registry.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" name="update_registry" class="btn btn-primary">
                    <i class="fas fa-save"></i>&nbsp; Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const PROGRAMS_BY_DEPT = <?php echo json_encode($programs_by_dept, JSON_UNESCAPED_UNICODE); ?>;
const CURRENT_PROG_ID  = <?php echo (int)($target['program_id'] ?? 0); ?>;

const deptSel = document.getElementById('dept_select');
const progSel = document.getElementById('prog_select');

function refreshPrograms() {
    const deptId = parseInt(deptSel.value, 10);
    progSel.innerHTML = '';
    if (!deptId || !PROGRAMS_BY_DEPT[deptId]) {
        progSel.innerHTML = '<option value="">-- Select department first --</option>';
        progSel.disabled = true;
        return;
    }
    progSel.disabled = false;
    progSel.innerHTML = '<option value="">-- Select program --</option>';
    PROGRAMS_BY_DEPT[deptId].forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        if (p.id === CURRENT_PROG_ID) opt.selected = true;
        progSel.appendChild(opt);
    });
}

deptSel.addEventListener('change', refreshPrograms);
refreshPrograms();
</script>
</body>
</html>
