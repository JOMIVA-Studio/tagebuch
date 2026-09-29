<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/sleep.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$dateParam = $_GET['date'] ?? date('Y-m-d');
$date = is_string($dateParam) ? DateTimeImmutable::createFromFormat('!Y-m-d', $dateParam) : false;
if ($date === false || $date->format('Y-m-d') !== $dateParam) {
    $date = new DateTimeImmutable('today');
}
$dateStr = $date->format('Y-m-d');

$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM diary_entries WHERE user_id = :uid AND entry_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$entry = $stmt->fetch();

$freeText = $entry['free_text'] ?? '';
$gratitude = $entry['gratitude'] ?? '';
$intention = $entry['intention'] ?? '';
$affirmation = $entry['affirmation'] ?? '';
$niceMoment = $entry['nice_moment'] ?? '';
$learning = $entry['learning'] ?? '';

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs WHERE user_id = :uid AND DATE(logged_at) = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$dayWaterTotal = (int) $stmt->fetchColumn();

$cycleMarked = false;
if ($user['gender'] === 'weiblich') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cycle_days WHERE user_id = :uid AND day_date = :date');
    $stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
    $cycleMarked = (bool) $stmt->fetchColumn();
}

$stmt = $pdo->prepare('SELECT duration_minutes, quality, under_five_hours FROM sleep_logs WHERE user_id = :uid AND sleep_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$sleepLog = $stmt->fetch();
$sleepMinutes = $sleepLog['duration_minutes'] ?? null;
$sleepQuality = $sleepLog['quality'] ?? null;
$qualityLabels = [1 => 'Schlecht', 2 => 'Eher schlecht', 3 => 'Mittel', 4 => 'Gut', 5 => 'Sehr gut'];

$stmt = $pdo->prepare('SELECT * FROM goals WHERE user_id = :uid AND is_active = 1 ORDER BY created_at');
$stmt->execute(['uid' => $user['id']]);
$goals = $stmt->fetchAll();

$goalStatusToday = [];
if ($goals) {
    $stmt = $pdo->prepare('SELECT goal_id, status FROM goal_logs WHERE goal_id IN (SELECT id FROM goals WHERE user_id = :uid) AND log_date = :date');
    $stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
    foreach ($stmt->fetchAll() as $row) {
        $goalStatusToday[(int) $row['goal_id']] = $row['status'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save_entry';

    if ($action === 'track_water') {
        $amount = (int) ($_POST['amount_ml'] ?? 0);
        if ($amount > 0 && $amount <= 5000) {
            $stmt = $pdo->prepare('INSERT INTO water_logs (user_id, amount_ml, logged_at) VALUES (:uid, :amount, :logged)');
            $stmt->execute(['uid' => $user['id'], 'amount' => $amount, 'logged' => $dateStr . ' ' . date('H:i:s')]);
            flash('success', $amount . ' ml Wasser hinzugefügt.');
        }
        redirect('/day.php?date=' . $dateStr);
    }

    if ($action === 'track_sleep_duration') {
        $underFive = ($_POST['minutes'] ?? '') === 'under5';
        $minutes = $underFive ? null : (int) ($_POST['minutes'] ?? 0);
        if ($underFive || ($minutes > 0 && $minutes <= 1440)) {
            $quality = $sleepQuality !== null ? (int) $sleepQuality : 3;
            $stmt = $pdo->prepare(
                'INSERT INTO sleep_logs (user_id, sleep_date, duration_minutes, quality, under_five_hours) VALUES (:uid, :date, :minutes, :quality, :under_five)
                 ON DUPLICATE KEY UPDATE duration_minutes = VALUES(duration_minutes), under_five_hours = VALUES(under_five_hours)'
            );
            $stmt->execute(['uid' => $user['id'], 'date' => $dateStr, 'minutes' => $minutes, 'quality' => $quality, 'under_five' => (int) $underFive]);
            flash('success', 'Schlafdauer gespeichert.');
        }
        redirect('/day.php?date=' . $dateStr);
    }

    if ($action === 'track_sleep_quality') {
        $quality = (int) ($_POST['quality'] ?? 0);
        if ($quality >= 1 && $quality <= 5) {
            $minutes = $sleepMinutes !== null ? (int) $sleepMinutes : 480;
            $stmt = $pdo->prepare(
                'INSERT INTO sleep_logs (user_id, sleep_date, duration_minutes, quality) VALUES (:uid, :date, :minutes, :quality)
                 ON DUPLICATE KEY UPDATE quality = VALUES(quality)'
            );
            $stmt->execute(['uid' => $user['id'], 'date' => $dateStr, 'minutes' => $minutes, 'quality' => $quality]);
            flash('success', 'Schlafqualität gespeichert.');
        }
        redirect('/day.php?date=' . $dateStr);
    }

    if ($action === 'toggle_cycle' && $user['gender'] === 'weiblich') {
        $marked = isset($_POST['cycle_marked']);
        $stmt = $pdo->prepare($marked
            ? 'INSERT IGNORE INTO cycle_days (user_id, day_date) VALUES (:uid, :date)'
            : 'DELETE FROM cycle_days WHERE user_id = :uid AND day_date = :date');
        $stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
        flash('success', $marked ? 'Periodentag markiert.' : 'Markierung entfernt.');
        redirect('/day.php?date=' . $dateStr);
    }

    if ($action === 'save_goals') {
        $checked = array_flip((array) ($_POST['done'] ?? []));
        $stmt = $pdo->prepare(
            'INSERT INTO goal_logs (goal_id, log_date, status) VALUES (:gid, :date, :status)
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        );
        foreach ($goals as $goal) {
            $gid = (int) $goal['id'];
            $status = isset($checked[$gid]) ? 'done' : 'not_done';
            $stmt->execute(['gid' => $gid, 'date' => $dateStr, 'status' => $status]);
        }
        flash('success', 'Ziele für diesen Tag gespeichert.');
        redirect('/day.php?date=' . $dateStr);
    }

    $freeText = trim($_POST['free_text'] ?? '');
    $gratitude = trim($_POST['gratitude'] ?? '');
    $intention = trim($_POST['intention'] ?? '');
    $affirmation = trim($_POST['affirmation'] ?? '');
    $niceMoment = trim($_POST['nice_moment'] ?? '');
    $learning = trim($_POST['learning'] ?? '');

    $fields = [
        'free_text' => $freeText, 'gratitude' => $gratitude, 'intention' => $intention,
        'affirmation' => $affirmation, 'nice_moment' => $niceMoment, 'learning' => $learning,
    ];

    if ($entry) {
        $stmt = $pdo->prepare(
            'UPDATE diary_entries SET free_text = :free_text, gratitude = :gratitude, intention = :intention,
                affirmation = :affirmation, nice_moment = :nice_moment, learning = :learning
             WHERE id = :id'
        );
        $stmt->execute($fields + ['id' => $entry['id']]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO diary_entries (user_id, entry_date, free_text, gratitude, intention, affirmation, nice_moment, learning)
             VALUES (:uid, :date, :free_text, :gratitude, :intention, :affirmation, :nice_moment, :learning)'
        );
        $stmt->execute($fields + ['uid' => $user['id'], 'date' => $dateStr]);
    }

    flash('success', 'Eintrag für den ' . $date->format('d.m.Y') . ' gespeichert.');
    redirect('/day.php?date=' . $dateStr);
}

$pageTitle = 'Tagebuch ' . $date->format('d.m.Y');
$heroTone = 'green';
$heroBadge = 'Tagebuch';
$dayNames = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
$heroTitle = $dayNames[(int) $date->format('w')] . ', ' . $date->format('d.m.Y');
$heroSubtitle = 'Ein kleiner Moment für dich.';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/diary.php?y=<?= $date->format('Y') ?>&amp;m=<?= $date->format('n') ?>">&larr; Zurück zum Kalender</a></p>

<div class="day-layout">
<form method="post" id="dayEntryForm" class="day-entry-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_entry">

  <div class="day-writing-grid"><section class="card day-morning">
    <h2 class="mb-0"><span aria-hidden="true">☀️</span> Mein Morgen</h2>
    <div class="field mt-md">
      <label for="gratitude">Wofür bin ich dankbar?</label>
      <textarea id="gratitude" name="gratitude" rows="2"><?= e($gratitude) ?></textarea>
    </div>
    <div class="field">
      <label for="intention">Was nehme ich mir heute vor?</label>
      <textarea id="intention" name="intention" rows="2"><?= e($intention) ?></textarea>
    </div>
    <div class="field mb-0">
      <label for="affirmation">Meine Affirmation</label>
      <textarea id="affirmation" name="affirmation" rows="2"><?= e($affirmation) ?></textarea>
    </div>
  </section>

  <section class="card day-evening">
    <h2 class="mb-0"><span aria-hidden="true">🌙</span> Mein Abend</h2>
    <div class="field mt-md">
      <label for="nice_moment">Was habe ich heute Schönes erlebt?</label>
      <textarea id="nice_moment" name="nice_moment" rows="2"><?= e($niceMoment) ?></textarea>
    </div>
    <div class="field mb-0">
      <label for="learning">Was nehme ich aus diesem Tag mit?</label>
      <textarea id="learning" name="learning" rows="2"><?= e($learning) ?></textarea>
    </div>
  </section></div>

  <details class="card day-notes" <?= $freeText !== '' ? 'open' : '' ?>>
    <summary>Weitere Gedanken</summary><label for="free_text" class="small text-muted">Was dir noch wichtig ist</label>
    <textarea id="free_text" name="free_text" rows="5" placeholder="Was dich sonst noch beschäftigt..."><?= e($freeText) ?></textarea>
  </details>

  <div class="day-save"><button type="submit" class="btn btn-primary">Eintrag speichern</button></div>
</form>

<aside class="day-trackers" id="dayTrackers" aria-label="Tracker für diesen Tag"><div class="card">
  <h2>Meine Tracker</h2><p class="small text-muted">Für diesen Tag · automatisch gespeichert</p><div role="status" class="day-tracker-status" aria-live="polite"></div>

  <div class="day-tracker-block">
    <div class="day-tracker-heading"><strong>Wasser</strong><span><?= $dayWaterTotal ?> ml</span></div>
    <form method="post" class="day-water-actions">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="track_water">
      <button type="submit" name="amount_ml" value="200" class="btn btn-ghost btn-sm">+200 ml</button>
      <button type="submit" name="amount_ml" value="300" class="btn btn-ghost btn-sm">+300 ml</button>
      <button type="submit" name="amount_ml" value="500" class="btn btn-ghost btn-sm">+500 ml</button>
    </form>
  </div>

  <?php if ($user['gender'] === 'weiblich'): ?>
    <form method="post" class="day-tracker-block"><div class="day-tracker-heading"><strong>Zyklus</strong></div>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_cycle">
      <label class="checkbox-row">
        <input type="checkbox" name="cycle_marked" value="1" <?= $cycleMarked ? 'checked' : '' ?> onchange="this.form.requestSubmit()">
        <span class="day-cycle-drop" aria-hidden="true"><?= icon('droplet') ?></span> Periodentag
      </label>
    </form>
  <?php endif; ?>

<section class="day-tracker-block"><div class="day-tracker-heading"><strong>Schlaf</strong></div>
    <form method="post" class="day-sleep-field">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="track_sleep_duration"><label for="daySleepDuration">Dauer</label><select id="daySleepDuration" name="minutes" onchange="this.form.requestSubmit()"><option value="" disabled <?= !$sleepLog ? 'selected' : '' ?>>Auswählen</option>
      <?php foreach (['under5' => 'Weniger als 5 Std.', 300 => '5 Std.', 330 => '5.5 Std.', 360 => '6 Std.', 390 => '6.5 Std.', 420 => '7 Std.', 450 => '7.5 Std.', 480 => '8 Std.', 510 => '8.5 Std.', 540 => '9 Std.'] as $minutes => $label): ?>
        <option value="<?= $minutes ?>" <?= ($minutes === 'under5' ? !empty($sleepLog['under_five_hours']) : ($sleepMinutes !== null && (int) $sleepMinutes === $minutes)) ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?></select>
    </form>
    <form method="post" class="day-sleep-field">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="track_sleep_quality"><label for="daySleepQuality">Erholung</label><select id="daySleepQuality" name="quality" onchange="this.form.requestSubmit()"><option value="" disabled <?= $sleepQuality === null ? 'selected' : '' ?>>Auswählen</option>
      <?php foreach ($qualityLabels as $value => $label): ?>
        <option value="<?= $value ?>" <?= (int) $sleepQuality === $value ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?></select>
    </form>
    <p class="small mt-sm mb-0"><a href="/sleep.php?date=<?= e($dateStr) ?>">Verlauf ansehen &rarr;</a></p>
  </section>



</div>
  <?php if ($goals): ?>
  <section class="card day-goals-card" aria-label="Meine Ziele für diesen Tag">
    <div class="day-goals-heading"><h2>Meine Ziele</h2><span><?= count(array_filter($goals, fn($goal) => ($goalStatusToday[(int)$goal['id']] ?? '') === 'done')) ?> / <?= count($goals) ?></span></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_goals">
      <div class="day-goals-list">
      <?php foreach ($goals as $goal): $gid = (int) $goal['id']; ?>
        <label class="day-goal-option">
          <input type="checkbox" name="done[]" onchange="this.form.requestSubmit()" value="<?= $gid ?>" <?= ($goalStatusToday[$gid] ?? '') === 'done' ? 'checked' : '' ?>>
          <span><?= e($goal['title']) ?></span>
        </label>
      <?php endforeach; ?>
      </div>

    </form>
  </section>
  <?php endif; ?>
</aside>

</div>
<script>
// Tracker actions update their own panel, keeping unsaved diary text intact.
document.addEventListener('submit', async function(event) {
  const form = event.target;
  if (!form.closest('#dayTrackers')) return;
  event.preventDefault();
  const panel = document.getElementById('dayTrackers');
  if (panel.dataset.busy) return;
  panel.dataset.busy = '1';
  const data = new FormData(form);
  if (event.submitter && event.submitter.name) data.set(event.submitter.name, event.submitter.value);
  const status = panel.querySelector('.day-tracker-status');
  const openDetails = Array.from(panel.querySelectorAll('details')).map(el => el.open);
  status.textContent = 'Wird gespeichert …';
  try {
    const response = await fetch(location.href, {method:'POST', body:data});
    if (!response.ok) throw new Error();
    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
    const updated = doc.getElementById('dayTrackers');
    if (!updated) throw new Error();
    updated.querySelectorAll('details').forEach((el,i) => el.open = openDetails[i]);
    updated.querySelector('.day-tracker-status').textContent = 'Gespeichert ✓';
    panel.replaceWith(updated);
  } catch (error) {
    status.textContent = 'Speichern konnte nicht bestätigt werden. Bitte prüfe den Tracker, bevor du erneut hinzufügst.';
    delete panel.dataset.busy;
  }
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
