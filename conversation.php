<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_login();

$conv = direct_conversation_for_user((int)($_GET['id'] ?? 0), $u);
if (!$conv) { flash('error', 'That conversation was not found.'); redirect('messages.php'); }
$other = user_by_id((int)((int)$conv['user_one'] === (int)$u['id'] ? $conv['user_two'] : $conv['user_one']));

$direct = [
    'id' => (int)$conv['id'],
    'note' => 'Private conversation between you and ' . $other['full_name'] . ' (' . $other['role'] . ').'
              . ($conv['subject'] ? ' Subject: ' . $conv['subject'] : ''),
];
$title = $other['full_name'];
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('messages.php')) ?>">&larr; Messages</a></p>
<div class="page-head"><h1><?= e($other['full_name']) ?></h1><span class="muted"><?= e(ucfirst($other['role'])) ?></span></div>
<div class="panel narrow wide" style="padding:0;border:0;background:none"><?php require __DIR__ . '/includes/chat_panel.php'; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
