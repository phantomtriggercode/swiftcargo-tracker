<?php
/**
 * Site-wide design system, split into two independent things a super
 * admin controls separately (see /admin/themes.php for colors,
 * /admin/templates.php for structural design):
 *
 *   - Color palettes (`color_palettes` table): just the 12 CSS color
 *     variables. Activating one only ever changes colors: never layout,
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
 *: never a template, never edit/delete anything.
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
    'classic'     => 'Classic: the site\'s original section order and look',
    'modern'      => 'Modern: soft rounded cards, reordered homepage sections, fade-up reveals',
    'minimal'     => 'Minimal: sharp corners, flat, no shadows, no motion',
    'bold'        => 'Bold: strong shadows, uppercase buttons, reordered sections, scale-in reveals',
    'corporate'   => 'Corporate: serif headings, restrained radius, formal hero',
    'dark-header' => 'Dark Header: dark navigation bar site-wide, reordered sections, slide-in reveals',
];

const TEMPLATE_ANIMATION_KEYS = [
    'none'     => 'None: content appears instantly',
    'fade'     => 'Fade: a gentle fade in',
    'fade-up'  => 'Fade Up: fades in while rising slightly',
    'scale-in' => 'Scale In: grows in from slightly smaller',
    'slide-in' => 'Slide In: slides in from the side',
];

/* ------------------------- Color palettes ------------------------- */

