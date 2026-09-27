-- Admin-managed shipment statuses, plus three more display switches.
--
-- Safe to run more than once: the table and the settings use IF NOT EXISTS
-- / INSERT IGNORE, and the column change is only applied when the column is
-- still the old fixed list.

-- ---------------------------------------------------------------
-- 1. Statuses become rows instead of a fixed list baked into the table.
--
-- shipments.status was an ENUM, so adding a status like "In Transit" meant
-- editing the database structure by hand. It becomes a plain VARCHAR and
-- the allowed values live in their own table, which staff can manage at
-- /admin/statuses.php.
--
-- Nothing is lost: every existing shipment keeps the exact status text it
-- already had, because the ENUM stored those words and VARCHAR keeps them.
-- ---------------------------------------------------------------
SET @db := DATABASE();

SET @sql := (SELECT IF(
  (SELECT DATA_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'status') = 'enum',
  'ALTER TABLE shipments MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT ''Pending''',
  'SELECT "shipments.status is already a free text column"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS shipment_statuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  -- Which colour the badge uses on the dashboard and tracking page.
  badge_class VARCHAR(30) NOT NULL DEFAULT 'badge-pending',
  sort_order INT NOT NULL DEFAULT 0,
  -- A protected status is one the code itself relies on, so it cannot be
  -- deleted. Only "Pending" is protected: a new shipment is created with it.
  is_protected TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO shipment_statuses (name, badge_class, sort_order, is_protected) VALUES
('Pending',              'badge-pending',   10, 1),
('Picked Up',            'badge-pending',   20, 0),
('En Route',             'badge-transit',   30, 0),
('Customs Clearance',    'badge-hold',      40, 0),
('Insurance Clearance',  'badge-hold',      50, 0),
('Out for Delivery',     'badge-transit',   60, 0),
('Delivered',            'badge-delivered', 70, 0),
('On Hold',              'badge-hold',      80, 0),
('Delayed',              'badge-alert',     90, 0),
('Exception',            'badge-alert',    100, 0);

-- ---------------------------------------------------------------
-- 2. More switches for the public tracking page (super admin only, at
--    /admin/tracking_display.php). All default to on, so an existing site
--    looks exactly as it does now until someone changes them.
-- ---------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('insurance_enabled', '1'),
('tracking_show_history', '1');
