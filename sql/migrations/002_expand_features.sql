-- Migration 002: expanded shipment fields, site content/CMS + rate settings,
-- supported countries list, and public shipment requests.
--
-- Safe to run once on your existing SwiftCargo Tracker database via
-- phpMyAdmin's Import tab. It does NOT touch your existing shipments'
-- data (origin/destination/etc.) — it only adds new columns/tables and
-- remaps a couple of renamed enum values. Run this only once.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- Step 1: widen enums so both old and new values are valid,
-- so no existing row is rejected during the transition.
-- ---------------------------------------------------------------
ALTER TABLE shipments
  MODIFY COLUMN service_type ENUM('Standard','Express','Priority','Regular') NOT NULL DEFAULT 'Standard';

ALTER TABLE shipments
  MODIFY COLUMN status ENUM(
    'Pending','Picked Up','In Transit','Out for Delivery','Delivered','Delayed','Exception',
    'En Route','Customs Clearance','Insurance Clearance','On Hold'
  ) NOT NULL DEFAULT 'Pending';

-- ---------------------------------------------------------------
-- Step 2: migrate old values to their new names.
-- ---------------------------------------------------------------
UPDATE shipments SET service_type = 'Regular' WHERE service_type IN ('Standard', 'Priority');
UPDATE shipments SET status = 'En Route' WHERE status = 'In Transit';
UPDATE tracking_events SET status = 'En Route' WHERE status = 'In Transit';

-- ---------------------------------------------------------------
-- Step 3: narrow the enums down to their final, clean value sets.
-- ---------------------------------------------------------------
ALTER TABLE shipments
  MODIFY COLUMN service_type ENUM('Regular','Express') NOT NULL DEFAULT 'Regular';

ALTER TABLE shipments
  MODIFY COLUMN status ENUM(
    'Pending','Picked Up','En Route','Customs Clearance','Insurance Clearance',
    'Out for Delivery','Delivered','On Hold','Delayed','Exception'
  ) NOT NULL DEFAULT 'Pending';

-- ---------------------------------------------------------------
-- Step 4: new shipment fields.
-- ---------------------------------------------------------------
ALTER TABLE shipments
  ADD COLUMN shipping_method ENUM('Air','Sea','Land') NOT NULL DEFAULT 'Air' AFTER service_type,
  ADD COLUMN land_method ENUM('Van','Trailer','Train') NULL DEFAULT NULL AFTER shipping_method,
  ADD COLUMN packaging_type ENUM(
    'Box','Crate','Pallet','Loose Cargo','Full Container Load (FCL)','Less Than Container Load (LCL)','Envelope/Document'
  ) NOT NULL DEFAULT 'Box' AFTER package_description,
  ADD COLUMN dimensions VARCHAR(100) NULL DEFAULT NULL AFTER weight_kg,
  ADD COLUMN insured TINYINT(1) NOT NULL DEFAULT 0 AFTER dimensions,
  ADD COLUMN insurance_value DECIMAL(10,2) NULL DEFAULT NULL AFTER insured;

-- ---------------------------------------------------------------
-- Site content + calculator rate settings (simple key/value store,
-- editable from the admin panel).
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value LONGTEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Home page
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('home_hero_title', 'Ship anywhere. Track everything. Live.'),
('home_hero_lead', 'SwiftCargo moves freight and parcels across the United States and worldwide, and shows you exactly where they are on a live map — with an email sent to your receiver on every single update.'),
('stat_countries', '195+'),
('stat_ontime', '98.6%'),
('stat_support', '24/7'),
('stat_delivered', '1.2M+');

-- About page
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('about_title', 'About SwiftCargo'),
('about_lead', 'A US-based freight and parcel carrier built around one idea: you should always know exactly where your shipment is.'),
('about_body', 'SwiftCargo was built to give shippers and receivers complete visibility into every shipment, from the moment it is booked to the moment it is signed for. Every parcel and freight load is tracked through our network of hubs, with live map positioning and automatic email alerts sent the instant a shipment''s status changes.\n\nWe move shipments by air, sea, and land across all 50 states and to destinations worldwide, offering both Regular and Express service levels, optional shipment insurance, and support for freight packaging including pallets, crates, and full or partial container loads.\n\nThis site is operated for educational and demonstration purposes. All tracking data shown is either seed data or was entered by a site administrator for demonstration.');

