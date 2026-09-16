<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $text = trim($_POST['quote_text'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($text === '') {
            $errors[] = 'Bitte gib einen Spruch ein.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO quotes (quote_text, sort_order) VALUES (:t, :sort)');
            $stmt->execute(['t' => $text, 'sort' => $sort]);
            flash('success', 'Spruch hinzugefuegt.');
            redirect('/admin/quotes.php');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM quotes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash('success', 'Spruch geloescht.');
        redirect('/admin/quotes.php');
    }
}

$quotes = $pdo->query('SELECT * FROM quotes ORDER BY sort_order, id')->fetchAll();
$activeCount = count(array_filter($quotes, fn($q) => (int) $q['is_active'] === 1));

$pageTitle = 'Sprueche verwalten';
require __DIR__ . '/partials/header.php';
?>

<h2>Spruch des Tages</h2>
<p class="text-muted">
  Im Tagebuch wird jeden Tag automatisch ein aktiver Spruch angezeigt
  (rotierend nach Kalendertag). Aktuell <?= $activeCount ?> aktive Sprueche hinterlegt.
</p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card">
  <h3>Neuer Spruch</h3>
  <form method="post" class="flex gap-md" style="flex-wrap:wrap; align-items:flex-end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field mb-0" style="flex:3; min-width:260px;">
      <label>Text</label>
      <input type="text" name="quote_text" required>
    </div>
    <div class="field mb-0" style="width:120px;">
      <label>Reihenfolge</label>
      <input type="number" name="sort_order" value="<?= count($quotes) + 1 ?>">
    </div>
    <button type="submit" class="btn btn-primary">Hinzufuegen</button>
  </form>
</div>

<div class="card mt-md">
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Spruch</th><th>Aktiv</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($quotes as $q): ?>
          <tr>
            <td><?= (int) $q['sort_order'] ?></td>
            <td><?= e($q['quote_text']) ?></td>
            <td><?= $q['is_active'] ? 'Ja' : 'Nein' ?></td>
            <td class="flex gap-sm">
              <a class="btn btn-ghost btn-sm" href="/admin/quote_edit.php?id=<?= (int) $q['id'] ?>">Bearbeiten</a>
              <form method="post" onsubmit="return confirm('Spruch wirklich loeschen?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Loeschen</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
