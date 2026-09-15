<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs WHERE user_id = :uid AND DATE(logged_at) = CURDATE()');
$stmt->execute(['uid' => $user['id']]);
$todayTotal = (int) $stmt->fetchColumn();

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
    Der Zyklus-Tracker ist fuer Userinnen mit dem Profil-Geschlecht "weiblich" verfuegbar.
    Du kannst das in deinem <a href="/profile.php">Profil</a> einstellen.
  </p>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
