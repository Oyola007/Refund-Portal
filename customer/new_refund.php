<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('customer');

$fields = ['passenger_name', 'passenger_email', 'passenger_phone', 'mailing_address', 'city_state_country', 'booking_ref', 'ticket_number',
           'flight_numbers', 'travel_date', 'departure_city', 'destination_city', 'itinerary',
           'amount_paid', 'amount_requested', 'payment_method', 'preferred_refund_method',
           'reason_category', 'reason_other', 'reason_details', 'signature_name'];
$old = array_fill_keys($fields, '');
$old['passenger_name'] = $u['full_name'];
$old['passenger_email'] = $u['email'];
$old['passenger_phone'] = $u['phone'];
$oldDocs = [];
$declared = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($fields as $k) $old[$k] = trim((string)($_POST[$k] ?? ''));
    $old['passenger_email'] = strtolower($old['passenger_email']);
    $old['passenger_phone'] = preg_replace('/[\s\-()]/', '', $old['passenger_phone']);
    $old['booking_ref'] = strtoupper($old['booking_ref']);
    $oldDocs = array_values(array_intersect(DOCS, (array)($_POST['documents_provided'] ?? [])));
    $declared = !empty($_POST['declaration']);

    // Passenger information
    if (mb_strlen($old['passenger_name']) < 3 || mb_strlen($old['passenger_name']) > 120) $errors[] = 'Enter the passenger\'s full legal name.';
    if (!filter_var($old['passenger_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^\+?[0-9]{9,15}$/', $old['passenger_phone'])) $errors[] = 'Enter a valid phone number.';
    if (mb_strlen($old['mailing_address']) < 5 || mb_strlen($old['mailing_address']) > 255) $errors[] = 'Enter your mailing address.';
    if (mb_strlen($old['city_state_country']) < 2 || mb_strlen($old['city_state_country']) > 150) $errors[] = 'Enter your city, state and country.';
    if (!preg_match('/^[A-Z0-9]{5,20}$/', $old['booking_ref'])) $errors[] = 'Enter the confirmation / booking number (letters and digits only).';
    if ($old['ticket_number'] !== '' && !preg_match('/^[0-9\- ]{10,30}$/', $old['ticket_number'])) $errors[] = 'The ticket number should be digits, for example 016-1234567890.';

    // Trip information
    if ($old['flight_numbers'] === '' || mb_strlen($old['flight_numbers']) > 100) $errors[] = 'Enter the flight number(s).';
    $d = DateTime::createFromFormat('Y-m-d', $old['travel_date']);
    if (!$d || $d->format('Y-m-d') !== $old['travel_date']) $errors[] = 'Enter the travel date.';
    if ($old['departure_city'] === '' || mb_strlen($old['departure_city']) > 100) $errors[] = 'Enter the departure airport or city.';
    if ($old['destination_city'] === '' || mb_strlen($old['destination_city']) > 100) $errors[] = 'Enter the destination airport or city.';
    if (mb_strlen($old['itinerary']) > 255) $errors[] = 'The original itinerary is too long. Keep it under 255 characters.';

    // Refund request
    $paid = is_numeric($old['amount_paid']) ? (float)$old['amount_paid'] : 0;
    $req = is_numeric($old['amount_requested']) ? (float)$old['amount_requested'] : 0;
    if ($paid <= 0 || $paid > 1000000) $errors[] = 'Enter the amount you paid, in USD.';
    if ($req <= 0 || $req > 1000000) $errors[] = 'Enter the amount you are requesting, in USD.';
    elseif ($paid > 0 && $req > $paid) $errors[] = 'The amount requested cannot be more than the amount paid.';
    if (!in_array($old['payment_method'], PAYMENT_METHODS, true)) $errors[] = 'Choose the payment method you used.';
    if ($old['preferred_refund_method'] !== '' && !in_array($old['preferred_refund_method'], REFUND_METHODS, true)) $errors[] = 'Choose a valid refund method.';

    // Reason
    if (!in_array($old['reason_category'], REASONS, true)) $errors[] = 'Choose the reason for your refund.';
    if ($old['reason_category'] === 'Other') {
        if ($old['reason_other'] === '' || mb_strlen($old['reason_other']) > 150) $errors[] = 'You chose Other. Say what the reason is (150 characters at most).';
    } else {
        $old['reason_other'] = '';
    }
    if (mb_strlen($old['reason_details']) > 5000) $errors[] = 'Additional details are too long.';

    // Declaration
    if (!$declared) $errors[] = 'Tick the declaration to confirm the information is accurate.';
    if (mb_strlen($old['signature_name']) < 3) $errors[] = 'Type your full name as your signature.';

    // Files
    $accepted = [];
    try { $accepted = validate_uploads(collect_uploads('documents')); }
    catch (UploadException $ex) { $errors[] = $ex->getMessage(); }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $ref = new_ref_no();
            $pdo->prepare('INSERT INTO refund_requests
                (ref_no, user_id, passenger_name, passenger_email, passenger_phone, mailing_address, city_state_country, booking_ref, ticket_number,
                 flight_numbers, travel_date, departure_city, destination_city, itinerary, amount_paid, amount_requested, payment_method,
                 preferred_refund_method, reason_category, reason_other, reason_details, documents_provided, signature_name, declared_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $ref, $u['id'], $old['passenger_name'], $old['passenger_email'], $old['passenger_phone'], $old['mailing_address'],
                    $old['city_state_country'], $old['booking_ref'], $old['ticket_number'] ?: null,
                    $old['flight_numbers'], $old['travel_date'], $old['departure_city'], $old['destination_city'], $old['itinerary'] ?: null,
                    $paid, $req, $old['payment_method'],
                    $old['preferred_refund_method'] ?: null, $old['reason_category'], $old['reason_other'] ?: null,
                    $old['reason_details'] ?: null, $oldDocs ? implode(',', $oldDocs) : null, $old['signature_name'], date('Y-m-d H:i:s'),
                ]);
            $id = (int)$pdo->lastInsertId();
            log_history($id, (int)$u['id'], null, 'pending', 'Request submitted');
            $convId = ensure_conversation($id, (int)$u['id']);
            if ($accepted) save_message($convId, (int)$u['id'], 'Supporting documents sent with the refund request.', $accepted);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('new_refund: ' . $ex->getMessage());
            $errors[] = 'We could not save your request. Please try again.';
        }
        if (!$errors) {
            flash('ok', "Request $ref submitted. Use the conversation to send anything else the airline asks for.");
            redirect('customer/refund.php?id=' . $id);
        }
    }
}

