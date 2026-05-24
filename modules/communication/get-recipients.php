<?php
require_once __DIR__ . '/../../config/db.php';
startSecureSession();
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}
$db = (new Database())->getConnection();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$group = $data['group'] ?? '';
$filter_class = $data['class'] ?? '';
$filter_section = $data['section'] ?? '';
$filter_campus = $data['campus'] ?? '';
$attendance_date = $data['attendance_date'] ?? '';
$exam_id = $data['exam_id'] ?? '';

$results = [];
try {
    if ($group === 'all_students') {
        $sql = "SELECT id, first_name, last_name, guardian_phone, guardian_name, `class`, `section`, campus FROM students WHERE LOWER(COALESCE(status, 'Active')) = 'active'";
        $conds = [];
        $params = [];
        if ($filter_class) { $conds[] = '`class` = ?'; $params[] = $filter_class; }
        if ($filter_section) { $conds[] = '`section` = ?'; $params[] = $filter_section; }
        if ($filter_campus) { $conds[] = 'campus_id = ?'; $params[] = (int)$filter_campus; }
        if ($conds) { $sql .= ' AND ' . implode(' AND ', $conds); }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    } elseif ($group === 'staff') {
        $sql = "SELECT id, full_name AS first_name, '' AS last_name, phone AS guardian_phone, full_name AS guardian_name, 'Staff' AS `class`, '' AS `section`, campus, campus_id FROM staff WHERE LOWER(COALESCE(status, 'Active')) = 'active' AND COALESCE(is_active, 1) = 1";
        $params = [];
        if ($filter_campus) {
            $sql .= ' AND campus_id = ?';
            $params[] = (int)$filter_campus;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    } elseif ($group === 'fee_defaulters') {
        $sql = "SELECT fc.id AS fee_id, s.id AS student_id, s.first_name, s.last_name, s.guardian_phone, s.guardian_name, s.class, s.section, fc.amount, fc.paid_amount, fc.due_date, fc.fee_type, (fc.amount - fc.paid_amount) AS amount_due FROM fee_collections fc JOIN students s ON fc.student_id = s.id WHERE fc.status != 'Paid' AND COALESCE(fc.paid_amount,0) < COALESCE(fc.amount,0) AND LOWER(COALESCE(s.status, 'Active')) = 'active'";
        $conds = [];
        $params = [];
        if ($filter_class) { $conds[] = 's.`class` = ?'; $params[] = $filter_class; }
        if ($filter_section) { $conds[] = 's.`section` = ?'; $params[] = $filter_section; }
        if ($filter_campus) { $conds[] = 's.campus_id = ?'; $params[] = (int)$filter_campus; }
        if ($conds) { $sql .= ' AND ' . implode(' AND ', $conds); }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    } elseif ($group === 'absentees' || $group === 'late') {
        if (!$attendance_date) {
            echo json_encode(['success' => false, 'message' => 'Please select attendance date.']); exit;
        }
        $status = $group === 'absentees' ? 'Absent' : 'Late';
        $sql = "SELECT sa.student_id AS id, s.first_name, s.last_name, s.guardian_phone, s.guardian_name, s.class, s.section, sa.attendance_date, sa.status FROM student_attendance sa JOIN students s ON sa.student_id = s.id WHERE sa.status = ? AND sa.attendance_date = ? AND LOWER(COALESCE(s.status, 'Active')) = 'active'";
        $params = [$status, $attendance_date];
        if ($filter_class) { $sql .= ' AND s.`class` = ?'; $params[] = $filter_class; }
        if ($filter_section) { $sql .= ' AND s.`section` = ?'; $params[] = $filter_section; }
        if ($filter_campus) { $sql .= ' AND s.campus_id = ?'; $params[] = (int)$filter_campus; }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    } elseif ($group === 'failed') {
        if (!$exam_id) { echo json_encode(['success' => false, 'message' => 'Please select exam.']); exit; }
        $sql = "SELECT em.id AS mark_id, s.id AS student_id, s.first_name, s.last_name, s.guardian_phone, s.guardian_name, s.class, s.section, em.subject, em.obtained_marks, em.total_marks, em.grade FROM exam_marks em JOIN students s ON em.student_id = s.id WHERE em.obtained_marks < (em.total_marks * 0.40) AND em.exam_schedule_id = ?";
        $params = [(int)$exam_id];
        if ($filter_class) { $sql .= ' AND s.`class` = ?'; $params[] = $filter_class; }
        if ($filter_section) { $sql .= ' AND s.`section` = ?'; $params[] = $filter_section; }
        if ($filter_campus) { $sql .= ' AND s.campus_id = ?'; $params[] = (int)$filter_campus; }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid target group.']); exit;
    }
} catch (Exception $e) {
    error_log('WhatsApp Center Recipients Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load recipients.']); exit;
}

echo json_encode(['success' => true, 'data' => $results]);
