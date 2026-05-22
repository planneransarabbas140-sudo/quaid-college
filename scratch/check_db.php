<?php
require_once 'config/db.php';
$db = new Database();
$pdo = $db->getConnection();

$tables = ['pos_sales', 'admissions', 'fee_collections'];

foreach ($tables as $table) {
    echo "Columns for $table:\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Error or table $table does not exist: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
?>
