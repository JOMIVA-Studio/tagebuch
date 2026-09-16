<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$quoteCount = (int) $pdo->query('SELECT COUNT(*) FROM quotes WHERE is_active = 1')->fetchColumn();
$entryCount = (int) $pdo->query('SELECT COUNT(*) FROM diary_entries')->fetchColumn();
$goalCount = (int) $pdo->query('SELECT COUNT(*) FROM goals WHERE is_active = 1')->fetchColumn();

$stmt = $pdo->query('SELECT id, name, email, created_at, last_activity_at FROM users ORDER BY created_at DESC LIMIT 10');
$recentUsers = $stmt->fetchAll();

$pageTitle = 'Uebersicht';
require __DIR__ . '/partials/header.php';
?>

<h2>Administration</h2>

<div class="card-grid">
  <div class="card"><div class="text-muted small">User</div><h2 class="mt-0"><?= $userCount ?></h2></div>
  <div class="card"><div class="text-muted small">Aktive Sprueche</div><h2 class="mt-0"><?= $quoteCount ?></h2></div>
  <div class="card"><div class="text-muted small">Tagebucheintraege</div><h2 class="mt-0"><?= $entryCount ?></h2></div>
  <div class="card"><div class="text-muted small">Aktive Ziele</div><h2 class="mt-0"><?= $goalCount ?></h2></div>
</div>

<div class="card mt-md">
  <h3>Neueste User</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>E-Mail</th><th>Registriert</th><th>Zuletzt aktiv</th></tr></thead>
      <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e((new DateTimeImmutable($u['created_at']))->format('d.m.Y')) ?></td>
            <td><?= $u['last_activity_at'] ? e((new DateTimeImmutable($u['last_activity_at']))->format('d.m.Y H:i')) : '-' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
