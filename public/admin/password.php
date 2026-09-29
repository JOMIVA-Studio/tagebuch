<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
$admin = require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!password_verify($current, $admin['password_hash'])) {
        $errors[] = 'Das aktuelle Passwort ist falsch.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($new !== $confirm) {
        $errors[] = 'Die neuen Passwörter stimmen nicht überein.';
    } else {
        $stmt = $pdo->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $admin['id']]);
        flash('success', 'Admin-Passwort wurde geändert.');
        redirect('/admin/password.php');
    }
}

$pageTitle = 'Admin-Passwort';
require __DIR__ . '/partials/header.php';
?>

<h2>Passwort ändern</h2>
<p class="text-muted">Angemeldet als <strong><?= e($admin['username']) ?></strong>.</p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="card stack-narrow">
  <?= csrf_field() ?>
  <div class="field">
    <label for="current_password">Aktuelles Passwort</label>
    <input type="password" id="current_password" name="current_password" required>
  </div>
  <div class="field">
    <label for="new_password">Neues Passwort</label>
    <input type="password" id="new_password" name="new_password" required minlength="8">
  </div>
  <div class="field mb-0">
    <label for="new_password_confirm">Neues Passwort bestätigen</label>
    <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8">
  </div>
  <button type="submit" class="btn btn-secondary mt-md">Speichern</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
