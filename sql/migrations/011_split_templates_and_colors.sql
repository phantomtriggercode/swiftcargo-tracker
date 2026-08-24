-- Migration 011: split the combined `themes` table (color + structural
-- style in one row) into two independent things a super admin controls
-- separately:
--   - `color_palettes` — just the 12 colors. Activating one never touches
--     the page layout.
--   - `templates` — the structural design (layout_key selects section
--     order/hero treatment in style.css, animation_key selects the
--     scroll-reveal animation style, logo_path is that template's own
--     default logo, used when no custom logo is uploaded under Branding).
--     Activating one never touches colors.
--
-- Every existing theme's colors become a color palette (same name, same
-- is_active/is_preset/is_admin_selectable). The 6 distinct structural
-- styles that existed (classic/modern/minimal/bold/corporate/dark-header)
-- become the 6 templates, and whichever one matches the currently-active
-- theme's style is marked active — so this migration causes no visible
-- change the moment you run it.
--
-- The old `themes` table is renamed (not dropped) to `themes_legacy_backup`
-- so nothing is lost; safe to drop that yourself later once you've
-- confirmed everything looks right.
--
-- Safe to run once via phpMyAdmin's Import tab.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS color_palettes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_preset TINYINT(1) NOT NULL DEFAULT 0,
  is_admin_selectable TINYINT(1) NOT NULL DEFAULT 0,

  color_primary VARCHAR(9) NOT NULL,
  color_primary_dark VARCHAR(9) NOT NULL,
  color_accent VARCHAR(9) NOT NULL,
  color_ink VARCHAR(9) NOT NULL,
  color_ink_soft VARCHAR(9) NOT NULL,
  color_muted VARCHAR(9) NOT NULL,
  color_border VARCHAR(9) NOT NULL,
  color_bg_soft VARCHAR(9) NOT NULL,
  color_white VARCHAR(9) NOT NULL,
  color_ok VARCHAR(9) NOT NULL,
  color_warn VARCHAR(9) NOT NULL,
  color_danger VARCHAR(9) NOT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  layout_key VARCHAR(30) NOT NULL DEFAULT 'classic',
  animation_key VARCHAR(30) NOT NULL DEFAULT 'fade',
  logo_path VARCHAR(255) NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_preset TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO color_palettes (
  name, is_active, is_preset, is_admin_selectable,
  color_primary, color_primary_dark, color_accent,
  color_ink, color_ink_soft, color_muted, color_border, color_bg_soft, color_white,
  color_ok, color_warn, color_danger
)
SELECT
  name, is_active, is_preset, is_admin_selectable,
  color_primary, color_primary_dark, color_accent,
  color_ink, color_ink_soft, color_muted, color_border, color_bg_soft, color_white,
  color_ok, color_warn, color_danger
FROM themes;

INSERT INTO templates (name, layout_key, animation_key, logo_path, is_active, is_preset) VALUES
('Classic', 'classic', 'fade', '/assets/images/template-logos/classic.svg', 0, 1),
('Modern', 'modern', 'fade-up', '/assets/images/template-logos/modern.svg', 0, 1),
('Minimal', 'minimal', 'none', '/assets/images/template-logos/minimal.svg', 0, 1),
('Bold', 'bold', 'scale-in', '/assets/images/template-logos/bold.svg', 0, 1),
('Corporate', 'corporate', 'fade', '/assets/images/template-logos/corporate.svg', 0, 1),
('Dark Header', 'dark-header', 'slide-in', '/assets/images/template-logos/dark-header.svg', 0, 1);

UPDATE templates SET is_active = 1
WHERE layout_key = (SELECT style_key FROM themes WHERE is_active = 1 LIMIT 1);

-- Defensive fallback in case nothing matched above.
UPDATE templates SET is_active = 1
WHERE layout_key = 'classic' AND (SELECT COUNT(*) FROM (SELECT id FROM templates WHERE is_active = 1) x) = 0;

RENAME TABLE themes TO themes_legacy_backup;
