-- Multi-user security hardening
-- Assumes there is already at least one row in users.

SET @owner_user_id := (SELECT MIN(id) FROM users);

ALTER TABLE clients
  ADD COLUMN user_id INT NULL AFTER id,
  ADD INDEX idx_clients_user_id (user_id);

UPDATE clients
SET user_id = @owner_user_id
WHERE user_id IS NULL;

ALTER TABLE clients
  MODIFY COLUMN user_id INT NOT NULL;

ALTER TABLE invoices
  ADD COLUMN user_id INT NULL AFTER id,
  MODIFY COLUMN invoice_number VARCHAR(50) NULL,
  ADD INDEX idx_invoices_user_id (user_id);

UPDATE invoices
SET user_id = @owner_user_id
WHERE user_id IS NULL;

ALTER TABLE invoices
  MODIFY COLUMN user_id INT NOT NULL;

ALTER TABLE invoices
  DROP INDEX unique_invoice_number,
  ADD UNIQUE KEY unique_invoice_number_user (user_id, invoice_number);

UPDATE expenses
SET user_id = @owner_user_id
WHERE user_id IS NULL;

ALTER TABLE expenses
  MODIFY COLUMN user_id INT NOT NULL,
  ADD INDEX idx_expenses_user_id (user_id);

UPDATE reminders
SET user_id = @owner_user_id
WHERE user_id IS NULL;

ALTER TABLE reminders
  MODIFY COLUMN user_id INT NOT NULL,
  ADD INDEX idx_reminders_user_id (user_id);

DELETE s_null
FROM settings s_null
INNER JOIN settings s_other
  ON s_null.setting_key = s_other.setting_key
 AND s_null.user_id IS NULL
 AND (
      (s_other.user_id IS NULL AND s_null.id < s_other.id)
      OR s_other.user_id = @owner_user_id
 );

UPDATE settings
SET user_id = @owner_user_id
WHERE user_id IS NULL;

ALTER TABLE settings
  MODIFY COLUMN user_id INT NOT NULL;

ALTER TABLE invoice_sequences
  ADD COLUMN user_id INT NULL FIRST;

UPDATE invoice_sequences
SET user_id = @owner_user_id
WHERE user_id IS NULL;

ALTER TABLE invoice_sequences
  MODIFY COLUMN user_id INT NOT NULL,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (user_id, seq_year);
