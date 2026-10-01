<?php
/**
 * Second step of signing in from a browser that has not been seen before:
 * the six-digit code emailed to the admin. Only reachable straight after a
 * correct password (see admin/login.php); anyone else is sent back there.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

enforce_admin_gate();

if (admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$pending = login_code_pending();
$admin = $pending ? login_code_admin() : null;
if (!$pending || !$admin) {
    login_code_clear();
    flash_set('error', 'Your sign-in timed out. Please enter your password again.');
    redirect('/admin/login.php');
}

$error = null;
$notice = flash_get('code_notice');
$ip = client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'verify');

    if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'This page was open too long. Please try again.';
    } elseif ($action === 'restart') {
        login_code_clear();
        redirect('/admin/login.php');
    } elseif ($action === 'resend') {
        $sent = login_code_send();
        if ($sent['ok']) {
            flash_set('code_notice', 'A new code is on its way. Only the newest code works.');
            redirect('/admin/login_code.php');
        }
        $error = match ($sent['error'] ?? '') {
            'wait'     => 'Please wait ' . (int) ($sent['wait'] ?? 60) . ' seconds before asking for another code.',
            'too_many' => 'Too many codes have been sent. Please wait ' . max(1, (int) ceil(($sent['wait'] ?? 900) / 60)) . ' minutes.',
            'expired'  => 'Your sign-in timed out. Please enter your password again.',
            default    => 'The email could not be sent just now. Please try again in a minute.',
        };
        if (($sent['error'] ?? '') === 'expired') {
            flash_set('error', $error);
            redirect('/admin/login.php');
        }
    } else {
        $result = login_code_verify((string) ($_POST['code'] ?? ''));
        if ($result['ok']) {
            $remember = !empty($_POST['remember']);
            record_login_attempt($ip, (string) $pending['identifier'], true);
            complete_admin_login($admin, 'With an emailed code' . ($remember && trusted_browser_days() > 0 ? ', browser remembered' : ''));
            if ($remember) {
                remember_this_browser((int) $admin['id']);
            }
            redirect('/admin/dashboard.php');
        }

        // Every wrong code also counts against the account's and the
        // address's ordinary sign-in limits.
        if (in_array($result['error'] ?? '', ['wrong', 'locked'], true)) {
            record_login_attempt($ip, (string) $pending['identifier'], false);
            login_failure_delay();
        }

        switch ($result['error'] ?? '') {
            case 'locked':
                log_admin_activity('Sign-in code failed', 'Too many wrong codes; sign-in restarted', (int) $admin['id'], $admin['full_name']);
                flash_set('error', 'Too many wrong codes. For your security, please sign in again to get a new code.');
                redirect('/admin/login.php');
                break;
            case 'expired':
                flash_set('error', 'Your sign-in timed out. Please enter your password again.');
                redirect('/admin/login.php');
                break;
            case 'code_expired':
                $error = 'That code has expired. Press "Send a new code" and use the newest email.';
                break;
            case 'too_many':
                $error = 'Too many attempts from your network. Please wait ' . max(1, (int) ceil(($result['wait'] ?? 900) / 60)) . ' minutes.';
                break;
            default:
                $left = (int) ($result['tries_left'] ?? 0);
                $error = 'That code is not right. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.';
        }
    }
}

$days = trusted_browser_days();
$wait = login_code_resend_wait();
?>
<!DOCTYPE html>
<html lang="en" data-area="admin">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Enter your code | <?= h(get_site_name()) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
<?= palette_style_tag() ?>
</head>
<body>
<div class="login-page">
  <div class="form-card">
    <a href="/index.php" class="logo" style="justify-content:center;margin-bottom:22px;">
      <?php $authLockup = logo_stands_alone(); ?>
      <?= logo_img_tag($authLockup ? 54 : 46, $authLockup ? 230 : 84, 'mark-img', $authLockup ? get_site_name() : '') ?>
      <?php if (!$authLockup && header_shows_title()): ?>
        <span><?= h(get_site_name()) ?></span>
      <?php endif; ?>
    </a>
    <h3 style="text-align:center;margin:0 0 8px;">Check your email</h3>
    <p style="text-align:center;color:var(--muted);font-size:14px;margin:0 0 18px;">
      This browser is new to your account, so we sent a <?= LOGIN_CODE_LENGTH ?>-digit code to
      <strong style="color:var(--ink);"><?= h(masked_email((string) $admin['email'])) ?></strong>.
      It expires in <?= (int) (LOGIN_CODE_TTL_SECONDS / 60) ?> minutes.
    </p>

    <?php if ($notice): ?>
      <div class="alert alert-success"><?= h($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <div class="form-group">
        <label for="code">Sign-in code</label>
        <input type="text" id="code" name="code" class="otp-input" required autofocus
               inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{<?= LOGIN_CODE_LENGTH ?>,<?= LOGIN_CODE_LENGTH + 2 ?>}"
               maxlength="<?= LOGIN_CODE_LENGTH + 2 ?>" placeholder="<?= str_repeat('•', LOGIN_CODE_LENGTH) ?>">
      </div>
      <?php if ($days > 0): ?>
        <div class="form-group">
          <label style="display:flex;align-items:flex-start;gap:8px;font-weight:normal;font-size:13.5px;">
            <input type="checkbox" name="remember" value="1" checked style="margin-top:3px;">
            <span>Remember this browser for <?= h(trusted_browser_days_label($days)) ?>. Untick on a shared or public computer.</span>
          </label>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-block">Verify and sign in</button>
    </form>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px;">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="resend">
        <button type="submit" class="btn btn-outline btn-sm" id="resend-btn" data-wait="<?= (int) $wait ?>">Send a new code</button>
      </form>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restart">
        <button type="submit" class="link-button">Use a different account</button>
      </form>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin:14px 0 0;line-height:1.5;">
      Nothing arrived? Check your spam or junk folder. Codes can take a minute.
    </p>
  </div>
</div>
<script>
  (function () {
    // Counts down until "Send a new code" is allowed again, so the button
    // is not pressed only to be told to wait.
    var btn = document.getElementById('resend-btn');
    if (!btn) return;
    var wait = parseInt(btn.getAttribute('data-wait'), 10) || 0;
    var label = btn.textContent;
    function tick() {
      if (wait <= 0) { btn.disabled = false; btn.textContent = label; return; }
      btn.disabled = true;
      btn.textContent = label + ' (' + wait + 's)';
      wait -= 1;
      window.setTimeout(tick, 1000);
    }
    tick();
  })();
</script>
</body>
</html>
