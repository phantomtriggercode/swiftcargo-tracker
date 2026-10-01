<?php
/**
 * Shared public site header. Expects $activeNav to be set (optional) by the caller.
 */
$activeNav = $activeNav ?? '';
require_once __DIR__ . '/site.php';
maybe_send_go_live_alert();

// Which page this is, for the SEO record staff wrote in the admin panel.
// Pages set $seoPage themselves; $activeNav is the fallback because the
// two use the same names for the same pages.
$seo = seo_meta_for($seoPage ?? $activeNav ?? '', $pageTitle ?? '');
$googleVerification = seo_verification_token('seo_google_verification');
$bingVerification = seo_verification_token('seo_bing_verification');
?>
<!DOCTYPE html>
<html lang="en" data-template="<?= h(site_template()) ?>" data-animation="<?= h(active_template_animation_key()) ?>">
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
<?php
// Each template has its own typeface. The files live on this site, so no
// visitor's address is handed to a font service, and a blocked or slow
// third party can never hold the page up. Classic uses the system's own.
$__tpl = site_template();
if (is_file(__DIR__ . '/../assets/fonts/fonts-' . $__tpl . '.css')): ?>
<link rel="stylesheet" href="<?= h(asset_url('/assets/fonts/fonts-' . $__tpl . '.css')) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= h(asset_url('/assets/css/templates/' . $__tpl . '.css')) ?>">
<?= palette_style_tag() ?>
<script>
  /* Opts this page in to the scroll-reveal animations by adding .js-anim,
     which is what lets style.css hide [data-reveal] sections until they
     scroll into view. Inline and in <head> on purpose: it has to run
     before first paint, or hidden sections would flash visible first.

     The timer is the safety net. If assets/js/reveal.js never gets to run
     (blocked by an extension, 404 after a bad upload, a future CSP change),
     nothing would ever add .is-visible and those sections would stay
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
<body class="tpl-<?= h($__tpl) ?> page-<?= h($activeNav !== '' ? $activeNav : ($seoPage ?? 'page')) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<?php
// The header itself is the template's own: a two-row header, a floating
// pill, a centred editorial masthead, a loud slab with a ticker, a
// three-tier corporate header, or a dark glass bar.
include __DIR__ . '/headers/' . $__tpl . '.php';
?>

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
      document.documentElement.classList.remove('nav-open');
    }
    function openMenu() {
      dropdown.classList.add('is-open');
      backdrop.classList.add('is-open');
      menuBtn.setAttribute('aria-expanded', 'true');
      document.documentElement.classList.add('nav-open');
    }

    menuBtn.addEventListener('click', function () {
      dropdown.classList.contains('is-open') ? closeMenu() : openMenu();
    });
    backdrop.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && dropdown.classList.contains('is-open')) { closeMenu(); menuBtn.focus(); }
    });
    dropdown.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
  })();
</script>
<main id="main">
