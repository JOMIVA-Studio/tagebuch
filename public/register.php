<?php
require __DIR__ . '/../src/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$errors = [];
$nameValue = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nameValue = trim($_POST['name'] ?? '');
    $emailValue = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($nameValue === '') {
        $errors[] = 'Bitte gib deinen Namen ein.';
    }
    if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte gib eine gueltige E-Mail-Adresse ein.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Die Passwoerter stimmen nicht ueberein.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $emailValue]);
        if ($stmt->fetch()) {
            $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password_hash, last_activity_at) VALUES (:name, :email, :hash, NOW())'
        );
        $stmt->execute([
            'name'  => $nameValue,
            'email' => $emailValue,
            'hash'  => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) db()->lastInsertId();

        login_user($userId);

        try {
            send_templated_mail('welcome', ['id' => $userId, 'name' => $nameValue, 'email' => $emailValue]);
        } catch (Throwable $e) {
            // SMTP evtl. noch nicht konfiguriert - Registrierung soll trotzdem gelingen.
        }

        flash('success', 'Konto erstellt! Lege jetzt dein erstes Ziel an, um loszulegen.');
        redirect('/goals.php?new=1');
    }
}

$pageTitle = 'Registrieren';
require __DIR__ . '/partials/header.php';
?>

<div class="stack-narrow">
  <div class="badge">Neu hier?</div>
  <h2>Konto erstellen</h2>

  <?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="field">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required value="<?= e($nameValue) ?>" autofocus>
    </div>
    <div class="field">
      <label for="email">E-Mail</label>
      <input type="email" id="email" name="email" required value="<?= e($emailValue) ?>">
    </div>
    <div class="field">
      <label for="password">Passwort</label>
      <input type="password" id="password" name="password" required minlength="8">
      <div class="hint">Mindestens 8 Zeichen.</div>
    </div>
    <div class="field mb-0">
      <label for="password_confirm">Passwort bestaetigen</label>
      <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
    </div>
    <button type="submit" class="btn btn-primary btn-block mt-md">Registrieren</button>
  </form>

  <p class="text-center mt-md small text-muted">
    Bereits registriert? <a href="/login.php">Jetzt anmelden</a>
  </p>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
