<?php
// File: result-cards-print.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/result_card_functions.php';

if (!isLoggedIn()) {
    redirect('modules/auth/login.php');
}

$conn = (new Database())->getConnection();
$role = getUserRole();
$examId = (int)($_GET['exam_id'] ?? 0);
$studentId = (int)($_GET['student_id'] ?? 0);
$classId = trim((string)($_GET['class_id'] ?? ''));

if ($examId <= 0 || ($studentId <= 0 && $classId === '')) {
    redirect('result-cards.php');
}

if (!in_array($role, ['admin', 'owner', 'teacher', 'student'], true)) {
    redirect('dashboard.php');
}

if ($role === 'student') {
    if ($studentId <= 0 || $classId !== '') {
        setFlashMessage('error', 'You are not allowed to print class result cards.');
        redirect('dashboard.php');
    }

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$studentId, getUserId()]);
    if (!$stmt->fetchColumn()) {
        setFlashMessage('error', 'You are not allowed to view that result card.');
        redirect('dashboard.php');
    }
}

$cards = [];
if ($studentId > 0) {
    $card = getResultCardForPrint($conn, $studentId, $examId);
    if ($card) {
        $cards[] = $card;
    }
} else {
    foreach (getStudentsByClass($conn, $classId) as $student) {
        $card = getResultCardForPrint($conn, (int)$student['id'], $examId);
        if ($card) {
            $cards[] = $card;
        }
    }
}

if (!$cards) {
    redirect('result-cards.php?msg=error');
}

$school = getSchoolInfoForCard($conn);

