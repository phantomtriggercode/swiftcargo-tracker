<?php
/**
 * Search engine optimisation: the per-page title, description and target
 * keywords staff write in the admin panel, and the tags that turn those
 * into something Google, Bing and social sites can actually read.
 *
 * Every public page names itself with $seoPage before it includes the
 * header (for example $seoPage = 'home'). The header then asks this file
 * for that page's record and prints the tags. A page with no record saved
 * falls back to sensible text built from the site name, so a site that has
 * never opened the SEO screen still has a complete, valid set of tags.
 *
 * Requires config/db.php and includes/settings.php to already be loaded.
 */

/**
 * Every public page that can be tuned, in the order they appear on the SEO
 * screen, with the URL each one lives at and a short explanation of who is
 * reading that page. The explanation is shown to whoever is writing the
 * keywords, because "who is this page for" is most of the work.
 */
function seo_pages(): array
{
    return [
        'home' => [
            'label' => 'Home',
            'path' => '/index.php',
            'hint' => 'Where most searches for your company name land. Target your brand plus the service, e.g. "international courier" or "parcel delivery".',
            'priority' => '1.0',
            'changefreq' => 'weekly',
        ],
        'track' => [
            'label' => 'Track Shipment',
            'path' => '/track.php',
            'hint' => 'The page people search for by name. "Track my parcel", "tracking number", "where is my shipment".',
            'priority' => '0.9',
            'changefreq' => 'weekly',
        ],
        'request' => [
            'label' => 'Ship Now',
            'path' => '/request-shipment.php',
            'hint' => 'Someone ready to book. Target intent: "send a parcel to", "book a courier", "shipping quote".',
            'priority' => '0.9',
            'changefreq' => 'monthly',
        ],
        'services' => [
            'label' => 'Services',
            'path' => '/services.php',
            'hint' => 'People comparing options. Target the service names themselves: air freight, sea freight, express delivery.',
            'priority' => '0.8',
            'changefreq' => 'monthly',
        ],
        'countries' => [
            'label' => 'Countries',
            'path' => '/countries.php',
            'hint' => 'Searches that name a place. "Shipping to Germany", "courier to Nigeria". Worth naming your busiest routes.',
            'priority' => '0.7',
            'changefreq' => 'monthly',
        ],
        'about' => [
            'label' => 'About',
            'path' => '/about.php',
            'hint' => 'People checking you are real before they book. Target your company name and where you operate.',
            'priority' => '0.6',
            'changefreq' => 'yearly',
        ],
        'contact' => [
            'label' => 'Contact',
            'path' => '/contact.php',
            'hint' => 'Often searched with your name plus "phone number", "customer service" or "support".',
            'priority' => '0.6',
            'changefreq' => 'yearly',
        ],
        'privacy' => [
            'label' => 'Privacy Policy',
            'path' => '/privacy.php',
            'hint' => 'Rarely searched for. Worth a clear title so it is never mistaken for a more important page.',
            'priority' => '0.3',
            'changefreq' => 'yearly',
        ],
        'terms' => [
            'label' => 'Terms of Service',
            'path' => '/terms.php',
            'hint' => 'As above. A plain title is all this one needs.',
            'priority' => '0.3',
            'changefreq' => 'yearly',
        ],
    ];
}

/**
 * The saved record for one page, or an empty one if nothing has been
 * written yet.
 *
 * Fails open to empty on any database problem, the same way get_setting()
 * does: a site whose seo_pages table has not been imported yet must still
 * render every page, just with the fallback tags below.
 */
function get_seo_page(string $pageKey): array
{
    static $cache = [];
    if (array_key_exists($pageKey, $cache)) {
        return $cache[$pageKey];
    }

    $empty = [
        'meta_title' => '', 'meta_description' => '', 'focus_keyword' => '',
        'meta_keywords' => '', 'og_title' => '', 'og_description' => '',
        'canonical_path' => '', 'noindex' => 0,
    ];

    try {
        $stmt = db()->prepare('SELECT * FROM seo_pages WHERE page_key = ? LIMIT 1');
        $stmt->execute([$pageKey]);
        $row = $stmt->fetch();
        $cache[$pageKey] = $row ? array_merge($empty, $row) : $empty;
    } catch (PDOException $e) {
        $cache[$pageKey] = $empty;
    }

    return $cache[$pageKey];
}

/** Every saved record at once, keyed by page, for the admin screen. */
function get_all_seo_pages(): array
{
    try {
        $rows = db()->query('SELECT * FROM seo_pages')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }

    $byKey = [];
    foreach ($rows as $row) {
        $byKey[$row['page_key']] = $row;
    }
    return $byKey;
}

