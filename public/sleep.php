<?php
require __DIR__ . '/../src/bootstrap.php';
$user = require_login();
touch_user_activity((int) $user['id']);
$pdo = db();

$dateStr = is_string($_GET['date'] ?? null) ? $_GET['date'] : date('Y-m-d');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
if (!$date || $date->format('Y-m-d') !== $dateStr) {
    $dateStr = date('Y-m-d');
}

$qualityLabels = [1 => 'Schlecht', 2 => 'Eher schlecht', 3 => 'Mittel', 4 => 'Gut', 5 => 'Sehr gut'];
$durationOptions = [330 => '5.5 Std.', 360 => '6 Std.', 390 => '6.5 Std.', 420 => '7 Std.', 450 => '7.5 Std.', 480 => '8 Std.', 510 => '8.5 Std.', 540 => '9 Std.'];

$stmt = $pdo->prepare('SELECT * FROM sleep_logs WHERE user_id = :uid AND sleep_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$sleep = $stmt->fetch();
$currentMinutes = $sleep['duration_minutes'] ?? null;
$currentQuality = $sleep['quality'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'set_duration') {
        $minutes = (int) ($_POST['minutes'] ?? 0);
        if ($minutes > 0 && $minutes <= 1440) {
            $quality = $currentQuality !== null ? (int) $currentQuality : 3;
            $stmt = $pdo->prepare(
                'INSERT INTO sleep_logs (user_id, sleep_date, duration_minutes, quality) VALUES (:uid, :date, :minutes, :quality)
                 ON DUPLICATE KEY UPDATE duration_minutes = VALUES(duration_minutes)'
            );
            $stmt->execute(['uid' => $user['id'], 'date' => $dateStr, 'minutes' => $minutes, 'quality' => $quality]);
            flash('success', 'Schlafdauer gespeichert.');
        }
        redirect('/sleep.php?date=' . $dateStr);
    }

    if ($action === 'set_quality') {
        $quality = (int) ($_POST['quality'] ?? 0);
        if ($quality >= 1 && $quality <= 5) {
            $minutes = $currentMinutes !== null ? (int) $currentMinutes : 480;
            $stmt = $pdo->prepare(
                'INSERT INTO sleep_logs (user_id, sleep_date, duration_minutes, quality) VALUES (:uid, :date, :minutes, :quality)
                 ON DUPLICATE KEY UPDATE quality = VALUES(quality)'
            );
            $stmt->execute(['uid' => $user['id'], 'date' => $dateStr, 'minutes' => $minutes, 'quality' => $quality]);
            flash('success', 'Schlafqualität gespeichert.');
        }
        redirect('/sleep.php?date=' . $dateStr);
    }
}

$stmt = $pdo->prepare('SELECT * FROM sleep_logs WHERE user_id = :uid ORDER BY sleep_date DESC LIMIT 14');
$stmt->execute(['uid' => $user['id']]);
$history = $stmt->fetchAll();

$pageTitle = 'Schlaf-Tracker';
$heroTone = 'magenta';
$heroBadge = 'Tracker';
$heroTitle = 'Dein Schlaf';
$heroSubtitle = 'Ein Klick genügt - ohne etwas zu tippen.';
require __DIR__ . '/partials/header.php';
?>
<p><a href="/tracker.php">&larr; Zu allen Trackern</a></p>

<div class="card">
  <form method="get" class="field">
    <label for="sleep_date">Aufwachdatum</label>
    <div class="flex gap-sm">
      <input type="date" id="sleep_date" name="date" value="<?= e($dateStr) ?>" required>
      <button class="btn btn-ghost">Anzeigen</button>
    </div>
  </form>
  <h2>Nacht zum <?= e((new DateTimeImmutable($dateStr))->format('d.m.Y')) ?></h2>

  <div class="field mb-0">
    <label class="mb-0">Wie lange hast du geschlafen?</label>
    <form method="post" class="flex gap-sm mt-sm" style="flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_duration">
      <?php foreach ($durationOptions as $minutes => $label): ?>
        <button type="submit" name="minutes" value="<?= $minutes ?>" class="btn btn-sm <?= (int) $currentMinutes === $minutes ? 'btn-secondary' : 'btn-ghost' ?>"><?= $label ?></button>
      <?php endforeach; ?>
    </form>
  </div>

  <div class="field mb-0 mt-md">
    <label class="mb-0">Wie erholsam war dein Schlaf?</label>
    <form method="post" class="flex gap-sm mt-sm" style="flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_quality">
      <?php foreach ($qualityLabels as $value => $label): ?>
        <button type="submit" name="quality" value="<?= $value ?>" class="btn btn-sm <?= (int) $currentQuality === $value ? 'btn-secondary' : 'btn-ghost' ?>"><?= $label ?></button>
      <?php endforeach; ?>
    </form>
  </div>

  <p class="small text-muted mt-md mb-0">Pro Aufwachdatum wird ein Eintrag gespeichert, jeder Klick aktualisiert ihn sofort.</p>
</div>

<div class="card mt-md">
  <h2>Deine letzten 14 Nächte mit Eintrag</h2>
  <?php if (!$history): ?>
    <p class="text-muted">Noch keine Nächte erfasst.</p>
  <?php endif; ?>
  <?php foreach ($history as $night): ?>
    <a class="overview-row" href="/sleep.php?date=<?= e($night['sleep_date']) ?>">
      <span><?= e((new DateTimeImmutable($night['sleep_date']))->format('d.m.Y')) ?> · <?= intdiv((int) $night['duration_minutes'], 60) ?> Std. <?= (int) $night['duration_minutes'] % 60 ?> Min.</span>
      <span class="small text-muted"><?= e($qualityLabels[(int) $night['quality']]) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
