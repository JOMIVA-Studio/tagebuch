<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/uploads.php';

// Liefert hochgeladene Bilder nur an die eingeloggte Besitzerin aus.
$user = current_user();
if (!$user) { http_response_code(401); exit; }

$relative = upload_relative_path(UPLOAD_URL_PREFIX . (string) ($_GET['f'] ?? ''), (int) $user['id']);
$file = $relative !== null ? upload_root() . '/' . $relative : null;
if ($file === null || !is_file($file)) { http_response_code(404); exit; }

$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
session_write_close();
header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('Content-Length: ' . filesize($file));
readfile($file);
