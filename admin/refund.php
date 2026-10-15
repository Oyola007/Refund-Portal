<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$refund = refund_for_user((int)($_GET['id'] ?? 0), $u);
if (!$refund) { flash('error', 'That refund was not found.'); redirect('admin/refunds.php'); }
ensure_conversation((int)$refund['id'], (int)$refund['user_id']);

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if (mb_strlen($note) > 2000) {
        $errors[] = 'The note is too long. Keep it under 2000 characters.';

    } elseif ($action === 'transfer') {
        // ---- Hand an approved refund over to finance ----
        if ($refund['status'] !== 'approved' || $refund['handler'] !== 'admin') {
            $errors[] = 'Only approved refunds that are still with admin can be transferred.';
        } else {
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare("UPDATE refund_requests SET handler = 'finance', transferred_by = ?, transferred_at = NOW(), transfer_note = ?
                                     WHERE id = ? AND status = 'approved' AND handler = 'admin'");
                $st->execute([$u['id'], $note !== '' ? $note : null, $refund['id']]);
                if ($st->rowCount() !== 1) throw new RuntimeException('Refund is no longer transferable.');
                log_history((int)$refund['id'], (int)$u['id'], 'approved', 'approved', 'Transferred to finance' . ($note !== '' ? ': ' . $note : ''));
                ensure_conversation((int)$refund['id'], (int)$refund['user_id'], 'customer_finance');
                $internal = ensure_conversation((int)$refund['id'], (int)$refund['user_id'], 'admin_finance');
                save_message($internal, (int)$u['id'], 'Transferred to finance for payment.' . ($note !== '' ? "\n\n" . $note : ''), []);
                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('transfer: ' . $ex->getMessage());
                $errors[] = 'The transfer did not go through. Reload the page and try again.';
            }
            if (!$errors) {
                send_mail($refund['customer_email'], 'Your refund ' . $refund['ref_no'] . ' is with our finance team',
                    '<p>Hello ' . e($refund['customer_name']) . ',</p><p>Your refund was approved and has been passed to our finance team for payment.'
                    . ($note !== '' ? '</p><p>' . nl2br(e($note)) : '') . '</p><p>You can message them from the Finance tab on your refund page.</p>');
                flash('ok', 'Refund transferred to finance. The customer has been told.');
                redirect('admin/refund.php?id=' . $refund['id'] . '&thread=admin_finance');
            }
        }

    } else {
        // ---- Review decision ----
        $map = ['review' => 'under_review', 'approve' => 'approved', 'reject' => 'rejected'];
        if (!isset($map[$action]) || !in_array($refund['status'], ['pending', 'under_review'], true)) {
            $errors[] = 'This refund can no longer be changed.';
        } elseif ($action === 'reject' && $note === '') {
            $errors[] = 'Add a reason so the customer knows why it was rejected.';
        } else {
            $new = $map[$action];
            $pdo->prepare('UPDATE refund_requests SET status = ?, decision_note = ?, decided_by = ? WHERE id = ?')
                ->execute([$new, $note !== '' ? $note : $refund['decision_note'], $action === 'review' ? null : $u['id'], $refund['id']]);
            log_history((int)$refund['id'], (int)$u['id'], $refund['status'], $new, $note !== '' ? $note : null);
            send_mail($refund['customer_email'], 'Update on refund ' . $refund['ref_no'],
                '<p>Hello ' . e($refund['customer_name']) . ',</p><p>Your refund request is now <strong>' . e(STATUSES[$new]) . '</strong>.</p>'
                . ($note !== '' ? '<p>' . nl2br(e($note)) . '</p>' : '') . '<p>Log in to view details or reply in the conversation.</p>');
            flash('ok', 'Status changed to ' . STATUSES[$new] . '.');
            redirect('admin/refund.php?id=' . $refund['id']);
        }
    }
}

$open = in_array($refund['status'], ['pending', 'under_review'], true);
$canTransfer = $refund['status'] === 'approved' && $refund['handler'] === 'admin';
$title = $refund['ref_no'];
require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= e(url('admin/refunds.php')) ?>">&larr; Refunds</a></p>
<div class="split">
  <div>
    <?php require __DIR__ . '/../includes/refund_detail.php'; ?>

    <?php if ($open): ?>
    <section class="panel">
      <h2>Decision</h2>
      <?php foreach ($errors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Note to the customer <textarea name="note" rows="3" placeholder="Required when rejecting"></textarea></label>
        <div class="row">
          <?php if ($refund['status'] === 'pending'): ?><button class="btn ghost" name="action" value="review">Start review</button><?php endif; ?>
          <button class="btn" name="action" value="approve">Approve</button>
          <button class="btn danger" name="action" value="reject">Reject</button>
        </div>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($canTransfer): ?>
    <section class="panel">
      <h2>Transfer to finance</h2>
      <p class="muted">Approved. Pass this client to finance so they can pay the refund. Finance and the customer get a private chat, and you keep an internal thread with finance.</p>
      <?php foreach ($errors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="transfer">
        <label>Note <span class="hint">(the customer will see this)</span>
          <textarea name="note" rows="3" placeholder="For example: documents verified, refund to the original card"></textarea></label>
        <div><button class="btn" type="submit">Transfer to finance</button></div>
      </form>
    </section>
    <?php elseif ($refund['handler'] === 'finance'): ?>
    <section class="panel">
      <h2>With finance</h2>
      <p class="muted">Transferred <?= e(when($refund['transferred_at'])) ?>. Use the Finance (internal) tab to talk to them.</p>
    </section>
    <?php endif; ?>
  </div>
  <div><?php require __DIR__ . '/../includes/chat_panel.php'; ?></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
