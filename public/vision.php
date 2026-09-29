<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/uploads.php';

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
$textFonts = ['hand'=>'Handschrift', 'script'=>'Verspielt', 'serif'=>'Elegant', 'marker'=>'Filzstift', 'sans'=>'Modern', 'mono'=>'Schreibmaschine'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_text') {
        $content = is_string($_POST['content'] ?? null) ? trim($_POST['content']) : '';
        $font = is_string($_POST['font'] ?? null) ? $_POST['font'] : '';
        $size = filter_var($_POST['font_size'] ?? null, FILTER_VALIDATE_INT);
        $id = (int) ($_POST['text_id'] ?? 0);
        if ($content === '' || mb_strlen($content) > 1000 || !isset($textFonts[$font]) || $size === false || $size < 16 || $size > 64) {
            $errors[] = 'Bitte gib einen Text mit höchstens 1000 Zeichen, eine Schrift und eine Größe von 16 bis 64 ein.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE vision_texts SET content=:content, font=:font, font_size=:size WHERE id=:id AND user_id=:uid');
                $stmt->execute(['content'=>$content, 'font'=>$font, 'size'=>$size, 'id'=>$id, 'uid'=>$user['id']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO vision_texts (user_id, content, font, font_size) VALUES (:uid,:content,:font,:size)');
                $stmt->execute(['uid'=>$user['id'], 'content'=>$content, 'font'=>$font, 'size'=>$size]);
            }
            flash('success', 'Text gespeichert.');
            redirect('/vision.php');
        }
    }
    if ($action === 'delete_text') {
        $stmt = $pdo->prepare('DELETE FROM vision_texts WHERE id=:id AND user_id=:uid');
        $stmt->execute(['id'=>(int) ($_POST['id'] ?? 0), 'uid'=>$user['id']]);
        redirect('/vision.php');
    }

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
            flash('success', 'Foto(s) hinzugefügt. Zieh sie an die gewünschte Stelle.');
            redirect('/vision.php');
        }
    }

    if ($action === 'set_background_style') {
        $style = $_POST['style'] ?? '';
        if ($style === 'slate') {
            $stmt = $pdo->prepare('UPDATE vision_boards SET background_type = "image", background_image = :image WHERE user_id = :uid');
            $stmt->execute(['image'=>'/assets/img/vision/slate.jpg', 'uid'=>$user['id']]);
            flash('success', 'Hintergrund gespeichert.');
        }
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
$stmt = $pdo->prepare('SELECT * FROM vision_texts WHERE user_id=:uid ORDER BY z_index, id');
$stmt->execute(['uid'=>$user['id']]);
$textItems = $stmt->fetchAll();
$editText = ['id'=>0, 'content'=>'', 'font'=>'hand', 'font_size'=>28];
foreach ($textItems as $textItem) {
    if ((int) $textItem['id'] === (int) ($_GET['edit_text'] ?? 0)) { $editText = $textItem; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_text' && $errors) {
    $editText = ['id'=>$id, 'content'=>$content, 'font'=>isset($textFonts[$font]) ? $font : 'hand', 'font_size'=>$size === false ? 28 : max(16, min(64, $size))];
}


$presetColors = ['#f3ecfb', '#e4d5f5', '#faf8fb', '#fdf6ec', '#fbedf8', '#ffffff'];
$boardStyles = ['slate' => 'Schiefer', 'chalk' => 'Wandtafel', 'cork' => 'Kork'];
$selectedMaterial = $board['background_type'] === 'image' && $board['background_image'] === '/assets/img/vision/slate.jpg' ? 'slate' : $board['background_type'];

$materialDescriptions = ['slate'=>'Grauer Naturstein', 'chalk'=>'Dunkelgrüne Tafel', 'cork'=>'Natürliche Korkstruktur'];
$boardClass = $selectedMaterial === 'slate' ? 'vision-bg-slate' : '';
$boardStyle = '';
if (in_array($board['background_type'], ['cork', 'chalk', 'metal'], true)) {
    $boardClass = 'vision-bg-' . $board['background_type'];
} elseif ($board['background_type'] === 'image' && $board['background_image']) {
    $boardStyle = 'background-image:url(' . e($board['background_image']) . '); background-size:cover; background-position:center;';
} else {
    $boardStyle = 'background:' . e($board['background_color']) . ';';
}

$stmt = $pdo->prepare('SELECT id FROM vision_manifestations WHERE user_id=? ORDER BY (id = COALESCE((SELECT manifestation_id FROM home_visions WHERE home_visions.user_id=vision_manifestations.user_id), 0)) DESC, id DESC LIMIT 1');
$stmt->execute([$user['id']]);
$homeVisionId = (int) $stmt->fetchColumn();

$savedPage = max(1, (int) ($_GET['saved_page'] ?? 1));
$savedOffset = ($savedPage - 1) * 6;
$stmt = $pdo->prepare('SELECT id, created_at FROM vision_manifestations WHERE user_id = :uid ORDER BY id DESC LIMIT 7 OFFSET :offset');
$stmt->bindValue(':uid', (int) $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $savedOffset, PDO::PARAM_INT);
$stmt->execute();
$savedVisions = $stmt->fetchAll();
$hasMoreVisions = count($savedVisions) > 6;
$savedVisions = array_slice($savedVisions, 0, 6);

$pageTitle = 'Visionboard';
$heroTone = 'purple';
$heroBadge = 'Visionboard';
$heroTitle = 'Deine Ziele';
$heroSubtitle = 'Dein Visionboard.';
require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/vision.css?v=<?= substr(hash_file('sha256', __DIR__ . '/assets/css/vision.css'), 0, 12) ?>">

<p><a href="/goals.php">&larr; Zurück zu deinen Zielen</a></p>

<?php foreach ($errors as $error): ?>
  <div class="flash flash-error"><?= e($error) ?></div>
<?php endforeach; ?>



<?php if (!empty($board['draft_backup'])): ?>
<form method="post" action="/vision_saved_action.php" class="mb-md">
<?= csrf_field() ?><input type="hidden" name="action" value="restore_draft">
<button class="btn btn-ghost btn-sm">Vorheriges Visionboard wieder öffnen</button>
</form>
<?php endif; ?>
<div class="vision-workspace">
<section class="vision-canvas-panel" aria-label="Dein Visionboard">
  <div class="vision-board-heading"><div><h2>Meine Vision</h2></div><span class="vision-count"><?= count($items) + count($textItems) ?> Elemente</span><button type="button" class="btn btn-ghost btn-sm vision-print-btn" id="visionPdfButton">PDF öffnen / drucken</button></div>
<div id="visionBoard" class="vision-board <?= e($boardClass) ?>" style="<?= $boardStyle ?>">
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
  <?php foreach ($textItems as $textItem): ?>
    <div class="vision-item vision-text-item font-<?= e(array_key_exists($textItem['font'], $textFonts) ? $textItem['font'] : 'hand') ?>" data-kind="text" data-id="<?= (int) $textItem['id'] ?>" style="left:<?= (int) $textItem['pos_x'] ?>px; top:<?= (int) $textItem['pos_y'] ?>px; z-index:<?= (int) $textItem['z_index'] ?>; --text-size:<?= max(16,min(64,(int) $textItem['font_size'])) ?>px;">
      <span class="vision-text-pin" aria-hidden="true"></span>
      <div class="vision-text-content"><?= e($textItem['content']) ?></div>
      <div class="vision-text-actions"><a href="/vision.php?edit_text=<?= (int) $textItem['id'] ?>#textEditor" aria-label="Text bearbeiten">✎</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_text"><input type="hidden" name="id" value="<?= (int) $textItem['id'] ?>"><button type="submit" aria-label="Text entfernen">×</button></form></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$items && !$textItems): ?>
    <div class="vision-empty"><span class="vision-empty-icon" aria-hidden="true">✧</span><h2>Platz für deine Träume.</h2><p class="small">Füge dein erstes Bild hinzu.</p></div>
  <?php endif; ?>
</div>

<div class="manifest-bar"><button type="button" class="btn btn-secondary" id="manifestButton" <?= !$items && !$textItems ? 'disabled' : '' ?>>✨ Manifestieren</button><span class="small text-muted">Als Bild und PDF festhalten</span></div>
<p id="manifestStatus" class="small" role="status" aria-live="polite"></p>
<p class="vision-board-help">Bilder verschieben · automatisch gespeichert</p>
</section>
<aside class="vision-tools" aria-label="Vision gestalten">
  <details class="card vision-tool vision-background-tool" open>
    <summary>Hintergrund</summary>
    <div class="background-options">
      <form method="post" class="background-swatches background-materials">
        <?= csrf_field() ?><input type="hidden" name="action" value="set_background_style">
        <?php foreach ($boardStyles as $styleKey=>$styleLabel): $selected = $selectedMaterial === $styleKey; ?>
          <button type="submit" name="style" value="<?= e($styleKey) ?>" class="background-circle vision-bg-<?= e($styleKey) ?> <?= $selected ? 'selected' : '' ?>" aria-label="<?= e($styleLabel) ?>" title="<?= e($styleLabel) ?>" aria-pressed="<?= $selected ? 'true' : 'false' ?>"></button>
        <?php endforeach; ?>
      </form>
      <form method="post" class="background-swatches background-colors">
        <?= csrf_field() ?><input type="hidden" name="action" value="set_background_color">
        <?php foreach ($presetColors as $color): $selected = $board['background_type'] === 'color' && strtolower($board['background_color']) === strtolower($color); ?>
          <button type="submit" name="background_color" value="<?= $color ?>" class="background-circle <?= $selected ? 'selected' : '' ?>" style="background:<?= $color ?>" aria-label="Hintergrundfarbe <?= $color ?>" title="<?= $color ?>" aria-pressed="<?= $selected ? 'true' : 'false' ?>"></button>
        <?php endforeach; ?>
      </form>
      <form method="post" class="background-swatches">
        <?= csrf_field() ?><input type="hidden" name="action" value="set_background_color">
        <label class="background-custom-color" title="Eigene Farbe wählen"><input aria-label="Eigene Hintergrundfarbe wählen" type="color" name="background_color" value="<?= e($board['background_color']) ?>" onchange="this.form.requestSubmit()"></label>
      </form>
      <form method="post" enctype="multipart/form-data" class="background-swatches">
        <?= csrf_field() ?><input type="hidden" name="action" value="set_background_image">
        <label class="background-upload" title="Eigenes Hintergrundbild wählen"><span aria-hidden="true">＋</span><input type="file" name="background" accept="image/*" aria-label="Eigenes Hintergrundbild wählen" onchange="if(this.files.length) this.form.requestSubmit()"></label>
      </form>
    </div>
  </details>
  <details class="card vision-tool" id="textEditor" <?= $editText['id'] || ($errors && ($_POST['action'] ?? '') === 'save_text') ? 'open' : '' ?>>
    <summary><?= $editText['id'] ? 'Text bearbeiten' : 'Text hinzufügen' ?></summary>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_text"><input type="hidden" name="text_id" value="<?= (int) $editText['id'] ?>">
      <div class="field"><label for="visionText">Deine Worte</label><textarea id="visionText" name="content" maxlength="1000" rows="3" required placeholder="Ich gestalte mein Leben …"><?= e($editText['content']) ?></textarea></div>
      <div class="field"><label for="visionFont">Schrift</label><select id="visionFont" name="font"><?php foreach ($textFonts as $key=>$label): ?><option value="<?= $key ?>" <?= $editText['font'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="visionSize">Schriftgröße</label><input id="visionSize" type="number" name="font_size" min="16" max="64" value="<?= (int) $editText['font_size'] ?>" required></div>
      <div id="textPreview" class="vision-text-preview" aria-label="Schriftvorschau"></div>
      <button class="btn btn-primary mt-sm"><?= $editText['id'] ? 'Änderungen speichern' : 'Aufs Board setzen' ?></button>
      <?php if ($editText['id']): ?><a href="/vision.php#textEditor" class="small">Neuen Text hinzufügen</a><?php endif; ?>
    </form>
  </details>
  <details class="card vision-tool" open>
    <summary>Bilder hinzufügen</summary>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <div class="field mb-0">
        <input type="file" aria-label="Bilder für dein Visionboard auswählen" name="photos[]" accept="image/*" multiple required>
      </div>
      <button type="submit" class="btn btn-primary mt-md">Hochladen</button>
    </form>
  </details>




</aside>

</div>

<section class="saved-visions" id="savedVisions" aria-labelledby="savedVisionsTitle">
  <h2 id="savedVisionsTitle">Deine manifestierten Visionen</h2>
  <?php if (isset($_GET['saved'])): ?><p class="flash flash-success">Deine Vision ist gespeichert. Mit „Startseite“ wählst du dein Bild für die Startseite.</p><?php endif; ?>
  <?php if (!$savedVisions): ?><p class="text-muted small">Deine gespeicherten Visionen erscheinen hier.</p><?php endif; ?>
  <div class="saved-vision-grid">
    <?php foreach ($savedVisions as $savedVision): ?>
      <article class="card saved-vision-card">
        <a href="/vision_file.php?id=<?= (int) $savedVision['id'] ?>&amp;format=pdf" target="_blank" rel="noopener" aria-label="Vision als PDF öffnen"><img src="/vision_file.php?id=<?= (int) $savedVision['id'] ?>" alt="Gespeicherte Vision vom <?= e((new DateTimeImmutable($savedVision['created_at']))->format('d.m.Y')) ?>" loading="lazy"></a>
        <p class="small text-muted"><?= e((new DateTimeImmutable($savedVision['created_at']))->format('d.m.Y · H:i')) ?></p>
        <div class="flex gap-sm" style="flex-wrap:wrap"><a class="btn btn-secondary btn-sm" href="/vision_file.php?id=<?= (int) $savedVision['id'] ?>&amp;format=pdf" target="_blank" rel="noopener">PDF öffnen / drucken</a><form method="post" action="/vision_saved_action.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $savedVision['id'] ?>"><input type="hidden" name="action" value="home">
<button type="submit" class="btn btn-ghost btn-sm" aria-pressed="<?= $homeVisionId === (int) $savedVision['id'] ? 'true' : 'false' ?>"><?= $homeVisionId === (int) $savedVision['id'] ? '✓ Startseite' : 'Startseite' ?></button>
</form></div>
        <div class="flex gap-sm mt-sm">
          <form method="post" action="/vision_saved_action.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $savedVision['id'] ?>"><input type="hidden" name="action" value="edit"><button type="submit" class="btn btn-secondary btn-sm">Bearbeiten</button></form>
          <details class="manifest-delete">
            <summary class="btn btn-ghost btn-sm">Löschen</summary>
            <form method="post" action="/vision_saved_action.php" class="manifest-delete-confirm">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $savedVision['id'] ?>"><input type="hidden" name="action" value="delete">
              <p class="small">Dieses gespeicherte Bild und seine PDF löschen?</p>
              <button type="submit" class="btn btn-ghost btn-sm">Ja, löschen</button>
            </form>
          </details>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="flex-between mt-sm">
    <?php if ($savedPage > 1): ?><a href="?saved_page=<?= $savedPage - 1 ?>#savedVisions">← Neuere Visionen</a><?php endif; ?>
    <?php if ($hasMoreVisions): ?><a href="?saved_page=<?= $savedPage + 1 ?>#savedVisions">Ältere Visionen →</a><?php endif; ?>
  </div>
</section>
<script src="/assets/js/html-to-image.js"></script>
<script src="/assets/js/manifest.js?v=3" defer></script>
<script>
(function () {
  var board = document.getElementById('visionBoard');
  var textInput = document.getElementById('visionText');
  var fontInput = document.getElementById('visionFont');
  var sizeInput = document.getElementById('visionSize');
  var preview = document.getElementById('textPreview');
  function updatePreview() {
    preview.textContent = textInput.value || 'Deine Vision beginnt hier.';
    preview.className = 'vision-text-preview font-' + fontInput.value;
    preview.style.fontSize = Math.max(16, Math.min(64, Number(sizeInput.value) || 28)) + 'px';
  }
  [textInput, fontInput, sizeInput].forEach(function(el) { el.addEventListener('input', updatePreview); });
  updatePreview();
  var csrfToken = document.querySelector('#visionBoard').closest('body').querySelector('input[name=csrf_token]').value;

  function savePosition(item) {
    var data = new URLSearchParams();
    data.set('csrf_token', csrfToken);
    data.set('id', item.dataset.id);
    data.set('kind', item.dataset.kind || 'image');
    data.set('x', parseInt(item.style.left, 10));
    data.set('y', parseInt(item.style.top, 10));
    fetch('/vision_position.php', { method: 'POST', body: data }).then(function(response) {
      if (!response.ok) throw new Error('save');
    }).catch(function() { alert('Position konnte nicht gespeichert werden. Bitte versuche es erneut.'); });
  }

  board.querySelectorAll('.vision-item').forEach(function (item) {
    var active = false, offsetX = 0, offsetY = 0, boardRect = null;

    item.addEventListener('pointerdown', function (e) {
      if (e.target.closest('a, button, input, textarea, select')) return;
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

    item.addEventListener('pointercancel', function () { active = false; });
    item.addEventListener('pointerup', function () {
      if (!active) return;
      active = false;
      savePosition(item);
    });
  });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
