<?php
/**
 * Tracking page display settings.
 *
 * Controls what a customer sees after entering a tracking number: whether
 * the live map appears at all, and whether the company logo sits above the
 * result. Super admin only, since both change the public face of the site.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $map  = !empty($_POST['live_map_enabled']) ? '1' : '0';
    $logo = !empty($_POST['tracking_show_logo']) ? '1' : '0';

    set_setting('live_map_enabled', $map);
    set_setting('tracking_show_logo', $logo);

    log_admin_activity(
        'Changed tracking page display',
        'Live map ' . ($map === '1' ? 'on' : 'off') . ', logo ' . ($logo === '1' ? 'on' : 'off')
    );
    flash_set('success', 'Tracking page settings saved.');
    redirect('/admin/tracking_display.php');
}

$mapOn  = live_map_enabled();
$logoOn = tracking_shows_logo();

$activeAdminNav = 'tracking_display';
$pageTitle = 'Tracking Page';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Tracking Page</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:640px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    What a customer sees after they enter a tracking number. These only
    affect the public tracking page. Nothing here changes the admin panel,
    and no shipment data is deleted by either switch.
  </p>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
        <input type="checkbox" name="live_map_enabled" value="1" <?= $mapOn ? 'checked' : '' ?>>
        Show the live map
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        With this off, the tracking page shows no map, no map legend and
        <strong>no coordinates at all</strong>. Latitude and longitude are left
        out of the page and out of the tracking feed the page uses, so they
        cannot be read from the page source either. Customers still get the
        status, the full timeline with place names, and every shipment detail.
        Staff still record coordinates as normal when adding an update, so
        turning the map back on restores everything with nothing lost.
      </span>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
        <input type="checkbox" name="tracking_show_logo" value="1" <?= $logoOn ? 'checked' : '' ?>>
        Show the company logo above the tracked shipment
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Puts your logo and company name at the top of the result, so a printed
        or screenshotted tracking page is clearly branded. Change the logo
        itself under <a href="/admin/branding.php" style="color:var(--brand-red);">Branding</a>.
      </span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Settings</button>
  </form>
</div>

<div class="form-card" style="max-width:640px;margin-top:16px;">
  <h3 style="margin-top:0;">Right now</h3>
  <ul style="color:var(--muted);font-size:14px;line-height:1.9;padding-left:20px;margin-bottom:0;">
    <li>Live map is <strong><?= $mapOn ? 'ON' : 'OFF' ?></strong><?= $mapOn ? '' : ', and coordinates are hidden everywhere on the public side' ?>.</li>
    <li>Logo above tracking results is <strong><?= $logoOn ? 'ON' : 'OFF' ?></strong>.</li>
    <li>Waybill and label PDFs are printable from the admin panel only.</li>
  </ul>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
