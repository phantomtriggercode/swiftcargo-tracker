<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

if (admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    // Trimmed like the username — copying a password from Notes, Mail, or a
    // password manager on a phone very often carries an invisible trailing
    // newline or space along with it, which would otherwise fail silently.
    $password = trim((string) ($_POST['password'] ?? ''));

    if (attempt_admin_login($username, $password)) {
        redirect('/admin/dashboard.php');
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme-style="<?= h(active_theme_style_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Login | <?= h(get_site_name()) ?></title>
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
    <h3 style="text-align:center;margin:0 0 20px;">Staff Login</h3>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="username">Username or Email</label>
        <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus
          autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>
    <p style="text-align:center;margin:16px 0 0;font-size:13.5px;">
      <a href="/admin/forgot_password.php" style="color:var(--brand-red);">Forgot your password?</a>
    </p>
  </div>
</div>
</body>
</html>
