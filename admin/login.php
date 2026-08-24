<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

if (admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error = flash_get('error');
$ip = client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    // Trimmed like the username — copying a password from Notes, Mail, or a
    // password manager on a phone very often carries an invisible trailing
    // newline or space along with it, which would otherwise fail silently.
    $password = trim((string) ($_POST['password'] ?? ''));
    $honeypot = (string) ($_POST['website'] ?? '');
    $captchaAnswer = (string) ($_POST['captcha_answer'] ?? '');
    $csrfOk = csrf_verify((string) ($_POST['csrf_token'] ?? ''));

    $rateLimitMsg = login_rate_limit_check($ip, $username);

    if ($rateLimitMsg !== null) {
        $error = $rateLimitMsg;
    } elseif (!$csrfOk) {
        // Two very different problems both land here, and telling them apart
        // matters: an empty session means the browser never sent the session
        // cookie back at all (cookies blocked, or the cookie was rejected),
        // whereas a session that exists but holds a different token is the
        // ordinary "this form sat open too long" case.
        $error = empty($_SESSION['csrf_token'])
            ? 'Your browser did not keep the login session. This usually means cookies are '
              . 'blocked for this site, or you are on an http:// address while the site is '
              . 'secured with https:// — open the site at its https:// address and try again.'
            : 'This login form was open too long — please try again.';
    } elseif (honeypot_tripped($honeypot)) {
        // A real visitor never sees or fills this field — only a bot
        // filling every input does. Treat it exactly like a wrong
        // password: no hint that a trap was sprung.
        record_login_attempt($ip, $username, false);
        $error = 'Invalid username or password.';
    } elseif (!verify_captcha($captchaAnswer)) {
        record_login_attempt($ip, $username, false);
        $error = 'Incorrect answer to the security question below — please try again.';
    } else {
        $loginOk = attempt_admin_login($username, $password);
        record_login_attempt($ip, $username, $loginOk);
        if ($loginOk) {
            redirect('/admin/dashboard.php');
        }
        $error = 'Invalid username or password.';
    }
}

$captcha = new_captcha_challenge();
?>
<!DOCTYPE html>
<html lang="en" data-template="<?= h(active_template_layout_key()) ?>" data-animation="<?= h(active_template_animation_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?= h(get_site_name()) ?></title>
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
    <h3 style="text-align:center;margin:0 0 20px;">Login</h3>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
      </div>
      <div class="form-group">
        <label for="username">Username or Email</label>
        <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus
          autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label for="captcha_answer">Security check: what is <?= (int) $captcha['a'] ?> + <?= (int) $captcha['b'] ?>?</label>
        <input type="text" inputmode="numeric" pattern="[0-9]*" id="captcha_answer" name="captcha_answer" required autocomplete="off">
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
