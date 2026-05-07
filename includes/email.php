<?php
/**
 * High-level email helper.
 *
 * Reads SMTP settings from the email_settings table (created in
 * migration 012), builds a Mailer, and sends. Returns a result
 * tuple so callers can decide whether to flash an error.
 *
 * If the `enabled` setting is 0, send_email() is a NO-OP that
 * returns ['ok' => false, 'error' => 'Email is disabled in
 * Email Settings.']. This keeps unconfigured installs from
 * silently failing or — worse — emailing real students with bad
 * config.
 */

require_once __DIR__ . '/../lib/Mailer.php';

if (!function_exists('email_settings')) {
    /**
     * Read all email_settings rows into an associative array.
     * Cached for the request lifetime.
     */
    function email_settings(PDO $pdo): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        $cache = [];
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM email_settings");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            // Migration 012 hasn't run yet — caller will see "disabled".
        }
        return $cache;
    }
}

if (!function_exists('save_email_setting')) {
    function save_email_setting(PDO $pdo, string $key, string $value): void {
        $pdo->prepare("
            INSERT INTO email_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$key, $value]);
    }
}

if (!function_exists('send_email')) {
    /**
     * Send an email using the configured SMTP credentials.
     *
     * @return array{ok: bool, error: ?string, transcript: ?string}
     */
    function send_email(PDO $pdo, string $to, string $subject, string $body, bool $is_html = false): array {
        $cfg = email_settings($pdo);

        if (empty($cfg['enabled']) || $cfg['enabled'] === '0') {
            return ['ok' => false, 'error' => 'Email is disabled in Email Settings.', 'transcript' => null];
        }
        if (empty($cfg['smtp_host']) || empty($cfg['from_address'])) {
            return ['ok' => false, 'error' => 'SMTP host or From address is not configured.', 'transcript' => null];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => "Invalid recipient address: $to", 'transcript' => null];
        }

        $mailer = new Mailer([
            'host'         => $cfg['smtp_host'],
            'port'         => (int)($cfg['smtp_port'] ?? 25),
            'secure'       => $cfg['smtp_secure'] ?? 'none',
            'user'         => $cfg['smtp_user'] ?? '',
            'pass'         => $cfg['smtp_pass'] ?? '',
            'from_address' => $cfg['from_address'],
            'from_name'    => $cfg['from_name'] ?? '',
        ]);

        try {
            $mailer->send($to, $subject, $body, $is_html);
            return ['ok' => true, 'error' => null, 'transcript' => $mailer->transcript()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'transcript' => $mailer->transcript()];
        }
    }
}

/**
 * Convenience wrapper that swallows errors and logs (so app logic
 * keeps moving even if SMTP is misconfigured). Returns whether the
 * email was actually sent.
 */
if (!function_exists('try_send_email')) {
    function try_send_email(PDO $pdo, string $to, string $subject, string $body, bool $is_html = false): bool {
        $r = send_email($pdo, $to, $subject, $body, $is_html);
        if (!$r['ok']) {
            error_log("[rmu_internship email] {$r['error']} — to=$to subject=$subject");
        }
        return $r['ok'];
    }
}
