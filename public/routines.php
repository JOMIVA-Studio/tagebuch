<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$today = date('Y-m-d');
$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM morning_entries WHERE user_id = :uid AND entry_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $today]);
$morningDone = (bool) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT id FROM evening_entries WHERE user_id = :uid AND entry_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $today]);
$eveningDone = (bool) $stmt->fetchColumn();

$pageTitle = 'Routinen';
$heroTone = 'green';
$heroBadge = 'Routinen';
$heroTitle = 'Morgen- und Abendroutine';
$heroSubtitle = 'Ein fester Rahmen fuer den Start und den Abschluss deines Tages.';
require __DIR__ . '/partials/header.php';
?>

<div class="card-grid">
  <a href="/morning.php" class="card goal-card card-accent-left">
    <div class="goal-title">
      <h3 class="mb-0"><?= icon('sun') ?> Morgenroutine</h3>
      <?php if ($morningDone): ?><span class="status-pill done">Erledigt</span><?php endif; ?>
    </div>
    <p class="text-muted small mb-0">Schlaf, Zyklus, Dankbarkeit, Vorsatz und Affirmation.</p>
  </a>

  <a href="/evening.php" class="card goal-card card-accent-left magenta">
    <div class="goal-title">
      <h3 class="mb-0"><?= icon('moon') ?> Abendroutine</h3>
      <?php if ($eveningDone): ?><span class="status-pill done">Erledigt</span><?php endif; ?>
    </div>
    <p class="text-muted small mb-0">Schoener Moment, Learning, Wasser und Ziele des Tages.</p>
  </a>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
