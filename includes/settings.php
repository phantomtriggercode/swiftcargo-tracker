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
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? $row['setting_value'] : null;
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

function get_logo_url(): ?string
{
    $path = get_setting('logo_path', '');
    return $path !== '' ? $path : null;
}

/**
 * Admin-replaceable site image (hero/illustration photos). An empty stored
 * value means "reset to default" — set_setting() only upserts rows, it
 * can't remove one, so a blank string is how a reset is represented.
 */
function get_site_image(string $key, string $default): string
{
    $path = get_setting($key, '');
    return $path !== '' ? $path : $default;
}
