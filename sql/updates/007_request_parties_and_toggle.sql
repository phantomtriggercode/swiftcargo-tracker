-- Sender and receiver contact details on public shipment requests, plus a
-- switch to turn the public "Ship Now" request form on or off.
--
-- Safe to run more than once: each column is added only if missing, and
-- the setting uses INSERT IGNORE.

SET @db := DATABASE();

-- Sender contact on a request.
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'sender_name') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN sender_name VARCHAR(150) NULL DEFAULT NULL AFTER phone',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'sender_phone') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN sender_phone VARCHAR(40) NULL DEFAULT NULL AFTER sender_name',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'sender_email') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN sender_email VARCHAR(190) NULL DEFAULT NULL AFTER sender_phone',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'sender_address') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN sender_address VARCHAR(255) NULL DEFAULT NULL AFTER sender_email',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Receiver contact on a request.
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'receiver_name') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN receiver_name VARCHAR(150) NULL DEFAULT NULL AFTER sender_address',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'receiver_phone') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN receiver_phone VARCHAR(40) NULL DEFAULT NULL AFTER receiver_name',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'receiver_email') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN receiver_email VARCHAR(190) NULL DEFAULT NULL AFTER receiver_phone',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipment_requests' AND COLUMN_NAME = 'receiver_address') = 0,
  'ALTER TABLE shipment_requests ADD COLUMN receiver_address VARCHAR(255) NULL DEFAULT NULL AFTER receiver_email',
  'SELECT 1'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- The public "Ship Now" request form, on by default. When off, the page is
-- unavailable and every "Ship Now" / "Request a Shipment" link is hidden.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('request_shipment_enabled', '1');
