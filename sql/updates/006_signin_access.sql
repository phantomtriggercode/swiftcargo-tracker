-- Controls for how the staff sign-in page is reached, set at
-- /admin/branding.php by a super admin. Both default to the current
-- behaviour, so importing this changes nothing until someone uses them.
--
--   header_show_login  '0' hides the sign-in link from the public header
--                      (the default). '1' shows it again.
--   admin_access_key   '' leaves the sign-in page reachable at its normal
--                      address (the default). Any other value turns the
--                      page into a 404 for anyone who does not present the
--                      key as ?k=KEY, so scanners never find it.
--
-- Safe to run more than once: INSERT IGNORE leaves any value you have set.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('header_show_login', '0'),
('admin_access_key', '');
