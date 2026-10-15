<?php
require_once __DIR__ . '/../includes/functions.php';
$u = require_role('admin');
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['do'] ?? '') === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$u['id']) {
            $errors[] = 'You cannot deactivate your own account.';
        } else {
            $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('ok', 'User updated.');
            redirect('admin/users.php');
        }
    } elseif (($_POST['do'] ?? '') === 'add') {
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = preg_replace('/[\s\-()]/', '', trim($_POST['phone'] ?? ''));
        $role = $_POST['role'] ?? '';
        if (mb_strlen($name) < 3) $errors[] = 'Enter the full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
        if (!preg_match('/^\+?[0-9]{9,15}$/', $phone)) $errors[] = 'Enter a valid phone number.';
        if (!in_array($role, ['customer', 'admin', 'finance'], true)) $errors[] = 'Choose a role.';
        if (!$errors) {
            $x = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $x->execute([$email]);
            if ($x->fetch()) $errors[] = 'That email already has an account.';
        }
        if (!$errors) {
            $pdo->prepare('INSERT INTO users (full_name, email, phone, role) VALUES (?,?,?,?)')->execute([$name, $email, $phone, $role]);
            flash('ok', 'User added. They can log in with their email.');
            redirect('admin/users.php');
        }
    }
}
$users = $pdo->query('SELECT * FROM users ORDER BY role, full_name')->fetchAll();
$title = 'Users';
require __DIR__ . '/../includes/header.php';
?>
<h1>Users</h1>
<p class="muted">Login codes not arriving? <a href="<?= e(url('mail_test.php')) ?>">Test the email settings</a>.</p>
<?php foreach ($errors as $err): ?><div class="flash f-error"><?= e($err) ?></div><?php endforeach; ?>
<section class="panel">
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $x): ?>
      <tr>
        <td><?= e($x['full_name']) ?></td><td><?= e($x['email']) ?></td><td class="mono"><?= e($x['phone']) ?></td>
        <td><?= e(ucfirst($x['role'])) ?></td><td><?= $x['is_active'] ? 'Active' : 'Deactivated' ?></td>
        <td>
          <?php if ((int)$x['id'] !== (int)$u['id']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
            <button class="btn small ghost"><?= $x['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<section class="panel narrow wide">
  <h2>Add a user</h2>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="do" value="add">
    <div class="grid2">
      <label>Full name <input name="full_name" required></label>
      <label>Email <input type="email" name="email" required></label>
      <label>Phone <input type="tel" name="phone" required></label>
      <label>Role <select name="role"><option value="finance">Finance</option><option value="admin">Admin</option><option value="customer">Customer</option></select></label>
    </div>
    <button class="btn" type="submit">Add user</button>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
