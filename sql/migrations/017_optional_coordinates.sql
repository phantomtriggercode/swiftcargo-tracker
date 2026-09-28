-- Makes every coordinate column optional.
--
-- With the live map switched off, staff are no longer asked for latitude
-- and longitude, so there is nothing sensible to store. Until now these
-- columns were NOT NULL, which left only 0,0 as a stand-in: a real place in
-- the Atlantic off the coast of Africa. Any shipment created while the map
-- was off would have appeared there the moment the map was switched back
-- on. NULL says "not recorded", and the tracking page leaves those points
-- off the map rather than inventing one.
--
-- Safe to run more than once: setting a column to NULL-able when it already
-- is changes nothing, and no existing value is touched.

ALTER TABLE shipments
  MODIFY COLUMN origin_lat DECIMAL(10,7) NULL DEFAULT NULL,
  MODIFY COLUMN origin_lng DECIMAL(10,7) NULL DEFAULT NULL,
  MODIFY COLUMN destination_lat DECIMAL(10,7) NULL DEFAULT NULL,
  MODIFY COLUMN destination_lng DECIMAL(10,7) NULL DEFAULT NULL,
  MODIFY COLUMN current_lat DECIMAL(10,7) NULL DEFAULT NULL,
  MODIFY COLUMN current_lng DECIMAL(10,7) NULL DEFAULT NULL;

ALTER TABLE tracking_events
  MODIFY COLUMN lat DECIMAL(10,7) NULL DEFAULT NULL,
  MODIFY COLUMN lng DECIMAL(10,7) NULL DEFAULT NULL;
