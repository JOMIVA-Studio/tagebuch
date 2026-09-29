<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/uploads.php';
$user = current_user();
if (!$user) { http_response_code(401); exit; }
$id = (int) ($_GET['id'] ?? 0);
$isPdf = ($_GET['format'] ?? '') === 'pdf';
$data = manifestation_contents(db(), (int) $user['id'], $id, $isPdf ? 'pdf' : 'jpg');
if ($data === null) { http_response_code(404); exit; }
header('Content-Type: ' . ($isPdf ? 'application/pdf' : 'image/jpeg'));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . strlen($data));
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="Meine-Vision-' . $id . ($isPdf ? '.pdf' : '.jpg') . '"');
session_write_close();
echo $data;
