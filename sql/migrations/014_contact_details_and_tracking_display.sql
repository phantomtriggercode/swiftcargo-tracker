-- Adds full contact details for both the sender and the receiver, and the
-- two super admin switches that control what the public tracking page shows.
--
-- Safe to run twice: each column is added only if it is not already there,
-- and the settings use INSERT IGNORE.

-- ---------------------------------------------------------------
-- Sender and receiver contact details.
--
-- Every new column is nullable with no default, so importing this cannot
-- fail on shipments that already exist. They simply have these fields
-- empty until someone edits them, and the tracking page leaves out any
-- line that has no value rather than printing a blank row.
-- ---------------------------------------------------------------
SET @db := DATABASE();

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'sender_email') = 0,
  'ALTER TABLE shipments ADD COLUMN sender_email VARCHAR(190) NULL DEFAULT NULL AFTER sender_name',
  'SELECT "sender_email already exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'sender_phone') = 0,
  'ALTER TABLE shipments ADD COLUMN sender_phone VARCHAR(40) NULL DEFAULT NULL AFTER sender_email',
  'SELECT "sender_phone already exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'receiver_phone') = 0,
  'ALTER TABLE shipments ADD COLUMN receiver_phone VARCHAR(40) NULL DEFAULT NULL AFTER receiver_email',
  'SELECT "receiver_phone already exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------
-- Tracking page display switches (super admin only, at
-- /admin/tracking_display.php).
--
-- live_map_enabled defaults to 1 so nothing changes for an existing site
-- until the owner decides to turn the map off.
--
-- tracking_show_logo defaults to 1 so the company logo appears above a
-- tracked shipment straight away, which is what it was added for.
-- ---------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('live_map_enabled', '1'),
('tracking_show_logo', '1');