$title = 'New refund request';
require __DIR__ . '/../includes/header.php';
$v = fn(string $k) => e($old[$k]);
?>
<section class="panel form-page">
  <h1>Refund request</h1>
  <p class="muted">Fill in the form below. The airline may ask for more documents in the conversation after you submit.</p>
  <?php foreach ($errors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>

    <fieldset class="sec">
      <legend>Passenger information</legend>
      <div class="grid2">
        <label>Full legal name <input name="passenger_name" value="<?= $v('passenger_name') ?>" required autocomplete="name"></label>
        <label>Email address <input type="email" name="passenger_email" value="<?= $v('passenger_email') ?>" required></label>
        <label>Phone number <input type="tel" name="passenger_phone" value="<?= $v('passenger_phone') ?>" required></label>
        <label>Mailing address <input name="mailing_address" value="<?= $v('mailing_address') ?>" required autocomplete="street-address"></label>
        <label>City / State / Country <input name="city_state_country" value="<?= $v('city_state_country') ?>" placeholder="Nairobi, Nairobi County, Kenya" required></label>
        <label>Confirmation / booking number <input name="booking_ref" value="<?= $v('booking_ref') ?>" maxlength="20" placeholder="ABC123" required></label>
      </div>
      <label>Ticket number <span class="hint">(optional)</span> <input name="ticket_number" value="<?= $v('ticket_number') ?>" placeholder="016-1234567890"></label>
    </fieldset>

    <fieldset class="sec">
      <legend>Trip information</legend>
      <div class="grid2">
        <label>Flight number(s) <input name="flight_numbers" value="<?= $v('flight_numbers') ?>" placeholder="UA 123, UA 456" required></label>
        <label>Travel date <input type="date" name="travel_date" value="<?= $v('travel_date') ?>" required></label>
        <label>Departure airport / city <input name="departure_city" value="<?= $v('departure_city') ?>" placeholder="NBO Nairobi" required></label>
        <label>Destination airport / city <input name="destination_city" value="<?= $v('destination_city') ?>" placeholder="JFK New York" required></label>
      </div>
      <label>Original itinerary / route <span class="hint">(optional)</span> <input name="itinerary" value="<?= $v('itinerary') ?>" placeholder="NBO - AMS - JFK"></label>
    </fieldset>

    <fieldset class="sec">
      <legend>Refund request</legend>
      <div class="grid2">
        <label>Amount paid (USD) <input type="number" name="amount_paid" min="0.01" step="0.01" value="<?= $v('amount_paid') ?>" required></label>
        <label>Amount requested (USD) <input type="number" name="amount_requested" min="0.01" step="0.01" value="<?= $v('amount_requested') ?>" required></label>
        <label>Payment method used
          <select name="payment_method" required>
            <option value="">Choose one</option>
            <?php foreach (PAYMENT_METHODS as $m): ?><option <?= $old['payment_method'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Preferred refund method <span class="hint">(if applicable)</span>
          <select name="preferred_refund_method">
            <option value="">No preference</option>
            <?php foreach (REFUND_METHODS as $m): ?><option <?= $old['preferred_refund_method'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
    </fieldset>

    <fieldset class="sec">
      <legend>Reason for refund</legend>
      <div class="checks">
        <?php foreach (REASONS as $r): ?>
          <label class="check"><input type="radio" name="reason_category" value="<?= e($r) ?>" <?= $old['reason_category'] === $r ? 'checked' : '' ?> required> <?= e($r) ?></label>
        <?php endforeach; ?>
      </div>
      <label>If Other, say what <input name="reason_other" value="<?= $v('reason_other') ?>" maxlength="150"></label>
    </fieldset>

    <fieldset class="sec">
      <legend>Additional details</legend>
      <label class="sr" for="rd">Additional details</label>
      <textarea id="rd" name="reason_details" rows="5" placeholder="Tell us what happened, with dates and times where you can."><?= $v('reason_details') ?></textarea>
    </fieldset>

    <fieldset class="sec">
      <legend>Supporting documents</legend>
      <p class="hint" style="margin:0">Tick what you have. You can attach the files now or send them later in the conversation.</p>
      <div class="checks">
        <?php foreach (DOCS as $d): ?>
          <label class="check"><input type="checkbox" name="documents_provided[]" value="<?= e($d) ?>" <?= in_array($d, $oldDocs, true) ? 'checked' : '' ?>> <?= e($d) ?></label>
        <?php endforeach; ?>
      </div>
      <label>Attach files <span class="hint">(photos, PDF, Word or video; up to <?= MAX_FILES_PER_MESSAGE ?> files, 25 MB each)</span>
        <input type="file" name="documents[]" multiple accept="image/*,video/*,.pdf,.doc,.docx">
      </label>
      <p class="notice">Never send card passwords, PINs or other login details. The airline will not ask for them.</p>
    </fieldset>

    <fieldset class="sec">
      <legend>Declaration</legend>
      <label class="check"><input type="checkbox" name="declaration" value="1" <?= $declared ? 'checked' : '' ?> required>
        I certify that the information provided above is accurate to the best of my knowledge. I understand that the airline may request additional documentation before processing the refund.</label>
      <div class="grid2">
        <label>Passenger signature <span class="hint">(type your full name)</span> <input name="signature_name" value="<?= $v('signature_name') ?>" required></label>
        <label>Date <input value="<?= e(date('d M Y')) ?>" readonly></label>
      </div>
    </fieldset>

    <div><button class="btn" type="submit">Submit refund request</button></div>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
