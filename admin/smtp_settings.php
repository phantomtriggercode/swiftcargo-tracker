<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

$fields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from_email', 'smtp_from_name'];
$errors = [];
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $host = trim($_POST['smtp_host'] ?? '');
        $port = trim($_POST['smtp_port'] ?? '');
        $user = trim($_POST['smtp_user'] ?? '');
        $pass = $_POST['smtp_pass'] ?? '';
        $secure = $_POST['smtp_secure'] ?? 'tls';
        $fromEmail = trim($_POST['smtp_from_email'] ?? '');
        $fromName = trim($_POST['smtp_from_name'] ?? '');

        if ($host === '') $errors[] = 'SMTP host is required.';
        if (!ctype_digit($port)) $errors[] = 'Port must be a number.';
        if ($user === '') $errors[] = 'SMTP username is required.';
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid "from" email is required.';
        if (!in_array($secure, ['tls', 'ssl'], true)) $errors[] = 'Invalid encryption type.';

        if (!$errors) {
            set_setting('smtp_host', $host);
            set_setting('smtp_port', $port);
            set_setting('smtp_user', $user);
            // Keep the existing password if the field was left blank (so the masked value isn't accidentally wiped).
            if ($pass !== '') {
                set_setting('smtp_pass', $pass);
            }
            set_setting('smtp_secure', $secure);
            set_setting('smtp_from_email', $fromEmail);
            set_setting('smtp_from_name', $fromName ?: get_site_name());
            flash_set('success', 'Email settings saved.');
            redirect('/admin/smtp_settings.php');
        }
    } elseif ($action === 'test') {
        $testEmail = trim($_POST['test_email'] ?? '');
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address to send the test to.';
        } else {
            $testResult = send_smtp_mail(
                $testEmail,
                $testEmail,
                get_site_name() . ' — Test Email',
                '<p style="font-family:sans-serif;">This is a test email from your ' . h(get_site_name()) . ' admin panel. If you received this, your SMTP settings are working.</p>',
                'This is a test email from your ' . get_site_name() . ' admin panel. If you received this, your SMTP settings are working.'
            );
        }
    }
}

$cfg = smtp_config();

$activeAdminNav = 'smtp';
$pageTitle = 'Email (SMTP) Settings';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Email (SMTP) Settings</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>
<?php if ($testResult): ?>
  <?php if ($testResult['ok']): ?>
    <div class="alert alert-success">Test email sent successfully.</div>
  <?php else: ?>
    <div class="alert alert-error">Test email failed: <?= h($testResult['error']) ?></div>
  <?php endif; ?>
<?php endif; ?>

<div class="form-card" style="max-width:560px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    Enter any SMTP mailbox's details here — your own webmail from Hostinger
    (hPanel &rarr; Emails &rarr; create a mailbox, then use its host/username/password),
    a Gmail account with an <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--brand-red);">App Password</a>,
    or a free <a href="https://ethereal.email" target="_blank" style="color:var(--brand-red);">Ethereal</a> test
    inbox for development. No third-party email API is used — this connects
    directly to the mailbox over standard SMTP.
  </p>

  <form method="post">
    <input type="hidden" name="action" value="save">
    <div class="form-row">
      <div class="form-group">
        <label>SMTP Host</label>
        <input type="text" name="smtp_host" value="<?= h($cfg['host']) ?>" placeholder="e.g. smtp.hostinger.com" required>
      </div>
      <div class="form-group">
        <label>Port</label>
        <input type="text" name="smtp_port" value="<?= h((string) $cfg['port']) ?>" placeholder="587" required>
      </div>
    </div>
    <div class="form-group">
      <label>Encryption</label>
      <select name="smtp_secure">
        <option value="tls" <?= $cfg['secure'] === 'tls' ? 'selected' : '' ?>>TLS (usually port 587)</option>
        <option value="ssl" <?= $cfg['secure'] === 'ssl' ? 'selected' : '' ?>>SSL (usually port 465)</option>
      </select>
    </div>
    <div class="form-group">
      <label>SMTP Username</label>
      <input type="text" name="smtp_user" value="<?= h($cfg['user']) ?>" placeholder="e.g. tracking@yourdomain.com" required>
    </div>
    <div class="form-group">
      <label>SMTP Password</label>
      <input type="password" name="smtp_pass" placeholder="<?= $cfg['pass'] !== '' ? 'Leave blank to keep current password' : 'Enter password' ?>" autocomplete="new-password">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>"From" Email</label>
        <input type="text" name="smtp_from_email" value="<?= h($cfg['from_email']) ?>" placeholder="e.g. tracking@yourdomain.com" required>
      </div>
      <div class="form-group">
        <label>"From" Name</label>
        <input type="text" name="smtp_from_name" value="<?= h($cfg['from_name']) ?>" placeholder="<?= h(get_site_name()) ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Email Settings</button>
  </form>
</div>

<div class="form-card" style="max-width:560px;margin-top:16px;">
  <h3 style="margin-top:0;">Send a Test Email</h3>
  <form method="post">
    <input type="hidden" name="action" value="test">
    <div class="form-group">
      <label>Send test to</label>
      <input type="email" name="test_email" placeholder="you@example.com" required>
    </div>
    <button type="submit" class="btn btn-outline btn-block">Send Test Email</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
