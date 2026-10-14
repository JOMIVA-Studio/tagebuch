<?php
declare(strict_types=1);
// Legt einen Admin-Zugang an oder setzt dessen Passwort neu.
// Aufruf: php bin/create-admin.php [benutzername]
// Ohne Passwort-Eingabe wird ein zufälliges Passwort erzeugt und einmalig angezeigt.
if (PHP_SAPI !== 'cli') { exit(1); }

require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/db.php';

/** Eingabe lesen – auch auf Servern ohne readline-Erweiterung. */
function ask(string $prompt): string
{
    if (function_exists('readline')) {
        return trim((string) readline($prompt));
    }
    echo $prompt;
    return trim((string) fgets(STDIN));
}

$username = $argv[1] ?? ask('Benutzername [admin]: ');
$username = $username !== '' ? $username : 'admin';

$password = ask('Passwort (leer lassen = zufällig erzeugen): ');
$generated = false;
if ($password === '') {
    $password = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
    $generated = true;
} elseif (strlen($password) < 12) {
    fwrite(STDERR, "Das Passwort muss mindestens 12 Zeichen lang sein.\n");
    exit(1);
}

$pdo = db();
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :u');
$stmt->execute(['u' => $username]);
$id = $stmt->fetchColumn();

if ($id) {
    $pdo->prepare('UPDATE admins SET password_hash = :h, must_change_password = :m WHERE id = :id')->execute(['h' => $hash, 'm' => $generated ? 1 : 0, 'id' => $id]);
    echo "Passwort für '$username' wurde neu gesetzt.\n";
} else {
    $pdo->prepare('INSERT INTO admins (username, password_hash, must_change_password) VALUES (:u, :h, :m)')->execute(['u' => $username, 'h' => $hash, 'm' => $generated ? 1 : 0]);
    echo "Admin '$username' wurde angelegt.\n";
}

if ($generated) {
    echo "Passwort: $password\n(Bitte sicher notieren, es wird nicht erneut angezeigt. Beim ersten Login musst du es ändern.)\n";
}
