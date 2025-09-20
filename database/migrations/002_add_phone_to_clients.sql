-- Forward migration: add phone column to clients
ALTER TABLE clients
  ADD COLUMN phone VARCHAR(30) NULL AFTER email;
