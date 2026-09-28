<?php
/**
 * The sitemap, listing every public page for search engines.
 *
 * Served from PHP rather than a static sitemap.xml so the URLs always
 * match whatever domain this codebase is actually deployed to, and so a
 * page taken out of search results on the SEO screen disappears from here
 * at the same time rather than being submitted and then refused.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$base = get_site_url();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

if (!seo_site_hidden()) {
    foreach (seo_pages() as $key => $page) {
        // A page marked "keep out of search results" is left out entirely.
        // Listing it here and then telling the crawler not to index it
        // wastes its visit and is reported back as an error.
        // The Ship Now page is dropped from the sitemap while the request
        // form is switched off, since the page is unavailable then.
        if ($key === 'request' && !request_shipment_enabled()) {
            continue;
        }
        $record = get_seo_page($key);
        if ((int) $record['noindex'] === 1) {
            continue;
        }

        $path = $record['canonical_path'] !== '' ? $record['canonical_path'] : $page['path'];
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // lastmod is when the page's own text was last edited, which is
        // what a crawler uses to decide whether to bother re-reading it.
        $lastmod = $record['updated_at'] ?? '';
        $lastmod = $lastmod !== '' ? date('Y-m-d', strtotime((string) $lastmod)) : date('Y-m-d');

        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($base . $path, ENT_XML1) . '</loc>' . "\n";
        echo '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1) . '</lastmod>' . "\n";
        echo '    <changefreq>' . htmlspecialchars($page['changefreq'], ENT_XML1) . '</changefreq>' . "\n";
        echo '    <priority>' . htmlspecialchars($page['priority'], ENT_XML1) . '</priority>' . "\n";
        echo '  </url>' . "\n";
    }
}

echo '</urlset>' . "\n";
