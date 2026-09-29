<?php
require __DIR__ . '/../src/bootstrap.php';
$user = current_user();
if (!$user) { http_response_code(401); exit; }
$id = (int) ($_GET['id'] ?? 0);
$isPdf = ($_GET['format'] ?? '') === 'pdf';
$column = $isPdf ? 'pdf_data' : 'image_data';
$q = db()->prepare("SELECT $column FROM vision_manifestations WHERE id=? AND user_id=?");
$q->execute([$id, $user['id']]); $data = $q->fetchColumn();
if ($data === false) { http_response_code(404); exit; }
header('Content-Type: ' . ($isPdf ? 'application/pdf' : 'image/jpeg'));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . strlen($data));
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="Meine-Vision-' . $id . ($isPdf ? '.pdf' : '.jpg') . '"');
session_write_close();
echo $data;
