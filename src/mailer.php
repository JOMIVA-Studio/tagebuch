<?php
declare(strict_types=1);

/**
 * Sehr schlanker SMTP-Client ohne externe Abhängigkeiten (kein Composer
 * nötig). Unterstützt SSL (Port 465), STARTTLS (Port 587) und
 * unverschlüsselt, sowie AUTH LOGIN.
 */
class SmtpMailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $encryption, // 'none' | 'ssl' | 'tls'
        private string $username,
        private string $password,
        private string $fromEmail,
        private string $fromName
    ) {
    }

    public static function fromSettings(array $settings): self
    {
        return new self(
            $settings['host'] ?? '',
            (int) ($settings['port'] ?? 587),
            $settings['encryption'] ?? 'tls',
            $settings['username'] ?? '',
            $settings['password'] ?? '',
            $settings['from_email'] ?? '',
            $settings['from_name'] ?? ''
        );
    }

    /**
     * @throws RuntimeException bei jedem Fehler, Nachricht enthält den Grund.
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): void
    {
        if ($this->host === '' || $this->fromEmail === '') {
            throw new RuntimeException('SMTP ist nicht konfiguriert (Host/Absender fehlt). Bitte in der Administration hinterlegen.');
        }

        $transport = $this->encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            throw new RuntimeException("Verbindung zu {$this->host}:{$this->port} fehlgeschlagen: $errstr");
        }

        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO localhost', 250);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS-Verschlüsselung fehlgeschlagen.');
                }
                $this->command($socket, 'EHLO localhost', 250);
            }

            if ($this->username !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($this->username), 334);
                $this->command($socket, base64_encode($this->password), 235);
            }

            $this->command($socket, 'MAIL FROM:<' . $this->fromEmail . '>', 250);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', 250);
            $this->command($socket, 'DATA', 354);

            $headers = [
                'From: ' . $this->encodeHeader($this->fromName) . ' <' . $this->fromEmail . '>',
                'To: ' . $this->encodeHeader($toName) . ' <' . $toEmail . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Date: ' . date('r'),
            ];

            $body = preg_replace('/^\./m', '..', $htmlBody);
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            $this->command($socket, $message, 250);

            $this->command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /** @param resource $socket */
    private function command($socket, string $line, int $expectedCode): string
    {
        fwrite($socket, $line . "\r\n");
        return $this->expect($socket, $expectedCode);
    }

    /** @param resource $socket */
    private function expect($socket, int $expectedCode): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            // Mehrzeilige Antworten haben "-" nach dem Code, die letzte Zeile ein Leerzeichen.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SMTP-Fehler: erwartet $expectedCode, erhalten: " . trim($response));
        }

        return $response;
    }
}

/**
 * Rendert eine Mail-Vorlage aus der DB (Platzhalter wie {{name}}) und
 * versendet sie über die hinterlegten SMTP-Zugangsdaten. Protokolliert
 * jeden Versand in email_log.
 */
function send_templated_mail(string $templateKey, array $user, array $placeholders = []): void
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE template_key = :key');
    $stmt->execute(['key' => $templateKey]);
    $template = $stmt->fetch();

    if ($template === false) {
        throw new RuntimeException("Mail-Vorlage '$templateKey' ist nicht in der Administration hinterlegt.");
    }

    $smtpSettings = $pdo->query('SELECT * FROM smtp_settings WHERE id = 1')->fetch() ?: [];

    $replace = array_merge(['name' => $user['name'] ?? '', 'email' => $user['email'] ?? ''], $placeholders);
    $subject = strtr($template['subject'], wrap_placeholders($replace));
    $body = strtr($template['body_html'], wrap_placeholders($replace));

    try {
        $mailer = SmtpMailer::fromSettings($smtpSettings);
        $mailer->send($user['email'], $user['name'] ?? '', $subject, $body);

        log_email($templateKey, $user['id'] ?? null, $user['email'], 'sent', null);
    } catch (Throwable $e) {
        log_email($templateKey, $user['id'] ?? null, $user['email'], 'failed', $e->getMessage());
        throw $e;
    }
}

function wrap_placeholders(array $values): array
{
    $wrapped = [];
    foreach ($values as $key => $value) {
        $wrapped['{{' . $key . '}}'] = (string) $value;
    }
    return $wrapped;
}

function log_email(string $templateKey, ?int $userId, string $recipient, string $status, ?string $error): void
{
    $stmt = db()->prepare(
        'INSERT INTO email_log (user_id, template_key, recipient, status, error_message)
         VALUES (:user_id, :template_key, :recipient, :status, :error)'
    );
    $stmt->execute([
        'user_id'      => $userId,
        'template_key' => $templateKey,
        'recipient'    => $recipient,
        'status'       => $status,
        'error'        => $error,
    ]);
}
