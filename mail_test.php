<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_role('admin');

$to = $u['email'];
$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = strtolower(trim($_POST['to'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $ok = send_mail($to, APP_NAME . ' test email', '<p>If you can read this, email sending works and login codes will arrive.</p>', true);
        $result = ['ok' => $ok, 'error' => $GLOBALS['mail_last_error'] ?? '', 'debug' => $GLOBALS['mail_debug'] ?? ''];
    }
}

// Can this computer reach the mail server at all?
$reach = '';
if (MAIL_ENABLED) {
    $fp = @fsockopen(SMTP_HOST, (int)SMTP_PORT, $errno, $errstr, 5);
    if ($fp) { fclose($fp); $reach = 'Yes'; } else { $reach = "No ($errstr). Check your internet connection and firewall."; }
}
$checks = [
    ['MAIL_ENABLED is true', MAIL_ENABLED, 'Set MAIL_ENABLED to true in config.php.'],
    ['SMTP_USER is filled in', SMTP_USER !== '' && SMTP_USER !== 'youraddress@gmail.com', 'Put your Gmail address in SMTP_USER.'],
    ['SMTP_PASS is filled in', SMTP_PASS !== '' && SMTP_PASS !== 'your-16-char-app-password', 'Put the Google App Password in SMTP_PASS.'],
    ['PHP OpenSSL is on', extension_loaded('openssl'), 'In XAMPP, open php.ini and make sure extension=openssl is not commented out, then restart Apache.'],
    ['PHPMailer files are present', is_file(__DIR__ . '/lib/PHPMailer/PHPMailer.php'), 'The lib/PHPMailer folder is missing. Copy it from the zip.'],
    ['Mail server reachable (' . SMTP_HOST . ':' . SMTP_PORT . ')', $reach === 'Yes', $reach ?: 'Turn MAIL_ENABLED on first.'],
];

$title = 'Email test';
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('admin/users.php')) ?>">&larr; Users</a></p>
<section class="panel narrow wide">
  <h1>Email test</h1>
  <ul class="plain">
    <?php foreach ($checks as [$label, $pass, $fix]): ?>
      <li><strong><?= $pass ? 'OK' : 'Fix' ?></strong> &middot; <?= e($label) ?><?= $pass ? '' : '<br><span class="muted">' . e($fix) . '</span>' ?></li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="panel narrow wide">
  <h2>Send a test email</h2>
  <?php if ($error): ?><div class="flash f-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($result): ?>
    <?php if ($result['ok']): ?>
      <div class="flash f-ok">Sent. Check the inbox of <?= e($to) ?> (and the Spam folder). If it arrives, login codes will too.</div>
    <?php else: ?>
      <div class="flash f-error"><strong>Not sent.</strong> <?= e($result['error']) ?></div>
    <?php endif; ?>
    <?php if ($result['debug']): ?><details><summary>Technical details</summary><pre class="mono" style="white-space:pre-wrap;overflow-x:auto"><?= e($result['debug']) ?></pre></details><?php endif; ?>
  <?php endif; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Send to <input type="email" name="to" value="<?= e($to) ?>" required></label>
    <div><button class="btn" type="submit">Send test email</button></div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
