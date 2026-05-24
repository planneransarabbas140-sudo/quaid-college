<?php
// File: modules/old_data_archive/download-session.php
// Download session data as ZIP of CSVs (production-safe)

require_once __DIR__ . '/../../config/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}
requireRole(['admin', 'owner']);

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo 'Invalid CSRF token';
    exit;
}

$session = trim((string)($_POST['session'] ?? ''));
$allowedSessions = ['2023-24', '2024-25', '2025-26', '2026-27'];
if (!preg_match('/^\d{4}-\d{2}$/', $session) || !in_array($session, $allowedSessions, true)) {
    http_response_code(400);
    echo 'Invalid session';
    exit;
}

$yearStart = (int)substr($session, 0, 4);
if ($yearStart < 2000 || $yearStart > (int)date('Y') + 5) {
    http_response_code(400);
    echo 'Invalid year';
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$tempDir = null;
$zipPath = null;

try {
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive extension is not available on this server.');
    }

    $tempDir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'old_data_' . $session . '_' . time();
    if (!mkdir($tempDir, 0755, true)) {
        throw new Exception('Failed to create temp directory.');
    }

    $files = [];
    $files[] = oda_export_students($pdo, $yearStart, $tempDir, $session);
    $files[] = oda_export_attendance($pdo, $yearStart, $tempDir, $session);
    $files[] = oda_export_fee_records($pdo, $session, $tempDir);
    $files[] = oda_export_exam_marks($pdo, $yearStart, $tempDir, $session);
    $files[] = oda_export_income($pdo, $yearStart, $tempDir, $session);
    $files[] = oda_export_expenses($pdo, $yearStart, $tempDir, $session);
    $files[] = oda_export_transactions($pdo, $yearStart, $tempDir, $session);

    $zipPath = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'school_data_' . $session . '_' . time() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new Exception('Failed to create ZIP file.');
    }

    foreach ($files as $path) {
        if (is_file($path)) {
            $zip->addFile($path, basename($path));
        }
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="school_data_' . $session . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($zipPath);
} catch (Exception $e) {
    error_log('Old Data Archive download error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error generating download.';
} finally {
    if ($zipPath && is_file($zipPath)) {
        @unlink($zipPath);
    }
    if ($tempDir && is_dir($tempDir)) {
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*.csv') as $csv) {
            @unlink($csv);
        }
        @rmdir($tempDir);
    }
}

function oda_csv_open(string $path) {
    $fp = fopen($path, 'w');
    if (!$fp) {
        throw new Exception('Failed to create CSV file.');
    }
    return $fp;
}

