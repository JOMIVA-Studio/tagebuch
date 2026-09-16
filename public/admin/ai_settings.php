<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$errors = [];

$settings = $pdo->query('SELECT * FROM ai_settings WHERE id = 1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $provider = $_POST['provider'] ?? 'openai';
    $apiKey = trim($_POST['api_key'] ?? '');

    if (!in_array($provider, ['openai'], true)) {
        $errors[] = 'Unbekannter Anbieter.';
    } else {
        $keyToStore = $apiKey !== '' ? $apiKey : $settings['api_key'];
        $stmt = $pdo->prepare('UPDATE ai_settings SET provider = :provider, api_key = :api_key WHERE id = 1');
        $stmt->execute(['provider' => $provider, 'api_key' => $keyToStore]);
        flash('success', 'KI-Einstellungen gespeichert.');
        redirect('/admin/ai_settings.php');
    }
}

$pageTitle = 'KI-Bilder';
require __DIR__ . '/partials/header.php';
?>

<h2>KI-Bilder fuer das Visionboard</h2>
<p class="text-muted">
  Damit User im Visionboard Bilder per KI erzeugen koennen, braucht es einen
  API-Zugang. Aktuell unterstuetzt: OpenAI (Modell dall-e-3). Ein Key kann
  unter <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a>
  erstellt werden - die Nutzung wird dort nach Verbrauch abgerechnet.
</p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="card stack-narrow">
  <?= csrf_field() ?>
  <div class="field">
    <label for="provider">Anbieter</label>
    <select id="provider" name="provider">
      <option value="openai" selected>OpenAI (dall-e-3)</option>
    </select>
  </div>
  <div class="field mb-0">
    <label for="api_key">API-Key</label>
    <input type="password" id="api_key" name="api_key" placeholder="<?= $settings['api_key'] ? 'unveraendert lassen = leer' : 'sk-...' ?>">
    <div class="hint"><?= $settings['api_key'] ? 'Es ist bereits ein Key hinterlegt.' : 'Noch kein Key hinterlegt.' ?></div>
  </div>
  <button type="submit" class="btn btn-primary mt-md">Speichern</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
