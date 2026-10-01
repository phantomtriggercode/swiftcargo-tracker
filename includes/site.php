<?php
/**
 * Building blocks for the public site's six templates.
 *
 * Each template (Classic, Modern, Minimal, Bold, Corporate, Dark Header)
 * is a different website on top of the same content: its own header, its
 * own homepage, its own page banners, footer, typeface, motion and wording.
 * What stays the same is the business itself: the company name, logo,
 * contact details, services, statuses and every switch in the admin panel.
 *
 * Colours are never set here. They come from the active colour palette,
 * and every template draws text with the contrast-checked variables from
 * palette_contrast_vars() (includes/design.php), so any palette works with
 * any template.
 *
 * Requires config/db.php, includes/functions.php and includes/settings.php.
 */

require_once __DIR__ . '/reviews.php';
require_once __DIR__ . '/partners.php';

/** The active template's key, always one of the six. */
function site_template(): string
{
    static $key = null;
    if ($key === null) {
        $active = active_template_layout_key();
        $key = array_key_exists($active, TEMPLATE_LAYOUT_KEYS) ? $active : 'classic';
    }
    return $key;
}

/** The active template's entry from a [template => value] list. */
function tpl(array $choices)
{
    $t = site_template();
    if (array_key_exists($t, $choices)) {
        return $choices[$t];
    }
    return $choices['classic'] ?? reset($choices);
}

// ---------------------------------------------------------------
// Wording.
//
// Each template speaks in its own voice. Copy the owner has written
// under Site Content always wins, in every template; only text still at
// the wording this site shipped with is replaced by the template's own.
// That is what lets a template change the words without ever throwing
// away something somebody typed.
// ---------------------------------------------------------------

/** The wording each editable field shipped with, in every past version. */
function shipped_copy(): array
{
    return [
        'home_hero_title'       => ['Ship anywhere. Track everything. Live.'],
        'home_hero_lead'        => [
            '{site} moves freight and parcels across the United States and worldwide, and shows you exactly where they are on a live map, with an email sent to your receiver on every single update.',
            '{site} moves freight and parcels across the United States and worldwide, with an email sent to your receiver on every single update.',
        ],
        'home_track_title'      => ['Track your shipment'],
        'home_track_lead'       => [
            'Enter your tracking number to see live location and delivery status.',
            'Enter your tracking number to see its current status and delivery progress.',
        ],
        'home_features_eyebrow' => ['Why {site}'],
        'home_features_title'   => ['Built for peace of mind'],
        'home_features_lead'    => ['Every shipment is monitored end-to-end, with automatic alerts so your receiver is never left guessing.'],
        'home_operate_eyebrow'  => ['How We Operate'],
        'home_operate_title'    => ['Real people, real fleet, real care'],
        'home_operate_lead'     => ['From the warehouse floor to your front door, every shipment is handled by trained staff and tracked the whole way.'],
        'home_steps_eyebrow'    => ['How it works'],
        'home_steps_title'      => ['Three simple steps'],
        'home_gallery_eyebrow'  => ['In Action'],
        'home_gallery_title'    => ['Our Fleet in Motion'],
        'services_title'        => ['Our Services'],
        'services_lead'         => ['Flexible shipping options for every kind of package, budget and deadline.'],
        'services_include_eyebrow' => ['Every plan includes'],
        'services_include_title'   => ['Full visibility, no extra cost'],
        'about_title'           => ['About {site}', 'About SwiftCargo'],
        'about_lead'            => ['A US-based freight and parcel carrier built around one idea: you should always know exactly where your shipment is.'],
        'countries_title'       => ['Countries We Ship To'],
        'countries_intro'       => ['{site} ships to every country in the world. Wherever your shipment is headed, we can get it there.'],
        'contact_intro'         => ['Questions about a shipment, a quote, or our services? Reach our support team any time.'],
        'request_title'         => ['Request a Shipment'],
        'request_lead'          => ["Tell us what you're shipping and when. We'll get back to you with a confirmed quote. Prices below are a live estimate."],
    ];
}

