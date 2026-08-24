-- Migration 003: admin profile — login by email, password reset via email.
--
-- Safe to run once on your existing SwiftCargo Tracker database via
-- phpMyAdmin's Import tab. Only adds new columns to `admins`; your
-- existing admin account and password are untouched.

SET NAMES utf8mb4;

ALTER TABLE admins
  ADD COLUMN email VARCHAR(150) NULL UNIQUE AFTER username,
  ADD COLUMN reset_token VARCHAR(64) NULL AFTER password_hash,
  ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token;
