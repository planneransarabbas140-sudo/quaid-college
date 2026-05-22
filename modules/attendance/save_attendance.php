<?php
// File: modules/attendance/save_attendance.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$role = getUserRole();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class = sanitizeInput($_POST['class'] ?? '');
    $section = sanitizeInput($_POST['section'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    $attendance = $_POST['attendance'] ?? [];

    try {
        if (!in_array($role, ['admin', 'teacher'], true)) {
            throw new Exception('You are not allowed to mark attendance.');
        }

        if ($role === 'teacher') {
            createApprovalRequest($db, 'attendance', 'mark_attendance', [
                'class' => $class,
                'section' => $section,
                'date' => $date,
                'attendance' => $attendance
            ]);

            setFlashMessage('success', 'Attendance submitted for admin approval.');
            header('Location: index.php?class=' . urlencode($class) . '&section=' . urlencode($section) . '&date=' . urlencode($date));
            exit;
        }

        $db->exec("CREATE TABLE IF NOT EXISTS student_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Present',
            remarks VARCHAR(255) DEFAULT NULL,
            marked_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_date (student_id, attendance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->beginTransaction();

        foreach ($attendance as $studentId => $status) {
            $studentId = (int)$studentId;
            $status = sanitizeInput($status);

            $check = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND attendance_date = ?");
            $check->execute([$studentId, $date]);

            if ($check->fetch()) {
                $db->prepare("UPDATE student_attendance SET status = ?, marked_by = ? WHERE student_id = ? AND attendance_date = ?")
                   ->execute([$status, getUserId(), $studentId, $date]);
            } else {
                $db->prepare("INSERT INTO student_attendance (student_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?)")
                   ->execute([$studentId, $date, $status, getUserId()]);
            }
        }

        $db->commit();

        $absent = [];
        foreach ($attendance as $studentId => $status) {
            if ($status === 'Absent') {
                $stmt = $db->prepare("SELECT first_name, last_name, guardian_phone FROM students WHERE id = ?");
                $stmt->execute([(int)$studentId]);
                $student = $stmt->fetch();
                if ($student && !empty($student['guardian_phone'])) {
                    $absent[] = $student;
                }
            }
        }

        $_SESSION['absent_students'] = $absent;
        $_SESSION['attendance_class'] = $class;
        $_SESSION['attendance_date'] = $date;

        setFlashMessage('success', 'Attendance saved successfully.');
        header('Location: index.php?saved=1&class=' . urlencode($class) . '&section=' . urlencode($section) . '&date=' . urlencode($date));
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', 'Error: ' . $e->getMessage());
        header('Location: index.php?class=' . urlencode($class) . '&section=' . urlencode($section) . '&date=' . urlencode($date));
        exit;
    }
}
?>
