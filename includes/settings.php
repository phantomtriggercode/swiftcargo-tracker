<?php
/**
 * Simple key/value settings store backing the admin-editable site content
 * (home/about/contact/footer/countries text) and the shipping calculator
 * rates. Requires config/db.php to already be loaded.
 */

function get_setting(string $key, string $default = ''): string
{
    static $cache = [];

    if (!array_key_exists($key, $cache)) {
        // Falls back to $default on any DB error (e.g. the settings table
        // doesn't exist yet: schema.sql not imported) instead of a fatal
        // error, since this runs on nearly every page load. Same
        // reasoning as includes/design.php's get_active_palette().
        try {
            $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $cache[$key] = $row ? $row['setting_value'] : null;
        } catch (PDOException $e) {
            $cache[$key] = null;
        }
    }

    return $cache[$key] ?? $default;
}

function get_setting_float(string $key, float $default = 0.0): float
{
    $value = get_setting($key, (string) $default);
    return is_numeric($value) ? (float) $value : $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('
        INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');
    $stmt->execute([$key, $value]);
}

/**
 * Renders a settings value that may contain literal "\n\n" paragraph
 * breaks (as stored by the SQL seed data) or real newlines (as saved by
 * the admin textarea) into safe HTML paragraphs.
 */
function render_paragraphs(string $text): string
{
    $normalized = str_replace(['\\r\\n', '\\n', "\r\n"], "\n", $text);
    $paragraphs = preg_split('/\n\s*\n/', trim($normalized));
    $html = '';
    foreach ($paragraphs as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $html .= '<p>' . nl2br(h($p)) . '</p>';
    }
    return $html;
}

/**
 * Parses the newline-separated countries_list setting into a clean array.
 */
function get_countries_list(): array
{
    $raw = get_setting('countries_list', '');
    $normalized = str_replace(['\\r\\n', '\\n', "\r\n"], "\n", $raw);
    $lines = array_map('trim', explode("\n", $normalized));
    return array_values(array_filter($lines, static fn($l) => $l !== ''));
}

/**
 * White-label branding: the site's display name and logo. Both are fully
 * admin-editable (admin/branding.php) so this codebase isn't tied to any
 * one brand or domain. Falls back to the SITE_NAME constant (config.php)
 * when no override has been saved yet, so existing installs keep working
 * unchanged until an admin edits it.
 */
function get_site_name(): string
{
    $value = get_setting('site_name', '');
    return $value !== '' ? $value : (defined('SITE_NAME') ? SITE_NAME : 'Shipping Company');
}

/**
 * The real pixel dimensions of a logo file on disk, or null if they cannot
 * be worked out.
 *
 * This is what lets the site lay out a logo it has never seen. A square
 * badge and a wide name-plate are both "the logo", and they need entirely
 * different room: forcing both into the same box either squashes the wide
 * one or strands the square one in a wide gap.
 *
 * Fails open to null on anything unreadable, and every caller falls back
 * to treating the logo as square, which is what the built-in marks are.
 */
function logo_file_dimensions(string $url): ?array
{
    static $cache = [];
    if (array_key_exists($url, $cache)) {
        return $cache[$url];
    }
    $cache[$url] = null;

    // Only ever look at files inside this site. A logo path comes from the
    // database, and a path that had escaped the web root would otherwise
    // let this read anything on the server.
    // rawurldecode first: a logo dropped onto the server by hand can have
    // spaces in its name, and the stored path may carry them either raw or
    // percent-encoded. Both have to find the same file on disk.
    $path = realpath(__DIR__ . '/..' . rawurldecode((string) parse_url($url, PHP_URL_PATH)));
    $root = realpath(__DIR__ . '/..');
    if ($path === false || $root === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
        return null;
    }

    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
        // An SVG has no pixels, so its proportions come from the viewBox
        // (or from width/height when there is no viewBox). Only the opening
        // tag is read, never the whole file.
        $head = (string) file_get_contents($path, false, null, 0, 2048);
        if (preg_match('/viewBox\s*=\s*["\']\s*[\d.eE+-]+[,\s]+[\d.eE+-]+[,\s]+([\d.eE+-]+)[,\s]+([\d.eE+-]+)/i', $head, $m)) {
            $w = (float) $m[1];
            $h = (float) $m[2];
        } elseif (preg_match('/\bwidth\s*=\s*["\']([\d.]+)/i', $head, $mw)
            && preg_match('/\bheight\s*=\s*["\']([\d.]+)/i', $head, $mh)) {
            $w = (float) $mw[1];
            $h = (float) $mh[1];
        } else {
            return null;
        }
        if ($w <= 0 || $h <= 0) {
            return null;
        }
        $cache[$url] = ['width' => $w, 'height' => $h, 'ratio' => $w / $h];
        return $cache[$url];
    }

    // getimagesize reads only the header of a JPEG/PNG/GIF/WebP, not the
    // whole picture, so this stays cheap even for a large upload.
    $size = @getimagesize($path);
    if (!is_array($size) || empty($size[0]) || empty($size[1])) {
        return null;
    }

    $cache[$url] = ['width' => $size[0], 'height' => $size[1], 'ratio' => $size[0] / $size[1]];
    return $cache[$url];
}

