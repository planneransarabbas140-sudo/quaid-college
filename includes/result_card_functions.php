<?php
// Result Cards Module - Functions
// Version: 1.0
// Depends on: students, exam_schedule, exam_marks,
//             grading_system, result_cards tables
// Used by: result-cards.php, result-cards-print.php,
//          result-cards-ajax.php

require_once __DIR__ . '/../config/db.php';

function rcText($value) {
    return trim((string)$value);
}

function rcExamGroup(PDO $conn, $exam_id) {
    if (!tableExists($conn, 'exam_schedule')) {
        return null;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM exam_schedule WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exam) {
            return null;
        }

        $stmt = $conn->prepare("
            SELECT id, exam_title, exam_type, class, section, subject, exam_date, total_marks, campus
            FROM exam_schedule
            WHERE exam_title = ? AND class = ? AND section = ?
            ORDER BY exam_date ASC, subject ASC, id ASC
        ");
        $stmt->execute([$exam['exam_title'], $exam['class'], $exam['section']]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $exam['subjects'] = $subjects;
        $exam['subject_ids'] = array_map('intval', array_column($subjects, 'id'));
        return $exam;
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return null;
    }
}

function getExamsForResultCard($conn, $class_id = null) {
    if (!tableExists($conn, 'exam_schedule')) {
        return [];
    }

    try {
        $params = [];
        $where = "WHERE exam_title IS NOT NULL AND exam_title <> ''";
        if ($class_id !== null && rcText($class_id) !== '') {
            $where .= " AND class = ?";
            $params[] = rcText($class_id);
        }

        $stmt = $conn->prepare("
            SELECT MIN(id) AS id, exam_title, exam_type, class, section, campus,
                   MIN(exam_date) AS first_exam_date,
                   COUNT(*) AS subject_count
            FROM exam_schedule
            $where
            GROUP BY exam_title, exam_type, class, section, campus
            ORDER BY first_exam_date DESC, exam_title ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return [];
    }
}

function getClassesForResultCard($conn) {
    return getClassList($conn);
}

function getCampusesForResultCard($conn) {
    if (!tableExists($conn, 'campuses')) {
        return [];
    }
    try {
        $stmt = $conn->prepare("SELECT id, name, type FROM campuses ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return [];
    }
}

function getStudentsByClass($conn, $class_id) {
    if (!tableExists($conn, 'students')) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT id, student_id, registration_number, roll_number, first_name, last_name, class, section, father_name, guardian_name, campus_id
            FROM students
            WHERE class = ?
              AND LOWER(COALESCE(status, 'Active')) = 'active'
            ORDER BY section ASC, first_name ASC, last_name ASC
        ");
        $stmt->execute([rcText($class_id)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return [];
    }
}

function getStudentDetailForCard($conn, $student_id) {
    if (!tableExists($conn, 'students')) {
        return null;
    }

    try {
        $stmt = $conn->prepare("
            SELECT s.*, c.name AS campus_name
            FROM students s
            LEFT JOIN campuses c ON c.id = s.campus_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$student_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return null;
    }
}

function calculateGrade($conn, $percentage) {
    $percentage = round((float)$percentage, 2);
    if (tableExists($conn, 'grading_system')) {
        try {
            $stmt = $conn->prepare("
                SELECT grade, remarks
                FROM grading_system
                WHERE ? BETWEEN min_percentage AND max_percentage
                ORDER BY min_percentage DESC
                LIMIT 1
            ");
            $stmt->execute([$percentage]);
            $grade = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($grade) {
                return $grade;
            }
        } catch (Exception $e) {
            error_log("Result Cards Error: " . $e->getMessage());
        }
    }

    return ['grade' => 'F', 'remarks' => 'Fail'];
}

function getStudentMarksForExam($conn, $student_id, $exam_id) {
    if (!tableExists($conn, 'exam_marks')) {
        return [];
    }

    $exam = rcExamGroup($conn, (int)$exam_id);
    if (!$exam || empty($exam['subject_ids'])) {
        return [];
    }

    try {
        $placeholders = implode(', ', array_fill(0, count($exam['subject_ids']), '?'));
        $params = array_merge([(int)$student_id], $exam['subject_ids']);
        $stmt = $conn->prepare("
            SELECT es.subject AS subject_name,
                   COALESCE(em.total_marks, es.total_marks, 0) AS total_marks,
                   COALESCE(em.obtained_marks, 0) AS obtained_marks,
                   em.grade AS saved_grade,
                   em.remarks
            FROM exam_schedule es
            LEFT JOIN exam_marks em ON em.exam_schedule_id = es.id AND em.student_id = ?
            WHERE es.id IN ($placeholders)
            ORDER BY es.subject ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $total = (float)$row['total_marks'];
            $obtained = round((float)$row['obtained_marks'], 2);
            $percentage = $total > 0 ? round(($obtained / $total) * 100, 2) : 0.00;
            $grade = calculateGrade($conn, $percentage);
            $row['total_marks'] = round($total, 2);
            $row['obtained_marks'] = $obtained;
            $row['percentage'] = $percentage;
            $row['grade'] = $grade['grade'];
            $row['remarks'] = $row['remarks'] ?: $grade['remarks'];
            $row['pass_fail'] = strtoupper((string)$grade['grade']) === 'F' ? 'Fail' : 'Pass';
        }
        unset($row);
        return $rows;
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return [];
    }
}

function rcTotalsForStudentExam($conn, $student_id, $exam_id) {
    $marks = getStudentMarksForExam($conn, (int)$student_id, (int)$exam_id);
    $obtained = 0.00;
    $possible = 0.00;
    foreach ($marks as $mark) {
        $obtained += (float)$mark['obtained_marks'];
        $possible += (float)$mark['total_marks'];
    }
    $percentage = $possible > 0 ? round(($obtained / $possible) * 100, 2) : 0.00;
    return [
        'marks' => $marks,
        'obtained' => round($obtained, 2),
        'possible' => round($possible, 2),
        'percentage' => $percentage,
        'grade' => calculateGrade($conn, $percentage)
    ];
}

function calculateClassPosition($conn, $student_id, $exam_id, $class_id) {
    $students = getStudentsByClass($conn, $class_id);
    $scores = [];
    foreach ($students as $student) {
        $totals = rcTotalsForStudentExam($conn, (int)$student['id'], (int)$exam_id);
        if ($totals['possible'] > 0) {
            $scores[] = ['student_id' => (int)$student['id'], 'obtained' => $totals['obtained']];
        }
    }
    usort($scores, static function ($a, $b) {
        return $b['obtained'] <=> $a['obtained'];
    });

    $rank = 1;
    $previous = null;
    foreach ($scores as $index => $score) {
        if ($previous !== null && $score['obtained'] < $previous) {
            $rank = $index + 1;
        }
        if ((int)$score['student_id'] === (int)$student_id) {
            return $rank;
        }
        $previous = $score['obtained'];
    }
    return null;
}

function getStudentAttendanceSummary($conn, $student_id, $exam_id) {
    $summary = ['present' => 0, 'total' => 0, 'percentage' => 0.00];
    if (!tableExists($conn, 'student_attendance')) {
        return $summary;
    }

    try {
        $exam = rcExamGroup($conn, (int)$exam_id);
        $endDate = $exam['exam_date'] ?? date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total_days,
                   SUM(CASE WHEN status IN ('Present', 'Late', 'Half Day') THEN 1 ELSE 0 END) AS present_days
            FROM student_attendance
            WHERE student_id = ? AND attendance_date <= ?
        ");
        $stmt->execute([(int)$student_id, $endDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary['total'] = (int)($row['total_days'] ?? 0);
        $summary['present'] = (int)($row['present_days'] ?? 0);
        $summary['percentage'] = $summary['total'] > 0 ? round(($summary['present'] / $summary['total']) * 100, 2) : 0.00;
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
    }

    return $summary;
}

function generateResultCard($conn, $student_id, $exam_id, $class_id, $session, $remarks) {
    if (!tableExists($conn, 'result_cards')) {
        return false;
    }

    try {
        $student = getStudentDetailForCard($conn, (int)$student_id);
        if (!$student) {
            return false;
        }

        $totals = rcTotalsForStudentExam($conn, (int)$student_id, (int)$exam_id);
        if (empty($totals['marks'])) {
            return false;
        }

        $position = calculateClassPosition($conn, (int)$student_id, (int)$exam_id, $class_id);
        $attendance = getStudentAttendanceSummary($conn, (int)$student_id, (int)$exam_id);
        $promoted = strtoupper((string)$totals['grade']['grade']) === 'F' ? 0 : 1;

        $stmt = $conn->prepare("
            INSERT INTO result_cards
                (student_id, exam_id, class_id, campus_id, session_year, total_marks_obtained,
                 total_marks_possible, percentage, grade, position_in_class, attendance_present,
                 attendance_total, teacher_remarks, is_promoted, status, generated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ON DUPLICATE KEY UPDATE
                class_id = VALUES(class_id),
                campus_id = VALUES(campus_id),
                session_year = VALUES(session_year),
                total_marks_obtained = VALUES(total_marks_obtained),
                total_marks_possible = VALUES(total_marks_possible),
                percentage = VALUES(percentage),
                grade = VALUES(grade),
                position_in_class = VALUES(position_in_class),
                attendance_present = VALUES(attendance_present),
                attendance_total = VALUES(attendance_total),
                teacher_remarks = VALUES(teacher_remarks),
                is_promoted = VALUES(is_promoted),
                generated_by = VALUES(generated_by)
        ");
        $stmt->execute([
            (int)$student_id,
            (int)$exam_id,
            rcText($class_id),
            !empty($student['campus_id']) ? (int)$student['campus_id'] : null,
            rcText($session),
            $totals['obtained'],
            $totals['possible'],
            $totals['percentage'],
            $totals['grade']['grade'],
            $position,
            $attendance['present'],
            $attendance['total'],
            rcText($remarks),
            $promoted,
            getUserId()
        ]);

        $stmt = $conn->prepare("SELECT id FROM result_cards WHERE student_id = ? AND exam_id = ? LIMIT 1");
        $stmt->execute([(int)$student_id, (int)$exam_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return false;
    }
}

function generateClassResultCards($conn, $class_id, $exam_id, $session, $campus_id = null, $remarks = '') {
    $students = getStudentsByClass($conn, $class_id);
    $success = 0;
    $failed = 0;
    foreach ($students as $student) {
        if ($campus_id !== null && $campus_id !== '' && (int)($student['campus_id'] ?? 0) !== (int)$campus_id) {
            continue;
        }
        generateResultCard($conn, (int)$student['id'], (int)$exam_id, $class_id, $session, $remarks) ? $success++ : $failed++;
    }
    return ['success' => $success, 'failed' => $failed];
}

function recalculateClassPositions($conn, $exam_id, $class_id) {
    if (!tableExists($conn, 'result_cards')) {
        return 0;
    }

    $updated = 0;
    foreach (getStudentsByClass($conn, $class_id) as $student) {
        $position = calculateClassPosition($conn, (int)$student['id'], (int)$exam_id, $class_id);
        if ($position !== null) {
            $stmt = $conn->prepare("UPDATE result_cards SET position_in_class = ? WHERE student_id = ? AND exam_id = ?");
            $stmt->execute([$position, (int)$student['id'], (int)$exam_id]);
            $updated += $stmt->rowCount();
        }
    }
    return $updated;
}

function getResultCardForPrint($conn, $student_id, $exam_id) {
    $student = getStudentDetailForCard($conn, (int)$student_id);
    $exam = rcExamGroup($conn, (int)$exam_id);
    if (!$student || !$exam) {
        return null;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM result_cards WHERE student_id = ? AND exam_id = ? LIMIT 1");
        $stmt->execute([(int)$student_id, (int)$exam_id]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$card) {
            return null;
        }

        $card['student'] = $student;
        $card['exam'] = $exam;
        $card['marks'] = getStudentMarksForExam($conn, (int)$student_id, (int)$exam_id);
        return $card;
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return null;
    }
}

function getResultCardList($conn, $filters = [], $campus_id = null) {
    if (!tableExists($conn, 'result_cards')) {
        return [];
    }

    try {
        $sql = "
            SELECT rc.*, rc.student_id AS student_pk, es.exam_title, es.exam_type, es.section AS exam_section,
                   CONCAT_WS(' ', s.first_name, s.last_name) AS student_name,
                   s.student_id AS student_code, s.roll_number, s.section, s.campus_id,
                   c.name AS campus_name
            FROM result_cards rc
            JOIN students s ON s.id = rc.student_id
            LEFT JOIN exam_schedule es ON es.id = rc.exam_id
            LEFT JOIN campuses c ON c.id = rc.campus_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.roll_number LIKE ?)";
            $search = '%' . rcText($filters['search']) . '%';
            array_push($params, $search, $search, $search, $search);
        }
        if (!empty($filters['class_id'])) {
            $sql .= " AND rc.class_id = ?";
            $params[] = rcText($filters['class_id']);
        }
        if (!empty($filters['exam_id'])) {
            $sql .= " AND rc.exam_id = ?";
            $params[] = (int)$filters['exam_id'];
        }
        if (!empty($filters['session_year'])) {
            $sql .= " AND rc.session_year = ?";
            $params[] = rcText($filters['session_year']);
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['draft', 'published'], true)) {
            $sql .= " AND rc.status = ?";
            $params[] = $filters['status'];
        }
        $campusFilter = $campus_id ?? ($filters['campus_id'] ?? null);
        if ($campusFilter !== null && $campusFilter !== '') {
            $sql .= " AND rc.campus_id = ?";
            $params[] = (int)$campusFilter;
        }

        $sql .= " ORDER BY rc.generated_at DESC, student_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return [];
    }
}

function publishResultCards($conn, $exam_id, $class_id) {
    if (!tableExists($conn, 'result_cards')) {
        return false;
    }

    try {
        $stmt = $conn->prepare("UPDATE result_cards SET status = 'published' WHERE exam_id = ? AND class_id = ?");
        return $stmt->execute([(int)$exam_id, rcText($class_id)]);
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return false;
    }
}

function deleteResultCard($conn, $result_card_id) {
    try {
        $stmt = $conn->prepare("DELETE FROM result_cards WHERE id = ? AND status = 'draft'");
        $stmt->execute([(int)$result_card_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
        return false;
    }
}

function getSchoolInfoForCard($conn) {
    $schoolName = 'Quaid-e-Azam Group of Colleges';
    $phone = '+923338879961';
    $email = 'info@qgc.edu.pk';
    $address = 'College Road, Rajanpur, Punjab';
    $siteStats = __DIR__ . '/site-stats.php';
    if (is_file($siteStats)) {
        require $siteStats;
        $phone = $site_phone ?? $phone;
        $email = $site_email ?? $email;
    }

    return [
        'name' => $schoolName,
        'address' => $address,
        'phone' => $phone,
        'email' => $email,
        'logo' => 'assets/images/qgc-logo.png'
    ];
}

function getResultCardSummary($conn, $campus_id = null) {
    $summary = ['total_cards' => 0, 'published' => 0, 'draft' => 0, 'classes_covered' => 0, 'exams_covered' => 0];
    if (!tableExists($conn, 'result_cards')) {
        return $summary;
    }

    try {
        $where = '';
        $params = [];
        if ($campus_id !== null && $campus_id !== '') {
            $where = 'WHERE campus_id = ?';
            $params[] = (int)$campus_id;
        }
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total_cards,
                   SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                   SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
                   COUNT(DISTINCT class_id) AS classes_covered,
                   COUNT(DISTINCT exam_id) AS exams_covered
            FROM result_cards
            $where
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($summary as $key => $value) {
            $summary[$key] = (int)($row[$key] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Result Cards Error: " . $e->getMessage());
    }
    return $summary;
}

function validateResultCardInput($data) {
    $errors = [];
    $type = rcText($data['generate_type'] ?? 'individual');
    if (!in_array($type, ['individual', 'class'], true)) {
        $errors[] = 'Please select a valid generation type.';
    }
    if ((int)($data['exam_id'] ?? 0) <= 0) {
        $errors[] = 'Please select an exam.';
    }
    if (rcText($data['class_id'] ?? '') === '') {
        $errors[] = 'Please select a class.';
    }
    if ($type === 'individual' && (int)($data['student_id'] ?? 0) <= 0) {
        $errors[] = 'Please select a student.';
    }
    if (rcText($data['session_year'] ?? '') === '') {
        $errors[] = 'Session/year is required.';
    }
    return ['valid' => empty($errors), 'errors' => $errors];
}
