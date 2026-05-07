<?php
require __DIR__ . '/../includes/db.php';
require_role('admin');
require_once __DIR__ . '/../includes/email.php';

$flash      = ['type' => '', 'msg' => ''];
$transcript = null;

// =====================================================================
// POST: save settings
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $keys = ['enabled','smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass',
             'from_address','from_name','test_to'];
    foreach ($keys as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        // Don't blank out the password if the field was left empty (admin
        // probably didn't want to retype it).
        if ($k === 'smtp_pass' && $v === '') continue;
        save_email_setting($pdo, $k, $v);
    }
    $flash = ['type' => 'success', 'msg' => 'Email settings saved.'];
    // Reset cache so the next email_settings() call sees the fresh values.
    email_settings($pdo);
}

// =====================================================================
// POST: send test email
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $to = trim($_POST['test_to'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $flash = ['type' => 'error', 'msg' => 'Provide a valid test-to address.'];
    } else {
        save_email_setting($pdo, 'test_to', $to);

        $r = send_email(
            $pdo, $to,
            'RMU Internship Portal — Test Email',
            "<p>This is a test email from your RMU Internship Portal admin settings.</p>" .
            "<p>If you can read this, SMTP is working.</p>",
            true
        );
        save_email_setting($pdo, 'last_test_at', date('Y-m-d H:i:s'));
        save_email_setting($pdo, 'last_test_msg', $r['ok'] ? 'OK' : ($r['error'] ?? 'Unknown error'));

        $flash = $r['ok']
            ? ['type' => 'success', 'msg' => "Test email sent to $to."]
            : ['type' => 'error',   'msg' => "Test failed: " . $r['error']];
        $transcript = $r['transcript'] ?? null;
    }
}

$cfg = email_settings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Settings | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/registry.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/email.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="header-panel">
        <div>
            <h1>Email Settings</h1>
            <p>SMTP configuration for transactional emails. Defaults point at <a href="https://github.com/axllent/mailpit">Mailpit</a> (local dev catcher) so notifications don't accidentally email real students before you've configured production credentials.</p>
        </div>
        <div class="date-chip <?php echo (!empty($cfg['enabled']) && $cfg['enabled'] !== '0') ? 'on' : 'off'; ?>">
            <i class="fas fa-envelope"></i>&nbsp;
            <?php echo (!empty($cfg['enabled']) && $cfg['enabled'] !== '0') ? 'Enabled' : 'Disabled'; ?>
        </div>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="banner banner-<?php echo htmlspecialchars($flash['type']); ?>">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3><i class="fas fa-cog"></i>&nbsp; SMTP Configuration</h3>

        <form method="POST" autocomplete="off" class="single-form">
            <div class="grid-2">
                <div class="field">
                    <label>Status</label>
                    <select name="enabled">
                        <option value="0" <?php echo ($cfg['enabled'] ?? '0') === '0' ? 'selected' : ''; ?>>Disabled (no emails are sent)</option>
                        <option value="1" <?php echo ($cfg['enabled'] ?? '0') === '1' ? 'selected' : ''; ?>>Enabled</option>
                    </select>
                </div>
                <div class="field">
                    <label>From Address <span class="req">*</span></label>
                    <input type="email" name="from_address" required
                           value="<?php echo htmlspecialchars($cfg['from_address'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>From Name</label>
                    <input type="text" name="from_name"
                           value="<?php echo htmlspecialchars($cfg['from_name'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>SMTP Host <span class="req">*</span></label>
                    <input type="text" name="smtp_host" required
                           value="<?php echo htmlspecialchars($cfg['smtp_host'] ?? ''); ?>"
                           placeholder="e.g. smtp.gmail.com">
                </div>
                <div class="field">
                    <label>SMTP Port <span class="req">*</span></label>
                    <input type="number" name="smtp_port" required min="1" max="65535"
                           value="<?php echo htmlspecialchars($cfg['smtp_port'] ?? '587'); ?>">
                    <small class="muted small">Common: <code>1025</code> (Mailpit), <code>587</code> (STARTTLS), <code>465</code> (SSL).</small>
                </div>
                <div class="field">
                    <label>Encryption</label>
                    <select name="smtp_secure">
                        <option value="none" <?php echo ($cfg['smtp_secure'] ?? '') === 'none' ? 'selected' : ''; ?>>None (plain TCP)</option>
                        <option value="tls"  <?php echo ($cfg['smtp_secure'] ?? '') === 'tls'  ? 'selected' : ''; ?>>STARTTLS (port 587)</option>
                        <option value="ssl"  <?php echo ($cfg['smtp_secure'] ?? '') === 'ssl'  ? 'selected' : ''; ?>>SSL/TLS (port 465)</option>
                    </select>
                </div>
                <div class="field">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_user"
                           value="<?php echo htmlspecialchars($cfg['smtp_user'] ?? ''); ?>"
                           placeholder="(leave blank for unauthenticated SMTP / Mailpit)">
                </div>
                <div class="field">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_pass"
                           placeholder="<?php echo !empty($cfg['smtp_pass']) ? '•••• (kept unchanged)' : ''; ?>">
                    <small class="muted small">For Gmail use an <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener">app password</a>, never your real password. Leave blank to keep the saved value.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="save_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i>&nbsp; Save Settings
                </button>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top: 25px;">
        <h3><i class="fas fa-paper-plane"></i>&nbsp; Send Test Email</h3>
        <p class="muted">Sends a one-line test to verify SMTP. Saves the timestamp + outcome of the last attempt below.</p>

        <form method="POST" class="single-form">
            <div class="grid-2">
                <div class="field">
                    <label>Send Test To</label>
                    <input type="email" name="test_to" required
                           value="<?php echo htmlspecialchars($cfg['test_to'] ?? ''); ?>"
                           placeholder="your.email@rmu.edu.gh">
                </div>
                <div class="field" style="align-self: end;">
                    <button type="submit" name="send_test" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>&nbsp; Send Test
                    </button>
                </div>
            </div>
        </form>

        <?php if (!empty($cfg['last_test_at'])): ?>
            <div class="last-test <?php echo ($cfg['last_test_msg'] ?? '') === 'OK' ? 'ok' : 'err'; ?>">
                <strong>Last test:</strong>
                <?php echo htmlspecialchars($cfg['last_test_at']); ?>
                — <?php echo htmlspecialchars($cfg['last_test_msg']); ?>
            </div>
        <?php endif; ?>

        <?php if ($transcript): ?>
            <details class="smtp-transcript">
                <summary>SMTP transcript</summary>
                <pre><?php echo htmlspecialchars($transcript); ?></pre>
            </details>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
