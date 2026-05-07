<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

$flash = ['type' => '', 'msg' => ''];

// =====================================================================
// POST handlers
// =====================================================================

// Add or update template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $id        = (int)($_POST['id']               ?? 0);
    $name      = trim($_POST['name']               ?? '');
    $dept_id   = (int)($_POST['department_id']     ?? 0);
    $year_id   = (int)($_POST['academic_year_id']  ?? 0);
    $sem_id    = (int)($_POST['semester_id']       ?? 0);
    $body      = trim($_POST['body']               ?? '');

    if ($name === '' || $body === '') {
        $flash = ['type' => 'error', 'msg' => 'Name and body are required.'];
    } else {
        $params = [
            $name,
            $dept_id > 0 ? $dept_id : null,
            $year_id > 0 ? $year_id : null,
            $sem_id  > 0 ? $sem_id  : null,
            $body,
        ];

        try {
            if ($id > 0) {
                $params[] = $id;
                $pdo->prepare("
                    UPDATE letter_templates
                    SET name = ?, department_id = ?, academic_year_id = ?, semester_id = ?, body = ?
                    WHERE id = ?
                ")->execute($params);
                header("Location: letter_templates.php?msg=updated");
                exit;
            } else {
                $pdo->prepare("
                    INSERT INTO letter_templates
                        (name, department_id, academic_year_id, semester_id, body)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute($params);
                header("Location: letter_templates.php?msg=created");
                exit;
            }
        } catch (PDOException $e) {
            $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Delete
if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM letter_templates WHERE id = ?")->execute([(int)$_GET['delete_id']]);
    header("Location: letter_templates.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    $map = [
        'created' => ['success', 'Template created.'],
        'updated' => ['success', 'Template updated.'],
        'deleted' => ['success', 'Template deleted.'],
    ];
    if (isset($map[$_GET['msg']])) $flash = ['type' => $map[$_GET['msg']][0], 'msg' => $map[$_GET['msg']][1]];
}

// =====================================================================
// Fetch
// =====================================================================
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$years       = $pdo->query("SELECT id, name FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$semesters   = $pdo->query("SELECT id, academic_year_id, label FROM semesters ORDER BY academic_year_id, sort_order")->fetchAll(PDO::FETCH_ASSOC);

// Index semesters by year so the dependent dropdown can filter client-side.
$sems_by_year = [];
foreach ($semesters as $s) {
    $sems_by_year[$s['academic_year_id']][] = $s;
}

$templates = $pdo->query("
    SELECT lt.*,
           d.name AS dept_name,
           y.name AS year_name,
           s.label AS sem_label
    FROM letter_templates lt
    LEFT JOIN departments    d ON d.id = lt.department_id
    LEFT JOIN academic_years y ON y.id = lt.academic_year_id
    LEFT JOIN semesters      s ON s.id = lt.semester_id
    ORDER BY (lt.department_id IS NOT NULL) DESC,
             (lt.academic_year_id IS NOT NULL) DESC,
             (lt.semester_id IS NOT NULL) DESC,
             lt.name
")->fetchAll(PDO::FETCH_ASSOC);

$edit_template = null;
if (!empty($_GET['edit_id'])) {
    foreach ($templates as $t) {
        if ((int)$t['id'] === (int)$_GET['edit_id']) { $edit_template = $t; break; }
    }
}

// Available placeholders — shown to admin as a help block.
$PLACEHOLDERS = [
    '{student_name}'    => "Student's full name",
    '{student_index}'   => 'Student index number',
    '{student_program}' => 'Programme of study',
    '{department}'      => "Student's department",
    '{company_name}'    => 'Host organisation',
    '{company_address}' => 'Host organisation address',
    '{start_date}'      => 'Attachment start date (formatted)',
    '{end_date}'        => 'Attachment end date (formatted)',
    '{weeks}'           => 'Total weeks of attachment',
    '{hod_name}'        => "HOD's full name",
    '{hod_title}'       => "HOD's job title",
    '{academic_year}'   => "Academic year (e.g. 2025/2026)",
    '{semester}'        => 'Semester label',
    '{date}'            => 'Date the letter is generated',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Letter Templates | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/letter_templates.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Letter Templates</h1>
            <p>Edit the body of the introduction letter. Set scope (department, academic year, semester) so different cohorts can get different wording.</p>
        </div>
        <div class="date-chip">
            <i class="fas fa-file-alt"></i>&nbsp; <?php echo count($templates); ?> templates
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- Editor -->
    <div class="card edit-card">
        <h3>
            <?php if ($edit_template): ?>
                <i class="fas fa-edit"></i>&nbsp; Edit "<?php echo htmlspecialchars($edit_template['name']); ?>"
            <?php else: ?>
                <i class="fas fa-plus-circle"></i>&nbsp; Create Template
            <?php endif; ?>
        </h3>

        <form method="POST" class="single-form">
            <?php if ($edit_template): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_template['id']; ?>">
            <?php endif; ?>

            <div class="grid-2">
                <div class="field">
                    <label>Template Name <span class="req">*</span></label>
                    <input type="text" name="name" required
                           value="<?php echo htmlspecialchars($edit_template['name'] ?? ''); ?>"
                           placeholder="e.g. ICT 2025/2026 Sem 2">
                </div>
                <div class="field">
                    <label>Department</label>
                    <select name="department_id">
                        <option value="">-- Any department (default) --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>"
                                    <?php echo ($edit_template['department_id'] ?? '') == $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Academic Year</label>
                    <select name="academic_year_id" id="year_select">
                        <option value="">-- Any year --</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo (int)$y['id']; ?>"
                                    <?php echo ($edit_template['academic_year_id'] ?? '') == $y['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($y['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Semester</label>
                    <select name="semester_id" id="sem_select">
                        <option value="">-- Any semester --</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Letter Body <span class="req">*</span></label>
                <textarea name="body" rows="10" required class="body-textarea" placeholder="The body text of the letter. Use the placeholders below to insert dynamic values."><?php echo htmlspecialchars($edit_template['body'] ?? ''); ?></textarea>
            </div>

            <div class="placeholders-help">
                <strong>Placeholders</strong> (click to insert at cursor):
                <div class="placeholder-grid">
                    <?php foreach ($PLACEHOLDERS as $p => $desc): ?>
                        <button type="button" class="placeholder-btn"
                                data-token="<?php echo htmlspecialchars($p); ?>"
                                title="<?php echo htmlspecialchars($desc); ?>">
                            <?php echo htmlspecialchars($p); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <?php if ($edit_template): ?>
                    <a href="letter_templates.php" class="btn btn-ghost">Cancel</a>
                <?php endif; ?>
                <button type="submit" name="save_template" class="btn btn-primary">
                    <i class="fas fa-save"></i>&nbsp;
                    <?php echo $edit_template ? 'Save Changes' : 'Create Template'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Templates list -->
    <div class="card" style="margin-top: 25px;">
        <h3><i class="fas fa-list"></i>&nbsp; All Templates</h3>
        <p class="muted small">When generating a letter, the most specific match wins (department + year + semester &gt; department + year &gt; department &gt; default).</p>

        <?php if (empty($templates)): ?>
            <p class="empty">No templates. Create the first one above.</p>
        <?php else: ?>
            <table class="prog-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Scope</th>
                        <th>Body Preview</th>
                        <th class="actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                            <td>
                                <?php if (!$t['dept_name'] && !$t['year_name'] && !$t['sem_label']): ?>
                                    <span class="role-badge role-archived">Default (any)</span>
                                <?php else: ?>
                                    <?php if ($t['dept_name']): ?>
                                        <span class="role-badge role-secretary"><?php echo htmlspecialchars($t['dept_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($t['year_name']): ?>
                                        <span class="role-badge role-student"><?php echo htmlspecialchars($t['year_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($t['sem_label']): ?>
                                        <span class="role-badge role-hod"><?php echo htmlspecialchars($t['sem_label']); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="muted small"><?php echo htmlspecialchars(mb_substr($t['body'], 0, 90)) . (mb_strlen($t['body']) > 90 ? '…' : ''); ?></td>
                            <td class="actions-col">
                                <a href="?edit_id=<?php echo (int)$t['id']; ?>" class="btn btn-ghost btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete_id=<?php echo (int)$t['id']; ?>"
                                   class="btn btn-ghost btn-sm btn-danger"
                                   onclick="return confirm('Delete this template?');" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
const SEMS_BY_YEAR = <?php echo json_encode($sems_by_year, JSON_UNESCAPED_UNICODE); ?>;
const PRESERVED_SEM = <?php echo json_encode($edit_template['semester_id'] ?? null); ?>;

const yearSel = document.getElementById('year_select');
const semSel  = document.getElementById('sem_select');

function refreshSemesters() {
    const yid = yearSel.value;
    semSel.innerHTML = '<option value="">-- Any semester --</option>';
    if (!yid || !SEMS_BY_YEAR[yid]) return;
    SEMS_BY_YEAR[yid].forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.label;
        if (PRESERVED_SEM && parseInt(s.id, 10) === parseInt(PRESERVED_SEM, 10)) opt.selected = true;
        semSel.appendChild(opt);
    });
}
yearSel.addEventListener('change', refreshSemesters);
refreshSemesters();

// Insert placeholder at cursor in the body textarea.
const bodyEl = document.querySelector('.body-textarea');
document.querySelectorAll('.placeholder-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const t = btn.dataset.token;
        const start = bodyEl.selectionStart;
        const end   = bodyEl.selectionEnd;
        bodyEl.value = bodyEl.value.slice(0, start) + t + bodyEl.value.slice(end);
        bodyEl.focus();
        bodyEl.selectionStart = bodyEl.selectionEnd = start + t.length;
    });
});
</script>
</body>
</html>
