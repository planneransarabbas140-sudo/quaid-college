-- Vouchers Module
-- Version: 1.0
-- Tables: vouchers, voucher_items
-- Related files: vouchers.php, vouchers-print.php,
--                vouchers-ajax.php, includes/voucher_functions.php
-- Related modules: Fee Management > Collect Fee, Total Transactions
-- Reset: TRUNCATE voucher_items; TRUNCATE vouchers;
-- Remove: DROP TABLE voucher_items; DROP TABLE vouchers;

CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_number VARCHAR(50) NOT NULL UNIQUE,
    student_id INT DEFAULT NULL,
    family_id VARCHAR(50) DEFAULT NULL, -- guardian phone identifier
    class VARCHAR(50) DEFAULT NULL,
    voucher_type ENUM('individual','family','bulk') DEFAULT 'individual',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('unpaid','paid','cancelled') DEFAULT 'unpaid',
    note VARCHAR(255) DEFAULT NULL,
    generated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS voucher_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    fee_head VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
