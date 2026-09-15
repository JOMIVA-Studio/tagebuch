<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Das aktuelle Passwort ist falsch.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        } elseif ($new !== $confirm) {
            $errors[] = 'Die neuen Passwoerter stimmen nicht ueberein.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $stmt->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);
            flash('success', 'Passwort wurde geaendert.');
            redirect('/profile.php');
        }
    }

    if ($action === 'update_gender') {
        $gender = $_POST['gender'] ?? '';
        $gender = in_array($gender, ['weiblich', 'maennlich', 'divers'], true) ? $gender : null;
        $stmt = $pdo->prepare('UPDATE users SET gender = :gender WHERE id = :id');
        $stmt->execute(['gender' => $gender, 'id' => $user['id']]);
        flash('success', 'Angabe gespeichert.');
        redirect('/profile.php');
    }

    if ($action === 'update_reminders') {
        $enabled = isset($_POST['reminder_emails_enabled']) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE users SET reminder_emails_enabled = :val WHERE id = :id');
        $stmt->execute(['val' => $enabled, 'id' => $user['id']]);
        flash('success', 'Einstellung fuer Erinnerungs-E-Mails gespeichert.');
        redirect('/profile.php');
    }

    if ($action === 'delete_account') {
        $current = (string) ($_POST['delete_password'] ?? '');
        $confirmBox = isset($_POST['confirm_delete']);

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Passwort ist falsch. Konto wurde nicht geloescht.';
        } elseif (!$confirmBox) {
            $errors[] = 'Bitte bestaetige die Loeschung ueber die Checkbox.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $user['id']]);
            logout_user();
            flash('success', 'Dein Konto und alle zugehoerigen Daten wurden geloescht.');
            redirect('/login.php');
        }
    }
}

$pageTitle = 'Profil';
$heroTone = 'green';
$heroBadge = 'Konto';
$heroTitle = 'Profil';
$heroSubtitle = $user['email'];
require __DIR__ . '/partials/header.php';
?>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card-grid">
  <div class="card">
    <h3>Passwort aendern</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="field">
        <label for="current_password">Aktuelles Passwort</label>
        <input type="password" id="current_password" name="current_password" required>
      </div>
      <div class="field">
        <label for="new_password">Neues Passwort</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
      </div>
      <div class="field mb-0">
        <label for="new_password_confirm">Neues Passwort bestaetigen</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8">
      </div>
      <button type="submit" class="btn btn-secondary mt-md">Passwort speichern</button>
    </form>
  </div>

  <div class="card">
    <h3>Geschlecht</h3>
    <p class="text-muted small">Wird nur fuer die Freischaltung des Zyklus-Trackers verwendet.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_gender">
      <div class="field mb-0">
        <label for="gender">Geschlecht</label>
        <select id="gender" name="gender">
          <option value="" <?= $user['gender'] === null ? 'selected' : '' ?>>Keine Angabe</option>
          <option value="weiblich" <?= $user['gender'] === 'weiblich' ? 'selected' : '' ?>>Weiblich</option>
          <option value="maennlich" <?= $user['gender'] === 'maennlich' ? 'selected' : '' ?>>Maennlich</option>
          <option value="divers" <?= $user['gender'] === 'divers' ? 'selected' : '' ?>>Divers</option>
        </select>
      </div>
      <button type="submit" class="btn btn-ghost mt-md">Speichern</button>
    </form>
  </div>

  <div class="card">
    <h3>Erinnerungen</h3>
    <p class="text-muted small">Wenn du eine Weile nicht aktiv warst, kann dir eine Erinnerungs-Mail geschickt werden (Vorlage und Versand werden vom Admin verwaltet).</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_reminders">
      <div class="checkbox-row field">
        <input type="checkbox" id="reminder_emails_enabled" name="reminder_emails_enabled" <?= $user['reminder_emails_enabled'] ? 'checked' : '' ?>>
        <label for="reminder_emails_enabled" style="margin:0;">Erinnerungs-E-Mails erhalten, wenn ich inaktiv war</label>
      </div>
      <button type="submit" class="btn btn-ghost">Speichern</button>
    </form>
  </div>
</div>

<div class="card mt-md" style="border-color:var(--color-danger);">
  <h3 style="color:var(--color-danger);">Konto loeschen</h3>
  <p class="text-muted small">Dies loescht dein Konto sowie alle Tagebucheintraege und Ziele unwiderruflich.</p>
  <form method="post" onsubmit="return confirm('Konto wirklich unwiderruflich loeschen?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_account">
    <div class="field">
      <label for="delete_password">Passwort zur Bestaetigung</label>
      <input type="password" id="delete_password" name="delete_password" required>
    </div>
    <div class="checkbox-row field">
      <input type="checkbox" id="confirm_delete" name="confirm_delete" required>
      <label for="confirm_delete" style="margin:0;">Ich moechte mein Konto unwiderruflich loeschen.</label>
    </div>
    <button type="submit" class="btn btn-danger">Konto endgueltig loeschen</button>
  </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
