<?php
/**
 * Minimal header: an editorial masthead. The logo and name sit centred on
 * their own row, with a quiet text link to tracking; the menu runs
 * underneath in small spaced capitals between two hairlines.
 * Included by includes/header.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$__phone = trim(get_setting('contact_phone', ''));
?>
<header class="site-header hdr-minimal" id="site-header">
  <div class="container hdr-masthead">
    <div class="hdr-side hdr-side--left">
      <?php if ($__phone !== ''): ?><a href="<?= h(phone_href()) ?>" class="hdr-quiet-link"><?= h($__phone) ?></a><?php endif; ?>
    </div>
    <?= render_brand(['mark_h' => 52, 'lockup_h' => 64]) ?>
    <div class="hdr-side hdr-side--right header-actions">
      <a href="/track.php" class="hdr-quiet-link header-track-btn"><?= h(header_track_label()) ?> <span aria-hidden="true">&rarr;</span></a>
      <?= render_menu_button() ?>
    </div>
  </div>
  <div class="hdr-navrow">
    <div class="container">
      <nav class="main-nav" aria-label="Main navigation"><?= render_nav_links($activeNav) ?></nav>
    </div>
  </div>
  <?= render_mobile_nav($activeNav) ?>
</header>
