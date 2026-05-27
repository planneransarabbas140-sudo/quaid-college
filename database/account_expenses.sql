-- Accounts Expenses module migration

CREATE TABLE IF NOT EXISTS account_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_title VARCHAR(220) DEFAULT NULL,
    expense_category VARCHAR(120) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expense_date DATE DEFAULT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
    paid_to VARCHAR(180) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    account_transaction_id INT DEFAULT NULL,
    INDEX idx_account_expenses_date (expense_date),
    INDEX idx_account_expenses_category (expense_category),
    INDEX idx_account_expenses_status (status),
    INDEX idx_account_expenses_tx (account_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounts_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(120) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    transaction_date DATE DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    transaction_id VARCHAR(100) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_accounts_transactions_date (transaction_date),
    INDEX idx_accounts_transactions_type (transaction_type),
    INDEX idx_accounts_transactions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing installations with older accounts tables are also checked and patched
-- safely by modules/accounts/expenses.php using the project's columnExists() helper.
