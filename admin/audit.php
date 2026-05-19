<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');

// ----------------------------------------------------------------------
// Filters
// ----------------------------------------------------------------------
$q             = trim($_GET['q']      ?? '');
$action_filter = trim($_GET['action'] ?? '');
$actor_filter  = trim($_GET['actor']  ?? '');
$since         = trim($_GET['since']  ?? '');

$where  = [];
$params = [];

if ($q !== '') {
    $where[]  = '(a.target_id LIKE ? OR a.payload_json LIKE ?)';
    $like     = "%$q%";
    $params[] = $like;
    $params[] = $like;
}
if ($action_filter !== '') {
    $where[]  = 'a.action = ?';
    $params[] = $action_filter;
}
if ($actor_filter !== '') {
    $where[]  = 'u.full_name LIKE ?';
    $params[] = "%$actor_filter%";
}
if ($since !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
    $where[]  = 'a.created_at >= ?';
    $params[] = $since . ' 00:00:00';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name AS actor_name, u.email AS actor_email
        FROM audit_log a
        LEFT JOIN users u ON u.id = a.actor_user_id
        $where_sql
        ORDER BY a.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    $missing_migration = false;
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'audit_log')) {
        $rows = $actions = [];
        $total = 0;
        $missing_migration = true;
    } else {
        throw $e;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Log | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/users.css'); ?>">
    <style>
        .audit-table { font-size: 0.88rem; }
        .audit-table code.action { background: #eef2ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; }
        .audit-table .payload { color: #6b7280; font-family: monospace; font-size: 0.78rem; max-width: 380px; white-space: pre-wrap; word-break: break-all; }
        .audit-table .when { color: #6b7280; white-space: nowrap; }
        .audit-table .actor-anon { color: #9ca3af; font-style: italic; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Audit Log</h1>
            <p>Append-only record of sensitive admin actions. Newest first.</p>
        </div>
        <div class="date-chip">
            <i class="fas fa-shield-alt"></i>&nbsp; <?php echo number_format($total); ?> events
        </div>
    </div>

    <?php if ($missing_migration): ?>
        <div class="banner banner-warning">
            <i class="fas fa-exclamation-triangle"></i>
            The audit_log table doesn't exist yet. Run
            <code>migrations/016_audit_log.sql</code> from phpMyAdmin to enable this page.
        </div>
    <?php endif; ?>

    <form method="GET" class="filter-bar">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search target ID or payload (e.g. an email, an index)" class="filter-input wide">
        <select name="action" class="filter-input">
            <option value="">All actions</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $action_filter === $a ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="actor" value="<?php echo htmlspecialchars($actor_filter); ?>"
               placeholder="Actor name" class="filter-input">
        <input type="date" name="since" value="<?php echo htmlspecialchars($since); ?>"
               title="Show events from this date onward" class="filter-input">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        <?php if ($q !== '' || $action_filter !== '' || $actor_filter !== '' || $since !== ''): ?>
            <a href="audit.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <div class="card" style="padding: 0;">
        <table class="users-table audit-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="empty">No audit events match.</td></tr>
                <?php else: foreach ($rows as $r):
                    $payload = $r['payload_json']
                        ? json_decode($r['payload_json'], true)
                        : null;
                ?>
                    <tr>
                        <td class="when">
                            <?php echo htmlspecialchars(date('d M Y H:i:s', strtotime($r['created_at']))); ?>
                        </td>
                        <td>
                            <?php if ($r['actor_name']): ?>
                                <strong><?php echo htmlspecialchars($r['actor_name']); ?></strong>
                                <div class="muted small"><?php echo htmlspecialchars($r['actor_role'] ?? ''); ?></div>
                            <?php else: ?>
                                <span class="actor-anon">— system —</span>
                            <?php endif; ?>
                        </td>
                        <td><code class="action"><?php echo htmlspecialchars($r['action']); ?></code></td>
                        <td>
                            <?php if ($r['target_type'] || $r['target_id']): ?>
                                <span class="muted small"><?php echo htmlspecialchars($r['target_type'] ?? ''); ?></span>
                                <?php if ($r['target_id']): ?>
                                    <code><?php echo htmlspecialchars($r['target_id']); ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payload !== null): ?>
                                <div class="payload"><?php echo htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted small"><?php echo htmlspecialchars($r['ip'] ?? '—'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if (count($rows) === 500): ?>
            <p class="muted small" style="padding: 12px;">Showing newest 500 events. Use filters to narrow down.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
