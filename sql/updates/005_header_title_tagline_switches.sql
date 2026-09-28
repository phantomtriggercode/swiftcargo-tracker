-- Two switches for what the site header shows beside the logo, both on by
-- default so nothing changes until someone turns one off. Set at
-- /admin/branding.php.
--
--   header_show_title    show the company name as text beside the logo
--   header_show_tagline  show the tagline under that name
--
-- Turning both off gives the logo the whole brand area to itself and shows
-- it considerably larger, which is what a logo with the company name
-- already written into it wants. The company name itself stays editable
-- either way: it is still used in the page title, in emails, on the
-- waybill and everywhere else the header is not involved.
--
-- These replace the single 'logo_includes_name' row added by
-- sql/updates/004_logo_includes_name.sql, which tried to work the same
-- thing out from the logo's shape. Guessing meant someone could type a
-- company name, see no change on the site, and have no way of knowing why.
-- If you already imported 004, its row is simply ignored now and can be
-- left alone; nothing reads it.
--
-- Safe to run more than once: INSERT IGNORE leaves any value you have set.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('header_show_title', '1'),
('header_show_tagline', '1');
