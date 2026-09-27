-- Live chat settings rows.
--
-- These three were added to sql/schema.sql when live chat was built, but no
-- separate file was written for databases that already existed, so an older
-- install never got the rows. The site works either way (a missing row reads
-- as its default), but without them the settings table does not show that
-- live chat exists, and nothing records that the feature is switched off on
-- purpose rather than simply absent.
--
-- Safe to run more than once: INSERT IGNORE leaves any row you already have,
-- including a Tawk.to account you have already connected.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('live_chat_enabled', '0'),
('live_chat_property_id', ''),
('live_chat_widget_id', '');
