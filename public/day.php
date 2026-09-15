<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
touch_user_activity((int) $user['id']);

$dateParam = $_GET['date'] ?? date('Y-m-d');
$date = DateTimeImmutable::createFromFormat('Y-m-d', $dateParam);
if ($date === false) {
    $date = new DateTimeImmutable('today');
}
$dateStr = $date->format('Y-m-d');

$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM questions WHERE is_active = 1 ORDER BY sort_order, id');
$stmt->execute();
$questions = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM diary_entries WHERE user_id = :uid AND entry_date = :date');
$stmt->execute(['uid' => $user['id'], 'date' => $dateStr]);
$entry = $stmt->fetch();

$answers = [];
if ($entry) {
    $stmt = $pdo->prepare('SELECT question_id, answer_text FROM diary_answers WHERE diary_entry_id = :eid');
    $stmt->execute(['eid' => $entry['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $answers[(int) $row['question_id']] = $row['answer_text'];
    }
}

$freeText = $entry['free_text'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $freeText = trim($_POST['free_text'] ?? '');

    $pdo->beginTransaction();
    try {
        if ($entry) {
            $stmt = $pdo->prepare('UPDATE diary_entries SET free_text = :text WHERE id = :id');
            $stmt->execute(['text' => $freeText, 'id' => $entry['id']]);
            $entryId = (int) $entry['id'];
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO diary_entries (user_id, entry_date, free_text) VALUES (:uid, :date, :text)'
            );
            $stmt->execute(['uid' => $user['id'], 'date' => $dateStr, 'text' => $freeText]);
            $entryId = (int) $pdo->lastInsertId();
        }

        $upsert = $pdo->prepare(
            'INSERT INTO diary_answers (diary_entry_id, question_id, answer_text)
             VALUES (:eid, :qid, :answer)
             ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text)'
        );

        foreach ($questions as $question) {
            $qid = (int) $question['id'];
            $answer = trim((string) ($_POST['question'][$qid] ?? ''));
            $upsert->execute(['eid' => $entryId, 'qid' => $qid, 'answer' => $answer]);
            $answers[$qid] = $answer;
        }

        $pdo->commit();
        flash('success', 'Eintrag fuer den ' . $date->format('d.m.Y') . ' gespeichert.');
        redirect('/day.php?date=' . $dateStr);
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Der Eintrag konnte nicht gespeichert werden.');
    }
}

$pageTitle = 'Eintrag ' . $date->format('d.m.Y');
$heroTone = 'purple';
$heroBadge = 'Tagebuch';
$heroTitle = $date->format('d.m.Y');
$heroSubtitle = 'Beantworte die Fragen und halte fest, was dich heute beschaeftigt.';
require __DIR__ . '/partials/header.php';
?>

<p><a href="/dashboard.php">&larr; Zurueck zum Kalender</a></p>

<form method="post">
  <?= csrf_field() ?>

  <?php foreach ($questions as $question):
      $qid = (int) $question['id'];
      $value = $answers[$qid] ?? '';
  ?>
    <div class="card">
      <label for="q<?= $qid ?>" style="font-weight:700; display:block; margin-bottom:12px;"><?= e($question['question_text']) ?></label>

      <?php if ($question['answer_type'] === 'scale_1_5'): ?>
        <div class="scale-row">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <label>
              <input type="radio" name="question[<?= $qid ?>]" value="<?= $i ?>" <?= (string) $value === (string) $i ? 'checked' : '' ?>>
              <?= $i ?>
            </label>
          <?php endfor; ?>
        </div>

      <?php elseif ($question['answer_type'] === 'yesno'): ?>
        <div class="yesno-row">
          <label><input type="radio" name="question[<?= $qid ?>]" value="ja" <?= $value === 'ja' ? 'checked' : '' ?>> Ja</label>
          <label><input type="radio" name="question[<?= $qid ?>]" value="nein" <?= $value === 'nein' ? 'checked' : '' ?>> Nein</label>
        </div>

      <?php elseif ($question['answer_type'] === 'number'): ?>
        <input type="number" step="any" id="q<?= $qid ?>" name="question[<?= $qid ?>]" value="<?= e($value) ?>">

      <?php else: ?>
        <textarea id="q<?= $qid ?>" name="question[<?= $qid ?>]" rows="2"><?= e($value) ?></textarea>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <label for="free_text" style="font-weight:700; display:block; margin-bottom:12px;">Freitext</label>
    <textarea id="free_text" name="free_text" rows="6" placeholder="Wie war dein Tag?"><?= e($freeText) ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary mt-md">Eintrag speichern</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
