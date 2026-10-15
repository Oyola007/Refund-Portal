<?php
/* Needs $refund. Prints the full refund form as submitted, plus the timeline. */
$docsGiven = $refund['documents_provided'] ? explode(',', $refund['documents_provided']) : [];
$reason = $refund['reason_category'] . ($refund['reason_other'] ? ': ' . $refund['reason_other'] : '');
?>
<section class="panel">
  <div class="page-head">
    <div>
      <h1 class="mono"><?= e($refund['ref_no']) ?></h1>
      <p class="muted">Submitted <?= e(when($refund['created_at'])) ?></p>
    </div>
    <div style="text-align:right">
      <?= badge($refund['status']) ?>
      <?php if (in_array($refund['status'], ['approved', 'paid'], true)): ?>
        <br><small class="muted">With <?= e($refund['handler'] === 'finance' ? 'finance' : (($u['role'] ?? '') === 'customer' ? 'the refunds team' : 'admin')) ?></small>
      <?php endif; ?>
    </div>
  </div>

  <h3>Passenger</h3>
  <dl class="facts">
    <div><dt>Full legal name</dt><dd><?= e($refund['passenger_name']) ?></dd></div>
    <div><dt>Email</dt><dd><?= e($refund['passenger_email']) ?></dd></div>
    <div><dt>Phone</dt><dd><?= e($refund['passenger_phone']) ?></dd></div>
    <div><dt>Mailing address</dt><dd><?= e($refund['mailing_address']) ?><br><span class="muted"><?= e($refund['city_state_country']) ?></span></dd></div>
    <div><dt>Booking number</dt><dd class="mono"><?= e($refund['booking_ref']) ?></dd></div>
    <div><dt>Ticket number</dt><dd class="mono"><?= e($refund['ticket_number'] ?: 'Not given') ?></dd></div>
  </dl>

  <div class="facts-group">
    <h3>Trip</h3>
    <dl class="facts">
      <div><dt>Flight(s)</dt><dd><?= e($refund['flight_numbers']) ?></dd></div>
      <div><dt>Travel date</dt><dd><?= e(date('d M Y', strtotime($refund['travel_date']))) ?></dd></div>
      <div><dt>From</dt><dd><?= e($refund['departure_city']) ?></dd></div>
      <div><dt>To</dt><dd><?= e($refund['destination_city']) ?></dd></div>
      <div><dt>Original itinerary</dt><dd><?= e($refund['itinerary'] ?: 'Not given') ?></dd></div>
    </dl>
  </div>

  <div class="facts-group">
    <h3>Refund request</h3>
    <dl class="facts">
      <div><dt>Amount paid</dt><dd class="mono"><?= money($refund['amount_paid']) ?></dd></div>
      <div><dt>Amount requested</dt><dd class="mono"><?= money($refund['amount_requested']) ?></dd></div>
      <div><dt>Payment method used</dt><dd><?= e($refund['payment_method']) ?></dd></div>
      <div><dt>Preferred refund method</dt><dd><?= e($refund['preferred_refund_method'] ?: 'No preference') ?></dd></div>
      <div><dt>Reason</dt><dd><?= e($reason) ?></dd></div>
    </dl>
    <?php if ($refund['reason_details']): ?>
      <h3>Additional details</h3>
      <p class="prose"><?= nl2br(e($refund['reason_details'])) ?></p>
    <?php endif; ?>
  </div>

  <div class="facts-group">
    <h3>Supporting documents</h3>
    <p class="prose"><?= $docsGiven ? e(implode(', ', $docsGiven)) : 'None ticked.' ?> <span class="muted">Files sent by the passenger appear in the conversation.</span></p>
    <h3>Declaration</h3>
    <p class="prose">Signed by <strong><?= e($refund['signature_name']) ?></strong> on <?= e(when($refund['declared_at'])) ?>.</p>
  </div>

  <?php if ($refund['transfer_note'] && $refund['handler'] === 'finance'): ?>
    <div class="facts-group">
      <h3>Note when passed to finance</h3>
      <p class="prose"><?= nl2br(e($refund['transfer_note'])) ?></p>
    </div>
  <?php endif; ?>

  <?php if ($refund['decision_note']): ?>
    <div class="facts-group">
      <h3>Decision note</h3>
      <p class="prose"><?= nl2br(e($refund['decision_note'])) ?></p>
    </div>
  <?php endif; ?>
</section>
<section class="panel">
  <h3 style="margin-top:0">Timeline</h3>
  <ul class="timeline">
    <?php foreach (history_for((int)$refund['id']) as $h): ?>
      <li>
        <?= badge($h['new_status']) ?>
        <span><?= e($h['note'] ?: '') ?></span>
        <small class="muted"><?= e(when($h['changed_at'])) ?><?= $h['full_name'] ? ' by ' . e($h['full_name']) : '' ?></small>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
