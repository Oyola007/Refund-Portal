<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_login();

[$where, $scopeArgs] = inbox_scope($u);
$s = db()->prepare("SELECT cv.id, cv.kind, cv.subject, cv.refund_id, cv.last_message_at, r.ref_no,
                           IF(cv.user_one = ?, cv.user_two, cv.user_one) AS other_id,
                           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = cv.id AND m.sender_id <> ? AND m.read_at IS NULL) AS unread,
                           (SELECT COALESCE(NULLIF(m2.body, ''), 'Attachment') FROM messages m2 WHERE m2.conversation_id = cv.id ORDER BY m2.id DESC LIMIT 1) AS preview
                    FROM conversations cv LEFT JOIN refund_requests r ON r.id = cv.refund_id
                    WHERE $where ORDER BY cv.last_message_at DESC LIMIT 100");
$s->execute(array_merge([$u['id'], $u['id']], $scopeArgs));
$rows = $s->fetchAll();

// Names of the other person in direct chats
$names = [];
$ids = array_values(array_unique(array_filter(array_column($rows, 'other_id'))));
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q = db()->prepare("SELECT id, full_name, role FROM users WHERE id IN ($in)");
    $q->execute($ids);
    foreach ($q->fetchAll() as $x) $names[$x['id']] = $x;
}

$refundPage = ['customer' => 'customer/refund.php', 'admin' => 'admin/refund.php', 'finance' => 'finance/pay.php'][$u['role']];
$title = 'Messages';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1>Messages</h1>
  <a class="btn" href="<?= e(url('new_message.php')) ?>">New conversation</a>
</div>
<section class="panel">
  <?php if (!$rows): ?>
    <p class="empty">No conversations yet. Use New conversation to message someone.</p>
  <?php else: ?>
  <ul class="thread-list">
    <?php foreach ($rows as $r):
        if ($r['kind'] === 'direct') {
            $o = $names[$r['other_id']] ?? ['full_name' => 'Unknown', 'role' => ''];
            $title1 = $o['full_name'] . ($o['role'] ? ' (' . $o['role'] . ')' : '');
            $sub = $r['subject'] ?: 'Direct conversation';
            $link = url('conversation.php?id=' . $r['id']);
        } else {
            $title1 = 'Refund ' . $r['ref_no'];
            $sub = thread_label($u['role'], $r['kind']);
            $link = url($refundPage . '?id=' . $r['refund_id'] . '&thread=' . $r['kind']);
        }
    ?>
    <li>
      <a href="<?= e($link) ?>">
        <span><span class="thread-title"><?= e($title1) ?></span> <span class="thread-sub">&middot; <?= e($sub) ?></span></span>
        <span class="thread-meta"><?= $r['last_message_at'] ? e(when($r['last_message_at'])) : '' ?><?= $r['unread'] ? '<span class="dot">' . (int)$r['unread'] . '</span>' : '' ?></span>
        <span class="thread-prev"><?= e(mb_strimwidth((string)$r['preview'], 0, 100, '...')) ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
