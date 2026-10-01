<?php
/**
 * Classic header: a dark contact strip over a white bar with the logo on
 * the left, the menu in the middle and "Track Now" on the right.
 * Included by includes/header.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$__phone = trim(get_setting('contact_phone', ''));
$__email = trim(get_setting('contact_email', ''));
?>
<div class="topbar">
  <div class="container">
    <div class="topbar-contacts">
      <?php if ($__phone !== ''): ?><a href="<?= h(phone_href()) ?>"><?= ui_icon('phone', 15) ?> <?= h($__phone) ?></a><?php endif; ?>
      <?php if ($__email !== ''): ?><a href="mailto:<?= h($__email) ?>" class="topbar-hide-sm"><?= ui_icon('mail', 15) ?> <?= h($__email) ?></a><?php endif; ?>
    </div>
    <div class="topbar-links">
      <a href="/contact.php">Support</a>
      <?php if (header_shows_login()): ?><a href="<?= h(admin_login_url()) ?>">Staff Login</a><?php endif; ?>
    </div>
  </div>
</div>

<header class="site-header hdr-classic" id="site-header">
  <div class="container hdr-row">
    <?= render_brand(['mark_h' => 56, 'lockup_h' => 68]) ?>
    <nav class="main-nav" aria-label="Main navigation"><?= render_nav_links($activeNav) ?></nav>
    <div class="header-actions">
      <a href="/track.php" class="btn btn-primary header-track-btn"><?= h(header_track_label()) ?></a>
      <?= render_menu_button() ?>
    </div>
  </div>
  <?= render_mobile_nav($activeNav) ?>
</header>
