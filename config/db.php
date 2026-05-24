<?php
// File: config/db.php
// Database configuration and connection class

function startSecureSession() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // Fix for environments where PHP cannot write to the default session.save_path (e.g. XAMPP tmp perms).
    // Prefer a writable folder inside the project.
    $currentSavePath = (string)ini_get('session.save_path');
    $projectSessions = realpath(__DIR__ . '/../storage/sessions') ?: (__DIR__ . '/../storage/sessions');

    $isSavePathWritable = false;
    if ($currentSavePath !== '') {
        $checkPath = $currentSavePath;
        // session.save_path can contain extra directives (e.g. "5;path"), keep last segment as path
        if (strpos($checkPath, ';') !== false) {
            $parts = array_filter(array_map('trim', explode(';', $checkPath)));
            $checkPath = end($parts) ?: $checkPath;
        }
        $isSavePathWritable = is_dir($checkPath) && is_writable($checkPath);
    }

    if (!$isSavePathWritable) {
        if (!is_dir($projectSessions)) {
            @mkdir($projectSessions, 0775, true);
        }
        if (is_dir($projectSessions) && is_writable($projectSessions)) {
            ini_set('session.save_path', $projectSessions);
        }
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) === '443')
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

class Database {
    private $host = 'localhost';
    private $port = '3306';
    private $dbname = 'quaid_college_db';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("SET time_zone = '+05:00'");
            ensureCoreBackendSchema($this->conn);
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please contact the system administrator.");
        }
    }
}

// Global helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && getUserRole() !== null;
}

