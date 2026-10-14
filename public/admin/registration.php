<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
$admin = require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $code = trim((string) ($_POST['invite_code'] ?? ''));
    if ($code !== '' && mb_strlen($code) < 6) {
        $errors[] = 'Der Einladungscode muss mindestens 6 Zeichen lang sein (oder leer, damit sich jeder registrieren kann).';
    } elseif (mb_strlen($code) > 100) {
        $errors[] = 'Der Einladungscode darf höchstens 100 Zeichen lang sein.';
    } else {
        $pdo->prepare('UPDATE app_settings SET invite_code = :code WHERE id = 1')->execute(['code' => $code !== '' ? $code : null]);
        flash('success', $code !== '' ? 'Einladungscode gespeichert. Neue Konten brauchen jetzt diesen Code.' : 'Einladungscode entfernt. Jetzt kann sich jeder registrieren.');
        redirect('/admin/registration.php');
    }
}

$currentCode = registration_invite_code();

$pageTitle = 'Registrierung';
require __DIR__ . '/partials/header.php';
?>

<h2>Registrierung</h2>
<p class="text-muted">
  Mit einem Einladungscode können sich nur Personen registrieren, die ihn von dir bekommen haben.
  Lässt du das Feld leer, kann sich jeder registrieren.
</p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card stack-narrow">
  <p class="mt-0"><strong>Aktuell:</strong> <?= $currentCode !== null ? 'Registrierung nur mit Einladungscode.' : 'Registrierung für alle offen.' ?></p>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field mb-0">
      <label for="invite_code">Einladungscode</label>
      <input type="text" id="invite_code" name="invite_code" value="<?= e($currentCode ?? '') ?>" autocomplete="off" maxlength="100">
      <small class="text-muted">Mindestens 6 Zeichen. Leer lassen = Registrierung für alle.</small>
    </div>
    <button type="submit" class="btn btn-secondary mt-md">Speichern</button>
  </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
