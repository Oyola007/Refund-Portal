<?php
$u = current_user();
$title = $title ?? APP_NAME;
$flashes = flashes();
$unreadAll = 0;
if ($u) { try { $unreadAll = inbox_unread($u); } catch (Throwable $e) { $unreadAll = 0; } }
$nav = [
    'customer' => ['customer/dashboard.php' => 'My refunds', 'customer/new_refund.php' => 'New refund'],
    'admin'    => ['admin/dashboard.php' => 'Overview', 'admin/refunds.php' => 'Refunds', 'admin/users.php' => 'Users'],
    'finance'  => ['finance/dashboard.php' => 'Payouts'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css') . '?v=' . (int)@filemtime(__DIR__ . '/../assets/css/style.css')) ?>">
</head>
<body>
<header class="top">
  <a class="brand" href="<?= e(url($u ? home_for($u['role']) : '')) ?>"><?= e(APP_NAME) ?></a>
  <nav>
    <?php if ($u): foreach ($nav[$u['role']] as $path => $label): ?>
      <a href="<?= e(url($path)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
      <a href="<?= e(url('messages.php')) ?>">Messages<?= $unreadAll ? ' <span class="dot">' . $unreadAll . '</span>' : '' ?></a>
      <span class="who"><?= e($u['full_name']) ?></span>
      <a class="btn small ghost" href="<?= e(url('logout.php')) ?>">Log out</a>
    <?php else: ?>
      <a href="<?= e(url('login.php')) ?>">Log in</a>
      <a class="btn small" href="<?= e(url('register.php')) ?>">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main class="wrap">
<?php foreach ($flashes as $f): ?>
  <div class="flash f-<?= e($f['t']) ?>" role="status"><?= e($f['m']) ?></div>
<?php endforeach; ?>
