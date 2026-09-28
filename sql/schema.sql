-- Complete database for this site: every table, every column, every
-- default, plus the starter data a brand new site needs (one admin login,
-- the carrier list, the colour palettes, the design templates, all site
-- copy, and the calculator rates).
--
-- HOW TO USE IT
--
--   Fresh install    Import this one file and nothing else. Skip the
--                    sql/migrations/ folder entirely.
--
--   Existing site    Import this one file as well. It is written to be
--                    re-imported safely over a database that already has
--                    real data in it:
--
--                      * Tables that already exist are left exactly as
--                        they are (CREATE TABLE IF NOT EXISTS).
--                      * Tables that do not exist yet are created.
--                      * Columns added by later versions are added to
--                        existing tables, each one checked first, so
--                        re-running changes nothing.
--                      * Settings you have never had are added with
--                        their defaults. Settings you have already set
--                        keep the value you set (INSERT IGNORE).
--                      * Starter data (the default admin, carriers,
--                        palettes, templates, statuses, demo shipments)
--                        is added ONLY when that table is completely
--                        empty. A site with real data gets none of it
--                        back, and in particular the default admin
--                        password is never restored.
--
--   Import through your host's phpMyAdmin, or with:
--       mysql -u USER -p DATABASE < schema.sql
--
-- The numbered files under sql/migrations/ do the same work one change at
-- a time. They exist for anyone who would rather apply a single specific
-- change than re-import the whole file; you do not need them if you
-- import this.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- Bringing an older database up to date.
--
-- The CREATE TABLE IF NOT EXISTS statements further down leave an
-- existing table exactly as it is, which is what makes this file safe to
-- re-import, but it also means a table created by an earlier version
-- never gains the columns added since. This section adds them, one at a
-- time, each one checked first.
--
-- It runs before everything else because the starter data below refers to
-- columns that some of these statements add: on a database old enough to
-- be missing one, the import would otherwise stop there.
--
-- Every statement here checks two things: that the table exists at all
-- (on a fresh install it does not yet, and is about to be created
-- complete), and that the column is actually missing. So this section is
-- a no-op both on a brand new database and on one that is already up to
-- date, and can be run any number of times.
--
-- One caveat worth knowing: a column added here is added without the
-- foreign key the same column has on a freshly created table. Nothing in
-- the site depends on that constraint, and adding it to a table that may
-- already hold rows that violate it would fail the import.
--
-- If you would rather apply one specific change than re-import this whole
-- file, the numbered files in sql/migrations/ do the same work
-- individually.
-- ---------------------------------------------------------------
SET @db := DATABASE();

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'email') = 0,
  'ALTER TABLE admins ADD COLUMN email VARCHAR(150) NULL UNIQUE AFTER username',
  'SELECT "admins.email is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'reset_token') = 0,
  'ALTER TABLE admins ADD COLUMN reset_token VARCHAR(64) NULL AFTER password_hash',
  'SELECT "admins.reset_token is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'reset_token_expires') = 0,
  'ALTER TABLE admins ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token',
  'SELECT "admins.reset_token_expires is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'is_super_admin') = 0,
  'ALTER TABLE admins ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER full_name',
  'SELECT "admins.is_super_admin is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'is_active') = 0,
  'ALTER TABLE admins ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_super_admin',
  'SELECT "admins.is_active is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'must_change_password') = 0,
  'ALTER TABLE admins ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
  'SELECT "admins.must_change_password is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'color_palettes') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'color_palettes' AND COLUMN_NAME = 'is_admin_selectable') = 0,
  'ALTER TABLE color_palettes ADD COLUMN is_admin_selectable TINYINT(1) NOT NULL DEFAULT 0 AFTER is_preset',
  'SELECT "color_palettes.is_admin_selectable is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'shipping_method') = 0,
  'ALTER TABLE shipments ADD COLUMN shipping_method ENUM(''Air'',''Sea'',''Land'') NOT NULL DEFAULT ''Air'' AFTER service_type',
  'SELECT "shipments.shipping_method is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'land_method') = 0,
  'ALTER TABLE shipments ADD COLUMN land_method ENUM(''Van'',''Trailer'',''Train'') NULL DEFAULT NULL AFTER shipping_method',
  'SELECT "shipments.land_method is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'packaging_type') = 0,
  'ALTER TABLE shipments ADD COLUMN packaging_type ENUM(''Box'',''Crate'',''Pallet'',''Loose Cargo'',''Full Container Load (FCL)'',''Less Than Container Load (LCL)'',''Envelope/Document'') NOT NULL DEFAULT ''Box'' AFTER package_description',
  'SELECT "shipments.packaging_type is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'dimensions') = 0,
  'ALTER TABLE shipments ADD COLUMN dimensions VARCHAR(100) NULL DEFAULT NULL AFTER weight_kg',
  'SELECT "shipments.dimensions is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'insured') = 0,
  'ALTER TABLE shipments ADD COLUMN insured TINYINT(1) NOT NULL DEFAULT 0 AFTER dimensions',
  'SELECT "shipments.insured is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'insurance_value') = 0,
  'ALTER TABLE shipments ADD COLUMN insurance_value DECIMAL(10,2) NULL DEFAULT NULL AFTER insured',
  'SELECT "shipments.insurance_value is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'courier_id') = 0,
  'ALTER TABLE shipments ADD COLUMN courier_id INT UNSIGNED NULL DEFAULT NULL AFTER shipping_method',
  'SELECT "shipments.courier_id is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'payment_type') = 0,
  'ALTER TABLE shipments ADD COLUMN payment_type ENUM(''Full Payment'', ''Partial Payment'', ''Payment on Arrival'') NOT NULL DEFAULT ''Full Payment'' AFTER insurance_value',
  'SELECT "shipments.payment_type is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'payment_price') = 0,
  'ALTER TABLE shipments ADD COLUMN payment_price DECIMAL(10,2) NULL DEFAULT NULL AFTER payment_type',
  'SELECT "shipments.payment_price is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'payment_initial_amount') = 0,
  'ALTER TABLE shipments ADD COLUMN payment_initial_amount DECIMAL(10,2) NULL DEFAULT NULL AFTER payment_price',
  'SELECT "shipments.payment_initial_amount is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'payment_amount_paid') = 0,
  'ALTER TABLE shipments ADD COLUMN payment_amount_paid DECIMAL(10,2) NULL DEFAULT NULL AFTER payment_initial_amount',
  'SELECT "shipments.payment_amount_paid is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'sender_email') = 0,
  'ALTER TABLE shipments ADD COLUMN sender_email VARCHAR(190) NULL DEFAULT NULL AFTER sender_name',
  'SELECT "shipments.sender_email is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'sender_phone') = 0,
  'ALTER TABLE shipments ADD COLUMN sender_phone VARCHAR(40) NULL DEFAULT NULL AFTER sender_email',
  'SELECT "shipments.sender_phone is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'receiver_phone') = 0,
  'ALTER TABLE shipments ADD COLUMN receiver_phone VARCHAR(40) NULL DEFAULT NULL AFTER receiver_email',
  'SELECT "shipments.receiver_phone is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'estimated_delivery_time') = 0,
  'ALTER TABLE shipments ADD COLUMN estimated_delivery_time TIME NULL DEFAULT NULL AFTER estimated_delivery',
  'SELECT "shipments.estimated_delivery_time is already present"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- The shipment status used to be a fixed list baked into the table, which
