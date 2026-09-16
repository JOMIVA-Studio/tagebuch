<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $frequencyType = $_POST['frequency_type'] ?? 'weekly';
    $frequencyTarget = max(1, (int) ($_POST['frequency_target'] ?? 1));
    $visionItemId = (int) ($_POST['vision_item_id'] ?? 0);

    if (!in_array($frequencyType, ['daily', 'weekly', 'monthly'], true)) {
        $frequencyType = 'weekly';
    }
    if ($title === '') {
        $errors[] = 'Bitte gib einen Titel fuer das Ziel ein.';
    }

    if (!$errors) {
        if ($visionItemId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM vision_items WHERE id = :id AND user_id = :uid');
            $stmt->execute(['id' => $visionItemId, 'uid' => $user['id']]);
            if (!$stmt->fetch()) {
                $visionItemId = 0;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO goals (user_id, title, description, vision_item_id, frequency_type, frequency_target)
             VALUES (:uid, :title, :description, :vision_item_id, :freq, :target)'
        );
        $stmt->execute([
            'uid'            => $user['id'],
            'title'          => $title,
            'description'    => $description !== '' ? $description : null,
            'vision_item_id' => $visionItemId > 0 ? $visionItemId : null,
            'freq'           => $frequencyType,
            'target'         => $frequencyTarget,
        ]);
        flash('success', 'Ziel "' . $title . '" wurde angelegt.');
        redirect('/goals.php');
    }
}

$stmt = $pdo->prepare('SELECT id, image_path FROM vision_items WHERE user_id = :uid ORDER BY z_index DESC');
$stmt->execute(['uid' => $user['id']]);
$visionItems = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT g.*, vi.image_path AS vision_image_path
     FROM goals g
     LEFT JOIN vision_items vi ON vi.id = g.vision_item_id
     WHERE g.user_id = :uid AND g.is_active = 1
     ORDER BY g.created_at DESC'
);
$stmt->execute(['uid' => $user['id']]);
$goals = $stmt->fetchAll();

function period_bounds(string $type): array
{
    $today = new DateTimeImmutable('today');
    return match ($type) {
        'daily'   => [$today, $today],
        'monthly' => [$today->modify('first day of this month'), $today->modify('last day of this month')],
        default   => [$today->modify('monday this week'), $today->modify('sunday this week')],
    };
}

$progressStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM goal_logs WHERE goal_id = :gid AND status = 'done' AND log_date BETWEEN :start AND :end"
);

$showForm = isset($_GET['new']) || empty($goals);

$freqLabels = ['daily' => 'taeglich', 'weekly' => 'woechentlich', 'monthly' => 'monatlich'];

$pageTitle = 'Ziele';
$heroTone = 'magenta';
$heroBadge = 'Regelmaessigkeit';
$heroTitle = 'Deine Ziele';
$heroSubtitle = 'Lege Ziele mit Wunsch-Haeufigkeit an und verfolge deinen Fortschritt.';
require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/vision.css">

<div class="flex-between">
  <h2 class="mb-0">Aktive Ziele</h2>
  <?php if (!$showForm): ?>
    <a href="/goals.php?new=1" class="btn btn-primary btn-sm">+ Neues Ziel</a>
  <?php endif; ?>
</div>

<?php if ($showForm): ?>
  <div class="card mt-md">
    <h3>Neues Ziel anlegen</h3>
    <?php foreach ($errors as $error): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label for="title">Titel</label>
        <input type="text" id="title" name="title" required placeholder="z.B. Meditieren" value="<?= e($_POST['title'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="description">Beschreibung (optional)</label>
        <textarea id="description" name="description" rows="2"><?= e($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label>Bild vom Visionboard (optional)</label>
        <?php if ($visionItems): ?>
          <div class="vision-picker">
            <label class="vision-picker-option">
              <input type="radio" name="vision_item_id" value="0" checked>
              <span class="vision-picker-none">Kein Bild</span>
            </label>
            <?php foreach ($visionItems as $vi): ?>
              <label class="vision-picker-option">
                <input type="radio" name="vision_item_id" value="<?= (int) $vi['id'] ?>">
                <img src="<?= e($vi['image_path']) ?>" alt="">
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="small text-muted mb-0">Noch keine Fotos auf deinem <a href="/vision.php">Visionboard</a>. Lade zuerst welche hoch, dann kannst du eines hier auswaehlen.</p>
        <?php endif; ?>
      </div>
      <div class="field flex gap-md" style="flex-wrap:wrap;">
        <div style="flex:1; min-width:160px;">
          <label for="frequency_type">Haeufigkeit</label>
          <select id="frequency_type" name="frequency_type">
            <option value="daily">Taeglich</option>
            <option value="weekly" selected>Woechentlich</option>
            <option value="monthly">Monatlich</option>
          </select>
        </div>
        <div style="flex:1; min-width:160px;">
          <label for="frequency_target">Wie oft pro Periode?</label>
          <input type="number" id="frequency_target" name="frequency_target" min="1" value="1">
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-sm">Ziel speichern</button>
      <?php if (!empty($goals)): ?>
        <a href="/goals.php" class="btn btn-ghost mt-sm">Abbrechen</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<?php if (empty($goals)): ?>
  <div class="empty-state">Noch keine Ziele vorhanden. Lege oben dein erstes Ziel an - mindestens eines ist erforderlich.</div>
<?php else: ?>
  <div class="card-grid mt-md">
    <?php foreach ($goals as $goal):
        [$start, $end] = period_bounds($goal['frequency_type']);
        $progressStmt->execute(['gid' => $goal['id'], 'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')]);
        $done = (int) $progressStmt->fetchColumn();
        $target = (int) $goal['frequency_target'];
        $percent = $target > 0 ? min(100, (int) round($done / $target * 100)) : 0;
    ?>
      <a href="/goal.php?id=<?= (int) $goal['id'] ?>" class="card goal-card card-accent-left <?= ['weekly'=>'','daily'=>'purple','monthly'=>'magenta'][$goal['frequency_type']] ?>">
        <?php if ($goal['vision_image_path']): ?>
          <img class="goal-card-image" src="<?= e($goal['vision_image_path']) ?>" alt="">
        <?php endif; ?>
        <div class="goal-title">
          <h3 class="mb-0"><?= e($goal['title']) ?></h3>
        </div>
        <?php if ($goal['description']): ?><p class="text-muted small mb-0"><?= e($goal['description']) ?></p><?php endif; ?>
        <div class="goal-progress"><div class="goal-progress-bar" style="width: <?= $percent ?>%;"></div></div>
        <div class="goal-meta"><?= $done ?> / <?= $target ?> · <?= $freqLabels[$goal['frequency_type']] ?></div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
