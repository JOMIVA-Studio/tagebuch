<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

if ($user['gender'] !== 'weiblich') {
    flash('error', 'Der Zyklus-Tracker ist nur verfuegbar, wenn im Profil "weiblich" als Geschlecht hinterlegt ist.');
    redirect('/tracker.php');
}

$pdo = db();

$today = new DateTimeImmutable('today');
$year = (int) ($_GET['y'] ?? $today->format('Y'));
$month = (int) ($_GET['m'] ?? $today->format('n'));

$firstOfMonth = DateTimeImmutable::createFromFormat('Y-n-j', "$year-$month-1");
if ($firstOfMonth === false) {
    $firstOfMonth = $today->modify('first day of this month');
}
$year = (int) $firstOfMonth->format('Y');
$month = (int) $firstOfMonth->format('n');
$lastOfMonth = $firstOfMonth->modify('last day of this month');

$daysInMonth = (int) $firstOfMonth->format('t');
$startOffset = (int) $firstOfMonth->format('N') - 1; // Montag = 1 ... Sonntag = 7

$prevMonth = $firstOfMonth->modify('-1 month');
$nextMonth = $firstOfMonth->modify('+1 month');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $checkedDays = array_flip((array) ($_POST['days'] ?? []));

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT IGNORE INTO cycle_days (user_id, day_date) VALUES (:uid, :date)');
        $delete = $pdo->prepare('DELETE FROM cycle_days WHERE user_id = :uid AND day_date = :date');

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if (isset($checkedDays[$dateStr])) {
                $insert->execute(['uid' => $user['id'], 'date' => $dateStr]);
            } else {
                $delete->execute(['uid' => $user['id'], 'date' => $dateStr]);
            }
        }
        $pdo->commit();
        flash('success', 'Periodentage fuer ' . $firstOfMonth->format('F Y') . ' gespeichert.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Die Eintraege konnten nicht gespeichert werden.');
    }
    redirect('/cycle.php?y=' . $year . '&m=' . $month);
}

$stmt = $pdo->prepare(
    'SELECT day_date FROM cycle_days WHERE user_id = :uid AND day_date BETWEEN :start AND :end'
);
$stmt->execute([
    'uid'   => $user['id'],
    'start' => $firstOfMonth->format('Y-m-d'),
    'end'   => $lastOfMonth->format('Y-m-d'),
]);
$markedDaysThisMonth = array_flip(array_map(
    fn($d) => (int) substr($d, 8, 2),
    $stmt->fetchAll(PDO::FETCH_COLUMN)
));

$stmt = $pdo->prepare('SELECT day_date FROM cycle_days WHERE user_id = :uid ORDER BY day_date ASC');
$stmt->execute(['uid' => $user['id']]);
$allMarkedDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$periodStarts = [];
$prevDate = null;
foreach ($allMarkedDates as $dateStr) {
    $date = new DateTimeImmutable($dateStr);
    if ($prevDate === null || (int) $date->diff($prevDate)->days > 1) {
        $periodStarts[] = $date;
    }
    $prevDate = $date;
}

$avgCycleLength = null;
if (count($periodStarts) >= 2) {
    $diffs = [];
    for ($i = 1; $i < count($periodStarts); $i++) {
        $diffs[] = (int) $periodStarts[$i]->diff($periodStarts[$i - 1])->days;
    }
    $avgCycleLength = (int) round(array_sum($diffs) / count($diffs));
}

$currentCycleDay = null;
$predictedNext = null;
if ($periodStarts) {
    $lastStart = end($periodStarts);
    $currentCycleDay = (int) $today->diff($lastStart)->days + 1;
    if ($avgCycleLength !== null) {
        $predictedNext = $lastStart->modify('+' . $avgCycleLength . ' days');
    }
}

$monthNames = [1=>'Januar','Februar','Maerz','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
$weekdays = ['Mo','Di','Mi','Do','Fr','Sa','So'];

$pageTitle = 'Zyklus-Tracker';
$heroTone = 'magenta';
$heroBadge = 'Tracker';
$heroTitle = 'Zyklus';
$heroSubtitle = 'Markiere im Kalender die Tage deiner Menstruation.';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/tracker.php">&larr; Zurueck zu allen Trackern</a></p>

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
  <form method="post">
    <?= csrf_field() ?>
    <div class="calendar-nav">
      <a class="btn btn-ghost btn-sm" href="?y=<?= $prevMonth->format('Y') ?>&m=<?= $prevMonth->format('n') ?>">&larr; Zurueck</a>
      <h2 class="mb-0"><?= $monthNames[$month] ?> <?= $year ?></h2>
      <a class="btn btn-ghost btn-sm" href="?y=<?= $nextMonth->format('Y') ?>&m=<?= $nextMonth->format('n') ?>">Weiter &rarr;</a>
    </div>

    <div class="calendar-grid">
      <?php foreach ($weekdays as $wd): ?>
        <div class="calendar-weekday"><?= $wd ?></div>
      <?php endforeach; ?>

      <?php for ($i = 0; $i < $startOffset; $i++): ?>
        <div class="calendar-day empty"></div>
      <?php endfor; ?>

      <?php for ($day = 1; $day <= $daysInMonth; $day++):
          $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
          $isToday = $dateStr === $today->format('Y-m-d');
          $isMarked = isset($markedDaysThisMonth[$day]);
      ?>
        <label class="calendar-day cycle-day <?= $isToday ? 'today' : '' ?>">
          <input type="checkbox" name="days[]" value="<?= $dateStr ?>" <?= $isMarked ? 'checked' : '' ?>>
          <span><?= $day ?></span>
          <span class="cycle-drop"><?= icon('droplet') ?></span>
        </label>
      <?php endfor; ?>
    </div>

    <button type="submit" class="btn btn-primary mt-md">Speichern</button>
  </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
