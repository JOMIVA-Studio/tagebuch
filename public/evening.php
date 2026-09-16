<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();
$dateStr = date('Y-m-d');

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs WHERE user_id = :uid AND DATE(logged_at) = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$dayWaterTotal = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM goals WHERE user_id = :uid AND is_active = 1 ORDER BY created_at');
$stmt->execute(['uid' => $user['id']]);
$goals = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT goal_id, status FROM goal_logs WHERE goal_id IN (SELECT id FROM goals WHERE user_id = :uid) AND log_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$goalStatusToday = [];
foreach ($stmt->fetchAll() as $row) {
    $goalStatusToday[(int) $row['goal_id']] = $row['status'];
}

$stmt = $pdo->prepare('SELECT * FROM evening_entries WHERE user_id = :uid AND entry_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$evening = $stmt->fetch();
$niceMoment = $evening['nice_moment'] ?? '';
$learning = $evening['learning'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'track_water') {
        $amount = (int) ($_POST['amount_ml'] ?? 0);
        if ($amount > 0 && $amount <= 5000) {
            $stmt = $pdo->prepare('INSERT INTO water_logs (user_id, amount_ml, logged_at) VALUES (:uid, :amount, :logged)');
            $stmt->execute(['uid' => $user['id'], 'amount' => $amount, 'logged' => $dateStr . ' ' . date('H:i:s')]);
            flash('success', $amount . ' ml Wasser hinzugefuegt.');
        }
        redirect('/evening.php');
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
        flash('success', 'Ziele fuer heute gespeichert.');
        redirect('/evening.php');
    }

    if ($action === 'save') {
        $niceMoment = trim($_POST['nice_moment'] ?? '');
        $learning = trim($_POST['learning'] ?? '');

        $stmt = $pdo->prepare(
            'INSERT INTO evening_entries (user_id, entry_date, nice_moment, learning)
             VALUES (:uid, :date, :nice_moment, :learning)
             ON DUPLICATE KEY UPDATE nice_moment = VALUES(nice_moment), learning = VALUES(learning)'
        );
        $stmt->execute([
            'uid' => $user['id'], 'date' => $dateStr,
            'nice_moment' => $niceMoment, 'learning' => $learning,
        ]);
        flash('success', 'Abendroutine gespeichert.');
        redirect('/evening.php');
    }
}

$pageTitle = 'Abendroutine';
$heroTone = 'magenta';
$heroBadge = 'Routine';
$heroTitle = 'Guten Abend, ' . $user['name'] . '!';
$heroSubtitle = (new DateTimeImmutable($dateStr))->format('d.m.Y');
require __DIR__ . '/partials/header.php';
?>

<p><a href="/routines.php">&larr; Zurueck zu den Routinen</a></p>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <div class="card">
    <label for="nice_moment" style="font-weight:700; display:block; margin-bottom:12px;">Was habe ich heute Schoenes erlebt?</label>
    <textarea id="nice_moment" name="nice_moment" rows="3"><?= e($niceMoment) ?></textarea>
  </div>

  <div class="card">
    <label for="learning" style="font-weight:700; display:block; margin-bottom:12px;">Mein Learning fuer heute</label>
    <textarea id="learning" name="learning" rows="3"><?= e($learning) ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary mt-md">Abendroutine speichern</button>
</form>

<div class="card mt-md card-accent-left magenta">
  <h2>Was habe ich heute Gutes getan?</h2>

  <div class="field mb-0">
    <label class="mb-0">Wasser <span class="text-muted small">(heute: <?= $dayWaterTotal ?> ml)</span></label>
    <form method="post" class="flex gap-sm mt-sm" style="flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="track_water">
      <button type="submit" name="amount_ml" value="200" class="btn btn-ghost btn-sm">+200 ml</button>
      <button type="submit" name="amount_ml" value="300" class="btn btn-ghost btn-sm">+300 ml</button>
      <button type="submit" name="amount_ml" value="500" class="btn btn-ghost btn-sm">+500 ml</button>
    </form>
  </div>

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
        <button type="submit" class="btn btn-ghost btn-sm mt-md">Ziele fuer heute speichern</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
