<?php
/**
 * Serves the HTML verification file Google Search Console and Bing
 * Webmaster Tools ask you to upload.
 *
 * Both offer two ways to prove you own a site: a meta tag in the page
 * head, or a file at a specific address. The meta tag is set on the SEO
 * screen and needs nothing else. This covers the other method, without
 * anyone having to upload a file by hand and remember to put it back the
 * next time the site is redeployed: paste the file name into the same
 * screen and this answers for it.
 *
 * .htaccess rewrites /google<token>.html here. Nothing else reaches this
 * file, and it only ever answers for the exact name that was saved.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$requested = (string) ($_GET['file'] ?? '');
$expected = trim(get_setting('seo_google_verification_file', ''));

// Both sides are compared as plain file names. The saved value is
// checked against the same pattern it is saved under, so a stray paste
// can never turn into a path or into markup on the page.
$valid = $expected !== ''
    && preg_match('/^[A-Za-z0-9_\-]+\.html$/', $expected)
    && hash_equals($expected, $requested);

if (!$valid) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

// Google checks for exactly this one line, naming the file back to itself.
echo 'google-site-verification: ' . $expected;
