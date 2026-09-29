<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $amount = (int) ($_POST['amount_ml'] ?? 0);
        if ($amount > 0 && $amount <= 5000) {
            $stmt = $pdo->prepare('INSERT INTO water_logs (user_id, amount_ml) VALUES (:uid, :amount)');
            $stmt->execute(['uid' => $user['id'], 'amount' => $amount]);
            flash('success', $amount . ' ml hinzugefügt.');
        } else {
            flash('error', 'Bitte gib eine Menge zwischen 1 und 5000 ml ein.');
        }
        redirect('/water.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM water_logs WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $user['id']]);
        flash('success', 'Eintrag gelöscht.');
        redirect('/water.php');
    }
}

$stmt = $pdo->prepare(
    "SELECT * FROM water_logs WHERE user_id = :uid AND DATE(logged_at) = CURDATE() ORDER BY logged_at DESC"
);
$stmt->execute(['uid' => $user['id']]);
$todayLogs = $stmt->fetchAll();
$todayTotal = array_sum(array_column($todayLogs, 'amount_ml'));

$stmt = $pdo->prepare(
    "SELECT DATE(logged_at) AS log_date, SUM(amount_ml) AS total_ml
     FROM water_logs
     WHERE user_id = :uid AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(logged_at)
     ORDER BY log_date DESC"
);
$stmt->execute(['uid' => $user['id']]);
$weekTotals = [];
foreach ($stmt->fetchAll() as $row) {
    $weekTotals[$row['log_date']] = (int) $row['total_ml'];
}
$maxWeekTotal = $weekTotals ? max($weekTotals) : 0;

$pageTitle = 'Wasser-Tracker';
$heroTone = 'magenta';
$heroBadge = 'Tracker';
$heroTitle = 'Wasser';
$heroSubtitle = 'Trage ein, wie viel du getrunken hast.';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/tracker.php">&larr; Zurück zu allen Trackern</a></p>

<div class="card-grid">
  <div class="card text-center">
    <div class="text-muted small">Heute getrunken</div>
    <h2 class="mt-0"><?= $todayTotal ?> ml</h2>

    <form method="post" class="flex gap-sm" style="flex-wrap:wrap; justify-content:center;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <button type="submit" name="amount_ml" value="200" class="btn btn-ghost btn-sm">+200 ml</button>
      <button type="submit" name="amount_ml" value="300" class="btn btn-ghost btn-sm">+300 ml</button>
      <button type="submit" name="amount_ml" value="500" class="btn btn-ghost btn-sm">+500 ml</button>
    </form>

    <form method="post" class="flex gap-sm mt-md" style="justify-content:center;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="number" name="amount_ml" min="1" max="5000" placeholder="ml" style="max-width:120px;">
      <button type="submit" class="btn btn-primary">Hinzufügen</button>
    </form>
  </div>

  <div class="card">
    <h3>Letzte 7 Tage</h3>
    <?php if (!$weekTotals): ?>
      <p class="text-muted small">Noch keine Einträge.</p>
    <?php else: ?>
      <?php foreach ($weekTotals as $date => $total): ?>
        <div class="mt-sm">
          <div class="flex-between mb-0"><span class="small"><?= e((new DateTimeImmutable($date))->format('d.m.Y')) ?></span><span class="small text-muted"><?= $total ?> ml</span></div>
          <div class="goal-progress"><div class="goal-progress-bar" style="width: <?= $maxWeekTotal > 0 ? round($total / $maxWeekTotal * 100) : 0 ?>%;"></div></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-md">
  <h3>Heutige Einträge</h3>
  <?php if (!$todayLogs): ?>
    <p class="text-muted small">Noch keine Einträge heute.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Uhrzeit</th><th>Menge</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($todayLogs as $log): $fid = 'wd' . (int) $log['id']; ?>
            <tr>
              <td><?= e((new DateTimeImmutable($log['logged_at']))->format('H:i')) ?></td>
              <td><?= (int) $log['amount_ml'] ?> ml</td>
              <td><button form="<?= $fid ?>" type="submit" class="btn btn-danger btn-sm">Löschen</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php foreach ($todayLogs as $log): ?>
  <form id="wd<?= (int) $log['id'] ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $log['id'] ?>">
  </form>
<?php endforeach; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
