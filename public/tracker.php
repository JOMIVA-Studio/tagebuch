<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/sleep.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs WHERE user_id = :uid AND DATE(logged_at) = CURDATE()');
$stmt->execute(['uid' => $user['id']]);
$todayTotal = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT duration_minutes, under_five_hours FROM sleep_logs WHERE user_id = :uid AND sleep_date = :date');
$stmt->execute(['uid'=>$user['id'], 'date'=>date('Y-m-d')]);
$todaySleep = $stmt->fetch();

$showCycle = $user['gender'] === 'weiblich';

$pageTitle = 'Tracker';
$heroTone = 'magenta';
$heroBadge = 'Tracker';
$heroTitle = 'Deine Tracker';
$heroSubtitle = 'Behalte im Blick, was dir wichtig ist.';
require __DIR__ . '/partials/header.php';
?>

<div class="card-grid">
  <a href="/water.php" class="card goal-card card-accent-left">
    <div class="goal-title">
      <h3 class="mb-0"><?= icon('droplet') ?> Wasser</h3>
    </div>
    <p class="text-muted small mb-0">Trage ein, wie viel Wasser du getrunken hast.</p>
    <div class="goal-meta"><?= $todayTotal ?> ml heute</div>
  </a>

  <a href="/sleep.php" class="card goal-card card-accent-left magenta">
    <h3 class="mb-0"><?= icon('moon') ?> Schlaf</h3>
    <p class="text-muted small mb-0">Schlafdauer und Erholung festhalten.</p>
    <div class="goal-meta"><?= $todaySleep !== false ? e(sleep_duration_label($todaySleep)) . ' · letzte Nacht' : 'Für letzte Nacht noch kein Eintrag' ?></div>
  </a>

  <?php if ($showCycle): ?>
    <a href="/cycle.php" class="card goal-card card-accent-left magenta">
      <div class="goal-title">
        <h3 class="mb-0"><?= icon('moon') ?> Zyklus</h3>
      </div>
      <p class="text-muted small mb-0">Verfolge deinen Zyklus und Vorhersagen.</p>
    </a>
  <?php endif; ?>
</div>

<?php if (!$showCycle): ?>
  <p class="text-muted small mt-md">
    Der Zyklus-Tracker ist für Userinnen mit dem Profil-Geschlecht "weiblich" verfügbar.
    Du kannst das in deinem <a href="/profile.php">Profil</a> einstellen.
  </p>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
