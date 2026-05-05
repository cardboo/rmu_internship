<?php
require __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$flash = ['type' => '', 'msg' => ''];

// =====================================================================
// POST handlers
// =====================================================================

// ----- Add department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dept'])) {
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($name === '') {
        $flash = ['type' => 'error', 'msg' => 'Department name is required.'];
    } else {
        try {
            $pdo->prepare("INSERT INTO departments (name, code) VALUES (?, ?)")
                ->execute([$name, $code !== '' ? $code : null]);
            $flash = ['type' => 'success', 'msg' => "Department '$name' added."];
        } catch (PDOException $e) {
            $flash = ['type' => 'error', 'msg' =>
                $e->getCode() === '23000'
                    ? "Department '$name' already exists."
                    : 'Database error: ' . $e->getMessage()];
        }
    }
}

// ----- Update department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dept'])) {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($id === 0 || $name === '') {
        $flash = ['type' => 'error', 'msg' => 'Invalid input.'];
    } else {
        try {
            $pdo->prepare("UPDATE departments SET name = ?, code = ? WHERE id = ?")
                ->execute([$name, $code !== '' ? $code : null, $id]);
            $flash = ['type' => 'success', 'msg' => 'Department updated.'];
            // Strip ?edit param from URL via redirect after success
            header("Location: programs.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            $flash = ['type' => 'error', 'msg' => 'Update failed: ' . $e->getMessage()];
        }
    }
}

// ----- Delete department
if (isset($_GET['delete_dept'])) {
    $id = (int)$_GET['delete_dept'];
    $hasPrograms = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE department_id = ?");
    $hasPrograms->execute([$id]);
    $hasRegistry = $pdo->prepare("SELECT COUNT(*) FROM student_registry WHERE department_id = ?");
    $hasRegistry->execute([$id]);

    if ($hasPrograms->fetchColumn() > 0) {
        $flash = ['type' => 'error', 'msg' => 'Cannot delete: department still has programs. Move or delete them first.'];
    } elseif ($hasRegistry->fetchColumn() > 0) {
        $flash = ['type' => 'error', 'msg' => 'Cannot delete: registry entries reference this department.'];
    } else {
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
        header("Location: programs.php?msg=dept_deleted");
        exit;
    }
}