-- Contact page
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('contact_intro', 'Questions about a shipment, a quote, or our services? Reach our support team any time.'),
('contact_phone', '+1 (800) 555-0199'),
('contact_email', 'support@swiftcargo.test'),
('contact_address', '4500 Freight Way, Dallas, TX 75201, United States');

-- Footer
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('footer_tagline', 'Reliable freight and parcel shipping across the United States and worldwide, with real-time tracking and instant email alerts, so you always know where your shipment is.'),
('footer_bottom_note', 'Demo project — not a real courier company.');

-- Countries we ship to
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('countries_intro', 'SwiftCargo ships to every country in the world. Wherever your shipment is headed, we can get it there.'),
('countries_list', 'Afghanistan\nAlbania\nAlgeria\nAndorra\nAngola\nAntigua and Barbuda\nArgentina\nArmenia\nAustralia\nAustria\nAzerbaijan\nBahamas\nBahrain\nBangladesh\nBarbados\nBelarus\nBelgium\nBelize\nBenin\nBhutan\nBolivia\nBosnia and Herzegovina\nBotswana\nBrazil\nBrunei\nBulgaria\nBurkina Faso\nBurundi\nCabo Verde\nCambodia\nCameroon\nCanada\nCentral African Republic\nChad\nChile\nChina\nColombia\nComoros\nCongo (Republic of the)\nCongo (Democratic Republic of the)\nCosta Rica\nCroatia\nCuba\nCyprus\nCzechia\nDenmark\nDjibouti\nDominica\nDominican Republic\nEcuador\nEgypt\nEl Salvador\nEquatorial Guinea\nEritrea\nEstonia\nEswatini\nEthiopia\nFiji\nFinland\nFrance\nGabon\nGambia\nGeorgia\nGermany\nGhana\nGreece\nGrenada\nGuatemala\nGuinea\nGuinea-Bissau\nGuyana\nHaiti\nHonduras\nHungary\nIceland\nIndia\nIndonesia\nIran\nIraq\nIreland\nIsrael\nItaly\nJamaica\nJapan\nJordan\nKazakhstan\nKenya\nKiribati\nKosovo\nKuwait\nKyrgyzstan\nLaos\nLatvia\nLebanon\nLesotho\nLiberia\nLibya\nLiechtenstein\nLithuania\nLuxembourg\nMadagascar\nMalawi\nMalaysia\nMaldives\nMali\nMalta\nMarshall Islands\nMauritania\nMauritius\nMexico\nMicronesia\nMoldova\nMonaco\nMongolia\nMontenegro\nMorocco\nMozambique\nMyanmar\nNamibia\nNauru\nNepal\nNetherlands\nNew Zealand\nNicaragua\nNiger\nNigeria\nNorth Korea\nNorth Macedonia\nNorway\nOman\nPakistan\nPalau\nPanama\nPapua New Guinea\nParaguay\nPeru\nPhilippines\nPoland\nPortugal\nQatar\nRomania\nRussia\nRwanda\nSaint Kitts and Nevis\nSaint Lucia\nSaint Vincent and the Grenadines\nSamoa\nSan Marino\nSao Tome and Principe\nSaudi Arabia\nSenegal\nSerbia\nSeychelles\nSierra Leone\nSingapore\nSlovakia\nSlovenia\nSolomon Islands\nSomalia\nSouth Africa\nSouth Korea\nSouth Sudan\nSpain\nSri Lanka\nSudan\nSuriname\nSweden\nSwitzerland\nSyria\nTaiwan\nTajikistan\nTanzania\nThailand\nTimor-Leste\nTogo\nTonga\nTrinidad and Tobago\nTunisia\nTurkey\nTurkmenistan\nTuvalu\nUganda\nUkraine\nUnited Arab Emirates\nUnited Kingdom\nUnited States\nUruguay\nUzbekistan\nVanuatu\nVatican City\nVenezuela\nVietnam\nYemen\nZambia\nZimbabwe');

-- Shipping calculator rates (all figures in USD)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('rate_base_fee', '15.00'),
('rate_price_per_kg', '3.50'),
('rate_air_multiplier', '1.8'),
('rate_sea_multiplier', '1.0'),
('rate_land_multiplier', '1.2'),
('rate_express_multiplier', '1.5'),
('rate_insurance_percent', '2.5');

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
