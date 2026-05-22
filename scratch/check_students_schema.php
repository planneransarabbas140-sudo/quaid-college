<?php
require_once 'config/db.php';
$database = new Database();
$db = $database->getConnection();

$table = 'students';
$stmt = $db->query("DESCRIBE $table");
echo "Schema for $table:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
