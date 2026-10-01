<?php
/**
 * Sign-in security: the switch for emailed sign-in codes on new browsers,
 * how long a browser is remembered, and a way to make every remembered
 * browser prove itself again. Super admin only.
 *
 * Turning codes on is a two-step affair on purpose. If codes were switched
 * on while the site's email was not actually arriving, nobody could get
 * into the panel from a new browser. So a code is first sent to the super
 * admin doing it, and the switch only flips once that code has been typed
 * back in, which proves the whole chain works.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/mailer.php';
require_super_admin();

$me = current_admin();
$errors = [];
$codesOn = login_codes_enabled();
$forcedOff = defined('LOGIN_CODES_FORCE_OFF') && LOGIN_CODES_FORCE_OFF;

$smtp = smtp_config();
$smtpReady = trim((string) $smtp['host']) !== '' && filter_var(trim((string) $smtp['from_email']), FILTER_VALIDATE_EMAIL);
$myEmail = trim((string) ($me['email'] ?? ''));
$myEmailOk = $myEmail !== '' && filter_var($myEmail, FILTER_VALIDATE_EMAIL);

$enableIntro = 'Someone is turning on sign-in codes for the ' . get_site_name() . ' admin panel from your account. Enter this code on the Sign-in Security page to confirm your email works:';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'send_enable_code') {
        if (!$smtpReady) {
            $errors[] = 'Set up the site\'s email under Email (SMTP) first. Codes are sent by email.';
        } elseif (!$myEmailOk) {
            $errors[] = 'Add your own email address under My Profile first. The confirmation code goes there.';
        } else {
            $sent = confirmation_code_send($me, 'enable_login_codes', $enableIntro);
            if ($sent['ok']) {
                flash_set('success', 'A confirmation code was sent to ' . masked_email($myEmail) . '. Enter it below to turn sign-in codes on.');
                redirect('/admin/security.php');
            }
            $errors[] = match ($sent['error'] ?? '') {
                'wait'     => 'Please wait ' . (int) ($sent['wait'] ?? 60) . ' seconds before asking for another code.',
                'too_many' => 'Too many codes have been sent. Please wait ' . max(1, (int) ceil(($sent['wait'] ?? 900) / 60)) . ' minutes.',
                'no_email' => 'Add your own email address under My Profile first.',
                default    => 'The email could not be sent. Check the settings under Email (SMTP) and send a test email from there. '
                    . (($sent['detail'] ?? '') !== '' ? '(' . $sent['detail'] . ')' : ''),
            };
        }
    } elseif ($action === 'confirm_enable') {
        $check = confirmation_code_check((int) $me['id'], 'enable_login_codes', (string) ($_POST['code'] ?? ''));
        if ($check['ok']) {
            set_setting('login_otp_enabled', '1');
            // The browser that just proved it can receive the code is the
            // one turning this on; it does not need to be asked again.
            remember_this_browser((int) $me['id']);
            log_admin_activity('Turned on sign-in codes', 'New browsers now need an emailed code');
            flash_set('success', 'Sign-in codes are on. From now on, every admin signing in from a new browser is emailed a code.');
            redirect('/admin/security.php');
        }
        $errors[] = match ($check['error'] ?? '') {
            'wrong'        => 'That code is not right. ' . (int) ($check['tries_left'] ?? 0) . ' tries left.',
            'locked'       => 'Too many wrong codes. Send a new code and try again.',
            'code_expired' => 'That code has expired. Send a new one.',
            'too_many'     => 'Too many attempts. Please wait a few minutes.',
            default        => 'Send a confirmation code first.',
        };
    } elseif ($action === 'disable') {
        // Turning a protection off asks for the password again, so a
        // session left open on someone's desk cannot quietly do it.
        $hashStmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $hashStmt->execute([(int) $me['id']]);
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $hashStmt->fetchColumn())) {
            login_failure_delay();
            $errors[] = 'Your password was not right, so sign-in codes were left on.';
        } else {
            set_setting('login_otp_enabled', '0');
            log_admin_activity('Turned off sign-in codes', 'A password alone signs in again');
            flash_set('success', 'Sign-in codes are off. A correct password alone signs an admin in.');
            redirect('/admin/security.php');
        }
    } elseif ($action === 'save_days') {
        $days = (int) ($_POST['remember_days'] ?? 30);
        if (!in_array($days, TRUSTED_BROWSER_DAY_CHOICES, true)) {
            $errors[] = 'Please choose one of the listed periods.';
        } else {
            set_setting('login_otp_remember_days', (string) $days);
            log_admin_activity('Changed remembered-browser period', trusted_browser_days_label($days));
            flash_set('success', 'Saved. Browsers verified from now on are remembered for ' . trusted_browser_days_label($days) . '.');
            redirect('/admin/security.php');
        }
    } elseif ($action === 'forget_everyone') {
        $count = 0;
        try {
            $count = (int) db()->exec('DELETE FROM admin_trusted_browsers');
        } catch (PDOException $e) {
            $errors[] = 'The remembered-browser list is not set up yet. Import the latest sql/schema.sql.';
        }
        if (!$errors) {
            write_trusted_browser_cookie([], 1);
            log_admin_activity('Forgot every remembered browser', $count . ' browser(s), all admins');
            flash_set('success', 'Done. Every admin, you included, will be asked for an emailed code the next time they sign in.');
            redirect('/admin/security.php');
        }
    }
}

// Accounts that could not receive a code if one were needed.
$noEmail = db()->query("SELECT id, username, full_name, is_active FROM admins WHERE email IS NULL OR email = '' ORDER BY full_name")->fetchAll();

$rememberedTotal = null;
try {
    $rememberedTotal = (int) db()->query('SELECT COUNT(*) FROM admin_trusted_browsers WHERE expires_at > NOW()')->fetchColumn();
} catch (PDOException $e) {
    $rememberedTotal = null;
}

$awaitingCode = confirmation_code_outstanding((int) $me['id'], 'enable_login_codes');
$days = trusted_browser_days();

$activeAdminNav = 'security';
$pageTitle = 'Sign-in Security';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Sign-in Security</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<?php if ($rememberedTotal === null): ?>
  <div class="alert alert-error" style="max-width:720px;">
    The database is missing the remembered-browsers table. Import the latest
    <code>sql/schema.sql</code> (it skips everything already there) before turning codes on.
  </div>
<?php endif; ?>

<div class="form-card form-card--super" style="max-width:720px;">
  <h3 style="margin-top:0;">Emailed codes for new browsers</h3>

  <p style="font-size:14px;color:var(--ink-soft);margin-top:0;">
    When this is on, a correct password is not enough the first time an admin
    signs in from a browser. A <?= LOGIN_CODE_LENGTH ?>-digit code is emailed to
    the address on their account, and the panel only opens once they type it in.
    After that the browser is remembered, so the code is only asked for again on
    a different browser, after its cookies are cleared, after the remembered
    period ends, or after a password change.
  </p>

  <div class="status-line <?= $codesOn ? 'status-line--on' : 'status-line--off' ?>">
    <strong>Status:</strong>
    <?php if ($forcedOff): ?>
      Off, because <code>LOGIN_CODES_FORCE_OFF</code> is set in config.php. Remove that line to use this switch again.
    <?php elseif ($codesOn): ?>
      On. New browsers need an emailed code.
    <?php else: ?>
      Off. A correct password alone signs an admin in.
    <?php endif; ?>
  </div>

  <?php if ($noEmail): ?>
    <div class="alert alert-error" style="margin-top:14px;">
      <strong><?= count($noEmail) === 1 ? 'This account has' : 'These accounts have' ?> no email address</strong>
      and could not receive a code<?= $codesOn ? ', so they cannot sign in from a new browser until one is added' : '' ?>:
      <?php foreach ($noEmail as $i => $row): ?>
        <?= $i ? ', ' : '' ?><a href="/admin/admin_edit.php?id=<?= (int) $row['id'] ?>" style="color:inherit;text-decoration:underline;"><?= h($row['full_name']) ?> (<?= h($row['username']) ?>)</a>
      <?php endforeach; ?>.
    </div>
  <?php endif; ?>

  <?php if (!$codesOn && !$forcedOff): ?>
    <h4 style="margin:20px 0 8px;">Before you turn it on</h4>
    <ul class="check-list">
      <li class="<?= $smtpReady ? 'ok' : 'bad' ?>">
        The site can send email.
        <?= $smtpReady ? 'Settings found.' : 'Not set up yet: fill in <a href="/admin/smtp_settings.php">Email (SMTP)</a> first.' ?>
      </li>
      <li class="<?= $myEmailOk ? 'ok' : 'bad' ?>">
        Your account has an email address.
        <?= $myEmailOk ? h(masked_email($myEmail)) : 'Add one under <a href="/admin/profile.php">My Profile</a>.' ?>
      </li>
      <li class="<?= $noEmail ? 'bad' : 'ok' ?>">
        Every admin has an email address.
        <?= $noEmail ? 'Not yet, see the list above.' : 'Yes.' ?>
      </li>
    </ul>

    <?php if ($awaitingCode): ?>
      <form method="post" style="margin-top:16px;" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="confirm_enable">
        <div class="form-group">
          <label for="code">Enter the code we just emailed you</label>
          <input type="text" id="code" name="code" class="otp-input" required inputmode="numeric"
                 autocomplete="one-time-code" maxlength="<?= LOGIN_CODE_LENGTH + 2 ?>" placeholder="<?= str_repeat('•', LOGIN_CODE_LENGTH) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Confirm and turn on</button>
      </form>
      <form method="post" style="margin-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_enable_code">
        <button type="submit" class="link-button">Send another code</button>
      </form>
    <?php else: ?>
      <form method="post" style="margin-top:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_enable_code">
        <button type="submit" class="btn btn-primary" <?= ($smtpReady && $myEmailOk && $rememberedTotal !== null) ? '' : 'disabled' ?>>Email me a code to turn this on</button>
        <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:8px;">
          Proves the email really arrives before anyone depends on it. Codes stay off until you type it back in.
        </span>
      </form>
    <?php endif; ?>
  <?php elseif ($codesOn): ?>
    <form method="post" style="margin-top:18px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="disable">
      <div class="form-group" style="max-width:360px;">
        <label for="current_password">To turn codes off, enter your password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger);">Turn sign-in codes off</button>
    </form>
  <?php endif; ?>
</div>

<div class="form-card form-card--super" style="max-width:720px;margin-top:16px;">
  <h3 style="margin-top:0;">How long a browser is remembered</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_days">
    <div class="form-group" style="max-width:360px;">
      <label for="remember_days">After entering a code, skip it on that browser for</label>
      <select id="remember_days" name="remember_days">
        <?php foreach (TRUSTED_BROWSER_DAY_CHOICES as $choice): ?>
          <option value="<?= $choice ?>" <?= $choice === $days ? 'selected' : '' ?>><?= h(ucfirst(trusted_browser_days_label($choice))) ?></option>
        <?php endforeach; ?>
      </select>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Clearing the browser's cookies always brings the code back, whatever this says.
        Each admin can also untick "Remember this browser" on a shared computer.
      </span>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
  </form>
</div>

<div class="form-card form-card--super" style="max-width:720px;margin-top:16px;">
  <h3 style="margin-top:0;">Forget every remembered browser</h3>
  <p style="font-size:14px;color:var(--ink-soft);margin-top:0;">
    <?php if ($rememberedTotal !== null): ?>
      <?= $rememberedTotal ?> browser<?= $rememberedTotal === 1 ? ' is' : 's are' ?> remembered across all accounts.
    <?php endif; ?>
    Use this if you think a password or a computer may have been compromised:
    every admin, you included, has to enter an emailed code at their next sign-in.
    Each admin can see and remove their own browsers under My Profile.
  </p>
  <form method="post" onsubmit="return confirm('Forget every remembered browser for every admin?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="forget_everyone">
    <button type="submit" class="btn btn-outline">Forget all remembered browsers</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