-- meant adding a status like "In Transit" required editing the database
-- structure by hand. It is a plain text column now, and the allowed
-- values live in shipment_statuses, which staff manage at
-- /admin/statuses.php. Nothing is lost in the conversion: every shipment
-- keeps the exact status text it already had.
SET @sql := (SELECT IF(
  (SELECT DATA_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'status') = 'enum',
  'ALTER TABLE shipments MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT ''Pending''',
  'SELECT "shipments.status is already a free text column"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Coordinates became optional when the live map became something a super
-- admin can switch off. While it is off there is nothing sensible to
-- store, and these columns used to be NOT NULL, which left only 0,0 as a
-- stand-in: a real place in the Atlantic off the coast of Africa, where
-- every shipment booked during that window would have appeared the moment
-- the map was switched back on. NULL means "not recorded", and the
-- tracking page leaves those points off the map rather than inventing one.
--
-- Widening a column that is already nullable changes nothing and touches
-- no existing value, so this runs unconditionally.
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'shipments') = 1,
  'ALTER TABLE shipments
     MODIFY COLUMN origin_lat DECIMAL(10,7) NULL DEFAULT NULL,
     MODIFY COLUMN origin_lng DECIMAL(10,7) NULL DEFAULT NULL,
     MODIFY COLUMN destination_lat DECIMAL(10,7) NULL DEFAULT NULL,
     MODIFY COLUMN destination_lng DECIMAL(10,7) NULL DEFAULT NULL,
     MODIFY COLUMN current_lat DECIMAL(10,7) NULL DEFAULT NULL,
     MODIFY COLUMN current_lng DECIMAL(10,7) NULL DEFAULT NULL',
  'SELECT "shipments will be created complete"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tracking_events') = 1,
  'ALTER TABLE tracking_events
     MODIFY COLUMN lat DECIMAL(10,7) NULL DEFAULT NULL,
     MODIFY COLUMN lng DECIMAL(10,7) NULL DEFAULT NULL',
  'SELECT "tracking_events will be created complete"'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------------------------------------------------------------
-- Admins (staff who manage shipments from the admin panel)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  reset_token VARCHAR(64) NULL,
  reset_token_expires DATETIME NULL,
  full_name VARCHAR(150) NOT NULL,
  is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The starter login for a brand new site: username `admin`, password
-- `ChangeMe123!`. This first account can create, suspend and delete every
-- other account.
--
-- It is created with must_change_password set, so the very first sign-in
-- cannot reach any page until a new password has been set. A password
-- printed in a setup guide is public knowledge the moment the site is
-- online, and this is what makes it useless past that first sign-in.
--
-- Added ONLY when the admins table is completely empty. That is part of
-- what makes this file safe to re-import: on a site that already has real
-- accounts this does nothing at all, and an account with a publicly known
-- password is never put back.
SET @seed_admins := (SELECT COUNT(*) FROM admins);

INSERT INTO admins (username, password_hash, full_name, is_super_admin, is_active, must_change_password)
SELECT * FROM (
  SELECT 'admin' AS c1, '$2y$12$HYDffKZi7ppAiampmKCVU.Fm8Fk/S4.vKv.dvwoUYPRyvoXs.l9G.' AS c2, 'Site Administrator' AS c3, 1 AS c4, 1 AS c5, 1 AS c6
) AS seed
WHERE @seed_admins = 0;

-- ---------------------------------------------------------------
-- Couriers / carriers (managed from /admin/couriers.php: admins can
-- rename, deactivate, or add new carriers like DHL, UPS, FedEx, USPS)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS couriers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Added only when the carrier list is empty, so a site that has renamed
-- or removed carriers does not get the originals back on a re-import.
SET @seed_couriers := (SELECT COUNT(*) FROM couriers);

INSERT INTO couriers (name, sort_order)
SELECT * FROM (
  SELECT 'DHL Express' AS c1, 1 AS c2
  UNION ALL SELECT 'UPS', 2
  UNION ALL SELECT 'FedEx', 3
  UNION ALL SELECT 'USPS', 4
  UNION ALL SELECT 'TNT Express', 5
  UNION ALL SELECT 'Aramex', 6
  UNION ALL SELECT 'DPD', 7
  UNION ALL SELECT 'Royal Mail', 8
) AS seed
WHERE @seed_couriers = 0;

