-- Lets a super admin keep asking staff for coordinates even while the live
-- map is switched off.
--
-- Turning the map off normally hides latitude and longitude everywhere,
-- which is usually what you want. But a site that plans to switch the map
-- back on later would rather keep collecting coordinates in the meantime,
-- so no shipment booked during that window is left without a position.
-- This setting is what allows that.
--
-- Defaults to 0, meaning the coordinate fields follow the map exactly as
-- they already do, so nothing changes for an existing site until someone
-- turns it on.
--
-- Safe to run more than once: INSERT IGNORE leaves any value you have set.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('collect_coordinates', '0');
