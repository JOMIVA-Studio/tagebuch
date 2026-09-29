<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$errors = [];
$typeLabels = ['text' => 'Freitext', 'scale_1_5' => 'Skala 1-5', 'yesno' => 'Ja/Nein', 'number' => 'Zahl'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $text = trim($_POST['question_text'] ?? '');
        $type = $_POST['answer_type'] ?? 'text';
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if (!array_key_exists($type, $typeLabels)) {
            $type = 'text';
        }
        if ($text === '') {
            $errors[] = 'Bitte gib einen Fragetext ein.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO questions (question_text, answer_type, sort_order) VALUES (:t, :type, :sort)'
            );
            $stmt->execute(['t' => $text, 'type' => $type, 'sort' => $sort]);
            flash('success', 'Frage hinzugefügt.');
            redirect('/admin/questions.php');
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $text = trim($_POST['question_text'] ?? '');
        $type = $_POST['answer_type'] ?? 'text';
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if (!array_key_exists($type, $typeLabels)) {
            $type = 'text';
        }
        $stmt = $pdo->prepare(
            'UPDATE questions SET question_text = :t, answer_type = :type, sort_order = :sort, is_active = :active WHERE id = :id'
        );
        $stmt->execute(['t' => $text, 'type' => $type, 'sort' => $sort, 'active' => $active, 'id' => $id]);
        flash('success', 'Frage aktualisiert.');
        redirect('/admin/questions.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM questions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash('success', 'Frage gelöscht.');
        redirect('/admin/questions.php');
    }
}

$questions = $pdo->query('SELECT * FROM questions ORDER BY sort_order, id')->fetchAll();

$pageTitle = 'Fragen verwalten';
require __DIR__ . '/partials/header.php';
?>

<h2>Fragen-Katalog</h2>
<p class="text-muted">Diese Fragen gelten zentral für alle User bei jedem Tagebucheintrag.</p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card">
  <h3>Neue Frage</h3>
  <form method="post" class="flex gap-md" style="flex-wrap:wrap; align-items:flex-end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field mb-0" style="flex:2; min-width:220px;">
      <label>Fragetext</label>
      <input type="text" name="question_text" required>
    </div>
    <div class="field mb-0" style="flex:1; min-width:150px;">
      <label>Typ</label>
      <select name="answer_type">
        <?php foreach ($typeLabels as $val => $label): ?>
          <option value="<?= $val ?>"><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field mb-0" style="width:100px;">
      <label>Reihenfolge</label>
      <input type="number" name="sort_order" value="0">
    </div>
    <button type="submit" class="btn btn-primary">Hinzufügen</button>
  </form>
</div>

<div class="card mt-md">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Frage</th><th>Typ</th><th>Reihenfolge</th><th>Aktiv</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($questions as $q): $fid = 'qf' . (int) $q['id']; $did = 'qd' . (int) $q['id']; ?>
          <tr>
            <td><input form="<?= $fid ?>" type="text" name="question_text" value="<?= e($q['question_text']) ?>" style="min-width:220px;"></td>
            <td>
              <select form="<?= $fid ?>" name="answer_type">
                <?php foreach ($typeLabels as $val => $label): ?>
                  <option value="<?= $val ?>" <?= $q['answer_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= (int) $q['sort_order'] ?>" style="width:80px;"></td>
            <td><input form="<?= $fid ?>" type="checkbox" name="is_active" <?= $q['is_active'] ? 'checked' : '' ?>></td>
            <td class="flex gap-sm">
              <button form="<?= $fid ?>" type="submit" class="btn btn-ghost btn-sm">Speichern</button>
              <button form="<?= $did ?>" type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Frage wirklich löschen? Bestehende Antworten dazu gehen verloren.');">Löschen</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php foreach ($questions as $q): ?>
  <form id="qf<?= (int) $q['id'] ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
  </form>
  <form id="qd<?= (int) $q['id'] ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
  </form>
<?php endforeach; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
