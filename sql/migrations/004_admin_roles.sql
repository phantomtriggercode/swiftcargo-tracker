-- Migration 004: super-admin role + suspend/reactivate for regular admins.
--
-- Safe to run once via phpMyAdmin's Import tab. Adds two columns to
-- `admins` and promotes every existing admin account to super admin
-- (so nobody gets locked out of their own site by this migration) —
-- go to /admin/admins.php afterward to demote accounts that shouldn't
-- have full access.

SET NAMES utf8mb4;

ALTER TABLE admins
  ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER full_name,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_super_admin;

UPDATE admins SET is_super_admin = 1;
