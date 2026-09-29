<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$today = new DateTimeImmutable('today');
$year = (int) ($_GET['y'] ?? $today->format('Y'));
$month = (int) ($_GET['m'] ?? $today->format('n'));
if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12) { $year = (int) $today->format('Y'); $month = (int) $today->format('n'); }

$firstOfMonth = DateTimeImmutable::createFromFormat('Y-n-j', "$year-$month-1");
if ($firstOfMonth === false) {
    $firstOfMonth = $today->modify('first day of this month');
}
$year = (int) $firstOfMonth->format('Y');
$month = (int) $firstOfMonth->format('n');

$daysInMonth = (int) $firstOfMonth->format('t');
// ISO: Montag = 1 ... Sonntag = 7
$startOffset = (int) $firstOfMonth->format('N') - 1;

$prevMonth = $firstOfMonth->modify('-1 month');
$nextMonth = $firstOfMonth->modify('+1 month');

$stmt = db()->prepare(
    'SELECT entry_date FROM diary_entries WHERE user_id = :uid AND entry_date BETWEEN :start AND :end'
);
$stmt->execute([
    'uid'   => $user['id'],
    'start' => $firstOfMonth->format('Y-m-d'),
    'end'   => $firstOfMonth->modify('last day of this month')->format('Y-m-d'),
]);
$entryDays = array_flip(array_map(
    fn($d) => (int) substr($d, 8, 2),
    $stmt->fetchAll(PDO::FETCH_COLUMN)
));

$monthNames = [1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
$weekdays = ['Mo','Di','Mi','Do','Fr','Sa','So'];

$pageTitle = 'Tagebuch';
$heroTone = 'green';
$heroBadge = 'Tagebuch';
$heroTitle = 'Dein Tagebuch';
$heroSubtitle = 'Ein Moment für dich – Tag für Tag.';
require __DIR__ . '/partials/header.php';
?>
<section class="card diary-calendar" aria-label="Tagebuchkalender">


  <div class="calendar-nav">
    <a class="btn btn-ghost btn-sm" aria-label="Vorheriger Monat" href="?y=<?= $prevMonth->format('Y') ?>&m=<?= $prevMonth->format('n') ?>">‹</a>
    <h2 class="mb-0"><?= $monthNames[$month] ?> <?= $year ?></h2>
    <a class="btn btn-ghost btn-sm" aria-label="Nächster Monat" href="?y=<?= $nextMonth->format('Y') ?>&m=<?= $nextMonth->format('n') ?>">›</a>
  </div>

  <p class="diary-calendar-hint">Wähle einen Tag für deinen Eintrag.</p>
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
        $hasEntry = isset($entryDays[$day]);
    ?>
      <a class="calendar-day <?= $isToday ? 'today' : '' ?> <?= $hasEntry ? 'has-entry' : '' ?>" href="/day.php?date=<?= $dateStr ?>" aria-label="<?= $day ?>. <?= e($monthNames[$month]) ?> <?= $year ?><?= $hasEntry ? ', Eintrag vorhanden' : '' ?>" <?= $isToday ? 'aria-current="date"' : '' ?>>
        <span><?= $day ?></span>
        <?php if ($hasEntry): ?><span class="dot"></span><?php endif; ?>
      </a>
    <?php endfor; ?>
  </div>
  <div class="diary-calendar-footer"><div class="calendar-legend"><span><i class="legend-dot"></i>Heute</span><span><i class="legend-dot soft"></i>Mit Eintrag</span></div><a class="btn btn-primary btn-sm" href="/day.php?date=<?= $today->format('Y-m-d') ?>">Heutiger Eintrag →</a></div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
