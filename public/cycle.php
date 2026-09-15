<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

if ($user['gender'] !== 'weiblich') {
    flash('error', 'Der Zyklus-Tracker ist nur verfuegbar, wenn im Profil "weiblich" als Geschlecht hinterlegt ist.');
    redirect('/tracker.php');
}

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $startDate = $_POST['start_date'] ?? '';
        $note = trim($_POST['note'] ?? '');
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);

        if ($parsed === false) {
            $errors[] = 'Bitte gib ein gueltiges Datum ein.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO cycle_entries (user_id, start_date, note) VALUES (:uid, :date, :note)'
            );
            $stmt->execute([
                'uid'  => $user['id'],
                'date' => $parsed->format('Y-m-d'),
                'note' => $note !== '' ? $note : null,
            ]);
            flash('success', 'Zyklusstart gespeichert.');
            redirect('/cycle.php');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM cycle_entries WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $user['id']]);
        flash('success', 'Eintrag geloescht.');
        redirect('/cycle.php');
    }
}

$stmt = $pdo->prepare('SELECT * FROM cycle_entries WHERE user_id = :uid ORDER BY start_date ASC');
$stmt->execute(['uid' => $user['id']]);
$entriesAsc = $stmt->fetchAll();
$entriesDesc = array_reverse($entriesAsc);

$avgCycleLength = null;
$currentCycleDay = null;
$predictedNext = null;

if (count($entriesAsc) >= 2) {
    $diffs = [];
    for ($i = 1; $i < count($entriesAsc); $i++) {
        $prev = new DateTimeImmutable($entriesAsc[$i - 1]['start_date']);
        $curr = new DateTimeImmutable($entriesAsc[$i]['start_date']);
        $diffs[] = (int) $curr->diff($prev)->days;
    }
    $avgCycleLength = (int) round(array_sum($diffs) / count($diffs));
}

if ($entriesAsc) {
    $lastStart = new DateTimeImmutable(end($entriesAsc)['start_date']);
    $today = new DateTimeImmutable('today');
    $currentCycleDay = (int) $today->diff($lastStart)->days + 1;
    if ($avgCycleLength !== null) {
        $predictedNext = $lastStart->modify('+' . $avgCycleLength . ' days');
    }
}

$pageTitle = 'Zyklus-Tracker';
$heroTone = 'magenta';
$heroBadge = 'Tracker';
$heroTitle = 'Zyklus';
$heroSubtitle = 'Verfolge deinen Zyklus und behalte Vorhersagen im Blick.';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/tracker.php">&larr; Zurueck zu allen Trackern</a></p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card-grid">
  <div class="card text-center">
    <div class="text-muted small">Aktueller Zyklustag</div>
    <h2 class="mt-0"><?= $currentCycleDay ?? '–' ?></h2>
  </div>
  <div class="card text-center">
    <div class="text-muted small">Durchschnittliche Zykluslaenge</div>
    <h2 class="mt-0"><?= $avgCycleLength !== null ? $avgCycleLength . ' Tage' : '–' ?></h2>
  </div>
  <div class="card text-center">
    <div class="text-muted small">Naechster Zyklusstart (Schaetzung)</div>
    <h2 class="mt-0"><?= $predictedNext !== null ? e($predictedNext->format('d.m.Y')) : '–' ?></h2>
  </div>
</div>

<div class="card mt-md">
  <h3>Zyklusstart erfassen</h3>
  <form method="post" class="flex gap-md" style="flex-wrap:wrap; align-items:flex-end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field mb-0" style="flex:1; min-width:160px;">
      <label for="start_date">Datum</label>
      <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="field mb-0" style="flex:2; min-width:200px;">
      <label for="note">Notiz (optional)</label>
      <input type="text" id="note" name="note">
    </div>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </form>
</div>

<div class="card mt-md">
  <h3>Verlauf</h3>
  <?php if (!$entriesDesc): ?>
    <p class="text-muted small">Noch keine Eintraege.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Datum</th><th>Notiz</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($entriesDesc as $entry): $fid = 'cd' . (int) $entry['id']; ?>
            <tr>
              <td><?= e((new DateTimeImmutable($entry['start_date']))->format('d.m.Y')) ?></td>
              <td><?= e($entry['note'] ?? '') ?></td>
              <td><button form="<?= $fid ?>" type="submit" class="btn btn-danger btn-sm">Loeschen</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php foreach ($entriesDesc as $entry): ?>
  <form id="cd<?= (int) $entry['id'] ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
  </form>
<?php endforeach; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
