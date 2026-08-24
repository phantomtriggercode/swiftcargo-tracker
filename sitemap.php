<?php
/**
 * A minimal sitemap covering the public, indexable pages. Served as XML
 * from a .php file (not a static sitemap.xml) so the URLs always match
 * whatever domain this codebase is actually deployed under.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$base = get_site_url();
$pages = ['/index.php', '/about.php', '/services.php', '/countries.php', '/track.php', '/request-shipment.php', '/contact.php', '/privacy.php', '/terms.php'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $page) {
    echo '  <url><loc>' . htmlspecialchars($base . $page, ENT_XML1) . '</loc></url>' . "\n";
}
echo '</urlset>' . "\n";
