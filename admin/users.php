<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

// =====================================================================
// Actions
// =====================================================================

// Archive (replaces hard delete — supervisor #12)
if (isset($_GET['archive_id'])) {
    $id = (int)$_GET['archive_id'];
    if ($id !== (int)$_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET is_archived = 1, archived_at = NOW() WHERE id = ?")->execute([$id]);
        header("Location: users.php?msg=archived");
    } else {
        header("Location: users.php?msg=error_self");
    }
    exit;
}

// Restore from archive
if (isset($_GET['restore_id'])) {
    $id = (int)$_GET['restore_id'];
    $pdo->prepare("UPDATE users SET is_archived = 0, archived_at = NULL WHERE id = ?")->execute([$id]);
    header("Location: users.php?msg=restored&archived=1");
    exit;
}

// =====================================================================
// Filters
// =====================================================================
$q             = trim($_GET['q']    ?? '');
$role_filter   = trim($_GET['role'] ?? '');
$dept_filter   = trim($_GET['dept'] ?? '');
$show_archived = !empty($_GET['archived']);

$where  = ['u.id <> ?'];
$params = [$_SESSION['user_id']]; // never list yourself
$where[] = $show_archived ? 'COALESCE(u.is_archived,0) = 1' : 'COALESCE(u.is_archived,0) = 0';

if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.index_number LIKE ? OR u.department LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($role_filter !== '' && in_array($role_filter, ['admin','hod','secretary','student'], true)) {
    $where[] = 'u.role = ?';
    $params[] = $role_filter;
}
if ($dept_filter !== '') {
    $where[] = 'u.department = ?';
    $params[] = $dept_filter;
}

$sql = "SELECT u.* FROM users u WHERE " . implode(' AND ', $where) . " ORDER BY u.created_at DESC, u.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for header pills
$counts = $pdo->query("
    SELECT
        SUM(CASE WHEN COALESCE(is_archived,0) = 0 THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN COALESCE(is_archived,0) = 1 THEN 1 ELSE 0 END) AS archived
    FROM users
")->fetch(PDO::FETCH_ASSOC);

// Departments for dropdown
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department <> '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

$msg_map = [
    'archived'   => ['success', 'User archived. They can no longer log in until restored.'],
    'restored'   => ['success', 'User restored.'],
    'error_self' => ['error',   'You cannot archive your own account.'],
    'created'    => ['success', 'New user created. Temporary password shown — share it with them.'],
    'updated'    => ['success', 'User updated.'],
];
$flash = $msg_map[$_GET['msg'] ?? ''] ?? null;
$temp_pw_notice = $_SESSION['temp_pw_notice'] ?? null;
unset($_SESSION['temp_pw_notice']);
$flash_warning  = $_SESSION['flash_warning'] ?? null;
unset($_SESSION['flash_warning']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Admin</title>
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
            <h1>Manage Users</h1>
            <p>Search, filter, edit, or archive RMU portal accounts. Newest first.</p>
        </div>
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i>&nbsp; Add New User
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="banner banner-<?php echo $flash[0]; ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash[1]); ?>
        </div>
    <?php endif; ?>

    <?php if ($temp_pw_notice): ?>
        <div class="banner banner-warning temp-pw-banner">
            <i class="fas fa-key"></i>
            <strong>Temporary password for <?php echo htmlspecialchars($temp_pw_notice['name']); ?>:</strong>
            <code><?php echo htmlspecialchars($temp_pw_notice['password']); ?></code>
            &nbsp;Share this with the user — they will be forced to change it on first login.
        </div>
    <?php endif; ?>

    <?php if ($flash_warning): ?>
        <div class="banner banner-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($flash_warning); ?>
        </div>
    <?php endif; ?>

    <div class="counts-row">
        <a href="users.php" class="count-pill <?php echo !$show_archived ? 'active' : ''; ?>">
            Active <span class="count-num"><?php echo (int)$counts['active']; ?></span>
        </a>
        <a href="users.php?archived=1" class="count-pill <?php echo $show_archived ? 'active warn' : ''; ?>">
            Archived <span class="count-num"><?php echo (int)$counts['archived']; ?></span>
        </a>
    </div>

    <form method="GET" class="filter-bar">
        <?php if ($show_archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search by name, email, index # or department" class="filter-input wide">
        <select name="role" class="filter-input">
            <option value="">All roles</option>
            <?php foreach (['admin','hod','secretary','student'] as $r): ?>
                <option value="<?php echo $r; ?>" <?php echo $role_filter === $r ? 'selected' : ''; ?>>
                    <?php echo ucfirst($r); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="dept" class="filter-input">
            <option value="">All departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $dept_filter === $d ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($d); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        <?php if ($q !== '' || $role_filter !== '' || $dept_filter !== ''): ?>
            <a href="users.php<?php echo $show_archived ? '?archived=1' : ''; ?>" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <div class="card" style="padding: 0;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Identifier</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="empty">No users found.</td></tr>
                <?php else: foreach ($users as $u): ?>
                    <tr class="<?php echo !empty($u['is_archived']) ? 'row-archived' : ''; ?>">
                        <td>
                            <span class="ident-chip">
                                <?php echo htmlspecialchars($u['index_number'] ?? ('STF-' . $u['id'])); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($u['full_name'] ?? ''); ?></strong>
                            <?php if (!empty($u['must_change_password'])): ?>
                                <span class="pill-temp" title="Must change temporary password on next login">temp pw</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted"><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($u['department'] ?? '—'); ?></td>
                        <td><span class="role-badge role-<?php echo htmlspecialchars($u['role'] ?: 'unknown'); ?>">
                            <?php echo strtoupper($u['role'] ?: 'unset'); ?>
                        </span></td>
                        <td class="muted"><?php echo htmlspecialchars(date('d M Y', strtotime($u['created_at']))); ?></td>
                        <td class="actions-col">
                            <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-ghost btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if (!empty($u['is_archived'])): ?>
                                <a href="users.php?restore_id=<?php echo $u['id']; ?>" class="btn btn-ghost btn-sm" title="Restore">
                                    <i class="fas fa-undo"></i>
                                </a>
                            <?php else: ?>
                                <a href="users.php?archive_id=<?php echo $u['id']; ?>"
                                   class="btn btn-ghost btn-sm btn-danger"
                                   onclick="return confirm('Archive <?php echo htmlspecialchars(addslashes($u['full_name'] ?? '')); ?>? They will not be able to log in until restored.');"
                                   title="Archive">
                                    <i class="fas fa-archive"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
