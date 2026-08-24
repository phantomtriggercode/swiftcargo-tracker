<?php
/**
 * Shared public site header. Expects $activeNav to be set (optional) by the caller.
 */
$activeNav = $activeNav ?? '';
maybe_send_go_live_alert();
?>
<!DOCTYPE html>
<html lang="en" data-theme-style="<?= h(active_theme_style_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — ' . h(get_site_name()) : h(get_site_name()) . ' | Global Shipping & Tracking' ?></title>
<meta name="description" content="Track your shipment live on the map and get instant email alerts on every status update.">
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
<?= theme_style_tag() ?>
</head>
<body>

<div class="topbar">
  <div class="container">
    <div>Customer Service: <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', get_setting('contact_phone', '+18005550199'))) ?>"><?= h(get_setting('contact_phone', '+1 (800) 555-0199')) ?></a></div>
    <div class="topbar-links">
      <a href="/contact.php">Support</a>
      <a href="/admin/login.php">Staff Login</a>
    </div>
  </div>
</div>

<header class="site-header">
  <div class="container">
    <a href="/index.php" class="logo">
      <?php if ($logoUrl = get_logo_url()): ?>
        <img src="<?= h($logoUrl) ?>" alt="" width="34" height="34" class="mark-img">
      <?php else: ?>
        <img src="/assets/images/logo-mark.svg" alt="" width="34" height="34" class="mark-img">
      <?php endif; ?>
      <span class="word-brand"><?= h(get_site_name()) ?></span>
    </a>
    <nav class="main-nav">
      <a href="/index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
      <a href="/track.php" class="<?= $activeNav === 'track' ? 'active' : '' ?>">Track Shipment</a>
      <a href="/request-shipment.php" class="<?= $activeNav === 'request' ? 'active' : '' ?>">Ship Now</a>
      <a href="/services.php" class="<?= $activeNav === 'services' ? 'active' : '' ?>">Services</a>
      <a href="/countries.php" class="<?= $activeNav === 'countries' ? 'active' : '' ?>">Countries</a>
      <a href="/about.php" class="<?= $activeNav === 'about' ? 'active' : '' ?>">About</a>
      <a href="/contact.php" class="<?= $activeNav === 'contact' ? 'active' : '' ?>">Contact</a>
    </nav>
    <div class="header-actions">
      <a href="/track.php" class="btn btn-primary header-track-btn">Track Now</a>
      <button type="button" class="nav-menu-btn" id="nav-menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav-dropdown">&#9776;</button>
    </div>
  </div>

  <nav class="mobile-nav-dropdown" id="mobile-nav-dropdown" aria-label="Mobile navigation">
    <a href="/index.php" class="mobile-nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
    <a href="/track.php" class="mobile-nav-link <?= $activeNav === 'track' ? 'active' : '' ?>">Track Shipment</a>
    <a href="/request-shipment.php" class="mobile-nav-link <?= $activeNav === 'request' ? 'active' : '' ?>">Ship Now</a>
    <a href="/services.php" class="mobile-nav-link <?= $activeNav === 'services' ? 'active' : '' ?>">Services</a>
    <a href="/countries.php" class="mobile-nav-link <?= $activeNav === 'countries' ? 'active' : '' ?>">Countries</a>
    <a href="/about.php" class="mobile-nav-link <?= $activeNav === 'about' ? 'active' : '' ?>">About</a>
    <a href="/contact.php" class="mobile-nav-link <?= $activeNav === 'contact' ? 'active' : '' ?>">Contact</a>
  </nav>
</header>

<div class="mobile-nav-backdrop" id="mobile-nav-backdrop"></div>

<script>
  (function () {
    var menuBtn = document.getElementById('nav-menu-btn');
    var dropdown = document.getElementById('mobile-nav-dropdown');
    var backdrop = document.getElementById('mobile-nav-backdrop');
    if (!menuBtn || !dropdown || !backdrop) return;

    function closeMenu() {
      dropdown.classList.remove('is-open');
      backdrop.classList.remove('is-open');
      menuBtn.setAttribute('aria-expanded', 'false');
    }
    function openMenu() {
      dropdown.classList.add('is-open');
      backdrop.classList.add('is-open');
      menuBtn.setAttribute('aria-expanded', 'true');
    }

    menuBtn.addEventListener('click', function () {
      dropdown.classList.contains('is-open') ? closeMenu() : openMenu();
    });
    backdrop.addEventListener('click', closeMenu);
    dropdown.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
  })();
</script>
