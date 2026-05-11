<?php
/**
 * Hardcoded SMTP transport for the RMU Internship Portal.
 *
 * Was previously DB-backed via admin/email_settings.php — that page
 * has been removed in favour of these baked-in credentials. Local
 * dev / production both use this same Gmail account.
 *
 * To change credentials, edit the constants below.
 */

require_once __DIR__ . '/../lib/Mailer.php';

const SMTP_HOST         = 'smtp.gmail.com';
const SMTP_PORT         = 587;
const SMTP_SECURE       = 'tls';
const SMTP_USER         = 'isabdulaisaiku@gmail.com';
const SMTP_PASS         = 'twkurtspdegwanpu';        // Gmail app password
const SMTP_FROM_ADDRESS = 'isabdulaisaiku@gmail.com';
const SMTP_FROM_NAME    = 'RMU Internship Portal';

if (!function_exists('send_email')) {
    /**
     * Send a single transactional email.
     *
     * Signature kept identical to the previous (DB-backed) version so
     * existing call sites don't need to change. The $pdo parameter
     * is now unused but kept for compatibility.
     *
     * @return array{ok: bool, error: ?string, transcript: ?string}
     */
    function send_email(?PDO $pdo, string $to, string $subject, string $body, bool $is_html = false): array {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => "Invalid recipient address: $to", 'transcript' => null];
        }

        $mailer = new Mailer([
            'host'         => SMTP_HOST,
            'port'         => SMTP_PORT,
            'secure'       => SMTP_SECURE,
            'user'         => SMTP_USER,
            'pass'         => SMTP_PASS,
            'from_address' => SMTP_FROM_ADDRESS,
            'from_name'    => SMTP_FROM_NAME,
        ]);

        try {
            $mailer->send($to, $subject, $body, $is_html);
            return ['ok' => true, 'error' => null, 'transcript' => $mailer->transcript()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'transcript' => $mailer->transcript()];
        }
    }
}

if (!function_exists('try_send_email')) {
    /**
     * Best-effort wrapper. Logs failure but returns false instead of
     * throwing, so business logic isn't blocked by SMTP issues.
     */
    function try_send_email(?PDO $pdo, string $to, string $subject, string $body, bool $is_html = false): bool {
        $r = send_email($pdo, $to, $subject, $body, $is_html);
        if (!$r['ok']) {
            error_log("[rmu_internship email] {$r['error']} — to=$to subject=$subject");
        }
        return $r['ok'];
    }
}
