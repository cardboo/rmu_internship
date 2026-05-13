<?php
/**
 * Pure-PHP socket diagnostic — bypasses PHPMailer entirely.
 *
 *   php tools/test_smtp_socket.php
 *
 * If this prints "Connected OK", the network is fine and the
 * failure is inside PHPMailer (probably the 7.0.x alpha). Switch
 * to PHPMailer 6.9.x.
 * If this fails with the same 10061, it's a real firewall /
 * antivirus / ISP block on outbound 587.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403); die("CLI only.\n");
}

$host = 'smtp.gmail.com';
$port = 587;

echo "Resolving $host ...\n";
$ip = gethostbyname($host);
echo "  -> $ip\n\n";

echo "Opening TCP socket to $host:$port (10s timeout) ...\n";
$errno = 0; $errstr = '';
$ctx   = stream_context_create();
$sock  = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);

if (!$sock) {
    echo "FAILED to connect.\n";
    echo "  errno  = $errno\n";
    echo "  errstr = $errstr\n";
    echo "\nThis is happening BEFORE PHPMailer is involved.\n";
    echo "Means: outbound port 587 is blocked on this machine.\n";
    echo "Check: Windows Firewall, antivirus mail-scan, ISP block.\n";
    exit(1);
}

echo "Connected OK.\n\n";

stream_set_timeout($sock, 5);
$greeting = fgets($sock, 1024);
echo "Server greeting:\n  " . trim($greeting) . "\n\n";

fwrite($sock, "EHLO test\r\n");
$line = ''; $reply = '';
while (($line = fgets($sock, 1024)) !== false) {
    $reply .= $line;
    if (preg_match('/^\d{3} /', $line)) break;
}
echo "EHLO reply:\n";
foreach (explode("\n", trim($reply)) as $l) echo "  $l\n";

fwrite($sock, "QUIT\r\n");
fclose($sock);
echo "\nDone — network path to Gmail is fine. If PHPMailer still\n";
echo "fails after this, replace lib/PHPMailer/PHPMailer-7.0.2/\n";
echo "with the 6.9.x release.\n";
exit(0);
