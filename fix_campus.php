<?php
// File: fix_campus.php
// Purpose: Fix wrong campus names in the database

require_once 'config/db.php';

$database = new Database();
$db = $database->getConnection();

echo "Starting Campus Name Cleanup...\n";

try {
    // 1. Update Hamid Campus to Rajanpur
    $sql1 = "UPDATE admissions SET campus = 'Rajanpur' WHERE campus = 'Hamid Campus'";
    $stmt1 = $db->prepare($sql1);
    $stmt1->execute();
    $count1 = $stmt1->rowCount();
    echo "Updated $count1 records from 'Hamid Campus' to 'Rajanpur' in admissions table.\n";

    // 2. Update any other non-standard campus names to Rajanpur
    $sql2 = "UPDATE admissions SET campus = 'Rajanpur' WHERE campus NOT IN ('Rajanpur', 'Fazilpur', 'Kot Mithan')";
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute();
    $count2 = $stmt2->rowCount();
    echo "Updated $count2 non-standard records to 'Rajanpur' in admissions table.\n";

    // 3. Also update students table just in case
    $sql3 = "UPDATE students SET campus = 'Rajanpur' WHERE campus = 'Hamid Campus'";
    $stmt3 = $db->prepare($sql3);
    $stmt3->execute();
    $count3 = $stmt3->rowCount();
    echo "Updated $count3 records from 'Hamid Campus' to 'Rajanpur' in students table.\n";

    $sql4 = "UPDATE students SET campus = 'Rajanpur' WHERE campus NOT IN ('Rajanpur', 'Fazilpur', 'Kot Mithan')";
    $stmt4 = $db->prepare($sql4);
    $stmt4->execute();
    $count4 = $stmt4->rowCount();
    echo "Updated $count4 non-standard records to 'Rajanpur' in students table.\n";

    echo "Cleanup completed successfully!\n";
} catch (PDOException $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
?>
