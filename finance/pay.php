<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('finance');
$refund = refund_for_user((int)($_GET['id'] ?? 0), $u);
if (!$refund) { flash('error', 'That refund is not with finance.'); redirect('finance/dashboard.php'); }

$pdo = db();
$payout = null;
if ($refund['status'] === 'paid') {
    $s = $pdo->prepare('SELECT p.*, f.full_name AS finance_name FROM payouts p JOIN users f ON f.id = p.finance_user_id WHERE p.refund_id = ?');
    $s->execute([$refund['id']]);
    $payout = $s->fetch();
}

$methods = PAYOUT_METHODS;
$errors = [];
$returnErrors = [];
$old = ['method' => PAYOUT_METHODS[0], 'transaction_ref' => '', 'amount' => $refund['amount_requested']];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $refund['status'] === 'approved') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'pay') {
        $old = ['method' => $_POST['method'] ?? '', 'transaction_ref' => trim($_POST['transaction_ref'] ?? ''), 'amount' => trim($_POST['amount'] ?? '')];
        if (!in_array($old['method'], $methods, true)) $errors[] = 'Choose a payment method.';
        if ($old['transaction_ref'] === '' || mb_strlen($old['transaction_ref']) > 80) $errors[] = 'Enter the transaction reference.';
        if (!is_numeric($old['amount']) || (float)$old['amount'] <= 0) $errors[] = 'Enter the amount paid.';

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('INSERT INTO payouts (refund_id, finance_user_id, method, transaction_ref, amount) VALUES (?,?,?,?,?)')
                    ->execute([$refund['id'], $u['id'], $old['method'], $old['transaction_ref'], $old['amount']]);
                $pdo->prepare("UPDATE refund_requests SET status = 'paid' WHERE id = ? AND status = 'approved'")->execute([$refund['id']]);
                log_history((int)$refund['id'], (int)$u['id'], 'approved', 'paid', $old['method'] . ' ' . $old['transaction_ref']);
                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'This refund already has a payout recorded.';
            }
            if (!$errors) {
                send_mail($refund['customer_email'], 'Your refund ' . $refund['ref_no'] . ' has been paid',
                    '<p>Hello ' . e($refund['customer_name']) . ',</p><p>We paid ' . e(money($old['amount'])) . ' by ' . e($old['method']) . '. Transaction reference: <strong>' . e($old['transaction_ref']) . '</strong>.</p>');
                flash('ok', 'Payout recorded and the customer has been notified.');
                redirect('finance/dashboard.php');
            }
        }

    } elseif ($do === 'return') {
        // ---- Send the client back to admin ----
        $why = trim($_POST['why'] ?? '');
        if ($why === '') {
            $returnErrors[] = 'Tell admin why you are returning it.';
        } elseif (mb_strlen($why) > 2000) {
            $returnErrors[] = 'That note is too long.';
        } else {
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare("UPDATE refund_requests SET handler = 'admin' WHERE id = ? AND status = 'approved' AND handler = 'finance'");
                $st->execute([$refund['id']]);
                if ($st->rowCount() !== 1) throw new RuntimeException('Refund is no longer returnable.');
                log_history((int)$refund['id'], (int)$u['id'], 'approved', 'approved', 'Returned to admin');
                $internal = ensure_conversation((int)$refund['id'], (int)$refund['user_id'], 'admin_finance');
                save_message($internal, (int)$u['id'], "Returned to admin.\n\n" . $why, []);
                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('return: ' . $ex->getMessage());
                $returnErrors[] = 'The refund could not be returned. Reload the page and try again.';
            }
            if (!$returnErrors) {
                flash('ok', 'Returned to admin with your note.');
                redirect('finance/dashboard.php');
            }
        }
    }
}

$title = 'Payout ' . $refund['ref_no'];
require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= e(url('finance/dashboard.php')) ?>">&larr; Payouts</a></p>
<div class="split">
  <div><?php require __DIR__ . '/../includes/refund_detail.php'; ?></div>
  <div>
    <section class="panel">
      <?php if ($payout): ?>
        <h2>Payout recorded</h2>
        <dl class="facts">
          <div><dt>Method</dt><dd><?= e($payout['method']) ?></dd></div>
          <div><dt>Transaction</dt><dd class="mono"><?= e($payout['transaction_ref']) ?></dd></div>
          <div><dt>Amount</dt><dd class="mono"><?= money($payout['amount']) ?></dd></div>
          <div><dt>Paid by</dt><dd><?= e($payout['finance_name']) ?>, <?= e(when($payout['paid_at'])) ?></dd></div>
        </dl>
      <?php else: ?>
        <h2>Record payout</h2>
        <?php foreach ($errors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="pay">
          <label>Method
            <select name="method"><?php foreach ($methods as $m): ?><option <?= $old['method'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select>
          </label>
          <label>Transaction reference <input name="transaction_ref" value="<?= e($old['transaction_ref']) ?>" required></label>
          <label>Amount paid (USD) <input type="number" name="amount" step="0.01" min="0.01" value="<?= e($old['amount']) ?>" required></label>
          <button class="btn" type="submit">Mark as paid</button>
        </form>
      <?php endif; ?>
    </section>

    <?php if ($refund['status'] === 'approved'): ?>
    <section class="panel">
      <h2>Return to admin</h2>
      <p class="muted">Something wrong with this client's details? Send the refund back. Your note goes to admin in the internal chat. The customer is not shown it.</p>
      <?php foreach ($returnErrors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="return">
        <label>Why <textarea name="why" rows="3" required></textarea></label>
        <div><button class="btn danger" type="submit">Return to admin</button></div>
      </form>
    </section>
    <?php endif; ?>

    <?php require __DIR__ . '/../includes/chat_panel.php'; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
