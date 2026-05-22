<?php
// File: modules/fee_management/export_dues.php - Export Dues to Excel
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

// Get students with outstanding dues
$query = "SELECT 
            s.student_id, s.first_name, s.last_name, s.class, s.section, 
            s.guardian_name, s.guardian_phone, s.guardian_email,
            COALESCE(SUM(fc.amount - fc.paid_amount), 0) as total_due,
            MAX(fc.due_date) as last_due_date
          FROM students s
          LEFT JOIN fee_collections fc ON s.id = fc.student_id AND fc.status IN ('Pending', 'Partially Paid')
          GROUP BY s.id
          HAVING total_due > 0
          ORDER BY total_due DESC";

$dues_students = $db->query($query)->fetchAll();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="outstanding_dues_' . date('Y-m-d') . '.xls"');

echo "<table border='1'>";
echo "<tr>";
echo "<th>Student ID</th>";
echo "<th>Student Name</th>";
echo "<th>Class</th>";
echo "<th>Section</th>";
echo "<th>Guardian Name</th>";
echo "<th>Guardian Phone</th>";
echo "<th>Guardian Email</th>";
echo "<th>Total Due (PKR)</th>";
echo "<th>Last Due Date</th>";
echo "</tr>";

foreach ($dues_students as $student) {
    echo "<tr>";
    echo "<td>{$student['student_id']}</td>";
    echo "<td>{$student['first_name']} {$student['last_name']}</td>";
    echo "<td>{$student['class']}</td>";
    echo "<td>{$student['section']}</td>";
    echo "<td>{$student['guardian_name']}</td>";
    echo "<td>{$student['guardian_phone']}</td>";
    echo "<td>{$student['guardian_email']}</td>";
    echo "<td>" . number_format($student['total_due'], 2) . "</td>";
    echo "<td>{$student['last_due_date']}</td>";
    echo "</tr>";
}

echo "</table>";
?>