<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM quotes WHERE id = :id');
$stmt->execute(['id' => $id]);
$quote = $stmt->fetch();

if (!$quote) {
    flash('error', 'Spruch nicht gefunden.');
    redirect('/admin/quotes.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $text = trim($_POST['quote_text'] ?? '');
    $sort = (int) ($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($text === '') {
        $errors[] = 'Bitte gib einen Text ein.';
    } else {
        $stmt = $pdo->prepare('UPDATE quotes SET quote_text = :t, sort_order = :sort, is_active = :active WHERE id = :id');
        $stmt->execute(['t' => $text, 'sort' => $sort, 'active' => $active, 'id' => $id]);
        flash('success', 'Spruch gespeichert.');
        redirect('/admin/quotes.php');
    }

    $quote['quote_text'] = $text;
    $quote['sort_order'] = $sort;
    $quote['is_active'] = $active;
}

$pageTitle = 'Spruch bearbeiten';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/admin/quotes.php">&larr; Zurück zu allen Sprüchen</a></p>
<h2>Spruch bearbeiten</h2>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="field">
    <label for="quote_text">Text</label>
    <textarea id="quote_text" name="quote_text" rows="3" required><?= e($quote['quote_text']) ?></textarea>
  </div>
  <div class="field flex gap-md" style="flex-wrap:wrap; align-items:center;">
    <div style="flex:1; min-width:140px;">
      <label for="sort_order">Reihenfolge</label>
      <input type="number" id="sort_order" name="sort_order" value="<?= (int) $quote['sort_order'] ?>">
    </div>
    <div class="checkbox-row" style="padding-top:22px;">
      <input type="checkbox" id="is_active" name="is_active" <?= $quote['is_active'] ? 'checked' : '' ?>>
      <label for="is_active" style="margin:0;">Aktiv</label>
    </div>
  </div>
  <button type="submit" class="btn btn-primary mt-md">Speichern</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
