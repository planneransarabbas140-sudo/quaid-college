<?php
require_once 'config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    $sql = "ALTER TABLE students
        ADD COLUMN IF NOT EXISTS blood_group VARCHAR(5) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS religion VARCHAR(50) DEFAULT 'Islam',
        ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) DEFAULT 'Pakistani',
        ADD COLUMN IF NOT EXISTS roll_number VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS guardian_relation VARCHAR(50) DEFAULT 'Father',
        ADD COLUMN IF NOT EXISTS guardian_email VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT 'Punjab',
        ADD COLUMN IF NOT EXISTS pin_code VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS medical_info TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS campus VARCHAR(100) DEFAULT 'Misbah Campus — Rajanpur',
        ADD COLUMN IF NOT EXISTS cnic VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL";
    
    $db->exec($sql);
    echo "Table 'students' updated successfully with all missing columns.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
