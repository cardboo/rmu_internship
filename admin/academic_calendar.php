<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

$flash = ['type' => '', 'msg' => ''];

// =====================================================================
// POST handlers
// =====================================================================

// Add a new academic year (with two semesters in the same form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_year'])) {
    $name       = trim($_POST['name']       ?? '');
    $start      = trim($_POST['start_date'] ?? '');
    $end        = trim($_POST['end_date']   ?? '');
    $sem1_label = trim($_POST['sem1_label'] ?? 'Semester 1');
    $sem1_start = trim($_POST['sem1_start'] ?? '');
    $sem1_end   = trim($_POST['sem1_end']   ?? '');
    $sem2_label = trim($_POST['sem2_label'] ?? 'Semester 2');
    $sem2_start = trim($_POST['sem2_start'] ?? '');
    $sem2_end   = trim($_POST['sem2_end']   ?? '');

    $err = null;
    if ($name === '' || $start === '' || $end === '') {
        $err = 'Year name, start date and end date are required.';
    } elseif (!preg_match('#^\d{4}/\d{4}$#', $name)) {
        $err = 'Year name should look like "2025/2026".';
    } elseif ($end <= $start) {
        $err = 'Year end date must be after the start date.';
    } elseif ($sem1_start === '' || $sem1_end === '' || $sem2_start === '' || $sem2_end === '') {
        $err = 'Both semester start and end dates are required.';
    } elseif ($sem1_end <= $sem1_start || $sem2_end <= $sem2_start) {
        $err = 'Each semester must end after it starts.';
    } elseif ($sem2_start <= $sem1_end) {
        $err = 'Semester 2 must start after Semester 1 ends.';
    }

    if (!$err) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO academic_years (name, start_date, end_date) VALUES (?, ?, ?)")
                ->execute([$name, $start, $end]);
            $year_id = (int)$pdo->lastInsertId();

            $insSem = $pdo->prepare(
                "INSERT INTO semesters (academic_year_id, label, start_date, end_date, sort_order)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insSem->execute([$year_id, $sem1_label, $sem1_start, $sem1_end, 1]);
            $insSem->execute([$year_id, $sem2_label, $sem2_start, $sem2_end, 2]);

            $pdo->commit();
            $flash = ['type' => 'success', 'msg' => "Academic year $name added."];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $flash = ['type' => 'error', 'msg' => "Year $name already exists."];
            } else {
                $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
            }
        }
    } else {
        $flash = ['type' => 'error', 'msg' => $err];
    }
}

// Update an existing year + its semesters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_year'])) {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name']       ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end   = trim($_POST['end_date']   ?? '');

    if ($id === 0 || $name === '' || $start === '' || $end === '' || $end <= $start) {
        $flash = ['type' => 'error', 'msg' => 'Invalid input.'];
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE academic_years SET name = ?, start_date = ?, end_date = ? WHERE id = ?")
                ->execute([$name, $start, $end, $id]);

            // Update each posted semester row
            if (!empty($_POST['sem']) && is_array($_POST['sem'])) {
                $upd = $pdo->prepare("UPDATE semesters SET label = ?, start_date = ?, end_date = ? WHERE id = ? AND academic_year_id = ?");
                foreach ($_POST['sem'] as $sid => $row) {
                    $sid_i = (int)$sid;
                    $sl = trim($row['label']      ?? '');
                    $ss = trim($row['start_date'] ?? '');
                    $se = trim($row['end_date']   ?? '');
                    if ($sid_i > 0 && $sl !== '' && $ss !== '' && $se !== '' && $se > $ss) {
                        $upd->execute([$sl, $ss, $se, $sid_i, $id]);
                    }
                }
            }
            $pdo->commit();
            header("Location: academic_calendar.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $flash = ['type' => 'error', 'msg' => 'Update failed: ' . $e->getMessage()];
        }
    }
}

// Set a year as current (mutex)
if (isset($_GET['set_current_id'])) {
    $id = (int)$_GET['set_current_id'];
    try {
        $pdo->beginTransaction();
        $pdo->exec("UPDATE academic_years SET is_current = 0");
        $pdo->prepare("UPDATE academic_years SET is_current = 1 WHERE id = ?")->execute([$id]);
        $pdo->commit();
        header("Location: academic_calendar.php?msg=current_set");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $flash = ['type' => 'error', 'msg' => 'Failed: ' . $e->getMessage()];
    }
}

