<?php
/**
 * Email transport — PHPMailer-backed.
 *
 * PHPMailer files are expected at lib/PHPMailer/src/. See
 * lib/PHPMailer/README.md for the one-time install steps.
 */

const SMTP_HOST         = 'smtp.gmail.com';
const SMTP_PORT         = 587;
const SMTP_USER         = 'isabdulaisaiku@gmail.com';
const SMTP_PASS         = 'twkurtspdegwanpu';        // Gmail app password
const SMTP_FROM_ADDRESS = 'isabdulaisaiku@gmail.com';
const SMTP_FROM_NAME    = 'RMU Internship Portal';

// Load PHPMailer if it's been dropped into lib/PHPMailer/src/.
$_phpmailer_dir = __DIR__ . '/../lib/PHPMailer/src';
if (is_file($_phpmailer_dir . '/PHPMailer.php')) {
    require_once $_phpmailer_dir . '/Exception.php';
    require_once $_phpmailer_dir . '/PHPMailer.php';
    require_once $_phpmailer_dir . '/SMTP.php';
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
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->Timeout    = 20;
            $mail->CharSet    = 'UTF-8';

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
