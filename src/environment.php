<?php
declare(strict_types=1);

/**
 * Gemeinsame Laufzeit-Einstellungen für User- und Admin-Bereich:
 * Fehlerausgabe nur im Debug-Modus, sonst Log-Datei + neutrale Fehlerseite.
 */
function app_debug(): bool
{
    return (bool) (app_config()['debug'] ?? false);
}

function init_app_environment(): void
{
    date_default_timezone_set('Europe/Zurich');
    error_reporting(E_ALL);
    ini_set('display_errors', app_debug() ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

    set_exception_handler(static function (Throwable $e): void {
        error_log((string) $e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (app_debug()) {
            echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }
        echo '<!doctype html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Mein Tagebuch</title></head><body style="font-family:sans-serif;text-align:center;padding:60px 16px;">'
            . '<h1>Da ist etwas schiefgelaufen</h1><p>Bitte versuche es in ein paar Minuten noch einmal.</p>'
            . '<p><a href="/">Zur Startseite</a></p></body></html>';
    });

    $key = (string) (app_config()['app_key'] ?? '');
    if (!app_debug() && (strlen($key) < 32 || str_starts_with($key, 'bitte-hier'))) {
        throw new RuntimeException('app_key ist nicht gesetzt. Bitte in src/config.php oder als Umgebungsvariable APP_KEY einen eigenen Schlüssel hinterlegen.');
    }
}

/** Session-Cookies nur über HTTPS senden, sobald die Seite per HTTPS läuft. */
function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
