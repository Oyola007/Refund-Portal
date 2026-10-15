<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('customer');

$s = db()->prepare("SELECT r.*, (SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
                     WHERE c.refund_id = r.id AND c.kind IN ('customer_admin','customer_finance') AND m.sender_id <> ? AND m.read_at IS NULL) AS unread
                    FROM refund_requests r WHERE r.user_id = ? ORDER BY r.created_at DESC");
$s->execute([$u['id'], $u['id']]);
$rows = $s->fetchAll();

$counts = array_fill_keys(array_keys(STATUSES), 0);
foreach ($rows as $r) $counts[$r['status']]++;

$title = 'My refunds';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h1>My refunds</h1>
  <a class="btn" href="<?= e(url('customer/new_refund.php')) ?>">New refund</a>
</div>

<div class="stats">
  <?php foreach (STATUSES as $k => $label): ?>
    <div class="stat"><span class="num"><?= $counts[$k] ?></span><span class="lbl"><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>

<section class="panel">
  <?php if (!$rows): ?>
    <p class="empty">You have not requested a refund yet. <a href="<?= e(url('customer/new_refund.php')) ?>">Start your first request</a>.</p>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Reference</th><th>Flight</th><th>Amount</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="mono"><?= e($r['ref_no']) ?></td>
        <td><?= e($r['flight_numbers']) ?><br><span class="muted"><?= e($r['departure_city']) ?> to <?= e($r['destination_city']) ?></span></td>
        <td class="mono"><?= money($r['amount_requested']) ?></td>
        <td><?= badge($r['status']) ?></td>
        <td><?= e(when($r['created_at'])) ?></td>
        <td><a href="<?= e(url('customer/refund.php?id=' . $r['id'])) ?>">Open<?= $r['unread'] ? ' <span class="dot" title="New messages">' . (int)$r['unread'] . '</span>' : '' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
