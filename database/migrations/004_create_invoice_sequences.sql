-- Sequence table for invoice numbering per year
CREATE TABLE IF NOT EXISTS invoice_sequences (
  seq_year INT PRIMARY KEY,
  last_number INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
