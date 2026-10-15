<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$sql = "SELECT r.*, c.full_name AS customer_name,
        (SELECT COUNT(*) FROM messages m JOIN conversations cv ON cv.id = m.conversation_id
         WHERE cv.refund_id = r.id AND cv.kind IN ('customer_admin','admin_finance') AND m.sender_id <> ? AND m.read_at IS NULL) AS unread
        FROM refund_requests r JOIN users c ON c.id = r.user_id WHERE 1=1";
$args = [$u['id']];
if (isset(STATUSES[$status])) { $sql .= ' AND r.status = ?'; $args[] = $status; }
if ($q !== '') { $sql .= ' AND (r.ref_no LIKE ? OR r.booking_ref LIKE ? OR r.flight_numbers LIKE ? OR r.passenger_name LIKE ? OR c.email LIKE ?)'; $like = "%$q%"; array_push($args, $like, $like, $like, $like, $like); }
$sql .= ' ORDER BY r.created_at DESC LIMIT 200';
$s = db()->prepare($sql);
$s->execute($args);
$rows = $s->fetchAll();

$title = 'Refunds';
require __DIR__ . '/../includes/header.php';
?>
<h1>Refunds</h1>
<form method="get" class="filters">
  <select name="status" aria-label="Status">
    <option value="">All statuses</option>
    <?php foreach (STATUSES as $k => $label): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <input name="q" value="<?= e($q) ?>" placeholder="Search reference, booking, flight, name or email" aria-label="Search">
  <button class="btn small" type="submit">Filter</button>
</form>
<section class="panel">
  <?php if (!$rows): ?><p class="empty">No refunds match.</p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Reference</th><th>Passenger</th><th>Flight</th><th>Amount</th><th>Status</th><th>With</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="mono"><?= e($r['ref_no']) ?></td><td><?= e($r['passenger_name']) ?></td><td><?= e($r['flight_numbers']) ?></td>
        <td class="mono"><?= money($r['amount_requested']) ?></td><td><?= badge($r['status']) ?></td><td><?= e(ucfirst($r['handler'])) ?></td>
        <td><?= e(when($r['created_at'])) ?></td>
        <td><a href="<?= e(url('admin/refund.php?id=' . $r['id'])) ?>">Open<?= $r['unread'] ? ' <span class="dot">' . (int)$r['unread'] . '</span>' : '' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
