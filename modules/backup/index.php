<?php
// File: modules/backup/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = $_SESSION['backup_success'] ?? '';
$importResults = $_SESSION['backup_import_results'] ?? [];
unset($_SESSION['backup_success'], $_SESSION['backup_import_results']);

function backup_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function backup_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function backup_execute(PDO $db, $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function backup_table_name($table) {
    return '`' . str_replace('`', '``', $table) . '`';
}

function backup_table_exists(PDO $db, $table) {
    $stmt = backup_execute($db, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function backup_column_exists(PDO $db, $table, $column) {
    $stmt = backup_execute($db, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function backup_record_count(PDO $db, $table) {
    if (!backup_table_exists($db, $table)) {
        return 0;
    }
    $stmt = backup_execute($db, 'SELECT COUNT(*) FROM ' . backup_table_name($table));
    return (int)$stmt->fetchColumn();
}

function backup_table_columns(PDO $db, $table) {
    $stmt = backup_execute($db, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION", [$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function backup_log(PDO $db, $type, $size) {
    backup_execute(
        $db,
        "INSERT INTO backup_logs (backup_type, file_size, created_by, created_at) VALUES (?, ?, ?, NOW())",
        [$type, $size, getUserId()]
    );
}

function backup_clean($value) {
    return sanitizeInput((string)($value ?? ''));
}

function backup_download_headers($filename, $contentType) {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function backup_build_sql_dump(PDO $db, array $tables) {
    $dump = "-- Quaid College System SQL Backup\n";
    $dump .= "-- Database: quaid_college_db\n";
    $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        if (!backup_table_exists($db, $table)) {
            continue;
        }

        $createStmt = backup_execute($db, 'SHOW CREATE TABLE ' . backup_table_name($table));
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? '';

        $dump .= "-- Table: $table\n";
        $dump .= "DROP TABLE IF EXISTS " . backup_table_name($table) . ";\n";
        $dump .= $createSql . ";\n\n";

        $rowsStmt = backup_execute($db, 'SELECT * FROM ' . backup_table_name($table));
        while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(fn($column) => backup_table_name($column), array_keys($row));
            $values = array_map(function ($value) use ($db) {
                if ($value === null) {
                    return 'NULL';
                }
                return $db->quote((string)$value);
            }, array_values($row));

            $dump .= 'INSERT INTO ' . backup_table_name($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
        }

        $dump .= "\n";
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

function backup_csv_string(PDO $db, $table) {
    $handle = fopen('php://temp', 'r+');
    $columns = backup_table_columns($db, $table);
    fputcsv($handle, $columns);

    if ($columns) {
        $stmt = backup_execute($db, 'SELECT * FROM ' . backup_table_name($table));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($handle, array_map(fn($column) => $row[$column] ?? '', $columns));
        }
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return $csv;
}

function backup_sample_csv($type) {
    $handle = fopen('php://temp', 'r+');
    if ($type === 'students') {
        fputcsv($handle, ['student_id', 'first_name', 'last_name', 'gender', 'date_of_birth', 'class', 'section', 'guardian_name', 'guardian_phone', 'address']);
        fputcsv($handle, ['STD-001', 'Ali', 'Khan', 'Male', '2008-01-15', '10th', 'A', 'Muhammad Khan', '03001234567', 'Rajanpur']);
    }
    if ($type === 'fee_collections') {
        fputcsv($handle, ['student_id', 'fee_structure_id', 'amount_paid', 'payment_date', 'payment_method', 'transaction_id', 'status', 'academic_year', 'remarks']);
        fputcsv($handle, ['1', '1', '5000', date('Y-m-d'), 'Cash', '', 'Paid', date('Y'), 'Monthly fee']);
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return $csv;
}

function backup_import_students(PDO $db, $filePath) {
    $result = ['imported' => 0, 'skipped' => 0, 'messages' => []];
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Unable to open uploaded CSV.');
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception('CSV file is empty.');
    }
    $headers = array_map(fn($header) => trim((string)$header), $headers);

    $allowedColumns = ['student_id', 'first_name', 'last_name', 'gender', 'date_of_birth', 'class', 'section', 'guardian_name', 'guardian_phone', 'address'];
    $line = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $line++;
        $data = array_combine($headers, array_pad($row, count($headers), ''));
        if (!$data) {
            $result['skipped']++;
            $result['messages'][] = "Line $line skipped: invalid columns.";
            continue;
        }

        $studentCode = backup_clean($data['student_id'] ?? '');
        $firstName = backup_clean($data['first_name'] ?? '');
        $lastName = backup_clean($data['last_name'] ?? '');

        if ($studentCode === '' || $firstName === '' || $lastName === '') {
            $result['skipped']++;
            $result['messages'][] = "Line $line skipped: student_id, first_name, and last_name are required.";
            continue;
        }

        $dup = backup_execute($db, "SELECT id FROM students WHERE student_id = ? LIMIT 1", [$studentCode])->fetch();
        if ($dup) {
            $result['skipped']++;
            $result['messages'][] = "Line $line skipped: duplicate student_id $studentCode.";
            continue;
        }

        $insert = [];
        foreach ($allowedColumns as $column) {
            if (backup_column_exists($db, 'students', $column)) {
                $insert[$column] = backup_clean($data[$column] ?? '');
            }
        }

        $columns = array_keys($insert);
        $sql = 'INSERT INTO students (' . implode(', ', array_map('backup_table_name', $columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        backup_execute($db, $sql, array_values($insert));
        $result['imported']++;
    }

    fclose($handle);
    return $result;
}

function backup_import_fee_collections(PDO $db, $filePath) {
    $result = ['imported' => 0, 'skipped' => 0, 'messages' => []];
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Unable to open uploaded CSV.');
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception('CSV file is empty.');
    }
    $headers = array_map(fn($header) => trim((string)$header), $headers);

    $amountColumn = backup_column_exists($db, 'fee_collections', 'amount_paid') ? 'amount_paid' : (backup_column_exists($db, 'fee_collections', 'paid_amount') ? 'paid_amount' : 'amount');
    $firstFeeStructureId = backup_table_exists($db, 'fee_structure') ? backup_execute($db, "SELECT id FROM fee_structure ORDER BY id LIMIT 1")->fetchColumn() : null;

    $line = 1;
    while (($row = fgetcsv($handle)) !== false) {
        $line++;
        $data = array_combine($headers, array_pad($row, count($headers), ''));
        if (!$data) {
            $result['skipped']++;
            $result['messages'][] = "Line $line skipped: invalid columns.";
            continue;
        }

        $studentId = (int)backup_clean($data['student_id'] ?? '');
        $amount = (float)backup_clean($data[$amountColumn] ?? ($data['amount_paid'] ?? $data['paid_amount'] ?? $data['amount'] ?? 0));
        $paymentDate = backup_clean($data['payment_date'] ?? date('Y-m-d'));

        if ($studentId <= 0 || $amount <= 0 || $paymentDate === '') {
            $result['skipped']++;
            $result['messages'][] = "Line $line skipped: student_id, amount, and payment_date are required.";
            continue;
        }

        $insert = [];
        foreach (['student_id', 'fee_structure_id', 'payment_date', 'payment_method', 'transaction_id', 'status', 'academic_year', 'remarks'] as $column) {
            if (backup_column_exists($db, 'fee_collections', $column)) {
                $insert[$column] = backup_clean($data[$column] ?? '');
            }
        }

        $insert['student_id'] = $studentId;
        $insert[$amountColumn] = $amount;
        $insert['payment_date'] = $paymentDate;
        if (backup_column_exists($db, 'fee_collections', 'fee_structure_id') && empty($insert['fee_structure_id'])) {
            $insert['fee_structure_id'] = $firstFeeStructureId ?: 1;
        }
        if (backup_column_exists($db, 'fee_collections', 'status') && empty($insert['status'])) {
            $insert['status'] = 'Paid';
        }
        if (backup_column_exists($db, 'fee_collections', 'payment_method') && empty($insert['payment_method'])) {
            $insert['payment_method'] = 'Cash';
        }
        if (backup_column_exists($db, 'fee_collections', 'academic_year') && empty($insert['academic_year'])) {
            $insert['academic_year'] = date('Y');
        }

        $columns = array_keys($insert);
        $sql = 'INSERT INTO fee_collections (' . implode(', ', array_map('backup_table_name', $columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        backup_execute($db, $sql, array_values($insert));
        $result['imported']++;
    }

    fclose($handle);
    return $result;
}

function backup_restore_sql(PDO $db, $filePath) {
    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if (!$lines) {
        throw new Exception('SQL file is empty.');
    }

    $firstTen = implode("\n", array_slice($lines, 0, 10));
    if (stripos($firstTen, 'quaid_college') === false) {
        throw new Exception('Security check failed. SQL backup must contain quaid_college in the first 10 lines.');
    }

    $statement = '';
    $count = 0;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }

        $statement .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            backup_execute($db, $statement);
            $statement = '';
            $count++;
        }
    }

    if (trim($statement) !== '') {
        backup_execute($db, $statement);
        $count++;
    }

    return $count;
}

try {
    backup_execute($db, "
        CREATE TABLE IF NOT EXISTS backup_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_type VARCHAR(50) NOT NULL,
            file_size VARCHAR(50) NOT NULL,
            created_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    $error = 'Unable to prepare backup logs: ' . $e->getMessage();
}

$systemTables = [
    'users', 'students', 'admission_applications', 'whatsapp_logs', 'staff', 'complaints', 'tasks',
    'approval_requests', 'front_desk_inquiries', 'visitors', 'fee_structure', 'fee_collections',
    'fee_transactions', 'student_attendance', 'staff_attendance', 'leave_applications', 'payroll',
    'timetable', 'syllabus', 'accounts_transactions', 'expenses', 'income', 'pos_products',
    'library_books', 'transport_routes', 'backup_logs', 'erase_logs'
];

$csvModules = [
    'students' => ['Students', 'students'],
    'staff' => ['Staff', 'staff'],
    'fee_collections' => ['Fee Collections', 'fee_collections'],
    'attendance' => ['Attendance', 'student_attendance'],
    'payroll' => ['Payroll', 'payroll'],
    'admissions' => ['Admissions', 'admission_applications'],
    'accounts_transactions' => ['Accounts Transactions', 'accounts_transactions'],
    'expenses' => ['Expenses', 'expenses'],
];

try {
    $download = $_GET['download'] ?? '';
    if ($download === 'sql') {
        $dump = backup_build_sql_dump($db, $systemTables);
        $size = strlen($dump);
        backup_log($db, 'Full SQL', number_format($size / 1024, 2) . ' KB');
        backup_download_headers('quaid-college-backup-' . date('Y-m-d-His') . '.sql', 'application/sql; charset=utf-8');
        echo $dump;
        exit;
    }

    if ($download === 'csv') {
        $module = $_GET['module'] ?? '';
        if (!isset($csvModules[$module])) {
            throw new Exception('Invalid CSV module selected.');
        }
        [$moduleName, $tableName] = $csvModules[$module];
        if (!backup_table_exists($db, $tableName)) {
            throw new Exception('Selected table does not exist.');
        }
        $csv = backup_csv_string($db, $tableName);
        backup_log($db, 'CSV: ' . $moduleName, number_format(strlen($csv) / 1024, 2) . ' KB');
        backup_download_headers(strtolower(str_replace(' ', '-', $moduleName)) . '-' . date('Y-m-d-His') . '.csv', 'text/csv; charset=utf-8');
        echo $csv;
        exit;
    }

    if ($download === 'sample_students' || $download === 'sample_fees') {
        $type = $download === 'sample_students' ? 'students' : 'fee_collections';
        $csv = backup_sample_csv($type);
        backup_download_headers($type . '-sample.csv', 'text/csv; charset=utf-8');
        echo $csv;
        exit;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        requireCsrfToken();

        if ($action === 'import_students') {
            if (!isset($_FILES['students_csv']) || $_FILES['students_csv']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid students CSV file.');
            }
            $result = backup_import_students($db, $_FILES['students_csv']['tmp_name']);
            $_SESSION['backup_import_results'] = ['Students CSV' => $result];
            $_SESSION['backup_success'] = $result['imported'] . ' students imported, ' . $result['skipped'] . ' skipped.';
            redirect('index.php#import');
        }

        if ($action === 'import_fee_collections') {
            if (!isset($_FILES['fees_csv']) || $_FILES['fees_csv']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid fee collections CSV file.');
            }
            $result = backup_import_fee_collections($db, $_FILES['fees_csv']['tmp_name']);
            $_SESSION['backup_import_results'] = ['Fee Collections CSV' => $result];
            $_SESSION['backup_success'] = $result['imported'] . ' fee records imported, ' . $result['skipped'] . ' skipped.';
            redirect('index.php#import');
        }

        if ($action === 'restore_sql') {
            if (!isset($_FILES['sql_backup']) || $_FILES['sql_backup']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid SQL backup file.');
            }
            $count = backup_restore_sql($db, $_FILES['sql_backup']['tmp_name']);
            $_SESSION['backup_success'] = 'SQL backup restored successfully. ' . $count . ' statements executed.';
            redirect('index.php#import');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$moduleCounts = [];
foreach ($csvModules as $key => $module) {
    $moduleCounts[$key] = backup_record_count($db, $module[1]);
}

$backupLogs = [];
try {
    $backupLogs = backup_execute($db, "
        SELECT bl.*, u.full_name
        FROM backup_logs bl
        LEFT JOIN users u ON u.id = bl.created_by
        ORDER BY bl.created_at DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $error = $error ?: 'Unable to load backup history: ' . $e->getMessage();
}

$page_title = 'Backup & Import';
include '../../includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= backup_h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= backup_h($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        <h2 class="page-title mb-1">Backup & Import</h2>
        <p class="text-muted mb-0">Export database backups, download CSV files, import records, and restore trusted SQL backups.</p>
    </div>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">Backup / Export</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab">Import Data</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="backup" role="tabpanel" tabindex="0">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Export Database Backup</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">Download a complete SQL dump with table structure and INSERT statements.</p>
                <a href="?download=sql" class="btn btn-primary btn-lg">
                    <i class="fas fa-download me-2"></i>Download Full SQL Backup
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Export Data as Excel/CSV</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Module Name</th>
                                <th class="text-end">Records</th>
                                <th class="text-end">Export</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csvModules as $key => $module): ?>
                                <tr>
                                    <td class="fw-bold"><?= backup_h($module[0]) ?></td>
                                    <td class="text-end"><span class="badge text-bg-secondary"><?= number_format($moduleCounts[$key]) ?></span></td>
                                    <td class="text-end">
                                        <a href="?download=csv&module=<?= backup_h($key) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-file-csv me-1"></i>Export CSV
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Backup History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>File Size</th>
                                <th>Created By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$backupLogs): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No backup history yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($backupLogs as $log): ?>
                                <tr>
                                    <td><?= backup_h($log['backup_type']) ?></td>
                                    <td><?= backup_h($log['file_size']) ?></td>
                                    <td><?= backup_h($log['full_name'] ?: 'System') ?></td>
                                    <td><?= backup_h(date('d M Y h:i A', strtotime($log['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="import" role="tabpanel" tabindex="0">
        <?php foreach ($importResults as $label => $result): ?>
            <div class="alert alert-info">
                <strong><?= backup_h($label) ?>:</strong>
                <?= (int)$result['imported'] ?> imported, <?= (int)$result['skipped'] ?> skipped.
                <?php if (!empty($result['messages'])): ?>
                    <ul class="mb-0 mt-2">
                        <?php foreach (array_slice($result['messages'], 0, 8) as $message): ?>
                            <li><?= backup_h($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Import Students from CSV</h5>
                    </div>
                    <div class="card-body">
                        <a href="?download=sample_students" class="btn btn-sm btn-outline-secondary mb-3">
                            <i class="fas fa-download me-1"></i>Download Sample CSV
                        </a>
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="import_students">
                            <label class="form-label fw-bold">Students CSV</label>
                            <input type="file" name="students_csv" class="form-control mb-3" accept=".csv" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Import Students
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Import Fee Collections from CSV</h5>
                    </div>
                    <div class="card-body">
                        <a href="?download=sample_fees" class="btn btn-sm btn-outline-secondary mb-3">
                            <i class="fas fa-download me-1"></i>Download Sample CSV
                        </a>
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="import_fee_collections">
                            <label class="form-label fw-bold">Fee Collections CSV</label>
                            <input type="file" name="fees_csv" class="form-control mb-3" accept=".csv" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Import Fee Collections
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card shadow-sm border-danger">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold text-danger mb-0">Restore from SQL Backup</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            This will execute the SQL file directly. Make sure it is from this system.
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="restore_sql">
                            <label class="form-label fw-bold">SQL Backup File</label>
                            <input type="file" name="sql_backup" class="form-control mb-3" accept=".sql" required>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Restore this SQL backup now?');">
                                <i class="fas fa-database me-2"></i>Restore SQL Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash) {
        const trigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
        if (trigger && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(trigger).show();
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
