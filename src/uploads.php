<?php
declare(strict_types=1);

/**
 * Hochgeladene Bilder liegen ausserhalb des Webroots in storage/uploads/<bereich>/<user-id>/
 * und werden nur über public/media.php an die eingeloggte Besitzerin ausgeliefert.
 * In der Datenbank steht die URL "/media.php?f=<bereich>/<user-id>/<datei>".
 */
const UPLOAD_URL_PREFIX = '/media.php?f=';
const UPLOAD_PATH_PATTERN = '#^(vision|vision-bg)/(\d+)/([a-z0-9-]{1,80}\.(?:jpg|png|webp|gif))$#D';

function upload_root(): string
{
    return __DIR__ . '/../storage/uploads';
}

function manifestation_root(): string
{
    return __DIR__ . '/../storage/manifestations';
}

/**
 * Nimmt einen $_FILES-Eintrag entgegen, prüft ihn (echtes Bild, erlaubter
 * Typ, Grössenlimit) und speichert ihn unter einem zufälligen Dateinamen
 * im Upload-Verzeichnis des Users. Wirft RuntimeException bei Problemen.
 *
 * @return string URL zur gespeicherten Datei (z.B. "/media.php?f=vision/3/ab12cd.jpg")
 */
function store_uploaded_image(array $file, int $userId, string $subdir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Der Upload ist fehlgeschlagen.');
    }

    $maxBytes = 8 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Die Datei ist zu gross (maximal 8 MB).');
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('Die Datei ist kein gültiges Bild.');
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];
    $extension = $allowed[$info[2]] ?? null;
    if ($extension === null) {
        throw new RuntimeException('Erlaubt sind nur JPG-, PNG-, WEBP- oder GIF-Bilder.');
    }

    $relative = $subdir . '/' . $userId . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
    $target = upload_root() . '/' . $relative;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
        throw new RuntimeException('Das Upload-Verzeichnis konnte nicht angelegt werden.');
    }
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
    }

    return UPLOAD_URL_PREFIX . $relative;
}

/** Speichert Bytes (z. B. ein wiederhergestelltes Bild) als Upload der Userin. */
function store_upload_bytes(string $bytes, int $userId, string $subdir, string $extension): string
{
    $relative = $subdir . '/' . $userId . '/restored-' . bin2hex(random_bytes(12)) . '.' . $extension;
    $target = upload_root() . '/' . $relative;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
        throw new RuntimeException('Das Upload-Verzeichnis konnte nicht angelegt werden.');
    }
    if (file_put_contents($target, $bytes) === false) {
        throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
    }
    return UPLOAD_URL_PREFIX . $relative;
}

/**
 * Liefert den relativen Speicherpfad zu einer Upload-URL, wenn sie der Userin gehört – sonst null.
 */
function upload_relative_path(?string $url, int $userId): ?string
{
    if ($url === null || !str_starts_with($url, UPLOAD_URL_PREFIX)) {
        return null;
    }
    $relative = substr($url, strlen(UPLOAD_URL_PREFIX));
    if (!preg_match(UPLOAD_PATH_PATTERN, $relative, $m) || (int) $m[2] !== $userId) {
        return null;
    }
    return $relative;
}

/**
 * Löscht eine hochgeladene Datei, sobald sie nirgends mehr verwendet wird: weder auf dem
 * aktuellen Board noch als Hintergrund noch in einer gespeicherten Vision oder im Entwurf.
 */
function delete_upload_if_unreferenced(PDO $pdo, int $userId, ?string $url): void
{
    $relative = upload_relative_path($url, $userId);
    if ($relative === null) {
        return;
    }

    $plain = $url;
    $jsonEscaped = substr(json_encode($url), 1, -1); // so steht die URL in board_data/draft_backup
    $checks = [
        ['SELECT COUNT(*) FROM vision_items WHERE user_id = :uid AND image_path = :url', ['url' => $plain]],
        ['SELECT COUNT(*) FROM vision_boards WHERE user_id = :uid AND (background_image = :url OR draft_backup LIKE :a OR draft_backup LIKE :b)',
            ['url' => $plain, 'a' => '%' . like_escape($plain) . '%', 'b' => '%' . like_escape($jsonEscaped) . '%']],
        ['SELECT COUNT(*) FROM vision_manifestations WHERE user_id = :uid AND (board_data LIKE :a OR board_data LIKE :b)',
            ['a' => '%' . like_escape($plain) . '%', 'b' => '%' . like_escape($jsonEscaped) . '%']],
    ];
    foreach ($checks as [$sql, $params]) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['uid' => $userId] + $params);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
    }

    $file = upload_root() . '/' . $relative;
    if (is_file($file)) {
        unlink($file);
    }
}

/** Alle Upload-URLs, die in einem gespeicherten Board-Zustand (JSON) vorkommen. */
function upload_urls_in_state(?string $json): array
{
    $state = $json ? json_decode($json, true) : null;
    if (!is_array($state)) {
        return [];
    }
    $urls = [$state['board']['background_image'] ?? null];
    foreach ($state['items'] ?? [] as $item) {
        $urls[] = $item['image_path'] ?? null;
    }
    return array_values(array_unique(array_filter($urls, 'is_string')));
}

function like_escape(string $value): string
{
    return addcslashes($value, '\\%_');
}

function manifestation_file(int $userId, int $id, string $extension): string
{
    return manifestation_root() . '/' . $userId . '/' . $id . '.' . $extension;
}

function store_manifestation_files(int $userId, int $id, string $jpeg, string $pdf): void
{
    $dir = manifestation_root() . '/' . $userId;
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Das Speicherverzeichnis konnte nicht angelegt werden.');
    }
    if (file_put_contents(manifestation_file($userId, $id, 'jpg'), $jpeg) === false
        || file_put_contents(manifestation_file($userId, $id, 'pdf'), $pdf) === false) {
        throw new RuntimeException('Die Vision konnte nicht gespeichert werden.');
    }
}

/** Liest Bild/PDF einer Vision – aus der Datei oder (Altbestand) aus der Datenbank. */
function manifestation_contents(PDO $pdo, int $userId, int $id, string $extension): ?string
{
    $file = manifestation_file($userId, $id, $extension);
    if (is_file($file)) {
        $data = file_get_contents($file);
        return $data === false ? null : $data;
    }
    $column = $extension === 'pdf' ? 'pdf_data' : 'image_data';
    $stmt = $pdo->prepare("SELECT $column FROM vision_manifestations WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $data = $stmt->fetchColumn();
    return is_string($data) ? $data : null;
}

function delete_manifestation_files(int $userId, int $id): void
{
    foreach (['jpg', 'pdf'] as $extension) {
        $file = manifestation_file($userId, $id, $extension);
        if (is_file($file)) {
            unlink($file);
        }
    }
}

/** Entfernt beim Löschen eines Kontos alle Dateien der Userin. */
function delete_all_user_files(int $userId): void
{
    $dirs = [manifestation_root() . '/' . $userId];
    foreach (glob(upload_root() . '/*', GLOB_ONLYDIR) ?: [] as $area) {
        $dirs[] = $area . '/' . $userId;
    }
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
}
