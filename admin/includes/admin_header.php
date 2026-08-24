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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — Admin' : 'Admin' ?> | <?= h(get_site_name()) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
</head>
<body>

<div class="admin-mobile-bar">
  <a href="/admin/dashboard.php" class="logo">
    <?php if ($logoUrl = get_logo_url()): ?>
      <img src="<?= h($logoUrl) ?>" alt="" width="28" height="28" class="mark-img">
    <?php else: ?>
      <img src="/assets/images/logo-mark.svg" alt="" width="28" height="28" class="mark-img">
    <?php endif; ?>
    <span class="word-cargo"><?= h(get_site_name()) ?></span>
  </a>
  <button type="button" class="admin-menu-btn" id="admin-menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="admin-sidebar">&#9776;</button>
</div>
<div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop"></div>

<div class="admin-wrap">
  <aside class="admin-sidebar" id="admin-sidebar">
    <a href="/admin/dashboard.php" class="logo">
      <?php if ($logoUrl = get_logo_url()): ?>
        <img src="<?= h($logoUrl) ?>" alt="" width="34" height="34" class="mark-img">
      <?php else: ?>
        <img src="/assets/images/logo-mark.svg" alt="" width="34" height="34" class="mark-img">
      <?php endif; ?>
      <span class="word-cargo"><?= h(get_site_name()) ?></span>
    </a>
    <nav>
      <a href="/admin/dashboard.php" class="<?= $activeAdminNav === 'dashboard' ? 'active' : '' ?>">Shipments</a>
      <a href="/admin/shipment_form.php" class="<?= $activeAdminNav === 'new' ? 'active' : '' ?>">New Shipment</a>
      <a href="/admin/requests.php" class="<?= $activeAdminNav === 'requests' ? 'active' : '' ?>">Shipment Requests</a>
      <a href="/admin/couriers.php" class="<?= $activeAdminNav === 'couriers' ? 'active' : '' ?>">Couriers &amp; Carriers</a>
      <a href="/admin/content.php" class="<?= $activeAdminNav === 'content' ? 'active' : '' ?>">Site Content</a>
      <a href="/admin/images.php" class="<?= $activeAdminNav === 'images' ? 'active' : '' ?>">Site Images</a>
      <a href="/admin/rates.php" class="<?= $activeAdminNav === 'rates' ? 'active' : '' ?>">Calculator Rates</a>
      <a href="/admin/branding.php" class="<?= $activeAdminNav === 'branding' ? 'active' : '' ?>">Branding</a>
      <a href="/admin/smtp_settings.php" class="<?= $activeAdminNav === 'smtp' ? 'active' : '' ?>">Email (SMTP)</a>
      <a href="/admin/profile.php" class="<?= $activeAdminNav === 'profile' ? 'active' : '' ?>">My Profile</a>
      <?php if ($__navAdmin && $__navAdmin['is_super_admin']): ?>
        <a href="/admin/admins.php" class="<?= $activeAdminNav === 'admins' ? 'active' : '' ?>">Admin Accounts</a>
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
