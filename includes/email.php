<?php
/**
 * Email transport — PHPMailer-backed.
 *
 * SMTP credentials live in a gitignored local file so they never
 * land in commit history. Copy
 *     includes/email_secrets.local.example.php
 *   → includes/email_secrets.local.php
 * and fill in real values. Anything that file doesn't define()
 * falls back to the safe defaults below.
 *
 * PHPMailer source is expected at lib/PHPMailer/.../src/ — see
 * lib/PHPMailer/README.md and tools/install_phpmailer.bat.
 */

$_secrets_file = __DIR__ . '/email_secrets.local.php';
if (is_file($_secrets_file)) require_once $_secrets_file;

// Defaults — point at Brevo on port 2525 (no AV mail-guard interception).
// Override any of these in email_secrets.local.php.
if (!defined('SMTP_HOST'))         define('SMTP_HOST',         'smtp-relay.brevo.com');
if (!defined('SMTP_PORT'))         define('SMTP_PORT',         2525);
if (!defined('SMTP_SECURE'))       define('SMTP_SECURE',       'tls');   // 'tls' or 'ssl'
if (!defined('SMTP_USER'))         define('SMTP_USER',         '');
if (!defined('SMTP_PASS'))         define('SMTP_PASS',         '');
if (!defined('SMTP_FROM_ADDRESS')) define('SMTP_FROM_ADDRESS', 'noreply@rmu.edu.gh');
if (!defined('SMTP_FROM_NAME'))    define('SMTP_FROM_NAME',    'RMU Internship Portal');

// Auto-locate the PHPMailer src/ directory wherever it ended up
// under lib/PHPMailer/. Tolerates both "lib/PHPMailer/src/" and
// "lib/PHPMailer/PHPMailer-x.y.z/src/" (the path that results from
// extracting the official release zip directly).
$_phpmailer_src = null;
$_pm_candidates = array_merge(
    [__DIR__ . '/../lib/PHPMailer/src'],
    glob(__DIR__ . '/../lib/PHPMailer/*/src') ?: []
);
foreach ($_pm_candidates as $_cand) {
    if (is_file($_cand . '/PHPMailer.php')) { $_phpmailer_src = $_cand; break; }
}
if ($_phpmailer_src !== null) {
    require_once $_phpmailer_src . '/Exception.php';
    require_once $_phpmailer_src . '/PHPMailer.php';
    require_once $_phpmailer_src . '/SMTP.php';
}

if (!function_exists('send_email')) {
    /**
     * Send a single transactional email through PHPMailer + Gmail SMTP.
     *
     * @return array{ok: bool, error: ?string, transcript: ?string}
     */
    function send_email(?PDO $pdo, string $to, string $subject, string $body, bool $is_html = false): array {
        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return [
                'ok'         => false,
                'error'      => 'PHPMailer is not installed. See lib/PHPMailer/README.md for the one-time install steps.',
                'transcript' => null,
            ];
        }
        if (SMTP_USER === '' || SMTP_PASS === '') {
            return [
                'ok'         => false,
                'error'      => 'SMTP credentials are not configured. Create includes/email_secrets.local.php from the example file and fill in your relay user + key.',
                'transcript' => null,
            ];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => "Invalid recipient address: $to", 'transcript' => null];
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // Capture the SMTP transcript so we can surface it on failure.
        $debug_log = [];
        $mail->SMTPDebug   = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) use (&$debug_log) {
            $debug_log[] = "[$level] " . rtrim($str);
        };

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = (SMTP_SECURE === 'ssl')
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS       // implicit SSL (port 465)
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;   // STARTTLS (port 587)
            $mail->Port       = SMTP_PORT;
            $mail->Timeout    = 20;
            $mail->CharSet    = 'UTF-8';

            // Local-dev TLS bypass: Windows PHP ships without a CA bundle,
            // so OpenSSL can't verify Gmail's certificate chain by default
            // ("certificate verify failed"). The connection is still
            // STARTTLS-encrypted — only the chain validation is skipped.
            //
            // For production, the proper fix is to set curl.cainfo and
            // openssl.cafile in php.ini to a cacert.pem from
            // https://curl.se/ca/cacert.pem and remove this block.
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML($is_html);
            $mail->Body    = $body;
            if (!$is_html) $mail->AltBody = $body;

            $mail->send();
            return ['ok' => true, 'error' => null, 'transcript' => implode("\n", $debug_log)];
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return [
                'ok'         => false,
                'error'      => $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(),
                'transcript' => implode("\n", $debug_log),
            ];
        } catch (Throwable $e) {
            return [
                'ok'         => false,
                'error'      => $e->getMessage(),
                'transcript' => implode("\n", $debug_log),
            ];
        }
    }
}

if (!function_exists('try_send_email')) {
    /**
     * Best-effort wrapper. Logs failure but returns false instead of
     * throwing, so the underlying business action (saving a record,
     * approving a request, etc.) isn't blocked by SMTP issues.
     */
    function try_send_email(?PDO $pdo, string $to, string $subject, string $body, bool $is_html = false): bool {
        $r = send_email($pdo, $to, $subject, $body, $is_html);
        if (!$r['ok']) {
            error_log("[rmu_internship email] {$r['error']} — to=$to subject=$subject");
        }
        return $r['ok'];
    }
}
