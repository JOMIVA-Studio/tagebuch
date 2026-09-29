<?php
declare(strict_types=1);

/**
 * Nimmt einen $_FILES-Eintrag entgegen, prüft ihn (echtes Bild, erlaubter
 * Typ, Grössenlimit) und speichert ihn unter einem zufälligen Dateinamen
 * im Upload-Verzeichnis des Users. Wirft RuntimeException bei Problemen.
 *
 * @return string Relativer Web-Pfad zur gespeicherten Datei (z.B. "/uploads/vision/3/ab12cd.jpg")
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

    $dir = __DIR__ . '/../public/uploads/' . $subdir . '/' . $userId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Das Upload-Verzeichnis konnte nicht angelegt werden.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
    }

    return '/uploads/' . $subdir . '/' . $userId . '/' . $filename;
}
