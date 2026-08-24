<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/mailer.php';
require_super_admin();

$me = current_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $makeSuperAdmin = !empty($_POST['is_super_admin']);

        if ($fullName === '') $errors[] = 'Full name is required.';
        if ($username === '') $errors[] = 'Username is required.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email, or leave it blank.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        if (!$errors) {
            $dup = db()->prepare('SELECT id FROM admins WHERE username = ? OR (email = ? AND email IS NOT NULL AND email != "")');
            $dup->execute([$username, $email]);
            if ($dup->fetch()) {
                $errors[] = 'That username or email is already in use.';
            }
        }

        if (!$errors) {
            $stmt = db()->prepare('INSERT INTO admins (username, email, password_hash, full_name, is_super_admin, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([$username, $email !== '' ? $email : null, password_hash($password, PASSWORD_DEFAULT), $fullName, $makeSuperAdmin ? 1 : 0]);
            flash_set('success', 'Admin account created.');
            redirect('/admin/admins.php');
        }
    } elseif ($action === 'toggle_active') {
        $target = fetch_admin($targetId);
        if (!$target) {
            flash_set('error', 'Admin not found.');
        } elseif ($targetId === (int) $me['id']) {
            flash_set('error', "You can't suspend your own account.");
        } elseif ($target['is_active'] && $target['is_super_admin'] && count_active_super_admins() <= 1) {
            flash_set('error', "Can't suspend the last active super admin.");
        } else {
            $stmt = db()->prepare('UPDATE admins SET is_active = ? WHERE id = ?');
            $stmt->execute([$target['is_active'] ? 0 : 1, $targetId]);
            flash_set('success', $target['is_active'] ? 'Admin suspended.' : 'Admin reactivated.');
        }
        redirect('/admin/admins.php');
    } elseif ($action === 'toggle_super') {
        $target = fetch_admin($targetId);
        if (!$target) {
            flash_set('error', 'Admin not found.');
        } elseif ($target['is_super_admin'] && $target['is_active'] && count_active_super_admins() <= 1) {
            flash_set('error', "Can't remove super admin from the last active super admin.");
        } else {
            $stmt = db()->prepare('UPDATE admins SET is_super_admin = ? WHERE id = ?');
            $stmt->execute([$target['is_super_admin'] ? 0 : 1, $targetId]);
            flash_set('success', $target['is_super_admin'] ? 'Super admin access removed.' : 'Admin promoted to super admin.');
        }
        redirect('/admin/admins.php');
    } elseif ($action === 'delete') {
        $target = fetch_admin($targetId);
        if (!$target) {
            flash_set('error', 'Admin not found.');
        } elseif ($targetId === (int) $me['id']) {
            flash_set('error', "You can't delete your own account.");
        } elseif ($target['is_super_admin'] && $target['is_active'] && count_active_super_admins() <= 1) {
            flash_set('error', "Can't delete the last active super admin.");
        } else {
            $stmt = db()->prepare('DELETE FROM admins WHERE id = ?');
            $stmt->execute([$targetId]);
            flash_set('success', 'Admin account deleted.');
        }
        redirect('/admin/admins.php');
    } elseif ($action === 'send_reset') {
        $target = fetch_admin($targetId);
        if (!$target || empty($target['email'])) {
            flash_set('error', "This admin doesn't have an email address set, so a reset link can't be sent.");
        } else {
            $token = create_password_reset($target['email']);
            $resetUrl = get_site_url() . '/admin/reset_password.php?token=' . $token;
            $siteName = get_site_name();
            $htmlBody = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#111827;">'
                . '<p>An admin reset your ' . h($siteName) . ' password. Use the link below to set a new one.</p>'
                . '<p><a href="' . h($resetUrl) . '" style="display:inline-block;background:#d40511;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Reset Password</a></p>'
                . '<p style="color:#6b7280;font-size:12.5px;">This link expires in 1 hour.</p>'
                . '</div>';
            $altBody = "Reset your {$siteName} admin password: {$resetUrl}\n\nThis link expires in 1 hour.";
            $result = send_smtp_mail($target['email'], $target['full_name'], 'Reset your ' . $siteName . ' admin password', $htmlBody, $altBody);
            flash_set($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Reset link sent to ' . $target['email'] . '.' : 'Could not send email: ' . $result['error']);
        }
        redirect('/admin/admins.php');
    }
}

function fetch_admin(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$admins = db()->query('SELECT * FROM admins ORDER BY is_super_admin DESC, created_at ASC')->fetchAll();

$activeAdminNav = 'admins';
$pageTitle = 'Admin Accounts';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Admin Accounts</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="table-responsive">
<table class="data-table">
  <thead>
    <tr>
      <th>Name</th>
      <th>Username</th>
      <th>Email</th>
      <th>Role</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($admins as $a): ?>
      <tr>
        <td data-label="Name"><?= h($a['full_name']) ?><?= (int) $a['id'] === (int) $me['id'] ? ' <span style="color:var(--muted);font-size:12px;">(you)</span>' : '' ?></td>
        <td data-label="Username"><?= h($a['username']) ?></td>
        <td data-label="Email"><?= h($a['email'] ?: '—') ?></td>
        <td data-label="Role"><?= $a['is_super_admin'] ? '<span class="badge badge-transit">Super Admin</span>' : '<span class="badge badge-pending">Admin</span>' ?></td>
        <td data-label="Status"><?= $a['is_active'] ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge badge-alert">Suspended</span>' ?></td>
        <td class="actions" data-label="Actions">
          <div class="row-actions">
            <button type="button" class="row-actions-btn" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= h($a['username']) ?>">&#8942;</button>
            <template class="row-actions-source">
              <form method="post">
                <input type="hidden" name="action" value="toggle_super">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button type="submit"><?= $a['is_super_admin'] ? 'Remove Super Admin' : 'Make Super Admin' ?></button>
              </form>
              <form method="post">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button type="submit"><?= $a['is_active'] ? 'Suspend' : 'Reactivate' ?></button>
              </form>
              <form method="post">
                <input type="hidden" name="action" value="send_reset">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button type="submit">Send Password Reset</button>
              </form>
              <form method="post" onsubmit="return confirm('Delete the admin account &quot;<?= h(addslashes($a['username'])) ?>&quot;? This cannot be undone.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button type="submit" class="danger">Delete</button>
              </form>
            </template>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="form-card" style="max-width:520px;margin-top:20px;">
  <h3 style="margin-top:0;">Add an Admin</h3>
  <form method="post">
    <input type="hidden" name="action" value="create">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required>
      </div>
      <div class="form-group">
        <label>Email (optional)</label>
        <input type="email" name="email">
      </div>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" minlength="8" required>
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
        <input type="checkbox" name="is_super_admin" value="1" style="width:auto;">
        Give this account super admin access
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Super admins can manage every other admin account, including this one. Regular admins can't see this page at all.
      </span>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Create Admin</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
