<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();
$goalId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM goals WHERE id = :id AND user_id = :uid');
$stmt->execute(['id' => $goalId, 'uid' => $user['id']]);
$goal = $stmt->fetch();

if (!$goal) {
    flash('error', 'Ziel nicht gefunden.');
    redirect('/goals.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'log') {
        $logDate = $_POST['log_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'done';
        $note = trim($_POST['note'] ?? '');
        if (!in_array($status, ['done', 'partial', 'not_done'], true)) {
            $status = 'done';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO goal_logs (goal_id, log_date, status, note)
             VALUES (:gid, :date, :status, :note)
             ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)'
        );
        $stmt->execute([
            'gid'    => $goal['id'],
            'date'   => $logDate,
            'status' => $status,
            'note'   => $note !== '' ? $note : null,
        ]);
        flash('success', 'Status fuer den ' . $logDate . ' gespeichert.');
        redirect('/goal.php?id=' . $goal['id']);
    }

    if ($action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $frequencyType = $_POST['frequency_type'] ?? 'weekly';
        $frequencyTarget = max(1, (int) ($_POST['frequency_target'] ?? 1));
        if (!in_array($frequencyType, ['daily', 'weekly', 'monthly'], true)) {
            $frequencyType = 'weekly';
        }

        if ($title === '') {
            $errors[] = 'Bitte gib einen Titel ein.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE goals SET title = :title, description = :description, frequency_type = :freq, frequency_target = :target WHERE id = :id'
            );
            $stmt->execute([
                'title'       => $title,
                'description' => $description !== '' ? $description : null,
                'freq'        => $frequencyType,
                'target'      => $frequencyTarget,
                'id'          => $goal['id'],
            ]);
            flash('success', 'Ziel aktualisiert.');
            redirect('/goal.php?id=' . $goal['id']);
        }
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM goals WHERE user_id = :uid AND is_active = 1');
        $stmt->execute(['uid' => $user['id']]);
        $activeCount = (int) $stmt->fetchColumn();

        if ($activeCount <= 1) {
            flash('error', 'Du musst mindestens ein Ziel behalten. Lege zuerst ein neues an, bevor du dieses loeschst.');
            redirect('/goal.php?id=' . $goal['id']);
        }

        $stmt = $pdo->prepare('DELETE FROM goals WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $goal['id'], 'uid' => $user['id']]);
        flash('success', 'Ziel geloescht.');
        redirect('/goals.php');
    }
}

// neu laden nach evtl. Update
$stmt = $pdo->prepare('SELECT * FROM goals WHERE id = :id AND user_id = :uid');
$stmt->execute(['id' => $goalId, 'uid' => $user['id']]);
$goal = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM goal_logs WHERE goal_id = :gid ORDER BY log_date DESC LIMIT 20');
$stmt->execute(['gid' => $goal['id']]);
$logs = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM goals WHERE user_id = :uid AND is_active = 1');
$stmt->execute(['uid' => $user['id']]);
$isOnlyGoal = (int) $stmt->fetchColumn() <= 1;

$freqLabels = ['daily' => 'Taeglich', 'weekly' => 'Woechentlich', 'monthly' => 'Monatlich'];
$statusLabels = ['done' => 'Erledigt', 'partial' => 'Teilweise', 'not_done' => 'Nicht erledigt'];

$pageTitle = $goal['title'];
$heroTone = 'purple';
$heroBadge = $freqLabels[$goal['frequency_type']];
$heroTitle = $goal['title'];
$heroSubtitle = $goal['description'] ?: '';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/goals.php">&larr; Zurueck zu allen Zielen</a></p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card-grid">
  <div class="card">
    <h3>Status erfassen</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="log">
      <div class="field">
        <label for="log_date">Datum</label>
        <input type="date" id="log_date" name="log_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
      </div>
      <div class="field">
        <label>Status</label>
        <div class="yesno-row">
          <label><input type="radio" name="status" value="done" checked> Erledigt</label>
          <label><input type="radio" name="status" value="partial"> Teilweise</label>
          <label><input type="radio" name="status" value="not_done"> Nicht erledigt</label>
        </div>
      </div>
      <div class="field mb-0">
        <label for="note">Notiz (optional)</label>
        <input type="text" id="note" name="note">
      </div>
      <button type="submit" class="btn btn-primary mt-md">Status speichern</button>
    </form>
  </div>

  <div class="card">
    <h3>Ziel bearbeiten</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <div class="field">
        <label for="title">Titel</label>
        <input type="text" id="title" name="title" required value="<?= e($goal['title']) ?>">
      </div>
      <div class="field">
        <label for="description">Beschreibung</label>
        <textarea id="description" name="description" rows="2"><?= e($goal['description'] ?? '') ?></textarea>
      </div>
      <div class="field flex gap-md" style="flex-wrap:wrap;">
        <div style="flex:1; min-width:140px;">
          <label for="frequency_type">Haeufigkeit</label>
          <select id="frequency_type" name="frequency_type">
            <?php foreach ($freqLabels as $val => $label): ?>
              <option value="<?= $val ?>" <?= $goal['frequency_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1; min-width:140px;">
          <label for="frequency_target">Ziel pro Periode</label>
          <input type="number" id="frequency_target" name="frequency_target" min="1" value="<?= (int) $goal['frequency_target'] ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-secondary mt-sm">Aenderungen speichern</button>
    </form>

    <hr class="divider">

    <form method="post" onsubmit="return confirm('Dieses Ziel wirklich loeschen?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="btn btn-danger btn-sm" <?= $isOnlyGoal ? 'disabled' : '' ?>>Ziel loeschen</button>
      <?php if ($isOnlyGoal): ?>
        <div class="hint">Dies ist dein einziges Ziel und kann nicht geloescht werden.</div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card mt-md">
  <h3>Verlauf</h3>
  <?php if (empty($logs)): ?>
    <p class="text-muted">Noch keine Eintraege.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Datum</th><th>Status</th><th>Notiz</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= e((new DateTimeImmutable($log['log_date']))->format('d.m.Y')) ?></td>
              <td><span class="status-pill <?= e($log['status']) ?>"><?= $statusLabels[$log['status']] ?></span></td>
              <td><?= e($log['note'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
