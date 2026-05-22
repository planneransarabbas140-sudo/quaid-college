<?php
require_once 'config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    // Add campus
    $db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS campus VARCHAR(100) DEFAULT 'Misbah Campus' AFTER section");
    echo "Column 'campus' added to 'students' table (if not exists).\n";

    // Add payment fields to students table if they don't exist
    $db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'Cash'");
    $db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(100) DEFAULT 'N/A'");
    $db->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS admission_fee DECIMAL(10,2) DEFAULT 5000.00");
    echo "Payment columns added to 'students' table (if not exists).\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