function getUserRole() {
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    if ($role === null || $role === '') {
        return null;
    }

    $role = strtolower((string)$role);
    $_SESSION['role'] = $role;
    $_SESSION['user_role'] = $role;
    return $role;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isPasswordChangeRequired() {
    return !empty($_SESSION['must_change_password']);
}

function enforcePasswordChange() {
    if (!isPasswordChangeRequired()) {
        return;
    }

    $currentScript = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    if (in_array($currentScript, ['change-password.php', 'logout.php'], true)) {
        return;
    }

    setFlashMessage('info', 'Please change your temporary password before continuing.');
    redirect(BASE_URL . 'change-password.php');
}

function requireRole($roles) {
    enforcePasswordChange();

    $roles = is_array($roles) ? $roles : [$roles];
    $role = getUserRole();
    if (!$role || !in_array($role, $roles, true)) {
        setFlashMessage('error', 'You are not allowed to access that section.');
        redirect(BASE_URL . 'dashboard.php');
    }
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken($token = null) {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $token = $token ?? ($_POST['csrf_token'] ?? '');

    return is_string($sessionToken)
        && is_string($token)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
}

function requireCsrfToken() {
    if (!verifyCsrfToken()) {
        throw new Exception('Security check failed. Please refresh the page and try again.');
    }
}

function tableExists(PDO $db, $table) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $db, $table, $column) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function firstExistingColumn(PDO $db, $table, array $columns) {
    foreach ($columns as $column) {
        if (columnExists($db, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function ensureColumn(PDO $db, $table, $column, $definition) {
    if (!tableExists($db, $table) || columnExists($db, $table, $column)) {
        return;
    }

    $db->exec("ALTER TABLE `$table` ADD COLUMN $definition");
}

function safeExecSchema(PDO $db, $sql) {
    try {
        $db->exec($sql);
    } catch (Exception $e) {
        // Schema compatibility should never block the page.
    }
}

function ensureCoreBackendSchema(PDO $db) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    safeExecSchema($db, "CREATE TABLE IF NOT EXISTS campuses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        type VARCHAR(80) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_campus_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach (getCollegeCampuses() as $campus) {
        $stmt = $db->prepare("INSERT IGNORE INTO campuses (name, type, phone) VALUES (?, ?, ?)");
        $stmt->execute([
            $campus['name'] ?? 'Campus',
            $campus['type'] ?? null,
            '+923338879961'
        ]);
    }

    $columnMap = [
        'users' => [
            'must_change_password' => "`must_change_password` TINYINT(1) NOT NULL DEFAULT 0"
        ],
        'students' => [
            'registration_number' => "`registration_number` VARCHAR(50) DEFAULT NULL",
            'father_name' => "`father_name` VARCHAR(150) DEFAULT NULL",
            'phone' => "`phone` VARCHAR(50) DEFAULT NULL",
            'email' => "`email` VARCHAR(150) DEFAULT NULL",
            'campus' => "`campus` VARCHAR(120) DEFAULT NULL",
            'campus_id' => "`campus_id` INT DEFAULT NULL",
            'status' => "`status` VARCHAR(50) NOT NULL DEFAULT 'Active'"
        ],
        'staff' => [
            'user_id' => "`user_id` INT DEFAULT NULL",
            'employee_code' => "`employee_code` VARCHAR(50) DEFAULT NULL",
            'designation' => "`designation` VARCHAR(100) DEFAULT NULL",
            'campus' => "`campus` VARCHAR(120) DEFAULT NULL",
            'campus_id' => "`campus_id` INT DEFAULT NULL",
            'joining_date' => "`joining_date` DATE DEFAULT NULL",
            'status' => "`status` VARCHAR(50) NOT NULL DEFAULT 'Active'",
            'salary' => "`salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00"
        ],
        'fee_collections' => [
            'fee_type' => "`fee_type` VARCHAR(100) DEFAULT NULL",
            'amount' => "`amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00",
            'paid_amount' => "`paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00"
        ],
        'pos_products' => [
            'is_active' => "`is_active` TINYINT(1) NOT NULL DEFAULT 1"
        ],
        'library_books' => [
            'book_id' => "`book_id` VARCHAR(50) DEFAULT NULL",
            'publisher' => "`publisher` VARCHAR(150) DEFAULT NULL",
            'publication_year' => "`publication_year` INT DEFAULT NULL",
            'total_copies' => "`total_copies` INT NOT NULL DEFAULT 1",
            'available_copies' => "`available_copies` INT NOT NULL DEFAULT 1",
            'shelf_number' => "`shelf_number` VARCHAR(80) DEFAULT NULL",
            'description' => "`description` TEXT DEFAULT NULL",
            'status' => "`status` VARCHAR(50) NOT NULL DEFAULT 'Available'"
        ],
        'transport_routes' => [
            'start_point' => "`start_point` VARCHAR(100) DEFAULT NULL",
            'end_point' => "`end_point` VARCHAR(100) DEFAULT NULL",
            'stops' => "`stops` TEXT DEFAULT NULL",
            'distance' => "`distance` VARCHAR(50) DEFAULT NULL",
            'departure_time' => "`departure_time` TIME DEFAULT NULL",
            'return_time' => "`return_time` TIME DEFAULT NULL",
            'vehicle_id' => "`vehicle_id` INT DEFAULT NULL",
            'campus' => "`campus` VARCHAR(80) DEFAULT NULL"
        ],
        'timetable' => [
            'teacher_name' => "`teacher_name` VARCHAR(150) DEFAULT NULL",
            'day' => "`day` VARCHAR(20) DEFAULT NULL",
            'room' => "`room` VARCHAR(50) DEFAULT NULL",
            'campus' => "`campus` VARCHAR(80) DEFAULT NULL"
        ]
    ];

    foreach ($columnMap as $table => $columns) {
        foreach ($columns as $column => $definition) {
            try {
                ensureColumn($db, $table, $column, $definition);
            } catch (Exception $e) {
                // Keep checking the rest of the backend schema.
            }
        }
    }

    safeExecSchema($db, "CREATE TABLE IF NOT EXISTS pos_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_name VARCHAR(150) DEFAULT 'Walk-in',
        class VARCHAR(80) DEFAULT NULL,
        items LONGTEXT NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        transaction_id VARCHAR(100) DEFAULT NULL,
        campus VARCHAR(120) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    safeExecSchema($db, "CREATE TABLE IF NOT EXISTS library_issues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        student_id INT NOT NULL,
        issue_date DATE NOT NULL,
        return_date DATE NOT NULL,
        actual_return DATE DEFAULT NULL,
        fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('Issued','Returned') NOT NULL DEFAULT 'Issued',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_library_issue_book (book_id),
        INDEX idx_library_issue_student (student_id),
        INDEX idx_library_issue_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    safeExecSchema($db, "CREATE TABLE IF NOT EXISTS transport_vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_number VARCHAR(20) UNIQUE,
        vehicle_type VARCHAR(50),
        model VARCHAR(100),
        capacity INT,
        driver_name VARCHAR(100),
        driver_phone VARCHAR(20),
        route VARCHAR(100),
        status ENUM('Active','Inactive','Maintenance') DEFAULT 'Active',
        campus VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    safeExecSchema($db, "CREATE TABLE IF NOT EXISTS transport_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        route_id INT,
        pickup_point VARCHAR(100),
        monthly_fee DECIMAL(10,2),
        status ENUM('Active','Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    safeExecSchema($db, "CREATE TABLE IF NOT EXISTS transport_drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100),
        cnic VARCHAR(20),
        phone VARCHAR(20),
        license_number VARCHAR(50),
        license_expiry DATE,
        address TEXT,
        status ENUM('Active','Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    safeExecSchema($db, "ALTER TABLE transport_routes MODIFY vehicle_number VARCHAR(50) NULL");
    safeExecSchema($db, "ALTER TABLE transport_routes MODIFY driver_name VARCHAR(150) NULL");
    safeExecSchema($db, "ALTER TABLE timetable MODIFY day_of_week VARCHAR(20) NULL");
    safeExecSchema($db, "ALTER TABLE timetable MODIFY period_number INT NULL");
    safeExecSchema($db, "ALTER TABLE timetable MODIFY room_number VARCHAR(50) NULL");

    if (tableExists($db, 'students')) {
        safeExecSchema($db, "UPDATE students SET registration_number = COALESCE(NULLIF(registration_number, ''), student_id) WHERE registration_number IS NULL OR registration_number = ''");
        safeExecSchema($db, "UPDATE students SET father_name = COALESCE(NULLIF(father_name, ''), guardian_name) WHERE father_name IS NULL OR father_name = ''");
        safeExecSchema($db, "UPDATE students SET phone = COALESCE(NULLIF(phone, ''), guardian_phone) WHERE phone IS NULL OR phone = ''");
        safeExecSchema($db, "UPDATE students SET email = COALESCE(NULLIF(email, ''), guardian_email) WHERE email IS NULL OR email = ''");
        safeExecSchema($db, "UPDATE students SET status = 'Active' WHERE status IS NULL OR status = ''");
    }

    if (tableExists($db, 'staff')) {
        safeExecSchema($db, "UPDATE staff SET employee_code = CONCAT('EMP-', LPAD(id, 4, '0')) WHERE employee_code IS NULL OR employee_code = ''");
        safeExecSchema($db, "UPDATE staff SET designation = COALESCE(NULLIF(designation, ''), role, 'Staff') WHERE designation IS NULL OR designation = ''");
        safeExecSchema($db, "UPDATE staff SET status = CASE WHEN COALESCE(is_active, 1) = 1 THEN 'Active' ELSE 'Inactive' END WHERE status IS NULL OR status = ''");
    }

    if (tableExists($db, 'fee_collections')) {
        safeExecSchema($db, "UPDATE fee_collections fc LEFT JOIN fee_structure fs ON fs.id = fc.fee_structure_id SET fc.fee_type = COALESCE(NULLIF(fc.fee_type, ''), fs.fee_type, 'Fee Collection') WHERE fc.fee_type IS NULL OR fc.fee_type = ''");
        safeExecSchema($db, "UPDATE fee_collections SET paid_amount = COALESCE(NULLIF(paid_amount, 0), amount_paid), amount = COALESCE(NULLIF(amount, 0), amount_paid) WHERE COALESCE(amount_paid, 0) > 0");
    }

    if (tableExists($db, 'library_books')) {
        safeExecSchema($db, "UPDATE library_books SET book_id = CONCAT('LIB-', LPAD(id, 3, '0')) WHERE book_id IS NULL OR book_id = ''");
        safeExecSchema($db, "UPDATE library_books SET total_copies = COALESCE(NULLIF(total_copies, 0), quantity, 1)");
        safeExecSchema($db, "UPDATE library_books SET available_copies = COALESCE(NULLIF(available_copies, 0), available, total_copies, 1)");
        safeExecSchema($db, "UPDATE library_books SET status = CASE WHEN available_copies > 0 THEN 'Available' ELSE 'Issued' END WHERE status IS NULL OR status = ''");
    }

    if (tableExists($db, 'timetable')) {
        safeExecSchema($db, "UPDATE timetable SET day = COALESCE(NULLIF(day, ''), day_of_week) WHERE day IS NULL OR day = ''");
        safeExecSchema($db, "UPDATE timetable SET room = COALESCE(NULLIF(room, ''), room_number) WHERE room IS NULL OR room = ''");
    }
}

function ensureApprovalSystem(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS approval_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requested_by INT DEFAULT NULL,
            role VARCHAR(50) NOT NULL,
            module_name VARCHAR(100) NOT NULL,
            action_type VARCHAR(100) NOT NULL,
            request_data LONGTEXT NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_remarks TEXT DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_approval_status (status),
            INDEX idx_approval_module (module_name),
            INDEX idx_approval_requested_by (requested_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function createApprovalRequest(PDO $db, $moduleName, $actionType, array $requestData) {
    ensureApprovalSystem($db);
    $stmt = $db->prepare("
        INSERT INTO approval_requests (requested_by, role, module_name, action_type, request_data, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        getUserId(),
        getUserRole() ?? 'unknown',
        $moduleName,
        $actionType,
        json_encode($requestData, JSON_UNESCAPED_UNICODE)
    ]);
    return (int)$db->lastInsertId();
}

function ensureHomeworkTable(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS homework_diary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        diary_date DATE NOT NULL,
        due_date DATE DEFAULT NULL,
        class VARCHAR(100) NOT NULL,
        section VARCHAR(50) DEFAULT NULL,
        subject VARCHAR(120) NOT NULL,
        title VARCHAR(180) NOT NULL,
        homework TEXT NOT NULL,
        instructions TEXT DEFAULT NULL,
        assigned_by INT DEFAULT NULL,
        status ENUM('Assigned','Completed','Archived') DEFAULT 'Assigned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_diary_class (class),
        INDEX idx_diary_date (diary_date),
        INDEX idx_diary_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureLmsTables(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_name VARCHAR(180) NOT NULL,
        class VARCHAR(120) DEFAULT NULL,
        subject VARCHAR(120) DEFAULT NULL,
        campus VARCHAR(120) DEFAULT NULL,
        teacher_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_course_context (course_name, class, subject, campus)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS course_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT DEFAULT NULL,
        title VARCHAR(220) NOT NULL,
        description TEXT DEFAULT NULL,
        class VARCHAR(120) DEFAULT NULL,
        subject VARCHAR(120) DEFAULT NULL,
        campus VARCHAR(120) DEFAULT NULL,
        original_file_name VARCHAR(255) NOT NULL,
        stored_file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(20) NOT NULL,
        file_size BIGINT NOT NULL DEFAULT 0,
        uploaded_by INT DEFAULT NULL,
        download_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_course_materials_course (course_id),
        INDEX idx_course_materials_filters (class, subject, campus)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getOrCreateCourse(PDO $db, $courseName, $class, $subject, $campus, $teacherId = null) {
    ensureLmsTables($db);
    $name = trim($courseName) !== '' ? trim($courseName) : trim(($class ?: 'General') . ' ' . ($subject ?: 'Course'));
    $stmt = $db->prepare("
        SELECT id FROM courses
        WHERE course_name = ? AND COALESCE(class, '') = ? AND COALESCE(subject, '') = ? AND COALESCE(campus, '') = ?
        LIMIT 1
    ");
    $stmt->execute([$name, $class ?: '', $subject ?: '', $campus ?: '']);
    $existing = $stmt->fetch();
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $db->prepare("INSERT INTO courses (course_name, class, subject, campus, teacher_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $class ?: null, $subject ?: null, $campus ?: null, $teacherId]);
    return (int)$db->lastInsertId();
}

function applyApprovalRequest(PDO $db, array $request) {
    $data = json_decode($request['request_data'] ?? '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    $module = strtolower((string)$request['module_name']);
    $action = strtolower((string)$request['action_type']);

    if ($module === 'homework' || $module === 'daily_diary') {
        ensureHomeworkTable($db);
        $stmt = $db->prepare("INSERT INTO homework_diary
            (diary_date, due_date, class, section, subject, title, homework, instructions, assigned_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['diary_date'] ?? date('Y-m-d'),
            $data['due_date'] ?? null,
            $data['class'] ?? '',
            $data['section'] ?? null,
            $data['subject'] ?? '',
            $data['title'] ?? '',
            $data['homework'] ?? ($data['diary_note'] ?? ''),
            $data['instructions'] ?? null,
            $request['requested_by'] ?? null
        ]);
        return;
    }

    if ($module === 'lms') {
        ensureLmsTables($db);
        $courseId = getOrCreateCourse(
            $db,
            $data['course_name'] ?? '',
            $data['class'] ?? '',
            $data['subject'] ?? '',
            $data['campus'] ?? '',
            $request['requested_by'] ?? null
        );
        $stmt = $db->prepare("INSERT INTO course_materials
            (course_id, title, description, class, subject, campus, original_file_name, stored_file_name, file_path, file_type, file_size, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $courseId,
            $data['title'] ?? '',
            $data['description'] ?? null,
            $data['class'] ?? null,
            $data['subject'] ?? null,
            $data['campus'] ?? null,
            $data['original_file_name'] ?? '',
            $data['stored_file_name'] ?? '',
            $data['file_path'] ?? '',
            $data['file_type'] ?? '',
            (int)($data['file_size'] ?? 0),
            $request['requested_by'] ?? null
        ]);
        return;
    }

    if ($module === 'complaints') {
        if (!tableExists($db, 'complaints')) {
            $db->exec("CREATE TABLE IF NOT EXISTS complaints (
                id INT AUTO_INCREMENT PRIMARY KEY,
                complaint_number VARCHAR(100) NOT NULL UNIQUE,
                subject VARCHAR(255) NOT NULL,
                complainant_name VARCHAR(150) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'Open',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        $stmt = $db->prepare("INSERT INTO complaints (complaint_number, subject, complainant_name) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['complaint_number'] ?? ('CMP-' . date('Ymd') . '-' . random_int(1000, 9999)),
            $data['subject'] ?? '',
            $data['complainant_name'] ?? 'Student'
        ]);
        return;
    }

    if ($module === 'attendance') {
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
        $attendance = $data['attendance'] ?? [];
        foreach ($attendance as $studentId => $status) {
            $stmt = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND attendance_date = ?");
            $stmt->execute([(int)$studentId, $data['date'] ?? date('Y-m-d')]);
            if ($stmt->fetch()) {
                $db->prepare("UPDATE student_attendance SET status = ?, marked_by = ? WHERE student_id = ? AND attendance_date = ?")
                   ->execute([$status, $request['requested_by'] ?? null, (int)$studentId, $data['date'] ?? date('Y-m-d')]);
            } else {
                $db->prepare("INSERT INTO student_attendance (student_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?)")
                   ->execute([(int)$studentId, $data['date'] ?? date('Y-m-d'), $status, $request['requested_by'] ?? null]);
            }
        }
    }
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = htmlspecialchars((string)$_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8');
        $alertClass = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-danger' : 'alert-info');
        echo "<div class='alert {$alertClass} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['flash']);
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function saveUploadedFile(array $file, $uploadDir, $prefix, array $allowedExtensions, $maxBytes, $requireImage = false) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Please select a valid file to upload.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new Exception('Uploaded file size is not allowed.');
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExtensions = array_map('strtolower', $allowedExtensions);
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowedExtensions));
    }

    if ($requireImage && @getimagesize($file['tmp_name']) === false) {
        throw new Exception('Uploaded file must be a valid image.');
    }

    $allowedMimeTypes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    if (isset($allowedMimeTypes[$extension]) && function_exists('finfo_open')) {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo ? finfo_file($fileInfo, $file['tmp_name']) : false;
        if ($fileInfo) {
            finfo_close($fileInfo);
        }

        if ($mimeType && !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
            throw new Exception('Uploaded file content does not match its extension.');
        }
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Upload folder could not be created.');
    }

    if (!is_writable($uploadDir)) {
        throw new Exception('Upload folder is not writable.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid upload source.');
    }

    $safePrefix = preg_replace('/[^a-z0-9_-]+/i', '_', $prefix);
    $fileName = $safePrefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    return $fileName;
}

function generateUniqueId($prefix) {
    return $prefix . '-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function ensureAdmissionApplicationsTable(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS admission_applications (
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
            INDEX idx_admission_applications_status (status),
            INDEX idx_admission_applications_cnic (cnic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function buildStudentValuesFromApplication(PDO $db, array $app, $studentCode) {
    $parts = explode(' ', trim($app['full_name'] ?? ''), 2);
    $values = [
        'first_name' => $parts[0] ?? '',
        'last_name' => $parts[1] ?? '',
        'date_of_birth' => $app['dob'] ?? null,
        'dob' => $app['dob'] ?? null,
        'gender' => $app['gender'] ?? '',
        'blood_group' => '',
        'religion' => $app['religion'] ?? 'Islam',
        'nationality' => $app['nationality'] ?? 'Pakistani',
        'admission_date' => date('Y-m-d'),
        'class' => $app['program'] ?? '',
        'section' => 'A',
        'roll_number' => '',
        'guardian_name' => $app['father_name'] ?? '',
        'guardian_relation' => 'Father',
        'guardian_phone' => $app['phone'] ?? '',
        'guardian_email' => $app['email'] ?? '',
        'father_name' => $app['father_name'] ?? '',
        'cnic' => $app['cnic'] ?? '',
        'phone' => $app['phone'] ?? '',
        'email' => $app['email'] ?? '',
        'address' => $app['address'] ?? '',
        'city' => '',
        'state' => 'Punjab',
        'pin_code' => '',
        'emergency_contact' => $app['whatsapp'] ?? '',
        'medical_info' => '',
        'session' => $app['session'] ?? '2026-2028',
        'status' => 'Active',
        'photo' => $app['photo'] ?? '',
    ];

    if (columnExists($db, 'students', 'student_id')) {
        $values['student_id'] = $studentCode;
    }
    if (columnExists($db, 'students', 'registration_number')) {
        $values['registration_number'] = $studentCode;
    }
    if (columnExists($db, 'students', 'campus_id')) {
        $values['campus_id'] = getOrCreateCampusId($db, $app['campus'] ?? '');
    } elseif (columnExists($db, 'students', 'campus')) {
        $values['campus'] = $app['campus'] ?? '';
    }
    if (columnExists($db, 'students', 'program_id')) {
        $values['program_id'] = getOrCreateProgramId($db, $app['program'] ?? '');
    }

    return array_filter(
        $values,
        fn($value, $column) => columnExists($db, 'students', $column),
        ARRAY_FILTER_USE_BOTH
    );
}

function createStudentLoginFromApplication(PDO $db, array $app, $studentCode, $password) {
    $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $studentCode));
    $username = $baseUsername ?: ('student' . random_int(1000, 9999));
    $suffix = 1;
    while (true) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            break;
        }
        $username = $baseUsername . $suffix;
        $suffix++;
    }

    $email = trim((string)($app['email'] ?? ''));
    if ($email === '') {
        $email = $username . '@students.qgc.local';
    }
    $baseEmail = $email;
    $suffix = 1;
    while (true) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            break;
        }
        $email = preg_replace('/@/', '+' . $suffix . '@', $baseEmail, 1);
        $suffix++;
    }

    $values = [
        'full_name' => $app['full_name'] ?? '',
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'student',
        'phone' => $app['phone'] ?? '',
        'campus' => $app['campus'] ?? 'Rajanpur',
        'is_active' => 1,
        'status' => 'active',
        'must_change_password' => 1,
    ];
    $values = array_filter(
        $values,
        fn($value, $column) => columnExists($db, 'users', $column),
        ARRAY_FILTER_USE_BOTH
    );

    $columns = array_keys($values);
    $placeholders = array_map(fn($column) => ':' . $column, $columns);
    $stmt = $db->prepare("INSERT INTO users (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")");
    $stmt->execute(array_combine($placeholders, array_values($values)));

    return [
        'id' => (int)$db->lastInsertId(),
        'username' => $username,
        'email' => $email,
    ];
}

function approveAdmissionApplication(PDO $db, $applicationId, $adminUserId, $password = '') {
    ensureAdmissionApplicationsTable($db);
    $stmt = $db->prepare("SELECT * FROM admission_applications WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([(int)$applicationId]);
    $app = $stmt->fetch();
    if (!$app) {
        throw new Exception('Admission application not found or already reviewed.');
    }

    if (!in_array($app['program'], array_column(getCampusPrograms($app['campus']), 'name'), true)) {
        throw new Exception('Application program is not available at the selected campus.');
    }

    $dupStudent = $db->prepare("SELECT id FROM students WHERE cnic = ? LIMIT 1");
    $dupStudent->execute([$app['cnic']]);
    if ($dupStudent->fetch()) {
        throw new Exception('A student record with this CNIC already exists.');
    }

    $year = date('Y');
    $cntStmt = $db->query("SELECT COUNT(*) as cnt FROM students WHERE YEAR(admission_date) = $year");
    $cnt = (int)$cntStmt->fetch()['cnt'] + 1;
    $studentCode = "QGC-$year-" . str_pad((string)$cnt, 4, '0', STR_PAD_LEFT);
    $generatedPassword = $password !== '' ? $password : 'QGC@' . random_int(100000, 999999);

    $login = createStudentLoginFromApplication($db, $app, $studentCode, $generatedPassword);

    $studentValues = buildStudentValuesFromApplication($db, $app, $studentCode);
    if (columnExists($db, 'students', 'user_id')) {
        $studentValues['user_id'] = $login['id'];
    }
    $columns = array_keys($studentValues);
    $placeholders = array_map(fn($column) => ':' . $column, $columns);
    $stmt = $db->prepare("INSERT INTO students (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")");
    $stmt->execute(array_combine($placeholders, array_values($studentValues)));
    $studentId = (int)$db->lastInsertId();

    $stmt = $db->prepare("
        UPDATE admission_applications
        SET status = 'approved', student_id = ?, user_id = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$studentId, $login['id'], $adminUserId, (int)$applicationId]);

    return [
        'student_code' => $studentCode,
        'username' => $login['username'],
        'email' => $login['email'],
        'password' => $generatedPassword,
        'phone' => $app['whatsapp'] ?: $app['phone'],
        'student_name' => $app['full_name'],
    ];
}

function getCurrentAcademicYear() {
    $year = date('Y');
    $month = date('m');
    return $month >= 3 ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year;
}

function getStudentDisplayId(array $student) {
    foreach (['student_id', 'registration_number', 'roll_number'] as $key) {
        if (!empty($student[$key])) {
            return $student[$key];
        }
    }

    return 'STU-' . str_pad((string)($student['id'] ?? 0), 4, '0', STR_PAD_LEFT);
}

function getStudentList($class_id = null, PDO $db = null) {
    $db = $db ?: (new Database())->getConnection();
    if (!tableExists($db, 'students')) {
        return [];
    }

    $studentCodeParts = [];
    foreach (['student_id', 'registration_number', 'roll_number'] as $column) {
        if (columnExists($db, 'students', $column)) {
            $studentCodeParts[] = "NULLIF(s.`$column`, '')";
        }
    }
    $studentCodeExpr = $studentCodeParts
        ? 'COALESCE(' . implode(', ', $studentCodeParts) . ", CONCAT('STU-', LPAD(s.id, 4, '0')))"
        : "CONCAT('STU-', LPAD(s.id, 4, '0'))";

    $classExpr = columnExists($db, 'students', 'class')
        ? 's.class'
        : (columnExists($db, 'students', 'class_id') ? 'CAST(s.class_id AS CHAR)' : 'NULL');
    $familyExpr = columnExists($db, 'students', 'family_id') ? 's.family_id' : 'NULL';
    $where = [];
    $params = [];

    if (columnExists($db, 'students', 'status')) {
        $where[] = "LOWER(COALESCE(s.status, 'Active')) = 'active'";
    }

    $class_id = trim((string)($class_id ?? ''));
    if ($class_id !== '') {
        if (columnExists($db, 'students', 'class_id')) {
            $where[] = 's.class_id = :class_id';
        } elseif (columnExists($db, 'students', 'class')) {
            $where[] = 's.class = :class_id';
        }
        $params[':class_id'] = $class_id;
    }

    $stmt = $db->prepare("
        SELECT s.*,
               CONCAT_WS(' ', s.first_name, s.last_name) AS student_name,
               $studentCodeExpr AS display_student_id,
               $classExpr AS class_name,
               $familyExpr AS family_id
        FROM students s
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY class_name ASC, s.first_name ASC, s.last_name ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getClassList(PDO $conn) {
    foreach (['classes', 'class_list', 'tbl_classes'] as $table) {
        if (!tableExists($conn, $table)) {
            continue;
        }

        $idColumn = firstExistingColumn($conn, $table, ['id', 'class_id']);
        $nameColumn = firstExistingColumn($conn, $table, ['class_name', 'name', 'class']);
        if (!$nameColumn) {
            continue;
        }

        $idExpr = $idColumn ? "`$idColumn`" : "`$nameColumn`";
        $stmt = $conn->query("SELECT $idExpr AS id, `$nameColumn` AS class_name FROM `$table` ORDER BY `$nameColumn` ASC");
        return $stmt->fetchAll();
    }

    if (tableExists($conn, 'students') && columnExists($conn, 'students', 'class')) {
        $stmt = $conn->query("
            SELECT DISTINCT class AS id, class AS class_name
            FROM students
            WHERE class IS NOT NULL AND class <> ''
            ORDER BY class ASC
        ");
        return $stmt->fetchAll();
    }

    return [];
}

function feeTransactionStructureJoin(PDO $conn, $collectionAlias = 'fc', $structureAlias = 'fs', $studentAlias = 's') {
    if (!tableExists($conn, 'fee_structure')) {
        return '';
    }

    if (columnExists($conn, 'fee_collections', 'fee_structure_id')) {
        return "LEFT JOIN fee_structure $structureAlias ON $structureAlias.id = $collectionAlias.fee_structure_id";
    }

    if (columnExists($conn, 'fee_collections', 'fee_type')
        && columnExists($conn, 'fee_structure', 'fee_type')
        && columnExists($conn, 'fee_structure', 'class')
        && columnExists($conn, 'students', 'class')) {
        return "LEFT JOIN fee_structure $structureAlias ON $structureAlias.fee_type = $collectionAlias.fee_type AND $structureAlias.class = $studentAlias.class";
    }

    return '';
}

function feeTransactionDebitExpression(PDO $conn, $collectionAlias = 'fc', $structureAlias = 'fs') {
    $debitColumn = firstExistingColumn($conn, 'fee_collections', ['amount']);
    if ($debitColumn) {
        return "COALESCE($collectionAlias.`$debitColumn`, 0)";
    }

    if (tableExists($conn, 'fee_structure') && columnExists($conn, 'fee_structure', 'amount')) {
        return "COALESCE($structureAlias.amount, 0)";
    }

    $paymentColumn = firstExistingColumn($conn, 'fee_collections', ['paid_amount', 'amount_paid']);
    return $paymentColumn ? "COALESCE($collectionAlias.`$paymentColumn`, 0)" : '0';
}

function feeTransactionCreditExpression(PDO $conn, $collectionAlias = 'fc') {
    $paymentColumn = firstExistingColumn($conn, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
    if (!$paymentColumn) {
        return '0';
    }

    $creditExpr = "COALESCE($collectionAlias.`$paymentColumn`, 0)";
    if (!columnExists($conn, 'fee_collections', 'status')) {
        return $creditExpr;
    }

    return "CASE WHEN LOWER(COALESCE($collectionAlias.status, '')) IN ('paid', 'partially paid', 'partial') THEN $creditExpr ELSE 0 END";
}

function getTotalStudentsWithTransactions(PDO $conn) {
    if (!tableExists($conn, 'students') || !tableExists($conn, 'fee_collections')) {
        return 0;
    }

    $stmt = $conn->query("
        SELECT COUNT(DISTINCT s.id)
        FROM students s
        JOIN fee_collections fc ON fc.student_id = s.id
    ");
    return (int)$stmt->fetchColumn();
}

function getTransactionSummary(PDO $conn) {
    $summary = ['debit' => 0, 'credit' => 0, 'pending' => 0, 'total_tx' => 0];
    if (!tableExists($conn, 'students') || !tableExists($conn, 'fee_collections')) {
        return $summary;
    }

    $debitExpr = feeTransactionDebitExpression($conn);
    $creditExpr = feeTransactionCreditExpression($conn);
    $structureJoin = feeTransactionStructureJoin($conn);
    $stmt = $conn->query("
        SELECT COALESCE(SUM($debitExpr), 0) AS debit,
               COALESCE(SUM($creditExpr), 0) AS credit,
               COUNT(DISTINCT fc.id) AS total_tx
        FROM fee_collections fc
        JOIN students s ON s.id = fc.student_id
        $structureJoin
    ");
    $row = $stmt->fetch() ?: [];

    $summary['debit'] = (float)($row['debit'] ?? 0);
    $summary['credit'] = (float)($row['credit'] ?? 0);
    $summary['pending'] = $summary['debit'] - $summary['credit'];
    $summary['total_tx'] = (int)($row['total_tx'] ?? 0);
    return $summary;
}

function getStudentTransactionRegister(PDO $conn, $class_id = null, $search = '') {
    if (!tableExists($conn, 'students') || !tableExists($conn, 'fee_collections')) {
        return [];
    }

    $studentCodeParts = [];
    foreach (['student_id', 'registration_number', 'roll_number'] as $column) {
        if (columnExists($conn, 'students', $column)) {
            $studentCodeParts[] = "NULLIF(s.`$column`, '')";
        }
    }
    $studentCodeExpr = $studentCodeParts
        ? 'COALESCE(' . implode(', ', $studentCodeParts) . ", CONCAT('STU-', LPAD(s.id, 4, '0')))"
        : "CONCAT('STU-', LPAD(s.id, 4, '0'))";
    $classExpr = columnExists($conn, 'students', 'class')
        ? 's.class'
        : (columnExists($conn, 'students', 'class_id') ? 'CAST(s.class_id AS CHAR)' : 'NULL');
    $familyExpr = columnExists($conn, 'students', 'family_id') ? 's.family_id' : 'NULL';
    $debitExpr = feeTransactionDebitExpression($conn);
    $creditExpr = feeTransactionCreditExpression($conn);
    $structureJoin = feeTransactionStructureJoin($conn);
    $where = [];
    $params = [];

    if (columnExists($conn, 'students', 'status')) {
        $where[] = "LOWER(COALESCE(s.status, 'Active')) = 'active'";
    }

    $class_id = trim((string)($class_id ?? ''));
    if ($class_id !== '') {
        if (columnExists($conn, 'students', 'class_id')) {
            $where[] = 's.class_id = :class_id';
        } elseif (columnExists($conn, 'students', 'class')) {
            $where[] = 's.class = :class_id';
        }
        $params[':class_id'] = $class_id;
    }

    $search = trim((string)$search);
    if ($search !== '') {
        $searchColumns = ['s.first_name LIKE :search', 's.last_name LIKE :search'];
        foreach (['student_id', 'registration_number', 'roll_number'] as $column) {
            if (columnExists($conn, 'students', $column)) {
                $searchColumns[] = "s.`$column` LIKE :search";
            }
        }
        $where[] = '(' . implode(' OR ', $searchColumns) . ')';
        $params[':search'] = '%' . $search . '%';
    }

    $groupBy = ['s.id', 's.first_name', 's.last_name'];
    foreach (['student_id', 'registration_number', 'roll_number', 'class', 'class_id', 'section', 'admission_date', 'family_id'] as $column) {
        if (columnExists($conn, 'students', $column)) {
            $groupBy[] = "s.`$column`";
        }
    }

    $stmt = $conn->prepare("
        SELECT s.id AS student_pk,
               $studentCodeExpr AS student_id,
               CONCAT_WS(' ', s.first_name, s.last_name) AS student_name,
               $classExpr AS class_name,
               " . (columnExists($conn, 'students', 'section') ? 's.section' : 'NULL') . " AS section,
               " . (columnExists($conn, 'students', 'admission_date') ? 's.admission_date' : 'NULL') . " AS admission_date,
               $familyExpr AS family_id,
               0 AS opening_balance,
               COALESCE(SUM($debitExpr), 0) AS total_debit,
               COALESCE(SUM($creditExpr), 0) AS total_credit,
               COALESCE(SUM($debitExpr - $creditExpr), 0) AS live_pending,
               COUNT(DISTINCT fc.id) AS transaction_count
        FROM students s
        JOIN fee_collections fc ON fc.student_id = s.id
        $structureJoin
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        GROUP BY " . implode(', ', array_unique($groupBy)) . "
        ORDER BY family_id ASC, class_name ASC, s.first_name ASC, s.last_name ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getCollegeCampuses() {
    return [
        'Rajanpur' => [
            'name' => 'Misbah Campus - Rajanpur',
            'short' => 'Rajanpur',
            'type' => 'Main Campus',
        ],
        'Fazilpur' => [
            'name' => 'Hamid Campus - Fazilpur',
            'short' => 'Fazilpur',
            'type' => 'Sub Campus',
        ],
        'Kot Mithan' => [
            'name' => 'Abul Rehman Campus - Kot Mithan',
            'short' => 'Kot Mithan',
            'type' => 'Sub Campus',
        ],
    ];
}

function getCampusPrograms($campus = null) {
    $common = [
        ['cat' => 'intermediate', 'name' => 'FA Arts', 'duration' => '2 Years'],
        ['cat' => 'intermediate', 'name' => 'FSc Pre-Medical', 'duration' => '2 Years'],
        ['cat' => 'intermediate', 'name' => 'FSc Pre-Engineering', 'duration' => '2 Years'],
        ['cat' => 'intermediate', 'name' => 'ICS Computer Science', 'duration' => '2 Years'],
        ['cat' => 'degree', 'name' => 'BSCS', 'duration' => '4 Years'],
        ['cat' => 'degree', 'name' => 'BSIT', 'duration' => '4 Years'],
        ['cat' => 'degree', 'name' => 'BS Biology', 'duration' => '4 Years'],
        ['cat' => 'degree', 'name' => 'BS Mathematics', 'duration' => '4 Years'],
        ['cat' => 'degree', 'name' => 'B.Ed', 'duration' => '2 Years'],
        ['cat' => 'degree', 'name' => 'ADP Science', 'duration' => '2 Years'],
        ['cat' => 'degree', 'name' => 'ADP Arts', 'duration' => '2 Years'],
        ['cat' => 'navttc', 'name' => 'Web Development', 'duration' => '6 Months'],
        ['cat' => 'navttc', 'name' => 'Digital Marketing', 'duration' => '6 Months'],
        ['cat' => 'navttc', 'name' => 'Graphic Designing', 'duration' => '6 Months'],
        ['cat' => 'navttc', 'name' => 'Computer Office Management', 'duration' => '6 Months'],
    ];

    $programs = [
        'Rajanpur' => array_merge($common, [
            ['cat' => 'navttc', 'name' => 'Mobile Application Development', 'duration' => '6 Months'],
            ['cat' => 'navttc', 'name' => 'Artificial Intelligence', 'duration' => '6 Months'],
        ]),
        'Fazilpur' => array_merge([
            ['cat' => 'matric', 'name' => '9th Grade Science', 'duration' => '1 Year'],
            ['cat' => 'matric', 'name' => '10th Grade Science', 'duration' => '1 Year'],
        ], $common, [
            ['cat' => 'navttc', 'name' => 'UI/UX Design', 'duration' => '6 Months'],
            ['cat' => 'navttc', 'name' => 'Mobile Application Development', 'duration' => '6 Months'],
            ['cat' => 'navttc', 'name' => 'Artificial Intelligence', 'duration' => '6 Months'],
            ['cat' => 'navttc', 'name' => 'Beautician & Parlor', 'duration' => '6 Months'],
        ]),
        'Kot Mithan' => array_merge($common, [
            ['cat' => 'navttc', 'name' => 'UI/UX Design', 'duration' => '6 Months'],
            ['cat' => 'navttc', 'name' => 'Beautician & Parlor', 'duration' => '6 Months'],
        ]),
    ];

    if ($campus !== null) {
        return $programs[$campus] ?? [];
    }

    return $programs;
}

function getProgramCategoryLabel($category) {
    $labels = [
        'matric' => 'Matric',
        'intermediate' => 'Intermediate',
        'degree' => 'Degree Programs',
        'navttc' => 'NAVTTC Short Courses',
    ];
    return $labels[$category] ?? ucwords((string)$category);
}

function renderCampusOptions($selected = '') {
    echo '<option value="">Select Campus</option>';
    foreach (getCollegeCampuses() as $value => $campus) {
        $label = $campus['name'] . ' - ' . $campus['type'];
        $isSelected = $selected === $value ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
}

function renderProgramOptions($selected = '', $campus = null) {
    echo '<option value="">Select Campus First</option>';
    $programsByCampus = $campus ? [$campus => getCampusPrograms($campus)] : getCampusPrograms();
    $seen = [];
    foreach ($programsByCampus as $campusKey => $programs) {
        foreach ($programs as $program) {
            $key = $campusKey . '|' . $program['cat'];
            $seen[$key][] = $program;
        }
    }
    foreach ($seen as $key => $programs) {
        [$campusKey, $category] = explode('|', $key, 2);
        $campusName = getCollegeCampuses()[$campusKey]['name'] ?? $campusKey;
        echo '<optgroup label="' . htmlspecialchars($campusName . ' - ' . getProgramCategoryLabel($category), ENT_QUOTES, 'UTF-8') . '" data-campus="' . htmlspecialchars($campusKey, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($programs as $program) {
            $label = $program['name'] . ' - ' . $program['duration'];
            $isSelected = $selected === $program['name'] ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8') . '" data-campus="' . htmlspecialchars($campusKey, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        echo '</optgroup>';
    }
}

function getCampusProgramScript($campusSelectId = 'campus', $programSelectId = 'program') {
    return "<script>
document.addEventListener('DOMContentLoaded', function() {
    const campusSelect = document.getElementById('{$campusSelectId}');
    const programSelect = document.getElementById('{$programSelectId}');
    if (!campusSelect || !programSelect) return;

    function filterPrograms() {
        const campus = campusSelect.value;
        let firstVisible = null;
        Array.from(programSelect.options).forEach((option) => {
            if (option.value === '') {
                option.textContent = campus ? 'Select Program' : 'Select Campus First';
                option.hidden = false;
                option.disabled = false;
                return;
            }
            const show = option.dataset.campus === campus;
            option.hidden = !show;
            option.disabled = !show;
            if (show && !firstVisible) firstVisible = option;
        });
        Array.from(programSelect.querySelectorAll('optgroup')).forEach((group) => {
            const show = group.dataset.campus === campus;
            group.hidden = !show;
            group.disabled = !show;
        });
        if (!campus || (programSelect.selectedOptions[0] && programSelect.selectedOptions[0].disabled)) {
            programSelect.value = '';
        }
        programSelect.disabled = !campus;
    }

    campusSelect.addEventListener('change', filterPrograms);
    filterPrograms();
});
</script>";
}

function getOrCreateCampusId(PDO $db, $campusValue) {
    if (!tableExists($db, 'campuses')) {
        return null;
    }

    $campus = getCollegeCampuses()[$campusValue] ?? ['name' => $campusValue ?: 'Main Campus'];
    $stmt = $db->prepare("SELECT id FROM campuses WHERE name = ? OR name LIKE ? LIMIT 1");
    $stmt->execute([$campus['name'], '%' . ($campus['short'] ?? $campusValue) . '%']);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }

    $stmt = $db->prepare("INSERT INTO campuses (name) VALUES (?)");
    $stmt->execute([$campus['name']]);
    return (int)$db->lastInsertId();
}

function getOrCreateProgramId(PDO $db, $programName) {
    if (!tableExists($db, 'programs')) {
        return null;
    }

    $stmt = $db->prepare("SELECT id FROM programs WHERE name = ? LIMIT 1");
    $stmt->execute([$programName]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }

    $duration = '2 Years';
    $type = 'Academic';
    foreach (getCampusPrograms() as $programs) {
        foreach ($programs as $program) {
            if ($program['name'] === $programName) {
                $duration = $program['duration'];
                $type = getProgramCategoryLabel($program['cat']);
                break 2;
            }
        }
    }

    $years = stripos($duration, '4') !== false ? 4 : (stripos($duration, '1') !== false ? 1 : 2);
    $semesters = $years * 2;
    $stmt = $db->prepare("INSERT INTO programs (name, type, duration_years, total_semesters) VALUES (?, ?, ?, ?)");
    $stmt->execute([$programName, $type, $years, $semesters]);
    return (int)$db->lastInsertId();
}

function ensureFinanceTables(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_name VARCHAR(100) DEFAULT NULL,
            reference_id INT NULL,
            campus VARCHAR(255) DEFAULT NULL,
            category VARCHAR(255) NOT NULL,
            description TEXT,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            expense_type ENUM('manual','auto') DEFAULT 'manual',
            status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
            created_by INT DEFAULT NULL,
            approved_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_expenses_module (module_name, reference_id),
            INDEX idx_expenses_status (status),
            INDEX idx_expenses_campus (campus),
            INDEX idx_expenses_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $expenseColumns = [
        'module_name' => "ALTER TABLE expenses ADD COLUMN module_name VARCHAR(100) DEFAULT NULL AFTER id",
        'reference_id' => "ALTER TABLE expenses ADD COLUMN reference_id INT NULL AFTER module_name",
        'expense_type' => "ALTER TABLE expenses ADD COLUMN expense_type ENUM('manual','auto') DEFAULT 'manual' AFTER amount",
        'created_by' => "ALTER TABLE expenses ADD COLUMN created_by INT DEFAULT NULL AFTER status",
        'approved_by' => "ALTER TABLE expenses ADD COLUMN approved_by INT NULL AFTER created_by",
        'created_at' => "ALTER TABLE expenses ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($expenseColumns as $column => $sql) {
        if (!columnExists($db, 'expenses', $column)) {
            $db->exec($sql);
        }
    }

    if (!columnExists($db, 'expenses', 'campus')) {
        $db->exec("ALTER TABLE expenses ADD COLUMN campus VARCHAR(255) DEFAULT NULL AFTER reference_id");
    }
    if (!columnExists($db, 'expenses', 'description')) {
        $db->exec("ALTER TABLE expenses ADD COLUMN description TEXT AFTER category");
    }

    try {
        $db->exec("ALTER TABLE expenses MODIFY status ENUM('pending','approved','rejected','paid') DEFAULT 'pending'");
    } catch (Exception $e) {
        $db->exec("UPDATE expenses SET status = LOWER(status)");
    }

    foreach ([
        'idx_expenses_module' => "CREATE INDEX idx_expenses_module ON expenses (module_name, reference_id)",
        'idx_expenses_status' => "CREATE INDEX idx_expenses_status ON expenses (status)",
        'idx_expenses_campus' => "CREATE INDEX idx_expenses_campus ON expenses (campus)",
        'idx_expenses_category' => "CREATE INDEX idx_expenses_category ON expenses (category)"
    ] as $index => $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {}
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS income (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(100) NOT NULL,
            reference_id INT NULL,
            campus VARCHAR(255) DEFAULT NULL,
            description TEXT,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_income_source (source, reference_id),
            INDEX idx_income_campus (campus)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function normalizeFinanceStatus($status) {
    $status = strtolower(trim((string)$status));
    return in_array($status, ['pending', 'approved', 'rejected', 'paid'], true) ? $status : 'pending';
}

function getCampusNameByStudent(PDO $db, $studentId) {
    if (!$studentId || !tableExists($db, 'students')) {
        return null;
    }

    if (columnExists($db, 'students', 'campus')) {
        $stmt = $db->prepare("SELECT campus FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$studentId]);
        return $stmt->fetchColumn() ?: null;
    }

    if (columnExists($db, 'students', 'campus_id') && tableExists($db, 'campuses')) {
        $stmt = $db->prepare("
            SELECT c.name
            FROM students s
            LEFT JOIN campuses c ON c.id = s.campus_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$studentId]);
        return $stmt->fetchColumn() ?: null;
    }

    return null;
}

function normalizeCampusName($campus) {
    $campus = trim((string)$campus);
    if ($campus === '') {
        return 'Unassigned';
    }

    $lower = strtolower($campus);
    if (strpos($lower, 'rajanpur') !== false) {
        return 'Rajanpur Campus';
    }
    if (strpos($lower, 'fazilpur') !== false) {
        return 'Fazilpur Campus';
    }
    if (strpos($lower, 'kot') !== false || strpos($lower, 'mithan') !== false) {
        return 'Kot Mithan Campus';
    }

    return $campus;
}

function recordExpense(PDO $db, array $data) {
    ensureFinanceTables($db);

    $moduleName = $data['module_name'] ?? 'manual';
    $referenceId = isset($data['reference_id']) && $data['reference_id'] !== '' ? (int)$data['reference_id'] : null;
    $campusName = normalizeCampusName($data['campus'] ?? '');

    if ($referenceId !== null) {
        $stmt = $db->prepare("SELECT id FROM expenses WHERE module_name = ? AND reference_id = ? LIMIT 1");
        $stmt->execute([$moduleName, $referenceId]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $stmt = $db->prepare("
                UPDATE expenses
                SET campus = ?, category = ?, description = ?, amount = ?, expense_type = ?, status = ?, created_by = COALESCE(created_by, ?), approved_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $campusName,
                $data['category'] ?? 'Miscellaneous',
                $data['description'] ?? null,
                (float)($data['amount'] ?? 0),
                $data['expense_type'] ?? 'auto',
                normalizeFinanceStatus($data['status'] ?? 'pending'),
                $data['created_by'] ?? getUserId(),
                $data['approved_by'] ?? null,
                $existingId
            ]);
            return (int)$existingId;
        }
    }

    $columns = [];
    $placeholders = [];
    $values = [];
    $insertData = [
        'module_name' => $moduleName,
        'reference_id' => $referenceId,
        'campus' => $campusName,
        'category' => $data['category'] ?? 'Miscellaneous',
        'description' => $data['description'] ?? null,
        'amount' => (float)($data['amount'] ?? 0),
        'expense_type' => $data['expense_type'] ?? 'auto',
        'status' => normalizeFinanceStatus($data['status'] ?? 'pending'),
        'created_by' => $data['created_by'] ?? getUserId(),
        'approved_by' => $data['approved_by'] ?? null,
        'title' => $data['category'] ?? 'Expense',
        'expense_date' => $data['expense_date'] ?? date('Y-m-d'),
        'payment_method' => $data['payment_method'] ?? 'Cash',
        'receipt_no' => $data['receipt_no'] ?? null,
        'month' => $data['month'] ?? date('Y-m'),
        'added_by' => $_SESSION['username'] ?? 'System'
    ];

    foreach ($insertData as $column => $value) {
        if (columnExists($db, 'expenses', $column)) {
            $columns[] = "`$column`";
            $placeholders[] = '?';
            $values[] = $value;
        }
    }

    $stmt = $db->prepare("INSERT INTO expenses (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")");
    $stmt->execute($values);
    return (int)$db->lastInsertId();
}

function updateExpenseStatusByReference(PDO $db, $moduleName, $referenceId, $status, $approvedBy = null) {
    ensureFinanceTables($db);
    $stmt = $db->prepare("UPDATE expenses SET status = ?, approved_by = ? WHERE module_name = ? AND reference_id = ?");
    $stmt->execute([normalizeFinanceStatus($status), $approvedBy ?? getUserId(), $moduleName, (int)$referenceId]);
}

function recordIncome(PDO $db, array $data) {
    ensureFinanceTables($db);

    $source = $data['source'] ?? 'manual';
    $referenceId = isset($data['reference_id']) && $data['reference_id'] !== '' ? (int)$data['reference_id'] : null;
    $campusName = normalizeCampusName($data['campus'] ?? '');

    if ($referenceId !== null) {
        $stmt = $db->prepare("SELECT id FROM income WHERE source = ? AND reference_id = ? LIMIT 1");
        $stmt->execute([$source, $referenceId]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $stmt = $db->prepare("UPDATE income SET campus = ?, description = ?, amount = ? WHERE id = ?");
            $stmt->execute([
                $campusName,
                $data['description'] ?? null,
                (float)($data['amount'] ?? 0),
                $existingId
            ]);
            return (int)$existingId;
        }
    }

    $stmt = $db->prepare("
        INSERT INTO income (source, reference_id, campus, description, amount)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $source,
        $referenceId,
        $campusName,
        $data['description'] ?? null,
        (float)($data['amount'] ?? 0)
    ]);
    return (int)$db->lastInsertId();
}

function syncFinancialModuleData(PDO $db) {
    ensureFinanceTables($db);

    if (tableExists($db, 'fee_collections')) {
        $amountColumn = firstExistingColumn($db, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']);
        if ($amountColumn) {
            $feeTypeColumn = firstExistingColumn($db, 'fee_collections', ['fee_type', 'fee_name']);
            $dateColumn = firstExistingColumn($db, 'fee_collections', ['payment_date', 'created_at']);
            $selectFeeType = $feeTypeColumn ? "fc.`$feeTypeColumn`" : "'Fee Collection'";
            $selectDate = $dateColumn ? "fc.`$dateColumn`" : "fc.created_at";
            $joinCampus = tableExists($db, 'campuses') && columnExists($db, 'students', 'campus_id')
                ? "LEFT JOIN students s ON s.id = fc.student_id LEFT JOIN campuses c ON c.id = s.campus_id"
                : "";
            $selectCampus = $joinCampus ? "c.name" : "NULL";
            $rows = $db->query("
                SELECT fc.id, fc.student_id, fc.`$amountColumn` AS amount, $selectFeeType AS fee_label, $selectDate AS paid_on, $selectCampus AS campus
                FROM fee_collections fc
                $joinCampus
                WHERE COALESCE(fc.`$amountColumn`, 0) > 0
                ORDER BY fc.id DESC
                LIMIT 500
            ")->fetchAll();
            foreach ($rows as $row) {
                recordIncome($db, [
                    'source' => 'fee_management',
                    'reference_id' => $row['id'],
                    'campus' => $row['campus'] ?: getCampusNameByStudent($db, $row['student_id']),
                    'description' => 'Fee payment: ' . ($row['fee_label'] ?? 'Fee'),
                    'amount' => $row['amount']
                ]);
            }
        }
    }

    if (tableExists($db, 'pos_sales') && columnExists($db, 'pos_sales', 'total_amount')) {
        $rows = $db->query("
            SELECT id, campus, student_name, total_amount
            FROM pos_sales
            WHERE COALESCE(total_amount, 0) > 0
            ORDER BY id DESC
            LIMIT 500
        ")->fetchAll();
        foreach ($rows as $row) {
            recordIncome($db, [
                'source' => 'pos',
                'reference_id' => $row['id'],
                'campus' => $row['campus'] ?? null,
                'description' => 'POS sale: ' . ($row['student_name'] ?: 'Walk-in'),
                'amount' => $row['total_amount']
            ]);
        }
    }

    if (tableExists($db, 'payroll') && columnExists($db, 'payroll', 'net_salary')) {
        $staffJoin = '';
        $selectStaffName = "CONCAT('Staff #', p.staff_id)";
        $selectCampus = 'NULL';

        if (tableExists($db, 'staff')) {
            $staffJoin = 'LEFT JOIN staff s ON s.id = p.staff_id';
            if (columnExists($db, 'staff', 'full_name')) {
                $selectStaffName = 's.full_name';
            }

            if (columnExists($db, 'staff', 'campus')) {
                $selectCampus = 's.campus';
            }

            if (tableExists($db, 'campuses') && columnExists($db, 'staff', 'campus_id')) {
                $staffJoin .= ' LEFT JOIN campuses c ON c.id = s.campus_id';
                $selectCampus = 'c.name';
            }
        }

        $rows = $db->query("
            SELECT p.id, p.net_salary, p.status, p.salary_month, $selectStaffName AS full_name, $selectCampus AS campus
            FROM payroll p
            $staffJoin
            WHERE COALESCE(p.net_salary, 0) > 0
            ORDER BY p.id DESC
            LIMIT 500
        ")->fetchAll();
        foreach ($rows as $row) {
            recordExpense($db, [
                'module_name' => 'hr_payroll',
                'reference_id' => $row['id'],
                'campus' => $row['campus'] ?? null,
                'category' => 'Salaries',
                'description' => 'Salary for ' . ($row['full_name'] ?? 'Staff') . ' - ' . ($row['salary_month'] ?? ''),
                'amount' => $row['net_salary'],
                'expense_type' => 'auto',
                'status' => strtolower($row['status'] ?? '') === 'paid' ? 'paid' : 'pending'
            ]);
        }
    }
}

if (!defined('BASE_URL')) {
    $projectFolder = basename(dirname(__DIR__));
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = '/';

    if ($projectFolder !== '' && strpos($scriptName, '/' . $projectFolder . '/') !== false) {
        $basePath = substr($scriptName, 0, strpos($scriptName, '/' . $projectFolder . '/') + strlen('/' . $projectFolder . '/'));
    }

    define('BASE_URL', $basePath);
}

startSecureSession();
?>