/** Lower case, the company name replaced by a marker, punctuation ignored. */
function normalize_copy(string $text): string
{
    $site = get_site_name();
    $text = mb_strtolower($text);
    foreach (array_unique([mb_strtolower($site), 'swiftcargo']) as $name) {
        if ($name !== '') {
            $text = str_replace($name, '{site}', $text);
        }
    }
    $text = preg_replace('/[^a-z0-9{}]+/u', ' ', $text);
    return trim((string) $text);
}

/** True when $value is empty or still the wording the site shipped with. */
function copy_is_shipped(string $key, string $value): bool
{
    if (trim($value) === '') {
        return true;
    }
    $known = shipped_copy()[$key] ?? [];
    $normalized = normalize_copy($value);
    foreach ($known as $candidate) {
        if ($normalized === normalize_copy($candidate)) {
            return true;
        }
    }
    return false;
}

/**
 * The text for an editable field: the owner's own wording if they have
 * written some, otherwise the active template's. "{site}" in the template
 * text becomes the company name.
 */
function site_copy(string $key, string $templateText): string
{
    $stored = get_setting($key, '');
    if (!copy_is_shipped($key, $stored)) {
        return $stored;
    }
    return str_replace('{site}', get_site_name(), $templateText);
}

/** Like site_copy(), with the template's wording picked per template. */
function site_copy_tpl(string $key, array $byTemplate): string
{
    return site_copy($key, (string) tpl($byTemplate));
}

/**
 * Long editable text (the About story). Kept exactly as written, except
 * that untouched shipped text gets the real company name in place of the
 * placeholder name it shipped with.
 */
function site_body(string $key): string
{
    $text = get_setting($key, '');
    $site = get_site_name();
    if ($site !== 'SwiftCargo' && str_contains($text, 'SwiftCargo') && !str_contains($text, $site)) {
        $text = str_replace('SwiftCargo', $site, $text);
    }
    return $text;
}

// ---------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------

/**
 * The menu, in the active template's words. Items that are switched off
 * (Ship Now, Reviews) are left out here, so every header and footer that
 * uses this list follows the switches automatically.
 *
 * @return array<int, array{key: string, href: string, label: string}>
 */
function site_nav_items(): array
{
    $labels = tpl([
        'classic'     => ['home' => 'Home', 'track' => 'Track Shipment', 'request' => 'Ship Now', 'services' => 'Services', 'countries' => 'Countries', 'about' => 'About', 'testimonials' => 'Reviews', 'contact' => 'Contact'],
        'modern'      => ['home' => 'Home', 'track' => 'Track', 'request' => 'Ship', 'services' => 'Services', 'countries' => 'Coverage', 'about' => 'About', 'testimonials' => 'Reviews', 'contact' => 'Contact'],
        'minimal'     => ['home' => 'Home', 'track' => 'Tracking', 'request' => 'Book', 'services' => 'Services', 'countries' => 'Destinations', 'about' => 'About', 'testimonials' => 'Kind words', 'contact' => 'Contact'],
        'bold'        => ['home' => 'Home', 'track' => 'Track', 'request' => 'Ship Now', 'services' => 'Services', 'countries' => 'Countries', 'about' => 'About', 'testimonials' => 'Reviews', 'contact' => 'Contact'],
        'corporate'   => ['home' => 'Home', 'track' => 'Tracking', 'request' => 'Request a Quote', 'services' => 'Services', 'countries' => 'Global Network', 'about' => 'Company', 'testimonials' => 'Testimonials', 'contact' => 'Contact'],
        'dark-header' => ['home' => 'Home', 'track' => 'Live Tracking', 'request' => 'Ship', 'services' => 'Services', 'countries' => 'Network', 'about' => 'About', 'testimonials' => 'Reviews', 'contact' => 'Contact'],
    ]);

    $items = [
        ['key' => 'home', 'href' => '/index.php'],
        ['key' => 'track', 'href' => '/track.php'],
    ];
    if (request_shipment_enabled()) {
        $items[] = ['key' => 'request', 'href' => '/request-shipment.php'];
    }
    $items[] = ['key' => 'services', 'href' => '/services.php'];
    $items[] = ['key' => 'countries', 'href' => '/countries.php'];
    $items[] = ['key' => 'about', 'href' => '/about.php'];
    if (reviews_visible()) {
        $items[] = ['key' => 'testimonials', 'href' => '/testimonials.php'];
    }
    $items[] = ['key' => 'contact', 'href' => '/contact.php'];

    foreach ($items as &$item) {
        $item['label'] = $labels[$item['key']] ?? ucfirst($item['key']);
    }
    unset($item);
    return $items;
}

