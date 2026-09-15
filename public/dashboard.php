<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$today = new DateTimeImmutable('today');
$year = (int) ($_GET['y'] ?? $today->format('Y'));
$month = (int) ($_GET['m'] ?? $today->format('n'));

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

$monthNames = [1=>'Januar','Februar','Maerz','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
$weekdays = ['Mo','Di','Mi','Do','Fr','Sa','So'];

$goalsCount = user_has_goal((int) $user['id']);

$quoteCount = (int) db()->query('SELECT COUNT(*) FROM quotes WHERE is_active = 1')->fetchColumn();
$todayQuote = null;
if ($quoteCount > 0) {
    $offset = ((int) $today->format('z')) % $quoteCount;
    $stmt = db()->prepare('SELECT * FROM quotes WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1 OFFSET :offset');
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $todayQuote = $stmt->fetch();
}

$pageTitle = 'Startseite';
$heroTone = 'green';
$heroBadge = 'Startseite';
$heroTitle = 'Hallo ' . $user['name'] . '!';
$heroSubtitle = 'Deine Uebersicht mit Kalender und Spruch des Tages.';
require __DIR__ . '/partials/header.php';
?>

<?php if (!$goalsCount): ?>
  <div class="flash flash-info">
    Du hast noch kein Ziel angelegt. <a href="/goals.php?new=1">Jetzt erstes Ziel anlegen</a> (mindestens eines ist erforderlich).
  </div>
<?php endif; ?>

<?php if ($todayQuote): ?>
  <div class="card quote-card card-accent-left magenta">
    <div class="text-muted small" style="font-weight:700; text-transform:uppercase; letter-spacing:0.08em;">Spruch des Tages</div>
    <p class="quote-text mt-sm mb-0">&bdquo;<?= e($todayQuote['quote_text']) ?>&ldquo;</p>
    <?php if (!empty($todayQuote['author'])): ?><p class="text-muted small mt-sm mb-0">— <?= e($todayQuote['author']) ?></p><?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
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
        $hasEntry = isset($entryDays[$day]);
    ?>
      <a class="calendar-day <?= $isToday ? 'today' : '' ?> <?= $hasEntry ? 'has-entry' : '' ?>" href="/day.php?date=<?= $dateStr ?>">
        <span><?= $day ?></span>
        <?php if ($hasEntry): ?><span class="dot"></span><?php endif; ?>
      </a>
    <?php endfor; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
