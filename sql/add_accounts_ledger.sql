-- Quaid College System: Accounts and Ledger support columns

ALTER TABLE accounts_transactions
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Cash',
ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT NULL;

-- No new tables are required for these modules.
-- The Ledger module reads from existing accounts_transactions, fee_collections,
-- payroll, expenses, and users tables.
