<?php
// Kopiere diese Datei nach config.php und trage deine lokalen Zugangsdaten ein.
// config.php ist in .gitignore und wird NICHT versioniert.

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'tagebuch_app',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],

    // Zufaelliger, langer String pro Installation (fuer CSRF-Token-Salt etc.)
    'app_key' => 'bitte-hier-einen-zufaelligen-string-einsetzen',

    'app_name' => 'Mein Tagebuch',
];
