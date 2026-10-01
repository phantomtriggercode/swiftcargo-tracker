<?php
/**
 * Corporate header, three tiers: a dark strip with email, phone and
 * opening hours; a white band with the logo and three contact blocks; and
 * a full-width menu bar in the deep brand colour with "Request a Quote".
 * The top two scroll away and the menu bar stays pinned, gaining a small
 * logo once the big one has gone.
 * Included by includes/header.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$__phone = trim(get_setting('contact_phone', ''));
$__email = trim(get_setting('contact_email', ''));
$__address = trim(get_setting('contact_address', ''));
$__addressShort = $__address !== '' ? trim(explode(',', $__address)[0]) : '';
?>
<div class="topbar topbar--corporate">
  <div class="container">
    <div class="topbar-contacts">
      <?php if ($__email !== ''): ?><a href="mailto:<?= h($__email) ?>"><?= ui_icon('mail', 15) ?> <?= h($__email) ?></a><?php endif; ?>
      <span class="topbar-hide-sm"><?= ui_icon('clock', 15) ?> Online tracking, any time</span>
    </div>
    <div class="topbar-links">
      <a href="/track.php">Track a shipment</a>
      <a href="/contact.php">Support</a>
      <?php if (header_shows_login()): ?><a href="<?= h(admin_login_url()) ?>">Staff Login</a><?php endif; ?>
    </div>
  </div>
</div>
<header class="site-header hdr-corporate" id="site-header">
  <div class="hdr-band">
    <div class="container hdr-row">
      <?= render_brand(['mark_h' => 58, 'lockup_h' => 70]) ?>
      <div class="hdr-info">
        <?php if ($__phone !== ''): ?>
          <a class="hdr-info-item" href="<?= h(phone_href()) ?>"><span class="hdr-info-icon"><?= ui_icon('phone', 20) ?></span><span><small>Call us</small><strong><?= h($__phone) ?></strong></span></a>
        <?php endif; ?>
        <?php if ($__email !== ''): ?>
          <a class="hdr-info-item" href="mailto:<?= h($__email) ?>"><span class="hdr-info-icon"><?= ui_icon('mail', 20) ?></span><span><small>Email us</small><strong><?= h($__email) ?></strong></span></a>
        <?php endif; ?>
        <?php if ($__addressShort !== ''): ?>
          <span class="hdr-info-item"><span class="hdr-info-icon"><?= ui_icon('pin', 20) ?></span><span><small>Head office</small><strong><?= h($__addressShort) ?></strong></span></span>
        <?php endif; ?>
      </div>
      <div class="header-actions hdr-mobile-actions">
        <a href="/track.php" class="btn btn-primary header-track-btn"><?= h(header_track_label()) ?></a>
        <?= render_menu_button() ?>
      </div>
    </div>
  </div>
  <div class="hdr-navbar">
    <div class="container hdr-navbar-row">
      <a href="/index.php" class="hdr-mini-brand" aria-hidden="true" tabindex="-1"><?= logo_img_tag(30, 110, 'mark-img') ?></a>
      <nav class="main-nav" aria-label="Main navigation"><?= render_nav_links($activeNav, '', ['request']) ?></nav>
      <a href="<?= h(quote_url()) ?>" class="btn btn-accent hdr-quote-btn"><?= request_shipment_enabled() ? 'Request a Quote' : 'Contact Us' ?></a>
    </div>
  </div>
  <?= render_mobile_nav($activeNav) ?>
</header>