// Archive / un-archive
if (isset($_GET['archive_id'])) {
    $id = (int)$_GET['archive_id'];
    $pdo->prepare("UPDATE academic_years SET is_archived = 1 WHERE id = ? AND is_current = 0")
        ->execute([$id]);
    header("Location: academic_calendar.php?msg=archived");
    exit;
}
if (isset($_GET['unarchive_id'])) {
    $id = (int)$_GET['unarchive_id'];
    $pdo->prepare("UPDATE academic_years SET is_archived = 0 WHERE id = ?")->execute([$id]);
    header("Location: academic_calendar.php?msg=unarchived");
    exit;
}

if (isset($_GET['msg'])) {
    $map = [
        'updated'      => ['success', 'Academic year saved.'],
        'current_set'  => ['success', 'Current academic year switched.'],
        'archived'     => ['success', 'Year archived.'],
        'unarchived'   => ['success', 'Year restored from archive.'],
    ];
    $flash = $map[$_GET['msg']] ?? $flash;
    if ($flash !== ['type' => '', 'msg' => '']) {
        $flash = ['type' => $flash[0] ?? '', 'msg' => $flash[1] ?? ''];
    }
}

// =====================================================================
// Fetch
// =====================================================================
$years = $pdo->query("
    SELECT y.*,
           (SELECT COUNT(*) FROM semesters s WHERE s.academic_year_id = y.id) AS sem_count,
           (SELECT COUNT(*) FROM requests   WHERE academic_year_id = y.id) AS request_count,
           (SELECT COUNT(*) FROM logbooks   WHERE academic_year_id = y.id) AS logbook_count
    FROM academic_years y
    ORDER BY y.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Index semesters by year for quick render
$sems_by_year = [];
$semStmt = $pdo->query("SELECT * FROM semesters ORDER BY academic_year_id, sort_order, start_date");
foreach ($semStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $sems_by_year[$s['academic_year_id']][] = $s;
}

$edit_year = null;
if (isset($_GET['edit_year'])) {
    foreach ($years as $y) {
        if ((int)$y['id'] === (int)$_GET['edit_year']) { $edit_year = $y; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Calendar | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/calendar.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Academic Calendar</h1>
            <p>Manage current and past academic years and their semesters. Past years stay for the audit trail.</p>
        </div>
        <div class="date-chip">
            <i class="fas fa-calendar-alt"></i>&nbsp; <?= count($years) ?> year<?= count($years) === 1 ? '' : 's' ?> on file
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?= htmlspecialchars($flash['type']) ?>">
            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <?php if ($edit_year): ?>
        <!-- Edit form -->
        <div class="card edit-card">
            <h3><i class="fas fa-edit"></i>&nbsp; Edit Academic Year</h3>
            <form method="POST" class="single-form">
                <input type="hidden" name="id" value="<?= (int)$edit_year['id'] ?>">
                <div class="grid-2">
                    <div class="field">
                        <label>Year Name <span class="req">*</span></label>
                        <input type="text" name="name" required value="<?= htmlspecialchars($edit_year['name']) ?>">
                    </div>
                    <div class="field">
                        <label>Start Date <span class="req">*</span></label>
                        <input type="date" name="start_date" required value="<?= htmlspecialchars($edit_year['start_date']) ?>">
                    </div>
                    <div class="field">
                        <label>End Date <span class="req">*</span></label>
                        <input type="date" name="end_date" required value="<?= htmlspecialchars($edit_year['end_date']) ?>">
                    </div>
                </div>

                <h4 class="section-h">Semesters</h4>
                <?php foreach ($sems_by_year[$edit_year['id']] ?? [] as $s): ?>
                    <div class="grid-3 sem-row">
                        <div class="field">
                            <label>Label</label>
                            <input type="text" name="sem[<?= (int)$s['id'] ?>][label]" required
                                   value="<?= htmlspecialchars($s['label']) ?>">
                        </div>
                        <div class="field">
                            <label>Start</label>
                            <input type="date" name="sem[<?= (int)$s['id'] ?>][start_date]" required
                                   value="<?= htmlspecialchars($s['start_date']) ?>">
                        </div>
                        <div class="field">
                            <label>End</label>
                            <input type="date" name="sem[<?= (int)$s['id'] ?>][end_date]" required
                                   value="<?= htmlspecialchars($s['end_date']) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <a href="academic_calendar.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" name="update_year" class="btn btn-primary">
                        <i class="fas fa-save"></i>&nbsp; Save Changes
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- Add new year -->
        <div class="card">
            <h3><i class="fas fa-plus-circle"></i>&nbsp; Add Academic Year</h3>
            <p class="muted">Create the year and its two semesters in one go. You can edit later.</p>
            <form method="POST" class="single-form">
                <div class="grid-2">
                    <div class="field">
                        <label>Year Name <span class="req">*</span></label>
                        <input type="text" name="name" required placeholder="e.g. 2026/2027" pattern="\d{4}/\d{4}">
                    </div>
                    <div class="field"><!-- spacer --></div>
                    <div class="field">
                        <label>Year Start <span class="req">*</span></label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="field">
                        <label>Year End <span class="req">*</span></label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>

                <h4 class="section-h">Semester 1</h4>
                <div class="grid-3">
                    <div class="field">
                        <label>Label</label>
                        <input type="text" name="sem1_label" value="Semester 1">
                    </div>
                    <div class="field">
                        <label>Start <span class="req">*</span></label>
                        <input type="date" name="sem1_start" required>
                    </div>
                    <div class="field">
                        <label>End <span class="req">*</span></label>
                        <input type="date" name="sem1_end" required>
                    </div>
                </div>

                <h4 class="section-h">Semester 2</h4>
                <div class="grid-3">
                    <div class="field">
                        <label>Label</label>
                        <input type="text" name="sem2_label" value="Semester 2">
                    </div>
                    <div class="field">
                        <label>Start <span class="req">*</span></label>
                        <input type="date" name="sem2_start" required>
                    </div>
                    <div class="field">
                        <label>End <span class="req">*</span></label>
                        <input type="date" name="sem2_end" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="add_year" class="btn btn-primary">
                        <i class="fas fa-save"></i>&nbsp; Save Year
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Year list -->
    <div class="card" style="margin-top: 25px;">
        <h3><i class="fas fa-list"></i>&nbsp; All Academic Years</h3>
        <?php if (empty($years)): ?>
            <p class="empty">No academic years yet.</p>
        <?php else: ?>
            <?php foreach ($years as $y): ?>
                <div class="year-card <?= $y['is_current'] ? 'is-current' : '' ?> <?= $y['is_archived'] ? 'is-archived' : '' ?>">
                    <div class="year-card-head">
                        <div>
                            <div class="year-name">
                                <?= htmlspecialchars($y['name']) ?>
                                <?php if ($y['is_current']): ?>
                                    <span class="pill pill-current">CURRENT</span>
                                <?php elseif ($y['is_archived']): ?>
                                    <span class="pill pill-archived">ARCHIVED</span>
                                <?php endif; ?>
                            </div>
                            <div class="year-meta">
                                <?= htmlspecialchars(date('d M Y', strtotime($y['start_date']))) ?>
                                – <?= htmlspecialchars(date('d M Y', strtotime($y['end_date']))) ?>
                                &middot; <?= (int)$y['sem_count'] ?> semesters
                                &middot; <?= (int)$y['request_count'] ?> requests, <?= (int)$y['logbook_count'] ?> logbooks
                            </div>
                        </div>
                        <div class="year-actions">
                            <a href="?edit_year=<?= (int)$y['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>&nbsp; Edit
                            </a>
                            <?php if (!$y['is_current']): ?>
                                <a href="?set_current_id=<?= (int)$y['id'] ?>" class="btn btn-primary btn-sm"
                                   onclick="return confirm('Make <?= htmlspecialchars(addslashes($y['name'])) ?> the current academic year? Any other current year will be unset.');">
                                    <i class="fas fa-flag"></i>&nbsp; Set as current
                                </a>
                                <?php if ($y['is_archived']): ?>
                                    <a href="?unarchive_id=<?= (int)$y['id'] ?>" class="btn btn-ghost btn-sm">
                                        <i class="fas fa-undo"></i>&nbsp; Restore
                                    </a>
                                <?php else: ?>
                                    <a href="?archive_id=<?= (int)$y['id'] ?>" class="btn btn-ghost btn-sm btn-danger"
                                       onclick="return confirm('Archive this academic year? Past data stays queryable; admins can restore later.');">
                                        <i class="fas fa-archive"></i>&nbsp; Archive
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sem-list">
                        <?php foreach ($sems_by_year[$y['id']] ?? [] as $s): ?>
                            <div class="sem-chip">
                                <strong><?= htmlspecialchars($s['label']) ?></strong>
                                <span class="muted small">
                                    <?= htmlspecialchars(date('d M', strtotime($s['start_date']))) ?>
                                    – <?= htmlspecialchars(date('d M Y', strtotime($s['end_date']))) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
