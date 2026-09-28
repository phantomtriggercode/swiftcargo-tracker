<?php
/**
 * robots.txt, served by PHP.
 *
 * A static robots.txt cannot name the site's own domain, and the
 * Sitemap: line has to be an absolute URL for search engines to follow
 * it. Serving it from here means it is correct on whatever domain this
 * codebase is deployed to, with nothing to edit by hand.
 *
 * The web server rewrites /robots.txt to this file (see .htaccess), so
 * crawlers still request the ordinary address.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$base = get_site_url();

if (seo_site_hidden()) {
    // The whole site is being kept out of search results on purpose (the
    // switch on the SEO screen). Say so plainly here as well as in the
    // page tags, because a crawler that respects one usually respects
    // both, and between them nothing gets indexed by accident.
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

echo "User-agent: *\n";

// Nothing behind these is public, and nothing behind them should ever
// appear in a search result. The server refuses most of them outright;
// this is what stops a crawler wasting its time asking.
$blocked = [
    '/admin/',
    '/api/',
    '/documents/',
    '/config/',
    '/includes/',
    '/sql/',
    '/vendor/',
    '/verify.php',
];
foreach ($blocked as $path) {
    echo 'Disallow: ' . $path . "\n";
}

// A tracked shipment is one customer's private business. The page itself
// is public so the link in their email works, but a specific tracking
// number must never be indexed and shown to anyone else.
echo "Disallow: /track.php?tn=\n";
echo "Allow: /track.php\n";
echo "Allow: /\n";

echo "\n";
// Some crawlers exist only to copy a site wholesale. They are asked to
// leave; the rate limit in includes/security.php is what actually stops
// the ones that ignore this.
foreach (['AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot'] as $bot) {
    echo 'User-agent: ' . $bot . "\n";
    echo "Disallow: /\n\n";
}

echo 'Sitemap: ' . $base . "/sitemap.xml\n";
