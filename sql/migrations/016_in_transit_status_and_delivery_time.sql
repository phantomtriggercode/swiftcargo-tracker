-- Adds the "In Transit" status with its own message, and an estimated
-- delivery time to go alongside the estimated delivery date.
--
-- Safe to run more than once: the column is added only when missing and
-- every row uses INSERT IGNORE, so nothing you have edited is overwritten.

-- ---------------------------------------------------------------
-- 1. Estimated delivery time.
--
-- The date stays in its own column rather than becoming a DATETIME, so a
-- shipment can have a delivery date with no time yet, which is the normal
-- case when it is first booked. The tracking page shows the time only when
-- one has been set.
-- ---------------------------------------------------------------
SET @db := DATABASE();

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'estimated_delivery_time') = 0,
  'ALTER TABLE shipments ADD COLUMN estimated_delivery_time TIME NULL DEFAULT NULL AFTER estimated_delivery',
  'SELECT "estimated_delivery_time already exists"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------
-- 2. "In Transit" as a pickable status.
--
-- It sits between Picked Up and En Route in the list. Staff can rename,
-- recolour, reorder or remove it at /admin/statuses.php like any other.
-- ---------------------------------------------------------------
INSERT IGNORE INTO shipment_statuses (name, badge_class, sort_order, is_protected) VALUES
('In Transit', 'badge-transit', 25, 0);

-- ---------------------------------------------------------------
-- 3. Its default message, used when staff leave the remark blank.
--    Editable afterwards at /admin/status_messages.php.
-- ---------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('status_message_in_transit', 'Your shipment is in transit and on its way to the next stop in our network.');
