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

    if ($action === 'tracking_format') {
        $prefix = strtoupper(trim($_POST['tracking_number_prefix'] ?? ''));
        $suffix = strtoupper(trim($_POST['tracking_number_suffix'] ?? ''));
        if (!preg_match('/^[A-Z0-9]{0,8}$/', $prefix)) {
            flash_set('error', 'Prefix can only contain letters and numbers, up to 8 characters.');
            redirect('/admin/branding.php');
        }
        if (!preg_match('/^[A-Z0-9]{0,8}$/', $suffix)) {
            flash_set('error', 'Suffix can only contain letters and numbers, up to 8 characters.');
            redirect('/admin/branding.php');
        }
        set_setting('tracking_number_prefix', $prefix);
        set_setting('tracking_number_suffix', $suffix);
        flash_set('success', 'Tracking number format updated. This only affects shipments created from now on — existing tracking numbers don\'t change.');
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
  <h3 style="margin-top:0;">Tracking Number Format</h3>
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    New shipments get a tracking number built as
    <strong>prefix + 7 digits + 2 letters + suffix</strong> (e.g. with prefix
    "SC" that's <code>SC7482913KE</code>). Change either side here — it only
    applies going forward, existing tracking numbers are untouched. Leave a
    field blank to drop that part entirely.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="tracking_format">
    <div class="form-row">
      <div class="form-group">
        <label>Prefix</label>
        <input type="text" name="tracking_number_prefix" value="<?= h(get_setting('tracking_number_prefix', 'SC')) ?>" maxlength="8" placeholder="e.g. SC">
      </div>
      <div class="form-group">
        <label>Suffix</label>
        <input type="text" name="tracking_number_suffix" value="<?= h(get_setting('tracking_number_suffix', '')) ?>" maxlength="8" placeholder="e.g. US">
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Format</button>
  </form>
</div>

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
