-- Migration 007: limited theme picker for regular admins, and super-admin
-- account management (edit any admin's details, force a password change).
--
-- Safe to run once via phpMyAdmin's Import tab.

SET NAMES utf8mb4;

-- Regular (non-super-admin) accounts get a small, safe "Site Color" picker
-- at /admin/my_theme.php limited to whichever themes are flagged here —
-- they can activate one of these, but never edit or delete any theme.
-- Super admins keep full access to every theme via /admin/themes.php.
ALTER TABLE themes
  ADD COLUMN is_admin_selectable TINYINT(1) NOT NULL DEFAULT 0 AFTER is_preset;

UPDATE themes SET is_admin_selectable = 1 WHERE name = 'Classic Red';

INSERT INTO themes (
  name, style_key, is_active, is_preset, is_admin_selectable,
  color_primary, color_primary_dark, color_accent,
  color_ink, color_ink_soft, color_muted, color_border, color_bg_soft, color_white,
  color_ok, color_warn, color_danger
) VALUES (
  'Classic Green', 'classic', 0, 1, 1,
  '#15803d', '#166534', '#facc15',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f7f5', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'
);

-- Super admins can force an admin to set a new password on their next
-- login (e.g. after resetting it for them). The admin sees a plain
-- "set a new password to continue" prompt — nothing in the UI attributes
-- this to a super admin, matching how the rest of the super-admin role
-- stays out of a regular admin's own working screens.
ALTER TABLE admins
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
