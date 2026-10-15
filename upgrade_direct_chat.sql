-- Lets anyone start a one-on-one conversation (direct chats), and adds the Messages inbox ordering.
-- Run ONCE in phpMyAdmin (select refund_system, SQL tab, paste, Go).
-- Order of upgrade files: upgrade_to_airline.sql, then upgrade_transfer.sql, then this one.


SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE conversations
  MODIFY refund_id INT NULL,
  MODIFY customer_id INT NULL,
  MODIFY kind ENUM('customer_admin','customer_finance','admin_finance','direct') NOT NULL DEFAULT 'customer_admin',
  ADD COLUMN user_one INT NULL,
  ADD COLUMN user_two INT NULL,
  ADD COLUMN started_by INT NULL,
  ADD COLUMN pair_key VARCHAR(20) NULL,
  ADD COLUMN subject VARCHAR(150) NULL,
  ADD COLUMN last_message_at DATETIME NULL,
  ADD UNIQUE KEY uq_pair_key (pair_key),
  ADD CONSTRAINT fk_conv_user_one FOREIGN KEY (user_one) REFERENCES users(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_conv_user_two FOREIGN KEY (user_two) REFERENCES users(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_conv_started_by FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- Existing refund chats keep appearing in the inbox, ordered by their latest message.
UPDATE conversations c
SET last_message_at = (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id);