/**
 * True when the logo is one the site owner uploaded, rather than a mark
 * that ships with this codebase.
 *
 * It matters for the dark surfaces, the footer and the admin sidebar. The
 * built-in marks were drawn to sit on them; an uploaded logo was drawn for
 * whatever background its designer had in mind, and a logo with dark
 * lettering and a transparent background simply disappears on a dark
 * panel. Those places give an uploaded logo a light plate to sit on, which
 * is legible whatever the logo turns out to be.
 */
function logo_is_custom(): bool
{
    return get_setting('logo_path', '') !== '';
}

/** The logo actually in use, including the built-in fallback. */
function active_logo_url(): string
{
    return get_logo_url() ?: '/assets/images/logo-mark.svg';
}

/**
 * The same logo, as a URL safe to put in a src attribute.
 *
 * Uploads are given generated names with nothing awkward in them, but a
 * logo copied onto the server by hand can easily be called something like
 * "horizontal logo.png", and a raw space in a URL is not something every
 * browser and proxy handles the same way. Each path segment is encoded,
 * and an already-encoded path is left as it is rather than being encoded
 * twice.
 */
function logo_src_url(): string
{
    $url = active_logo_url();
    if (str_contains($url, '://')) {
        return $url;
    }

    $segments = array_map(
        static fn(string $segment): string => rawurlencode(rawurldecode($segment)),
        explode('/', $url)
    );

    return implode('/', $segments);
}

/** Width divided by height. 1.0 (square) when the file cannot be measured. */
function logo_aspect_ratio(): float
{
    $dims = logo_file_dimensions(active_logo_url());
    return $dims === null ? 1.0 : $dims['ratio'];
}

/**
 * True for a logo noticeably wider than it is tall: a name-plate rather
 * than a badge.
 *
 * 1.6 is the dividing line because it sits well clear of both cases in
 * practice. A badge is 1:1, or close to it once a little padding is
 * baked in. A name-plate carrying a company name alongside a graphic is
 * rarely under 2:1.
 */
function logo_is_wide(): bool
{
    return logo_aspect_ratio() >= 1.6;
}

/**
 * Whether the logo picture already has the company name written in it.
 *
 * When it does, printing the name again beside it says everything twice.
 * The site guesses from the shape, because a wide logo is nearly always a
 * name-plate, and /admin/branding.php lets that guess be overridden either
 * way for the logo that proves the rule.
 */
function logo_includes_name(): bool
{
    $setting = get_setting('logo_includes_name', 'auto');

    if ($setting === 'yes') {
        return true;
    }
    if ($setting === 'no') {
        return false;
    }

    return logo_is_wide();
}

/**
 * An <img> tag for the logo, sized to the space it is being put in without
 * ever distorting it.
 *
 * $maxHeight is the room available, and $maxWidth the most it may spread
 * sideways before it starts crowding whatever sits next to it. A tall
 * narrow logo uses the height; a very wide one gives height back so it
 * stays inside the width. The result is written into the width and height
 * attributes, so the browser reserves exactly the right space and the page
 * never jumps as the image arrives.
 */
function logo_img_tag(int $maxHeight, int $maxWidth, string $class = '', string $alt = ''): string
{
    $url = logo_src_url();
    $ratio = logo_aspect_ratio();

    $height = $maxHeight;
    $width = (int) round($height * $ratio);

    if ($width > $maxWidth) {
        $width = $maxWidth;
        $height = (int) round($width / $ratio);
    }

    return '<img src="' . h($url) . '"'
        . ' alt="' . h($alt) . '"'
        . ' width="' . $width . '" height="' . $height . '"'
        . ($class !== '' ? ' class="' . h($class) . '"' : '')
        . '>';
}

/**
 * The short line under the company name in the header, e.g.
 * "Fast, secure and reliable". Set at /admin/branding.php; blank hides it
 * entirely rather than leaving a gap.
 */
function get_header_tagline(): string
{
    return trim(get_setting('header_tagline', 'Fast, secure and reliable'));
}

/**
 * The largest the company name is allowed to be in the header, in pixels.
 *
 * The header has a fixed amount of room: the logo, the name, and then the
 * navigation. A four-letter name and a twenty-four-letter one both have to
 * sit in it and stay readable, and a name long enough to collide with the
 * navigation would otherwise either wrap onto a second line or push the
 * menu off the edge.
 *
 * Working the size out from the length here, in PHP, rather than leaving
 * it to CSS, is deliberate: CSS cannot count the characters in a name, and
 * doing it in JavaScript would mean the header visibly resizes itself
 * after the page has already been drawn. This way the right size is in the
 * first paint. The stylesheet still shrinks it further on narrow screens,
 * so this is the ceiling, not the fixed size.
 *
 * 24 characters is the design target: anything longer still renders in
 * full, just at the smallest size.
 */
