<?php
// File: result-cards-ajax.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/result_card_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!in_array(getUserRole(), ['admin', 'owner', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$conn = (new Database())->getConnection();
$action = trim((string)($_GET['action'] ?? ''));

if ($action === 'get_students_by_class') {
    $classId = trim((string)($_GET['class_id'] ?? ''));
    $rows = [];
    foreach (getStudentsByClass($conn, $classId) as $student) {
        $rows[] = [
            'id' => (int)$student['id'],
            'student_id' => $student['student_id'] ?? '',
            'roll_no' => $student['roll_number'] ?: ($student['student_id'] ?? ''),
            'name' => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
            'father_name' => $student['father_name'] ?: ($student['guardian_name'] ?? ''),
        ];
    }
    echo json_encode($rows);
    exit;
}

if ($action === 'preview_marks') {
    echo json_encode(getStudentMarksForExam($conn, (int)($_GET['student_id'] ?? 0), (int)($_GET['exam_id'] ?? 0)));
    exit;
}

if ($action === 'get_class_students_count') {
    $classId = trim((string)($_GET['class_id'] ?? ''));
    $students = getStudentsByClass($conn, $classId);
    $examId = (int)($_GET['exam_id'] ?? 0);
    $withMarks = 0;
    foreach ($students as $student) {
        if (getStudentMarksForExam($conn, (int)$student['id'], $examId)) {
            $withMarks++;
        }
    }
    echo json_encode([
        'count' => count($students),
        'with_marks' => $withMarks,
        'message' => count($students) . ' students found for this class and exam. ' . $withMarks . ' have marks entered.'
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
