<?php
function vision_state(PDO $pdo, int $uid): array {
    $q=$pdo->prepare('SELECT background_type,background_color,background_image FROM vision_boards WHERE user_id=?');
    $q->execute([$uid]); $state=['board'=>$q->fetch()];
    foreach (['items'=>'vision_items','texts'=>'vision_texts'] as $key=>$table) {
        $q=$pdo->prepare("SELECT * FROM $table WHERE user_id=? ORDER BY id"); $q->execute([$uid]); $state[$key]=$q->fetchAll();
    }
    return $state;
}
function restore_vision_state(PDO $pdo, int $uid, array $state): void {
    $b=$state['board'];
    $q=$pdo->prepare('UPDATE vision_boards SET background_type=?,background_color=?,background_image=? WHERE user_id=?');
    $q->execute([$b['background_type'],$b['background_color'],$b['background_image'],$uid]);
    foreach (['items'=>['vision_items',['image_path','pos_x','pos_y','width','z_index','caption','source']], 'texts'=>['vision_texts',['content','font','font_size','pos_x','pos_y','width','z_index']]] as $key=>[$table,$columns]) {
        $pdo->prepare("DELETE FROM $table WHERE user_id=?")->execute([$uid]);
        $q=$pdo->prepare("INSERT INTO $table (user_id,".implode(',',$columns).") VALUES (".implode(',',array_fill(0,count($columns)+1,'?')).")");
        foreach ($state[$key] as $row) { $values=[$uid]; foreach($columns as $column) $values[]=$row[$column]; $q->execute($values); }
    }
}
