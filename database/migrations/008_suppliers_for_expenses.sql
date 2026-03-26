-- Suppliers catalog for expenses

CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  nif VARCHAR(20) NULL,
  normalized_name VARCHAR(255) NOT NULL,
  default_category ENUM('suministros','material','servicios','transporte','software','profesionales','otros') DEFAULT 'otros',
  default_vat_rate DECIMAL(5,2) NOT NULL DEFAULT 21.00,
  notes TEXT NULL,
  last_used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_suppliers_user_name (user_id, normalized_name),
  INDEX idx_suppliers_user_last_used (user_id, last_used_at),
  UNIQUE KEY uniq_suppliers_user_nif (user_id, nif),
  CONSTRAINT fk_suppliers_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE expenses
  ADD COLUMN supplier_id INT NULL AFTER user_id,
  ADD INDEX idx_expenses_supplier_id (supplier_id),
  ADD CONSTRAINT fk_expenses_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id);

INSERT INTO suppliers (
  user_id,
  name,
  nif,
  normalized_name,
  default_category,
  default_vat_rate,
  notes,
  last_used_at,
  created_at,
  updated_at
)
SELECT
  e.user_id,
  TRIM(MIN(e.supplier_name)) AS name,
  NULLIF(MAX(NULLIF(TRIM(e.supplier_nif), '')), '') AS nif,
  LOWER(TRIM(e.supplier_name)) AS normalized_name,
  SUBSTRING_INDEX(GROUP_CONCAT(e.category ORDER BY e.updated_at DESC, e.id DESC), ',', 1) AS default_category,
  COALESCE(MAX(e.vat_rate), 21.00) AS default_vat_rate,
  NULLIF(SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(TRIM(e.notes), '') ORDER BY e.updated_at DESC, e.id DESC SEPARATOR '||'), '||', 1), '') AS notes,
  MAX(COALESCE(e.updated_at, e.created_at)) AS last_used_at,
  MIN(e.created_at) AS created_at,
  MAX(COALESCE(e.updated_at, e.created_at)) AS updated_at
FROM expenses e
WHERE TRIM(COALESCE(e.supplier_name, '')) <> ''
GROUP BY
  e.user_id,
  COALESCE(NULLIF(UPPER(TRIM(e.supplier_nif)), ''), CONCAT('NAME:', LOWER(TRIM(e.supplier_name))));

UPDATE expenses e
INNER JOIN suppliers s
  ON s.user_id = e.user_id
 AND (
      (NULLIF(TRIM(e.supplier_nif), '') IS NOT NULL AND s.nif = UPPER(TRIM(e.supplier_nif)))
      OR (
        NULLIF(TRIM(e.supplier_nif), '') IS NULL
        AND s.normalized_name = LOWER(TRIM(e.supplier_name))
      )
    )
SET e.supplier_id = s.id
WHERE e.supplier_id IS NULL;