function oda_export_students(PDO $pdo, int $yearStart, string $tempDir, string $session) {
    $path = $tempDir . DIRECTORY_SEPARATOR . 'students_' . $session . '.csv';
    $fp = oda_csv_open($path);
    fputcsv($fp, ['Name', 'Class', 'Section', 'Guardian', 'Phone', 'Status', 'Admission Date']);

    $stmt = $pdo->prepare("
        SELECT first_name, last_name, class, section, guardian_name, guardian_phone, status, admission_date
        FROM students
        WHERE YEAR(created_at) = ?
        ORDER BY first_name ASC, last_name ASC
    ");
    $stmt->execute([$yearStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, [
            trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            $row['class'] ?? '',
            $row['section'] ?? '',
            $row['guardian_name'] ?? '',
            $row['guardian_phone'] ?? '',
            $row['status'] ?? '',
            $row['admission_date'] ?? '',
        ]);
    }
    fclose($fp);
    return $path;
}

function oda_export_attendance(PDO $pdo, int $yearStart, string $tempDir, string $session) {
    $path = $tempDir . DIRECTORY_SEPARATOR . 'attendance_' . $session . '.csv';
    $fp = oda_csv_open($path);
    fputcsv($fp, ['Student Name', 'Class', 'Date', 'Status']);

    $stmt = $pdo->prepare("
        SELECT s.first_name, s.last_name, s.class, sa.attendance_date, sa.status
        FROM student_attendance sa
        JOIN students s ON sa.student_id = s.id
        WHERE YEAR(sa.attendance_date) = ?
        ORDER BY sa.attendance_date DESC, s.first_name ASC
    ");
    $stmt->execute([$yearStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, [
            trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            $row['class'] ?? '',
            $row['attendance_date'] ?? '',
            $row['status'] ?? '',
        ]);
    }
    fclose($fp);
    return $path;
}

function oda_export_fee_records(PDO $pdo, string $session, string $tempDir) {
    $path = $tempDir . DIRECTORY_SEPARATOR . 'fee_records_' . $session . '.csv';
    $fp = oda_csv_open($path);
    fputcsv($fp, ['Student', 'Class', 'Fee Type', 'Amount', 'Paid Amount', 'Status', 'Payment Date']);

    $stmt = $pdo->prepare("
        SELECT s.first_name, s.last_name, s.class, fc.fee_type, fc.amount,
               COALESCE(fc.paid_amount, fc.amount_paid, 0) as paid_amount,
               fc.status, fc.payment_date
        FROM fee_collections fc
        JOIN students s ON fc.student_id = s.id
        WHERE fc.academic_year = ?
        ORDER BY COALESCE(fc.payment_date, fc.created_at) DESC, s.first_name ASC
    ");
    $stmt->execute([$session]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, [
            trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            $row['class'] ?? '',
            $row['fee_type'] ?? '',
            $row['amount'] ?? 0,
            $row['paid_amount'] ?? 0,
            $row['status'] ?? '',
            $row['payment_date'] ?? '',
        ]);
    }
    fclose($fp);
    return $path;
}

function oda_export_exam_marks(PDO $pdo, int $yearStart, string $tempDir, string $session) {
    $path = $tempDir . DIRECTORY_SEPARATOR . 'exam_marks_' . $session . '.csv';
    $fp = oda_csv_open($path);
    fputcsv($fp, ['Student', 'Class', 'Subject', 'Total Marks', 'Obtained', 'Grade']);

    $stmt = $pdo->prepare("
        SELECT s.first_name, s.last_name, em.class, em.subject, em.total_marks, em.obtained_marks, em.grade
        FROM exam_marks em
        JOIN students s ON em.student_id = s.id
        WHERE YEAR(em.created_at) = ?
        ORDER BY em.class ASC, s.first_name ASC
    ");
    $stmt->execute([$yearStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, [
            trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            $row['class'] ?? '',
            $row['subject'] ?? '',
            $row['total_marks'] ?? 0,
            $row['obtained_marks'] ?? 0,
            $row['grade'] ?? '',
        ]);
    }
    fclose($fp);
    return $path;
}

function oda_export_income(PDO $pdo, int $yearStart, string $tempDir, string $session) {
    $path = $tempDir . DIRECTORY_SEPARATOR . 'income_' . $session . '.csv';
    $fp = oda_csv_open($path);
    fputcsv($fp, ['Source', 'Campus', 'Description', 'Amount', 'Date']);

    $stmt = $pdo->prepare("
        SELECT source, campus, description, amount, DATE(created_at) as dt
        FROM income
        WHERE YEAR(created_at) = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$yearStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, [
            $row['source'] ?? '',
            $row['campus'] ?? '',
            $row['description'] ?? '',
            $row['amount'] ?? 0,
            $row['dt'] ?? '',
        ]);
    }
    fclose($fp);
    return $path;
}

function oda_export_expenses(PDO $pdo, int $yearStart, string $tempDir, string $session) {
    $path = $tempDir . DIRECTORY_SEPARATOR . 'expenses_' . $session . '.csv';
    $fp = oda_csv_open($path);
    fputcsv($fp, ['Category', 'Campus', 'Description', 'Amount', 'Status', 'Type', 'Date']);

    $stmt = $pdo->prepare("
        SELECT category, campus, description, amount, status, expense_type, DATE(created_at) as dt
        FROM expenses
        WHERE YEAR(created_at) = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$yearStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, [
            $row['category'] ?? '',
            $row['campus'] ?? '',
            $row['description'] ?? '',
            $row['amount'] ?? 0,
            $row['status'] ?? '',
            $row['expense_type'] ?? '',
            $row['dt'] ?? '',
        ]);
    }
    fclose($fp);
    return $path;
}

function oda_export_transactions(PDO $pdo, int $yearStart, string $tempDir, string $session) {
    $path = $tempDir . DIRECTORY_SEPARATOR . 'transactions_' . $session . '.csv';
    $fp = oda_csv_open($path);
    fputcsv($fp, ['Type', 'Category', 'Amount', 'Payment Method', 'Date']);

    $stmt = $pdo->prepare("
        SELECT transaction_type, category, amount, payment_method, transaction_date
        FROM accounts_transactions
        WHERE YEAR(transaction_date) = ?
        ORDER BY transaction_date DESC
    ");
    $stmt->execute([$yearStart]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, [
            $row['transaction_type'] ?? '',
            $row['category'] ?? '',
            $row['amount'] ?? 0,
            $row['payment_method'] ?? '',
            $row['transaction_date'] ?? '',
        ]);
    }
    fclose($fp);
    return $path;
}

