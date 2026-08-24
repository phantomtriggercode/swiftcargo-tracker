-- Migration 008: fix two preset themes whose button text failed WCAG AA
-- contrast against their primary color (white text at 3.56:1 and 3.74:1 —
-- both below the 4.5:1 minimum for normal-size text, so button labels
-- were genuinely hard to read). Verified computationally against every
-- theme's colors — see the commit this migration ships with.
--
-- Safe to run once via phpMyAdmin's Import tab. Only touches the two
-- affected preset rows; any theme a super admin has already customized
-- (a differently-named copy, or one they edited in place) is untouched.

SET NAMES utf8mb4;

UPDATE themes
SET color_primary = '#c2410c', color_primary_dark = '#9a3412'
WHERE name = 'Sunset Orange' AND is_preset = 1;

UPDATE themes
SET color_primary = '#0f766e', color_primary_dark = '#115e59'
WHERE name = 'Teal Logistics' AND is_preset = 1;
