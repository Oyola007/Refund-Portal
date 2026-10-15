<?php
require_once __DIR__ . '/includes/functions.php';
if ($u = current_user()) redirect(home_for($u['role']));
$email = $_SESSION['otp_email'] ?? '';
if ($email === '') redirect('login.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    $s = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    $s->execute([$email]);
    $user = $s->fetch();
    if ($user && strlen($code) === 6 && check_otp((int)$user['id'], $code)) {
        session_regenerate_id(true);
        unset($_SESSION['otp_email'], $_SESSION['otp_debug']);
        $_SESSION['uid'] = (int)$user['id'];
        redirect(home_for($user['role']));
    }
    $error = 'That code is wrong or has expired. Check the code, or request a new one.';
}
$title = 'Enter your code';
require __DIR__ . '/includes/header.php';
?>
<section class="panel narrow">
  <h1>Enter your code</h1>
  <p class="muted">We sent a 6-digit code to <strong><?= e($email) ?></strong>. It expires in <?= (int)(OTP_TTL / 60) ?> minutes.</p>
  <?php if (!empty($_SESSION['otp_debug'])): ?>
    <div class="flash f-warn">The email could not be sent, so your code is shown here instead: <strong class="mono"><?= e($_SESSION['otp_debug']) ?></strong></div>
  <?php endif; ?>
  <?php if ($error): ?><div class="flash f-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>6-digit code
      <input class="otp" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code">
    </label>
    <button class="btn" type="submit">Log in</button>
  </form>
  <p class="muted">Didn't get it? <a href="<?= e(url('login.php')) ?>">Request a new code</a> (one every <?= (int)OTP_RESEND_WAIT ?> seconds).</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>