<?php
/**
 * Site-wide design system, split into two independent things a super
 * admin controls separately (see /admin/themes.php for colors,
 * /admin/templates.php for structural design):
 *
 *   - Color palettes (`color_palettes` table): just the 12 CSS color
 *     variables. Activating one only ever changes colors — never layout,
 *     animation, or logo.
 *   - Templates (`templates` table): the structural design. layout_key
 *     selects a section-order/hero/typography treatment defined in
 *     style.css under html[data-template="..."]; animation_key selects
 *     the scroll-reveal animation style (assets/js/reveal.js adds
 *     .is-visible to [data-reveal] elements, style.css decides what that
 *     transition looks like per animation_key); logo_path is that
 *     template's own default logo, used on every page whenever no custom
 *     logo has been uploaded under Branding.
 *
 * Regular (non-super-admin) accounts can only activate one of a small
 * fixed set of color palettes (is_admin_selectable) at /admin/my_theme.php
 * — never a template, never edit/delete anything.
 *
 * Requires config/db.php to already be loaded.
 */

const PALETTE_COLOR_FIELDS = [
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

const TEMPLATE_LAYOUT_KEYS = [
    'classic'     => 'Classic — the site\'s original section order and look',
    'modern'      => 'Modern — soft rounded cards, reordered homepage sections, fade-up reveals',
    'minimal'     => 'Minimal — sharp corners, flat, no shadows, no motion',
    'bold'        => 'Bold — strong shadows, uppercase buttons, reordered sections, scale-in reveals',
    'corporate'   => 'Corporate — serif headings, restrained radius, formal hero',
    'dark-header' => 'Dark Header — dark navigation bar site-wide, reordered sections, slide-in reveals',
];

const TEMPLATE_ANIMATION_KEYS = [
    'none'     => 'None — content appears instantly',
    'fade'     => 'Fade — a gentle fade in',
    'fade-up'  => 'Fade Up — fades in while rising slightly',
    'scale-in' => 'Scale In — grows in from slightly smaller',
    'slide-in' => 'Slide In — slides in from the side',
];

/* ------------------------- Color palettes ------------------------- */

function get_active_palette(): array
{
    static $palette = null;
    if ($palette !== null) {
        return $palette;
    }

    $row = db()->query('SELECT * FROM color_palettes WHERE is_active = 1 LIMIT 1')->fetch();
    if (!$row) {
        // Defensive fallback (migration not run yet, or every palette
        // somehow deleted) — matches the site's original hardcoded colors.
        $row = [
            'color_primary' => '#d40511', 'color_primary_dark' => '#a80410', 'color_accent' => '#ffcc00',
            'color_ink' => '#111827', 'color_ink_soft' => '#4b5563', 'color_muted' => '#6b7280',
            'color_border' => '#e5e7eb', 'color_bg_soft' => '#f4f5f7', 'color_white' => '#ffffff',
            'color_ok' => '#16a34a', 'color_warn' => '#d97706', 'color_danger' => '#dc2626',
        ];
    }
    $palette = $row;
    return $palette;
}

function get_all_palettes(): array
{
    return db()->query('SELECT * FROM color_palettes ORDER BY is_active DESC, name ASC')->fetchAll();
}

/** The small, fixed set of palettes a regular admin may switch between at /admin/my_theme.php. */
function get_admin_selectable_palettes(): array
{
    return db()->query('SELECT * FROM color_palettes WHERE is_admin_selectable = 1 ORDER BY name ASC')->fetchAll();
}

function get_palette(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM color_palettes WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Renders the <style> tag overriding :root color variables for the active palette. Place right after the main stylesheet <link>. */
function palette_style_tag(): string
{
    $palette = get_active_palette();
    $css = '';
    foreach (PALETTE_COLOR_FIELDS as $column => $meta) {
        $css .= $meta['css_var'] . ':' . h($palette[$column]) . ';';
    }
    return '<style id="active-palette-vars">:root{' . $css . '}</style>';
}

function activate_palette(int $id): bool
{
    $palette = get_palette($id);
    if (!$palette) {
        return false;
    }
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('UPDATE color_palettes SET is_active = 0');
    $stmt = $pdo->prepare('UPDATE color_palettes SET is_active = 1 WHERE id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
    return true;
}

/**
 * Permanently deletes a color palette. Blocks deleting the active one
 * (so the site never ends up with none active) and the last remaining
 * one. There is no undo — a deliberate, manual action from
 * /admin/themes.php, never automatic.
 */
function delete_palette(int $id): array
{
    $palette = get_palette($id);
    if (!$palette) {
        return ['ok' => false, 'error' => 'Color palette not found.'];
    }
    if ($palette['is_active']) {
        return ['ok' => false, 'error' => "Can't delete the active color palette — activate a different one first."];
    }
    $total = (int) db()->query('SELECT COUNT(*) FROM color_palettes')->fetchColumn();
    if ($total <= 1) {
        return ['ok' => false, 'error' => "Can't delete the last remaining color palette."];
    }
    $stmt = db()->prepare('DELETE FROM color_palettes WHERE id = ?');
    $stmt->execute([$id]);
    return ['ok' => true, 'error' => null];
}

/* ----------------------------- Templates ----------------------------- */

function get_active_template(): array
{
    static $template = null;
    if ($template !== null) {
        return $template;
    }

    $row = db()->query('SELECT * FROM templates WHERE is_active = 1 LIMIT 1')->fetch();
    if (!$row) {
        $row = ['layout_key' => 'classic', 'animation_key' => 'fade', 'logo_path' => null];
    }
    $template = $row;
    return $template;
}

function get_all_templates(): array
{
    return db()->query('SELECT * FROM templates ORDER BY is_active DESC, name ASC')->fetchAll();
}

function get_template(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM templates WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** The active template's layout_key, for the data-template attribute on <html>. */
function active_template_layout_key(): string
{
    return get_active_template()['layout_key'] ?? 'classic';
}

/** The active template's animation_key, for the data-animation attribute on <html>. */
function active_template_animation_key(): string
{
    return get_active_template()['animation_key'] ?? 'fade';
}

/** The active template's own default logo (used by get_logo_url() when no custom logo is uploaded). */
function active_template_logo_url(): ?string
{
    $path = get_active_template()['logo_path'] ?? null;
    return $path !== null && $path !== '' ? $path : null;
}

function activate_template(int $id): bool
{
    $template = get_template($id);
    if (!$template) {
        return false;
    }
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('UPDATE templates SET is_active = 0');
    $stmt = $pdo->prepare('UPDATE templates SET is_active = 1 WHERE id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
    return true;
}

/**
 * Permanently deletes a template. Blocks deleting the active one and the
 * last remaining one, same rationale as delete_palette(). No undo.
 */
function delete_template(int $id): array
{
    $template = get_template($id);
    if (!$template) {
        return ['ok' => false, 'error' => 'Template not found.'];
    }
    if ($template['is_active']) {
        return ['ok' => false, 'error' => "Can't delete the active template — activate a different one first."];
    }
    $total = (int) db()->query('SELECT COUNT(*) FROM templates')->fetchColumn();
    if ($total <= 1) {
        return ['ok' => false, 'error' => "Can't delete the last remaining template."];
    }
    $stmt = db()->prepare('DELETE FROM templates WHERE id = ?');
    $stmt->execute([$id]);
    return ['ok' => true, 'error' => null];
}
