<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$errors = [];
$testResult = null;

$settings = smtp_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $host = trim($_POST['host'] ?? '');
        $port = (int) ($_POST['port'] ?? 587);
        $encryption = $_POST['encryption'] ?? 'tls';
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $fromEmail = trim($_POST['from_email'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');

        if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
            $encryption = 'tls';
        }
        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Absender-E-Mail ist ungültig.';
        }

        if (!$errors) {
            // Passwort nur überschreiben, wenn ein neues eingegeben wurde.
            $passwordToStore = app_encrypt($password !== '' ? $password : $settings['password']);

            $stmt = $pdo->prepare(
                'UPDATE smtp_settings SET host=:host, port=:port, encryption=:enc, username=:user, password=:pass, from_email=:from_email, from_name=:from_name WHERE id = 1'
            );
            $stmt->execute([
                'host' => $host, 'port' => $port, 'enc' => $encryption, 'user' => $username,
                'pass' => $passwordToStore, 'from_email' => $fromEmail, 'from_name' => $fromName,
            ]);
            flash('success', 'SMTP-Einstellungen gespeichert.');
            redirect('/admin/smtp.php');
        }

        $settings = array_merge($settings, [
            'host' => $host, 'port' => $port, 'encryption' => $encryption,
            'username' => $username, 'from_email' => $fromEmail, 'from_name' => $fromName,
        ]);
    }

    if ($action === 'test') {
        $testEmail = trim($_POST['test_email'] ?? '');
        try {
            $mailer = SmtpMailer::fromSettings($settings);
            $mailer->send($testEmail, $testEmail, 'Test-E-Mail', '<p>Dies ist eine Test-E-Mail aus der Administration.</p>');
            $testResult = ['type' => 'success', 'message' => 'Test-E-Mail wurde gesendet.'];
        } catch (Throwable $e) {
            $testResult = ['type' => 'error', 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
}

$pageTitle = 'SMTP-Einstellungen';
require __DIR__ . '/partials/header.php';
?>

<h2>SMTP-Einstellungen</h2>
<p class="text-muted">Zugangsdaten für den Versand aller System-E-Mails (Willkommens-Mail, Erinnerungen etc.).</p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card-grid">
  <div class="card">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="field">
        <label for="host">SMTP-Host</label>
        <input type="text" id="host" name="host" value="<?= e($settings['host'] ?? '') ?>" placeholder="smtp.example.com">
      </div>
      <div class="field flex gap-md" style="flex-wrap:wrap;">
        <div style="flex:1; min-width:120px;">
          <label for="port">Port</label>
          <input type="number" id="port" name="port" value="<?= (int) ($settings['port'] ?? 587) ?>">
        </div>
        <div style="flex:1; min-width:150px;">
          <label for="encryption">Verschlüsselung</label>
          <select id="encryption" name="encryption">
            <option value="none" <?= ($settings['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Keine</option>
            <option value="ssl" <?= ($settings['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
            <option value="tls" <?= ($settings['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="username">Benutzername</label>
        <input type="text" id="username" name="username" value="<?= e($settings['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" placeholder="unverändert lassen = leer">
      </div>
      <div class="field">
        <label for="from_email">Absender-E-Mail</label>
        <input type="email" id="from_email" name="from_email" value="<?= e($settings['from_email'] ?? '') ?>">
      </div>
      <div class="field mb-0">
        <label for="from_name">Absender-Name</label>
        <input type="text" id="from_name" name="from_name" value="<?= e($settings['from_name'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary mt-md">Speichern</button>
    </form>
  </div>

  <div class="card">
    <h3>Testmail senden</h3>
    <?php if ($testResult): ?>
      <div class="flash flash-<?= $testResult['type'] === 'success' ? 'success' : 'error' ?>"><?= e($testResult['message']) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <div class="field mb-0">
        <label for="test_email">Ziel-E-Mail</label>
        <input type="email" id="test_email" name="test_email" required>
      </div>
      <button type="submit" class="btn btn-secondary mt-md">Testmail senden</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