function save_seo_page(string $pageKey, array $values): void
{
    $stmt = db()->prepare('
        INSERT INTO seo_pages
            (page_key, meta_title, meta_description, focus_keyword, meta_keywords, og_title, og_description, canonical_path, noindex)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            meta_title = VALUES(meta_title),
            meta_description = VALUES(meta_description),
            focus_keyword = VALUES(focus_keyword),
            meta_keywords = VALUES(meta_keywords),
            og_title = VALUES(og_title),
            og_description = VALUES(og_description),
            canonical_path = VALUES(canonical_path),
            noindex = VALUES(noindex)
    ');
    $stmt->execute([
        $pageKey,
        mb_substr(trim($values['meta_title'] ?? ''), 0, 255),
        mb_substr(trim($values['meta_description'] ?? ''), 0, 320),
        mb_substr(trim($values['focus_keyword'] ?? ''), 0, 120),
        mb_substr(trim($values['meta_keywords'] ?? ''), 0, 500),
        mb_substr(trim($values['og_title'] ?? ''), 0, 255),
        mb_substr(trim($values['og_description'] ?? ''), 0, 320),
        mb_substr(trim($values['canonical_path'] ?? ''), 0, 255),
        !empty($values['noindex']) ? 1 : 0,
    ]);
}

/**
 * Everything the header needs for one page, with each field either what
 * was saved or a reasonable fallback.
 *
 * $fallbackTitle is the page's own on-screen heading, passed in by the
 * page itself, so a page nobody has written SEO text for still gets a
 * title that describes it rather than the site name nine times over.
 */
function seo_meta_for(string $pageKey, string $fallbackTitle = '', string $fallbackDescription = ''): array
{
    $saved = get_seo_page($pageKey);
    $siteName = get_site_name();
    $pages = seo_pages();
    $path = $pages[$pageKey]['path'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/');

    $title = $saved['meta_title'] !== ''
        ? $saved['meta_title']
        : ($fallbackTitle !== '' ? $fallbackTitle . ' | ' . $siteName : $siteName);

    $description = $saved['meta_description'] !== ''
        ? $saved['meta_description']
        : ($fallbackDescription !== '' ? $fallbackDescription : get_setting('seo_default_description', ''));

    if ($description === '') {
        // Last resort, and map-aware: a site with the live map switched off
        // should not be advertising a live map in search results.
        $description = live_map_enabled()
            ? 'Track your shipment live on the map and get instant email alerts on every status update.'
            : 'Track your shipment and get instant email alerts on every status update.';
    }

    // Keywords: the focus keyword always leads, then the supporting list,
    // with duplicates and blanks dropped.
    $keywords = [];
    foreach (array_merge([$saved['focus_keyword']], explode(',', $saved['meta_keywords'])) as $keyword) {
        $keyword = trim($keyword);
        if ($keyword !== '' && !in_array(strtolower($keyword), array_map('strtolower', $keywords), true)) {
            $keywords[] = $keyword;
        }
    }

    $canonicalPath = $saved['canonical_path'] !== '' ? $saved['canonical_path'] : $path;
    if (!str_starts_with($canonicalPath, '/')) {
        $canonicalPath = '/' . $canonicalPath;
    }

    return [
        'title' => $title,
        'description' => $description,
        'keywords' => $keywords,
        'og_title' => $saved['og_title'] !== '' ? $saved['og_title'] : $title,
        'og_description' => $saved['og_description'] !== '' ? $saved['og_description'] : $description,
        'canonical' => get_site_url() . $canonicalPath,
        'noindex' => (int) $saved['noindex'] === 1,
        'image' => seo_share_image_url(),
    ];
}

/**
 * The picture shown when a page is shared on a social site or in a chat.
 * Uses whatever was chosen on the SEO screen, then the site logo, then
 * the built-in mark, so there is always something rather than a blank box.
 */
function seo_share_image_url(): string
{
    $configured = trim(get_setting('seo_share_image', ''));
    if ($configured !== '') {
        return str_starts_with($configured, 'http') ? $configured : get_site_url() . $configured;
    }

    $logo = get_logo_url();
    if ($logo) {
        return str_starts_with($logo, 'http') ? $logo : get_site_url() . $logo;
    }

    return get_site_url() . '/assets/images/logo-mark.svg';
}

/**
 * The site verification tags search engines ask you to add before they
 * will show you your own traffic.
 *
 * Google gives you a whole <meta> tag to paste; people paste the whole tag
 * about as often as they paste just the code out of it. Both are accepted
 * and the code is pulled out either way, because "it says it is not
 * verified and I definitely pasted it" is a miserable afternoon.
 */
function seo_verification_token(string $settingKey): string
{
    $raw = trim(get_setting($settingKey, ''));
    if ($raw === '') {
        return '';
    }

    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $raw, $m)) {
        $raw = $m[1];
    }

    // Verification codes are URL-safe text. Anything else is a paste that
    // went wrong, and must never be printed into an attribute unescaped.
    return preg_match('/^[A-Za-z0-9_\-]{10,120}$/', $raw) ? $raw : '';
}

/**
 * Structured data describing the business, which is what lets a search
 * engine show a logo, a phone number and the site's own search box
 * alongside the result rather than a bare blue link.
 */
function seo_structured_data(): string
{
    $siteUrl = get_site_url();
    $siteName = get_site_name();

    $organisation = [
        '@context' => 'https://schema.org',
        '@type' => 'MovingCompany',
        'name' => $siteName,
        'url' => $siteUrl,
        'logo' => seo_share_image_url(),
        'description' => get_setting('seo_default_description', '') ?: 'Courier and freight services with shipment tracking.',
    ];

    $phone = trim(get_setting('contact_phone', ''));
    if ($phone !== '') {
        $organisation['telephone'] = $phone;
        $organisation['contactPoint'] = [
            '@type' => 'ContactPoint',
            'telephone' => $phone,
            'contactType' => 'customer service',
        ];
    }

    $email = trim(get_setting('contact_email', ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $organisation['email'] = $email;
    }

    $address = trim(get_setting('contact_address', ''));
    if ($address !== '') {
        $organisation['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
        ];
    }

    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => $siteUrl,
        // Tells a search engine that tracking numbers can be looked up
        // here, which is what can earn the site its own search box in the
        // results.
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $siteUrl . '/track.php?tn={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    return '<script type="application/ld+json">' . json_encode($organisation, $flags) . '</script>' . "\n"
        . '<script type="application/ld+json">' . json_encode($website, $flags) . '</script>';
}

/**
 * True when the whole site is being kept out of search results, which is
 * what you want while it is still being built and emphatically not what
 * you want afterwards. The SEO screen says so in as many words.
 */
function seo_site_hidden(): bool
{
    return get_setting('seo_noindex_site', '0') === '1';
}
