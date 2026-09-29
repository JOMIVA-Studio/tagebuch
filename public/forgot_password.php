<?php
require __DIR__ . '/../src/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$errors = [];
$sent = false;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $emailValue = trim($_POST['email'] ?? '');

    if (throttle_too_many('password_reset', $emailValue)) {
        $errors[] = throttle_message();
    } else {
        throttle_hit('password_reset', $emailValue);

        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $emailValue]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $stmt = db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
            );
            $stmt->execute(['uid' => $user['id'], 'hash' => hash('sha256', $token)]);

            try {
                send_templated_mail('password_reset', $user, ['link' => app_url('/reset_password.php?token=' . $token)]);
            } catch (Throwable $e) {
                error_log('Passwort-Reset-Mail fehlgeschlagen: ' . $e->getMessage());
            }
        }

        // Immer dieselbe Antwort, damit niemand herausfinden kann, welche Adressen registriert sind.
        $sent = true;
    }
}

$pageTitle = 'Passwort vergessen';
require __DIR__ . '/partials/header.php';
?>

<div class="stack-narrow">
  <div class="badge">Passwort vergessen</div>
  <h2>Neues Passwort anfordern</h2>

  <?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <?php if ($sent): ?>
    <div class="flash flash-success">Falls diese E-Mail-Adresse bei uns registriert ist, haben wir dir einen Link zum Zurücksetzen geschickt. Er ist 60 Minuten gültig.</div>
  <?php else: ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <p class="small text-muted">Gib die E-Mail-Adresse deines Kontos ein. Wir schicken dir einen Link, mit dem du ein neues Passwort vergeben kannst.</p>
      <div class="field mb-0">
        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" required value="<?= e($emailValue) ?>" autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-block mt-md">Link anfordern</button>
    </form>
  <?php endif; ?>

  <p class="text-center mt-md small text-muted"><a href="/login.php">Zurück zur Anmeldung</a></p>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
