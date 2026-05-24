-- Class Billing Rules Table
-- Created: 2026-05-23
-- Purpose: Stores per-class fee billing overrides
-- Related files: settings-class-billing-rules.php,
--                includes/class_billing_functions.php
-- Related modules: Fee Management > Generate Fees
-- To reset: TRUNCATE class_billing_rules;
-- To remove: DROP TABLE class_billing_rules;

-- NOTE:
-- This codebase does not contain a dedicated `classes` master table.
-- Classes are stored as VARCHAR values in `students.class` and `fee_structure.class`.
-- Therefore, this table stores `class_name` (VARCHAR) and avoids a foreign key.

CREATE TABLE IF NOT EXISTS class_billing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    billing_frequency ENUM('monthly','quarterly','yearly','one-time') NOT NULL DEFAULT 'monthly',
    billing_type ENUM('individual','family') NOT NULL DEFAULT 'individual',
    rule_note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_name (class_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

