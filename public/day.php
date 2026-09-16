<?php
require __DIR__ . '/../src/bootstrap.php';

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
            flash('success', $amount . ' ml Wasser hinzugefuegt.');
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
        flash('success', 'Ziele fuer diesen Tag gespeichert.');
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

    flash('success', 'Eintrag fuer den ' . $date->format('d.m.Y') . ' gespeichert.');
    redirect('/day.php?date=' . $dateStr);
}

$pageTitle = 'Tagebuch ' . $date->format('d.m.Y');
$heroTone = 'purple';
$heroBadge = 'Tagebuch';
$heroTitle = $date->format('d.m.Y');
$heroSubtitle = 'Dein Tag - morgens, abends und alles dazwischen.';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/dashboard.php">&larr; Zurueck zum Kalender</a></p>

<div class="card card-accent-left magenta">
  <h2>Tracker</h2>

  <div class="field mb-0">
    <label class="mb-0">Wasser <span class="text-muted small">(<?= $dayWaterTotal ?> ml)</span></label>
    <form method="post" class="flex gap-sm mt-sm" style="flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="track_water">
      <button type="submit" name="amount_ml" value="200" class="btn btn-ghost btn-sm">+200 ml</button>
      <button type="submit" name="amount_ml" value="300" class="btn btn-ghost btn-sm">+300 ml</button>
      <button type="submit" name="amount_ml" value="500" class="btn btn-ghost btn-sm">+500 ml</button>
    </form>
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

  <?php if ($goals): ?>
    <div class="field mb-0 mt-md">
      <label class="mb-0">Ziele</label>
      <form method="post" class="mt-sm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_goals">
        <?php foreach ($goals as $goal): $gid = (int) $goal['id']; ?>
          <label class="checkbox-row mt-sm">
            <input type="checkbox" name="done[]" value="<?= $gid ?>" <?= ($goalStatusToday[$gid] ?? '') === 'done' ? 'checked' : '' ?>>
            <?= e($goal['title']) ?>
          </label>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-ghost btn-sm mt-md">Ziele speichern</button>
      </form>
    </div>
  <?php endif; ?>

  <p class="small mt-md mb-0"><a href="/sleep.php?date=<?= e($dateStr) ?>">Schlaf fuer diese Nacht erfassen &rarr;</a></p>
</div>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_entry">

  <div class="card card-accent-top">
    <h2 class="mb-0">Morgens</h2>
    <div class="field mt-md">
      <label for="gratitude">Wofuer bin ich dankbar?</label>
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
  </div>

  <div class="card card-accent-top purple">
    <h2 class="mb-0">Abends</h2>
    <div class="field mt-md">
      <label for="nice_moment">Was habe ich heute Schoenes erlebt?</label>
      <textarea id="nice_moment" name="nice_moment" rows="2"><?= e($niceMoment) ?></textarea>
    </div>
    <div class="field mb-0">
      <label for="learning">Mein Learning fuer heute</label>
      <textarea id="learning" name="learning" rows="2"><?= e($learning) ?></textarea>
    </div>
  </div>

  <div class="card">
    <label for="free_text" style="font-weight:700; display:block; margin-bottom:12px;">Freitext</label>
    <textarea id="free_text" name="free_text" rows="5" placeholder="Was dich sonst noch beschaeftigt..."><?= e($freeText) ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary mt-md">Tagebuch speichern</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
