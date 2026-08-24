<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/mailer.php';

if (admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$errors = [];
$submitted = false;
$ip = client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired — please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (login_rate_limit_check($ip, 'reset:' . strtolower($email)) !== null) {
        $errors[] = 'Too many requests. Please wait a while and try again.';
    }

    if (!$errors) {
        record_login_attempt($ip, 'reset:' . strtolower($email), false);
        $token = create_password_reset($email);
        if ($token !== null) {
            $resetUrl = get_site_url() . '/admin/reset_password.php?token=' . $token;
            $siteName = get_site_name();
            $theme = get_active_palette();
            $htmlBody = '<div style="font-family:Arial,sans-serif;font-size:14px;color:' . h($theme['color_ink']) . ';">'
                . '<p>We received a request to reset the password for the ' . h($siteName) . ' admin account tied to this email.</p>'
                . '<p><a href="' . h($resetUrl) . '" style="display:inline-block;background:' . h($theme['color_primary']) . ';color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Reset Password</a></p>'
                . '<p style="color:#6b7280;font-size:12.5px;">This link expires in 1 hour. If you didn\'t request this, you can ignore this email — your password won\'t change.</p>'
                . '</div>';
            $altBody = "Reset your {$siteName} admin password: {$resetUrl}\n\nThis link expires in 1 hour. If you didn't request this, you can ignore this email.";
            send_smtp_mail($email, $siteName . ' Admin', 'Reset your ' . $siteName . ' admin password', $htmlBody, $altBody);
        }
        // Always show the same message, whether or not that email is registered —
        // this keeps the form from revealing which emails have admin accounts.
        $submitted = true;
    }
}

$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en" data-template="<?= h(active_template_layout_key()) ?>" data-animation="<?= h(active_template_animation_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password | <?= h(get_site_name()) ?></title>
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
    <h3 style="text-align:center;margin:0 0 10px;">Reset Your Password</h3>

    <?php if ($submitted): ?>
      <p style="text-align:center;color:var(--muted);font-size:14px;">
        If an admin account exists for that email, we've sent a link to reset the password.
        It expires in 1 hour.
      </p>
      <p style="text-align:center;margin-top:16px;">
        <a href="/admin/login.php" style="color:var(--brand-red);">Back to login</a>
      </p>
    <?php else: ?>
      <p style="text-align:center;color:var(--muted);font-size:14px;margin-bottom:20px;">
        Enter the email address on your admin account and we'll send you a link to reset your password.
      </p>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= h($err) ?></div>
      <?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
      </form>
      <p style="text-align:center;margin:16px 0 0;font-size:13.5px;">
        <a href="/admin/login.php" style="color:var(--brand-red);">Back to login</a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
