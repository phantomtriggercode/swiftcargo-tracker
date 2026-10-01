<?php
/**
 * Bold header: a running ticker of services across the very top, then a
 * slab in the brand colour with the logo on a slanted white block, a loud
 * capitalised menu and a "Track it" button with a hard shadow.
 * Included by includes/header.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$__ticker = ['Air freight', 'Ocean freight', 'Road freight', 'Customs clearance', 'Warehousing', 'Door-to-door delivery', 'Tracking around the clock'];
$__tickerHtml = '';
foreach ($__ticker as $__word) {
    $__tickerHtml .= '<span>' . h($__word) . '</span><span class="tick-star" aria-hidden="true">&#10022;</span>';
}
?>
<div class="ticker" aria-hidden="true">
  <div class="marquee" data-marquee>
    <div class="marquee-track">
      <div class="marquee-group"><?= $__tickerHtml . $__tickerHtml ?></div>
      <div class="marquee-group"><?= $__tickerHtml . $__tickerHtml ?></div>
    </div>
  </div>
</div>
<header class="site-header hdr-bold" id="site-header">
  <div class="hdr-row">
    <div class="hdr-logo-slab">
      <?= render_brand(['mark_h' => 52, 'lockup_h' => 62]) ?>
    </div>
    <nav class="main-nav" aria-label="Main navigation"><?= render_nav_links($activeNav) ?></nav>
    <div class="header-actions">
      <?php if (header_shows_login()): ?><a href="<?= h(admin_login_url()) ?>" class="hdr-login">Staff</a><?php endif; ?>
      <a href="/track.php" class="btn btn-accent header-track-btn"><?= h(header_track_label()) ?></a>
      <?= render_menu_button() ?>
    </div>
  </div>
  <?= render_mobile_nav($activeNav) ?>
</header>
