<?php
/**
 * Minimal SMTP client.
 *
 * Just enough functionality for the RMU Internship Portal:
 *   - Plain TCP, implicit SSL (port 465), or STARTTLS (port 587)
 *   - AUTH LOGIN
 *   - Single-recipient plain-text or HTML body
 *
 * Why not PHPMailer? It's ~1500 LOC and we use ~5% of it. This is
 * ~250 lines and has no install steps — fits the "vanilla PHP, no
 * Composer" stance of v2.
 *
 * Usage:
 *   $m = new Mailer([
 *       'host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls',
 *       'user' => 'me@gmail.com',   'pass' => 'app-password',
 *       'from_address' => 'noreply@rmu.edu.gh',
 *       'from_name'    => 'RMU Internship Portal',
 *   ]);
 *   $m->send('student@example.com', 'Welcome', '<p>Hi…</p>', true);
 *
 * Throws RuntimeException on transport / protocol failure. Caller
 * is responsible for catching and surfacing to the user.
 */
class Mailer
{
    private $cfg;
    private $socket;
    private $log = [];

    public function __construct(array $cfg) {
        $defaults = [
            'host' => 'localhost', 'port' => 25, 'secure' => 'none',
            'user' => '',         'pass' => '',
            'from_address' => '', 'from_name' => '',
            'timeout' => 15,
        ];
        $this->cfg = array_merge($defaults, $cfg);
    }

    /** Returns a transcript of the SMTP exchange (for the test-email screen). */
    public function transcript(): string { return implode("\n", $this->log); }

    /**
     * Send a single email.
     *
     * @param string $to       recipient address
     * @param string $subject  subject line
     * @param string $body     message body
     * @param bool   $is_html  true => Content-Type: text/html
     */
    public function send(string $to, string $subject, string $body, bool $is_html = false): void {
        $this->connect();
        try {
            $this->ehlo();

            if ($this->cfg['secure'] === 'tls') {
                $this->cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS handshake failed.');
                }
                $this->ehlo(); // EHLO again over the encrypted channel.
            }

            if ($this->cfg['user'] !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($this->cfg['user']), 334);
                $this->cmd(base64_encode($this->cfg['pass']), 235);
            }

            $from = $this->cfg['from_address'];
            $this->cmd("MAIL FROM:<$from>", 250);
            $this->cmd("RCPT TO:<$to>",     250);
            $this->cmd('DATA',              354);

            $this->writeData($this->buildMessage($to, $subject, $body, $is_html));
            $this->cmd('.', 250);

            $this->cmd('QUIT', 221);
        } finally {
            if (is_resource($this->socket)) fclose($this->socket);
        }
    }

    // ------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------

    private function connect(): void {
        $scheme = $this->cfg['secure'] === 'ssl' ? 'ssl' : 'tcp';
        $url    = $scheme . '://' . $this->cfg['host'] . ':' . (int)$this->cfg['port'];
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client($url, $errno, $errstr, $this->cfg['timeout']);
        if (!$sock) {
            throw new RuntimeException("Could not connect to $url: $errstr ($errno)");
        }
        stream_set_timeout($sock, $this->cfg['timeout']);
        $this->socket = $sock;

        // Greeting (220).
        [$code, $msg] = $this->readResponse();
        if ($code !== 220) {
            throw new RuntimeException("Unexpected greeting: $msg");
        }
    }

    private function ehlo(): void {
        $host = gethostname() ?: 'localhost';
        $this->cmd("EHLO $host", 250);
    }

    private function cmd(string $line, int $expected): void {
        $this->log[] = '> ' . $line;
        fwrite($this->socket, $line . "\r\n");
        [$code, $msg] = $this->readResponse();
        $this->log[] = '< ' . $msg;
        if ($code !== $expected) {
            throw new RuntimeException("Expected $expected after \"$line\", got: $msg");
        }
    }

    private function writeData(string $data): void {
        // RFC 5321: lines starting with "." must be dot-stuffed.
        $data = preg_replace('/^\./m', '..', $data);
        // Normalise newlines to CRLF.
        $data = preg_replace('/\r?\n/', "\r\n", $data);
        fwrite($this->socket, $data . "\r\n");
        $this->log[] = '> [message body, ' . strlen($data) . ' bytes]';
    }

    private function readResponse(): array {
        $lines = [];
        while (!feof($this->socket)) {
            $raw = fgets($this->socket, 515);
            if ($raw === false) break;
            $lines[] = rtrim($raw);
            // 4th char is space on the FINAL line, '-' on continuation lines.
            if (strlen($raw) >= 4 && $raw[3] === ' ') break;
        }
        $msg  = implode("\n", $lines);
        $code = (int)substr($lines[0] ?? '', 0, 3);
        return [$code, $msg];
    }

    private function buildMessage(string $to, string $subject, string $body, bool $is_html): string {
        $from_name    = $this->cfg['from_name'];
        $from_address = $this->cfg['from_address'];
        $from = $from_name !== ''
            ? sprintf('"%s" <%s>', addslashes($from_name), $from_address)
            : $from_address;

        $headers   = [];
        $headers[] = 'From: ' . $from;
        $headers[] = 'To: '   . $to;
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . ($this->cfg['host'] ?: 'localhost') . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: ' . ($is_html ? 'text/html' : 'text/plain') . '; charset=utf-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** RFC 2047 Q-encoding for non-ASCII subject lines. */
    private function encodeHeader(string $value): string {
        if (preg_match('/^[\x20-\x7E]*$/', $value)) return $value;
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
