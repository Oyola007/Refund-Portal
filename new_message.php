<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_login();

$targets = message_targets($u);
$errors = [];
$to = (int)($_GET['to'] ?? 0);
$subject = '';
$body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = (int)($_POST['to'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    $recipient = null;
    foreach ($targets as $t) { if ((int)$t['id'] === $to) $recipient = $t; }
    if (!$recipient || !can_message($u, $recipient)) $errors[] = 'Choose who you want to message.';
    if (mb_strlen($subject) > 150) $errors[] = 'Keep the subject under 150 characters.';
    if (mb_strlen($body) > 4000) $errors[] = 'The message is too long. Keep it under 4000 characters.';

    $accepted = [];
    try { $accepted = validate_uploads(collect_uploads('files')); }
    catch (UploadException $ex) { $errors[] = $ex->getMessage(); }
    if (!$errors && $body === '' && !$accepted) $errors[] = 'Write a message or attach a file.';

    if (!$errors) {
        try {
            $convId = get_or_create_direct($u, $recipient, $subject !== '' ? $subject : null);
            $alreadyAlerted = unread_from_me($convId, (int)$u['id']) > 0;
            save_message($convId, (int)$u['id'], $body, $accepted);
            if (!$alreadyAlerted) notify_recipients($u, ['kind' => 'direct', 'other' => $recipient, 'refund' => null]);
            redirect('conversation.php?id=' . $convId);
        } catch (Throwable $ex) {
            error_log('new_message: ' . $ex->getMessage());
            $errors[] = 'The message could not be sent. Please try again.';
        }
    }
}

$groups = [];
foreach ($targets as $t) $groups[$t['role']][] = $t;
$roleLabel = ['admin' => 'Admin', 'finance' => 'Finance', 'customer' => 'Customers'];

$title = 'New conversation';
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('messages.php')) ?>">&larr; Messages</a></p>
<section class="panel narrow wide">
  <h1>New conversation</h1>
  <p class="muted">Only you and the person you choose can read it. If you already have a conversation with them, the message is added to it.</p>
  <?php foreach ($errors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <label>Send to
      <select name="to" required>
        <option value="">Choose a person</option>
        <?php foreach ($groups as $role => $people): ?>
          <optgroup label="<?= e($roleLabel[$role] ?? ucfirst($role)) ?>">
            <?php foreach ($people as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $to === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?><?= $role === 'customer' ? ' (' . e($p['email']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Subject <span class="hint">(optional)</span> <input name="subject" value="<?= e($subject) ?>" maxlength="150"></label>
    <label>Message <textarea name="body" rows="5" maxlength="4000"><?= e($body) ?></textarea></label>
    <label>Attach files <span class="hint">(photos, PDF, Word or video; up to <?= MAX_FILES_PER_MESSAGE ?> files, 25 MB each)</span>
      <input type="file" name="files[]" multiple accept="image/*,video/*,.pdf,.doc,.docx">
    </label>
    <div><button class="btn" type="submit">Send</button></div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
