-- Extend users table with profile fields for invoicing/branding
ALTER TABLE users
  ADD COLUMN company_name VARCHAR(255) NULL AFTER name,
  ADD COLUMN nif VARCHAR(20) NULL AFTER company_name,
  ADD COLUMN address TEXT NULL AFTER nif,
  ADD COLUMN phone VARCHAR(30) NULL AFTER address,
  ADD COLUMN billing_email VARCHAR(255) NULL AFTER phone,
  ADD COLUMN iban VARCHAR(34) NULL AFTER billing_email,
  ADD COLUMN logo_url VARCHAR(255) NULL AFTER iban,
  ADD COLUMN color_primary VARCHAR(20) NULL AFTER logo_url,
  ADD COLUMN color_accent VARCHAR(20) NULL AFTER color_primary;
