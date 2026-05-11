<?php
/**
 * CLI smoke test for the SMTP setup.
 *
 *   php tools/test_email.php your.email@example.com
 *
 * Surfaces the full PHPMailer/SMTP transcript on failure so you can
 * see exactly which step Gmail rejected (auth, TLS handshake, etc.).
 *
 * Web access is blocked by tools/.htaccess.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Run from the command line:\n  php tools/test_email.php your.email@example.com\n");
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/email.php';

$to = $argv[1] ?? null;
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/test_email.php your.email@example.com\n");
    exit(1);
}

$body = "<p>This is a test email from the RMU Internship Portal.</p>"
      . "<p>If you can read this, SMTP is working.</p>";

$r = send_email(null, $to, 'RMU portal — SMTP test', $body, true);

if ($r['ok']) {
    echo "Sent OK to $to\n";
    exit(0);
}

echo "FAILED: " . $r['error'] . "\n\n";
if (!empty($r['transcript'])) {
    echo "--- SMTP transcript ---\n";
    echo $r['transcript'] . "\n";
}
exit(1);
