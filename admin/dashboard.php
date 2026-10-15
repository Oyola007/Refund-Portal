<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$pdo = db();

$counts = array_fill_keys(array_keys(STATUSES), 0);
foreach ($pdo->query('SELECT status, COUNT(*) c FROM refund_requests GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
$total = array_sum($counts);
$requested = $pdo->query('SELECT COALESCE(SUM(amount_requested),0) FROM refund_requests')->fetchColumn();
$paidOut = $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payouts')->fetchColumn();
$toTransfer = (int)$pdo->query("SELECT COUNT(*) FROM refund_requests WHERE status = 'approved' AND handler = 'admin'")->fetchColumn();
$byReason = $pdo->query('SELECT reason_category, COUNT(*) c, SUM(amount_requested) t FROM refund_requests GROUP BY reason_category ORDER BY c DESC')->fetchAll();
$byMonth = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c, SUM(amount_requested) t FROM refund_requests
                        GROUP BY m ORDER BY m DESC LIMIT 6")->fetchAll();
$unread = $pdo->prepare("SELECT r.id, r.ref_no, c.full_name, COUNT(*) n FROM messages m
                         JOIN conversations cv ON cv.id = m.conversation_id JOIN refund_requests r ON r.id = cv.refund_id
                         JOIN users c ON c.id = r.user_id WHERE m.sender_id <> ? AND m.read_at IS NULL AND cv.kind IN ('customer_admin','admin_finance') GROUP BY r.id ORDER BY MAX(m.id) DESC LIMIT 8");
$unread->execute([$u['id']]);
$unread = $unread->fetchAll();

$title = 'Overview';
require __DIR__ . '/../includes/header.php';
?>
<h1>Overview</h1>
<div class="stats">
  <div class="stat"><span class="num"><?= $total ?></span><span class="lbl">All requests</span></div>
  <?php foreach (STATUSES as $k => $label): ?>
    <a class="stat" href="<?= e(url('admin/refunds.php?status=' . $k)) ?>"><span class="num"><?= $counts[$k] ?></span><span class="lbl"><?= e($label) ?></span></a>
  <?php endforeach; ?>
</div>
<div class="stats">
  <a class="stat" href="<?= e(url('admin/refunds.php?status=approved')) ?>"><span class="num"><?= $toTransfer ?></span><span class="lbl">Approved, not yet with finance</span></a>
  <div class="stat"><span class="num mono sm"><?= money($requested) ?></span><span class="lbl">Total requested</span></div>
  <div class="stat"><span class="num mono sm"><?= money($paidOut) ?></span><span class="lbl">Total paid out</span></div>
</div>

<div class="split even">
  <section class="panel">
    <h2>New messages</h2>
    <?php if (!$unread): ?><p class="empty">No unread messages.</p><?php else: ?>
    <ul class="plain">
      <?php foreach ($unread as $m): ?>
        <li><a href="<?= e(url('admin/refund.php?id=' . $m['id'])) ?>"><span class="mono"><?= e($m['ref_no']) ?></span> from <?= e($m['full_name']) ?></a> <span class="dot"><?= (int)$m['n'] ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
  <section class="panel">
    <h2>By month</h2>
    <?php if (!$byMonth): ?><p class="empty">No data yet.</p><?php else: ?>
    <table><thead><tr><th>Month</th><th>Requests</th><th>Amount</th></tr></thead><tbody>
      <?php foreach ($byMonth as $m): ?><tr><td><?= e(date('M Y', strtotime($m['m'] . '-01'))) ?></td><td><?= (int)$m['c'] ?></td><td class="mono"><?= money($m['t']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </section>
</div>

<section class="panel">
  <h2>By reason</h2>
  <?php if (!$byReason): ?><p class="empty">No data yet.</p><?php else: $max = max(array_column($byReason, 'c')); ?>
    <div class="bars">
    <?php foreach ($byReason as $r): ?>
      <div class="bar-row"><span><?= e($r['reason_category']) ?></span>
        <div class="bar"><i style="width:<?= round($r['c'] / $max * 100) ?>%"></i></div>
        <span class="mono"><?= (int)$r['c'] ?></span></div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