/** <a> links for the menu, marking the current page. */
function render_nav_links(string $activeNav, string $class = '', array $skip = []): string
{
    $html = '';
    foreach (site_nav_items() as $item) {
        if (in_array($item['key'], $skip, true)) {
            continue;
        }
        $isActive = $activeNav === $item['key'];
        $html .= '<a href="' . h($item['href']) . '" class="' . trim($class . ($isActive ? ' active' : '')) . '"'
            . ($isActive ? ' aria-current="page"' : '') . '><span>' . h($item['label']) . '</span></a>';
    }
    return $html;
}

/** The menu button shown on tablets and phones. */
function render_menu_button(): string
{
    return '<button type="button" class="nav-menu-btn" id="nav-menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav-dropdown">'
        . '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
        . '</button>';
}

/**
 * The menu that drops down (or slides in, depending on the template) on
 * tablets and phones. Same links as the desktop menu, plus the phone
 * number and the tracking button at the bottom.
 */
function render_mobile_nav(string $activeNav): string
{
    $phone = trim(get_setting('contact_phone', ''));
    $html = '<nav class="mobile-nav-dropdown" id="mobile-nav-dropdown" aria-label="Mobile navigation">'
        . '<div class="mobile-nav-links">' . render_nav_links($activeNav, 'mobile-nav-link') . '</div>'
        . '<div class="mobile-nav-foot">';
    if ($phone !== '') {
        $html .= '<a class="mobile-nav-phone" href="' . h(phone_href()) . '">'
            . '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>'
            . h($phone) . '</a>';
    }
    $html .= '<a class="btn btn-primary btn-block" href="/track.php">' . h(header_track_label()) . '</a>'
        . '</div></nav>';
    return $html;
}

