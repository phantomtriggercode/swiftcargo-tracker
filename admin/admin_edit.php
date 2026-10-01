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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forget_browsers') {
    $count = forget_trusted_browsers($id);
    log_admin_activity('Forgot remembered browsers', $target['username'] . ': ' . $count . ' browser(s)');
    flash_set('success', 'Done. ' . $target['full_name'] . ' will be asked for an emailed code at their next sign-in on any browser.');
    redirect('/admin/admin_edit.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $forceChange = !empty($_POST['force_change']);

    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($username === '') $errors[] = 'Username is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email, or leave it blank.';
    if ($email === '' && login_codes_enabled()) $errors[] = 'An email address is required while sign-in codes are on, or this admin could not sign in from a new browser.';
    if ($newPassword !== '' && ($pwError = password_policy_error($newPassword)) !== null) $errors[] = $pwError;

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
            // Clears any existing must_change_password flag as a side effect, // re-set it below if this new password should itself be temporary.
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
      <input type="password" name="new_password" minlength="12" placeholder="Leave blank to keep their current password" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
        <input type="checkbox" name="force_change" value="1" style="width:auto;" <?= !empty($target['must_change_password']) ? 'checked' : '' ?>>
        Require a password change the next time they log in
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        They'll just see a plain "set a new password to continue" prompt, nothing
        singles out who required it. Pairs well with setting a temporary password above.
      </span>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
  </form>
</div>

<div class="form-card" style="max-width:520px;margin-top:16px;">
  <h3 style="margin-top:0;">Remembered browsers</h3>
  <?php $theirBrowsers = list_trusted_browsers($id); ?>
  <p style="font-size:13.5px;color:var(--muted);margin-top:0;">
    <?= count($theirBrowsers) === 1 ? '1 browser is' : count($theirBrowsers) . ' browsers are' ?> remembered for this account.
    Forgetting them means the next sign-in on any browser needs an emailed code
    (when sign-in codes are on). Setting a new password above does this too.
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="forget_browsers">
    <button type="submit" class="btn btn-outline btn-sm" <?= $theirBrowsers ? '' : 'disabled' ?>>Forget their remembered browsers</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
