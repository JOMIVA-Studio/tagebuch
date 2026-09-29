<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/sleep.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$today = new DateTimeImmutable('today');
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

// Compact overview, scoped to the signed-in user and the current period.
$stmt = db()->prepare('SELECT * FROM goals WHERE user_id = :uid AND is_active = 1 ORDER BY created_at DESC LIMIT 3');
$stmt->execute(['uid' => $user['id']]);
$overviewGoals = $stmt->fetchAll();
$overviewProgress = db()->prepare("SELECT COUNT(*) FROM goal_logs WHERE goal_id = :gid AND status = 'done' AND log_date BETWEEN :start AND :end");
foreach ($overviewGoals as &$overviewGoal) {
    [$periodStart, $periodEnd, $periodLabel] = match ($overviewGoal['frequency_type']) {
        'daily' => [$today, $today, 'heute'],
        'monthly' => [$today->modify('first day of this month'), $today->modify('last day of this month'), 'diesen Monat'],
        default => [$today->modify('monday this week'), $today->modify('sunday this week'), 'diese Woche'],
    };
    $overviewProgress->execute(['gid' => $overviewGoal['id'], 'start' => $periodStart->format('Y-m-d'), 'end' => $periodEnd->format('Y-m-d')]);
    $overviewGoal['done'] = (int) $overviewProgress->fetchColumn();
    $overviewGoal['period_label'] = $periodLabel;
    $overviewGoal['percent'] = min(100, (int) round($overviewGoal['done'] / max(1, (int) $overviewGoal['frequency_target']) * 100));
}
unset($overviewGoal);
$stmt = db()->prepare('SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs WHERE user_id = :uid AND DATE(logged_at) = CURDATE()');
$stmt->execute(['uid' => $user['id']]);
$overviewWater = (int) $stmt->fetchColumn();

// One locally stored motif per weekday, using the app's local calendar date.
$dailyImageIndex = (int) $today->format('N') - 1;
$dailyImageDescriptions = [
    'Ein stiller Bergsee im Morgenlicht', 'Ein sonniger Waldweg zwischen grünen Farnen',
    'Lavendel im sanften Licht der aufgehenden Sonne', 'Rosa Blumen auf einer sonnigen Wiese',
    'Sanfte Wellen an einem ruhigen Strand', 'Ein Wasserfall zwischen moosbewachsenen Felsen',
    'Grüne Hügel und ein Baum im Morgenlicht',
];

$stmt = db()->prepare('SELECT duration_minutes, under_five_hours FROM sleep_logs WHERE user_id = :uid AND sleep_date = :date');
$stmt->execute(['uid'=>$user['id'], 'date'=>$today->format('Y-m-d')]);
$overviewSleep = $stmt->fetch();

require_once __DIR__ . '/../src/routines.php';
$stmt = db()->prepare('SELECT entry_date FROM diary_entries WHERE user_id = :uid AND entry_date <= :today ORDER BY entry_date');
$stmt->execute(['uid'=>$user['id'], 'today'=>$today->format('Y-m-d')]);
$streak = diary_streak($stmt->fetchAll(PDO::FETCH_COLUMN), $today);
$stmt = db()->prepare('SELECT * FROM daily_routines WHERE user_id = :uid');
$stmt->execute(['uid'=>$user['id']]);
$routine = $stmt->fetch() ?: [];
$now = new DateTimeImmutable('now');

$stmt = db()->prepare('SELECT id, created_at FROM vision_manifestations WHERE user_id=:uid ORDER BY (id = COALESCE((SELECT manifestation_id FROM home_visions WHERE home_visions.user_id=vision_manifestations.user_id), 0)) DESC, id DESC LIMIT 1');
$stmt->execute(['uid'=>$user['id']]);
$latestVision = $stmt->fetch();

$pageTitle = 'Startseite';
$heroTone = 'green';
$heroBadge = $today->format('d.m.Y');
$heroTitle = 'Hallo ' . $user['name'] . '!';
$heroSubtitle = 'Dein Tag auf einen Blick.';
require __DIR__ . '/partials/header.php';
?>

<div class="home-refresh">
<div class="home-toolbar">
  <a class="btn btn-primary btn-sm" href="/day.php?date=<?= $today->format('Y-m-d') ?>"><?= $streak['today'] ? 'Heutigen Eintrag ansehen' : 'Heute etwas festhalten' ?> →</a>
  <div class="home-tools">
    <details class="home-streak">
      <summary><span aria-hidden="true"><?= $streak['current'] > 0 ? '🔥' : '🌱' ?></span> <?= $streak['current'] ?> <?= $streak['current'] === 1 ? 'Tag' : 'Tage' ?> in Folge</summary>
      <div class="home-streak-info"><strong>Dein Rekord: <?= $streak['best'] ?> <?= $streak['best'] === 1 ? 'Tag' : 'Tage' ?></strong><p>Jeder Tag mit einem gespeicherten Tagebucheintrag zählt.</p></div>
    </details>
    <a href="/profile.php" class="home-reminders" aria-label="Erinnerungen einstellen">Erinnerungen</a>
  </div>
