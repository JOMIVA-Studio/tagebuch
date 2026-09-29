<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

if ($user['gender'] !== 'weiblich') {
    flash('error', 'Der Zyklus-Tracker ist nur verfügbar, wenn im Profil "weiblich" als Geschlecht hinterlegt ist.');
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
        flash('success', 'Periodentage für ' . $firstOfMonth->format('F Y') . ' gespeichert.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Die Einträge konnten nicht gespeichert werden.');
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

// Phasengrenzen anhand der eigenen durchschnittlichen Zykluslaenge (Annahme
// 28 Tage, solange noch nicht genug Daten vorliegen). Die Lutealphase wird
// in eine fruehe (stabiler) und eine spaete (PMS-nahe) Haelfte aufgeteilt.
$cycleLengthForPhase = $avgCycleLength ?? 28;
$ovulationStart = max(10, $cycleLengthForPhase - 14);
$ovulationEnd = $ovulationStart + 1;
$lateLutealStart = max($ovulationEnd + 3, $cycleLengthForPhase - 3);

$phases = [
    'menstruation' => [
        'label' => 'Menstruationsphase',
        'range' => 'Tag 1–5',
        'text'  => 'Die Gebärmutterschleimhaut wird abgestoßen. Energie und Hormonspiegel sind meist niedrig - ein guter Zeitpunkt für Ruhe, Rückzug und Selbstfürsorge.',
        'tips'  => [
            'Sport'      => 'Sanfte Bewegung wie Yoga, Spaziergänge oder leichtes Dehnen. Intensives Training eher pausieren.',
            'Ernährung'  => 'Eisenreiche Lebensmittel (Hülsenfrüchte, grünes Blattgemüse), warme, nährende Mahlzeiten, ausreichend trinken.',
            'Erholung'   => 'Extra Schlaf einplanen, Wärme (z.B. Wärmflasche) und bewusste Zeit für Rückzug gönnen.',
        ],
    ],
    'follikel' => [
        'label' => 'Follikelphase',
        'range' => 'Tag 6–' . ($ovulationStart - 1),
        'text'  => 'Östrogen steigt an, ein neuer Follikel reift heran. Energie, Stimmung und Konzentration nehmen tendenziell zu - gut geeignet für neue Projekte und Aktivität.',
        'tips'  => [
            'Sport'      => 'Guter Zeitpunkt für Cardio und neue Trainingsarten - Intensität kann langsam gesteigert werden.',
            'Ernährung'  => 'Leichte, frische Kost mit viel Gemüse, fermentierte Lebensmittel für die Darmflora.',
            'Erholung'   => 'Die steigende Energie für neue Ideen und sozialen Austausch nutzen.',
        ],
    ],
    'ovulation' => [
        'label' => 'Ovulationsphase',
        'range' => 'Tag ' . $ovulationStart . '–' . $ovulationEnd,
        'text'  => 'Der Eisprung findet statt, die fruchtbarsten Tage im Zyklus. Energie, Selbstvertrauen und Geselligkeit sind bei vielen am höchsten.',
        'tips'  => [
            'Sport'      => 'Höchste Belastbarkeit - Platz für intensives Training, HIIT oder Krafttraining.',
            'Ernährung'  => 'Leichte, ballaststoffreiche Kost mit Antioxidantien (z.B. Beeren), gut hydrieren.',
            'Erholung'   => 'Gute Zeit für wichtige Gespräche, Präsentationen oder soziale Aktivitäten.',
        ],
    ],
    'luteal_frueh' => [
        'label' => 'Frühe Lutealphase',
        'range' => 'Tag ' . ($ovulationEnd + 1) . '–' . ($lateLutealStart - 1),
        'text'  => 'Progesteron steigt an und bereitet den Körper auf eine mögliche Schwangerschaft vor. Energie und Stimmung sind meist noch stabil, nehmen aber allmählich ab.',
        'tips'  => [
            'Sport'      => 'Noch gut Platz für moderates Training wie Pilates, Schwimmen oder Krafttraining mit mittlerer Intensität.',
            'Ernährung'  => 'Ausgewogene Mahlzeiten mit gesunden Fetten und Ballaststoffen, um den Blutzucker stabil zu halten.',
            'Erholung'   => 'Guter Zeitpunkt, um laufende Projekte voranzutreiben und Aufgaben fokussiert abzuschliessen.',
        ],
    ],
    'luteal_spaet' => [
        'label' => 'Späte Lutealphase (PMS-nah)',
        'range' => 'Tag ' . $lateLutealStart . '–' . $cycleLengthForPhase,
        'text'  => 'Östrogen und Progesteron fallen deutlich ab. PMS-Symptome wie Reizbarkeit, Blähungen oder Müdigkeit können auftreten, Energie ist oft am niedrigsten kurz vor der Periode.',
        'tips'  => [
            'Sport'      => 'Sanftere Bewegung wie Spaziergänge, Yoga oder Dehnen - intensives Training eher reduzieren.',
            'Ernährung'  => 'Magnesiumreiche Lebensmittel (Nüsse, dunkle Schokolade), weniger Salz, Koffein und Zucker können Beschwerden lindern.',
            'Erholung'   => 'Bewusst kürzertreten, ausreichend Schlaf einplanen, Stress reduzieren und nachsichtig mit dir selbst sein.',
        ],
    ],
];

$currentPhase = null;
if ($currentCycleDay !== null) {
    $dayInCycle = (($currentCycleDay - 1) % $cycleLengthForPhase) + 1;

    if ($dayInCycle <= 5) {
        $currentPhase = 'menstruation';
    } elseif ($dayInCycle < $ovulationStart) {
        $currentPhase = 'follikel';
    } elseif ($dayInCycle <= $ovulationEnd) {
        $currentPhase = 'ovulation';
    } elseif ($dayInCycle < $lateLutealStart) {
        $currentPhase = 'luteal_frueh';
    } else {
        $currentPhase = 'luteal_spaet';
    }
}

$monthNames = [1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
$weekdays = ['Mo','Di','Mi','Do','Fr','Sa','So'];

$pageTitle = 'Zyklus-Tracker';
$heroTone = 'magenta';
$heroBadge = 'Tracker';
$heroTitle = 'Dein Zyklus';
$heroSubtitle = 'Dein Rhythmus. Dein Überblick.';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/tracker.php">&larr; Zurück zu allen Trackern</a></p>

<div class="cycle-refresh">
<div class="cycle-stats">
  <div class="cycle-stat">
    <div class="text-muted small">Aktueller Zyklustag</div>
    <h2 class="mt-0"><?= $currentCycleDay ?? '–' ?></h2>
  </div>
  <div class="cycle-stat">
    <div class="text-muted small">Zykluslänge · Durchschnitt</div>
    <h2 class="mt-0"><?= $avgCycleLength !== null ? $avgCycleLength . ' Tage' : '–' ?></h2>
  </div>
  <div class="cycle-stat">
    <div class="text-muted small">Nächster Start · geschätzt</div>
    <h2 class="mt-0"><?= $predictedNext !== null ? e($predictedNext->format('d.m.Y')) : '–' ?></h2>
  </div>
</div>

<div class="cycle-layout">
<section class="card cycle-calendar">
  <form method="post">
    <?= csrf_field() ?>
    <div class="calendar-nav">
      <a aria-label="Vorheriger Monat" class="btn btn-ghost btn-sm" href="?y=<?= $prevMonth->format('Y') ?>&m=<?= $prevMonth->format('n') ?>">‹</a>
      <h2 class="mb-0"><?= $monthNames[$month] ?> <?= $year ?></h2>
      <a aria-label="Nächster Monat" class="btn btn-ghost btn-sm" href="?y=<?= $nextMonth->format('Y') ?>&m=<?= $nextMonth->format('n') ?>">›</a>
    </div>

    <p class="cycle-instruction">Tippe auf deine Periodentage.</p>
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
          <input type="checkbox" name="days[]" aria-label="Periodentag <?= $day ?>. <?= $monthNames[$month] ?> <?= $year ?>" value="<?= $dateStr ?>" <?= $isMarked ? 'checked' : '' ?>>
          <span class="cycle-day-face"><span><?= $day ?></span><span class="cycle-drop"><?= icon('droplet') ?></span></span>
        </label>
      <?php endfor; ?>
    </div>

    <div class="cycle-calendar-footer"><span class="cycle-legend"><i></i> Periode <span class="cycle-today-dot"></span> Heute</span><button type="submit" class="btn btn-primary">Tage speichern</button></div>
  </form>
</section>
<aside class="cycle-aside">
<?php if ($currentPhase !== null): ?>
  <div class="card cycle-phase">
    <div class="text-muted small" style="font-weight:700; text-transform:uppercase; letter-spacing:0.08em;">Phase · geschätzt</div>
    <h2 class="mt-sm mb-0"><?= e($phases[$currentPhase]['label']) ?></h2>
    <p class="mt-sm mb-0"><?= e($phases[$currentPhase]['text']) ?></p>
    <div class="cycle-tips">
      <?php foreach ($phases[$currentPhase]['tips'] as $tipLabel => $tipText): ?>
        <div>
          <div class="small" style="font-weight:700;"><?= e($tipLabel) ?></div>
          <p class="small text-muted mb-0"><?= e($tipText) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <div class="flash flash-info mt-md">
    Markiere im Kalender unten mindestens einen Periodenstart, damit deine aktuelle Zyklusphase angezeigt werden kann.
  </div>
<?php endif; ?>

</aside>
</div>
<?php
$phaseGroups = [
 ['title'=>'Menstruation', 'icon'=>'◒', 'keys'=>['menstruation']],
 ['title'=>'Follikelphase', 'icon'=>'✿', 'keys'=>['follikel']],
 ['title'=>'Eisprung', 'icon'=>'☀', 'keys'=>['ovulation']],
 ['title'=>'Lutealphase', 'icon'=>'☾', 'keys'=>['luteal_frueh','luteal_spaet']],
];
?>
<section class="phase-overview" aria-labelledby="phaseOverviewTitle">
  <h2 id="phaseOverviewTitle">Deine vier Zyklusphasen</h2>
  <p class="small text-muted">Ein Überblick über deinen Rhythmus. Die Zeiträume sind geschätzt und können sich verschieben.</p>
  <div class="phase-overview-grid">
    <?php foreach ($phaseGroups as $index => $group): $active = in_array($currentPhase, $group['keys'], true); ?>
    <article class="phase-overview-card phase-tone-<?= $index ?> <?= $active ? 'is-current' : '' ?>">
      <div class="phase-overview-top"><span class="phase-symbol" aria-hidden="true"><?= $group['icon'] ?></span><span class="phase-number">0<?= $index + 1 ?></span></div>
      <h3><?= e($group['title']) ?></h3>
      <div class="phase-range"><?= count($group['keys']) === 1 ? e($phases[$group['keys'][0]]['range']) : 'Tag ' . ($ovulationEnd + 1) . '–' . $cycleLengthForPhase ?></div>
      <div class="phase-status-slot"><?php if ($active): ?><span class="phase-current">Deine Phase · geschätzt</span><?php endif; ?></div>
      <details class="phase-more">
        <summary>Wissen & Tipps</summary>
        <?php foreach ($group['keys'] as $key): $phase = $phases[$key]; ?>
        <div class="phase-detail-block">
          <?php if (count($group['keys']) > 1): ?><h4><?= e($phase['label']) ?></h4><p class="phase-range"><?= e($phase['range']) ?></p><?php endif; ?>
          <p class="phase-description"><?= e($phase['text']) ?></p>
          <dl class="phase-tip-list">
          <?php foreach ($phase['tips'] as $tipLabel => $tipText): ?>
            <div><dt><?= e($tipLabel) ?></dt><dd><?= e($tipText) ?></dd></div>
          <?php endforeach; ?>
          </dl>
        </div>
        <?php endforeach; ?>
      </details>
    </article>
    <?php endforeach; ?>
  </div>
</section>

</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
