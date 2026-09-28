-- The short line under the company name in the site header, e.g.
-- "FAST, SECURE AND RELIABLE". Edited at /admin/branding.php.
--
-- Stored in normal case; the header shows it in capitals. Setting it to an
-- empty string hides it entirely rather than leaving a gap under the name.
--
-- Safe to run more than once: INSERT IGNORE leaves any value you have
-- already set.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('header_tagline', 'Fast, secure and reliable');
