<?php
// Temp: check fee_types actual column names
require_once __DIR__ . '/config/db.php';
$db = (new Database())->getConnection();

$cols = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fee_types'
     ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_COLUMN);
echo "fee_types columns: " . implode(', ', $cols) . "\n";

// Check fee_collections columns too
$cols2 = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fee_collections'
     ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_COLUMN);
echo "fee_collections columns: " . implode(', ', $cols2) . "\n";

// Show existing rows
$rows = $db->query("SELECT * FROM fee_types")->fetchAll(PDO::FETCH_ASSOC);
echo "Rows count: " . count($rows) . "\n";
if ($rows) echo "First row keys: " . implode(', ', array_keys($rows[0])) . "\n";
