<?php
// File: modules/lms/index.php
require_once '../../includes/db.php';

if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$userRole = getUserRole();
$userId = getUserId();
$isAdmin = $userRole === 'admin';
$isTeacher = $userRole === 'teacher';
$isManager = in_array($userRole, ['admin', 'teacher'], true);
$message = '';
$messageType = '';

$uploadRoot = '../../uploads/lms/materials/';
$uploadPathForDb = 'uploads/lms/materials/';
$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png'];
$maxFileSize = 20 * 1024 * 1024; // 20 MB

if (!is_dir($uploadRoot)) {
    mkdir($uploadRoot, 0775, true);
}

function lmsCreateTables(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_name VARCHAR(180) NOT NULL,
            class VARCHAR(120) DEFAULT NULL,
            subject VARCHAR(120) DEFAULT NULL,
            campus VARCHAR(120) DEFAULT NULL,
            teacher_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_course_context (course_name, class, subject, campus)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS course_materials (
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
            INDEX idx_course_materials_filters (class, subject, campus),
            CONSTRAINT fk_course_materials_course
                FOREIGN KEY (course_id) REFERENCES courses(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT DEFAULT NULL,
            title VARCHAR(220) NOT NULL,
            description TEXT DEFAULT NULL,
            due_date DATETIME DEFAULT NULL,
            file_path VARCHAR(255) DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_assignments_course (course_id),
            CONSTRAINT fk_assignments_course
                FOREIGN KEY (course_id) REFERENCES courses(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS assignment_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assignment_id INT NOT NULL,
            student_id INT DEFAULT NULL,
            submission_text TEXT DEFAULT NULL,
            file_path VARCHAR(255) DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'submitted',
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_assignment_submissions_assignment (assignment_id),
            CONSTRAINT fk_assignment_submissions_assignment
                FOREIGN KEY (assignment_id) REFERENCES assignments(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS lms_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(220) NOT NULL,
            message TEXT NOT NULL,
            audience VARCHAR(80) NOT NULL DEFAULT 'All',
            campus VARCHAR(120) DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function lmsFormatBytes(int $bytes): string {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    return round($bytes / 1024, 2) . ' KB';
}

function lmsFileIcon(string $type): array {
    $type = strtolower($type);
    if ($type === 'pdf') return ['fa-file-pdf', '#e74c3c'];
    if (in_array($type, ['doc', 'docx'], true)) return ['fa-file-word', '#2563eb'];
    if (in_array($type, ['ppt', 'pptx'], true)) return ['fa-file-powerpoint', '#ea580c'];
    if ($type === 'zip') return ['fa-file-zipper', '#b7791f'];
    if (in_array($type, ['jpg', 'jpeg', 'png'], true)) return ['fa-file-image', '#16a34a'];
    return ['fa-file', '#64748b'];
}

function lmsGetOrCreateCourse(PDO $db, string $courseName, string $class, string $subject, string $campus, ?int $teacherId): ?int {
    if ($courseName === '' && $class === '' && $subject === '') {
        return null;
    }

    $name = $courseName !== '' ? $courseName : trim(($class ?: 'General') . ' ' . ($subject ?: 'Course'));
    $stmt = $db->prepare("
        SELECT id FROM courses
        WHERE course_name = :course_name
          AND COALESCE(class, '') = :class
          AND COALESCE(subject, '') = :subject
          AND COALESCE(campus, '') = :campus
        LIMIT 1
    ");
    $stmt->execute([
        ':course_name' => $name,
        ':class' => $class,
        ':subject' => $subject,
        ':campus' => $campus,
    ]);
    $existing = $stmt->fetch();
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $db->prepare("
        INSERT INTO courses (course_name, class, subject, campus, teacher_id)
        VALUES (:course_name, :class, :subject, :campus, :teacher_id)
    ");
    $stmt->execute([
        ':course_name' => $name,
        ':class' => $class ?: null,
        ':subject' => $subject ?: null,
        ':campus' => $campus ?: null,
        ':teacher_id' => $teacherId,
    ]);

    return (int)$db->lastInsertId();
}

lmsCreateTables($db);

// Download material before rendering shared header.
if (isset($_GET['download_id'])) {
    $downloadId = (int)$_GET['download_id'];
    $stmt = $db->prepare("SELECT id, original_file_name, file_path, file_type FROM course_materials WHERE id = :id");
    $stmt->execute([':id' => $downloadId]);
    $material = $stmt->fetch();

    if ($material) {
        $absolutePath = '../../' . $material['file_path'];
        $realUploadDir = realpath($uploadRoot);
        $realFile = realpath($absolutePath);

        if ($realUploadDir && $realFile && str_starts_with($realFile, $realUploadDir) && is_file($realFile)) {
            $db->prepare("UPDATE course_materials SET download_count = download_count + 1 WHERE id = :id")
               ->execute([':id' => $downloadId]);

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($material['original_file_name']) . '"');
            header('Content-Length: ' . filesize($realFile));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($realFile);
            exit;
        }
    }

    setFlashMessage('error', 'The requested material file could not be found.');
    redirect('index.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $userRole === 'student' && ($_POST['action'] ?? '') === 'submit_assignment') {
    try {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $submissionText = trim($_POST['submission_text'] ?? '');
        if ($assignmentId <= 0 || $submissionText === '') {
            throw new Exception('Please select an assignment and enter your submission.');
        }

        $student = $db->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $student->execute([$userId]);
        $studentId = (int)($student->fetchColumn() ?: 0);

        $stmt = $db->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, status) VALUES (?, ?, ?, 'submitted')");
        $stmt->execute([$assignmentId, $studentId ?: null, $submissionText]);
        setFlashMessage('success', 'Assignment submitted successfully.');
        redirect('index.php');
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isManager) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'upload_material') {
            $title = sanitizeInput($_POST['title'] ?? '');
            $courseName = sanitizeInput($_POST['course_name'] ?? '');
            $class = sanitizeInput($_POST['class'] ?? '');
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $campus = sanitizeInput($_POST['campus'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $contentExpense = (float)($_POST['content_expense'] ?? 0);

            if ($title === '') {
                throw new Exception('Please enter a material title.');
            }
            if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please select a valid file to upload.');
            }

            $file = $_FILES['material_file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions, true)) {
                throw new Exception('Invalid file type. Allowed files: ' . implode(', ', $allowedExtensions));
            }
            if ((int)$file['size'] <= 0) {
                throw new Exception('The uploaded file is empty.');
            }
            if ((int)$file['size'] > $maxFileSize) {
                throw new Exception('File is too large. Maximum allowed size is 20 MB.');
            }

            $storedName = 'LMS_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $destination = $uploadRoot . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception('Failed to save uploaded file. Please check upload folder permissions.');
            }

            if ($isTeacher) {
                $approvalId = createApprovalRequest($db, 'lms', 'upload_material', [
                    'title' => $title,
                    'course_name' => $courseName,
                    'class' => $class,
                    'subject' => $subject,
                    'campus' => $campus,
                    'description' => $description,
                    'original_file_name' => basename($file['name']),
                    'stored_file_name' => $storedName,
                    'file_path' => $uploadPathForDb . $storedName,
                    'file_type' => $extension,
                    'file_size' => (int)$file['size']
                ]);
                if ($contentExpense > 0) {
                    recordExpense($db, [
                        'module_name' => 'lms_content',
                        'reference_id' => $approvalId,
                        'campus' => $campus,
                        'category' => 'LMS Content',
                        'description' => 'LMS content cost pending approval: ' . $title,
                        'amount' => $contentExpense,
                        'expense_type' => 'auto',
                        'status' => 'pending',
                        'created_by' => getUserId()
                    ]);
                }
                setFlashMessage('success', 'Course material submitted for admin approval.');
            } else {
                $courseId = lmsGetOrCreateCourse($db, $courseName, $class, $subject, $campus, $userId ? (int)$userId : null);

                $stmt = $db->prepare("
                    INSERT INTO course_materials
                        (course_id, title, description, class, subject, campus, original_file_name, stored_file_name, file_path, file_type, file_size, uploaded_by)
                    VALUES
                        (:course_id, :title, :description, :class, :subject, :campus, :original_file_name, :stored_file_name, :file_path, :file_type, :file_size, :uploaded_by)
                ");
                $stmt->execute([
                    ':course_id' => $courseId,
                    ':title' => $title,
                    ':description' => $description ?: null,
                    ':class' => $class ?: null,
                    ':subject' => $subject ?: null,
                    ':campus' => $campus ?: null,
                    ':original_file_name' => basename($file['name']),
                    ':stored_file_name' => $storedName,
                    ':file_path' => $uploadPathForDb . $storedName,
                    ':file_type' => $extension,
                    ':file_size' => (int)$file['size'],
                    ':uploaded_by' => $userId,
                ]);
                $materialId = (int)$db->lastInsertId();
                if ($contentExpense > 0) {
                    recordExpense($db, [
                        'module_name' => 'lms_content',
                        'reference_id' => $materialId,
                        'campus' => $campus,
                        'category' => 'LMS Content',
                        'description' => 'LMS content/material cost: ' . $title,
                        'amount' => $contentExpense,
                        'expense_type' => 'auto',
                        'status' => 'pending',
                        'created_by' => getUserId()
                    ]);
                }

                setFlashMessage('success', 'Course material uploaded successfully.');
            }
            redirect('index.php');
        }

        if ($action === 'delete_material') {
            if (!$isAdmin) {
                throw new Exception('Only admin can delete LMS material.');
            }
            $materialId = (int)($_POST['material_id'] ?? 0);
            $stmt = $db->prepare("SELECT file_path FROM course_materials WHERE id = :id");
            $stmt->execute([':id' => $materialId]);
            $material = $stmt->fetch();

            if (!$material) {
                throw new Exception('Material not found.');
            }

            $absolutePath = '../../' . $material['file_path'];
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }

            $db->prepare("DELETE FROM course_materials WHERE id = :id")->execute([':id' => $materialId]);
            setFlashMessage('success', 'Course material deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

$search = trim($_GET['search'] ?? '');
$filterClass = trim($_GET['class'] ?? '');
$filterSubject = trim($_GET['subject'] ?? '');
$filterCampus = trim($_GET['campus'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(cm.title LIKE :search OR cm.description LIKE :search OR cm.original_file_name LIKE :search OR c.course_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($filterClass !== '') {
    $where[] = "cm.class = :class";
    $params[':class'] = $filterClass;
}
if ($filterSubject !== '') {
    $where[] = "cm.subject = :subject";
    $params[':subject'] = $filterSubject;
}
if ($filterCampus !== '') {
    $where[] = "cm.campus = :campus";
    $params[':campus'] = $filterCampus;
}

$sql = "
    SELECT cm.*, c.course_name
    FROM course_materials cm
    LEFT JOIN courses c ON c.id = cm.course_id
";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY cm.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$courses = $db->query("SELECT * FROM courses ORDER BY created_at DESC LIMIT 8")->fetchAll();
$availableAssignments = $db->query("SELECT a.*, c.course_name FROM assignments a LEFT JOIN courses c ON c.id = a.course_id ORDER BY a.created_at DESC LIMIT 20")->fetchAll();
$stats = [
    'courses' => (int)$db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'materials' => (int)$db->query("SELECT COUNT(*) FROM course_materials")->fetchColumn(),
    'assignments' => (int)$db->query("SELECT COUNT(*) FROM assignments")->fetchColumn(),
    'downloads' => (int)$db->query("SELECT COALESCE(SUM(download_count), 0) FROM course_materials")->fetchColumn(),
];

$classes = ['Matric', 'FA', 'FSc Pre-Medical', 'FSc Pre-Engineering', 'ICS', 'ADP Science', 'ADP Arts', 'BSCS', 'BSIT', 'BS Biology', 'BS Mathematics', 'B.Ed', 'NAVTTC'];
$subjects = ['English', 'Urdu', 'Islamic Studies', 'Pakistan Studies', 'Physics', 'Chemistry', 'Biology', 'Mathematics', 'Computer Science', 'Web Development', 'Digital Marketing', 'Graphic Design', 'AI', 'General'];
$campuses = ['Misbah Campus - Rajanpur', 'Hamid Campus - Fazilpur', 'Abul Rehman Campus - Kotmithan'];

$page_title = "Learning Management System";
include '../../includes/header.php';
?>

<style>
    .lms-hero {
        background: linear-gradient(135deg, #1a2f4a, #0c3547);
        border-radius: 18px;
        padding: 28px;
        color: #fff;
        box-shadow: 0 20px 55px rgba(26,47,74,.18);
        overflow: hidden;
        position: relative;
    }
    .lms-hero::after {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        right: -80px;
        top: -110px;
        border-radius: 50%;
        border: 42px solid rgba(42,181,160,.14);
    }
    .lms-hero h2 { font-weight: 900; position: relative; z-index: 1; }
    .lms-hero p { color: rgba(255,255,255,.72); max-width: 720px; position: relative; z-index: 1; }
    .lms-stat {
        background: #fff;
        border: 1px solid #e5edf6;
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 12px 32px rgba(26,47,74,.07);
    }
    .lms-stat i {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: inline-grid;
        place-items: center;
        color: #2ab5a0;
        background: rgba(42,181,160,.12);
        margin-bottom: 12px;
    }
    .lms-stat strong { display: block; font-size: 1.55rem; line-height: 1; color: #1a2f4a; }
    .lms-stat span { color: #64748b; font-size: .78rem; font-weight: 700; }
    .material-card {
        border: 1px solid #e5edf6;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 14px 36px rgba(26,47,74,.07);
        transition: 240ms ease;
        height: 100%;
    }
    .material-card:hover { transform: translateY(-4px); box-shadow: 0 22px 55px rgba(26,47,74,.12); border-color: rgba(42,181,160,.45); }
    .file-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: #f8fafc;
        font-size: 1.45rem;
    }
    .lms-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        background: rgba(42,181,160,.1);
        color: #168f7f;
        font-size: .72rem;
        font-weight: 800;
        margin: 0 5px 6px 0;
    }
    .empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 18px;
        background: #fff;
        padding: 54px 24px;
        text-align: center;
    }
    #uploadMaterialModal {
        z-index: 1305;
    }
    #uploadMaterialModal .modal-content {
        border-radius: 18px;
        overflow: hidden;
    }
    #uploadMaterialModal .modal-body {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
    }
    .modal-backdrop {
        z-index: 1290;
    }
    @media (min-width: 993px) {
        #uploadMaterialModal {
            left: 260px;
            width: calc(100% - 260px);
            padding-left: 24px !important;
            padding-right: 24px !important;
        }
        #uploadMaterialModal .modal-dialog {
            max-width: min(920px, calc(100vw - 340px));
            margin-left: auto;
            margin-right: auto;
        }
        body:has(#sidebar.active) #uploadMaterialModal {
            left: 0;
            width: 100%;
        }
        body:has(#sidebar.active) #uploadMaterialModal .modal-dialog {
            max-width: min(920px, calc(100vw - 48px));
        }
    }
    @media (max-width: 992px) {
        #uploadMaterialModal {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }
        #uploadMaterialModal .modal-body {
            max-height: calc(100vh - 180px);
        }
    }
</style>

<div class="container-fluid">
    <div class="lms-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <h2 class="mb-2">Learning Management System</h2>
                <p class="mb-0">Upload, manage, browse and download class material for every campus from one central LMS workspace.</p>
            </div>
            <?php if ($isManager): ?>
                <button class="btn btn-light fw-bold px-4 py-2 rounded-pill position-relative z-1" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
                    <i class="fas fa-cloud-upload-alt me-2 text-success"></i><?= $isTeacher ? 'Submit Material' : 'Upload Material' ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>
    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="lms-stat"><i class="fas fa-book-open"></i><strong><?= $stats['courses'] ?></strong><span>Courses</span></div></div>
        <div class="col-md-3"><div class="lms-stat"><i class="fas fa-folder-open"></i><strong><?= $stats['materials'] ?></strong><span>Materials</span></div></div>
        <div class="col-md-3"><div class="lms-stat"><i class="fas fa-clipboard-list"></i><strong><?= $stats['assignments'] ?></strong><span>Assignments</span></div></div>
        <div class="col-md-3"><div class="lms-stat"><i class="fas fa-download"></i><strong><?= $stats['downloads'] ?></strong><span>Total Downloads</span></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label small fw-bold text-muted">Search material</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Title, course, description...">
                    </div>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small fw-bold text-muted">Class</label>
                    <select name="class" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $classOption): ?>
                            <option value="<?= htmlspecialchars($classOption) ?>" <?= $filterClass === $classOption ? 'selected' : '' ?>><?= htmlspecialchars($classOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small fw-bold text-muted">Subject</label>
                    <select name="subject" class="form-select">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subjectOption): ?>
                            <option value="<?= htmlspecialchars($subjectOption) ?>" <?= $filterSubject === $subjectOption ? 'selected' : '' ?>><?= htmlspecialchars($subjectOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label small fw-bold text-muted">Campus</label>
                    <select name="campus" class="form-select">
                        <option value="">All Campuses</option>
                        <?php foreach ($campuses as $campusOption): ?>
                            <option value="<?= htmlspecialchars($campusOption) ?>" <?= $filterCampus === $campusOption ? 'selected' : '' ?>><?= htmlspecialchars($campusOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1 d-grid">
                    <button class="btn btn-primary"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-navy">Course Materials</h5>
        <span class="badge bg-light text-dark border"><?= count($materials) ?> item(s)</span>
    </div>

    <?php if (!$materials): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
            <h5 class="fw-bold">No course material found</h5>
            <p class="text-muted mb-0"><?= $isManager ? 'Upload your first course material to make it available for students.' : 'Your teachers have not uploaded course material yet.' ?></p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($materials as $material): ?>
                <?php [$icon, $iconColor] = lmsFileIcon($material['file_type']); ?>
                <div class="col-xl-4 col-md-6">
                    <div class="material-card p-4">
                        <div class="d-flex gap-3 align-items-start mb-3">
                            <div class="file-icon" style="color: <?= htmlspecialchars($iconColor) ?>;">
                                <i class="fas <?= htmlspecialchars($icon) ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($material['title']) ?></h6>
                                <div class="text-muted small"><?= htmlspecialchars($material['course_name'] ?: 'General Course') ?></div>
                            </div>
                        </div>

                        <?php if (!empty($material['description'])): ?>
                            <p class="text-muted small mb-3"><?= htmlspecialchars($material['description']) ?></p>
                        <?php endif; ?>

                        <div class="mb-3">
                            <?php if (!empty($material['class'])): ?><span class="lms-chip"><?= htmlspecialchars($material['class']) ?></span><?php endif; ?>
                            <?php if (!empty($material['subject'])): ?><span class="lms-chip"><?= htmlspecialchars($material['subject']) ?></span><?php endif; ?>
                            <?php if (!empty($material['campus'])): ?><span class="lms-chip"><?= htmlspecialchars($material['campus']) ?></span><?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between text-muted small mb-3">
                            <span><i class="fas fa-file me-1"></i><?= strtoupper(htmlspecialchars($material['file_type'])) ?> · <?= lmsFormatBytes((int)$material['file_size']) ?></span>
                            <span><i class="fas fa-download me-1"></i><?= (int)$material['download_count'] ?></span>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="index.php?download_id=<?= (int)$material['id'] ?>" class="btn btn-success flex-grow-1">
                                <i class="fas fa-download me-1"></i>Download
                            </a>
                            <?php if ($isAdmin): ?>
                                <form method="POST" onsubmit="return confirm('Delete this course material?');">
                                    <input type="hidden" name="action" value="delete_material">
                                    <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                                    <button class="btn btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($userRole === 'student'): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0 p-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-paper-plane me-2 text-success"></i>Submit Assignment</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="submit_assignment">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Assignment</label>
                        <select name="assignment_id" class="form-select" required>
                            <option value="">Select assignment</option>
                            <?php foreach ($availableAssignments as $assignment): ?>
                                <option value="<?= (int)$assignment['id'] ?>"><?= htmlspecialchars($assignment['title'] . ' - ' . ($assignment['course_name'] ?? 'General')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold">Submission Text</label>
                        <textarea name="submission_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-success rounded-pill px-4">Submit Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($isManager): ?>
<div class="modal fade" id="uploadMaterialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_material">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Course Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Material Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 1 Notes" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Course Name</label>
                        <input type="text" name="course_name" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Class / Program</label>
                        <select name="class" class="form-select">
                            <option value="">Select class</option>
                            <?php foreach ($classes as $classOption): ?>
                                <option value="<?= htmlspecialchars($classOption) ?>"><?= htmlspecialchars($classOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Subject</label>
                        <select name="subject" class="form-select">
                            <option value="">Select subject</option>
                            <?php foreach ($subjects as $subjectOption): ?>
                                <option value="<?= htmlspecialchars($subjectOption) ?>"><?= htmlspecialchars($subjectOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Campus</label>
                        <select name="campus" class="form-select">
                            <option value="">All campuses</option>
                            <?php foreach ($campuses as $campusOption): ?>
                                <option value="<?= htmlspecialchars($campusOption) ?>"><?= htmlspecialchars($campusOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Short note for students"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Content Expense (optional)</label>
                        <input type="number" step="0.01" name="content_expense" class="form-control" placeholder="PKR 0.00">
                        <div class="form-text">If paid content was purchased, it will be sent to Finance as a pending expense.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Upload File <span class="text-danger">*</span></label>
                        <input type="file" name="material_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.jpg,.jpeg,.png" required>
                        <div class="form-text">Allowed: pdf, doc, docx, ppt, pptx, zip, jpg, png. Maximum size: 20 MB.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i><?= $isTeacher ? 'Submit for Approval' : 'Upload Material' ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
