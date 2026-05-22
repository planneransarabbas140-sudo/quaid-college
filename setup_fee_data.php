<?php
try {
    $pdo = new PDO('mysql:host=localhost;port=3307;dbname=quaid_college_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Setting up sample fee structure data...\n";

    // Sample fee structures
    $fee_structures = [
        // Pre-School
        ['Pre-School', 'Admission Fee', 5000.00, 'One-time', '2024-2025'],
        ['Pre-School', 'Monthly Tuition', 3000.00, 'Monthly', '2024-2025'],
        ['Pre-School', 'Annual Development', 2000.00, 'Annual', '2024-2025'],

        // Primary Classes
        ['1st', 'Admission Fee', 6000.00, 'One-time', '2024-2025'],
        ['1st', 'Monthly Tuition', 3500.00, 'Monthly', '2024-2025'],
        ['1st', 'Exam Fee', 800.00, 'Semester', '2024-2025'],
        ['2nd', 'Admission Fee', 6000.00, 'One-time', '2024-2025'],
        ['2nd', 'Monthly Tuition', 3500.00, 'Monthly', '2024-2025'],
        ['2nd', 'Exam Fee', 800.00, 'Semester', '2024-2025'],
        ['3rd', 'Admission Fee', 6000.00, 'One-time', '2024-2025'],
        ['3rd', 'Monthly Tuition', 3500.00, 'Monthly', '2024-2025'],
        ['3rd', 'Exam Fee', 800.00, 'Semester', '2024-2025'],
        ['4th', 'Admission Fee', 6000.00, 'One-time', '2024-2025'],
        ['4th', 'Monthly Tuition', 3500.00, 'Monthly', '2024-2025'],
        ['4th', 'Exam Fee', 800.00, 'Semester', '2024-2025'],
        ['5th', 'Admission Fee', 6000.00, 'One-time', '2024-2025'],
        ['5th', 'Monthly Tuition', 3500.00, 'Monthly', '2024-2025'],
        ['5th', 'Exam Fee', 800.00, 'Semester', '2024-2025'],

        // Middle Classes
        ['6th', 'Admission Fee', 7000.00, 'One-time', '2024-2025'],
        ['6th', 'Monthly Tuition', 4000.00, 'Monthly', '2024-2025'],
        ['6th', 'Exam Fee', 1000.00, 'Semester', '2024-2025'],
        ['7th', 'Admission Fee', 7000.00, 'One-time', '2024-2025'],
        ['7th', 'Monthly Tuition', 4000.00, 'Monthly', '2024-2025'],
        ['7th', 'Exam Fee', 1000.00, 'Semester', '2024-2025'],
        ['8th', 'Admission Fee', 7000.00, 'One-time', '2024-2025'],
        ['8th', 'Monthly Tuition', 4000.00, 'Monthly', '2024-2025'],
        ['8th', 'Exam Fee', 1000.00, 'Semester', '2024-2025'],

        // Secondary Classes
        ['9th', 'Admission Fee', 8000.00, 'One-time', '2024-2025'],
        ['9th', 'Monthly Tuition', 4500.00, 'Monthly', '2024-2025'],
        ['9th', 'Exam Fee', 1200.00, 'Semester', '2024-2025'],
        ['10th', 'Admission Fee', 8000.00, 'One-time', '2024-2025'],
        ['10th', 'Monthly Tuition', 4500.00, 'Monthly', '2024-2025'],
        ['10th', 'Exam Fee', 1200.00, 'Semester', '2024-2025'],

        // Higher Secondary
        ['1st Year', 'Admission Fee', 10000.00, 'One-time', '2024-2025'],
        ['1st Year', 'Monthly Tuition', 5000.00, 'Monthly', '2024-2025'],
        ['1st Year', 'Exam Fee', 1500.00, 'Semester', '2024-2025'],
        ['2nd Year', 'Admission Fee', 10000.00, 'One-time', '2024-2025'],
        ['2nd Year', 'Monthly Tuition', 5000.00, 'Monthly', '2024-2025'],
        ['2nd Year', 'Exam Fee', 1500.00, 'Semester', '2024-2025'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO fee_structure (class, fee_type, amount, frequency, academic_year) VALUES (?, ?, ?, ?, ?)");

    foreach ($fee_structures as $fee) {
        $stmt->execute($fee);
    }

    echo "Sample fee structure data inserted successfully!\n";

    // Check if we have students to create some sample fee collections
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $student_count = $stmt->fetchColumn();

    if ($student_count > 0) {
        echo "Creating sample fee collections for existing students...\n";

        // Get some students
        $students = $pdo->query("SELECT id, class FROM students LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as $student) {
            // Get fee structures for this student's class
            $stmt = $pdo->prepare("SELECT fee_type, amount FROM fee_structure WHERE class = ? AND academic_year = '2024-2025' LIMIT 2");
            $stmt->execute([$student['class']]);
            $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($fees as $fee) {
                // Create a sample payment using the existing table structure
                $stmt_insert = $pdo->prepare("INSERT IGNORE INTO fee_collections (student_id, fee_type, amount, paid_amount, payment_date, payment_method, status, collected_by) VALUES (?, ?, ?, ?, ?, 'Cash', 'Paid', 1)");
                $stmt_insert->execute([
                    $student['id'],
                    $fee['fee_type'],
                    $fee['amount'],
                    $fee['amount'], // paid_amount = amount for full payment
                    date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'))
                ]);
            }
        }

        echo "Sample fee collections created!\n";
    }

    echo "Fee management setup complete!\n";

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>