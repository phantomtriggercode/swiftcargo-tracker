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
        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
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
      </span>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Details</button>
  </form>
</div>

<div class="form-card" style="max-width:520px;margin-top:16px;">
  <h3 style="margin-top:0;">Change Password</h3>
  <form method="post">
    <input type="hidden" name="action" value="password">
    <div class="form-group">
      <label>Current Password</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input type="password" name="new_password" minlength="8" required>
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input type="password" name="new_password_confirm" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Change Password</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
