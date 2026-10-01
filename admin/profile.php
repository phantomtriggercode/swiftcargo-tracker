<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$admin = current_admin();
if (!$admin) {
    redirect('/admin/login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($fullName === '') $errors[] = 'Full name cannot be empty.';
        if ($username === '') $errors[] = 'Username cannot be empty.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address, or leave it blank.';
        // With sign-in codes on, the email is how this admin gets into the
        // panel from any new browser. Clearing it would lock them out.
        if ($email === '' && login_codes_enabled()) $errors[] = 'An email address is required while sign-in codes are on: it is where your sign-in codes are sent.';

        if (!$errors) {
            $dupCheck = db()->prepare('SELECT id FROM admins WHERE (username = ? OR (email = ? AND email IS NOT NULL AND email != "")) AND id != ?');
            $dupCheck->execute([$username, $email, $admin['id']]);
            if ($dupCheck->fetch()) {
                $errors[] = 'That username or email is already used by another admin account.';
            }
        }

        if (!$errors) {
            $stmt = db()->prepare('UPDATE admins SET full_name = ?, username = ?, email = ? WHERE id = ?');
            $stmt->execute([$fullName, $username, $email !== '' ? $email : null, $admin['id']]);
            $_SESSION['admin_name'] = $fullName;
            flash_set('success', 'Profile updated.');
            redirect('/admin/profile.php');
        }
    } elseif ($action === 'forget_browser') {
        if (forget_trusted_browser((int) $admin['id'], (int) ($_POST['browser_id'] ?? 0))) {
            log_admin_activity('Forgot a remembered browser');
            flash_set('success', 'That browser will be asked for an emailed code at its next sign-in.');
        }
        redirect('/admin/profile.php');
    } elseif ($action === 'forget_other_browsers') {
        $count = forget_trusted_browsers((int) $admin['id'], true);
        log_admin_activity('Forgot remembered browsers', $count . ' browser(s), keeping this one');
        flash_set('success', $count === 1 ? '1 other browser forgotten.' : $count . ' other browsers forgotten.');
        redirect('/admin/profile.php');
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        $full = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $full->execute([$admin['id']]);
        $hash = $full->fetchColumn();

        if (!password_verify($current, (string) $hash)) {
            $errors[] = 'Current password is incorrect.';
        }
        if (($pwError = password_policy_error($newPassword)) !== null) {
            $errors[] = $pwError;
        }
        if ($newPassword !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (!$errors) {
            set_admin_password($admin['id'], $newPassword);
            flash_set('success', 'Password changed.');
            redirect('/admin/profile.php');
        }
    }
}

$activeAdminNav = 'profile';
$pageTitle = 'My Profile';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>My Profile</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:520px;">
  <h3 style="margin-top:0;">Account Details</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" value="<?= h($admin['full_name']) ?>" required>
    </div>
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" value="<?= h($admin['username']) ?>" required>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?= h($admin['email'] ?? '') ?>" placeholder="you@yourdomain.com">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Lets you log in with your email instead of your username, and is required for "Forgot password?" to work.
        <?php if (login_codes_enabled()): ?>
          Sign-in codes for new browsers are sent here too, so it must be an inbox you can open.
        <?php endif; ?>
      </span>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Details</button>
  </form>
</div>

<div class="form-card" style="max-width:520px;margin-top:16px;">
  <h3 style="margin-top:0;">Change Password</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <div class="form-group">
      <label>Current Password</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input type="password" name="new_password" minlength="12" required>
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input type="password" name="new_password_confirm" minlength="12" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Change Password</button>
  </form>
</div>

<?php $myBrowsers = list_trusted_browsers((int) $admin['id']); ?>
<div class="form-card" style="max-width:520px;margin-top:16px;">
  <h3 style="margin-top:0;">Remembered browsers</h3>
  <?php if (!login_codes_enabled()): ?>
    <p style="font-size:13.5px;color:var(--muted);margin-top:0;">
      Sign-in codes are off, so a password alone signs you in at the moment.
      The browsers below are remembered for when they are turned back on.
    </p>
  <?php else: ?>
    <p style="font-size:13.5px;color:var(--muted);margin-top:0;">
      These browsers can sign in with your password alone. Any other browser is
      emailed a code first. Remove one you no longer use or do not recognise.
    </p>
  <?php endif; ?>
  <?php if (!$myBrowsers): ?>
    <p style="font-size:14px;margin-bottom:0;">No browsers are remembered for your account.</p>
  <?php else: ?>
    <ul class="browser-list">
      <?php foreach ($myBrowsers as $b): ?>
        <li>
          <div>
            <strong><?= h($b['browser_label'] ?: 'A browser') ?></strong>
            <?php if ($b['is_this_browser']): ?><span class="badge badge-delivered" style="font-size:11px;padding:2px 8px;margin-left:6px;">This browser</span><?php endif; ?>
            <span>Last used <?= h(date('M j, Y g:i A', strtotime((string) ($b['last_used_at'] ?? $b['created_at'])))) ?> &middot; <?= h($b['ip_address']) ?> &middot; until <?= h(date('M j, Y', strtotime((string) $b['expires_at']))) ?></span>
          </div>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="forget_browser">
            <input type="hidden" name="browser_id" value="<?= (int) $b['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">Forget</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" style="margin-top:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="forget_other_browsers">
      <button type="submit" class="btn btn-outline btn-sm">Forget all except this browser</button>
    </form>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
