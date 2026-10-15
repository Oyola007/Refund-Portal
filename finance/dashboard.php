<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('finance');
$pdo = db();

$w = $pdo->prepare("SELECT r.*, (SELECT COUNT(*) FROM messages m JOIN conversations cv ON cv.id = m.conversation_id
                      WHERE cv.refund_id = r.id AND cv.kind IN ('customer_finance','admin_finance')
                        AND m.sender_id <> ? AND m.read_at IS NULL) AS unread
                    FROM refund_requests r WHERE r.status = 'approved' AND r.handler = 'finance' ORDER BY r.transferred_at ASC");
$w->execute([$u['id']]);
$waiting = $w->fetchAll();

$paid = $pdo->query("SELECT p.*, r.ref_no, r.id AS refund_id, r.passenger_name AS customer_name FROM payouts p
                     JOIN refund_requests r ON r.id = p.refund_id
                     ORDER BY p.paid_at DESC LIMIT 25")->fetchAll();
$owed = array_sum(array_column($waiting, 'amount_requested'));
$totals = $pdo->query('SELECT COUNT(*) n, COALESCE(SUM(amount),0) t FROM payouts')->fetch();

$title = 'Payouts';
require __DIR__ . '/../includes/header.php';
?>
<h1>Payouts</h1>
<div class="stats">
  <div class="stat"><span class="num"><?= count($waiting) ?></span><span class="lbl">Awaiting payout</span></div>
  <div class="stat"><span class="num mono sm"><?= money($owed) ?></span><span class="lbl">Amount owed</span></div>
  <div class="stat"><span class="num"><?= (int)$totals['n'] ?></span><span class="lbl">Refunds paid</span></div>
  <div class="stat"><span class="num mono sm"><?= money($totals['t']) ?></span><span class="lbl">Total paid out</span></div>
</div>

<section class="panel">
  <h2>Transferred to finance, waiting for payment</h2>
  <?php if (!$waiting): ?><p class="empty">Nothing to pay right now. Refunds appear here when admin transfers them.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Reference</th><th>Passenger</th><th>Flight</th><th>Amount</th><th>Transferred</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($waiting as $r): ?>
      <tr>
        <td class="mono"><?= e($r['ref_no']) ?></td><td><?= e($r['passenger_name']) ?></td><td><?= e($r['flight_numbers']) ?></td>
        <td class="mono"><?= money($r['amount_requested']) ?></td>
        <td><?= $r['transferred_at'] ? e(when($r['transferred_at'])) : '' ?></td>
        <td><a class="btn small" href="<?= e(url('finance/pay.php?id=' . $r['id'])) ?>">Open</a><?= $r['unread'] ? ' <span class="dot" title="New messages">' . (int)$r['unread'] . '</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Recent payouts</h2>
  <?php if (!$paid): ?><p class="empty">No payouts recorded yet.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Reference</th><th>Passenger</th><th>Method</th><th>Transaction</th><th>Amount</th><th>Paid</th></tr></thead>
    <tbody>
    <?php foreach ($paid as $p): ?>
      <tr>
        <td class="mono"><a href="<?= e(url('finance/pay.php?id=' . $p['refund_id'])) ?>"><?= e($p['ref_no']) ?></a></td>
        <td><?= e($p['customer_name']) ?></td><td><?= e($p['method']) ?></td>
        <td class="mono"><?= e($p['transaction_ref']) ?></td><td class="mono"><?= money($p['amount']) ?></td>
        <td><?= e(when($p['paid_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
