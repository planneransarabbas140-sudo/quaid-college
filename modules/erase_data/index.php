<?php
// File: modules/erase_data/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

requireRole('admin');

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = $_SESSION['erase_success'] ?? '';
unset($_SESSION['erase_success']);

function erase_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function erase_execute(PDO $db, $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function erase_table_name($table) {
    return '`' . str_replace('`', '``', $table) . '`';
}

function erase_table_exists(PDO $db, $table) {
    $stmt = erase_execute($db, "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function erase_record_count(PDO $db, $table) {
    if (!erase_table_exists($db, $table)) {
        return 0;
    }
    $stmt = erase_execute($db, 'SELECT COUNT(*) FROM ' . erase_table_name($table));
    return (int)$stmt->fetchColumn();
}

function erase_log_action(PDO $db, $eraseType, array $tablesAffected) {
    erase_execute(
        $db,
        "INSERT INTO erase_logs (erased_by, erase_type, tables_affected, erased_at) VALUES (?, ?, ?, NOW())",
        [getUserId(), $eraseType, implode(', ', $tablesAffected)]
    );
}

try {
    erase_execute($db, "
        CREATE TABLE IF NOT EXISTS erase_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            erased_by INT DEFAULT NULL,
            erase_type VARCHAR(100) NOT NULL,
            tables_affected TEXT NOT NULL,
            erased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    $error = 'Unable to prepare erase logs: ' . $e->getMessage();
}

$moduleCards = [
    'students' => ['Students', 'students', 'fas fa-user-graduate'],
    'staff' => ['Staff', 'staff', 'fas fa-user-tie'],
    'admissions' => ['Admissions', 'admission_applications', 'fas fa-user-plus'],
    'fee_collections' => ['Fee Collections', 'fee_collections', 'fas fa-money-bill-wave'],
    'fee_structure' => ['Fee Structure', 'fee_structure', 'fas fa-list'],
    'student_attendance' => ['Attendance (Student)', 'student_attendance', 'fas fa-calendar-check'],
    'staff_attendance' => ['Attendance (Staff)', 'staff_attendance', 'fas fa-calendar-day'],
    'payroll' => ['Payroll', 'payroll', 'fas fa-file-invoice-dollar'],
    'timetable' => ['Timetable', 'timetable', 'fas fa-clock'],
    'library_books' => ['Library Books', 'library_books', 'fas fa-book'],
    'transport_routes' => ['Transport Routes', 'transport_routes', 'fas fa-bus'],
    'accounts_transactions' => ['Accounts Transactions', 'accounts_transactions', 'fas fa-calculator'],
    'expenses' => ['Expenses', 'expenses', 'fas fa-wallet'],
    'complaints' => ['Complaints', 'complaints', 'fas fa-exclamation-circle'],
    'tasks' => ['Tasks', 'tasks', 'fas fa-tasks'],
    'visitors' => ['Visitors', 'visitors', 'fas fa-id-badge'],
    'inquiries' => ['Inquiries', 'front_desk_inquiries', 'fas fa-question-circle'],
];

$allEraseTables = [
    'fee_transactions',
    'fee_collections',
    'fee_structure',
    'student_attendance',
    'staff_attendance',
    'leave_applications',
    'payroll',
    'timetable',
    'syllabus',
    'accounts_transactions',
    'expenses',
    'income',
    'pos_products',
    'library_books',
    'transport_routes',
    'admission_applications',
    'whatsapp_logs',
    'complaints',
    'tasks',
    'approval_requests',
    'front_desk_inquiries',
    'visitors',
    'students',
    'staff',
    'users',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        requireCsrfToken();

        if ($action === 'erase_module') {
            $moduleKey = $_POST['module_key'] ?? '';
            $confirmName = trim((string)($_POST['confirm_name'] ?? ''));

            if (!isset($moduleCards[$moduleKey])) {
                throw new Exception('Invalid module selected.');
            }

            [$moduleName, $tableName] = $moduleCards[$moduleKey];
            if ($confirmName !== $moduleName) {
                throw new Exception('Please type the module name exactly to confirm.');
            }

            if (!erase_table_exists($db, $tableName)) {
                throw new Exception('The selected table does not exist.');
            }

            erase_execute($db, 'SET FOREIGN_KEY_CHECKS=0');
            erase_execute($db, 'TRUNCATE TABLE ' . erase_table_name($tableName));
            erase_execute($db, 'SET FOREIGN_KEY_CHECKS=1');
            erase_log_action($db, 'Module: ' . $moduleName, [$tableName]);

            $_SESSION['erase_success'] = $moduleName . ' data has been erased.';
            redirect('index.php');
        }

        if ($action === 'erase_all') {
            $confirmation = $_POST['erase_confirmation'] ?? '';
            if ($confirmation !== 'ERASE') {
                throw new Exception('Please type ERASE exactly to confirm.');
            }

            $existingTables = [];
            foreach ($allEraseTables as $tableName) {
                if (erase_table_exists($db, $tableName)) {
                    $existingTables[] = $tableName;
                }
            }

            erase_execute($db, 'SET FOREIGN_KEY_CHECKS=0');
            foreach ($existingTables as $tableName) {
                erase_execute($db, 'TRUNCATE TABLE ' . erase_table_name($tableName));
            }
            erase_execute($db, 'SET FOREIGN_KEY_CHECKS=1');
            erase_log_action($db, 'All Data', $existingTables);

            $_SESSION['erase_success'] = 'All data has been erased.';
            redirect('index.php');
        }
    } catch (Exception $e) {
        try {
            erase_execute($db, 'SET FOREIGN_KEY_CHECKS=1');
        } catch (Exception $ignored) {}
        $error = $e->getMessage();
    }
}

$moduleCounts = [];
foreach ($moduleCards as $key => $module) {
    $moduleCounts[$key] = erase_record_count($db, $module[1]);
}

$page_title = 'Erase All Data';
include '../../includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= erase_h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= erase_h($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        <h2 class="page-title mb-1">Erase All Data</h2>
        <p class="text-muted mb-0">Danger zone: remove all records permanently</p>
    </div>
</div>

<div class="card border-danger shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="text-danger fw-bold mb-2">⚠️ Danger Zone: Erase All Data</h4>
        <p class="mb-0 text-danger">This action permanently deletes all records from the system. This cannot be undone.</p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Erase by Module</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($moduleCards as $key => $module): ?>
                <?php [$moduleName, $tableName, $icon] = $module; ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card h-100 border shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex align-items-center justify-content-center p-3">
                                        <i class="<?= erase_h($icon) ?>"></i>
                                    </span>
                                    <div>
                                        <h6 class="fw-bold mb-0"><?= erase_h($moduleName) ?></h6>
                                        <small class="text-muted"><?= erase_h($tableName) ?></small>
                                    </div>
                                </div>
                                <span class="badge text-bg-secondary"><?= number_format($moduleCounts[$key]) ?></span>
                            </div>
                            <form method="POST" class="erase-module-form" data-module-name="<?= erase_h($moduleName) ?>">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="erase_module">
                                <input type="hidden" name="module_key" value="<?= erase_h($key) ?>">
                                <input type="hidden" name="confirm_name" value="">
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-trash-alt me-2"></i>Erase
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card border-danger shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold text-danger">Erase All Data</h5>
    </div>
    <div class="card-body p-4">
        <div class="border border-danger rounded p-4">
            <p class="fw-bold text-danger mb-3">Type ERASE below and confirm to continue.</p>
            <form method="POST" class="row g-3 align-items-end">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="erase_all">
                <div class="col-lg-8">
                    <label class="form-label fw-bold">Confirmation</label>
                    <input type="text" name="erase_confirmation" class="form-control form-control-lg" placeholder="Type ERASE" autocomplete="off" required>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-danger btn-lg w-100" onclick="return confirm('Erase all system data permanently?');">
                        <i class="fas fa-radiation me-2"></i>Erase All Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.erase-module-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const moduleName = form.dataset.moduleName || '';
            const typed = prompt('Type "' + moduleName + '" to erase this module permanently.');
            if (typed !== moduleName) {
                event.preventDefault();
                alert('Confirmation did not match. Nothing was erased.');
                return;
            }
            form.querySelector('input[name="confirm_name"]').value = typed;
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
