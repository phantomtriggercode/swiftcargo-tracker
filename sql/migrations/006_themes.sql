-- Migration 006: site-wide color themes, manageable only by super admins.
--
-- Safe to run once via phpMyAdmin's Import tab. Adds a `themes` table with
-- 10 seeded presets (6 distinct structural styles — classic, modern,
-- minimal, bold, corporate, dark-header — each paired with its own color
-- palette). "Classic Red" is seeded active, matching the site's existing
-- look exactly, so running this migration causes zero visible change until
-- a super admin picks something else at /admin/themes.php.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS themes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  style_key VARCHAR(30) NOT NULL DEFAULT 'classic',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_preset TINYINT(1) NOT NULL DEFAULT 0,

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

INSERT INTO themes (
  name, style_key, is_active, is_preset,
  color_primary, color_primary_dark, color_accent,
  color_ink, color_ink_soft, color_muted, color_border, color_bg_soft, color_white,
  color_ok, color_warn, color_danger
) VALUES
('Classic Red', 'classic', 1, 1,
  '#d40511', '#a80410', '#ffcc00',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f5f7', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Ocean Blue', 'modern', 0, 1,
  '#0369a1', '#075985', '#38bdf8',
  '#111827', '#4b5563', '#6b7280', '#e2e8f0', '#f1f5f9', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Emerald Freight', 'minimal', 0, 1,
  '#047857', '#065f46', '#34d399',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f6f5', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Sunset Orange', 'bold', 0, 1,
  '#ea580c', '#c2410c', '#fb923c',
  '#1c1917', '#57534e', '#78716c', '#e7e5e4', '#faf5f0', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Royal Purple', 'corporate', 0, 1,
  '#6d28d9', '#5b21b6', '#a78bfa',
  '#1e1b2e', '#4b5563', '#6b7280', '#e5e7eb', '#f6f4fb', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Midnight Navy', 'dark-header', 0, 1,
  '#1e3a8a', '#1e293b', '#60a5fa',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f5f7', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Charcoal Mono', 'minimal', 0, 1,
  '#111827', '#000000', '#9ca3af',
  '#111827', '#4b5563', '#6b7280', '#d1d5db', '#f3f4f6', '#ffffff',
  '#16a34a', '#b45309', '#dc2626'),
('Teal Logistics', 'modern', 0, 1,
  '#0d9488', '#0f766e', '#5eead4',
  '#111827', '#4b5563', '#6b7280', '#e2e8f0', '#f1f5f4', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Crimson Express', 'bold', 0, 1,
  '#be123c', '#9f1239', '#fb7185',
  '#18181b', '#52525b', '#71717a', '#e4e4e7', '#faf5f6', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Amber Cargo', 'corporate', 0, 1,
  '#b45309', '#92400e', '#fbbf24',
  '#1c1917', '#57534e', '#78716c', '#e7e5e4', '#faf7f0', '#ffffff',
  '#16a34a', '#b45309', '#dc2626');
