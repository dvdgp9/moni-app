-- Forward migration: add due_date column to invoices (nullable)
ALTER TABLE invoices
  ADD COLUMN due_date DATE NULL AFTER issue_date;
