-- Presupuestos (quotes)
CREATE TABLE IF NOT EXISTS quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  quote_number VARCHAR(50) NULL,
  client_id INT NOT NULL,
  status ENUM('draft','sent','accepted','rejected','expired','converted') DEFAULT 'draft',
  token VARCHAR(64) NOT NULL,
  issue_date DATE NOT NULL,
  valid_until DATE NULL,
  notes TEXT NULL,
  accepted_at DATETIME NULL,
  rejected_at DATETIME NULL,
  rejection_reason TEXT NULL,
  converted_invoice_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_quote_token (token),
  INDEX idx_quotes_user (user_id),
  INDEX idx_quotes_client (client_id),
  INDEX idx_quotes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Líneas de presupuesto
CREATE TABLE IF NOT EXISTS quote_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 21.00,
  irpf_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  INDEX idx_quote_items_quote (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Secuencias de numeración de presupuestos
CREATE TABLE IF NOT EXISTS quote_sequences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  seq_year SMALLINT NOT NULL,
  last_number INT NOT NULL DEFAULT 0,
  UNIQUE KEY unique_user_year (user_id, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
