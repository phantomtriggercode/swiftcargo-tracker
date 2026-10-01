<?php
/**
 * Dark header: a dark frosted bar with a glowing line under it, the menu
 * with a light-up underline, and a tracking box built into the header
 * itself on wide screens, so a number can be looked up from any page.
 * Included by includes/header.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
?>
<header class="site-header hdr-dark" id="site-header">
  <div class="container hdr-row">
    <?= render_brand(['mark_h' => 50, 'lockup_h' => 60, 'on_dark' => true]) ?>
    <nav class="main-nav" aria-label="Main navigation"><?= render_nav_links($activeNav) ?></nav>
    <div class="header-actions">
      <form class="hdr-track" action="/track.php" method="get" role="search">
        <label class="sr-only" for="hdr-tn">Tracking number</label>
        <input type="text" id="hdr-tn" name="tn" placeholder="Tracking ID" required autocomplete="off" spellcheck="false">
        <button type="submit" aria-label="Track"><?= ui_icon('search', 16) ?></button>
      </form>
      <a href="/track.php" class="btn btn-primary header-track-btn"><?= h(header_track_label()) ?></a>
      <?= render_menu_button() ?>
    </div>
  </div>
  <span class="hdr-glow-line" aria-hidden="true"></span>
  <?= render_mobile_nav($activeNav) ?>
</header>
