<?php
require_once __DIR__ . '/../config/db.php';
$db = (new Database())->getConnection();
$cols = $db->query("DESCRIBE students")->fetchAll(PDO::FETCH_COLUMN);
echo "students columns: " . implode(', ', $cols) . "\n";
