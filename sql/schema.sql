-- SwiftCargo Tracker database schema
-- Import this file via Hostinger's phpMyAdmin (or `mysql -u user -p dbname < schema.sql`)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- Admins (staff who manage shipments from the admin panel)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin login: username = admin / password = ChangeMe123!
-- (hash generated with PHP password_hash — change this password immediately after first login)
INSERT INTO admins (username, password_hash, full_name) VALUES
('admin', '$2y$12$HYDffKZi7ppAiampmKCVU.Fm8Fk/S4.vKv.dvwoUYPRyvoXs.l9G.', 'Site Administrator')
ON DUPLICATE KEY UPDATE username = username;

-- ---------------------------------------------------------------
-- Shipments
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_number VARCHAR(20) NOT NULL UNIQUE,

  sender_name VARCHAR(150) NOT NULL,
  sender_address VARCHAR(255) NOT NULL,

  receiver_name VARCHAR(150) NOT NULL,
  receiver_email VARCHAR(190) NOT NULL,
  receiver_address VARCHAR(255) NOT NULL,

  package_description VARCHAR(255) NOT NULL,
  weight_kg DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  service_type ENUM('Standard','Express','Priority') NOT NULL DEFAULT 'Standard',

  status ENUM('Pending','Picked Up','In Transit','Out for Delivery','Delivered','Delayed','Exception')
    NOT NULL DEFAULT 'Pending',

  origin_label VARCHAR(150) NOT NULL,
  origin_lat DECIMAL(10,7) NOT NULL,
  origin_lng DECIMAL(10,7) NOT NULL,

  destination_label VARCHAR(150) NOT NULL,
  destination_lat DECIMAL(10,7) NOT NULL,
  destination_lng DECIMAL(10,7) NOT NULL,

  current_lat DECIMAL(10,7) NOT NULL,
  current_lng DECIMAL(10,7) NOT NULL,

  estimated_delivery DATE DEFAULT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_tracking_number (tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Tracking events (status history / timeline shown on the map)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tracking_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shipment_id INT UNSIGNED NOT NULL,
  status VARCHAR(50) NOT NULL,
  location_label VARCHAR(150) NOT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  event_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,

  CONSTRAINT fk_tracking_shipment FOREIGN KEY (shipment_id)
    REFERENCES shipments(id) ON DELETE CASCADE,
  INDEX idx_shipment_id (shipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Demo seed data (safe to delete once you add real shipments)
-- Route: Lagos, Nigeria -> London, UK
-- ---------------------------------------------------------------
INSERT INTO shipments (
  tracking_number, sender_name, sender_address, receiver_name, receiver_email, receiver_address,
  package_description, weight_kg, service_type, status,
  origin_label, origin_lat, origin_lng,
  destination_label, destination_lat, destination_lng,
  current_lat, current_lng, estimated_delivery
) VALUES (
  'SC1000000NG', 'Adaeze Okonkwo', '14 Marina Street, Lagos, Nigeria',
  'James Whitfield', 'james.demo@example.com', '22 Baker Street, London, UK',
  'Documents & electronics parcel', 2.40, 'Express', 'In Transit',
  'Lagos, Nigeria', 6.5244000, 3.3792000,
  'London, United Kingdom', 51.5072000, -0.1276000,
  27.9930000, -1.5000000, DATE_ADD(CURDATE(), INTERVAL 2 DAY)
) ON DUPLICATE KEY UPDATE tracking_number = tracking_number;

SET @sid = (SELECT id FROM shipments WHERE tracking_number = 'SC1000000NG');

INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note, event_time) VALUES
(@sid, 'Pending', 'Lagos, Nigeria', 6.5244000, 3.3792000, 'Shipment booked and label created.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(@sid, 'Picked Up', 'Lagos, Nigeria', 6.5244000, 3.3792000, 'Package picked up from sender.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@sid, 'In Transit', 'Algiers, Algeria', 36.7538000, 3.0588000, 'Departed regional hub, en route to destination country.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@sid, 'In Transit', 'Over the Mediterranean Sea', 27.9930000, -1.5000000, 'In transit to next facility.', NOW());

INSERT INTO shipments (
  tracking_number, sender_name, sender_address, receiver_name, receiver_email, receiver_address,
  package_description, weight_kg, service_type, status,
  origin_label, origin_lat, origin_lng,
  destination_label, destination_lat, destination_lng,
  current_lat, current_lng, estimated_delivery
) VALUES (
  'SC1000001NG', 'Global Traders Ltd', '5 Industrial Ave, Abuja, Nigeria',
  'Sophia Martins', 'sophia.demo@example.com', '900 Fifth Ave, New York, USA',
  'Textile sample box', 5.10, 'Standard', 'Delivered',
  'Abuja, Nigeria', 9.0765000, 7.3986000,
  'New York, USA', 40.7128000, -74.0060000,
  40.7128000, -74.0060000, DATE_SUB(CURDATE(), INTERVAL 1 DAY)
) ON DUPLICATE KEY UPDATE tracking_number = tracking_number;

SET @sid2 = (SELECT id FROM shipments WHERE tracking_number = 'SC1000001NG');

INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note, event_time) VALUES
(@sid2, 'Pending', 'Abuja, Nigeria', 9.0765000, 7.3986000, 'Shipment booked and label created.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@sid2, 'Picked Up', 'Abuja, Nigeria', 9.0765000, 7.3986000, 'Package picked up from sender.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@sid2, 'In Transit', 'Casablanca, Morocco', 33.5731000, -7.5898000, 'Departed regional hub.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@sid2, 'In Transit', 'JFK Airport, New York', 40.6413000, -73.7781000, 'Arrived at destination country facility.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@sid2, 'Out for Delivery', 'New York, USA', 40.7128000, -74.0060000, 'Out for delivery with courier.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@sid2, 'Delivered', 'New York, USA', 40.7128000, -74.0060000, 'Package delivered and signed for.', DATE_SUB(NOW(), INTERVAL 1 DAY));
