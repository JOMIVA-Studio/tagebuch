<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();
$dateStr = date('Y-m-d');

$stmt = $pdo->prepare('SELECT * FROM sleep_logs WHERE user_id = :uid AND sleep_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$sleep = $stmt->fetch();
$qualityLabels = [1 => 'Sehr schlecht', 2 => 'Eher schlecht', 3 => 'Mittel', 4 => 'Gut', 5 => 'Sehr gut'];

$cycleMarked = false;
if ($user['gender'] === 'weiblich') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cycle_days WHERE user_id = :uid AND day_date = :date');
    $stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
    $cycleMarked = (bool) $stmt->fetchColumn();
}

$stmt = $pdo->prepare('SELECT * FROM morning_entries WHERE user_id = :uid AND entry_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$morning = $stmt->fetch();
$gratitude = $morning['gratitude'] ?? '';
$intention = $morning['intention'] ?? '';
$affirmation = $morning['affirmation'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'toggle_cycle' && $user['gender'] === 'weiblich') {
        $marked = isset($_POST['cycle_marked']);
        $stmt = $pdo->prepare($marked
            ? 'INSERT IGNORE INTO cycle_days (user_id, day_date) VALUES (:uid, :date)'
            : 'DELETE FROM cycle_days WHERE user_id = :uid AND day_date = :date');
        $stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
        flash('success', $marked ? 'Periodentag markiert.' : 'Markierung entfernt.');
        redirect('/morning.php');
    }

    if ($action === 'save') {
        $gratitude = trim($_POST['gratitude'] ?? '');
        $intention = trim($_POST['intention'] ?? '');
        $affirmation = trim($_POST['affirmation'] ?? '');

        $stmt = $pdo->prepare(
            'INSERT INTO morning_entries (user_id, entry_date, gratitude, intention, affirmation)
             VALUES (:uid, :date, :gratitude, :intention, :affirmation)
             ON DUPLICATE KEY UPDATE gratitude = VALUES(gratitude), intention = VALUES(intention), affirmation = VALUES(affirmation)'
        );
        $stmt->execute([
            'uid' => $user['id'], 'date' => $dateStr,
            'gratitude' => $gratitude, 'intention' => $intention, 'affirmation' => $affirmation,
        ]);
        flash('success', 'Morgenroutine gespeichert.');
        redirect('/morning.php');
    }
}

$pageTitle = 'Morgenroutine';
$heroTone = 'green';
$heroBadge = 'Routine';
$heroTitle = 'Guten Morgen, ' . $user['name'] . '!';
$heroSubtitle = (new DateTimeImmutable($dateStr))->format('d.m.Y');
require __DIR__ . '/partials/header.php';
?>

<p><a href="/routines.php">&larr; Zurueck zu den Routinen</a></p>

<div class="card card-accent-left">
  <h2>Deine Tracker</h2>

  <div class="field mb-0">
    <label class="mb-0">Schlaf</label>
    <?php if ($sleep): ?>
      <p class="small text-muted">
        <?= intdiv((int) $sleep['duration_minutes'], 60) ?> Std. <?= (int) $sleep['duration_minutes'] % 60 ?> Min. ·
        <?= e($qualityLabels[(int) $sleep['quality']]) ?>
      </p>
    <?php else: ?>
      <p class="small text-muted">Noch nicht erfasst.</p>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="/sleep.php?date=<?= e($dateStr) ?>"><?= $sleep ? 'Bearbeiten' : 'Schlaf erfassen' ?></a>
  </div>

  <?php if ($user['gender'] === 'weiblich'): ?>
    <form method="post" class="field mb-0 mt-md">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_cycle">
      <label class="checkbox-row">
        <input type="checkbox" name="cycle_marked" value="1" <?= $cycleMarked ? 'checked' : '' ?> onchange="this.form.submit()">
        Periodentag
      </label>
    </form>
  <?php endif; ?>
</div>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <div class="card">
    <label for="gratitude" style="font-weight:700; display:block; margin-bottom:12px;">Wofuer bin ich dankbar?</label>
    <textarea id="gratitude" name="gratitude" rows="3"><?= e($gratitude) ?></textarea>
  </div>

  <div class="card">
    <label for="intention" style="font-weight:700; display:block; margin-bottom:12px;">Was nehme ich mir heute vor?</label>
    <textarea id="intention" name="intention" rows="3"><?= e($intention) ?></textarea>
  </div>

  <div class="card">
    <label for="affirmation" style="font-weight:700; display:block; margin-bottom:12px;">Meine Affirmation fuer heute</label>
    <textarea id="affirmation" name="affirmation" rows="2"><?= e($affirmation) ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary mt-md">Morgenroutine speichern</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
