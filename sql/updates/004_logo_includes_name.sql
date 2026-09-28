-- How the site should treat the uploaded logo: whether the picture already
-- has the company name written into it. Set at /admin/branding.php.
--
--   auto  work it out from the shape. A logo wider than it is tall is
--         assumed to be a name-plate carrying the name; anything squarer
--         is assumed to be a symbol. Right almost always.
--   yes   show the logo on its own, larger, and do not print the name
--         beside it.
--   no    show it as a small mark beside the name and tagline.
--
-- Safe to run more than once: INSERT IGNORE leaves any value you have set.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('logo_includes_name', 'auto');
