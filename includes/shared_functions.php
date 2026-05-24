<?php
// SHARED FUNCTIONS LIBRARY
// Version: 1.0
// Rule: ALL modules must use functions from this file for dropdowns, lookups,
//       and data fetching. NEVER write a standalone "get classes" query in a
//       module file. Adding new shared data: add function here, then use
//       everywhere. Master table map: see includes/master_tables.php.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/master_tables.php';

if (!function_exists('sharedColumnExists')) {
    function sharedColumnExists(PDO $conn, $table, $column) {
        return function_exists('columnExists') ? columnExists($conn, $table, $column) : false;
    }
}

if (!function_exists('sharedTableExists')) {
    function sharedTableExists(PDO $conn, $table) {
        return function_exists('tableExists') ? tableExists($conn, $table) : false;
    }
}

if (!function_exists('sharedFetchAll')) {
    function sharedFetchAll(PDO $conn, $sql, array $params = []) {
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Shared Functions Error: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('sharedFetchOne')) {
    function sharedFetchOne(PDO $conn, $sql, array $params = []) {
        $rows = sharedFetchAll($conn, $sql, $params);
        return $rows[0] ?? null;
    }
}

if (!function_exists('getSchoolInfo')) {
    function getSchoolInfo(PDO $conn) {
        $info = [
            'name' => 'Quaid-e-Azam Group of Colleges',
            'logo' => BASE_URL . 'assets/images/qgc-logo.png',
            'address' => 'Rajanpur',
            'phone' => '+923338879961',
            'email' => '',
            'city' => 'Rajanpur',
        ];

        if (sharedTableExists($conn, 'campuses')) {
            $campus = sharedFetchOne($conn, "SELECT name, address, phone FROM campuses ORDER BY id ASC LIMIT 1");
            if ($campus) {
                $info['address'] = $campus['address'] ?: $info['address'];
                $info['phone'] = $campus['phone'] ?: $info['phone'];
            }
        }

        return $info;
    }
}

if (!function_exists('getAllSessions')) {
    function getAllSessions(PDO $conn) {
        $current = function_exists('getCurrentAcademicYear') ? getCurrentAcademicYear() : date('Y') . '-' . (date('Y') + 1);
        return [[
            'id' => $current,
            'session_name' => $current,
            'start_date' => null,
            'end_date' => null,
            'is_active' => 1,
        ]];
    }
}

if (!function_exists('getActiveSession')) {
    function getActiveSession(PDO $conn) {
        $sessions = getAllSessions($conn);
        return $sessions[0] ?? null;
    }
}

if (!function_exists('getCurrentSessionYear')) {
    function getCurrentSessionYear(PDO $conn = null) {
        return function_exists('getCurrentAcademicYear') ? getCurrentAcademicYear() : date('Y') . '-' . ((int)date('Y') + 1);
    }
}

if (!function_exists('getAllCampuses')) {
    function getAllCampuses(PDO $conn) {
        if (!sharedTableExists($conn, 'campuses')) {
            return [];
        }
        return sharedFetchAll($conn, "SELECT id, name AS campus_name, name, address, phone, 1 AS is_active FROM campuses ORDER BY name ASC");
    }
}

if (!function_exists('getActiveCampuses')) {
    function getActiveCampuses(PDO $conn) {
        return getAllCampuses($conn);
    }
}

if (!function_exists('getAllClasses')) {
    function getAllClasses(PDO $conn, $campus_id = null) {
        $sources = [
            ['students', 'class', 'campus_id', "LOWER(COALESCE(status, 'Active')) = 'active'"],
            ['fee_structure', 'class', null, 'COALESCE(is_active, 1) = 1'],
            ['exam_schedule', 'class', null, '1=1'],
            ['timetable', 'class', null, '1=1'],
            ['homework_diary', 'class', null, '1=1'],
            ['syllabus_files', 'class', null, '1=1'],
        ];
        $classes = [];
        foreach ($sources as $source) {
            [$table, $column, $campusColumn, $where] = $source;
            if (!sharedTableExists($conn, $table) || !sharedColumnExists($conn, $table, $column)) {
                continue;
            }
            $params = [];
            $campusFilter = '';
            if ($campus_id !== null && $campus_id !== '' && $campusColumn && sharedColumnExists($conn, $table, $campusColumn)) {
                $campusFilter = " AND `$campusColumn` = ?";
                $params[] = $campus_id;
            }
            $rows = sharedFetchAll($conn, "SELECT DISTINCT `$column` AS class_name FROM `$table` WHERE `$column` IS NOT NULL AND `$column` <> '' AND $where $campusFilter", $params);
            foreach ($rows as $row) {
                $name = trim((string)$row['class_name']);
                if ($name !== '') {
                    $classes[$name] = ['id' => $name, 'class_name' => $name, 'campus_id' => $campus_id];
                }
            }
        }
        ksort($classes, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($classes);
    }
}

if (!function_exists('getClassById')) {
    function getClassById(PDO $conn, $class_id) {
        $name = trim((string)$class_id);
        return $name === '' ? null : ['id' => $name, 'class_name' => $name];
    }
}

if (!function_exists('getClassNameById')) {
    function getClassNameById(PDO $conn, $class_id) {
        return trim((string)$class_id);
    }
}

if (!function_exists('getAllSections')) {
    function getAllSections(PDO $conn, $class_id = null) {
        $sections = [];
        $sources = [
            ['students', 'section', 'class'],
            ['exam_schedule', 'section', 'class'],
            ['timetable', 'section', 'class'],
            ['homework_diary', 'section', 'class'],
            ['syllabus_files', 'section', 'class'],
        ];
        foreach ($sources as $source) {
            [$table, $sectionColumn, $classColumn] = $source;
            if (!sharedTableExists($conn, $table) || !sharedColumnExists($conn, $table, $sectionColumn)) {
                continue;
            }
            $where = "`$sectionColumn` IS NOT NULL AND `$sectionColumn` <> ''";
            $params = [];
            if ($class_id !== null && $class_id !== '' && sharedColumnExists($conn, $table, $classColumn)) {
                $where .= " AND `$classColumn` = ?";
                $params[] = $class_id;
            }
            $rows = sharedFetchAll($conn, "SELECT DISTINCT `$sectionColumn` AS section_name FROM `$table` WHERE $where", $params);
            foreach ($rows as $row) {
                $name = trim((string)$row['section_name']);
                if ($name !== '') {
                    $sections[$name] = ['id' => $name, 'section_name' => $name, 'class_id' => $class_id];
                }
            }
        }
        ksort($sections, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($sections);
    }
}

if (!function_exists('getSectionsByClass')) {
    function getSectionsByClass(PDO $conn, $class_id) {
        return getAllSections($conn, $class_id);
    }
}

if (!function_exists('getSectionById')) {
    function getSectionById(PDO $conn, $section_id) {
        $name = trim((string)$section_id);
        return $name === '' ? null : ['id' => $name, 'section_name' => $name];
    }
}

if (!function_exists('getClassSectionLabel')) {
    function getClassSectionLabel(PDO $conn, $class_id, $section_id = null) {
        $class = getClassNameById($conn, $class_id);
        $section = trim((string)$section_id);
        return $section !== '' ? $class . ' - ' . $section : $class;
    }
}

if (!function_exists('getClassesWithSections')) {
    function getClassesWithSections(PDO $conn, $campus_id = null) {
        $grouped = [];
        foreach (getAllClasses($conn, $campus_id) as $class) {
            $grouped[$class['id']] = [
                'class_name' => $class['class_name'],
                'sections' => getSectionsByClass($conn, $class['id']),
            ];
        }
        return $grouped;
    }
}

if (!function_exists('getAllStudents')) {
    function getAllStudents(PDO $conn, $filters = []) {
        if (!sharedTableExists($conn, 'students')) {
            return [];
        }
        $where = [];
        $params = [];
        if (($filters['status'] ?? 'active') !== 'all' && sharedColumnExists($conn, 'students', 'status')) {
            $where[] = "LOWER(COALESCE(status, 'Active')) = ?";
            $params[] = strtolower((string)($filters['status'] ?? 'active'));
        }
        foreach (['class' => 'class_id', 'section' => 'section_id', 'campus_id' => 'campus_id'] as $column => $key) {
            if (($filters[$key] ?? '') !== '' && sharedColumnExists($conn, 'students', $column)) {
                $where[] = "`$column` = ?";
                $params[] = $filters[$key];
            }
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = "(first_name LIKE ? OR last_name LIKE ? OR father_name LIKE ? OR guardian_name LIKE ? OR roll_number LIKE ? OR student_id LIKE ?)";
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }
        $sql = "SELECT s.*, CONCAT_WS(' ', s.first_name, s.last_name) AS name, s.class AS class_name, s.section AS section_name FROM students s";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY s.class ASC, s.section ASC, s.roll_number ASC, s.first_name ASC";
        return sharedFetchAll($conn, $sql, $params);
    }
}

if (!function_exists('getStudentById')) {
    function getStudentById(PDO $conn, $student_id) {
        $rows = getAllStudents($conn, ['status' => 'all']);
        $student_id = (int)$student_id;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $student_id) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('getStudentsByClass')) {
    function getStudentsByClass(PDO $conn, $class_id, $section_id = null) {
        $filters = ['class_id' => $class_id];
        if ($section_id !== null && $section_id !== '') {
            $filters['section_id'] = $section_id;
        }
        return getAllStudents($conn, $filters);
    }
}

if (!function_exists('getStudentCountByClass')) {
    function getStudentCountByClass(PDO $conn, $class_id) {
        return count(getStudentsByClass($conn, $class_id));
    }
}

if (!function_exists('searchStudents')) {
    function searchStudents(PDO $conn, $search_term, $class_id = null) {
        $filters = ['search' => $search_term];
        if ($class_id !== null && $class_id !== '') {
            $filters['class_id'] = $class_id;
        }
        return getAllStudents($conn, $filters);
    }
}

if (!function_exists('getAllFamilies')) {
    function getAllFamilies(PDO $conn) {
        if (!sharedTableExists($conn, 'students')) {
            return [];
        }
        return sharedFetchAll($conn, "
            SELECT guardian_phone AS id, guardian_name AS family_name, father_name, guardian_phone AS phone, COUNT(*) AS student_count
            FROM students
            WHERE guardian_phone IS NOT NULL AND guardian_phone <> ''
            GROUP BY guardian_phone, guardian_name, father_name
            ORDER BY guardian_name ASC
        ");
    }
}

if (!function_exists('getFamilyById')) {
    function getFamilyById(PDO $conn, $family_id) {
        foreach (getAllFamilies($conn) as $family) {
            if ((string)$family['id'] === (string)$family_id) {
                return $family;
            }
        }
        return null;
    }
}

if (!function_exists('getStudentsByFamily')) {
    function getStudentsByFamily(PDO $conn, $family_id) {
        return getAllStudents($conn, ['status' => 'active', 'search' => '']) ? sharedFetchAll($conn, "
            SELECT s.*, CONCAT_WS(' ', s.first_name, s.last_name) AS name, s.class AS class_name, s.section AS section_name
            FROM students s
            WHERE s.guardian_phone = ? AND LOWER(COALESCE(s.status, 'Active')) = 'active'
            ORDER BY s.class ASC, s.section ASC, s.first_name ASC
        ", [$family_id]) : [];
    }
}

if (!function_exists('getAllStaff')) {
    function getAllStaff(PDO $conn, $type = null) {
        if (!sharedTableExists($conn, 'staff')) {
            return [];
        }
        $where = ["LOWER(COALESCE(status, 'Active')) = 'active'"];
        $params = [];
        if ($type) {
            $where[] = "(LOWER(role) LIKE ? OR LOWER(designation) LIKE ?)";
            $params[] = '%' . strtolower($type) . '%';
            $params[] = '%' . strtolower($type) . '%';
        }
        return sharedFetchAll($conn, "SELECT * FROM staff WHERE " . implode(' AND ', $where) . " ORDER BY full_name ASC", $params);
    }
}

if (!function_exists('getTeachers')) {
    function getTeachers(PDO $conn, $class_id = null) {
        return getAllStaff($conn, 'teacher');
    }
}

if (!function_exists('getStaffById')) {
    function getStaffById(PDO $conn, $staff_id) {
        return sharedFetchOne($conn, "SELECT * FROM staff WHERE id = ? LIMIT 1", [(int)$staff_id]);
    }
}

if (!function_exists('getAllSubjects')) {
    function getAllSubjects(PDO $conn, $class_id = null) {
        if (!sharedTableExists($conn, 'exam_schedule')) {
            return [];
        }
        $where = "subject IS NOT NULL AND subject <> ''";
        $params = [];
        if ($class_id !== null && $class_id !== '') {
            $where .= " AND class = ?";
            $params[] = $class_id;
        }
        return sharedFetchAll($conn, "SELECT DISTINCT subject AS id, subject AS subject_name, class AS class_id, MAX(total_marks) AS total_marks FROM exam_schedule WHERE $where GROUP BY subject, class ORDER BY subject ASC", $params);
    }
}

if (!function_exists('getSubjectsByClass')) {
    function getSubjectsByClass(PDO $conn, $class_id) {
        return getAllSubjects($conn, $class_id);
    }
}

if (!function_exists('getAllFeeHeads')) {
    function getAllFeeHeads(PDO $conn, $class_id = null) {
        if (!sharedTableExists($conn, 'fee_structure')) {
            return [];
        }
        $where = "COALESCE(is_active, 1) = 1";
        $params = [];
        if ($class_id !== null && $class_id !== '') {
            $where .= " AND class = ?";
            $params[] = $class_id;
        }
        return sharedFetchAll($conn, "SELECT id, fee_type AS fee_head_name, fee_type, amount, class AS class_id, frequency FROM fee_structure WHERE $where ORDER BY fee_type ASC", $params);
    }
}

if (!function_exists('getFeeHeadsByClass')) {
    function getFeeHeadsByClass(PDO $conn, $class_id) {
        return getAllFeeHeads($conn, $class_id);
    }
}

if (!function_exists('getAllExams')) {
    function getAllExams(PDO $conn, $class_id = null, $session_id = null) {
        if (!sharedTableExists($conn, 'exam_schedule')) {
            return [];
        }
        $where = "exam_title IS NOT NULL AND exam_title <> ''";
        $params = [];
        if ($class_id !== null && $class_id !== '') {
            $where .= " AND class = ?";
            $params[] = $class_id;
        }
        return sharedFetchAll($conn, "
            SELECT MIN(id) AS id, exam_title AS exam_name, exam_title, class AS class_id, section, MIN(exam_date) AS exam_date, NULL AS session_id
            FROM exam_schedule
            WHERE $where
            GROUP BY exam_title, class, section
            ORDER BY MIN(exam_date) DESC, exam_title ASC
        ", $params);
    }
}

if (!function_exists('getExamById')) {
    function getExamById(PDO $conn, $exam_id) {
        return sharedFetchOne($conn, "SELECT id, exam_title AS exam_name, exam_title, class AS class_id, section, exam_date FROM exam_schedule WHERE id = ? LIMIT 1", [(int)$exam_id]);
    }
}

if (!function_exists('getStudentAttendanceSummary')) {
    function getStudentAttendanceSummary(PDO $conn, $student_id, $from_date = null, $to_date = null) {
        if (!sharedTableExists($conn, 'student_attendance')) {
            return ['present' => 0, 'absent' => 0, 'total' => 0, 'percent' => 0, 'percentage' => 0];
        }
        $where = ['student_id = ?'];
        $params = [(int)$student_id];
        if ($from_date) {
            $where[] = 'attendance_date >= ?';
            $params[] = $from_date;
        }
        if ($to_date) {
            $where[] = 'attendance_date <= ?';
            $params[] = $to_date;
        }
        $row = sharedFetchOne($conn, "
            SELECT
              SUM(CASE WHEN status IN ('Present','Late','Half Day') THEN 1 ELSE 0 END) AS present,
              SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent,
              COUNT(*) AS total
            FROM student_attendance
            WHERE " . implode(' AND ', $where), $params) ?: [];
        $present = (int)($row['present'] ?? 0);
        $absent = (int)($row['absent'] ?? 0);
        $total = (int)($row['total'] ?? 0);
        $percent = calculatePercentage($present, $total);
        return ['present' => $present, 'absent' => $absent, 'total' => $total, 'percent' => $percent, 'percentage' => $percent];
    }
}

if (!function_exists('getClassAttendanceSummary')) {
    function getClassAttendanceSummary(PDO $conn, $class_id, $date) {
        if (!sharedTableExists($conn, 'student_attendance')) {
            return ['present' => 0, 'absent' => 0, 'total' => 0, 'percent' => 0];
        }
        $students = getStudentsByClass($conn, $class_id);
        $ids = array_map('intval', array_column($students, 'id'));
        if (!$ids) {
            return ['present' => 0, 'absent' => 0, 'total' => 0, 'percent' => 0];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$date]);
        $row = sharedFetchOne($conn, "
            SELECT SUM(CASE WHEN status IN ('Present','Late','Half Day') THEN 1 ELSE 0 END) AS present,
                   SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent,
                   COUNT(*) AS total
            FROM student_attendance
            WHERE student_id IN ($placeholders) AND attendance_date = ?
        ", $params) ?: [];
        $present = (int)($row['present'] ?? 0);
        $total = (int)($row['total'] ?? 0);
        return ['present' => $present, 'absent' => (int)($row['absent'] ?? 0), 'total' => $total, 'percent' => calculatePercentage($present, $total)];
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return 'Rs. ' . number_format((float)$amount, 0);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd-M-Y') {
        if (!$date) {
            return '';
        }
        return date($format, strtotime((string)$date));
    }
}

if (!function_exists('calculatePercentage')) {
    function calculatePercentage($obtained, $total) {
        $total = (float)$total;
        return $total > 0 ? round(((float)$obtained / $total) * 100, 2) : 0.0;
    }
}

if (!function_exists('getGradeFromPercentage')) {
    function getGradeFromPercentage(PDO $conn, $percentage) {
        if (!sharedTableExists($conn, 'grading_system')) {
            return ['grade' => '', 'remarks' => ''];
        }
        return sharedFetchOne($conn, "SELECT grade, remarks FROM grading_system WHERE ? BETWEEN min_percentage AND max_percentage ORDER BY min_percentage DESC LIMIT 1", [(float)$percentage]) ?: ['grade' => '', 'remarks' => ''];
    }
}

if (!function_exists('isPassOrFail')) {
    function isPassOrFail(PDO $conn, $percentage) {
        $grade = getGradeFromPercentage($conn, $percentage);
        return strtoupper((string)($grade['grade'] ?? '')) === 'F' ? 'Fail' : 'Pass';
    }
}

if (!function_exists('getCurrentAdminId')) {
    function getCurrentAdminId() {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('getCurrentAdminName')) {
    function getCurrentAdminName() {
        return $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
    }
}

if (!function_exists('redirectWithMessage')) {
    function redirectWithMessage($url, $msg_key) {
        $separator = strpos($url, '?') === false ? '?' : '&';
        header('Location: ' . $url . $separator . 'msg=' . urlencode($msg_key));
        exit;
    }
}

if (!function_exists('showAlertIfMessage')) {
    function showAlertIfMessage($msg, $messages_map) {
        if (!$msg || !isset($messages_map[$msg])) {
            return;
        }
        $item = $messages_map[$msg];
        $type = $item['type'] ?? 'info';
        $class = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-danger' : 'alert-info');
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars((string)$item['text'], ENT_QUOTES, 'UTF-8')
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}
?>
