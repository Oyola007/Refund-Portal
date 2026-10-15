-- Adds the transfer-to-finance step and the three conversation types.
-- Run ONCE in phpMyAdmin (select refund_system, SQL tab). Run upgrade_to_airline.sql first if you have not.


ALTER TABLE conversations
  ADD COLUMN kind ENUM('customer_admin','customer_finance','admin_finance') NOT NULL DEFAULT 'customer_admin',
  ADD UNIQUE KEY uq_refund_kind (refund_id, kind);
ALTER TABLE conversations DROP INDEX refund_id;

ALTER TABLE refund_requests
  ADD COLUMN handler ENUM('admin','finance') NOT NULL DEFAULT 'admin',
  ADD COLUMN transferred_by INT NULL,
  ADD COLUMN transferred_at DATETIME NULL,
  ADD COLUMN transfer_note TEXT NULL,
  ADD CONSTRAINT fk_refund_transferred_by FOREIGN KEY (transferred_by) REFERENCES users(id) ON DELETE SET NULL;

-- Finance used to see every approved refund, so refunds already approved or paid are handed to finance.
UPDATE refund_requests SET handler = 'finance', transferred_at = updated_at, updated_at = updated_at
WHERE status IN ('approved', 'paid');
