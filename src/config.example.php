<?php
// Kopiere diese Datei nach config.php. config.php ist in .gitignore und wird NICHT versioniert.
//
// Auf dem Live-Server die Werte bevorzugt als Umgebungsvariablen setzen (Hosting-Panel oder
// SetEnv in der Apache-Konfiguration), statt sie in Dateien zu schreiben:
//   APP_DEBUG, APP_KEY, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
// Die Standardwerte unten sind nur für die lokale Entwicklung mit MAMP gedacht.

$env = static function (string $name, ?string $default = null): ?string {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
};

return [
    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => (int) $env('DB_PORT', '8889'), // MAMP-Standardport für MySQL
        'name'    => $env('DB_NAME', 'tagebuch_app'),
        'user'    => $env('DB_USER', 'root'),       // Live: eigener DB-User mit Rechten nur auf diese Datenbank
        'pass'    => $env('DB_PASS', 'root'),
        'charset' => 'utf8mb4',
    ],

    // Öffentliche Adresse der App (für Links in E-Mails), live mit https://
    'app_url' => $env('APP_URL', 'http://localhost:8890'),

    // Nur lokal auf true setzen! Im Live-Betrieb zeigt true technische Fehlerdetails an.
    'debug' => $env('APP_DEBUG', '0') === '1',

    // Zufälliger Schlüssel (verschlüsselt u. a. das SMTP-Passwort in der Datenbank).
    // Erzeugen mit: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'app_key' => $env('APP_KEY', 'bitte-hier-einen-zufälligen-string-einsetzen'),

    'app_name' => 'Mein Tagebuch',
];
