<?php
// File: fix_dashboard.php
// Purpose: Fix campus names and redirect to dashboard

require_once 'config/db.php';

$database = new Database();
$db = $database->getConnection();

try {
    // 1. Update Hamid Campus to Rajanpur
    $db->exec("UPDATE admissions SET campus = 'Rajanpur' WHERE campus = 'Hamid Campus'");

    // 2. Update any other non-standard campus names to Rajanpur
    $db->exec("UPDATE admissions SET campus = 'Rajanpur' WHERE campus NOT IN ('Rajanpur', 'Fazilpur', 'Kot Mithan')");

    // Success message in session
    setFlashMessage('success', 'Database cleaned up successfully!');
} catch (PDOException $e) {
    setFlashMessage('error', 'Error during cleanup: ' . $e->getMessage());
}

// Redirect to dashboard
header("Location: dashboard.php");
exit();
?>
