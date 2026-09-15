<?php
require __DIR__ . '/../../src/admin_bootstrap.php';
require_admin();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM email_templates WHERE id = :id');
$stmt->execute(['id' => $id]);
$template = $stmt->fetch();

if (!$template) {
    flash('error', 'Vorlage nicht gefunden.');
    redirect('/admin/templates.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = $_POST['body_html'] ?? '';

    if ($name === '' || $subject === '' || trim($body) === '') {
        $errors[] = 'Bitte fuelle alle Felder aus.';
    } else {
        $stmt = $pdo->prepare('UPDATE email_templates SET name = :name, subject = :subject, body_html = :body WHERE id = :id');
        $stmt->execute(['name' => $name, 'subject' => $subject, 'body' => $body, 'id' => $id]);
        flash('success', 'Vorlage gespeichert.');
        redirect('/admin/templates.php');
    }

    $template['name'] = $name;
    $template['subject'] = $subject;
    $template['body_html'] = $body;
}

$pageTitle = 'Vorlage bearbeiten';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/admin/templates.php">&larr; Zurueck zu allen Vorlagen</a></p>
<h2><?= e($template['name']) ?></h2>
<p class="text-muted small">Schluessel: <code><?= e($template['template_key']) ?></code> · Platzhalter: <code>{{name}}</code>, <code>{{email}}</code></p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="field">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" required value="<?= e($template['name']) ?>">
  </div>
  <div class="field">
    <label for="subject">Betreff</label>
    <input type="text" id="subject" name="subject" required value="<?= e($template['subject']) ?>">
  </div>
  <div class="field mb-0">
    <label for="body_html">Inhalt (HTML)</label>
    <textarea id="body_html" name="body_html" rows="12" required><?= e($template['body_html']) ?></textarea>
  </div>
  <button type="submit" class="btn btn-primary mt-md">Speichern</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
