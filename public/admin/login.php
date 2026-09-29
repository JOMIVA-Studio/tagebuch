<?php
require __DIR__ . '/../../src/admin_bootstrap.php';

if (current_admin()) {
    redirect('/admin/index.php');
}

$errors = [];
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $usernameValue = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM admins WHERE username = :u');
    $stmt->execute(['u' => $usernameValue]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        login_admin((int) $admin['id']);
        redirect('/admin/index.php');
    }

    $errors[] = 'Benutzername oder Passwort ist falsch.';
}
?><!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin-Anmeldung</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body style="background:var(--color-dark); min-height:100vh; display:flex; align-items:center;">
  <div class="container">
    <div class="stack-narrow card">
      <div class="text-center">
        <img class="brand-logo brand-logo-lg" style="margin: 0 auto 14px;" src="/assets/img/logo-black.svg" alt="Logo">
      </div>
      <h2 class="text-center">Administration</h2>
      <p class="text-muted text-center small">Separater, session-unabhängiger Zugang.</p>

      <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
      <?php endforeach; ?>

      <form method="post">
        <?= csrf_field() ?>
        <div class="field">
          <label for="username">Benutzername</label>
          <input type="text" id="username" name="username" required value="<?= e($usernameValue) ?>" autofocus>
        </div>
        <div class="field mb-0">
          <label for="password">Passwort</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-secondary btn-block mt-md">Anmelden</button>
      </form>
    </div>
  </div>
</body>
</html>
