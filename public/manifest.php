<?php
// Web-App-Manifest: macht die Seite auf dem Handy als App installierbar. Das Symbol wählt der Nutzer im Profil.
require __DIR__ . '/../src/helpers.php';

$key = app_icon_key(is_string($_GET['i'] ?? null) ? $_GET['i'] : null);
$color = app_icon_choices()[$key][1];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode([
    'name' => 'Mein Tagebuch',
    'short_name' => 'Tagebuch',
    'start_url' => '/diary.php',
    'scope' => '/',
    'display' => 'standalone',
    'lang' => 'de',
    'background_color' => $color,
    'theme_color' => $color,
    'icons' => [
        ['src' => "/assets/icons/icon-$key-192.png", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => "/assets/icons/icon-$key-512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => "/assets/icons/icon-$key-512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
