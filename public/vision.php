<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/uploads.php';
require_once __DIR__ . '/../src/ai_image.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM vision_boards WHERE user_id = :uid');
$stmt->execute(['uid' => $user['id']]);
$board = $stmt->fetch();
if (!$board) {
    $pdo->prepare('INSERT INTO vision_boards (user_id) VALUES (:uid)')->execute(['uid' => $user['id']]);
    $board = ['user_id' => $user['id'], 'background_type' => 'color', 'background_color' => '#faf8fb', 'background_image' => null];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vision_items WHERE user_id = :uid');
        $stmt->execute(['uid' => $user['id']]);
        $count = (int) $stmt->fetchColumn();

        $files = $_FILES['photos'] ?? null;
        if ($files) {
            $fileCount = count((array) $files['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $single = [
                    'name' => $files['name'][$i], 'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i],
                ];
                try {
                    $path = store_uploaded_image($single, (int) $user['id'], 'vision');
                    $offset = ($count % 6) * 24;
                    $stmt = $pdo->prepare(
                        'INSERT INTO vision_items (user_id, image_path, pos_x, pos_y, z_index, source)
                         VALUES (:uid, :path, :x, :y, :z, "upload")'
                    );
                    $stmt->execute(['uid' => $user['id'], 'path' => $path, 'x' => 20 + $offset, 'y' => 20 + $offset, 'z' => $count + 1]);
                    $count++;
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        if (!$errors) {
            flash('success', 'Foto(s) hinzugefuegt. Zieh sie an die gewuenschte Stelle.');
            redirect('/vision.php');
        }
    }

    if ($action === 'generate_ai') {
        $prompt = trim($_POST['prompt'] ?? '');
        if ($prompt === '') {
            $errors[] = 'Bitte beschreibe, was die KI zeichnen soll.';
        } else {
            try {
                $path = generate_ai_image($prompt, (int) $user['id']);
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM vision_items WHERE user_id = :uid');
                $stmt->execute(['uid' => $user['id']]);
                $count = (int) $stmt->fetchColumn();
                $offset = ($count % 6) * 24;
                $stmt = $pdo->prepare(
                    'INSERT INTO vision_items (user_id, image_path, pos_x, pos_y, z_index, caption, source)
                     VALUES (:uid, :path, :x, :y, :z, :caption, "ai")'
                );
                $stmt->execute([
                    'uid' => $user['id'], 'path' => $path, 'x' => 20 + $offset, 'y' => 20 + $offset,
                    'z' => $count + 1, 'caption' => mb_substr($prompt, 0, 200),
                ]);
                flash('success', 'Bild erzeugt und aufs Board gelegt.');
                redirect('/vision.php');
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if ($action === 'set_background_style') {
        $style = $_POST['style'] ?? '';
        if (in_array($style, ['cork', 'chalk', 'metal'], true)) {
            $stmt = $pdo->prepare('UPDATE vision_boards SET background_type = :style WHERE user_id = :uid');
            $stmt->execute(['style' => $style, 'uid' => $user['id']]);
            flash('success', 'Hintergrund gespeichert.');
        }
        redirect('/vision.php');
    }

    if ($action === 'set_background_color') {
        $color = $_POST['background_color'] ?? '#faf8fb';
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $stmt = $pdo->prepare('UPDATE vision_boards SET background_type = "color", background_color = :color WHERE user_id = :uid');
            $stmt->execute(['color' => $color, 'uid' => $user['id']]);
            flash('success', 'Hintergrund gespeichert.');
        }
        redirect('/vision.php');
    }

    if ($action === 'set_background_image' && !empty($_FILES['background']['name'])) {
        try {
            $path = store_uploaded_image($_FILES['background'], (int) $user['id'], 'vision-bg');
            $stmt = $pdo->prepare('UPDATE vision_boards SET background_type = "image", background_image = :img WHERE user_id = :uid');
            $stmt->execute(['img' => $path, 'uid' => $user['id']]);
            flash('success', 'Hintergrundbild gespeichert.');
            redirect('/vision.php');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($action === 'delete_item') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM vision_items WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $user['id']]);
        redirect('/vision.php');
    }
}

$stmt = $pdo->prepare('SELECT * FROM vision_items WHERE user_id = :uid ORDER BY z_index');
$stmt->execute(['uid' => $user['id']]);
$items = $stmt->fetchAll();

$presetColors = ['#faf8fb', '#fdf6ec', '#eef6ee', '#eef1fb', '#201a2b', '#ffffff'];
$boardStyles = ['cork' => 'Korkwand', 'chalk' => 'Wandtafel', 'metal' => 'Magnetwand'];

$boardClass = '';
$boardStyle = '';
if (array_key_exists($board['background_type'], $boardStyles)) {
    $boardClass = 'vision-bg-' . $board['background_type'];
} elseif ($board['background_type'] === 'image' && $board['background_image']) {
    $boardStyle = 'background-image:url(' . e($board['background_image']) . '); background-size:cover; background-position:center;';
} else {
    $boardStyle = 'background:' . e($board['background_color']) . ';';
}

$pageTitle = 'Visionboard';
$heroTone = 'magenta';
$heroBadge = 'Visionboard';
$heroTitle = 'Deine Vision';
$heroSubtitle = 'Fotos platzieren, Hintergrund waehlen, mit KI neue Bilder fuer deine Visionen erzeugen.';
require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/vision.css">

<p><a href="/goals.php">&larr; Zurueck zu deinen Zielen</a></p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="card-grid">
  <div class="card">
    <h3>Fotos hochladen</h3>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <div class="field mb-0">
        <input type="file" name="photos[]" accept="image/*" multiple required>
      </div>
      <button type="submit" class="btn btn-primary mt-md">Hochladen</button>
    </form>
  </div>

  <div class="card">
    <h3>Hintergrund</h3>
    <form method="post" class="flex gap-md mb-0" style="flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_background_style">
      <?php foreach ($boardStyles as $styleKey => $styleLabel): ?>
        <button type="submit" name="style" value="<?= $styleKey ?>" class="vision-style-swatch vision-bg-<?= $styleKey ?> <?= $board['background_type'] === $styleKey ? 'selected' : '' ?>" aria-label="<?= $styleLabel ?>">
          <span><?= $styleLabel ?></span>
        </button>
      <?php endforeach; ?>
    </form>

    <p class="small text-muted mt-md mb-0">Oder eigene Farbe/Bild:</p>
    <form method="post" class="flex gap-sm mt-sm" style="flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_background_color">
      <?php foreach ($presetColors as $color): ?>
        <button type="submit" name="background_color" value="<?= $color ?>" class="btn btn-sm" style="background:<?= $color ?>; border:1.5px solid var(--color-border); width:34px; height:34px; padding:0; border-radius:50%;" aria-label="Hintergrundfarbe <?= $color ?>"></button>
      <?php endforeach; ?>
      <input type="color" name="background_color" value="<?= e($board['background_color']) ?>" onchange="this.form.submit()" style="width:34px; height:34px; padding:0; border:none; border-radius:50%; cursor:pointer;">
    </form>
    <form method="post" enctype="multipart/form-data" class="mt-md">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_background_image">
      <label class="small text-muted">Oder eigenes Hintergrundbild</label>
      <div class="flex gap-sm mt-sm">
        <input type="file" name="background" accept="image/*" style="flex:1;">
        <button type="submit" class="btn btn-ghost btn-sm">Setzen</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>Mit KI erzeugen</h3>
    <p class="small text-muted">Beschreibe deine Vision, die KI erstellt dazu ein Bild.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="generate_ai">
      <textarea name="prompt" rows="2" placeholder="z.B. ein helles Studio mit Blick aufs Meer" required></textarea>
      <button type="submit" class="btn btn-accent mt-md">Bild generieren</button>
    </form>
  </div>
</div>

<div id="visionBoard" class="vision-board mt-md <?= e($boardClass) ?>" style="<?= $boardStyle ?>">
  <?php foreach ($items as $item): ?>
    <div class="vision-item" data-id="<?= (int) $item['id'] ?>" style="left:<?= (int) $item['pos_x'] ?>px; top:<?= (int) $item['pos_y'] ?>px; width:<?= (int) $item['width'] ?>px; z-index:<?= (int) $item['z_index'] ?>;">
      <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['caption'] ?? '') ?>" draggable="false">
      <form method="post" class="vision-delete-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_item">
        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <button type="submit" class="vision-delete" aria-label="Entfernen">&times;</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$items): ?>
    <p class="vision-empty">Lade ein Foto hoch oder lass eines von der KI erzeugen - dann zieh es einfach an seinen Platz.</p>
  <?php endif; ?>
</div>

<script>
(function () {
  var board = document.getElementById('visionBoard');
  var csrfToken = document.querySelector('#visionBoard').closest('body').querySelector('input[name=csrf_token]').value;

  function savePosition(item) {
    var data = new URLSearchParams();
    data.set('csrf_token', csrfToken);
    data.set('id', item.dataset.id);
    data.set('x', parseInt(item.style.left, 10));
    data.set('y', parseInt(item.style.top, 10));
    fetch('/vision_position.php', { method: 'POST', body: data });
  }

  board.querySelectorAll('.vision-item').forEach(function (item) {
    var active = false, offsetX = 0, offsetY = 0, boardRect = null;

    item.addEventListener('pointerdown', function (e) {
      if (e.target.closest('.vision-delete')) return;
      active = true;
      boardRect = board.getBoundingClientRect();
      offsetX = e.clientX - item.offsetLeft;
      offsetY = e.clientY - item.offsetTop;
      item.style.zIndex = 999;
      item.setPointerCapture(e.pointerId);
    });

    item.addEventListener('pointermove', function (e) {
      if (!active) return;
      var x = e.clientX - offsetX;
      var y = e.clientY - offsetY;
      x = Math.max(0, Math.min(x, boardRect.width - item.offsetWidth));
      y = Math.max(0, Math.min(y, boardRect.height - item.offsetHeight));
      item.style.left = x + 'px';
      item.style.top = y + 'px';
    });

    item.addEventListener('pointerup', function () {
      if (!active) return;
      active = false;
      savePosition(item);
    });
  });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
