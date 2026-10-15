<?php
require_once __DIR__ . '/includes/functions.php';
if ($u = current_user()) redirect(home_for($u['role']));

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email'] = strtolower(trim($_POST['email'] ?? ''));
    $old['phone'] = preg_replace('/[\s\-()]/', '', trim($_POST['phone'] ?? ''));

    if (mb_strlen($old['full_name']) < 3) $errors[] = 'Enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^\+?[0-9]{9,15}$/', $old['phone'])) $errors[] = 'Enter a valid phone number, for example 0712345678.';

    if (!$errors) {
        $s = db()->prepare('SELECT id FROM users WHERE email = ?');
        $s->execute([$old['email']]);
        if ($s->fetch()) $errors[] = 'That email is already registered. Log in instead.';
    }
    if (!$errors) {
        db()->prepare("INSERT INTO users (full_name, email, phone, role) VALUES (?,?,?,'customer')")
            ->execute([$old['full_name'], $old['email'], $old['phone']]);
        flash('ok', 'Account created. Enter your email to get a login code.');
        redirect('login.php');
    }
}
$title = 'Register';
require __DIR__ . '/includes/header.php';
?>
<section class="panel narrow">
  <h1>Create an account</h1>
  <?php foreach ($errors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Full name <input name="full_name" value="<?= e($old['full_name']) ?>" required autocomplete="name"></label>
    <label>Email <input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></label>
    <label>Phone number <input type="tel" name="phone" value="<?= e($old['phone']) ?>" placeholder="+1" required autocomplete="tel"></label>
    <button class="btn" type="submit">Create account</button>
  </form>
  <p class="muted">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
