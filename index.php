<?php
require_once __DIR__ . '/includes/functions.php';
if ($u = current_user()) redirect(home_for($u['role']));
$title = 'Airline refunds';
require __DIR__ . '/includes/header.php';
?>
<div class="hero-grid">
  <section class="hero">
    <h1>Request a refund for your flight.</h1>
    <p class="lead">Fill in one form, follow your request from review to payout, and talk to the refunds team in the same place. Send tickets, boarding passes and photos as proof.</p>
    <div class="row">
      <a class="btn big" href="<?= e(url('register.php')) ?>">Create an account</a>
      <a class="btn big ghost" href="<?= e(url('login.php')) ?>">Log in</a>
    </div>
    <p class="hero-note">No password to remember. We email you a 6-digit code each time you log in.</p>
  </section>

  <aside class="ticket" aria-label="Example of a refund request">
    <div class="ticket-top">
      <span class="mono">Request #1042</span>
      <span class="badge b-approved">Approved</span>
    </div>
    <div class="ticket-route"><span>NBO</span><i></i><span>MBA</span></div>
    <div class="ticket-amount">
      <span class="muted">Refund amount</span>
      <strong>$186.00</strong>
    </div>
    <ul class="progress">
      <li class="done">Request sent</li>
      <li class="done">Documents checked</li>
      <li class="now">Approved, waiting for payout</li>
      <li>Paid to you</li>
    </ul>
  </aside>
</div>

<div class="highlights">
  <div class="hl"><b>1 form</b><span>for passenger, flight and proof</span></div>
  <div class="hl"><b>5 min</b><span>is how long your login code lasts</span></div>
  <div class="hl"><b>USD</b><span>payout once finance approves</span></div>
  <div class="hl"><b>7–10 days</b><span>working days to get paid</span></div>
</div>

<div class="section-head">
  <h2>How it works</h2>
  <p>Five steps from request to payout.</p>
</div>
<ol class="steps flow">
  <li><strong>Register</strong><span>Give your name, email and phone number.</span></li>
  <li><strong>Log in with a code</strong><span>Enter your email. We send a 6-digit code that expires in 5 minutes.</span></li>
  <li><strong>Fill in the refund form</strong><span>Passenger details, flight details, the amount, your reason and supporting documents.</span></li>
  <li><strong>Chat and share proof</strong><span>The refunds team may ask for more. Reply with text, photos, videos or documents.</span></li>
  <li><strong>Get paid</strong><span>Once approved, the finance team pays you in USD and marks the refund as paid.</span></li>
</ol>

<div class="section-head">
  <h2>Refund policy</h2>
  <p>Sample wording. Replace it with your airline's policy.</p>
</div>
<div class="policy">
  <div><h3>Cancelled flights</h3><p>Flights cancelled by the airline are refunded in full.</p></div>
  <div><h3>Schedule changes</h3><p>For a significant schedule change, you can ask for a refund of the unused ticket.</p></div>
  <div><h3>Duplicate charges</h3><p>Duplicate charges are refunded in full once confirmed.</p></div>
  <div><h3>Payment method</h3><p>Refunds go back to the original form of payment unless you ask for another method.</p></div>
  <div><h3>Payout time</h3><p>Approved refunds are paid within 7 to 10 working days.</p></div>
</div>

<div class="section-head"><h2>Common questions</h2></div>
<section class="panel faq">
  <details>
    <summary>What proof should I upload?</summary>
    <p>Your e-ticket or booking confirmation, boarding pass if you have one, and any airline message about the cancellation or change. Photos and short videos also work.</p>
  </details>
  <details>
    <summary>I did not get my login code.</summary>
    <p>Check your spam folder, then request a new code. Each code expires after 5 minutes.</p>
  </details>
  <details>
    <summary>How do I know where my request is?</summary>
    <p>Log in and open your request. The status updates from pending to under review, approved and paid, and the refunds team replies in the chat.</p>
  </details>
</section>

<section class="panel cta">
  <h2>Ready to claim your refund?</h2>
  <p>Create an account and send your request in a few minutes.</p>
  <div class="row">
    <a class="btn big" href="<?= e(url('register.php')) ?>">Create an account</a>
    <a class="btn big ghost" href="<?= e(url('login.php')) ?>">Log in</a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>