-- ---------------------------------------------------------------
-- Shipments
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_number VARCHAR(32) NOT NULL UNIQUE,

  -- Contact details for both ends of the shipment. Only the name is
  -- required: email, phone and address are nullable so a shipment can be
  -- created with whatever is known at the time, and the tracking page
  -- leaves out any line that is empty rather than printing a blank row.
  sender_name VARCHAR(150) NOT NULL,
  sender_email VARCHAR(190) NULL DEFAULT NULL,
  sender_phone VARCHAR(40) NULL DEFAULT NULL,
  sender_address VARCHAR(255) NOT NULL,

  receiver_name VARCHAR(150) NOT NULL,
  receiver_email VARCHAR(190) NOT NULL,
  receiver_phone VARCHAR(40) NULL DEFAULT NULL,
  receiver_address VARCHAR(255) NOT NULL,

  package_description VARCHAR(255) NOT NULL,
  packaging_type ENUM(
    'Box','Crate','Pallet','Loose Cargo','Full Container Load (FCL)','Less Than Container Load (LCL)','Envelope/Document'
  ) NOT NULL DEFAULT 'Box',
  weight_kg DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  dimensions VARCHAR(100) DEFAULT NULL,

  service_type ENUM('Regular','Express') NOT NULL DEFAULT 'Regular',
  shipping_method ENUM('Air','Sea','Land') NOT NULL DEFAULT 'Air',
  land_method ENUM('Van','Trailer','Train') NULL DEFAULT NULL,
  courier_id INT UNSIGNED NULL DEFAULT NULL,

  insured TINYINT(1) NOT NULL DEFAULT 0,
  insurance_value DECIMAL(10,2) NULL DEFAULT NULL,

  payment_type ENUM('Full Payment', 'Partial Payment', 'Payment on Arrival') NOT NULL DEFAULT 'Full Payment',
  payment_price DECIMAL(10,2) NULL DEFAULT NULL,
  payment_initial_amount DECIMAL(10,2) NULL DEFAULT NULL,
  payment_amount_paid DECIMAL(10,2) NULL DEFAULT NULL,

  -- Free text rather than a fixed list, because staff manage the available
  -- statuses themselves at /admin/statuses.php. The allowed values live in
  -- the shipment_statuses table below.
  status VARCHAR(50) NOT NULL DEFAULT 'Pending',

  origin_label VARCHAR(150) NOT NULL,
  -- Coordinates are optional: with the live map switched off staff are not
  -- asked for them, and NULL records that honestly. Storing 0,0 instead
  -- would put the shipment in the Atlantic the moment the map came back on.
  origin_lat DECIMAL(10,7) NULL DEFAULT NULL,
  origin_lng DECIMAL(10,7) NULL DEFAULT NULL,

  destination_label VARCHAR(150) NOT NULL,
  destination_lat DECIMAL(10,7) NULL DEFAULT NULL,
  destination_lng DECIMAL(10,7) NULL DEFAULT NULL,

  current_lat DECIMAL(10,7) NULL DEFAULT NULL,
  current_lng DECIMAL(10,7) NULL DEFAULT NULL,

  estimated_delivery DATE DEFAULT NULL,
  -- Kept separate from the date rather than folded into a DATETIME, so a
  -- shipment can have a delivery date with no time yet, which is the normal
  -- case when it is first booked.
  estimated_delivery_time TIME NULL DEFAULT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_tracking_number (tracking_number),
  CONSTRAINT fk_shipment_courier FOREIGN KEY (courier_id)
    REFERENCES couriers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Tracking events (status history / timeline shown on the map)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tracking_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shipment_id INT UNSIGNED NOT NULL,
  status VARCHAR(50) NOT NULL,
  location_label VARCHAR(150) NOT NULL,
  lat DECIMAL(10,7) NULL DEFAULT NULL,
  lng DECIMAL(10,7) NULL DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  event_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,

  CONSTRAINT fk_tracking_shipment FOREIGN KEY (shipment_id)
    REFERENCES shipments(id) ON DELETE CASCADE,
  INDEX idx_shipment_id (shipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- The statuses a shipment can be given, managed by staff at
-- /admin/statuses.php. Adding a row here (for example "In Transit") makes
-- it selectable when adding a tracking update, with no code change.
--
-- is_protected marks a status the code itself depends on, so the admin
-- panel refuses to delete it. Only "Pending" is protected, because that is
-- the status every new shipment is created with.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipment_statuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  badge_class VARCHAR(30) NOT NULL DEFAULT 'badge-pending',
  sort_order INT NOT NULL DEFAULT 0,
  is_protected TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Added only when the status list is empty. A site that has added its own
-- statuses, or deleted ones it does not use, keeps exactly the list it has.
SET @seed_shipment_statuses := (SELECT COUNT(*) FROM shipment_statuses);

INSERT INTO shipment_statuses (name, badge_class, sort_order, is_protected)
SELECT * FROM (
  SELECT 'Pending' AS c1, 'badge-pending' AS c2, 10 AS c3, 1 AS c4
  UNION ALL SELECT 'Picked Up', 'badge-pending', 20, 0
  UNION ALL SELECT 'In Transit', 'badge-transit', 25, 0
  UNION ALL SELECT 'En Route', 'badge-transit', 30, 0
  UNION ALL SELECT 'Customs Clearance', 'badge-hold', 40, 0
  UNION ALL SELECT 'Insurance Clearance', 'badge-hold', 50, 0
  UNION ALL SELECT 'Out for Delivery', 'badge-transit', 60, 0
  UNION ALL SELECT 'Delivered', 'badge-delivered', 70, 0
  UNION ALL SELECT 'On Hold', 'badge-hold', 80, 0
  UNION ALL SELECT 'Delayed', 'badge-alert', 90, 0
  UNION ALL SELECT 'Exception', 'badge-alert', 100, 0
) AS seed
WHERE @seed_shipment_statuses = 0;

-- ---------------------------------------------------------------
-- Site content + calculator rate settings (key/value, editable from
-- the admin panel at /admin/content.php and /admin/rates.php).
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value LONGTEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('home_hero_title', 'Ship anywhere. Track everything. Live.'),
('home_hero_lead', 'SwiftCargo moves freight and parcels across the United States and worldwide, and shows you exactly where they are on a live map, with an email sent to your receiver on every single update.'),
('stat_countries', '195+'),
('stat_ontime', '98.6%'),
('stat_support', '24/7'),
('stat_delivered', '1.2M+'),
('about_title', 'About SwiftCargo'),
('about_lead', 'A US-based freight and parcel carrier built around one idea: you should always know exactly where your shipment is.'),
('about_body', 'SwiftCargo was built to give shippers and receivers complete visibility into every shipment, from the moment it is booked to the moment it is signed for. Every parcel and freight load is tracked through our network of hubs, with live map positioning and automatic email alerts sent the instant a shipment''s status changes.\n\nWe move shipments by air, sea, and land across all 50 states and to destinations worldwide, offering both Regular and Express service levels, optional shipment insurance, and support for freight packaging including pallets, crates, and full or partial container loads.'),
('contact_intro', 'Questions about a shipment, a quote, or our services? Reach our support team any time.'),
('contact_phone', '+1 (800) 555-0199'),
('contact_email', 'support@swiftcargo.test'),
('contact_address', '4500 Freight Way, Dallas, TX 75201, United States'),
('footer_tagline', 'Reliable freight and parcel shipping across the United States and worldwide, with real-time tracking and instant email alerts, so you always know where your shipment is.'),
('footer_bottom_note', ''),
('countries_intro', 'SwiftCargo ships to every country in the world. Wherever your shipment is headed, we can get it there.'),
('countries_list', 'Afghanistan\nAlbania\nAlgeria\nAndorra\nAngola\nAntigua and Barbuda\nArgentina\nArmenia\nAustralia\nAustria\nAzerbaijan\nBahamas\nBahrain\nBangladesh\nBarbados\nBelarus\nBelgium\nBelize\nBenin\nBhutan\nBolivia\nBosnia and Herzegovina\nBotswana\nBrazil\nBrunei\nBulgaria\nBurkina Faso\nBurundi\nCabo Verde\nCambodia\nCameroon\nCanada\nCentral African Republic\nChad\nChile\nChina\nColombia\nComoros\nCongo (Republic of the)\nCongo (Democratic Republic of the)\nCosta Rica\nCroatia\nCuba\nCyprus\nCzechia\nDenmark\nDjibouti\nDominica\nDominican Republic\nEcuador\nEgypt\nEl Salvador\nEquatorial Guinea\nEritrea\nEstonia\nEswatini\nEthiopia\nFiji\nFinland\nFrance\nGabon\nGambia\nGeorgia\nGermany\nGhana\nGreece\nGrenada\nGuatemala\nGuinea\nGuinea-Bissau\nGuyana\nHaiti\nHonduras\nHungary\nIceland\nIndia\nIndonesia\nIran\nIraq\nIreland\nIsrael\nItaly\nJamaica\nJapan\nJordan\nKazakhstan\nKenya\nKiribati\nKosovo\nKuwait\nKyrgyzstan\nLaos\nLatvia\nLebanon\nLesotho\nLiberia\nLibya\nLiechtenstein\nLithuania\nLuxembourg\nMadagascar\nMalawi\nMalaysia\nMaldives\nMali\nMalta\nMarshall Islands\nMauritania\nMauritius\nMexico\nMicronesia\nMoldova\nMonaco\nMongolia\nMontenegro\nMorocco\nMozambique\nMyanmar\nNamibia\nNauru\nNepal\nNetherlands\nNew Zealand\nNicaragua\nNiger\nNigeria\nNorth Korea\nNorth Macedonia\nNorway\nOman\nPakistan\nPalau\nPanama\nPapua New Guinea\nParaguay\nPeru\nPhilippines\nPoland\nPortugal\nQatar\nRomania\nRussia\nRwanda\nSaint Kitts and Nevis\nSaint Lucia\nSaint Vincent and the Grenadines\nSamoa\nSan Marino\nSao Tome and Principe\nSaudi Arabia\nSenegal\nSerbia\nSeychelles\nSierra Leone\nSingapore\nSlovakia\nSlovenia\nSolomon Islands\nSomalia\nSouth Africa\nSouth Korea\nSouth Sudan\nSpain\nSri Lanka\nSudan\nSuriname\nSweden\nSwitzerland\nSyria\nTaiwan\nTajikistan\nTanzania\nThailand\nTimor-Leste\nTogo\nTonga\nTrinidad and Tobago\nTunisia\nTurkey\nTurkmenistan\nTuvalu\nUganda\nUkraine\nUnited Arab Emirates\nUnited Kingdom\nUnited States\nUruguay\nUzbekistan\nVanuatu\nVatican City\nVenezuela\nVietnam\nYemen\nZambia\nZimbabwe'),
('rate_base_fee', '15.00'),
('rate_price_per_kg', '3.50'),
('rate_air_multiplier', '1.8'),
('rate_sea_multiplier', '1.0'),
('rate_land_multiplier', '1.2'),
('rate_express_multiplier', '1.5'),
('rate_insurance_percent', '2.5'),
('tracking_number_prefix', 'SC'),
('tracking_number_suffix', ''),
('status_message_pending', 'Your shipment has been booked and a shipping label has been created. We are preparing it for pickup.'),
('status_message_picked_up', 'Your shipment has been picked up and is now in our network.'),
('status_message_in_transit', 'Your shipment is in transit and on its way to the next stop in our network.'),
('status_message_en_route', 'Your shipment is on the move and heading toward its next stop.'),
('status_message_customs_clearance', 'Your shipment has arrived at a customs checkpoint and is being cleared for onward transport. This can take 1-2 business days.'),
('status_message_insurance_clearance', 'Your shipment is undergoing an insurance review before continuing its journey.'),
('status_message_out_for_delivery', 'Your shipment is out for delivery and should arrive today.'),
('status_message_delivered', 'Your shipment has been delivered. Thank you for shipping with us.'),
('status_message_on_hold', 'Your shipment has been placed on hold. Our team is looking into it and will update you shortly.'),
('status_message_delayed', 'Your shipment has been delayed. We apologize for the inconvenience and are working to get it moving again.'),
('status_message_exception', 'There was an exception with your shipment that needs attention. Our team has been notified and will follow up.'),
('privacy_title', 'Privacy Policy'),
('privacy_lead', 'How we collect, use, and protect the information you share with us.'),
('privacy_body', 'This Privacy Policy explains what information we collect when you use this website or ship with us, why we collect it, and how it is handled.\n\nInformation we collect: when you request a shipment, track a package, or contact us, we collect the details you provide: names, email addresses, phone numbers, physical addresses, and information about the shipment itself (contents description, weight, dimensions, and declared value if insured). We do not ask for or store payment card numbers on this site.\n\nHow we use it: we use this information to create and manage shipments, send tracking and delivery status emails, respond to inquiries, calculate shipping estimates, and keep records required to operate our shipping service. Contact information tied to a shipment is also used to send status update emails as the shipment moves through our network.\n\nCookies and site data: this site uses a single session cookie to keep you logged in to the admin panel and to remember short-lived confirmation messages. We do not use third-party advertising or tracking cookies.\n\nSharing: we do not sell your personal information. We may share shipment details with the carrier or courier handling a given shipment, and with service providers who help us operate this site (for example, our email delivery provider), solely to provide the service you requested.\n\nData retention: we keep shipment and contact records for as long as needed to provide our services, meet legal or accounting obligations, and resolve disputes.\n\nSecurity: we use reasonable technical and organizational measures to protect the information we hold, including encrypted connections and access controls on our admin systems. No method of transmission or storage is 100% secure, and we cannot guarantee absolute security.\n\nYour choices: you can contact us at any time to ask what information we hold about you, request a correction, or request deletion where we are not required to keep it for legal or operational reasons.\n\nChanges to this policy: we may update this policy from time to time. The version posted here is always the current one.\n\nContact us: if you have questions about this policy, reach out using the details on our Contact page.'),
('terms_title', 'Terms of Service'),
('terms_lead', 'The terms that apply when you use our site and shipping services.'),
('terms_body', 'By using this website or requesting a shipment through us, you agree to the terms below.\n\nOur services: this site lets you request a shipment quote, and lets senders, receivers, and our staff track a shipment''s status and location. Submitting a shipment request is a request for service, not a confirmed booking. Our team follows up to confirm final details, pricing, and pickup arrangements before a shipment is created.\n\nAccuracy of information: you are responsible for providing accurate sender, receiver, and package information. Delays or delivery issues caused by incomplete or incorrect information are not our responsibility.\n\nEstimates and pricing: cost estimates shown on this site are calculated from the details you provide and are subject to confirmation by our team before a shipment is booked. Final pricing may differ based on verified weight, dimensions, destination, or service level.\n\nProhibited shipments: you agree not to ship anything illegal, hazardous, or prohibited by applicable customs, postal, or transport regulations. We may refuse or cancel a shipment that we reasonably believe violates this.\n\nInsurance: declared-value insurance is optional and, where selected, is subject to the terms communicated to you at the time of booking. We are not liable for loss or damage beyond any insurance coverage in place for a given shipment.\n\nLimitation of liability: to the fullest extent permitted by law, we are not liable for indirect, incidental, or consequential damages arising from the use of this site or our shipping services, beyond the value of the shipment (and any insurance coverage) involved.\n\nIntellectual property: the content, design, and branding on this site belong to us or our licensors and may not be copied or reused without permission.\n\nChanges: we may update these terms from time to time. Continued use of this site after a change means you accept the updated terms.\n\nContact us: questions about these terms can be sent using the details on our Contact page.'),
-- Live chat (Tawk.to). Off and unconfigured on a fresh install; the site
-- owner connects their own Tawk.to account at /admin/live_chat.php. These
-- rows are seeded only so the settings table shows what is available, -- the site behaves identically whether they exist or not.
-- What the public tracking page shows. Both are managed by a super admin
-- at /admin/tracking_display.php. The map is on by default; turning it off
-- also removes every coordinate from the page and from the tracking API.
('insurance_enabled', '1'),
('tracking_show_history', '1'),
('live_map_enabled', '1'),
-- Keep asking staff for coordinates even when the map is off, so nothing
-- booked during that window is left without a position. Off by default, so
-- the coordinate fields simply follow the map.
('collect_coordinates', '0'),
('tracking_show_logo', '1'),
('live_chat_enabled', '0'),
('live_chat_property_id', ''),
('live_chat_widget_id', ''),
-- Search engines. Written at /admin/seo.php. All blank or off, so
-- importing this changes nothing about how the site currently appears in
-- search results.
--
-- seo_noindex_site defaults to '0', meaning visible. Defaulting it the
-- other way would quietly take a working site out of Google, and leaving
-- it switched on after launch is the single most common reason a new site
-- never appears there at all.
('seo_default_description', ''),
('seo_share_image', ''),
('seo_noindex_site', '0'),
('seo_google_verification', ''),
('seo_google_verification_file', ''),
('seo_bing_verification', ''),
-- The short line under the company name in the header, shown in capitals.
-- Blank hides it rather than leaving a gap.
('header_tagline', 'Fast, secure and reliable');

-- ---------------------------------------------------------------
-- Site-wide color palettes (managed only by super admins, at
-- /admin/themes.php, the "Colors" page). Exactly one row is active
-- at a time; its colors are injected as CSS variable overrides on
-- every page. Colors are fully independent from the structural
-- design, activating a palette never touches layout (see
-- `templates` below). Presets are just as deletable as any palette a
-- super admin creates, is_preset is informational only.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS color_palettes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_preset TINYINT(1) NOT NULL DEFAULT 0,
  is_admin_selectable TINYINT(1) NOT NULL DEFAULT 0,

  color_primary VARCHAR(9) NOT NULL,
  color_primary_dark VARCHAR(9) NOT NULL,
  color_accent VARCHAR(9) NOT NULL,
  color_ink VARCHAR(9) NOT NULL,
  color_ink_soft VARCHAR(9) NOT NULL,
  color_muted VARCHAR(9) NOT NULL,
  color_border VARCHAR(9) NOT NULL,
  color_bg_soft VARCHAR(9) NOT NULL,
  color_white VARCHAR(9) NOT NULL,
  color_ok VARCHAR(9) NOT NULL,
  color_warn VARCHAR(9) NOT NULL,
  color_danger VARCHAR(9) NOT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Added only when no palettes exist yet. Without this a re-import would
-- duplicate all eleven presets and activate a second copy of the first
-- one, which would look like the site had reset its own colours.
SET @seed_color_palettes := (SELECT COUNT(*) FROM color_palettes);

INSERT INTO color_palettes (name, is_active, is_preset, is_admin_selectable, color_primary, color_primary_dark, color_accent, color_ink, color_ink_soft, color_muted, color_border, color_bg_soft, color_white, color_ok, color_warn, color_danger)
SELECT * FROM (
  SELECT 'Classic Red' AS c1, 1 AS c2, 1 AS c3, 1 AS c4, '#d40511' AS c5, '#a80410' AS c6, '#ffcc00' AS c7, '#111827' AS c8, '#4b5563' AS c9, '#6b7280' AS c10, '#e5e7eb' AS c11, '#f4f5f7' AS c12, '#ffffff' AS c13, '#16a34a' AS c14, '#d97706' AS c15, '#dc2626' AS c16
  UNION ALL SELECT 'Classic Green', 0, 1, 1, '#15803d', '#166534', '#facc15', '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f7f5', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Ocean Blue', 0, 1, 0, '#0369a1', '#075985', '#38bdf8', '#111827', '#4b5563', '#6b7280', '#e2e8f0', '#f1f5f9', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Emerald Freight', 0, 1, 0, '#047857', '#065f46', '#34d399', '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f6f5', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Sunset Orange', 0, 1, 0, '#c2410c', '#9a3412', '#fb923c', '#1c1917', '#57534e', '#78716c', '#e7e5e4', '#faf5f0', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Royal Purple', 0, 1, 0, '#6d28d9', '#5b21b6', '#a78bfa', '#1e1b2e', '#4b5563', '#6b7280', '#e5e7eb', '#f6f4fb', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Midnight Navy', 0, 1, 0, '#1e3a8a', '#1e293b', '#60a5fa', '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f5f7', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Charcoal Mono', 0, 1, 0, '#111827', '#000000', '#9ca3af', '#111827', '#4b5563', '#6b7280', '#d1d5db', '#f3f4f6', '#ffffff', '#16a34a', '#b45309', '#dc2626'
  UNION ALL SELECT 'Teal Logistics', 0, 1, 0, '#0f766e', '#115e59', '#5eead4', '#111827', '#4b5563', '#6b7280', '#e2e8f0', '#f1f5f4', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Crimson Express', 0, 1, 0, '#be123c', '#9f1239', '#fb7185', '#18181b', '#52525b', '#71717a', '#e4e4e7', '#faf5f6', '#ffffff', '#16a34a', '#d97706', '#dc2626'
  UNION ALL SELECT 'Amber Cargo', 0, 1, 0, '#b45309', '#92400e', '#fbbf24', '#1c1917', '#57534e', '#78716c', '#e7e5e4', '#faf7f0', '#ffffff', '#16a34a', '#b45309', '#dc2626'
) AS seed
WHERE @seed_color_palettes = 0;

-- ---------------------------------------------------------------
-- Structural design templates (managed only by super admins, at
-- /admin/templates.php). Exactly one row is active at a time.
-- layout_key selects the homepage section order/hero treatment/
-- corner+shadow style defined in style.css; animation_key selects
-- the scroll-reveal animation style; logo_path is that template's
-- own default logo mark, used site-wide whenever no custom logo is
-- uploaded under Branding. Activating a template never touches
-- colors, see `color_palettes` above.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  layout_key VARCHAR(30) NOT NULL DEFAULT 'classic',
  animation_key VARCHAR(30) NOT NULL DEFAULT 'fade',
  logo_path VARCHAR(255) NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_preset TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Added only when no templates exist yet, for the same reason as the
-- palettes above: a duplicate set with a second active row would fight
-- with the one already chosen.
SET @seed_templates := (SELECT COUNT(*) FROM templates);

INSERT INTO templates (name, layout_key, animation_key, logo_path, is_active, is_preset)
SELECT * FROM (
  SELECT 'Classic' AS c1, 'classic' AS c2, 'fade' AS c3, '/assets/images/template-logos/classic.svg' AS c4, 1 AS c5, 1 AS c6
  UNION ALL SELECT 'Modern', 'modern', 'fade-up', '/assets/images/template-logos/modern.svg', 0, 1
  UNION ALL SELECT 'Minimal', 'minimal', 'none', '/assets/images/template-logos/minimal.svg', 0, 1
  UNION ALL SELECT 'Bold', 'bold', 'scale-in', '/assets/images/template-logos/bold.svg', 0, 1
  UNION ALL SELECT 'Corporate', 'corporate', 'fade', '/assets/images/template-logos/corporate.svg', 0, 1
  UNION ALL SELECT 'Dark Header', 'dark-header', 'slide-in', '/assets/images/template-logos/dark-header.svg', 0, 1
) AS seed
WHERE @seed_templates = 0;

-- ---------------------------------------------------------------
-- Public "request a shipment" submissions.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipment_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  ship_from VARCHAR(255) NOT NULL,
  ship_to VARCHAR(255) NOT NULL,
  package_description VARCHAR(255) NOT NULL,
  weight_kg DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  dimensions VARCHAR(100) DEFAULT NULL,
  packaging_type VARCHAR(60) NOT NULL DEFAULT 'Box',
  shipping_method VARCHAR(20) NOT NULL DEFAULT 'Air',
  land_method VARCHAR(20) DEFAULT NULL,
  service_type VARCHAR(20) NOT NULL DEFAULT 'Regular',
  insured TINYINT(1) NOT NULL DEFAULT 0,
  insurance_value DECIMAL(10,2) DEFAULT NULL,
  preferred_date DATE DEFAULT NULL,
  preferred_time VARCHAR(20) DEFAULT NULL,
  pickup_method ENUM('Pickup','Drop-off') NOT NULL DEFAULT 'Pickup',
  estimated_cost DECIMAL(10,2) DEFAULT NULL,
  status ENUM('New','Contacted','Converted','Closed') NOT NULL DEFAULT 'New',
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Login attempt tracking, for rate-limiting/lockout on the admin
-- login form (see includes/security.php).
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  identifier VARCHAR(190) NOT NULL,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_ip_time (ip_address, attempted_at),
  INDEX idx_identifier_time (identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Audit trail of sensitive admin actions, viewable by super admins at
-- /admin/activity_log.php (see includes/auth.php's log_admin_activity()).
-- admin_id is nullable and ON DELETE SET NULL so a log entry survives
-- even after the admin account that made it is deleted.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  admin_name VARCHAR(150) NOT NULL,
  action VARCHAR(60) NOT NULL,
  details VARCHAR(500) NOT NULL DEFAULT '',
  ip_address VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_created (created_at),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Rate limiting (see includes/security.php).
--
-- One row per (bucket, actor, window) with a counter, rather than one row
-- per request, so the table stays small and the write stays cheap even
-- while an attack is in progress, which is exactly when it is written to
-- most.
--
--   bucket         what is being limited: 'login', 'contact', 'page'.
--   actor          who: normally an address, sometimes an address plus an
--                  account. Hashed if it would overflow the key.
--   blocked_until  unix timestamp; 0 means not currently blocked.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket VARCHAR(40) NOT NULL,
  actor VARCHAR(190) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (bucket, actor, window_start),
  INDEX idx_updated (updated_at),
  INDEX idx_blocked (bucket, actor, blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Requests that were refused outright because they were shaped like an
-- attack rather than like a visit. Kept so the site owner can see what is
-- being tried, and so a pattern (the same address probing all week) is
-- visible rather than invisible. The application clears out old rows; no
-- cron job is involved.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL DEFAULT '',
  reason VARCHAR(60) NOT NULL DEFAULT '',
  request_path VARCHAR(255) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_created (created_at),
  INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Per-page search engine settings, written by staff at /admin/seo.php:
-- the title and description a page shows in a search result, and the
-- keyword it is trying to rank for.
--
-- A page with no row here still renders perfectly well. The site falls
-- back to the page's own heading plus the site name for the title, and to
-- the site-wide description, so nothing has to be filled in for the site
-- to work.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seo_pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- Matches the keys in seo_pages() in includes/seo.php: home, track,
  -- request, services, countries, about, contact, privacy, terms.
  page_key VARCHAR(40) NOT NULL UNIQUE,

  meta_title VARCHAR(255) NOT NULL DEFAULT '',
  meta_description VARCHAR(320) NOT NULL DEFAULT '',
  -- The one phrase this page is trying to rank for.
  focus_keyword VARCHAR(120) NOT NULL DEFAULT '',
  -- Supporting phrases, comma separated as they were typed.
  meta_keywords VARCHAR(500) NOT NULL DEFAULT '',

  -- What a link to this page looks like when it is shared in a message.
  og_title VARCHAR(255) NOT NULL DEFAULT '',
  og_description VARCHAR(320) NOT NULL DEFAULT '',

  -- Overrides the address search engines are told is the real one for
  -- this page. Blank means "the page's own address", which is right
  -- almost always.
  canonical_path VARCHAR(255) NOT NULL DEFAULT '',
  -- 1 keeps the page online but out of search results and out of the
  -- sitemap.
  noindex TINYINT(1) NOT NULL DEFAULT 0,

  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Demo seed data.
--
-- Two example shipments with a full history, so a brand new site has
-- something to look at on the dashboard and something to type into the
-- tracking box. Delete them once you add real shipments.
--
-- Added ONLY when the shipments table is completely empty. On a site with
-- real shipments in it this whole section does nothing, which is what
-- lets this file be re-imported without two demo parcels reappearing in
-- the middle of a real list.
-- ---------------------------------------------------------------
SET @seed_demo := (SELECT COUNT(*) FROM shipments);

-- Shipment 1: domestic, Land/Trailer, Express, currently en route
INSERT INTO shipments (
  tracking_number, sender_name, sender_address, receiver_name, receiver_email, receiver_address,
  package_description, packaging_type, weight_kg, dimensions,
  service_type, shipping_method, land_method, insured, insurance_value, status,
  origin_label, origin_lat, origin_lng,
  destination_label, destination_lat, destination_lng,
  current_lat, current_lng, estimated_delivery
)
SELECT * FROM (
  SELECT
    'SC1000000US' AS c1,
   'Wells & Turner Logistics Group' AS c2,
   '1420 Alameda St, Los Angeles, CA 90021, USA' AS c3,
   'Michael Chen' AS c4,
   'michael.demo@example.com' AS c5,
   '245 Park Avenue, New York, NY 10167, USA' AS c6,
   'Consumer electronics, palletized' AS c7,
   'Pallet' AS c8,
   180.00 AS c9,
   '48in x 40in x 60in' AS c10,
   'Express' AS c11,
   'Land' AS c12,
   'Trailer' AS c13,
   1 AS c14,
   5000.00 AS c15,
   'En Route' AS c16,
   'Los Angeles, CA, USA' AS c17,
   34.0522000 AS c18,
   -118.2437000 AS c19,
   'New York, NY, USA' AS c20,
   40.7128000 AS c21,
   -74.0060000 AS c22,
   39.7392000 AS c23,
   -104.9903000 AS c24,
   DATE_ADD(CURDATE(), INTERVAL 2 DAY) AS c25
) AS seed
WHERE @seed_demo = 0;

SET @sid = (SELECT id FROM shipments WHERE tracking_number = 'SC1000000US');

INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note, event_time)
SELECT * FROM (
  SELECT @sid AS c1, 'Pending' AS c2, 'Los Angeles, CA, USA' AS c3, 34.0522000 AS c4, -118.2437000 AS c5, 'Shipment booked and label created.' AS c6, DATE_SUB(NOW(), INTERVAL 3 DAY) AS c7
  UNION ALL SELECT @sid, 'Picked Up', 'Los Angeles, CA, USA', 34.0522000, -118.2437000, 'Pallet picked up from sender facility.', DATE_SUB(NOW(), INTERVAL 2 DAY)
  UNION ALL SELECT @sid, 'En Route', 'Las Vegas, NV, USA', 36.1699000, -115.1398000, 'Departed regional hub, en route to destination.', DATE_SUB(NOW(), INTERVAL 1 DAY)
  UNION ALL SELECT @sid, 'En Route', 'Denver, CO, USA', 39.7392000, -104.9903000, 'In transit to next facility.', NOW()
) AS seed
WHERE @seed_demo = 0 AND @sid IS NOT NULL;

-- Shipment 2: international, Sea/Crate, Regular, delivered
INSERT INTO shipments (
  tracking_number, sender_name, sender_address, receiver_name, receiver_email, receiver_address,
  package_description, packaging_type, weight_kg, dimensions,
  service_type, shipping_method, land_method, insured, insurance_value, status,
  origin_label, origin_lat, origin_lng,
  destination_label, destination_lat, destination_lng,
  current_lat, current_lng, estimated_delivery
)
SELECT * FROM (
  SELECT
    'SC1000001US' AS c1,
   'Gulfstream Import Exports LLC' AS c2,
   '900 Bagby St, Houston, TX 77002, USA' AS c3,
   'Lena Fischer' AS c4,
   'lena.demo@example.com' AS c5,
   'Speicherstadt 12, 20457 Hamburg, Germany' AS c6,
   'Industrial machine parts, crated' AS c7,
   'Crate' AS c8,
   640.00 AS c9,
   '72in x 48in x 48in' AS c10,
   'Regular' AS c11,
   'Sea' AS c12,
   NULL AS c13,
   1 AS c14,
   12000.00 AS c15,
   'Delivered' AS c16,
   'Houston, TX, USA' AS c17,
   29.7604000 AS c18,
   -95.3698000 AS c19,
   'Hamburg, Germany' AS c20,
   53.5511000 AS c21,
   9.9937000 AS c22,
   53.5511000 AS c23,
   9.9937000 AS c24,
   DATE_SUB(CURDATE(), INTERVAL 1 DAY) AS c25
) AS seed
WHERE @seed_demo = 0;

SET @sid2 = (SELECT id FROM shipments WHERE tracking_number = 'SC1000001US');

INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note, event_time)
SELECT * FROM (
  SELECT @sid2 AS c1, 'Pending' AS c2, 'Houston, TX, USA' AS c3, 29.7604000 AS c4, -95.3698000 AS c5, 'Shipment booked and label created.' AS c6, DATE_SUB(NOW(), INTERVAL 9 DAY) AS c7
  UNION ALL SELECT @sid2, 'Picked Up', 'Houston, TX, USA', 29.7604000, -95.3698000, 'Crate picked up from sender facility.', DATE_SUB(NOW(), INTERVAL 8 DAY)
  UNION ALL SELECT @sid2, 'Customs Clearance', 'Port of Houston, TX, USA', 29.7355000, -95.0480000, 'Cleared US export customs.', DATE_SUB(NOW(), INTERVAL 7 DAY)
  UNION ALL SELECT @sid2, 'En Route', 'Atlantic Ocean', 40.0000000, -40.0000000, 'Vessel en route to Hamburg.', DATE_SUB(NOW(), INTERVAL 4 DAY)
  UNION ALL SELECT @sid2, 'Customs Clearance', 'Port of Hamburg, Germany', 53.5412000, 9.9339000, 'Cleared German import customs.', DATE_SUB(NOW(), INTERVAL 2 DAY)
  UNION ALL SELECT @sid2, 'Out for Delivery', 'Hamburg, Germany', 53.5511000, 9.9937000, 'Out for delivery with local courier.', DATE_SUB(NOW(), INTERVAL 1 DAY)
  UNION ALL SELECT @sid2, 'Delivered', 'Hamburg, Germany', 53.5511000, 9.9937000, 'Crate delivered and signed for.', DATE_SUB(NOW(), INTERVAL 1 DAY)
) AS seed
WHERE @seed_demo = 0 AND @sid2 IS NOT NULL;
