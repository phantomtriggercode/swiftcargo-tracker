<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

if (admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$admin = $token !== '' ? find_admin_by_reset_token($token) : null;

$errors = [];
$success = false;

if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired — please reload the page and try again.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        set_admin_password((int) $admin['id'], $password);
        $success = true;
    }
}

$pageTitle = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en" data-theme-style="<?= h(active_theme_style_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password | <?= h(get_site_name()) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
<?= theme_style_tag() ?>
</head>
<body>
<div class="login-page">
  <div class="form-card">
    <a href="/index.php" class="logo" style="justify-content:center;margin-bottom:22px;">
      <?php if ($logoUrl = get_logo_url()): ?>
        <img src="<?= h($logoUrl) ?>" alt="" width="36" height="36" class="mark-img">
      <?php else: ?>
        <img src="/assets/images/logo-mark.svg" alt="" width="36" height="36" class="mark-img">
      <?php endif; ?>
      <span><?= h(get_site_name()) ?></span>
    </a>

    <?php if (!$admin): ?>
      <h3 style="text-align:center;margin:0 0 10px;">Link Expired</h3>
      <p style="text-align:center;color:var(--muted);font-size:14px;">
        This password reset link is invalid or has expired. Reset links are only valid for 1 hour.
      </p>
      <p style="text-align:center;margin-top:16px;">
        <a href="/admin/forgot_password.php" style="color:var(--brand-red);">Request a new link</a>
      </p>
    <?php elseif ($success): ?>
      <h3 style="text-align:center;margin:0 0 10px;">Password Updated</h3>
      <p style="text-align:center;color:var(--muted);font-size:14px;">
        Your password has been changed. You can now log in with your new password.
      </p>
      <p style="text-align:center;margin-top:16px;">
        <a href="/admin/login.php" class="btn btn-primary btn-block">Go to Login</a>
      </p>
    <?php else: ?>
      <h3 style="text-align:center;margin:0 0 10px;">Set a New Password</h3>
      <p style="text-align:center;color:var(--muted);font-size:14px;margin-bottom:20px;">
        Choose a new password for <strong><?= h($admin['username']) ?></strong>.
      </p>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= h($err) ?></div>
      <?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="form-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" minlength="8" required autofocus>
        </div>
        <div class="form-group">
          <label for="password_confirm">Confirm New Password</label>
          <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Set New Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
