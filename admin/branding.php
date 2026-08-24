<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset_logo') {
        delete_uploaded_image(get_setting('logo_path', ''));
        set_setting('logo_path', '');
        flash_set('success', 'Logo reset to the default mark.');
        redirect('/admin/branding.php');
    }

    if ($action === 'go_live_alert') {
        $notifyEmail = trim($_POST['deploy_notify_email'] ?? '');
        if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Enter a valid email address, or leave it blank to turn this off.');
            redirect('/admin/branding.php');
        }
        set_setting('deploy_notify_email', $notifyEmail);
        flash_set('success', $notifyEmail !== '' ? 'Go-live alerts turned on.' : 'Go-live alerts turned off.');
        redirect('/admin/branding.php');
    }

    $siteName = trim($_POST['site_name'] ?? '');
    if ($siteName === '') {
        $errors[] = 'Site name cannot be empty.';
    }

    $upload = handle_image_upload('logo', 'logo', 2 * 1024 * 1024);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }

    if (!$errors) {
        set_setting('site_name', $siteName);
        if ($upload['path'] !== null) {
            $oldLogo = get_setting('logo_path', '');
            set_setting('logo_path', $upload['path']);
            delete_uploaded_image($oldLogo);
        }
        flash_set('success', 'Branding updated.');
        redirect('/admin/branding.php');
    }
}

$activeAdminNav = 'branding';
$pageTitle = 'Branding';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Branding</h1>
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

<div class="form-card" style="max-width:560px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    This name and logo appear everywhere across the site (header, footer, staff login,
    emails). Nothing here is tied to any domain — deploy this codebase under any
    domain name and set whatever brand you want.
  </p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <div class="form-group">
      <label>Site / Company Name</label>
      <input type="text" name="site_name" value="<?= h(get_site_name()) ?>" required>
    </div>

    <div class="form-group">
      <label>Logo</label>
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px;">
        <?php if ($logoUrl = get_logo_url()): ?>
          <img src="<?= h($logoUrl) ?>" alt="Current logo" style="height:44px;width:auto;border:1px solid var(--border);border-radius:8px;padding:4px;">
        <?php else: ?>
          <img src="/assets/images/logo-mark.svg" alt="Default logo mark" style="height:44px;width:44px;">
        <?php endif; ?>
        <span style="font-size:12.5px;color:var(--muted);">Current logo</span>
      </div>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">PNG, JPG, WEBP, GIF, or SVG. Max 2MB.</span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Branding</button>
  </form>
</div>

<?php if (get_logo_url()): ?>
  <form method="post" style="max-width:560px;margin-top:14px;">
    <input type="hidden" name="action" value="reset_logo">
    <button type="submit" class="btn btn-outline btn-sm">Reset to default logo mark</button>
  </form>
<?php endif; ?>

<div class="form-card" style="max-width:560px;margin-top:16px;">
  <h3 style="margin-top:0;">Go-Live Alert</h3>
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    Get an email the first time this site is visited on a new domain — useful if you
    deploy this codebase somewhere new and want to know the moment it's actually live.
    It fires once per domain (tracked in a setting, not hidden anywhere), then stays
    quiet until the domain changes again. Leave this blank to turn it off.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="go_live_alert">
    <div class="form-group">
      <label>Notify Email</label>
      <input type="email" name="deploy_notify_email" value="<?= h(get_setting('deploy_notify_email', '')) ?>" placeholder="you@yourdomain.com">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