function brand_name_max_font_size(): int
{
    $length = mb_strlen(get_site_name());

    if ($length <= 8)  return 27;
    if ($length <= 12) return 24;
    if ($length <= 16) return 21;
    if ($length <= 20) return 18;
    if ($length <= 24) return 16;

    return 15;
}

/**
 * The smallest the company name may shrink to on a narrow screen.
 *
 * Up to the 24-character design target, 13px is comfortably readable and
 * the name fits a 320px screen at that size. A name longer than the target
 * still has to be shown in full rather than cut off half way through a
 * word, so it is allowed to go smaller instead. Shrinking is a poor
 * outcome; clipping a company's own name is a worse one.
 */
function brand_name_min_font_size(): int
{
    return mb_strlen(get_site_name()) <= 24 ? 13 : 11;
}

/**
 * A custom uploaded logo (set at /admin/branding.php) always wins. With
 * none uploaded, falls back to the active template's own default logo
 * mark (see includes/design.php), so switching templates can change the
 * logo too, without the admin having to upload anything.
 */
function get_logo_url(): ?string
{
    $path = get_setting('logo_path', '');
    if ($path !== '') {
        return $path;
    }
    return active_template_logo_url();
}

/**
 * Admin-replaceable site image (hero/illustration photos). An empty stored
 * value means "reset to default": set_setting() only upserts rows, it
 * can't remove one, so a blank string is how a reset is represented.
 */
function get_site_image(string $key, string $default): string
{
    $path = get_setting($key, '');
    return $path !== '' ? $path : $default;
}

/**
 * The two switches that control what the public tracking page shows,
 * managed by a super admin at /admin/tracking_display.php.
 *
 * Both default to on, so an existing site behaves exactly as before until
 * someone decides otherwise.
 */
function live_map_enabled(): bool
{
    return get_setting('live_map_enabled', '1') === '1';
}

function tracking_shows_logo(): bool
{
    return get_setting('tracking_show_logo', '1') === '1';
}

/**
 * Does this piece of copy talk about the live map?
 *
 * Used to keep the public site honest when a super admin switches the map
 * off: nothing should still be advertising a feature that is no longer
 * there. Matching on the words rather than on a fixed list of settings
 * means it also catches wording the site owner has edited themselves.
 */
function mentions_live_map(string $text): bool
{
    return (bool) preg_match('/\bmaps?\b|\bmapping\b|live location|interactive world/i', $text);
}

/**
 * A short piece of admin-editable copy, with a map-free alternative used
 * whenever the live map is off and the stored wording mentions it.
 *
 * Wording that says nothing about the map is always left exactly as the
 * owner wrote it, map on or off.
 */
function get_setting_map_aware(string $key, string $defaultOn, string $defaultOff): string
{
    $value = get_setting($key, $defaultOn);

    if (live_map_enabled() || !mentions_live_map($value)) {
        return $value;
    }

    return $defaultOff;
}

/**
 * The map-free version of a longer body of text, for the editorial
 * paragraphs on pages like About where replacing the whole thing would
 * throw away the owner's writing.
 *
 * Drops only the individual sentences that mention the map and keeps
 * everything else, including the paragraph breaks. Returns the text
 * untouched when the map is on.
 */
function map_free_body(string $text): string
{
    if (live_map_enabled() || !mentions_live_map($text)) {
        return $text;
    }

    // Paragraphs are stored either as a literal "\n\n" or as real blank
    // lines, so normalise before splitting (same as render_paragraphs()).
    $normalized = str_replace(['\\r\\n', '\\n', "\r\n"], "\n", $text);
    $paragraphs = preg_split('/\n\s*\n/', $normalized);

    $kept = [];
    foreach ($paragraphs as $paragraph) {
        // Split on sentence ends, keeping the punctuation with the sentence.
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($paragraph));
        $keptSentences = array_filter(
            $sentences,
            static fn(string $sentence) => !mentions_live_map($sentence)
        );
        $rebuilt = trim(implode(' ', $keptSentences));
        if ($rebuilt !== '') {
            $kept[] = $rebuilt;
        }
    }

    return implode("\n\n", $kept);
}

/**
 * Whether shipment insurance is offered at all. Turning it off hides every
 * insurance field and label across the admin panel, the public site and the
 * waybill, without deleting what is already recorded against a shipment.
 */
function insurance_enabled(): bool
{
    return get_setting('insurance_enabled', '1') === '1';
}

/** Whether the tracked page shows the full update history at the bottom. */
function tracking_shows_history(): bool
{
    return get_setting('tracking_show_history', '1') === '1';
}

/**
 * Whether staff are asked for latitude and longitude at all.
 *
 * The map always needs them, so while it is on they are always collected
 * and the switch below is irrelevant. It only has a say when the map is
 * off: normally the coordinate fields disappear with the map, but a site
 * that plans to switch the map back on can keep collecting them so no
 * shipment booked in the meantime is left without a position.
 *
 * Deliberately not a free choice in both directions. "Map on, coordinates
 * off" would leave the map with nothing to draw, so it is not offered.
 */
function coordinates_collected(): bool
{
    return live_map_enabled() || get_setting('collect_coordinates', '0') === '1';
}
