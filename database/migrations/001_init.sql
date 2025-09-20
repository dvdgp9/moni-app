-- Settings generales por usuario (single-user de inicio)
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  user_id INT NULL,
  UNIQUE KEY unique_key_user (setting_key, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eventos/recordatorios configurables
CREATE TABLE IF NOT EXISTS reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  event_date DATE NOT NULL,
  recurring ENUM('none','yearly') DEFAULT 'yearly',
  user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Logs de envíos para idempotencia
CREATE TABLE IF NOT EXISTS reminder_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reminder_id INT NULL,
  event_date DATE NOT NULL,
  sent_to VARCHAR(255) NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (event_date),
  INDEX (sent_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuarios simples (para futura auth)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clientes (placeholder inicial)
CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  nif VARCHAR(20) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(30) NULL,
  address TEXT NULL,
  default_vat DECIMAL(5,2) DEFAULT 21.00,
  default_irpf DECIMAL(5,2) DEFAULT 15.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Facturas (placeholder inicial)
CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(50) NOT NULL,
  client_id INT NOT NULL,
  status ENUM('draft','issued','paid','cancelled') DEFAULT 'draft',
  issue_date DATE NOT NULL,
  due_date DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_invoice_number (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Líneas de factura (placeholder inicial)
CREATE TABLE IF NOT EXISTS invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 21.00,
  irpf_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
