<?php
/**
 * Shared admin layout header. Caller must have already required config/db.php,
 * includes/functions.php and includes/auth.php, and called require_admin().
 */
require_once __DIR__ . '/../../includes/settings.php';
$activeAdminNav = $activeAdminNav ?? '';
$__navAdmin = current_admin();
?>
<!DOCTYPE html>
<html lang="en" data-template="<?= h(active_template_layout_key()) ?>" data-animation="<?= h(active_template_animation_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' | Admin' : 'Admin' ?> | <?= h(get_site_name()) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
<?= palette_style_tag() ?>
</head>
<body>

<?php
  // Same rule as the public site: a logo that already carries the company
  // name is shown on its own, and gets the room the name would have taken.
  // Without this the name runs off the edge of the narrow sidebar.
  $adminLockup = logo_includes_name();
  $adminPlate  = logo_is_custom() ? ' logo-plate' : '';
?>
<div class="admin-mobile-bar">
  <a href="/admin/dashboard.php" class="logo">
    <?= logo_img_tag($adminLockup ? 40 : 34, $adminLockup ? 180 : 60, 'mark-img' . $adminPlate, $adminLockup ? get_site_name() : '') ?>
    <?php if (!$adminLockup): ?>
      <span class="word-cargo"><?= h(get_site_name()) ?></span>
    <?php endif; ?>
  </a>
  <button type="button" class="admin-menu-btn" id="admin-menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="admin-sidebar">&#9776;</button>
</div>
<div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop"></div>

<div class="admin-wrap">
  <aside class="admin-sidebar" id="admin-sidebar">
    <a href="/admin/dashboard.php" class="logo">
      <?= logo_img_tag($adminLockup ? 46 : 38, $adminLockup ? 172 : 64, 'mark-img' . $adminPlate, $adminLockup ? get_site_name() : '') ?>
      <?php if (!$adminLockup): ?>
        <span class="word-cargo"><?= h(get_site_name()) ?></span>
      <?php endif; ?>
    </a>
    <nav>
      <a href="/admin/dashboard.php" class="<?= $activeAdminNav === 'dashboard' ? 'active' : '' ?>">Shipments</a>
      <a href="/admin/shipment_form.php" class="<?= $activeAdminNav === 'new' ? 'active' : '' ?>">New Shipment</a>
      <a href="/admin/requests.php" class="<?= $activeAdminNav === 'requests' ? 'active' : '' ?>">Shipment Requests</a>
      <a href="/admin/couriers.php" class="<?= $activeAdminNav === 'couriers' ? 'active' : '' ?>">Couriers &amp; Carriers</a>
      <a href="/admin/statuses.php" class="<?= $activeAdminNav === 'statuses' ? 'active' : '' ?>">Shipment Statuses</a>
      <a href="/admin/status_messages.php" class="<?= $activeAdminNav === 'status_messages' ? 'active' : '' ?>">Status Messages</a>
      <a href="/admin/content.php" class="<?= $activeAdminNav === 'content' ? 'active' : '' ?>">Site Content</a>
      <a href="/admin/images.php" class="<?= $activeAdminNav === 'images' ? 'active' : '' ?>">Site Images</a>
      <a href="/admin/seo.php" class="<?= $activeAdminNav === 'seo' ? 'active' : '' ?>">Search Engines</a>
      <a href="/admin/rates.php" class="<?= $activeAdminNav === 'rates' ? 'active' : '' ?>">Calculator Rates</a>
      <a href="/admin/branding.php" class="<?= $activeAdminNav === 'branding' ? 'active' : '' ?>">Branding</a>
      <a href="/admin/smtp_settings.php" class="<?= $activeAdminNav === 'smtp' ? 'active' : '' ?>">Email (SMTP)</a>
      <?php if ($__navAdmin && $__navAdmin['is_super_admin']): ?>
        <a href="/admin/live_chat.php" class="<?= $activeAdminNav === 'live_chat' ? 'active' : '' ?>">Live Chat</a>
        <a href="/admin/tracking_display.php" class="<?= $activeAdminNav === 'tracking_display' ? 'active' : '' ?>">Tracking Page</a>
      <?php endif; ?>
      <a href="/admin/profile.php" class="<?= $activeAdminNav === 'profile' ? 'active' : '' ?>">My Profile</a>
      <?php if ($__navAdmin && !$__navAdmin['is_super_admin']): ?>
        <a href="/admin/my_theme.php" class="<?= $activeAdminNav === 'my_theme' ? 'active' : '' ?>">Site Color</a>
      <?php endif; ?>
      <?php if ($__navAdmin && $__navAdmin['is_super_admin']): ?>
        <a href="/admin/themes.php" class="<?= $activeAdminNav === 'themes' ? 'active' : '' ?>">Colors</a>
        <a href="/admin/templates.php" class="<?= $activeAdminNav === 'templates' ? 'active' : '' ?>">Templates</a>
        <a href="/admin/admins.php" class="<?= $activeAdminNav === 'admins' ? 'active' : '' ?>">Admin Accounts</a>
        <a href="/admin/activity_log.php" class="<?= $activeAdminNav === 'activity_log' ? 'active' : '' ?>">Activity Log</a>
        <a href="/admin/health.php" class="<?= $activeAdminNav === 'health' ? 'active' : '' ?>">System Health</a>
      <?php endif; ?>
      <a href="/track.php" target="_blank">View Public Site &#8599;</a>
      <a href="/admin/logout.php">Logout</a>
    </nav>
  </aside>
  <main class="admin-main">
<script>
  (function () {
    var menuBtn = document.getElementById('admin-menu-btn');
    var sidebar = document.getElementById('admin-sidebar');
    var backdrop = document.getElementById('admin-sidebar-backdrop');
    if (!menuBtn || !sidebar || !backdrop) return;

    function closeMenu() {
      sidebar.classList.remove('is-open');
      backdrop.classList.remove('is-open');
      menuBtn.setAttribute('aria-expanded', 'false');
    }
    function openMenu() {
      sidebar.classList.add('is-open');
      backdrop.classList.add('is-open');
      menuBtn.setAttribute('aria-expanded', 'true');
    }

    menuBtn.addEventListener('click', function () {
      sidebar.classList.contains('is-open') ? closeMenu() : openMenu();
    });
    backdrop.addEventListener('click', closeMenu);
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
  })();
</script>
