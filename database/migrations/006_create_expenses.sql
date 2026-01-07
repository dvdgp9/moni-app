-- Tabla de gastos/facturas recibidas
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_name VARCHAR(255) NOT NULL,
  supplier_nif VARCHAR(20) NULL,
  invoice_number VARCHAR(100) NULL,
  invoice_date DATE NOT NULL,
  base_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 21.00,
  vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  category ENUM('suministros','material','servicios','transporte','software','profesionales','otros') DEFAULT 'otros',
  pdf_path VARCHAR(500) NULL,
  notes TEXT NULL,
  status ENUM('pending','validated') DEFAULT 'pending',
  user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_invoice_date (invoice_date),
  INDEX idx_category (category),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
