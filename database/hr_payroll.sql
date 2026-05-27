-- HR Payroll migration for Quaid College ERP
-- Safe for new databases. Run before using modules/hr/payroll.php.

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    salary_month VARCHAR(20) NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_date DATE DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    generated_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payroll_month (staff_id, salary_month),
    INDEX idx_payroll_month (salary_month),
    INDEX idx_payroll_status (status),
    CONSTRAINT fk_payroll_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_generated_by
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payroll
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending';

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payroll ADD COLUMN gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER allowances',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll' AND COLUMN_NAME = 'gross_salary'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payroll ADD COLUMN tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gross_salary',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll' AND COLUMN_NAME = 'tax_amount'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payroll ADD COLUMN generated_by INT DEFAULT NULL AFTER status',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll' AND COLUMN_NAME = 'generated_by'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE payroll ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll' AND COLUMN_NAME = 'updated_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE staff ADD COLUMN salary DECIMAL(12,2) NOT NULL DEFAULT 0',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff' AND COLUMN_NAME = 'salary'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE payroll
SET gross_salary = basic_salary + COALESCE(allowances, 0)
WHERE gross_salary IS NULL OR gross_salary = 0;

UPDATE payroll
SET status = CASE LOWER(status)
    WHEN 'paid' THEN 'paid'
    ELSE 'pending'
END;
