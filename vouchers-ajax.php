<?php
// File: vouchers-ajax.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/voucher_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!in_array(getUserRole(), ['admin', 'owner'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

$action = trim((string)($_GET['action'] ?? ''));

if ($action === 'get_student_fees') {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if ($student_id <= 0) {
        echo json_encode([]);
        exit();
    }
    $fees = getStudentFeeBreakdown($conn, $student_id);
    echo json_encode($fees);
    exit();
}

if ($action === 'get_family_fees') {
    $family_id = trim((string)($_GET['family_id'] ?? ''));
    if ($family_id === '') {
        echo json_encode([]);
        exit();
    }
    $fees = getFamilyFeeBreakdown($conn, $family_id);
    echo json_encode($fees);
    exit();
}

if ($action === 'get_class_students') {
    $class_id = trim((string)($_GET['class_id'] ?? ''));
    $students = getStudentsForVoucher($conn, $class_id);
    
    // Format response to send only necessary info
    $formatted = [];
    foreach ($students as $student) {
        $formatted[] = [
            'id' => $student['id'],
            'student_id' => $student['student_id'],
            'name' => trim($student['first_name'] . ' ' . $student['last_name']),
            'class' => $student['class'],
            'section' => $student['section']
        ];
    }
    echo json_encode($formatted);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit();