</div>
<?php foreach (['morning'=>'Zeit für einen guten Start', 'evening'=>'Ein Moment für deinen Tagesrückblick'] as $kind=>$title): ?>
  <?php if (routine_due($routine, $now, $kind) && !($kind === 'evening' && $streak['today'])): ?>
    <div class="flash flash-info"><strong><?= e($title) ?></strong><br><a href="/day.php?date=<?= $today->format('Y-m-d') ?>"><?= $kind === 'morning' ? 'Schlaf festhalten und den Tag bewusst beginnen' : 'Gedanken, Wasser und Zyklus eintragen' ?> →</a></div>
  <?php endif; ?>
<?php endforeach; ?>
<?php if ($latestVision || $todayQuote): ?>
<div class="dashboard-grid home-inspiration">
<?php if ($latestVision): ?>
<section class="card home-vision" aria-label="Deine Vision">
  <a href="/vision_file.php?id=<?= (int) $latestVision['id'] ?>&amp;format=pdf" target="_blank" rel="noopener"><img src="/vision_file.php?id=<?= (int) $latestVision['id'] ?>" alt="Deine ausgewählte Vision"></a>
  <div><h2>Deine Vision</h2><p class="small text-muted">Das ist dein Warum.</p></div>
</section>
<?php endif; ?>
<aside class="daily-thought">
<?php if ($todayQuote): ?>
  <div class="card quote-card">
    <img class="quote-image" src="/assets/img/daily/day-<?= $dailyImageIndex ?>.jpg" alt="<?= e($dailyImageDescriptions[$dailyImageIndex]) ?>" width="1200" height="800" loading="lazy">
    <div class="section-eyebrow">Ein Gedanke für heute</div>
    <p class="quote-text mt-sm mb-0">&bdquo;<?= e($todayQuote['quote_text']) ?>&ldquo;</p>
    <?php if (!empty($todayQuote['author'])): ?><p class="text-muted small mt-sm mb-0">— <?= e($todayQuote['author']) ?></p><?php endif; ?>
  </div>
<?php endif; ?>


</aside>
</div>
<?php endif; ?>
<div class="dashboard-grid home-overview home-main-row" aria-label="Tagebuch, Ziele und Tracker">
<section class="card home-diary" aria-label="Tagebuchübersicht">
  <div class="flex-between"><h2 class="mb-0"><?= icon('home') ?> Tagebuch</h2><a class="overview-link" href="/diary.php">Zum Kalender →</a></div>
  <div class="home-diary-status"><span aria-hidden="true"><?= $streak['today'] ? '✅' : '✍️' ?></span><div><strong><?= $streak['today'] ? 'Heute schon festgehalten' : 'Ein Moment für dich' ?></strong><p class="small text-muted mb-0"><?= $today->format('d.m.Y') ?> · <?= $streak['today'] ? 'Dein Eintrag ist gespeichert.' : 'Wie geht es dir heute?' ?></p></div></div>
  <a class="btn btn-primary btn-sm" href="/day.php?date=<?= $today->format('Y-m-d') ?>"><?= $streak['today'] ? 'Eintrag ansehen' : 'Heute schreiben' ?> →</a>
</section>
  <div class="card overview-goals">
    <div class="flex-between"><h2 class="mb-0"><?= icon('target') ?> Ziele</h2><a class="overview-link" href="/goals.php">Alle Ziele →</a></div>
    <?php if (!$overviewGoals): ?>
      <p class="text-muted small mt-sm">Dein nächster kleiner Schritt beginnt hier.</p>
      <a class="overview-link" href="/goals.php?new=1">Erstes Ziel anlegen →</a>
    <?php else: ?>
      <?php foreach ($overviewGoals as $overviewGoal): ?>
        <a class="overview-row" href="/goal.php?id=<?= (int) $overviewGoal['id'] ?>">
          <span class="overview-title"><?= e($overviewGoal['title']) ?></span>
          <span class="goal-meta"><?= $overviewGoal['done'] ?> / <?= (int) $overviewGoal['frequency_target'] ?> · <?= e($overviewGoal['period_label']) ?></span>
          <span class="goal-progress" aria-hidden="true"><span class="overview-progress" style="width:<?= $overviewGoal['percent'] ?>%"></span></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="card overview-tracker">
    <div class="flex-between"><h2 class="mb-0"><?= icon('droplet') ?> Tracker</h2><a class="overview-link" href="/tracker.php">Öffnen →</a></div>
    <div class="tracker-tiles">
    <a class="overview-row" href="/water.php">
      <span class="overview-title">Wasser</span>
      <span class="overview-water"><?= number_format($overviewWater, 0, ',', '’') ?> <small>ml</small></span>

    </a>
    <a class="overview-row overview-cycle" href="/sleep.php"><span class="overview-title">Schlaf</span><span class="small"><?= $overviewSleep !== false ? (!empty($overviewSleep['under_five_hours']) ? '&lt; 5 Std.' : e(sleep_duration_label($overviewSleep))) : 'Eintragen →' ?></span></a>
    <?php if ($user['gender'] === 'weiblich'): ?>
      <a class="overview-row overview-cycle" href="/cycle.php"><span class="overview-title"><?= icon('moon') ?> Zyklus</span><span class="small">Öffnen →</span></a>
    <?php endif; ?>
    </div>
  </div>
</div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
