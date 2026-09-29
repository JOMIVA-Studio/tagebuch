<?php
declare(strict_types=1);
// Einmalige Umstellung bestehender Daten (wiederholbar ausführbar):
//  1. Uploads aus public/uploads/ nach storage/uploads/ verschieben und die URLs in der
//     Datenbank auf /media.php?f=… umstellen (danach nur noch mit Login abrufbar).
//  2. Bilder/PDFs gespeicherter Visionen aus der Datenbank (BLOB) nach storage/manifestations/.
// Aufruf: php bin/migrate-files.php
if (PHP_SAPI !== 'cli') { exit(1); }

require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/uploads.php';

$pdo = db();
$legacyRoot = __DIR__ . '/../public/uploads';
$moved = 0;

$replaceUrl = static function (string $old, string $new) use ($pdo): void {
    $jsonOld = substr(json_encode($old), 1, -1);
    $jsonNew = substr(json_encode($new), 1, -1);
    $pdo->prepare('UPDATE vision_items SET image_path = :new WHERE image_path = :old')->execute(['new' => $new, 'old' => $old]);
    $pdo->prepare('UPDATE vision_boards SET background_image = :new WHERE background_image = :old')->execute(['new' => $new, 'old' => $old]);
    foreach (['vision_boards' => 'draft_backup', 'vision_manifestations' => 'board_data'] as $table => $column) {
        $pdo->prepare("UPDATE $table SET $column = REPLACE(REPLACE($column, :old, :new), :jold, :jnew) WHERE $column IS NOT NULL")
            ->execute(['old' => $old, 'new' => $new, 'jold' => $jsonOld, 'jnew' => $jsonNew]);
    }
};

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($legacyRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $legacyRelative = substr($file->getPathname(), strlen($legacyRoot) + 1);
    // Formen: vision/<uid>/<datei> , vision-bg/<uid>/<datei> , vision/<uid>-restored-<hex>.jpg
    if (preg_match('#^(vision|vision-bg)/(\d+)/([a-z0-9]+\.(?:jpg|png|webp|gif))$#', $legacyRelative, $m)) {
        $newRelative = "$m[1]/$m[2]/$m[3]";
    } elseif (preg_match('#^(vision)/(\d+)-restored-([a-f0-9]+\.jpg)$#', $legacyRelative, $m)) {
        $newRelative = "$m[1]/$m[2]/restored-$m[3]";
    } else {
        continue; // .gitkeep, .htaccess usw.
    }

    $target = upload_root() . '/' . $newRelative;
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0750, true);
    }
    if (!rename($file->getPathname(), $target)) {
        fwrite(STDERR, "Konnte $legacyRelative nicht verschieben.\n");
        continue;
    }
    $replaceUrl('/uploads/' . $legacyRelative, UPLOAD_URL_PREFIX . $newRelative);
    $moved++;
}
echo "$moved Upload(s) nach storage/uploads/ verschoben.\n";

$rows = $pdo->query('SELECT id, user_id FROM vision_manifestations WHERE image_data IS NOT NULL OR pdf_data IS NOT NULL')->fetchAll();
foreach ($rows as $row) {
    $id = (int) $row['id'];
    $uid = (int) $row['user_id'];
    $jpeg = manifestation_contents($pdo, $uid, $id, 'jpg');
    $pdf = manifestation_contents($pdo, $uid, $id, 'pdf');
    if ($jpeg === null || $pdf === null) {
        continue;
    }
    store_manifestation_files($uid, $id, $jpeg, $pdf);
    $pdo->prepare('UPDATE vision_manifestations SET image_data = NULL, pdf_data = NULL WHERE id = ?')->execute([$id]);
}
echo count($rows) . " gespeicherte Vision(en) aus der Datenbank in Dateien ausgelagert.\n";
