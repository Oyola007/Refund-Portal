<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('customer');
$refund = refund_for_user((int)($_GET['id'] ?? 0), $u);
if (!$refund) { http_response_code(404); flash('error', 'That refund was not found.'); redirect('customer/dashboard.php'); }
ensure_conversation((int)$refund['id'], (int)$refund['user_id']);

$title = $refund['ref_no'];
require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= e(url('customer/dashboard.php')) ?>">&larr; My refunds</a></p>
<div class="split">
  <div><?php require __DIR__ . '/../includes/refund_detail.php'; ?></div>
  <div><?php require __DIR__ . '/../includes/chat_panel.php'; ?></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
