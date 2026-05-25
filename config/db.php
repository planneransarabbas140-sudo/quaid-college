<?php
if (!defined('BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = '/';
    $knownRoots = ['/modules/', '/admin/'];

    foreach ($knownRoots as $root) {
        $pos = strpos($scriptName, $root);
        if ($pos !== false) {
            $basePath = substr($scriptName, 0, $pos + 1);
            break;
        }
    }

    define('BASE_URL', $basePath ?: '/');
}

if (!function_exists('startSecureSession')) {
    function startSecureSession() {
        static $attempted = false;
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        if ($attempted) {
            return;
        }
        $attempted = true;

        $sessionPath = __DIR__ . '/../storage/sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0775, true);
        }
        if (is_dir($sessionPath)) {
            session_save_path($sessionPath);
        }

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => BASE_URL,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        @session_start();
    }
}

startSecureSession();

class Database {
    private $host     = 'switchyard.proxy.rlwy.net';
    private $port     = '52480';
    private $db_name  = 'railway';
    private $username = 'root';
    private $password = 'tpsoZPSwawoCMBtHEBERvDoEgWWOhFcP';
    private $conn;

    public function __construct() {
        $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
        if ($url) {
            $parts = parse_url($url);
            if ($parts !== false) {
                $this->host = $parts['host'] ?? $this->host;
                $this->port = isset($parts['port']) ? (string)$parts['port'] : $this->port;
                $this->username = isset($parts['user']) ? rawurldecode($parts['user']) : $this->username;
                $this->password = isset($parts['pass']) ? rawurldecode($parts['pass']) : $this->password;
                $this->db_name = isset($parts['path']) ? ltrim($parts['path'], '/') : $this->db_name;
            }
        } else {
            $this->host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: $this->host;
            $this->port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: $this->port;
            $this->db_name = getenv('MYSQLDATABASE') ?: getenv('DB_DATABASE') ?: $this->db_name;
            $this->username = getenv('MYSQLUSER') ?: getenv('DB_USERNAME') ?: $this->username;
            $this->password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: $this->password;
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Helper functions
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    return isset($_SESSION['user_id']) && 
           !empty($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return strtolower((string)($_SESSION['user_role'] ?? $_SESSION['role'] ?? 'guest'));
}

function requireRole($roles): void {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    $roles = array_map(static function ($role) {
        return strtolower((string)$role);
    }, $roles);

    if (!isLoggedIn()) {
        redirect(BASE_URL . 'modules/auth/login.php');
    }

    if (!in_array(getUserRole(), $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title></head><body style="font-family:Arial,sans-serif;padding:40px;">';
        echo '<h1>Access Denied</h1><p>You do not have permission to open this page.</p>';
        echo '<p><a href="' . htmlspecialchars(BASE_URL . 'dashboard.php', ENT_QUOTES, 'UTF-8') . '">Return to Dashboard</a></p>';
        echo '</body></html>';
        exit();
    }
}

function enforcePasswordChange() {
    $current = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($_SESSION['must_change_password']) && strpos($current, '/change-password.php') === false) {
        redirect(BASE_URL . 'change-password.php');
    }
}

function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    $token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? '';
    return is_string($token) && $token !== '' && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

function requireCsrfToken() {
    if (!verifyCsrfToken()) {
        throw new Exception('Security check failed. Please refresh the page and try again.');
    }
}

function tableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('tableExists failed: ' . $e->getMessage());
        return false;
    }
}

function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('columnExists failed: ' . $e->getMessage());
        return false;
    }
}

function ensureAdmissionApplicationsTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS admission_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id VARCHAR(30) NOT NULL UNIQUE,
        full_name VARCHAR(150) NOT NULL,
        father_name VARCHAR(150) NOT NULL,
        dob DATE NOT NULL,
        gender VARCHAR(20) NOT NULL,
        cnic VARCHAR(30) NOT NULL,
        religion VARCHAR(50) DEFAULT 'Islam',
        nationality VARCHAR(50) DEFAULT 'Pakistani',
        phone VARCHAR(30) NOT NULL,
        whatsapp VARCHAR(30) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        address TEXT NOT NULL,
        prev_institution VARCHAR(255) NOT NULL,
        matric_roll VARCHAR(50) DEFAULT NULL,
        matric_year VARCHAR(20) DEFAULT NULL,
        matric_total INT DEFAULT 1100,
        matric_obtained INT DEFAULT NULL,
        matric_grade VARCHAR(20) DEFAULT NULL,
        board_name VARCHAR(120) DEFAULT NULL,
        program VARCHAR(120) NOT NULL,
        campus VARCHAR(120) NOT NULL,
        session VARCHAR(30) DEFAULT '2026-2028',
        photo VARCHAR(255) NOT NULL,
        matric_certificate VARCHAR(255) NOT NULL,
        cnic_copy VARCHAR(255) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        transaction_id VARCHAR(120) DEFAULT NULL,
        admission_fee DECIMAL(10,2) DEFAULT 5000.00,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        remarks TEXT DEFAULT NULL,
        student_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_admission_applications_status (status),
        KEY idx_admission_applications_cnic (cnic)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureApprovalSystem(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS approval_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requested_by INT DEFAULT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'guest',
        module_name VARCHAR(100) NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        request_data LONGTEXT NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_remarks TEXT DEFAULT NULL,
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_approval_status (status),
        KEY idx_approval_module (module_name),
        KEY idx_approval_requested_by (requested_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function createApprovalRequest(PDO $db, string $moduleName, string $actionType, array $requestData = []): bool {
    ensureApprovalSystem($db);
    $stmt = $db->prepare("INSERT INTO approval_requests (requested_by, role, module_name, action_type, request_data) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([
        getUserId(),
        getUserRole(),
        $moduleName,
        $actionType,
        json_encode($requestData, JSON_UNESCAPED_UNICODE),
    ]);
}

function setFlashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashMessages(): array {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return is_array($messages) ? $messages : [];
}

function displayFlashMessage(): void {
    foreach (getFlashMessages() as $flash) {
        $type = in_array(($flash['type'] ?? ''), ['success', 'info', 'warning', 'danger', 'error'], true) ? $flash['type'] : 'info';
        $class = $type === 'error' ? 'danger' : $type;
        echo '<div class="alert alert-' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

function applyApprovalRequest(PDO $db, array $request): bool {
    $data = json_decode((string)($request['request_data'] ?? ''), true);
    if (!is_array($data)) {
        $data = [];
    }

    $module = (string)($request['module_name'] ?? '');
    $action = (string)($request['action_type'] ?? '');

    if ($module === 'complaints' && $action === 'create') {
        if (!tableExists($db, 'complaints')) {
            return true;
        }
        $stmt = $db->prepare("INSERT INTO complaints (complaint_number, subject, complainant_name, status, created_at) VALUES (?, ?, ?, 'Pending', NOW())");
        return $stmt->execute([
            $data['complaint_number'] ?? ('CMP-' . date('Ymd') . '-' . random_int(1000, 9999)),
            $data['subject'] ?? 'Complaint',
            $data['complainant_name'] ?? ($_SESSION['username'] ?? 'User'),
        ]);
    }

    return true;
}

function firstExistingColumn(PDO $db, string $table, array $columns): ?string {
    foreach ($columns as $column) {
        if (columnExists($db, $table, (string)$column)) {
            return (string)$column;
        }
    }
    return null;
}

function ensureFinanceTables(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS income (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(100),
        reference_id INT NULL,
        campus VARCHAR(255),
        description TEXT,
        amount DECIMAL(12,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_income_source (source, reference_id),
        KEY idx_income_campus (campus)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module_name VARCHAR(100),
        reference_id INT NULL,
        campus VARCHAR(255),
        category VARCHAR(255),
        description TEXT,
        amount DECIMAL(12,2) DEFAULT 0,
        expense_type ENUM('manual','auto') DEFAULT 'manual',
        status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
        created_by INT,
        approved_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_expenses_module (module_name, reference_id),
        KEY idx_expenses_status (status),
        KEY idx_expenses_campus (campus),
        KEY idx_expenses_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function syncFinancialModuleData(PDO $db): void {
    ensureFinanceTables($db);

    if (tableExists($db, 'fee_collections')) {
        $amountColumn = firstExistingColumn($db, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
        if ($amountColumn) {
            $campusExpr = columnExists($db, 'fee_collections', 'campus') ? 'fc.`campus`' : "''";
            $dateColumn = firstExistingColumn($db, 'fee_collections', ['payment_date', 'paid_at', 'created_at']);
            $dateExpr = $dateColumn ? "COALESCE(fc.`$dateColumn`, NOW())" : 'NOW()';
            $idColumn = columnExists($db, 'fee_collections', 'id') ? 'id' : null;
            if ($idColumn) {
                $db->exec("
                    INSERT INTO income (source, reference_id, campus, description, amount, created_at)
                    SELECT 'fee_collections', fc.`$idColumn`, $campusExpr, 'Fee collection', COALESCE(fc.`$amountColumn`, 0), $dateExpr
                    FROM fee_collections fc
                    WHERE COALESCE(fc.`$amountColumn`, 0) > 0
                      AND NOT EXISTS (
                          SELECT 1 FROM income i WHERE i.source = 'fee_collections' AND i.reference_id = fc.`$idColumn`
                      )
                ");
            }
        }
    }

    if (tableExists($db, 'accounts_transactions')) {
        $db->exec("
            INSERT INTO income (source, reference_id, campus, description, amount, created_at)
            SELECT 'accounts_transactions', tx.id, '', COALESCE(tx.description, tx.category), COALESCE(tx.amount, 0), COALESCE(tx.created_at, NOW())
            FROM accounts_transactions tx
            WHERE tx.transaction_type = 'Income'
              AND COALESCE(tx.amount, 0) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM income i WHERE i.source = 'accounts_transactions' AND i.reference_id = tx.id
              )
        ");
    }
}

function getCurrentAcademicYear(): string {
    $year = (int)date('Y');
    $month = (int)date('n');
    $start = $month >= 4 ? $year : $year - 1;
    return $start . '-' . ($start + 1);
}

function feeTransactionStructureJoin(PDO $db): string {
    if (!tableExists($db, 'fee_structure')) {
        return '';
    }

    if (columnExists($db, 'fee_collections', 'fee_structure_id') && columnExists($db, 'fee_structure', 'id')) {
        return 'LEFT JOIN fee_structure fs ON fs.id = fc.fee_structure_id';
    }

    if (
        columnExists($db, 'fee_collections', 'fee_type')
        && columnExists($db, 'fee_structure', 'fee_type')
        && columnExists($db, 'fee_structure', 'class')
        && columnExists($db, 'students', 'class')
    ) {
        return 'LEFT JOIN fee_structure fs ON fs.fee_type = fc.fee_type AND fs.class = s.class';
    }

    return '';
}

function feeTransactionCreditExpression(PDO $db): string {
    $paidColumn = firstExistingColumn($db, 'fee_collections', ['paid_amount', 'amount_paid', 'payment_amount', 'paid']);
    if ($paidColumn) {
        return "COALESCE(fc.`$paidColumn`, 0)";
    }

    $amountColumn = firstExistingColumn($db, 'fee_collections', ['amount', 'total_amount', 'fee_amount']);
    if (!$amountColumn) {
        return '0';
    }

    if (columnExists($db, 'fee_collections', 'status')) {
        return "CASE WHEN LOWER(COALESCE(fc.status, '')) = 'paid' THEN COALESCE(fc.`$amountColumn`, 0) ELSE 0 END";
    }

    return "COALESCE(fc.`$amountColumn`, 0)";
}

function feeTransactionDebitExpression(PDO $db): string {
    $amountColumn = firstExistingColumn($db, 'fee_collections', ['amount', 'total_amount', 'fee_amount', 'due_amount']);
    if ($amountColumn) {
        return "COALESCE(fc.`$amountColumn`, 0)";
    }

    if (tableExists($db, 'fee_structure') && columnExists($db, 'fee_structure', 'amount')) {
        return 'COALESCE(fs.amount, ' . feeTransactionCreditExpression($db) . ', 0)';
    }

    return feeTransactionCreditExpression($db);
}

function getTransactionSummary(PDO $db): array {
    $summary = ['debit' => 0, 'credit' => 0, 'pending' => 0, 'total_tx' => 0];
    if (!tableExists($db, 'fee_collections')) {
        return $summary;
    }

    $debitExpr = feeTransactionDebitExpression($db);
    $creditExpr = feeTransactionCreditExpression($db);
    $structureJoin = feeTransactionStructureJoin($db);
    $studentJoin = tableExists($db, 'students') ? 'LEFT JOIN students s ON s.id = fc.student_id' : '';

    try {
        $stmt = $db->query("
            SELECT COALESCE(SUM($debitExpr), 0) AS debit,
                   COALESCE(SUM($creditExpr), 0) AS credit,
                   COALESCE(SUM(GREATEST(($debitExpr) - ($creditExpr), 0)), 0) AS pending,
                   COUNT(*) AS total_tx
            FROM fee_collections fc
            $studentJoin
            $structureJoin
        ");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        return [
            'debit' => (float)($row['debit'] ?? 0),
            'credit' => (float)($row['credit'] ?? 0),
            'pending' => (float)($row['pending'] ?? 0),
            'total_tx' => (int)($row['total_tx'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log('getTransactionSummary failed: ' . $e->getMessage());
        return $summary;
    }
}

function getTotalStudentsWithTransactions(PDO $db): int {
    if (!tableExists($db, 'fee_collections') || !columnExists($db, 'fee_collections', 'student_id')) {
        return 0;
    }

    try {
        return (int)$db->query('SELECT COUNT(DISTINCT student_id) FROM fee_collections')->fetchColumn();
    } catch (Throwable $e) {
        error_log('getTotalStudentsWithTransactions failed: ' . $e->getMessage());
        return 0;
    }
}

function getClassList(PDO $db): array {
    $classes = [];

    foreach ([['students', 'class'], ['fee_structure', 'class']] as $source) {
        [$table, $column] = $source;
        if (!tableExists($db, $table) || !columnExists($db, $table, $column)) {
            continue;
        }

        try {
            $rows = $db->query("SELECT DISTINCT `$column` AS class_name FROM `$table` WHERE `$column` IS NOT NULL AND `$column` <> '' ORDER BY `$column` ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $name = trim((string)($row['class_name'] ?? ''));
                if ($name !== '') {
                    $classes[$name] = ['id' => $name, 'class_name' => $name];
                }
            }
        } catch (Throwable $e) {
            error_log("getClassList failed for $table: " . $e->getMessage());
        }
    }

    ksort($classes, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values($classes);
}

function studentDisplayCodeExpression(PDO $db, string $alias = 's'): string {
    $prefix = $alias !== '' ? $alias . '.' : '';
    $parts = [];
    foreach (['roll_number', 'registration_number', 'student_id'] as $column) {
        if (columnExists($db, 'students', $column)) {
            $parts[] = "NULLIF($prefix`$column`, '')";
        }
    }
    $parts[] = "CONCAT('STD-', {$prefix}id)";
    return 'COALESCE(' . implode(', ', $parts) . ')';
}

function getStudentTransactionRegister(PDO $db, string $classId = '', string $search = ''): array {
    if (!tableExists($db, 'students')) {
        return [];
    }

    $studentNameExpr = columnExists($db, 'students', 'first_name')
        ? "TRIM(CONCAT_WS(' ', s.first_name, s.last_name))"
        : (columnExists($db, 'students', 'full_name') ? 's.full_name' : (columnExists($db, 'students', 'name') ? 's.name' : "CONCAT('Student #', s.id)"));
    $studentCodeExpr = studentDisplayCodeExpression($db, 's');
    $classExpr = columnExists($db, 'students', 'class') ? 's.class' : "''";
    $sectionExpr = columnExists($db, 'students', 'section') ? 's.section' : "''";
    $admissionDateExpr = columnExists($db, 'students', 'admission_date') ? 's.admission_date' : 'NULL';
    $familyExpr = columnExists($db, 'students', 'family_id') ? 's.family_id' : "''";
    $openingColumn = firstExistingColumn($db, 'students', ['opening_balance', 'admission_fee']);
    $openingExpr = $openingColumn ? "COALESCE(s.`$openingColumn`, 0)" : '0';

    $feeAggSql = "SELECT NULL AS student_id, 0 AS total_debit, 0 AS total_credit, 0 AS live_pending, 0 AS transaction_count WHERE 1=0";
    if (tableExists($db, 'fee_collections') && columnExists($db, 'fee_collections', 'student_id')) {
        $debitExpr = feeTransactionDebitExpression($db);
        $creditExpr = feeTransactionCreditExpression($db);
        $structureJoin = feeTransactionStructureJoin($db);
        $feeAggSql = "
            SELECT fc.student_id,
                   COALESCE(SUM($debitExpr), 0) AS total_debit,
                   COALESCE(SUM($creditExpr), 0) AS total_credit,
                   COALESCE(SUM(GREATEST(($debitExpr) - ($creditExpr), 0)), 0) AS live_pending,
                   COUNT(*) AS transaction_count
            FROM fee_collections fc
            LEFT JOIN students s ON s.id = fc.student_id
            $structureJoin
            GROUP BY fc.student_id
        ";
    }

    $where = [];
    $params = [];

    if ($classId !== '' && columnExists($db, 'students', 'class')) {
        $where[] = 's.class = ?';
        $params[] = $classId;
    }

    if ($search !== '') {
        $searchParts = [];
        foreach (['first_name', 'last_name', 'full_name', 'name', 'student_id', 'registration_number', 'roll_number'] as $column) {
            if (columnExists($db, 'students', $column)) {
                $searchParts[] = "s.`$column` LIKE ?";
                $params[] = '%' . $search . '%';
            }
        }
        if ($searchParts) {
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $stmt = $db->prepare("
            SELECT s.id AS student_pk,
                   $studentCodeExpr AS student_id,
                   $studentNameExpr AS student_name,
                   $classExpr AS class_name,
                   $sectionExpr AS section,
                   $admissionDateExpr AS admission_date,
                   $familyExpr AS family_id,
                   $openingExpr AS opening_balance,
                   COALESCE(ft.total_debit, 0) AS total_debit,
                   COALESCE(ft.total_credit, 0) AS total_credit,
                   COALESCE(ft.live_pending, 0) AS live_pending,
                   COALESCE(ft.transaction_count, 0) AS transaction_count
            FROM students s
            LEFT JOIN ($feeAggSql) ft ON ft.student_id = s.id
            $whereSql
            ORDER BY class_name ASC, section ASC, student_name ASC, s.id ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('getStudentTransactionRegister failed: ' . $e->getMessage());
        return [];
    }
}

function getStudentList($classId = null, ?PDO $db = null): array {
    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }
    if (!$db instanceof PDO || !tableExists($db, 'students')) {
        return [];
    }

    $studentNameExpr = columnExists($db, 'students', 'first_name')
        ? "TRIM(CONCAT_WS(' ', first_name, last_name))"
        : (columnExists($db, 'students', 'full_name') ? 'full_name' : (columnExists($db, 'students', 'name') ? 'name' : "CONCAT('Student #', id)"));
    $studentCodeExpr = studentDisplayCodeExpression($db, '');
    $classExpr = columnExists($db, 'students', 'class') ? 'class' : "''";
    $sectionExpr = columnExists($db, 'students', 'section') ? 'section' : "''";
    $statusWhere = columnExists($db, 'students', 'status') ? "LOWER(COALESCE(status, 'active')) = 'active'" : '1=1';
    $where = [$statusWhere];
    $params = [];

    if ($classId !== null && $classId !== '' && columnExists($db, 'students', 'class')) {
        $where[] = 'class = ?';
        $params[] = $classId;
    }

    try {
        $stmt = $db->prepare("
            SELECT *, $studentNameExpr AS student_name, $studentCodeExpr AS display_student_id, $classExpr AS class_name, $sectionExpr AS section_name
            FROM students
            WHERE " . implode(' AND ', $where) . "
            ORDER BY class_name ASC, section_name ASC, student_name ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('getStudentList failed: ' . $e->getMessage());
        return [];
    }
}

function getAdmissionCampuses(): array {
    return [
        'Misbah Campus - Rajanpur' => [
            '9th Grade Science',
            '9th Grade Arts',
            '10th Grade Science',
            '10th Grade Arts',
            'FSc Pre-Medical',
            'FSc Pre-Engineering',
            'ICS',
            'FA',
            'BSCS',
            'BSIT',
            'BBA',
            'ADP',
            'Web Development',
            'Graphic Design',
            'NAVTTC IT',
        ],
        'Hamid Campus - Fazilpur' => [
            '9th Grade Science',
            'FSc Pre-Medical',
            'FSc Pre-Engineering',
            'ICS',
            'FA',
            'BSCS',
            'BSIT',
            'BBA',
            'Web Development',
            'Graphic Design',
            'NAVTTC IT',
        ],
        'Abul Rehman Campus - Kot Mithan' => [
            '9th Grade Science',
            '10th Grade Science',
            'FSc Pre-Medical',
            'ICS',
            'FA',
            'BSIT',
            'Computer Applications',
            'NAVTTC IT',
        ],
    ];
}

function getCampusPrograms(string $campus): array {
    $campuses = getAdmissionCampuses();
    $programs = $campuses[$campus] ?? reset($campuses);
    return array_map(static function ($name) {
        return ['name' => $name];
    }, $programs ?: []);
}

function renderCampusOptions(string $selected = ''): void {
    echo '<option value="">Select Campus</option>';
    foreach (array_keys(getAdmissionCampuses()) as $campus) {
        $isSelected = $campus === $selected ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($campus, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>' . htmlspecialchars($campus, ENT_QUOTES, 'UTF-8') . '</option>';
    }
}

function renderProgramOptions(string $selected = ''): void {
    echo '<option value="">Select Program</option>';
    $seen = [];
    foreach (getAdmissionCampuses() as $programs) {
        foreach ($programs as $program) {
            $seen[$program] = true;
        }
    }
    foreach (array_keys($seen) as $program) {
        $isSelected = $program === $selected ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($program, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>' . htmlspecialchars($program, ENT_QUOTES, 'UTF-8') . '</option>';
    }
}

function getCampusProgramScript(string $campusSelectId, string $programSelectId): string {
    $programs = json_encode(getAdmissionCampuses(), JSON_UNESCAPED_SLASHES);
    return "<script>
        const campusPrograms = $programs;
        const campusSelect = document.getElementById(" . json_encode($campusSelectId) . ");
        const programSelect = document.getElementById(" . json_encode($programSelectId) . ");
        function refreshProgramOptions() {
            if (!campusSelect || !programSelect) return;
            const selected = programSelect.value;
            const options = campusPrograms[campusSelect.value] || [];
            programSelect.innerHTML = '<option value=\"\">Select Program</option>';
            options.forEach(function(name) {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                if (name === selected) option.selected = true;
                programSelect.appendChild(option);
            });
        }
        if (campusSelect && programSelect) {
            campusSelect.addEventListener('change', refreshProgramOptions);
            refreshProgramOptions();
        }
    </script>";
}

function generateUniqueId(string $prefix = 'QAC'): string {
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function saveUploadedFile(array $file, string $uploadDir, string $prefix, array $allowedExtensions, int $maxBytes, bool $mustBeImage = false): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception(str_replace('_', ' ', $prefix) . ' upload failed.');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new Exception(str_replace('_', ' ', $prefix) . ' file is too large.');
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception(str_replace('_', ' ', $prefix) . ' file type is not allowed.');
    }
    if ($mustBeImage && !@getimagesize((string)$file['tmp_name'])) {
        throw new Exception(str_replace('_', ' ', $prefix) . ' must be a valid image.');
    }

    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new Exception('Upload directory is not writable.');
    }

    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new Exception('Could not save uploaded file.');
    }

    return 'uploads/admissions/' . $filename;
}

function sanitizeInput($data) {
    return htmlspecialchars(
        strip_tags(trim($data))
    );
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}
?>
