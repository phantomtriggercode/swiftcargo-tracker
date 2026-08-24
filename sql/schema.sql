-- SwiftCargo Tracker database schema
-- Import this file via Hostinger's phpMyAdmin (or `mysql -u user -p dbname < schema.sql`)
--
-- NOTE: if you already imported an earlier version of this file on a live
-- site, do NOT re-import this one — it will not touch your existing data,
-- but the safe way to pick up the new fields/tables is to run the migration
-- file instead: sql/migrations/002_expand_features.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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

-- Default admin login: username = admin / password = ChangeMe123!
-- (hash generated with PHP password_hash — change this password immediately after first login)
-- This first account is the super admin — it can create/suspend/delete
-- any other admin account from /admin/admins.php.
INSERT INTO admins (username, password_hash, full_name, is_super_admin, is_active) VALUES
('admin', '$2y$12$HYDffKZi7ppAiampmKCVU.Fm8Fk/S4.vKv.dvwoUYPRyvoXs.l9G.', 'Site Administrator', 1, 1)
ON DUPLICATE KEY UPDATE username = username;

-- ---------------------------------------------------------------
-- Couriers / carriers (managed from /admin/couriers.php — admins can
-- rename, deactivate, or add new carriers like DHL, UPS, FedEx, USPS)
-- ---------------------------------------------------------------
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

