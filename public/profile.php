<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();
$errors = [];
$stmt = $pdo->prepare('SELECT * FROM daily_routines WHERE user_id = :uid');
$stmt->execute(['uid'=>$user['id']]);
$routine = $stmt->fetch() ?: ['morning_enabled'=>0, 'morning_time'=>'08:00:00', 'evening_enabled'=>0, 'evening_time'=>'20:00:00'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'daily_routines') {
        $routine = [
            'morning_enabled'=>isset($_POST['morning_enabled']) ? 1 : 0,
            'evening_enabled'=>isset($_POST['evening_enabled']) ? 1 : 0,
            'morning_time'=>is_string($_POST['morning_time'] ?? null) ? $_POST['morning_time'] : '',
            'evening_time'=>is_string($_POST['evening_time'] ?? null) ? $_POST['evening_time'] : '',
        ];
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $routine['morning_time']) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $routine['evening_time'])) {
            $errors[] = 'Bitte gib zwei gültige Uhrzeiten ein.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO daily_routines (user_id, morning_enabled, morning_time, evening_enabled, evening_time) VALUES (:uid, :morning_enabled, :morning_time, :evening_enabled, :evening_time) ON DUPLICATE KEY UPDATE morning_enabled=VALUES(morning_enabled), morning_time=VALUES(morning_time), evening_enabled=VALUES(evening_enabled), evening_time=VALUES(evening_time)');
            $stmt->execute(['uid'=>$user['id']] + $routine);
            flash('success', 'Morgen- und Abend-Erinnerungen gespeichert.');
            redirect('/profile.php');
        }
    }

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

<div class="card mb-0" style="margin-bottom:24px;">
  <h2>Deine Morgen- und Abendroutine</h2>
  <p class="text-muted small">Erinnerungen im Tagebuch, jeweils ab deiner Uhrzeit für zwei Stunden. Zeitzone: Schweiz (Europe/Zurich).</p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="daily_routines">
    <div class="card-grid">
      <?php foreach (['morning'=>'Morgen', 'evening'=>'Abend'] as $kind=>$label): ?>
        <div>
          <div class="checkbox-row field"><input type="checkbox" id="<?= $kind ?>_enabled" name="<?= $kind ?>_enabled" <?= $routine[$kind . '_enabled'] ? 'checked' : '' ?>><label for="<?= $kind ?>_enabled"><?= $label ?>-Erinnerung aktivieren</label></div>
          <div class="field"><label for="<?= $kind ?>_time">Uhrzeit am <?= $label ?></label><input type="time" id="<?= $kind ?>_time" name="<?= $kind ?>_time" value="<?= e(substr($routine[$kind . '_time'], 0, 5)) ?>" required></div>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary">Erinnerungen speichern</button>
  </form>
</div>
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