function rcPrintH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rcOrdinal($number) {
    $number = (int)$number;
    if ($number <= 0) {
        return '-';
    }
    $suffix = 'th';
    if (!in_array($number % 100, [11, 12, 13], true)) {
        $suffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$number % 10] ?? 'th';
    }
    return $number . $suffix;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Result Card</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/print.css">
    <style>
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; font-size: 12px; }
        .print-shell { max-width: 850px; margin: 0 auto; padding: 18px; }
        .result-card-print { border: 1px solid #000; padding: 18px; margin-bottom: 22px; page-break-after: always; }
        .school-head { text-align: center; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 10px; position: relative; min-height: 74px; }
        .school-logo { position: absolute; left: 0; top: 0; width: 70px; height: 70px; object-fit: contain; }
        .school-name { font-size: 22px; font-weight: bold; text-transform: uppercase; margin: 0; }
        .school-meta { font-size: 11px; margin-top: 4px; }
        .title-row { text-align: center; font-weight: bold; font-size: 15px; letter-spacing: .5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px; vertical-align: top; }
        .student-table td { border: 1px solid #999; }
        .photo-cell { width: 95px; text-align: center; }
        .photo-cell img { width: 78px; height: 90px; object-fit: cover; border: 1px solid #000; }
        .marks-table th, .marks-table td { border: .5px solid #999; text-align: center; }
        .marks-table th { background: #f2f2f2; }
        .marks-table td:first-child, .marks-table th:first-child { text-align: left; }
        .total-row td { font-weight: bold; background: #f7f7f7; }
        .summary-box { border: 1px solid #999; padding: 8px; margin-top: 10px; }
        .remarks { min-height: 42px; border: 1px solid #999; padding: 8px; margin-top: 10px; }
        .signature-table td { width: 33.33%; text-align: center; padding-top: 38px; }
        .sig-line { border-top: 1px solid #000; display: inline-block; min-width: 150px; padding-top: 4px; }
        .stamp { display: inline-block; border: 2px solid #000; padding: 4px 12px; font-weight: bold; font-size: 14px; }
        .pass { color: #087f23; border-color: #087f23; }
        .fail { color: #b00020; border-color: #b00020; }
        .grade-strong { font-weight: bold; }
        .no-print-bar { text-align: center; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #ddd; }
        .no-print-bar button { background: #0f2d48; color: #fff; border: 0; border-radius: 4px; padding: 8px 16px; cursor: pointer; }
        @media print {
            .no-print-bar { display: none; }
            .print-shell { max-width: none; padding: 0; }
            .result-card-print { margin: 0; page-break-after: always; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <button onclick="window.print()">Print Result Card</button>
    </div>

    <div class="print-shell">
        <?php foreach ($cards as $card): ?>
            <?php
            $student = $card['student'];
            $exam = $card['exam'];
            $marks = $card['marks'];
            $status = strtoupper((string)$card['grade']) === 'F' ? 'FAIL' : 'PASS';
            $photo = trim((string)($student['photo'] ?? ''));
            $photoPath = $photo !== '' && file_exists(__DIR__ . '/uploads/students/photos/' . $photo)
                ? 'uploads/students/photos/' . $photo
                : 'assets/images/default-student.png';
            ?>
            <div class="result-card-print">
                <div class="school-head">
                    <img class="school-logo" src="<?= rcPrintH($school['logo']) ?>" alt="School Logo">
                    <h1 class="school-name"><?= rcPrintH($school['name']) ?></h1>
                    <?php if (!empty($student['campus_name'])): ?><div><?= rcPrintH($student['campus_name']) ?></div><?php endif; ?>
                    <div class="school-meta"><?= rcPrintH($school['address']) ?> | <?= rcPrintH($school['phone']) ?> | <?= rcPrintH($school['email']) ?></div>
                </div>

                <div class="title-row">ACADEMIC RESULT CARD</div>
                <table class="student-table">
                    <tr>
                        <td><strong>Exam:</strong> <?= rcPrintH($exam['exam_title']) ?></td>
                        <td><strong>Session:</strong> <?= rcPrintH($card['session_year']) ?></td>
                        <td rowspan="4" class="photo-cell"><img src="<?= rcPrintH($photoPath) ?>" alt="Student Photo"></td>
                    </tr>
                    <tr>
                        <td><strong>Student:</strong> <?= rcPrintH(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?></td>
                        <td><strong>Father:</strong> <?= rcPrintH($student['father_name'] ?: $student['guardian_name']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Class:</strong> <?= rcPrintH(($student['class'] ?? '-') . ' - ' . ($student['section'] ?? '-')) ?></td>
                        <td><strong>Roll No:</strong> <?= rcPrintH($student['roll_number'] ?: $student['student_id']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date:</strong> <?= date('d-M-Y') ?></td>
                        <td><strong>Status:</strong> <span class="stamp <?= strtolower($status) ?>"><?= $status ?></span></td>
                    </tr>
                </table>

                <h3 style="font-size:13px;margin:14px 0 6px;">SUBJECT-WISE PERFORMANCE</h3>
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Total Marks</th>
                            <th>Obtained</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                            <th>Pass?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marks as $mark): ?>
                            <tr>
                                <td><?= rcPrintH($mark['subject_name']) ?></td>
                                <td><?= number_format((float)$mark['total_marks'], 2) ?></td>
                                <td><?= number_format((float)$mark['obtained_marks'], 2) ?></td>
                                <td><?= number_format((float)$mark['percentage'], 2) ?>%</td>
                                <td class="<?= $mark['grade'] === 'A+' ? 'grade-strong' : '' ?>"><?= rcPrintH($mark['grade']) ?></td>
                                <td><?= rcPrintH($mark['pass_fail']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td>TOTAL</td>
                            <td><?= number_format((float)$card['total_marks_possible'], 2) ?></td>
                            <td><?= number_format((float)$card['total_marks_obtained'], 2) ?></td>
                            <td><?= number_format((float)$card['percentage'], 2) ?>%</td>
                            <td><?= rcPrintH($card['grade']) ?></td>
                            <td><?= $status ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="summary-box">
                    <strong>Attendance:</strong>
                    <?= (int)$card['attendance_present'] ?>/<?= (int)$card['attendance_total'] ?> days
                    (<?= $card['attendance_total'] ? number_format(((int)$card['attendance_present'] / (int)$card['attendance_total']) * 100, 2) : '0.00' ?>%)
                    &nbsp; | &nbsp;
                    <strong>Position in Class:</strong> <?= rcPrintH(rcOrdinal($card['position_in_class'])) ?>
                </div>

                <div class="remarks">
                    <strong>Teacher Remarks:</strong><br>
                    <?= nl2br(rcPrintH($card['teacher_remarks'] ?: 'Keep working hard and maintain regular study habits.')) ?>
                </div>

                <table class="signature-table">
                    <tr>
                        <td><span class="sig-line">Class Teacher</span></td>
                        <td><span class="sig-line">Principal</span></td>
                        <td><span class="sig-line">Parent Sign</span></td>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</body>
</html>
