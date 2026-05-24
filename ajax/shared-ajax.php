<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/shared_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}

$db = (new Database())->getConnection();
$action = $_GET['action'] ?? '';
$role = getUserRole();

$sensitiveActions = [
    'get_students',
    'get_student_detail',
    'get_family_students',
    'search_students',
];
if (in_array($action, $sensitiveActions, true) && !in_array($role, ['admin', 'owner', 'teacher', 'staff'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

try {
    switch ($action) {
        case 'get_sections':
            $data = getSectionsByClass($db, trim((string)($_GET['class_id'] ?? '')));
            break;
        case 'get_students':
            $data = getStudentsByClass($db, trim((string)($_GET['class_id'] ?? '')), trim((string)($_GET['section_id'] ?? '')));
            break;
        case 'get_subjects':
            $data = getSubjectsByClass($db, trim((string)($_GET['class_id'] ?? '')));
            break;
        case 'get_fee_heads':
            $data = getFeeHeadsByClass($db, trim((string)($_GET['class_id'] ?? '')));
            break;
        case 'get_student_detail':
            $data = getStudentById($db, (int)($_GET['student_id'] ?? 0));
            break;
        case 'get_family_students':
            $data = getStudentsByFamily($db, trim((string)($_GET['family_id'] ?? '')));
            break;
        case 'get_exams':
            $data = getAllExams($db, trim((string)($_GET['class_id'] ?? '')), trim((string)($_GET['session_id'] ?? '')));
            break;
        case 'search_students':
            $data = searchStudents($db, trim((string)($_GET['q'] ?? '')), trim((string)($_GET['class_id'] ?? '')));
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    error_log('Shared AJAX Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load data.']);
}
?>
