-- Migration 013: adds an audit trail of sensitive admin actions, viewable
-- by super admins at /admin/activity_log.php. Logs things like admin
-- account changes, shipment deletions, SMTP credential changes, and
-- color/template activation — not routine page views.
--
-- admin_id is nullable and ON DELETE SET NULL: a log entry survives even
-- after the admin account that made it is deleted, so the history isn't
-- silently lost — admin_name is stored alongside as a readable label for
-- that case.
--
-- Safe to run once via phpMyAdmin's Import tab.

SET NAMES utf8mb4;

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
