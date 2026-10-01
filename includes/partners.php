<?php
/**
 * The partner strip: companies the business works with, shown as a slowly
 * moving row of logos on the homepage. Managed under Partners in the
 * admin panel, with one switch to hide the whole strip.
 *
 * Nothing is listed out of the box. A company's name and logo are its
 * trademarks, so only companies the business really has an agreement with
 * belong here, shown with the logo artwork they supplied.
 *
 * Requires config/db.php and includes/settings.php to already be loaded.
 */

function partners_enabled(): bool
{
    return get_setting('partners_enabled', '1') === '1';
}

/** Published partners in display order. Empty on any database problem. */
function published_partners(): array
{
    static $rows = null;
    if ($rows === null) {
        try {
            $rows = db()->query('SELECT id, name, logo_path, website_url FROM partners
                                 WHERE is_published = 1 ORDER BY sort_order ASC, name ASC LIMIT 60')->fetchAll();
        } catch (PDOException $e) {
            $rows = [];
        }
    }
    return $rows;
}

/** True when the strip should appear: switched on and someone to show. */
function partners_visible(): bool
{
    return partners_enabled() && published_partners() !== [];
}

/**
 * One partner as it appears in the strip: the logo when there is one,
 * otherwise the name set in the site's own type, linked when a website
 * was given.
 */
function render_partner_item(array $partner): string
{
    $name = (string) $partner['name'];
    $logo = trim((string) ($partner['logo_path'] ?? ''));
    $inner = $logo !== ''
        ? '<img src="' . h($logo) . '" alt="' . h($name) . '" loading="lazy" decoding="async">'
        : '<span class="partner-name">' . h($name) . '</span>';

    $url = trim((string) ($partner['website_url'] ?? ''));
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        return '<a class="partner-item" href="' . h($url) . '" target="_blank" rel="noopener noreferrer nofollow" title="' . h($name) . '">' . $inner . '</a>';
    }
    return '<span class="partner-item" title="' . h($name) . '">' . $inner . '</span>';
}

/**
 * The partner strip. '' when switched off or empty, so a page can simply
 * echo it. The list is printed twice inside the moving track so the loop
 * joins up seamlessly; the copy is hidden from screen readers.
 */
function render_partners_strip(string $label = 'Trusted partners', string $variant = ''): string
{
    if (!partners_visible()) {
        return '';
    }
    $items = '';
    foreach (published_partners() as $partner) {
        $items .= render_partner_item($partner);
    }

    // A short list is repeated so the moving row is always wider than the
    // screen; otherwise a gap would open up as it scrolls.
    $count = count(published_partners());
    $repeat = max(1, (int) ceil(8 / max(1, $count)));
    $group = str_repeat($items, $repeat);

    return '<section class="partners-strip' . ($variant !== '' ? ' partners-strip--' . h($variant) : '') . '" aria-label="' . h($label) . '">'
        . '<div class="container"><div class="partners-label">' . h($label) . '</div></div>'
        . '<div class="marquee" data-marquee>'
        . '<div class="marquee-track">'
        . '<div class="marquee-group">' . $group . '</div>'
        . '<div class="marquee-group" aria-hidden="true">' . preg_replace('/<a /', '<a tabindex="-1" ', $group) . '</div>'
        . '</div></div></section>';
}
