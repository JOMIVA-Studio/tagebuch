<?php
require __DIR__ . '/../src/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$errors = [];
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $emailValue = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (throttle_too_many('login', $emailValue)) {
        $errors[] = throttle_message();
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $emailValue]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            throttle_clear('login', $emailValue);
            login_user((int) $user['id']);
            flash('success', 'Willkommen zurück, ' . $user['name'] . '!');
            redirect('/dashboard.php');
        }

        throttle_hit('login', $emailValue);
        $errors[] = 'E-Mail oder Passwort ist falsch.';
    }
}

$pageTitle = 'Anmelden';
require __DIR__ . '/partials/header.php';
?>

<div class="stack-narrow">
  <div class="badge">Anmeldung</div>
  <h2>Willkommen zurück</h2>

  <?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">E-Mail</label>
      <input type="email" id="email" name="email" required value="<?= e($emailValue) ?>" autofocus>
    </div>
    <div class="field mb-0">
      <label for="password">Passwort</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block mt-md">Anmelden</button>
  </form>

  <p class="text-center mt-md small text-muted">
    <a href="/forgot_password.php">Passwort vergessen?</a> ·
    Noch kein Konto? <a href="/register.php">Jetzt registrieren</a>
  </p>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
