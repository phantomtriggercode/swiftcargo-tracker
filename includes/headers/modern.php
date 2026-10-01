<?php
/**
 * Modern header: a floating rounded "pill" bar with a frosted-glass
 * background, the menu as soft pill links, a round search button that
 * jumps to tracking, and a rounded call to action. No contact strip.
 * Included by includes/header.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
?>
<header class="site-header hdr-modern" id="site-header">
  <div class="container">
    <div class="hdr-pill">
      <?= render_brand(['mark_h' => 46, 'lockup_h' => 54, 'mark_max_w' => 86, 'wide_max_w' => 150, 'lockup_max_w' => 240]) ?>
      <nav class="main-nav" aria-label="Main navigation"><?= render_nav_links($activeNav) ?></nav>
      <div class="header-actions">
        <a href="/track.php" class="hdr-icon-btn" aria-label="Track a shipment" title="Track a shipment"><?= ui_icon('search', 18) ?></a>
        <a href="/track.php" class="btn btn-primary header-track-btn"><?= h(header_track_label()) ?></a>
        <?= render_menu_button() ?>
      </div>
    </div>
  </div>
  <?= render_mobile_nav($activeNav) ?>
</header>
