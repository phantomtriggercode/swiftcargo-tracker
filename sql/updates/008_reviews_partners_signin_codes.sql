-- Customer reviews, the partner strip, and emailed sign-in codes for
-- admins on new browsers.
--
-- Safe to run more than once: every table is created only if missing and
-- every setting uses INSERT IGNORE, so values already saved are kept.
-- Re-importing sql/schema.sql does exactly the same; this file is only for
-- anyone who would rather apply this one change on its own.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admin_trusted_browsers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  browser_label VARCHAR(120) NOT NULL DEFAULT '',
  ip_address VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uniq_trusted_selector (selector),
  KEY idx_trusted_admin (admin_id),
  KEY idx_trusted_expires (expires_at),
  CONSTRAINT fk_trusted_browser_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_reviews_listing (is_published, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  logo_path VARCHAR(255) NULL,
  website_url VARCHAR(255) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_partners_listing (is_published, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('login_otp_enabled', '0'),
('login_otp_remember_days', '30'),
('reviews_enabled', '1'),
('partners_enabled', '1');
