-- Migration 005: courier/carrier selection on shipments + editable
-- tracking number prefix/suffix.
--
-- Safe to run once via phpMyAdmin's Import tab. Adds a `couriers` table
-- (managed from /admin/couriers.php — admins can rename, deactivate, or
-- add new carriers such as DHL, UPS, FedEx, USPS at any time), a nullable
-- `courier_id` column on `shipments`, widens `tracking_number` to fit a
-- custom prefix/suffix, and seeds the two new tracking-number-format
-- settings (edited from /admin/branding.php).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS couriers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO couriers (name, sort_order) VALUES
('DHL Express', 1),
('UPS', 2),
('FedEx', 3),
('USPS', 4),
('TNT Express', 5),
('Aramex', 6),
('DPD', 7),
('Royal Mail', 8);

ALTER TABLE shipments
  MODIFY COLUMN tracking_number VARCHAR(32) NOT NULL,
  ADD COLUMN courier_id INT UNSIGNED NULL DEFAULT NULL AFTER shipping_method,
  ADD CONSTRAINT fk_shipment_courier FOREIGN KEY (courier_id)
    REFERENCES couriers(id) ON DELETE SET NULL;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('tracking_number_prefix', 'SC'),
('tracking_number_suffix', '');