-- ---------------------------------------------------------------
-- Shipments
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shipments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_number VARCHAR(32) NOT NULL UNIQUE,

  sender_name VARCHAR(150) NOT NULL,
  sender_address VARCHAR(255) NOT NULL,

  receiver_name VARCHAR(150) NOT NULL,
  receiver_email VARCHAR(190) NOT NULL,
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

  status ENUM(
    'Pending','Picked Up','En Route','Customs Clearance','Insurance Clearance',
    'Out for Delivery','Delivered','On Hold','Delayed','Exception'
  ) NOT NULL DEFAULT 'Pending',

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
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  event_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  email_sent TINYINT(1) NOT NULL DEFAULT 0,

  CONSTRAINT fk_tracking_shipment FOREIGN KEY (shipment_id)
    REFERENCES shipments(id) ON DELETE CASCADE,
  INDEX idx_shipment_id (shipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
('home_hero_lead', 'SwiftCargo moves freight and parcels across the United States and worldwide, and shows you exactly where they are on a live map — with an email sent to your receiver on every single update.'),
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
('privacy_body', 'This Privacy Policy explains what information we collect when you use this website or ship with us, why we collect it, and how it is handled.\n\nInformation we collect: when you request a shipment, track a package, or contact us, we collect the details you provide — names, email addresses, phone numbers, physical addresses, and information about the shipment itself (contents description, weight, dimensions, and declared value if insured). We do not ask for or store payment card numbers on this site.\n\nHow we use it: we use this information to create and manage shipments, send tracking and delivery status emails, respond to inquiries, calculate shipping estimates, and keep records required to operate our shipping service. Contact information tied to a shipment is also used to send status update emails as the shipment moves through our network.\n\nCookies and site data: this site uses a single session cookie to keep you logged in to the admin panel and to remember short-lived confirmation messages. We do not use third-party advertising or tracking cookies.\n\nSharing: we do not sell your personal information. We may share shipment details with the carrier or courier handling a given shipment, and with service providers who help us operate this site (for example, our email delivery provider), solely to provide the service you requested.\n\nData retention: we keep shipment and contact records for as long as needed to provide our services, meet legal or accounting obligations, and resolve disputes.\n\nSecurity: we use reasonable technical and organizational measures to protect the information we hold, including encrypted connections and access controls on our admin systems. No method of transmission or storage is 100% secure, and we cannot guarantee absolute security.\n\nYour choices: you can contact us at any time to ask what information we hold about you, request a correction, or request deletion where we are not required to keep it for legal or operational reasons.\n\nChanges to this policy: we may update this policy from time to time. The version posted here is always the current one.\n\nContact us: if you have questions about this policy, reach out using the details on our Contact page.'),
('terms_title', 'Terms of Service'),
('terms_lead', 'The terms that apply when you use our site and shipping services.'),
('terms_body', 'By using this website or requesting a shipment through us, you agree to the terms below.\n\nOur services: this site lets you request a shipment quote, and lets senders, receivers, and our staff track a shipment''s status and location. Submitting a shipment request is a request for service, not a confirmed booking — our team follows up to confirm final details, pricing, and pickup arrangements before a shipment is created.\n\nAccuracy of information: you are responsible for providing accurate sender, receiver, and package information. Delays or delivery issues caused by incomplete or incorrect information are not our responsibility.\n\nEstimates and pricing: cost estimates shown on this site are calculated from the details you provide and are subject to confirmation by our team before a shipment is booked. Final pricing may differ based on verified weight, dimensions, destination, or service level.\n\nProhibited shipments: you agree not to ship anything illegal, hazardous, or prohibited by applicable customs, postal, or transport regulations. We may refuse or cancel a shipment that we reasonably believe violates this.\n\nInsurance: declared-value insurance is optional and, where selected, is subject to the terms communicated to you at the time of booking. We are not liable for loss or damage beyond any insurance coverage in place for a given shipment.\n\nLimitation of liability: to the fullest extent permitted by law, we are not liable for indirect, incidental, or consequential damages arising from the use of this site or our shipping services, beyond the value of the shipment (and any insurance coverage) involved.\n\nIntellectual property: the content, design, and branding on this site belong to us or our licensors and may not be copied or reused without permission.\n\nChanges: we may update these terms from time to time. Continued use of this site after a change means you accept the updated terms.\n\nContact us: questions about these terms can be sent using the details on our Contact page.');

-- ---------------------------------------------------------------
-- Site-wide color palettes (managed only by super admins, at
-- /admin/themes.php — the "Colors" page). Exactly one row is active
-- at a time; its colors are injected as CSS variable overrides on
-- every page. Colors are fully independent from the structural
-- design — activating a palette never touches layout (see
-- `templates` below). Presets are just as deletable as any palette a
-- super admin creates — is_preset is informational only.
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

INSERT INTO color_palettes (
  name, is_active, is_preset, is_admin_selectable,
  color_primary, color_primary_dark, color_accent,
  color_ink, color_ink_soft, color_muted, color_border, color_bg_soft, color_white,
  color_ok, color_warn, color_danger
) VALUES
('Classic Red', 1, 1, 1,
  '#d40511', '#a80410', '#ffcc00',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f5f7', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Classic Green', 0, 1, 1,
  '#15803d', '#166534', '#facc15',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f7f5', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Ocean Blue', 0, 1, 0,
  '#0369a1', '#075985', '#38bdf8',
  '#111827', '#4b5563', '#6b7280', '#e2e8f0', '#f1f5f9', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Emerald Freight', 0, 1, 0,
  '#047857', '#065f46', '#34d399',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f6f5', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Sunset Orange', 0, 1, 0,
  '#c2410c', '#9a3412', '#fb923c',
  '#1c1917', '#57534e', '#78716c', '#e7e5e4', '#faf5f0', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Royal Purple', 0, 1, 0,
  '#6d28d9', '#5b21b6', '#a78bfa',
  '#1e1b2e', '#4b5563', '#6b7280', '#e5e7eb', '#f6f4fb', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Midnight Navy', 0, 1, 0,
  '#1e3a8a', '#1e293b', '#60a5fa',
  '#111827', '#4b5563', '#6b7280', '#e5e7eb', '#f4f5f7', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Charcoal Mono', 0, 1, 0,
  '#111827', '#000000', '#9ca3af',
  '#111827', '#4b5563', '#6b7280', '#d1d5db', '#f3f4f6', '#ffffff',
  '#16a34a', '#b45309', '#dc2626'),
('Teal Logistics', 0, 1, 0,
  '#0f766e', '#115e59', '#5eead4',
  '#111827', '#4b5563', '#6b7280', '#e2e8f0', '#f1f5f4', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Crimson Express', 0, 1, 0,
  '#be123c', '#9f1239', '#fb7185',
  '#18181b', '#52525b', '#71717a', '#e4e4e7', '#faf5f6', '#ffffff',
  '#16a34a', '#d97706', '#dc2626'),
('Amber Cargo', 0, 1, 0,
  '#b45309', '#92400e', '#fbbf24',
  '#1c1917', '#57534e', '#78716c', '#e7e5e4', '#faf7f0', '#ffffff',
  '#16a34a', '#b45309', '#dc2626');

-- ---------------------------------------------------------------
-- Structural design templates (managed only by super admins, at
-- /admin/templates.php). Exactly one row is active at a time.
-- layout_key selects the homepage section order/hero treatment/
-- corner+shadow style defined in style.css; animation_key selects
-- the scroll-reveal animation style; logo_path is that template's
-- own default logo mark, used site-wide whenever no custom logo is
-- uploaded under Branding. Activating a template never touches
-- colors — see `color_palettes` above.
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

INSERT INTO templates (name, layout_key, animation_key, logo_path, is_active, is_preset) VALUES
('Classic', 'classic', 'fade', '/assets/images/template-logos/classic.svg', 1, 1),
('Modern', 'modern', 'fade-up', '/assets/images/template-logos/modern.svg', 0, 1),
('Minimal', 'minimal', 'none', '/assets/images/template-logos/minimal.svg', 0, 1),
('Bold', 'bold', 'scale-in', '/assets/images/template-logos/bold.svg', 0, 1),
('Corporate', 'corporate', 'fade', '/assets/images/template-logos/corporate.svg', 0, 1),
('Dark Header', 'dark-header', 'slide-in', '/assets/images/template-logos/dark-header.svg', 0, 1);

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

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Demo seed data (safe to delete once you add real shipments)
-- ---------------------------------------------------------------

-- Shipment 1: domestic, Land/Trailer, Express, currently en route
INSERT INTO shipments (
  tracking_number, sender_name, sender_address, receiver_name, receiver_email, receiver_address,
  package_description, packaging_type, weight_kg, dimensions,
  service_type, shipping_method, land_method, insured, insurance_value, status,
  origin_label, origin_lat, origin_lng,
  destination_label, destination_lat, destination_lng,
  current_lat, current_lng, estimated_delivery
) VALUES (
  'SC1000000US', 'Wells & Turner Logistics Group', '1420 Alameda St, Los Angeles, CA 90021, USA',
  'Michael Chen', 'michael.demo@example.com', '245 Park Avenue, New York, NY 10167, USA',
  'Consumer electronics, palletized', 'Pallet', 180.00, '48in x 40in x 60in',
  'Express', 'Land', 'Trailer', 1, 5000.00, 'En Route',
  'Los Angeles, CA, USA', 34.0522000, -118.2437000,
  'New York, NY, USA', 40.7128000, -74.0060000,
  39.7392000, -104.9903000, DATE_ADD(CURDATE(), INTERVAL 2 DAY)
) ON DUPLICATE KEY UPDATE tracking_number = tracking_number;

SET @sid = (SELECT id FROM shipments WHERE tracking_number = 'SC1000000US');

INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note, event_time) VALUES
(@sid, 'Pending', 'Los Angeles, CA, USA', 34.0522000, -118.2437000, 'Shipment booked and label created.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(@sid, 'Picked Up', 'Los Angeles, CA, USA', 34.0522000, -118.2437000, 'Pallet picked up from sender facility.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@sid, 'En Route', 'Las Vegas, NV, USA', 36.1699000, -115.1398000, 'Departed regional hub, en route to destination.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@sid, 'En Route', 'Denver, CO, USA', 39.7392000, -104.9903000, 'In transit to next facility.', NOW());

-- Shipment 2: international, Sea/Crate, Regular, delivered
INSERT INTO shipments (
  tracking_number, sender_name, sender_address, receiver_name, receiver_email, receiver_address,
  package_description, packaging_type, weight_kg, dimensions,
  service_type, shipping_method, land_method, insured, insurance_value, status,
  origin_label, origin_lat, origin_lng,
  destination_label, destination_lat, destination_lng,
  current_lat, current_lng, estimated_delivery
) VALUES (
  'SC1000001US', 'Gulfstream Import Exports LLC', '900 Bagby St, Houston, TX 77002, USA',
  'Lena Fischer', 'lena.demo@example.com', 'Speicherstadt 12, 20457 Hamburg, Germany',
  'Industrial machine parts, crated', 'Crate', 640.00, '72in x 48in x 48in',
  'Regular', 'Sea', NULL, 1, 12000.00, 'Delivered',
  'Houston, TX, USA', 29.7604000, -95.3698000,
  'Hamburg, Germany', 53.5511000, 9.9937000,
  53.5511000, 9.9937000, DATE_SUB(CURDATE(), INTERVAL 1 DAY)
) ON DUPLICATE KEY UPDATE tracking_number = tracking_number;

SET @sid2 = (SELECT id FROM shipments WHERE tracking_number = 'SC1000001US');

INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note, event_time) VALUES
(@sid2, 'Pending', 'Houston, TX, USA', 29.7604000, -95.3698000, 'Shipment booked and label created.', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(@sid2, 'Picked Up', 'Houston, TX, USA', 29.7604000, -95.3698000, 'Crate picked up from sender facility.', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(@sid2, 'Customs Clearance', 'Port of Houston, TX, USA', 29.7355000, -95.0480000, 'Cleared US export customs.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(@sid2, 'En Route', 'Atlantic Ocean', 40.0000000, -40.0000000, 'Vessel en route to Hamburg.', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@sid2, 'Customs Clearance', 'Port of Hamburg, Germany', 53.5412000, 9.9339000, 'Cleared German import customs.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@sid2, 'Out for Delivery', 'Hamburg, Germany', 53.5511000, 9.9937000, 'Out for delivery with local courier.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@sid2, 'Delivered', 'Hamburg, Germany', 53.5511000, 9.9937000, 'Crate delivered and signed for.', DATE_SUB(NOW(), INTERVAL 1 DAY));
