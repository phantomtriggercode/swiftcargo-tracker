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
