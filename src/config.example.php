<?php
// Kopiere diese Datei nach config.php und trage deine lokalen Zugangsdaten ein.
// config.php ist in .gitignore und wird NICHT versioniert.

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 8889, // MAMP-Standardport für MySQL (bei "Set Web & MySQL ports to 80 & 3306" stattdessen 3306)
        'name'    => 'tagebuch_app',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],

    // Zufälliger, langer String pro Installation (für CSRF-Token-Salt etc.)
    'app_key' => 'bitte-hier-einen-zufälligen-string-einsetzen',

    'app_name' => 'Mein Tagebuch',
];
