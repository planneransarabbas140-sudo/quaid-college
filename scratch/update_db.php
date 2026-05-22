<?php
require_once 'config/db.php';
$db = new Database();
$pdo = $db->getConnection();

$queries = [
    "ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Cash'",
    "ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)",
    "ALTER TABLE admissions ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Cash'",
    "ALTER TABLE admissions ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)",
    "ALTER TABLE admissions ADD COLUMN IF NOT EXISTS admission_fee DECIMAL(10,2) DEFAULT 5000.00",
    "ALTER TABLE fee_collections ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)",
    "ALTER TABLE accounts_transactions ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Cash'",
    "ALTER TABLE accounts_transactions ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)",
    "ALTER TABLE visitors ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'N/A'",
    "ALTER TABLE visitors ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100)",
    "ALTER TABLE visitors ADD COLUMN IF NOT EXISTS fee_paid DECIMAL(10,2) DEFAULT 0.00"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "Executed: $sql\n";
    } catch (PDOException $e) {
        echo "Error executing $sql: " . $e->getMessage() . "\n";
    }
}
?>
