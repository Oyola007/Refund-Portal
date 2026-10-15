<?php
require_once __DIR__ . '/includes/functions.php';
if ($u = current_user()) redirect(home_for($u['role']));

$error = '';
$notRegistered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $s = db()->prepare('SELECT * FROM users WHERE email = ?');
        $s->execute([$email]);
        $user = $s->fetch();

        if (!$user) {
            $error = 'This email is not registered.';
            $notRegistered = true;
        } elseif (!$user['is_active']) {
            $error = 'This account has been deactivated. Contact the refunds team.';
        } else {
            if (($_SESSION['otp_email'] ?? '') !== $email) unset($_SESSION['otp_debug']);
            $_SESSION['otp_email'] = $email;

            $r = issue_otp($user);
            if (!$r['throttled']) {
                unset($_SESSION['otp_debug']);              // never keep showing an old code
                if ($r['code']) $_SESSION['otp_debug'] = $r['code'];
            }

            if ($r['throttled']) {
                flash('warn', 'A code was sent less than a minute ago. Use it, or wait a moment and request another.');
            } elseif (!$r['sent'] && !$r['code']) {
                flash('error', 'We could not send the code by email. Try again shortly.');
            } elseif (!$r['sent']) {
                flash('warn', 'The email could not be sent, so your code is shown on this page instead.');
            } else {
                flash('ok', 'A 6-digit code has been sent to ' . $email . '.');
            }
            redirect('verify.php');
        }
    }
}
$title = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="panel narrow">
  <h1>Log in</h1>
  <?php if ($error): ?>
    <div class="flash f-error" role="alert">
      <?= e($error) ?>
      <?php if ($notRegistered): ?> <a href="<?= e(url('register.php')) ?>">Create an account</a> to continue.<?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus autocomplete="email"></label>
    <button class="btn" type="submit">Send my code</button>
  </form>
  <p class="muted">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>