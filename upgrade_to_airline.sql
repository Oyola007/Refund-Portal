-- Upgrades an existing refund_system database (the ticket version) to the airline form.
-- Run ONCE in phpMyAdmin: select the refund_system database, open the SQL tab, paste, Go.
-- Existing refunds are kept: the old ticket reference becomes the booking number, and the
-- old ticket description becomes the flight field, so you can edit them if needed.


ALTER TABLE refund_requests
  ADD COLUMN passenger_name VARCHAR(120) NOT NULL DEFAULT '',
  ADD COLUMN passenger_email VARCHAR(150) NOT NULL DEFAULT '',
  ADD COLUMN passenger_phone VARCHAR(20) NOT NULL DEFAULT '',
  ADD COLUMN mailing_address VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN city_state_country VARCHAR(150) NOT NULL DEFAULT '',
  ADD COLUMN booking_ref VARCHAR(20) NOT NULL DEFAULT '',
  ADD COLUMN ticket_number VARCHAR(30) NULL,
  ADD COLUMN flight_numbers VARCHAR(100) NOT NULL DEFAULT '',
  ADD COLUMN departure_city VARCHAR(100) NOT NULL DEFAULT '',
  ADD COLUMN destination_city VARCHAR(100) NOT NULL DEFAULT '',
  ADD COLUMN itinerary VARCHAR(255) NULL,
  ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT '',
  ADD COLUMN preferred_refund_method VARCHAR(40) NULL,
  ADD COLUMN reason_other VARCHAR(150) NULL,
  ADD COLUMN documents_provided VARCHAR(255) NULL,
  ADD COLUMN signature_name VARCHAR(120) NOT NULL DEFAULT '',
  ADD COLUMN declared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE refund_requests r JOIN users u ON u.id = r.user_id
SET r.passenger_name = u.full_name,
    r.passenger_email = u.email,
    r.passenger_phone = u.phone,
    r.booking_ref = LEFT(r.ticket_ref, 20),
    r.flight_numbers = LEFT(r.ticket_details, 100),
    r.amount_paid = r.amount_requested,
    r.signature_name = u.full_name,
    r.declared_at = r.created_at;

ALTER TABLE refund_requests
  DROP COLUMN ticket_ref,
  DROP COLUMN ticket_details,
  MODIFY reason_details TEXT NULL;
