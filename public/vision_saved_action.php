<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/vision_state.php';
$user=require_login();
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
csrf_verify();
$pdo=db(); $uid=(int)$user['id']; $action=$_POST['action']??'';
if ($action==='restore_draft') {
    $q=$pdo->prepare('SELECT draft_backup FROM vision_boards WHERE user_id=?');$q->execute([$uid]);
    $backup=$q->fetchColumn();
    if ($backup) {
        $pdo->beginTransaction();
        restore_vision_state($pdo,$uid,json_decode($backup,true,512,JSON_THROW_ON_ERROR));
        $pdo->prepare('UPDATE vision_boards SET draft_backup=NULL WHERE user_id=?')->execute([$uid]);
        $pdo->commit();
    }
    redirect('/vision.php');
}
$id=(int)($_POST['id']??0);
$q=$pdo->prepare('SELECT id,board_data,image_data FROM vision_manifestations WHERE id=? AND user_id=?');
$q->execute([$id,$uid]); $saved=$q->fetch();
if (!$saved) { http_response_code(404); exit; }
if ($action==='home') {
    $pdo->prepare('INSERT INTO home_visions (user_id,manifestation_id) VALUES (?,?) ON DUPLICATE KEY UPDATE manifestation_id=VALUES(manifestation_id)')->execute([$uid,$id]);
    flash('success','Diese Vision erscheint jetzt auf deiner Startseite.');
    redirect('/vision.php#savedVisions');
}
if ($action==='delete') {
    $pdo->prepare('DELETE FROM vision_manifestations WHERE id=? AND user_id=?')->execute([$id,$uid]);
    flash('success','Manifestation gelöscht.');redirect('/vision.php#savedVisions');
}
if ($action!=='edit') { http_response_code(400); exit; }
$state=$saved['board_data'] ? json_decode($saved['board_data'],true,512,JSON_THROW_ON_ERROR) : null;
if (!$state) {
    // Older manifestations contain only pixels. Keep a private copy so later deletion does not break the draft.
    $path='/uploads/vision/'.$uid.'-restored-'.bin2hex(random_bytes(12)).'.jpg';
    if (file_put_contents(__DIR__.$path,$saved['image_data'])===false) { http_response_code(500); exit; }
    $state=['board'=>['background_type'=>'image','background_color'=>'#ffffff','background_image'=>$path],'items'=>[],'texts'=>[]];
}
$pdo->beginTransaction();
$backup=json_encode(vision_state($pdo,$uid),JSON_THROW_ON_ERROR);
$pdo->prepare('UPDATE vision_boards SET draft_backup=COALESCE(draft_backup,?) WHERE user_id=?')->execute([$backup,$uid]);
restore_vision_state($pdo,$uid,$state);
$pdo->commit();
flash('success',$saved['board_data'] ? 'Vision zum Bearbeiten geöffnet. Manifestieren speichert deine Änderungen als neue Version.' : 'Diese ältere Vision ist als Bildvorlage geöffnet. Du kannst neue Texte und Bilder ergänzen und als neue Version manifestieren.');
redirect('/vision.php');
