<?php
/**
 * Pure-PHP socket diagnostic — bypasses PHPMailer entirely.
 *
 *   php tools/test_smtp_socket.php          (defaults: ssl + 465)
 *   php tools/test_smtp_socket.php tcp 587  (plain TCP, no TLS)
 *   php tools/test_smtp_socket.php tls 587  (STARTTLS-style, but raw)
 *
 * If this prints "Connected OK", the network is fine and the
 * failure is inside PHPMailer.
 * If this fails, the error message tells us exactly what's wrong
 * (cert chain, TLS handshake, missing openssl extension, etc.).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403); die("CLI only.\n");
}

$scheme = $argv[1] ?? 'ssl';   // 'ssl' (465) | 'tcp' (587) | 'tls' (587, but we won't STARTTLS here)
$port   = isset($argv[2]) ? (int)$argv[2] : ($scheme === 'ssl' ? 465 : 587);
$host   = 'smtp.gmail.com';

echo "PHP version: " . PHP_VERSION . "\n";
echo "openssl ext: " . (extension_loaded('openssl') ? 'loaded' : 'MISSING') . "\n";
if (extension_loaded('openssl')) {
    $loc = openssl_get_cert_locations();
    echo "  default cafile: {$loc['default_cert_file']}  exists=" . (is_file($loc['default_cert_file']) ? 'yes' : 'no') . "\n";
    echo "  ini openssl.cafile: " . (ini_get('openssl.cafile') ?: '(empty)') . "\n";
}
echo "\n";

echo "Resolving $host ...\n";
$ip = gethostbyname($host);
echo "  -> $ip\n\n";

$transport = ($scheme === 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
echo "Opening $transport (10s timeout) ...\n";

$ctx = stream_context_create([
    'ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ],
]);

$errno = 0; $errstr = '';
$sock = @stream_socket_client($transport, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);

if (!$sock) {
    echo "FAILED to connect.\n";
    echo "  errno  = $errno\n";
    echo "  errstr = $errstr\n\n";
    $err = error_get_last();
    if ($err) {
        echo "Last PHP error:\n";
        echo "  type    = {$err['type']}\n";
        echo "  message = {$err['message']}\n";
        echo "  file    = {$err['file']}:{$err['line']}\n";
    }
    exit(1);
}

echo "Connected OK.\n\n";

stream_set_timeout($sock, 5);
$greeting = fgets($sock, 1024);
echo "Server greeting:\n  " . trim($greeting ?: '(no greeting received)') . "\n\n";

fwrite($sock, "EHLO test.local\r\n");
$reply = '';
while (($line = fgets($sock, 1024)) !== false) {
    $reply .= $line;
    if (preg_match('/^\d{3} /', $line)) break;
}
echo "EHLO reply:\n";
foreach (explode("\n", trim($reply)) as $l) echo "  $l\n";

fwrite($sock, "QUIT\r\n");
fclose($sock);
echo "\nDone.\n";
exit(0);
