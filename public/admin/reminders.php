<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_once __DIR__ . '/../../src/reminders.php';
require_admin();

$pdo = db();
$runResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_threshold') {
        $days = max(1, (int) ($_POST['inactivity_threshold_days'] ?? 7));
        $stmt = $pdo->prepare('UPDATE app_settings SET inactivity_threshold_days = :days WHERE id = 1');
        $stmt->execute(['days' => $days]);
        flash('success', 'Schwellenwert gespeichert.');
        redirect('/admin/reminders.php');
    }

    if ($action === 'run_now') {
        $runResult = send_inactivity_reminders();
    }
}

$thresholdDays = (int) ($pdo->query('SELECT inactivity_threshold_days FROM app_settings WHERE id = 1')->fetchColumn() ?: 7);

$stmt = $pdo->query(
    "SELECT * FROM email_log WHERE template_key = 'inactivity_reminder' ORDER BY sent_at DESC LIMIT 25"
);
$log = $stmt->fetchAll();

$pageTitle = 'Erinnerungen';
require __DIR__ . '/partials/header.php';
?>

<h2>Inaktivitaets-Erinnerungen</h2>
<p class="text-muted">Nutzt die Vorlage <code>inactivity_reminder</code> (siehe <a href="/admin/templates.php">Mail-Vorlagen</a>) und die SMTP-Zugangsdaten aus <a href="/admin/smtp.php">SMTP-Einstellungen</a>.</p>

<div class="card-grid">
  <div class="card">
    <h3>Schwellenwert</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_threshold">
      <div class="field mb-0">
        <label for="inactivity_threshold_days">Als inaktiv gilt, wer laenger nicht aktiv war als (Tage)</label>
        <input type="number" id="inactivity_threshold_days" name="inactivity_threshold_days" min="1" value="<?= $thresholdDays ?>">
      </div>
      <button type="submit" class="btn btn-secondary mt-md">Speichern</button>
    </form>
  </div>

  <div class="card">
    <h3>Manuell versenden</h3>
    <p class="text-muted small">Fuer den Produktivbetrieb kannst du stattdessen <code>cron/send_inactivity_reminders.php</code> per Cronjob (z.B. taeglich) automatisch ausfuehren lassen.</p>
    <?php if ($runResult): ?>
      <div class="flash flash-success">
        Gesendet: <?= $runResult['sent'] ?> · Fehlgeschlagen: <?= $runResult['failed'] ?>
      </div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="run_now">
      <button type="submit" class="btn btn-primary">Jetzt pruefen &amp; versenden</button>
    </form>
  </div>
</div>

<div class="card mt-md">
  <h3>Versandprotokoll</h3>
  <?php if (empty($log)): ?>
    <p class="text-muted">Noch keine Erinnerungen versendet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Datum</th><th>Empfaenger</th><th>Status</th><th>Fehler</th></tr></thead>
        <tbody>
          <?php foreach ($log as $entry): ?>
            <tr>
              <td><?= e((new DateTimeImmutable($entry['sent_at']))->format('d.m.Y H:i')) ?></td>
              <td><?= e($entry['recipient']) ?></td>
              <td><span class="status-pill <?= $entry['status'] === 'sent' ? 'done' : 'not_done' ?>"><?= e($entry['status']) ?></span></td>
              <td class="small text-muted"><?= e($entry['error_message'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
