-- Migration 010: shipment payment tracking + admin-editable per-status
-- message templates.
--
-- Safe to run once via phpMyAdmin's Import tab.

SET NAMES utf8mb4;

-- Payment tracking on shipments. payment_price holds the total price for
-- 'Full Payment' and 'Payment on Arrival'; payment_initial_amount /
-- payment_amount_paid hold the expected total and amount paid so far for
-- 'Partial Payment' (the remaining balance is computed, never stored, so
-- it can't drift out of sync).
ALTER TABLE shipments
  ADD COLUMN payment_type ENUM('Full Payment', 'Partial Payment', 'Payment on Arrival') NOT NULL DEFAULT 'Full Payment' AFTER insurance_value,
  ADD COLUMN payment_price DECIMAL(10,2) NULL DEFAULT NULL AFTER payment_type,
  ADD COLUMN payment_initial_amount DECIMAL(10,2) NULL DEFAULT NULL AFTER payment_price,
  ADD COLUMN payment_amount_paid DECIMAL(10,2) NULL DEFAULT NULL AFTER payment_initial_amount;

-- Default explanatory writeup for each shipment status, editable at
-- /admin/status_messages.php. Used to fill in a tracking event's note
-- when staff leave it blank in /admin/add_update.php — baked into that
-- event at creation time, so editing a template later never rewrites
-- what a past update said.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('status_message_pending', 'Your shipment has been booked and a shipping label has been created. We are preparing it for pickup.'),
('status_message_picked_up', 'Your shipment has been picked up and is now in our network.'),
('status_message_en_route', 'Your shipment is on the move and heading toward its next stop.'),
('status_message_customs_clearance', 'Your shipment has arrived at a customs checkpoint and is being cleared for onward transport. This can take 1-2 business days.'),
('status_message_insurance_clearance', 'Your shipment is undergoing an insurance review before continuing its journey.'),
('status_message_out_for_delivery', 'Your shipment is out for delivery and should arrive today.'),
('status_message_delivered', 'Your shipment has been delivered. Thank you for shipping with us.'),
('status_message_on_hold', 'Your shipment has been placed on hold. Our team is looking into it and will update you shortly.'),
('status_message_delayed', 'Your shipment has been delayed. We apologize for the inconvenience and are working to get it moving again.'),
('status_message_exception', 'There was an exception with your shipment that needs attention. Our team has been notified and will follow up.');
