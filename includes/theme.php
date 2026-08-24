<?php
/**
 * Site-wide color theme system. Exactly one row in `themes` is active at
 * a time; its colors are injected as CSS variable overrides on every page
 * via theme_style_tag(), and its style_key selects a structural design
 * variant (radius/shadow/typography/header treatment) defined in
 * style.css under html[data-theme-style="..."]. Only super admins can
 * manage this — see /admin/themes.php and require_super_admin().
 *
 * Requires config/db.php to already be loaded.
 */

/**
 * Maps each theme color column to the CSS variable it overrides and a
 * human label, in the order shown on the edit form. Single source of
 * truth for both the color-picker form and the generated <style> tag.
 */
const THEME_COLOR_FIELDS = [
    'color_primary'      => ['label' => 'Primary / Brand',       'css_var' => '--brand-red'],
    'color_primary_dark' => ['label' => 'Primary (hover/dark)',  'css_var' => '--brand-red-dark'],
    'color_accent'       => ['label' => 'Accent',                'css_var' => '--brand-yellow'],
    'color_ink'          => ['label' => 'Text (main)',           'css_var' => '--ink'],
    'color_ink_soft'     => ['label' => 'Text (soft)',           'css_var' => '--ink-soft'],
    'color_muted'        => ['label' => 'Text (muted)',          'css_var' => '--muted'],
    'color_border'       => ['label' => 'Borders',               'css_var' => '--border'],
    'color_bg_soft'      => ['label' => 'Soft background',       'css_var' => '--bg-soft'],
    'color_white'        => ['label' => 'Page background',       'css_var' => '--white'],
    'color_ok'           => ['label' => 'Success',                'css_var' => '--ok'],
    'color_warn'         => ['label' => 'Warning',                'css_var' => '--warn'],
    'color_danger'       => ['label' => 'Danger',                 'css_var' => '--danger'],
];

const THEME_STYLE_KEYS = [
    'classic'     => 'Classic — the site\'s original look (rounded cards, light header)',
    'modern'      => 'Modern — softer, more rounded, deeper shadows',
    'minimal'     => 'Minimal — sharp corners, flat, no shadows',
    'bold'        => 'Bold — strong shadows, uppercase buttons',
    'corporate'   => 'Corporate — serif headings, restrained radius',
    'dark-header' => 'Dark Header — dark navigation bar site-wide',
];

function get_active_theme(): array
{
    static $theme = null;
    if ($theme !== null) {
        return $theme;
    }

    $row = db()->query('SELECT * FROM themes WHERE is_active = 1 LIMIT 1')->fetch();
    if (!$row) {
        // Defensive fallback (e.g. migration not run yet, or every theme
        // somehow deleted) — matches the site's original hardcoded colors
        // so nothing breaks if this table is empty.
        $row = [
            'style_key' => 'classic',
            'color_primary' => '#d40511', 'color_primary_dark' => '#a80410', 'color_accent' => '#ffcc00',
            'color_ink' => '#111827', 'color_ink_soft' => '#4b5563', 'color_muted' => '#6b7280',
            'color_border' => '#e5e7eb', 'color_bg_soft' => '#f4f5f7', 'color_white' => '#ffffff',
            'color_ok' => '#16a34a', 'color_warn' => '#d97706', 'color_danger' => '#dc2626',
        ];
    }
    $theme = $row;
    return $theme;
}

function get_all_themes(): array
{
    return db()->query('SELECT * FROM themes ORDER BY is_active DESC, name ASC')->fetchAll();
}

function get_theme(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM themes WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Renders the <style> tag that overrides :root color variables for the active theme. Place right after the main stylesheet <link>. */
function theme_style_tag(): string
{
    $theme = get_active_theme();
    $css = '';
    foreach (THEME_COLOR_FIELDS as $column => $meta) {
        $css .= $meta['css_var'] . ':' . h($theme[$column]) . ';';
    }
    return '<style id="active-theme-vars">:root{' . $css . '}</style>';
}

/** The active theme's style_key, for the data-theme-style attribute on <html>. */
function active_theme_style_key(): string
{
    return get_active_theme()['style_key'] ?? 'classic';
}

function activate_theme(int $id): bool
{
    $theme = get_theme($id);
    if (!$theme) {
        return false;
    }
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('UPDATE themes SET is_active = 0');
    $stmt = $pdo->prepare('UPDATE themes SET is_active = 1 WHERE id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
    return true;
}

/**
 * Permanently deletes a theme. Blocks deleting the active theme (so the
 * site never ends up with none active) and the last remaining theme.
 * There is no undo — this is a deliberate, manual action taken from
 * /admin/themes.php, never automatic.
 */
function delete_theme(int $id): array
{
    $theme = get_theme($id);
    if (!$theme) {
        return ['ok' => false, 'error' => 'Theme not found.'];
    }
    if ($theme['is_active']) {
        return ['ok' => false, 'error' => "Can't delete the active theme — activate a different one first."];
    }
    $total = (int) db()->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    if ($total <= 1) {
        return ['ok' => false, 'error' => "Can't delete the last remaining theme."];
    }
    $stmt = db()->prepare('DELETE FROM themes WHERE id = ?');
    $stmt->execute([$id]);
    return ['ok' => true, 'error' => null];
}
