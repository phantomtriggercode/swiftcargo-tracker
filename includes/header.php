<?php
/**
 * Shared public site header. Expects $activeNav to be set (optional) by the caller.
 */
$activeNav = $activeNav ?? '';
maybe_send_go_live_alert();

// Which page this is, for the SEO record staff wrote in the admin panel.
// Pages set $seoPage themselves; $activeNav is the fallback because the
// two use the same names for the same pages.
$seo = seo_meta_for($seoPage ?? $activeNav ?? '', $pageTitle ?? '');
$googleVerification = seo_verification_token('seo_google_verification');
$bingVerification = seo_verification_token('seo_bing_verification');
?>
<!DOCTYPE html>
<html lang="en" data-template="<?= h(active_template_layout_key()) ?>" data-animation="<?= h(active_template_animation_key()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($seo['title']) ?></title>
<meta name="description" content="<?= h($seo['description']) ?>">
<?php if ($seo['keywords']): ?>
<meta name="keywords" content="<?= h(implode(', ', $seo['keywords'])) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= h($seo['canonical']) ?>">

<?php // A page that answered 404 must never be indexed, whatever its
      // record says, or a mistyped URL can end up in search results as a
      // real page.
      $noIndex = $seo['noindex'] || seo_site_hidden() || http_response_code() === 404; ?>
<?php if ($noIndex): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<?php endif; ?>

<?php if ($googleVerification !== ''): ?>
<meta name="google-site-verification" content="<?= h($googleVerification) ?>">
<?php endif; ?>
<?php if ($bingVerification !== ''): ?>
<meta name="msvalidate.01" content="<?= h($bingVerification) ?>">
<?php endif; ?>

<?php /* What a link to this page looks like when it is shared or pasted
         into a chat: a title, a sentence and a picture rather than a bare
         URL. Open Graph is read by most sites; Twitter/X reads its own. */ ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= h(get_site_name()) ?>">
<meta property="og:title" content="<?= h($seo['og_title']) ?>">
<meta property="og:description" content="<?= h($seo['og_description']) ?>">
<meta property="og:url" content="<?= h($seo['canonical']) ?>">
<meta property="og:image" content="<?= h($seo['image']) ?>">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($seo['og_title']) ?>">
<meta name="twitter:description" content="<?= h($seo['og_description']) ?>">
<meta name="twitter:image" content="<?= h($seo['image']) ?>">

<meta name="theme-color" content="<?= h(get_active_palette()['color_primary']) ?>">
<?= seo_structured_data() ?>

<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
<?= palette_style_tag() ?>
<script>
  /* Opts this page in to the scroll-reveal animations by adding .js-anim,
     which is what lets style.css hide [data-reveal] sections until they
     scroll into view. Inline and in <head> on purpose: it has to run
     before first paint, or hidden sections would flash visible first.

     The timer is the safety net. If assets/js/reveal.js never gets to run: blocked by an extension, 404 after a bad upload, a future CSP change, nothing would ever add .is-visible and those sections would stay
     invisible forever. reveal.js stamps data-reveal-ready on <html> as
     soon as it starts; if that hasn't happened shortly after load, the
     class comes back off and every section simply renders normally,
     animations skipped. Readable content always wins over the effect. */
  (function () {
    var d = document.documentElement;
    d.className += (d.className ? ' ' : '') + 'js-anim';
    window.setTimeout(function () {
      if (!d.hasAttribute('data-reveal-ready')) {
        d.className = d.className.replace(/(^|\s)js-anim(\s|$)/, '$1$2');
      }
    }, 2000);
  })();
</script>
</head>
<body>

<div class="topbar">
  <div class="container">
    <div>Customer Service: <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', get_setting('contact_phone', '+18005550199'))) ?>"><?= h(get_setting('contact_phone', '+1 (800) 555-0199')) ?></a></div>
    <div class="topbar-links">
      <a href="/contact.php">Support</a>
      <a href="/admin/login.php">Login</a>
    </div>
  </div>
</div>

<header class="site-header">
  <div class="container">
    <?php
      // A logo that already has the company name written into it is shown
      // on its own and given the whole brand area to be legible in.
      // Anything else is a badge: it sits beside the name as a mark, and
      // the name's own size comes from its length (see
      // brand_name_max_font_size), so a short name reads large and a
      // 24-character one still fits on one line beside the navigation.
      $logoIsLockup = logo_includes_name();
    ?>
    <a href="/index.php" class="logo <?= $logoIsLockup ? 'logo-lockup' : '' ?>">
      <?= logo_img_tag(
            $logoIsLockup ? 68 : 56,
            $logoIsLockup ? 300 : 100,
            'mark-img',
            // With no text beside it the picture is the only thing naming
            // the company, so it carries that name for anyone using a
            // screen reader. Beside the text it is decoration and is left
            // unlabelled, so the name is not announced twice.
            $logoIsLockup ? get_site_name() : ''
          ) ?>
      <?php if (!$logoIsLockup): ?>
        <span class="brand-text" style="--brand-min:<?= brand_name_min_font_size() ?>px;--brand-max:<?= brand_name_max_font_size() ?>px;">
          <span class="word-brand"><?= h(get_site_name()) ?></span>
          <?php if ($headerTagline = get_header_tagline()): ?>
            <span class="brand-tagline"><?= h($headerTagline) ?></span>
          <?php endif; ?>
        </span>
      <?php endif; ?>
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