// ----- Add program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prog'])) {
    $dept_id = (int)($_POST['department_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $code    = strtoupper(trim($_POST['code'] ?? ''));
    if ($dept_id === 0 || $name === '') {
        $flash = ['type' => 'error', 'msg' => 'Department and program name are required.'];
    } else {
        try {
            $pdo->prepare("INSERT INTO programs (department_id, name, code) VALUES (?, ?, ?)")
                ->execute([$dept_id, $name, $code !== '' ? $code : null]);
            $flash = ['type' => 'success', 'msg' => "Program '$name' added."];
        } catch (PDOException $e) {
            $flash = ['type' => 'error', 'msg' =>
                $e->getCode() === '23000'
                    ? "Program already exists in that department."
                    : 'Database error: ' . $e->getMessage()];
        }
    }
}

// ----- Update program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_prog'])) {
    $id      = (int)($_POST['id'] ?? 0);
    $dept_id = (int)($_POST['department_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $code    = strtoupper(trim($_POST['code'] ?? ''));
    if ($id === 0 || $dept_id === 0 || $name === '') {
        $flash = ['type' => 'error', 'msg' => 'Invalid input.'];
    } else {
        try {
            $pdo->prepare("UPDATE programs SET department_id = ?, name = ?, code = ? WHERE id = ?")
                ->execute([$dept_id, $name, $code !== '' ? $code : null, $id]);
            header("Location: programs.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            $flash = ['type' => 'error', 'msg' => 'Update failed: ' . $e->getMessage()];
        }
    }
}

// ----- Delete program
if (isset($_GET['delete_prog'])) {
    $id = (int)$_GET['delete_prog'];
    $hasRegistry = $pdo->prepare("SELECT COUNT(*) FROM student_registry WHERE program_id = ?");
    $hasRegistry->execute([$id]);
    if ($hasRegistry->fetchColumn() > 0) {
        $flash = ['type' => 'error', 'msg' => 'Cannot delete: registry entries reference this program.'];
    } else {
        $pdo->prepare("DELETE FROM programs WHERE id = ?")->execute([$id]);
        header("Location: programs.php?msg=prog_deleted");
        exit;
    }
}

// Flash from redirect
if (isset($_GET['msg'])) {
    $map = [
        'updated'      => 'Saved successfully.',
        'dept_deleted' => 'Department deleted.',
        'prog_deleted' => 'Program deleted.',
    ];
    if (isset($map[$_GET['msg']])) {
        $flash = ['type' => 'success', 'msg' => $map[$_GET['msg']]];
    }
}

// =====================================================================
// Fetch
// =====================================================================
$departments = $pdo->query("
    SELECT d.*,
        (SELECT COUNT(*) FROM programs           WHERE department_id = d.id) AS program_count,
        (SELECT COUNT(*) FROM student_registry   WHERE department_id = d.id) AS student_count
    FROM departments d
    ORDER BY d.name
")->fetchAll(PDO::FETCH_ASSOC);

$programs = $pdo->query("
    SELECT p.*, d.name AS dept_name,
        (SELECT COUNT(*) FROM student_registry WHERE program_id = p.id) AS student_count
    FROM programs p
    JOIN departments d ON d.id = p.department_id
    ORDER BY d.name, p.name
")->fetchAll(PDO::FETCH_ASSOC);

$programs_by_dept = [];
foreach ($programs as $p) {
    $programs_by_dept[$p['department_id']][] = $p;
}

$edit_dept = null;
if (isset($_GET['edit_dept'])) {
    foreach ($departments as $d) {
        if ((int)$d['id'] === (int)$_GET['edit_dept']) { $edit_dept = $d; break; }
    }
}

$edit_prog = null;
if (isset($_GET['edit_prog'])) {
    foreach ($programs as $p) {
        if ((int)$p['id'] === (int)$_GET['edit_prog']) { $edit_prog = $p; break; }
    }
}

$initial_tab = $edit_dept ? 'depts' : 'programs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Programs &amp; Departments | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/programs.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Programs &amp; Departments</h1>
            <p>Manage the academic taxonomy used across the system.</p>
        </div>
        <div class="date-chip">
            <i class="fas fa-graduation-cap"></i>&nbsp;
            <?= count($departments) ?> dept · <?= count($programs) ?> programs
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?= htmlspecialchars($flash['type']) ?>">
            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="reg-tabs">
        <button class="tab-btn <?= $initial_tab === 'programs' ? 'active' : '' ?>" data-tab="programs">
            <i class="fas fa-book"></i>&nbsp; Programs
        </button>
        <button class="tab-btn <?= $initial_tab === 'depts' ? 'active' : '' ?>" data-tab="depts">
            <i class="fas fa-building"></i>&nbsp; Departments
        </button>
    </div>

    <!-- ================== PROGRAMS ================== -->
    <section id="tab-programs" class="tab-panel <?= $initial_tab === 'programs' ? 'active' : '' ?>">

        <?php if ($edit_prog): ?>
            <div class="card edit-card">
                <h3><i class="fas fa-edit"></i> Edit Program</h3>
                <form method="POST" class="single-form">
                    <input type="hidden" name="id" value="<?= (int)$edit_prog['id'] ?>">
                    <div class="grid-2">
                        <div class="field">
                            <label>Department <span class="req">*</span></label>
                            <select name="department_id" required>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $edit_prog['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['name']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Program Name <span class="req">*</span></label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($edit_prog['name']) ?>">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label>Code</label>
                            <input type="text" name="code" maxlength="10" value="<?= htmlspecialchars($edit_prog['code'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-actions">
                        <a href="programs.php" class="btn btn-ghost">Cancel</a>
                        <button type="submit" name="update_prog" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <h3><i class="fas fa-plus-circle"></i> Add Program</h3>
                <form method="POST" class="single-form">
                    <div class="grid-2">
                        <div class="field">
                            <label>Department <span class="req">*</span></label>
                            <select name="department_id" required>
                                <option value="">-- Select department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Program Name <span class="req">*</span></label>
                            <input type="text" name="name" required placeholder="e.g. BSc. Computer Science">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="field">
                            <label>Code</label>
                            <input type="text" name="code" placeholder="e.g. BCS" maxlength="10">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_prog" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Program
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top: 25px;">
            <h3><i class="fas fa-list"></i> All Programs</h3>

            <?php if (empty($departments)): ?>
                <p class="empty">No departments yet. Add a department first.</p>
            <?php else: foreach ($departments as $d): ?>
                <div class="dept-group">
                    <h4>
                        <?= htmlspecialchars($d['name']) ?>
                        <span class="count-pill"><?= count($programs_by_dept[$d['id']] ?? []) ?></span>
                    </h4>

                    <?php if (empty($programs_by_dept[$d['id']])): ?>
                        <p class="muted small">No programs in this department.</p>
                    <?php else: ?>
                        <table class="prog-table">
                            <thead>
                                <tr>
                                    <th>Program Name</th>
                                    <th>Code</th>
                                    <th>Students</th>
                                    <th class="actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programs_by_dept[$d['id']] as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><code><?= htmlspecialchars($p['code'] ?? '—') ?></code></td>
                                        <td><?= $p['student_count'] ?></td>
                                        <td class="actions-col">
                                            <a href="?edit_prog=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete_prog=<?= $p['id'] ?>"
                                               class="btn btn-ghost btn-sm btn-danger"
                                               onclick="return confirm('Delete program <?= htmlspecialchars(addslashes($p['name'])) ?>?');"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    <?php endif ?>
                </div>
            <?php endforeach; endif ?>
        </div>
    </section>

    <!-- ================== DEPARTMENTS ================== -->
    <section id="tab-depts" class="tab-panel <?= $initial_tab === 'depts' ? 'active' : '' ?>">

        <?php if ($edit_dept): ?>
            <div class="card edit-card">
                <h3><i class="fas fa-edit"></i> Edit Department</h3>
                <form method="POST" class="single-form">
                    <input type="hidden" name="id" value="<?= (int)$edit_dept['id'] ?>">
                    <div class="grid-2">
                        <div class="field">
                            <label>Department Name <span class="req">*</span></label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($edit_dept['name']) ?>">
                        </div>
                        <div class="field">
                            <label>Code</label>
                            <input type="text" name="code" maxlength="10" value="<?= htmlspecialchars($edit_dept['code'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-actions">
                        <a href="programs.php" class="btn btn-ghost">Cancel</a>
                        <button type="submit" name="update_dept" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <h3><i class="fas fa-plus-circle"></i> Add Department</h3>
                <form method="POST" class="single-form">
                    <div class="grid-2">
                        <div class="field">
                            <label>Department Name <span class="req">*</span></label>
                            <input type="text" name="name" required placeholder="e.g. Marine Engineering">
                        </div>
                        <div class="field">
                            <label>Code</label>
                            <input type="text" name="code" placeholder="e.g. MAR" maxlength="10">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_dept" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Department
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top: 25px;">
            <h3><i class="fas fa-list"></i> All Departments</h3>

            <?php if (empty($departments)): ?>
                <p class="empty">No departments yet.</p>
            <?php else: ?>
                <table class="prog-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Programs</th>
                            <th>Students</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($d['code'] ?? '—') ?></code></td>
                                <td><?= $d['program_count'] ?></td>
                                <td><?= $d['student_count'] ?></td>
                                <td class="actions-col">
                                    <a href="?edit_dept=<?= $d['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete_dept=<?= $d['id'] ?>"
                                       class="btn btn-ghost btn-sm btn-danger"
                                       onclick="return confirm('Delete department <?= htmlspecialchars(addslashes($d['name'])) ?>?');"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>
    </section>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});
</script>
</body>
</html>
