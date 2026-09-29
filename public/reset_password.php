<?php
require __DIR__ . '/../src/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];

$findReset = static function (string $token): array|false {
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return false;
    }
    $stmt = db()->prepare('SELECT * FROM password_resets WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()');
    $stmt->execute(['hash' => hash('sha256', $token)]);
    return $stmt->fetch();
};

$reset = $findReset($token);

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    } else {
        $pdo = db();
        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $reset['user_id']]);
        // Alle offenen Links dieses Kontos entwerten
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
            ->execute(['uid' => $reset['user_id']]);

        logout_user();
        flash('success', 'Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.');
        redirect('/login.php');
    }
}

$pageTitle = 'Neues Passwort';
require __DIR__ . '/partials/header.php';
?>

<div class="stack-narrow">
  <div class="badge">Passwort zurücksetzen</div>
  <h2>Neues Passwort vergeben</h2>

  <?php if (!$reset): ?>
    <div class="flash flash-error">Dieser Link ist ungültig oder abgelaufen.</div>
    <p class="text-center"><a href="/forgot_password.php">Neuen Link anfordern</a></p>
  <?php else: ?>
    <?php foreach ($errors as $error): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field">
        <label for="password">Neues Passwort</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
        <div class="hint">Mindestens 8 Zeichen.</div>
      </div>
      <div class="field mb-0">
        <label for="password_confirm">Passwort bestätigen</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary btn-block mt-md">Passwort speichern</button>
    </form>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
