<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin_base();

$admin = current_admin();
if (!$admin) {
    redirect('/admin/login.php');
}
if (empty($admin['must_change_password'])) {
    redirect('/admin/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired — please reload the page and try again.';
    }
    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($newPassword !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (!$errors) {
        set_admin_password($admin['id'], $newPassword);
        flash_set('success', 'Password updated.');
        redirect('/admin/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-template="<?= h(active_template_layout_key()) ?>" data-animation="<?= h(active_template_animation_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set a New Password | <?= h(get_site_name()) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
<?= palette_style_tag() ?>
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
    <h3 style="text-align:center;margin:0 0 8px;">Set a New Password</h3>
    <p style="text-align:center;color:var(--muted);font-size:13.5px;margin:0 0 20px;">
      Please set a new password to continue.
    </p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= h($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" minlength="8" required autofocus autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="new_password_confirm">Confirm New Password</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" minlength="8" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Set Password</button>
    </form>
    <p style="text-align:center;margin:16px 0 0;font-size:13.5px;">
      <a href="/admin/logout.php" style="color:var(--muted);">Log out instead</a>
    </p>
  </div>
</div>
</body>
</html>
