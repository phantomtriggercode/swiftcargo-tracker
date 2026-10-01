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
<html lang="en" data-area="admin">
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
  $adminLockup = logo_stands_alone();
  $adminPlate  = logo_is_custom() ? ' logo-plate' : '';
?>
<div class="admin-mobile-bar">
  <a href="/admin/dashboard.php" class="logo">
    <?= logo_img_tag($adminLockup ? 40 : 34, $adminLockup ? 180 : 60, 'mark-img' . $adminPlate, $adminLockup ? get_site_name() : '') ?>
    <?php if (!$adminLockup && header_shows_title()): ?>
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
      <?php if (!$adminLockup && header_shows_title()): ?>
        <span class="word-cargo"><?= h(get_site_name()) ?></span>
      <?php endif; ?>
    </a>
    <?php
      $__isSuper = $__navAdmin && $__navAdmin['is_super_admin'];

      // The menu as data: sections, each with a heading and its links.
      // 'super' marks a link only a super admin sees, which is also drawn
      // in its own colour with a small badge, so a super admin can tell at
      // a glance which controls are theirs alone. 'reg' marks a link only a
      // regular admin sees. Everything else is shown to every admin.
      $__nav = [
        'Shipments' => [
          ['dashboard', '/admin/dashboard.php', 'All Shipments'],
          ['new', '/admin/shipment_form.php', 'New Shipment'],
          ['requests', '/admin/requests.php', 'Shipment Requests'],
        ],
        'Shipment setup' => [
          ['couriers', '/admin/couriers.php', 'Couriers & Carriers'],
          ['statuses', '/admin/statuses.php', 'Shipment Statuses'],
          ['status_messages', '/admin/status_messages.php', 'Status Messages'],
        ],
        'Site content' => [
          ['content', '/admin/content.php', 'Site Content'],
          ['images', '/admin/images.php', 'Site Images'],
          ['reviews', '/admin/reviews.php', 'Reviews'],
          ['partners', '/admin/partners.php', 'Partners'],
          ['seo', '/admin/seo.php', 'Search Engines'],
          ['rates', '/admin/rates.php', 'Calculator Rates'],
          ['branding', '/admin/branding.php', 'Branding'],
        ],
        'Site controls' => [
          ['smtp', '/admin/smtp_settings.php', 'Email (SMTP)'],
          ['live_chat', '/admin/live_chat.php', 'Live Chat', 'super'],
          ['tracking_display', '/admin/tracking_display.php', 'Tracking Page &amp; Switches', 'super'],
          ['security', '/admin/security.php', 'Sign-in Security', 'super'],
          ['my_theme', '/admin/my_theme.php', 'Site Color', 'reg'],
          ['themes', '/admin/themes.php', 'Colors', 'super'],
          ['templates', '/admin/templates.php', 'Templates', 'super'],
        ],
        'Administration' => [
          ['admins', '/admin/admins.php', 'Admin Accounts', 'super'],
          ['activity_log', '/admin/activity_log.php', 'Activity Log', 'super'],
          ['health', '/admin/health.php', 'System Health', 'super'],
        ],
        'Your account' => [
          ['profile', '/admin/profile.php', 'My Profile'],
        ],
      ];
    ?>
    <nav>
      <?php foreach ($__nav as $__section => $__links): ?>
        <?php
          // Skip a whole section if nothing in it is visible to this admin.
          $__visible = array_filter($__links, function ($l) use ($__isSuper) {
            $scope = $l[3] ?? '';
            if ($scope === 'super') return $__isSuper;
            if ($scope === 'reg') return !$__isSuper;
            return true;
          });
        ?>
        <?php if ($__visible): ?>
          <div class="nav-section-title"><?= h($__section) ?></div>
          <?php foreach ($__visible as $__l): ?>
            <?php $__isSuperLink = ($__l[3] ?? '') === 'super'; ?>
            <a href="<?= h($__l[1]) ?>"
               class="<?= $activeAdminNav === $__l[0] ? 'active' : '' ?><?= $__isSuperLink ? ' nav-super' : '' ?>">
              <span><?= $__l[2] ?></span>
              <?php if ($__isSuperLink): ?><span class="nav-super-badge" title="Only super admins can see this">SUPER</span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endforeach; ?>

      <div class="nav-section-divider"></div>
      <a href="/track.php" target="_blank" rel="noopener">View Public Site &#8599;</a>
      <a href="/admin/logout.php" class="nav-logout">Logout</a>
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
