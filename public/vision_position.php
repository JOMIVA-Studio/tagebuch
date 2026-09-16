<?php
require __DIR__ . '/../src/bootstrap.php';

$user = current_user();
if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
$x = max(0, (int) ($_POST['x'] ?? 0));
$y = max(0, (int) ($_POST['y'] ?? 0));

$stmt = db()->prepare('UPDATE vision_items SET pos_x = :x, pos_y = :y WHERE id = :id AND user_id = :uid');
$stmt->execute(['x' => $x, 'y' => $y, 'id' => $id, 'uid' => $user['id']]);

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
