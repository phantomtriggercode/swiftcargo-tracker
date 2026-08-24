<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_super_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM admins WHERE id = ?');
$stmt->execute([$id]);
$target = $stmt->fetch();
if (!$target) {
    flash_set('error', 'Admin not found.');
    redirect('/admin/admins.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $forceChange = !empty($_POST['force_change']);

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($username === '') $errors[] = 'Username is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email, or leave it blank.';
    if ($newPassword !== '' && strlen($newPassword) < 8) $errors[] = 'New password must be at least 8 characters.';

    if (!$errors) {
        $dup = db()->prepare('SELECT id FROM admins WHERE (username = ? OR (email = ? AND email IS NOT NULL AND email != "")) AND id != ?');
        $dup->execute([$username, $email, $id]);
        if ($dup->fetch()) {
            $errors[] = 'That username or email is already used by another admin account.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare('UPDATE admins SET full_name = ?, username = ?, email = ? WHERE id = ?');
        $stmt->execute([$fullName, $username, $email !== '' ? $email : null, $id]);

        if ($newPassword !== '') {
            // Clears any existing must_change_password flag as a side effect —
            // re-set it below if this new password should itself be temporary.
            set_admin_password($id, $newPassword);
        }
        if ($forceChange) {
            set_must_change_password($id, true);
        } elseif ($newPassword === '') {
            // Only touch the flag on its own when no password was set above
            // (set_admin_password already resolved it in that branch).
            set_must_change_password($id, false);
        }

        log_admin_activity('Edited admin account', $username . ($newPassword !== '' ? ' (password changed)' : ''));
        flash_set('success', 'Admin account updated.');
        redirect('/admin/admin_edit.php?id=' . $id);
    }
    $target = array_merge($target, ['full_name' => $fullName, 'username' => $username, 'email' => $email]);
}

$activeAdminNav = 'admins';
$pageTitle = 'Edit Admin';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Edit Admin: <?= h($target['full_name']) ?></h1>
  <a href="/admin/admins.php" class="btn btn-outline btn-sm">&larr; All Admins</a>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:520px;">
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" value="<?= h($target['full_name']) ?>" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" value="<?= h($target['username']) ?>" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= h($target['email'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Set New Password</label>
      <input type="password" name="new_password" minlength="8" placeholder="Leave blank to keep their current password" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
        <input type="checkbox" name="force_change" value="1" style="width:auto;" <?= !empty($target['must_change_password']) ? 'checked' : '' ?>>
        Require a password change the next time they log in
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        They'll just see a plain "set a new password to continue" prompt — nothing
        singles out who required it. Pairs well with setting a temporary password above.
      </span>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