function get_active_palette(): array
{
    static $palette = null;
    if ($palette !== null) {
        return $palette;
    }

    // Defensive fallback, matches the site's original hardcoded colors.
    // Used whenever the query can't return a real row: the table doesn't
    // exist yet (new code deployed before migration 011 was run. This is
    // what a site-wide white screen right after an update almost always
    // means), or every palette somehow got deleted. This runs on every
    // public and admin page load, so it must never throw.
    try {
        $row = db()->query('SELECT * FROM color_palettes WHERE is_active = 1 LIMIT 1')->fetch();
    } catch (PDOException $e) {
        $row = null;
    }
    if (!$row) {
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

/* --------------------- Contrast-safe colour helpers ---------------------
 *
 * A palette is twelve colours somebody picked, and nothing stops two of
 * them being nearly the same: a pale yellow "primary", a "text" colour that
 * is almost the page background, a charcoal palette whose primary is the
 * same as its text. The templates put text on top of most of these
 * colours, so every one of those pairings is worked out here, from the
 * actual colours, using the WCAG contrast formula. Whatever palette is
 * active, text drawn with these variables stays readable.
 */

/** [r, g, b] (0-255) from "#rrggbb" or "#rgb". Falls back to black. */
function hex_to_rgb(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return [0, 0, 0];
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function rgb_to_hex(array $rgb): string
{
    return sprintf('#%02x%02x%02x', ...array_map(static fn($c) => max(0, min(255, (int) round($c))), $rgb));
}

/** WCAG relative luminance, 0 (black) to 1 (white). */
function color_luminance(string $hex): float
{
    $channels = array_map(static function ($c) {
        $c /= 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, hex_to_rgb($hex));
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/** WCAG contrast ratio between two colours, 1 to 21. */
function color_contrast(string $a, string $b): float
{
    $la = color_luminance($a);
    $lb = color_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** Mixes $a toward $b; $amount 0 is all $a, 1 is all $b. */
function color_mix(string $a, string $b, float $amount): string
{
    $ra = hex_to_rgb($a);
    $rb = hex_to_rgb($b);
    return rgb_to_hex([
        $ra[0] + ($rb[0] - $ra[0]) * $amount,
        $ra[1] + ($rb[1] - $ra[1]) * $amount,
        $ra[2] + ($rb[2] - $ra[2]) * $amount,
    ]);
}

/**
 * The best colour for text sitting on $background: whichever of the
 * candidates has the most contrast with it. White and near-black are
 * always among the candidates, so there is always a readable answer.
 */
function readable_text_on(string $background, array $prefer = []): string
{
    // A preferred colour (the palette's own text colour, say) wins when it
    // is comfortably readable, so the page keeps its intended look.
    foreach ($prefer as $candidate) {
        if (color_contrast($candidate, $background) >= 4.5) {
            return $candidate;
        }
    }
    $best = '#ffffff';
    $bestRatio = 0.0;
    foreach (['#ffffff', '#0b0f19'] as $candidate) {
        $ratio = color_contrast($candidate, $background);
        if ($ratio > $bestRatio) {
            $best = $candidate;
            $bestRatio = $ratio;
        }
    }
    return $best;
}

/**
 * The first candidate that reaches $minimum contrast on $background, or
 * the readable fallback. Used for coloured text (links, labels, accents)
 * so it keeps its colour when it can and turns plain when it cannot.
 */
function first_readable(array $candidates, string $background, float $minimum = 4.5): string
{
    foreach ($candidates as $candidate) {
        if (color_contrast($candidate, $background) >= $minimum) {
            return $candidate;
        }
    }
    return readable_text_on($background);
}

/**
 * The derived variables every template draws text with. Each "on-" colour
 * is the readable text colour for the matching background.
 */
function palette_contrast_vars(array $palette): array
{
    $primary = (string) $palette['color_primary'];
    $primaryDark = (string) $palette['color_primary_dark'];
    $accent = (string) $palette['color_accent'];
    $ink = (string) $palette['color_ink'];
    $page = (string) $palette['color_white'];
    $soft = (string) $palette['color_bg_soft'];

    // "Dark surface": the darker of text colour and page colour. On an
    // ordinary light palette that is the text colour; on a dark palette
    // (light text on a dark page) it is the page colour. Dark bands,
    // footers and image overlays use it, so they are dark either way.
    $dark = color_luminance($ink) <= color_luminance($page) ? $ink : $page;
    $light = $dark === $ink ? $page : $ink;
    // If the two are too alike to build a dark band from, fall back to a
    // deep version of the primary colour.
    if (color_contrast($dark, '#ffffff') < 7) {
        $dark = color_mix($primaryDark, '#000000', 0.55);
    }

    $vars = [
        '--on-brand'        => readable_text_on($primary, ['#ffffff']),
        '--on-brand-dark'   => readable_text_on($primaryDark, ['#ffffff']),
        '--on-accent'       => readable_text_on($accent, [$ink, '#ffffff']),
        '--on-ink'          => readable_text_on($ink, [$page, '#ffffff']),
        '--surface-dark'    => $dark,
        '--on-dark'         => readable_text_on($dark, [$light, '#ffffff']),
        '--on-dark-soft'    => color_mix(readable_text_on($dark, [$light, '#ffffff']), $dark, 0.28),
        '--on-dark-muted'   => color_mix(readable_text_on($dark, [$light, '#ffffff']), $dark, 0.42),
        '--text'            => readable_text_on($page, [$ink]),
        '--text-on-soft'    => readable_text_on($soft, [$ink]),
        // Coloured text on the page background (links, small labels).
        '--link'            => first_readable([$primary, $primaryDark, $ink], $page, 4.5),
        // Large or bold coloured text on the page (headings, numbers).
        '--brand-text'      => first_readable([$primary, $primaryDark, $ink], $page, 3.0),
        '--accent-text'     => first_readable([$accent, $primary, $primaryDark, $ink], $page, 3.0),
        // Coloured highlight on a dark surface.
        '--accent-on-dark'  => first_readable([$accent, color_mix($primary, '#ffffff', 0.35), '#ffffff'], $dark, 3.0),
        '--brand-on-dark'   => first_readable([$primary, $accent, color_mix($primary, '#ffffff', 0.4), '#ffffff'], $dark, 3.0),
        // Coloured highlight on a primary-coloured surface.
        '--accent-on-brand' => first_readable([$accent, readable_text_on($primary, ['#ffffff'])], $primary, 3.0),
        // Soft tints for icon tiles and badges, always light enough for
        // the primary colour on top of them to stay readable.
        '--brand-tint'      => color_mix($primary, $page, 0.88),
        '--brand-tint-2'    => color_mix($primary, $page, 0.76),
        '--accent-tint'     => color_mix($accent, $page, 0.8),
    ];

    // rgb triplets for translucent versions: rgba(var(--brand-red-rgb), .2)
    foreach (['--brand-red-rgb' => $primary, '--brand-red-dark-rgb' => $primaryDark, '--brand-yellow-rgb' => $accent,
              '--ink-rgb' => $ink, '--white-rgb' => $page, '--surface-dark-rgb' => $dark] as $name => $hex) {
        $vars[$name] = implode(',', hex_to_rgb($hex));
    }

    return $vars;
}

/** Renders the <style> tag overriding :root color variables for the active palette. Place right after the main stylesheet <link>. */
function palette_style_tag(): string
{
    $palette = get_active_palette();
    $css = '';
    foreach (PALETTE_COLOR_FIELDS as $column => $meta) {
        $css .= $meta['css_var'] . ':' . h($palette[$column]) . ';';
    }
    foreach (palette_contrast_vars($palette) as $name => $value) {
        $css .= $name . ':' . h($value) . ';';
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
 * one. There is no undo: a deliberate, manual action from
 * /admin/themes.php, never automatic.
 */
function delete_palette(int $id): array
{
    $palette = get_palette($id);
    if (!$palette) {
        return ['ok' => false, 'error' => 'Color palette not found.'];
    }
    if ($palette['is_active']) {
        return ['ok' => false, 'error' => "Can't delete the active color palette: activate a different one first."];
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

    // Same rationale as get_active_palette()'s fallback above: must never
    // throw, since this also runs on every page load.
    try {
        $row = db()->query('SELECT * FROM templates WHERE is_active = 1 LIMIT 1')->fetch();
    } catch (PDOException $e) {
        $row = null;
    }
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
        return ['ok' => false, 'error' => "Can't delete the active template: activate a different one first."];
    }
    $total = (int) db()->query('SELECT COUNT(*) FROM templates')->fetchColumn();
    if ($total <= 1) {
        return ['ok' => false, 'error' => "Can't delete the last remaining template."];
    }
    $stmt = db()->prepare('DELETE FROM templates WHERE id = ?');
    $stmt->execute([$id]);
    return ['ok' => true, 'error' => null];
}
