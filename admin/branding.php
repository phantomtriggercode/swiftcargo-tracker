<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

// The Go-Live Alert is a deployment tool, not day-to-day site branding, so
// only a super admin sees or manages it. Regular admins get this page with
// that section simply absent.
$__canManageDeployAlert = ($__a = current_admin()) && $__a['is_super_admin'];

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
        flash_set('success', 'Tracking number format updated. This only affects shipments created from now on, existing tracking numbers don\'t change.');
        redirect('/admin/branding.php');
    }

    if ($action === 'go_live_alert') {
        if (!$__canManageDeployAlert) {
            // Posted by someone who cannot see this section. Say nothing
            // about why, just send them back unchanged.
            redirect('/admin/branding.php');
        }
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

    $logoIncludesName = $_POST['logo_includes_name'] ?? 'auto';
    if (!in_array($logoIncludesName, ['auto', 'yes', 'no'], true)) {
        $logoIncludesName = 'auto';
    }

    $headerTagline = trim($_POST['header_tagline'] ?? '');
    if (mb_strlen($headerTagline) > 40) {
        $errors[] = 'The tagline has to be 40 characters or fewer, or it will not fit under the name.';
    }

    if (!$errors) {
        set_setting('site_name', $siteName);
        set_setting('header_tagline', $headerTagline);
        set_setting('logo_includes_name', $logoIncludesName);
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
    emails). Nothing here is tied to any domain, deploy this codebase under any
    domain name and set whatever brand you want.
  </p>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="form-group">
      <label>Site / Company Name</label>
      <input type="text" name="site_name" value="<?= h(get_site_name()) ?>" maxlength="60" required>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        The header is built to hold up to <strong>24 characters</strong> on one
        line, at any screen size, and a short name is shown larger to fill the
        same space. Your name is
        <strong><?= (int) mb_strlen(get_site_name()) ?> characters</strong>.
        Longer than 24 still shows in full, just at the smallest size.
      </span>
    </div>

    <div class="form-group">
      <label>Header Tagline</label>
      <input type="text" name="header_tagline" value="<?= h(get_header_tagline()) ?>"
             maxlength="40" placeholder="Fast, secure and reliable">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        The small line under the company name in the header. Shown in capitals
        whatever you type here, so write it normally. Leave it blank to show
        nothing at all rather than an empty line.
      </span>
    </div>

    <?php
      $logoDims = logo_file_dimensions(active_logo_url());
      $logoRatio = logo_aspect_ratio();
      $logoShape = $logoDims === null
        ? 'unknown'
        : ($logoRatio >= 1.6 ? 'wide' : ($logoRatio <= 0.7 ? 'tall' : 'square'));
    ?>
    <div class="form-group">
      <label>Logo</label>
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px;">
        <?php // Shown at its own proportions, so what you see here is the
              // shape the site is actually working with. ?>
        <span style="display:inline-flex;align-items:center;justify-content:center;height:64px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-soft);">
          <?= logo_img_tag(52, 240, '', 'Current logo') ?>
        </span>
        <span style="font-size:12.5px;color:var(--muted);line-height:1.6;">
          Current logo<br>
          <?php if ($logoDims !== null): ?>
            <strong><?= (int) $logoDims['width'] ?> &times; <?= (int) $logoDims['height'] ?></strong>
            <?php if ($logoShape === 'wide'): ?>
              &mdash; a wide name-plate
            <?php elseif ($logoShape === 'tall'): ?>
              &mdash; taller than it is wide
            <?php else: ?>
              &mdash; a square badge
            <?php endif; ?>
          <?php endif; ?>
        </span>
      </div>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        PNG, JPG, WEBP, GIF, or SVG. Max 2MB. Any shape works: the site
        measures the file and gives it the room it needs, so a wide logo is
        never squashed into a square and a square one is never stranded in a
        wide gap.
      </span>
    </div>

    <div class="form-group">
      <label>Does the logo already include the company name?</label>
      <select name="logo_includes_name">
        <?php $lin = get_setting('logo_includes_name', 'auto'); ?>
        <option value="auto" <?= $lin === 'auto' ? 'selected' : '' ?>>
          Work it out from the shape (currently: <?= logo_is_wide() ? 'yes' : 'no' ?>)
        </option>
        <option value="yes" <?= $lin === 'yes' ? 'selected' : '' ?>>Yes, the name is in the picture</option>
        <option value="no" <?= $lin === 'no' ? 'selected' : '' ?>>No, it is just a symbol</option>
      </select>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        When the name is already written into the logo, printing it again
        beside the picture says everything twice. Answer <strong>yes</strong>
        and the logo is shown on its own, larger, with the whole brand area
        to itself. Answer <strong>no</strong> and it sits as a small mark
        beside the name and tagline. Left on the first option, a logo wider
        than it is tall is assumed to carry the name, which is right almost
        always.
      </span>
    </div>

    <?php if ($logoShape === 'square'): ?>
      <div class="alert alert-error" style="font-size:13px;">
        <strong>About small print inside a square logo.</strong>
        This logo is roughly square, so in a page header it can only ever be
        about as tall as a line of text. Any wording drawn inside it, a
        tagline under the company initials for instance, will be a few
        pixels high there and will not be readable, at any size that still
        leaves room for the menu. That is a limit of the shape, not a
        setting. Two things that do work: the <strong>Header Tagline</strong>
        above says the same thing in real text right beside the logo, at a
        size that reads on a phone; and a <strong>wide</strong> version of
        the logo, with the name and tagline set alongside the symbol rather
        than under it, is shown far larger and stays legible. If you have
        both, upload the wide one here.
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary btn-block">Save Branding</button>
  </form>
</div>

<?php if (get_logo_url()): ?>
  <form method="post" style="max-width:560px;margin-top:14px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset_logo">
    <button type="submit" class="btn btn-outline btn-sm">Reset to default logo mark</button>
  </form>
<?php endif; ?>

<div class="form-card" style="max-width:560px;margin-top:16px;">
  <h3 style="margin-top:0;">Tracking Number Format</h3>
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    New shipments get a tracking number built as
    <strong>prefix + 7 digits + 2 letters + suffix</strong> (e.g. with prefix
    "SC" that's <code>SC7482913KE</code>). Change either side here. It only
    applies going forward, existing tracking numbers are untouched. Leave a
    field blank to drop that part entirely.
  </p>
  <form method="post">
    <?= csrf_field() ?>
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

<?php if ($__canManageDeployAlert): ?>
<div class="form-card" style="max-width:560px;margin-top:16px;">
  <h3 style="margin-top:0;">Go-Live Alert</h3>
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    Get an email the first time this site is visited on a new domain, useful if you
    deploy this codebase somewhere new and want to know the moment it's actually live.
    It fires once per domain (tracked in a setting, not hidden anywhere), then stays
    quiet until the domain changes again. Leave this blank to turn it off.
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="go_live_alert">
    <div class="form-group">
      <label>Notify Email</label>
      <input type="email" name="deploy_notify_email" value="<?= h(get_setting('deploy_notify_email', '')) ?>" placeholder="you@yourdomain.com">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save</button>
  </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