/** Small inline icons used in headers and footers. */
function ui_icon(string $name, int $size = 18): string
{
    $paths = [
        'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 7l9 6 9-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        'pin'    => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="9" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/>',
        'clock'  => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'search' => '<circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        'box'    => '<path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z M3 7.5l9 4.5 9-4.5 M12 12v9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        'globe'  => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" fill="none" stroke="currentColor" stroke-width="1.8"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        'quote'  => '<path d="M9 7H5v6h4v4l3-4V7zm10 0h-4v6h4v4l3-4V7z" fill="currentColor"/>',
    ];
    return '<svg class="ui-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

/** The label of the "Track" call to action in the header, per template. */
function header_track_label(): string
{
    return (string) tpl([
        'classic' => 'Track Now', 'modern' => 'Track parcel', 'minimal' => 'Track',
        'bold' => 'Track it', 'corporate' => 'Track Now', 'dark-header' => 'Track',
    ]);
}

// ---------------------------------------------------------------
// Brand block (logo, company name, tagline)
// ---------------------------------------------------------------

/**
 * The logo with the company name and tagline beside it, following the
 * Branding switches: name on/off, tagline on/off, and the bigger
 * logo-on-its-own layout when both are off. The name's size limits come
 * from its length (brand_name_max_font_size) so a 24-character name still
 * fits on one line.
 *
 * $onDark gives an uploaded logo a light plate, for dark or coloured
 * headers where dark lettering would disappear.
 */
function render_brand(array $opts = []): string
{
    $logoAlone = logo_stands_alone();
    $showTitle = header_shows_title();
    $tagline = header_shows_tagline() ? get_header_tagline() : '';

    $markH = (int) ($opts['mark_h'] ?? 56);
    $lockupH = (int) ($opts['lockup_h'] ?? 68);
    $markMaxW = logo_is_wide() ? (int) ($opts['wide_max_w'] ?? 170) : (int) ($opts['mark_max_w'] ?? 100);
    $lockupMaxW = (int) ($opts['lockup_max_w'] ?? 300);
    $onDark = !empty($opts['on_dark']) && logo_is_custom();
    $class = trim('logo ' . ($logoAlone ? 'logo-lockup ' : '') . ($onDark ? 'logo-on-dark ' : '') . ($opts['class'] ?? ''));

    $html = '<a href="/index.php" class="' . h($class) . '">'
        . logo_img_tag(
            $logoAlone ? $lockupH : $markH,
            $logoAlone ? $lockupMaxW : $markMaxW,
            'mark-img',
            $logoAlone ? get_site_name() : ''
        );

    if (!$logoAlone) {
        $html .= '<span class="brand-text" style="--brand-min:' . brand_name_min_font_size() . 'px;--brand-max:' . brand_name_max_font_size() . 'px;">';
        if ($showTitle) {
            $html .= '<span class="word-brand">' . h(get_site_name()) . '</span>';
        }
        if ($tagline !== '') {
            $html .= '<span class="brand-tagline">' . h($tagline) . '</span>';
        }
        $html .= '</span>';
    }

    return $html . '</a>';
}

// ---------------------------------------------------------------
// Pictures
// ---------------------------------------------------------------

/**
 * The photo library the templates draw on: the twenty illustrations plus
 * the large photographs, each with a description for screen readers.
 */
function site_photo(string $key): array
{
    static $photos = null;
    if ($photos === null) {
        $base = '/assets/images/illustrations/';
        $photos = [
            'port'        => [$base . '01_container_port_ship.jpg', 'Container ship at a port with cranes at sunset'],
            'port-team'   => [$base . '02_port_workers_tablet.jpg', 'Port staff checking a shipment on a tablet'],
            'crane'       => [$base . '03_crane_loading_container.jpg', 'Crane lifting a shipping container'],
            'truck'       => [$base . '04_road_freight_truck.jpg', 'Road freight truck on the highway'],
            'unload'      => [$base . '05_truck_offloading_cargo.jpg', 'Forklift unloading cargo from a truck'],
            'scan'        => [$base . '06_warehouse_barcode_scanning.jpg', 'Warehouse worker scanning a parcel barcode'],
            'boxes'       => [$base . '07_warehouse_box_handling.jpg', 'Warehouse worker carrying a parcel'],
            'forklift'    => [$base . '08_forklift_pallet_loading.jpg', 'Forklift loading a pallet of boxes'],
            'ship'        => [$base . '09_cargo_ship_at_sea.jpg', 'Cargo ship sailing at sea'],
            'air-load'    => [$base . '10_airport_air_cargo_loading.jpg', 'Air cargo being loaded onto an aircraft'],
            'air-ground'  => [$base . '11_aircraft_cargo_ground_handling.jpg', 'Ground crew handling air freight beside a plane'],
            'customs'     => [$base . '12_airport_customs_inspection.jpg', 'Customs officers inspecting a parcel'],
            'doorstep'    => [$base . '13_package_doorstep_delivery.jpg', 'Courier handing a parcel to a customer at the door'],
            'van'         => [$base . '14_delivery_van_driver.jpg', 'Delivery driver with a parcel beside the van'],
            'supervisor'  => [$base . '15_port_logistics_supervisor.jpg', 'Logistics supervisor at a container terminal'],
            'highway'     => [$base . '16_highway_container_transport.jpg', 'Container truck on a mountain highway'],
            'loader'      => [$base . '17_heavy_container_loader.jpg', 'Heavy loader moving a container'],
            'packing'     => [$base . '18_warehouse_package_packing.jpg', 'Warehouse worker packing a parcel'],
            'inspector'   => [$base . '19_port_operations_inspector.jpg', 'Port operations inspector with a tablet'],
            'network'     => [$base . '20_global_air_sea_road_logistics.jpg', 'Air, sea and road freight together'],
            'hero-collage'=> [$base . 'photo-hero-collage.jpg', 'Shipments on the move by road and air'],
            'yard'        => [$base . 'photo-container-yard.jpg', 'Shipping container being loaded at a yard'],
            'semi-sunset' => [$base . 'photo-semi-sunset.jpg', 'Delivery truck on the highway at sunset'],
            'semi-mountains' => [$base . 'photo-semi-mountains.jpg', 'Freight truck on a long-haul mountain route'],
            'stacking'    => [$base . 'photo-warehouse-stacking.jpg', 'Warehouse team stacking packages'],
            'unloading'   => [$base . 'photo-warehouse-unloading.jpg', 'Warehouse team unloading a delivery truck'],
            'clipboard'   => [$base . 'photo-staff-clipboard.jpg', 'Team member checking a shipment list'],
            'van-city'    => [$base . 'photo-van-city.jpg', 'Delivery van in the city'],
            'van-dusk'    => [$base . 'photo-van-dusk.jpg', 'Delivery van on the road at dusk'],
            'porch'       => [$base . 'photo-porch-delivery.jpg', 'Courier delivering a parcel to a porch'],
            'doorstep-alt'=> [$base . 'photo-doorstep-alt.jpg', 'Courier delivering a parcel at the front door'],
        ];
    }
    $photo = $photos[$key] ?? $photos['port'];
    return ['src' => $photo[0], 'alt' => $photo[1]];
}

/** An <img> for a library photo. */
function photo_img(string $key, string $class = '', bool $lazy = true, ?string $alt = null): string
{
    $p = site_photo($key);
    return '<img src="' . h($p['src']) . '" alt="' . h($alt ?? $p['alt']) . '"'
        . ($class !== '' ? ' class="' . h($class) . '"' : '')
        . ($lazy ? ' loading="lazy" decoding="async"' : '') . '>';
}

// ---------------------------------------------------------------
// Pieces every page uses
// ---------------------------------------------------------------

/**
 * The banner at the top of every inner page, drawn differently by each
 * template: a photo banner with a breadcrumb, a rounded gradient card, a
 * plain editorial heading, a diagonal slab with a giant watermark word, a
 * corporate photo banner with a breadcrumb bar, or a dark grid band.
 */
function page_banner(string $title, string $lead = '', array $opts = []): string
{
    $t = site_template();
    $key = (string) ($opts['key'] ?? '');
    $photo = site_photo((string) ($opts['photo'] ?? 'port'));
    $crumb = (string) ($opts['crumb'] ?? $title);
    $kicker = (string) ($opts['kicker'] ?? '');
    $number = (string) ($opts['number'] ?? '');
    $align = !empty($opts['center']) ? ' page-banner--center' : '';

    $leadHtml = $lead !== '' ? '<p class="page-banner-lead">' . h($lead) . '</p>' : '';
    $extra = (string) ($opts['extra'] ?? '');
    $kickerHtml = $kicker !== '' ? '<div class="page-banner-kicker">' . h($kicker) . '</div>' : '';
    $crumbs = '<nav class="crumbs" aria-label="Breadcrumb"><a href="/index.php">Home</a><span aria-hidden="true">/</span><span aria-current="page">' . h($crumb) . '</span></nav>';

    switch ($t) {
        case 'modern':
            return '<section class="page-banner page-banner--modern' . $align . '"><div class="container">'
                . '<div class="pb-card"><span class="pb-blob pb-blob--a" aria-hidden="true"></span><span class="pb-blob pb-blob--b" aria-hidden="true"></span>'
                . '<div class="pb-text">' . $kickerHtml . '<h1>' . h($title) . '</h1>' . $leadHtml . $extra . '</div>'
                . '<div class="pb-media" aria-hidden="true"><img src="' . h($photo['src']) . '" alt="" loading="eager"></div>'
                . '</div></div></section>';

        case 'minimal':
            return '<section class="page-banner page-banner--minimal' . $align . '"><div class="container">'
                . '<div class="pb-index">' . ($number !== '' ? h($number) . ' &mdash; ' : '') . h($kicker !== '' ? $kicker : $crumb) . '</div>'
                . '<h1>' . h($title) . '</h1>' . $leadHtml . $extra
                . '</div></section>';

        case 'bold':
            return '<section class="page-banner page-banner--bold' . $align . '">'
                . '<div class="pb-watermark" aria-hidden="true">' . h($crumb) . '</div>'
                . '<div class="container">' . $kickerHtml . '<h1>' . h($title) . '</h1>' . $leadHtml . $extra . '</div>'
                . '</section>';

        case 'corporate':
            return '<section class="page-banner page-banner--corporate' . $align . '" style="--pb-photo:url(\'' . h($photo['src']) . '\')">'
                . '<div class="container">' . $kickerHtml . '<h1>' . h($title) . '</h1>' . $leadHtml . $extra . '</div>'
                . '</section><div class="crumb-bar"><div class="container">' . $crumbs . '</div></div>';

        case 'dark-header':
            return '<section class="page-banner page-banner--dark' . $align . '"><span class="pb-grid" aria-hidden="true"></span><span class="pb-glow" aria-hidden="true"></span>'
                . '<div class="container">' . '<div class="page-banner-kicker">// ' . h(mb_strtolower($kicker !== '' ? $kicker : $crumb)) . '</div>'
                . '<h1>' . h($title) . '</h1>' . $leadHtml . $extra . '</div></section>';

        default: // classic
            return '<section class="page-banner page-banner--classic' . $align . '" style="--pb-photo:url(\'' . h($photo['src']) . '\')">'
                . '<div class="container">' . $crumbs . '<h1>' . h($title) . '</h1>' . $leadHtml . $extra . '</div>'
                . '</section>';
    }
}

/**
 * The tracking-number form, in the look of the template it sits in.
 */
function render_track_form(string $class = '', string $value = '', string $button = '', string $placeholder = ''): string
{
    $button = $button !== '' ? $button : (string) tpl([
        'classic' => 'Track', 'modern' => 'Track', 'minimal' => 'Track &rarr;', 'bold' => 'Track it',
        'corporate' => 'Track', 'dark-header' => 'Locate',
    ]);
    $placeholder = $placeholder !== '' ? $placeholder : (string) tpl([
        'classic' => 'Enter your tracking number', 'modern' => 'Paste your tracking number',
        'minimal' => 'Tracking number', 'bold' => 'YOUR TRACKING NUMBER', 'corporate' => 'Enter tracking number',
        'dark-header' => 'Tracking ID',
    ]);

    return '<form class="track-form ' . h($class) . '" action="/track.php" method="get" role="search">'
        . '<label class="sr-only" for="tn-' . substr(md5($class . $button), 0, 6) . '">Tracking number</label>'
        . '<input type="text" id="tn-' . substr(md5($class . $button), 0, 6) . '" name="tn" value="' . h($value) . '" placeholder="' . h($placeholder) . '" required autocomplete="off" autocapitalize="characters" spellcheck="false">'
        . '<button type="submit" class="btn btn-primary">' . $button . '</button>'
        . '</form>';
}

/** Stat figure with count-up data, e.g. "1.2M+" counts to 1.2 then adds "M+". */
function render_stat(string $value, string $label, string $class = 'stat'): string
{
    $attr = '';
    if (preg_match('/^([^0-9]*)([0-9]+(?:[.,][0-9]+)?)(.*)$/u', trim($value), $m)) {
        $number = str_replace(',', '', $m[2]);
        $decimals = str_contains($number, '.') ? strlen(substr($number, strpos($number, '.') + 1)) : 0;
        $attr = ' data-count="' . h($number) . '" data-decimals="' . $decimals . '" data-prefix="' . h($m[1]) . '" data-suffix="' . h($m[3]) . '"';
    }
    return '<div class="' . h($class) . '"><div class="stat-num"' . $attr . '>' . h($value) . '</div><div class="stat-label">' . h($label) . '</div></div>';
}

/** The four editable stats, as [value, label] pairs. */
function site_stats(): array
{
    return [
        [get_setting('stat_countries', '195+'), (string) tpl(['minimal' => 'Countries', 'bold' => 'Countries', 'dark-header' => 'Countries online', 'classic' => 'Countries & territories served', 'modern' => 'Countries covered', 'corporate' => 'Countries & territories'])],
        [get_setting('stat_ontime', '98.6%'), (string) tpl(['minimal' => 'On time', 'bold' => 'On time', 'dark-header' => 'On-time arrivals', 'classic' => 'On-time delivery rate', 'modern' => 'Delivered on time', 'corporate' => 'On-time performance'])],
        [get_setting('stat_support', '24/7'), (string) tpl(['minimal' => 'Support', 'bold' => 'Support', 'dark-header' => 'Network uptime', 'classic' => 'Live tracking & support', 'modern' => 'Human support', 'corporate' => 'Customer support'])],
        [get_setting('stat_delivered', '1.2M+'), (string) tpl(['minimal' => 'Parcels delivered', 'bold' => 'Delivered', 'dark-header' => 'Parcels delivered', 'classic' => 'Packages delivered', 'modern' => 'Parcels delivered', 'corporate' => 'Shipments delivered'])],
    ];
}

/** The six "why us" feature cards from Site Content, map-aware. */
function site_features(): array
{
    $defaults = [
        1 => ['Live Map Tracking', 'Watch your package move across an interactive world map in real time, from pickup to doorstep.', 'map-pin'],
        2 => ['Automatic Email Alerts', 'The receiver gets an email the instant a shipment status changes, so they always know: picked up, in transit, out for delivery, delivered.', 'mail'],
        3 => ['Real-Time Status Timeline', 'A full, timestamped history of every checkpoint your package has passed through.', 'clock'],
        4 => ['Regular & Express Options', 'Choose the service level that matches your urgency, and ship by air, sea, or land.', 'box'],
        5 => ['Worldwide Coverage', 'From coast to coast across the U.S. and worldwide, we move freight and parcels reliably to every country we serve.', 'globe'],
        6 => ['Secure Handling', 'Every parcel is logged, verified and handled by trained staff at each checkpoint.', 'shield'],
    ];
    $features = [];
    foreach ($defaults as $i => [$title, $desc, $icon]) {
        $t = get_setting("home_feature{$i}_title", $title);
        $d = get_setting("home_feature{$i}_desc", $desc);
        // The map card is dropped when the map is off and the card still
        // talks about it.
        if ($i === 1 && !live_map_enabled() && mentions_live_map($t . ' ' . $d)) {
            continue;
        }
        $features[] = ['title' => $t, 'desc' => $d, 'icon' => '/assets/images/icons/' . $icon . '.svg'];
    }
    return $features;
}

/** The four "how we operate" rows from Site Content. */
function site_operate_rows(): array
{
    $rows = [
        1 => ['home_row1_image', 'stacking', 'Careful handling at every hub', 'Every parcel and pallet is scanned, verified, and handled by trained staff the moment it arrives at one of our facilities, logged instantly so your tracking page updates in real time.'],
        2 => ['home_row2_image', 'semi-sunset', 'A fleet built for reliability', 'Ground transport by van, trailer, or rail, and air and sea freight for long-haul and international shipments, routed for speed without cutting corners.'],
        3 => ['home_row3_image', 'unloading', 'Fast, careful unloading', 'At every stop, our team unloads and sorts shipments quickly and carefully, keeping your delivery window tight and your package intact.'],
        4 => ['home_row4_image', 'doorstep', 'Right to your door', "The last mile matters most. Our couriers deliver directly to your doorstep, and your receiver gets an email the moment it's dropped off."],
    ];
    $out = [];
    foreach ($rows as $i => [$imageKey, $photoKey, $title, $desc]) {
        $photo = site_photo($photoKey);
        $out[] = [
            'image' => get_site_image($imageKey, $photo['src']),
            'alt' => $photo['alt'],
            'title' => get_setting("home_row{$i}_title", $title),
            'desc' => get_setting("home_row{$i}_desc", $desc),
        ];
    }
    return $out;
}

/** The three "how it works" steps from Site Content. */
function site_steps(): array
{
    return [
        ['title' => get_setting('home_step1_title', 'Book a shipment'), 'desc' => get_setting('home_step1_desc', 'Our team creates your shipment and issues a unique tracking number.')],
        ['title' => get_setting('home_step2_title', 'We move it'), 'desc' => get_setting('home_step2_desc', 'Your package travels through our network of hubs, with each checkpoint logged live.')],
        ['title' => get_setting('home_step3_title', 'You & the receiver stay informed'), 'desc' => get_setting_map_aware(
            'home_step3_desc',
            'Every update triggers an instant email, and anyone can watch progress on the live map.',
            'Every update triggers an instant email, and anyone can follow progress on the tracking page.'
        )],
    ];
}

/** The three service cards from Site Content (Priority, Express, Standard). */
function site_service_cards(): array
{
    return [
        ['title' => get_setting('services_card1_title', 'Priority'), 'desc' => get_setting('services_card1_desc', 'Our fastest service for time-critical shipments, with premium handling and priority routing at every hub.'), 'icon' => '/assets/images/icons/rocket.svg', 'photo' => 'air-load'],
        ['title' => get_setting('services_card2_title', 'Express'), 'desc' => get_setting('services_card2_desc', 'Reliable, fast international delivery, ideal for business documents and time-sensitive parcels.'), 'icon' => '/assets/images/icons/plane.svg', 'photo' => 'air-ground'],
        ['title' => get_setting('services_card3_title', 'Standard'), 'desc' => get_setting('services_card3_desc', 'Cost-effective shipping for everyday parcels, with the same live tracking and email alerts.'), 'icon' => '/assets/images/icons/truck.svg', 'photo' => 'truck'],
    ];
}

/**
 * The ways of moving freight, shown as picture cards by several templates.
 * Descriptive wording only; no figures that are not the owner's own.
 */
function site_modes(): array
{
    return [
        ['photo' => 'air-load', 'title' => 'Air freight', 'desc' => 'The fastest way across borders, for urgent parcels and high-value cargo.'],
        ['photo' => 'ship', 'title' => 'Ocean freight', 'desc' => 'Full and part container loads on the world\'s main shipping lanes.'],
        ['photo' => 'highway', 'title' => 'Road freight', 'desc' => 'Door-to-door trucking, from single pallets to full trailers.'],
        ['photo' => 'scan', 'title' => 'Warehousing', 'desc' => 'Secure storage, scanning and sorting at every one of our hubs.'],
        ['photo' => 'customs', 'title' => 'Customs clearance', 'desc' => 'Paperwork prepared and checked, so shipments clear without surprises.'],
        ['photo' => 'doorstep', 'title' => 'Last-mile delivery', 'desc' => 'Couriers who hand it over at the door, with an email when it lands.'],
    ];
}

/** The "get a quote" link target: the request form when it is on, else Contact. */
function quote_url(): string
{
    return request_shipment_enabled() ? '/request-shipment.php' : '/contact.php';
}

/** Phone number as a tel: link. */
function phone_href(): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', get_setting('contact_phone', ''));
}
