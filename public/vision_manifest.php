<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/vision_pdf.php';
require_once __DIR__ . '/../src/vision_state.php';
require_once __DIR__ . '/../src/uploads.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$user = current_user();
if (!$user) { http_response_code(401); echo json_encode(['error'=>'Bitte melde dich erneut an.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_verify();
$preview = ($_POST['preview'] ?? '') === '1';
$key = $_POST['request_key'] ?? '';
if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/D', $key)) { http_response_code(400); echo json_encode(['error'=>'Ungültige Anfrage.']); exit; }
$q = db()->prepare('SELECT id FROM vision_manifestations WHERE user_id=? AND request_key=?');
$q->execute([$user['id'], $key]);
if (!$preview && ($id = $q->fetchColumn())) { echo json_encode(['id'=>(int)$id]); exit; }
$encoded = $_POST['image'] ?? '';
if (!is_string($encoded) || strlen($encoded) > 7000000 || !str_starts_with($encoded, 'data:image/jpeg;base64,')) {
    http_response_code(400); echo json_encode(['error'=>'Das Bild ist zu groß oder ungültig.']); exit;
}
$jpeg = base64_decode(substr($encoded, 23), true);
$info = $jpeg !== false ? @getimagesizefromstring($jpeg) : false;
if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 16000000) {
    http_response_code(400); echo json_encode(['error'=>'Das Bildformat konnte nicht geprüft werden.']); exit;
}
// Re-encode validated pixels; never store arbitrary uploaded file bytes.
$decoded = @imagecreatefromstring($jpeg);
if (!$decoded) { http_response_code(400); echo json_encode(['error'=>'Das Bild konnte nicht gelesen werden.']); exit; }
ob_start(); imagejpeg($decoded, null, 96); $jpeg = ob_get_clean(); imagedestroy($decoded);
$pdf = vision_snapshot_pdf($jpeg, $info[0], $info[1]);
if ($preview) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Meine-Vision.pdf"');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}
try {
    // Bild und PDF liegen als Dateien in storage/manifestations/, nicht mehr als BLOB in der Datenbank.
    $q = db()->prepare('INSERT INTO vision_manifestations (user_id,request_key,width,height,board_data) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
    $q->execute([$user['id'], $key, $info[0], $info[1], json_encode(vision_state(db(), (int)$user['id']), JSON_THROW_ON_ERROR)]);
    $newId = (int) db()->lastInsertId();
    try {
        store_manifestation_files((int) $user['id'], $newId, $jpeg, $pdf);
    } catch (Throwable $e) {
        db()->prepare('DELETE FROM vision_manifestations WHERE id=? AND user_id=?')->execute([$newId, $user['id']]);
        throw $e;
    }
    echo json_encode(['id'=>$newId]);
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500); echo json_encode(['error'=>'Die Vision konnte nicht gespeichert werden. Bitte erneut versuchen.']);
}
