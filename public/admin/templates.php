<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$templates = $pdo->query('SELECT * FROM email_templates ORDER BY name')->fetchAll();

$pageTitle = 'Mail-Vorlagen';
require __DIR__ . '/partials/header.php';
?>

<h2>Mail-Vorlagen</h2>
<p class="text-muted">Alle vom System versendeten E-Mails verwenden diese Vorlagen. Platzhalter wie <code>{{name}}</code> werden beim Versand ersetzt.</p>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Schlüssel</th><th>Betreff</th><th>Zuletzt geändert</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($templates as $t): ?>
          <tr>
            <td><?= e($t['name']) ?></td>
            <td><code><?= e($t['template_key']) ?></code></td>
            <td><?= e($t['subject']) ?></td>
            <td><?= e((new DateTimeImmutable($t['updated_at']))->format('d.m.Y H:i')) ?></td>
            <td><a class="btn btn-ghost btn-sm" href="/admin/template_edit.php?id=<?= (int) $t['id'] ?>">Bearbeiten</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
