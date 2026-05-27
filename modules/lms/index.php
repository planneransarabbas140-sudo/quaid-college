<?php
// File: modules/lms/index.php
require_once '../../config/db.php';
require_once '../../includes/shared_functions.php';

if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$db = (new Database())->getConnection();
$role = getUserRole();
$userId = getUserId();
$isAdmin = in_array($role, ['admin', 'owner'], true);
$isTeacher = $role === 'teacher';
$canManage = $isAdmin || $isTeacher;

$uploadRoot = __DIR__ . '/../../uploads/lms/materials';
$uploadPublicRoot = 'uploads/lms/materials';
$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
$maxFileSize = 20 * 1024 * 1024;

function lms_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function lms_format_bytes(int $bytes): string {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    return round(max($bytes, 0) / 1024, 2) . ' KB';
}

function lms_file_icon(string $type): array {
    $type = strtolower($type);
    if ($type === 'pdf') {
        return ['fa-file-pdf', 'text-danger'];
    }
    if (in_array($type, ['doc', 'docx'], true)) {
        return ['fa-file-word', 'text-primary'];
    }
    if (in_array($type, ['ppt', 'pptx'], true)) {
        return ['fa-file-powerpoint', 'text-warning'];
    }
    return ['fa-file', 'text-secondary'];
}

function lms_teacher_id(PDO $db): ?int {
    if (tableExists($db, 'staff') && columnExists($db, 'staff', 'user_id')) {
        $stmt = $db->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
        $stmt->execute([getUserId()]);
        $staffId = $stmt->fetchColumn();
        if ($staffId) {
            return (int)$staffId;
        }
    }
    return null;
}

function lms_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS lms_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id VARCHAR(100) DEFAULT NULL,
        section_id VARCHAR(50) DEFAULT NULL,
        subject_id VARCHAR(120) DEFAULT NULL,
        teacher_id INT DEFAULT NULL,
        title VARCHAR(220) NOT NULL,
        description TEXT DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        original_file_name VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(20) DEFAULT NULL,
        file_size BIGINT NOT NULL DEFAULT 0,
        uploaded_by INT DEFAULT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        download_count INT NOT NULL DEFAULT 0,
        INDEX idx_lms_class (class_id, section_id),
        INDEX idx_lms_subject (subject_id),
        INDEX idx_lms_status (status),
        INDEX idx_lms_uploaded_at (uploaded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'class_id' => "ALTER TABLE lms_materials ADD COLUMN class_id VARCHAR(100) DEFAULT NULL",
        'section_id' => "ALTER TABLE lms_materials ADD COLUMN section_id VARCHAR(50) DEFAULT NULL",
        'subject_id' => "ALTER TABLE lms_materials ADD COLUMN subject_id VARCHAR(120) DEFAULT NULL",
        'teacher_id' => "ALTER TABLE lms_materials ADD COLUMN teacher_id INT DEFAULT NULL",
        'title' => "ALTER TABLE lms_materials ADD COLUMN title VARCHAR(220) DEFAULT NULL",
        'description' => "ALTER TABLE lms_materials ADD COLUMN description TEXT DEFAULT NULL",
        'file_path' => "ALTER TABLE lms_materials ADD COLUMN file_path VARCHAR(255) DEFAULT NULL",
        'original_file_name' => "ALTER TABLE lms_materials ADD COLUMN original_file_name VARCHAR(255) DEFAULT NULL",
        'file_type' => "ALTER TABLE lms_materials ADD COLUMN file_type VARCHAR(20) DEFAULT NULL",
        'file_size' => "ALTER TABLE lms_materials ADD COLUMN file_size BIGINT NOT NULL DEFAULT 0",
        'uploaded_by' => "ALTER TABLE lms_materials ADD COLUMN uploaded_by INT DEFAULT NULL",
        'uploaded_at' => "ALTER TABLE lms_materials ADD COLUMN uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'status' => "ALTER TABLE lms_materials ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
        'download_count' => "ALTER TABLE lms_materials ADD COLUMN download_count INT NOT NULL DEFAULT 0",
    ];

    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'lms_materials', $column)) {
            $db->exec($sql);
        }
    }
}

function lms_store_file(array $file, string $uploadRoot, string $uploadPublicRoot, array $allowedExtensions, int $maxFileSize): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Please select a valid material file.');
    }

    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxFileSize) {
        throw new Exception('File size must be between 1 byte and 20 MB.');
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, PPT, PPTX.');
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $info = finfo_open(FILEINFO_MIME_TYPE);
        if ($info) {
            $mime = (string)finfo_file($info, (string)$file['tmp_name']);
            finfo_close($info);
        }
    }

    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-office',
        'application/x-ole-storage',
        'application/octet-stream',
        'application/zip',
    ];
    if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
        throw new Exception('Uploaded file content does not match the allowed document types.');
    }

    if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
        throw new Exception('LMS upload directory is not writable.');
    }

    $storedName = 'lms_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = rtrim($uploadRoot, '/\\') . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new Exception('Could not save the uploaded material.');
    }

    return [
        'file_path' => $uploadPublicRoot . '/' . $storedName,
        'original_file_name' => $originalName,
        'file_type' => $extension,
        'file_size' => (int)$file['size'],
    ];
}

lms_ensure_schema($db);
$teacherId = lms_teacher_id($db);

if (isset($_GET['download_id'])) {
    $downloadId = (int)$_GET['download_id'];
    $where = 'id = ?';
    $params = [$downloadId];
    if (!$isAdmin && !$isTeacher) {
        $where .= " AND LOWER(status) = 'active'";
    }

    $stmt = $db->prepare("SELECT id, file_path, original_file_name FROM lms_materials WHERE $where LIMIT 1");
    $stmt->execute($params);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($material) {
        $realUploadDir = realpath($uploadRoot);
        $realFile = realpath(__DIR__ . '/../../' . $material['file_path']);
        if ($realUploadDir && $realFile && strpos($realFile, rtrim($realUploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0 && is_file($realFile)) {
            $db->prepare('UPDATE lms_materials SET download_count = download_count + 1 WHERE id = ?')->execute([$downloadId]);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($material['original_file_name'] ?: $material['file_path']) . '"');
            header('Content-Length: ' . filesize($realFile));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($realFile);
            exit;
        }
    }

    setFlashMessage('error', 'The requested LMS material could not be found.');
    redirect('index.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_material') {
            if (!$canManage) {
                throw new Exception('You are not allowed to upload LMS material.');
            }

            $classId = sanitizeInput($_POST['class_id'] ?? '');
            $sectionId = sanitizeInput($_POST['section_id'] ?? '');
            $subjectId = sanitizeInput($_POST['subject_id'] ?? '');
            $selectedTeacherId = $isAdmin ? (int)($_POST['teacher_id'] ?? 0) : (int)$teacherId;
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

            if ($classId === '' || $subjectId === '' || $title === '') {
                throw new Exception('Please fill class, subject, and title.');
            }

            $fileData = lms_store_file($_FILES['material_file'] ?? [], $uploadRoot, $uploadPublicRoot, $allowedExtensions, $maxFileSize);
            $stmt = $db->prepare("INSERT INTO lms_materials
                (class_id, section_id, subject_id, teacher_id, title, description, file_path, original_file_name, file_type, file_size, uploaded_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $classId,
                $sectionId,
                $subjectId,
                $selectedTeacherId ?: null,
                $title,
                $description,
                $fileData['file_path'],
                $fileData['original_file_name'],
                $fileData['file_type'],
                $fileData['file_size'],
                $userId,
                $status,
            ]);

            setFlashMessage('success', 'LMS material uploaded successfully.');
            redirect('index.php');
        }

        if ($action === 'toggle_status') {
            if (!$canManage) {
                throw new Exception('You are not allowed to update LMS material.');
            }

            $materialId = (int)($_POST['material_id'] ?? 0);
            if ($materialId <= 0) {
                throw new Exception('Invalid material selected.');
            }

            $where = 'id = ?';
            $params = [$materialId];
            if (!$isAdmin) {
                $where .= ' AND (uploaded_by = ? OR teacher_id = ?)';
                $params[] = $userId;
                $params[] = $teacherId;
            }

            $stmt = $db->prepare("SELECT status FROM lms_materials WHERE $where LIMIT 1");
            $stmt->execute($params);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$material) {
                throw new Exception('Material not found or not owned by you.');
            }

            $nextStatus = strtolower((string)$material['status']) === 'active' ? 'inactive' : 'active';
            $stmt = $db->prepare("UPDATE lms_materials SET status = ? WHERE $where");
            $stmt->execute(array_merge([$nextStatus], $params));
            setFlashMessage('success', 'LMS material status updated.');
            redirect('index.php');
        }

        if ($action === 'delete_material') {
            if (!$canManage) {
                throw new Exception('You are not allowed to delete LMS material.');
            }

            $materialId = (int)($_POST['material_id'] ?? 0);
            if ($materialId <= 0) {
                throw new Exception('Invalid material selected.');
            }

            $where = 'id = ?';
            $params = [$materialId];
            if (!$isAdmin) {
                $where .= ' AND (uploaded_by = ? OR teacher_id = ?)';
                $params[] = $userId;
                $params[] = $teacherId;
            }

            $stmt = $db->prepare("SELECT file_path FROM lms_materials WHERE $where LIMIT 1");
            $stmt->execute($params);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$material) {
                throw new Exception('Material not found or not owned by you.');
            }

            $realUploadDir = realpath($uploadRoot);
            $realFile = realpath(__DIR__ . '/../../' . $material['file_path']);
            if ($realUploadDir && $realFile && strpos($realFile, rtrim($realUploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0 && is_file($realFile)) {
                @unlink($realFile);
            }

            $stmt = $db->prepare("DELETE FROM lms_materials WHERE $where");
            $stmt->execute($params);
            setFlashMessage('success', 'LMS material deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

$classes = getAllClasses($db);
if (empty($classes)) {
    foreach (['Matric', 'FA/FSc', 'ICS', 'ADP', 'BSCS', 'BSIT', 'B.Ed'] as $className) {
        $classes[] = ['id' => $className, 'class_name' => $className];
    }
}
$sections = getAllSections($db);
$subjects = getAllSubjects($db);
$fallbackSubjects = ['English', 'Urdu', 'Islamiyat', 'Pakistan Studies', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science'];
foreach ($fallbackSubjects as $subject) {
    $subjects[] = ['id' => $subject, 'subject_name' => $subject];
}
$seenSubjects = [];
$subjects = array_values(array_filter($subjects, static function ($subject) use (&$seenSubjects) {
    $key = (string)($subject['id'] ?? $subject['subject_name'] ?? '');
    if ($key === '' || isset($seenSubjects[$key])) {
        return false;
    }
    $seenSubjects[$key] = true;
    return true;
}));
$teachers = getTeachers($db);

$filterClass = trim((string)($_GET['class_id'] ?? ''));
$filterSection = trim((string)($_GET['section_id'] ?? ''));
$filterSubject = trim((string)($_GET['subject_id'] ?? ''));
$filterStatus = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';

$where = [];
$params = [];
if ($filterClass !== '') {
    $where[] = 'lm.class_id = ?';
    $params[] = $filterClass;
}
if ($filterSection !== '') {
    $where[] = 'lm.section_id = ?';
    $params[] = $filterSection;
}
if ($filterSubject !== '') {
    $where[] = 'lm.subject_id = ?';
    $params[] = $filterSubject;
}
if ($filterStatus !== '') {
    $where[] = 'LOWER(lm.status) = ?';
    $params[] = $filterStatus;
}
if (!$isAdmin && !$isTeacher) {
    $where[] = "LOWER(lm.status) = 'active'";
}
if ($isTeacher && !$isAdmin) {
    $where[] = "(LOWER(lm.status) = 'active' OR lm.uploaded_by = ? OR lm.teacher_id = ?)";
    $params[] = $userId;
    $params[] = $teacherId;
}
if ($role === 'student' && tableExists($db, 'students') && columnExists($db, 'students', 'user_id')) {
    $student = sharedFetchOne($db, 'SELECT class, section FROM students WHERE user_id = ? LIMIT 1', [$userId]);
    if ($student) {
        $where[] = 'lm.class_id = ?';
        $params[] = $student['class'];
        if (!empty($student['section'])) {
            $where[] = "(lm.section_id = ? OR lm.section_id = '')";
            $params[] = $student['section'];
        }
    }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = $db->prepare("
    SELECT lm.*, COALESCE(s.full_name, u.full_name, u.username, 'Teacher') AS teacher_name
    FROM lms_materials lm
    LEFT JOIN staff s ON s.id = lm.teacher_id
    LEFT JOIN users u ON u.id = lm.uploaded_by
    $whereSql
    ORDER BY lm.uploaded_at DESC, lm.id DESC
");
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => count($materials),
    'active' => 0,
    'inactive' => 0,
    'downloads' => 0,
];
foreach ($materials as $material) {
    strtolower((string)$material['status']) === 'inactive' ? $stats['inactive']++ : $stats['active']++;
    $stats['downloads'] += (int)$material['download_count'];
}

$page_title = 'Learning Management System';
include '../../includes/header.php';
?>

<div class="container-fluid lms-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-graduation-cap me-2" style="color:var(--teal);"></i>Learning Management System</h2>
            <div class="text-muted">Upload and share class study material by section and subject.</div>
        </div>
        <?php if ($canManage): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal"><i class="fas fa-cloud-upload-alt me-1"></i>Upload Material</button>
        <?php endif; ?>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total Materials</div><h3 class="fw-bold mb-0"><?= (int)$stats['total'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Active</div><h3 class="fw-bold text-success mb-0"><?= (int)$stats['active'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Inactive</div><h3 class="fw-bold text-secondary mb-0"><?= (int)$stats['inactive'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Downloads</div><h3 class="fw-bold text-primary mb-0"><?= (int)$stats['downloads'] ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Class</label>
                    <select name="class_id" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= lms_h($class['id']) ?>" <?= $filterClass === (string)$class['id'] ? 'selected' : '' ?>><?= lms_h($class['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= lms_h($section['id']) ?>" <?= $filterSection === (string)$section['id'] ? 'selected' : '' ?>><?= lms_h($section['section_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Subject</label>
                    <select name="subject_id" class="form-select">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= lms_h($subject['id']) ?>" <?= $filterSubject === (string)$subject['id'] ? 'selected' : '' ?>><?= lms_h($subject['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach (['active', 'inactive'] as $status): ?>
                            <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <?php if (!$materials): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="fas fa-folder-open fa-3x mb-3"></i><div>No LMS material found.</div></div></div>
            </div>
        <?php endif; ?>

        <?php foreach ($materials as $material): ?>
            <?php [$icon, $iconClass] = lms_file_icon((string)$material['file_type']); ?>
            <div class="col-xl-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm material-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex gap-3 align-items-start mb-3">
                            <div class="file-icon <?= lms_h($iconClass) ?>"><i class="fas <?= lms_h($icon) ?>"></i></div>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-1"><?= lms_h($material['title']) ?></h5>
                                <div class="small text-muted"><?= lms_h($material['teacher_name']) ?> - <?= lms_h(date('d M Y', strtotime($material['uploaded_at']))) ?></div>
                            </div>
                            <span class="badge <?= strtolower((string)$material['status']) === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= lms_h(ucfirst((string)$material['status'])) ?></span>
                        </div>

                        <?php if (!empty($material['description'])): ?>
                            <p class="text-muted small"><?= nl2br(lms_h($material['description'])) ?></p>
                        <?php endif; ?>

                        <div class="mb-3">
                            <span class="badge bg-light text-dark border"><?= lms_h($material['class_id']) ?></span>
                            <span class="badge bg-light text-dark border"><?= lms_h($material['section_id'] ?: 'All Sections') ?></span>
                            <span class="badge bg-light text-dark border"><?= lms_h($material['subject_id']) ?></span>
                        </div>

                        <div class="small text-muted mb-3">
                            <i class="fas fa-file me-1"></i><?= lms_h(strtoupper((string)$material['file_type'])) ?> - <?= lms_h(lms_format_bytes((int)$material['file_size'])) ?>
                            <span class="ms-2"><i class="fas fa-download me-1"></i><?= (int)$material['download_count'] ?></span>
                        </div>

                        <div class="d-flex gap-2 mt-auto">
                            <a class="btn btn-success flex-fill" href="index.php?download_id=<?= (int)$material['id'] ?>"><i class="fas fa-download me-1"></i>Download</a>
                            <?php if ($canManage && ($isAdmin || (int)$material['uploaded_by'] === (int)$userId || (int)$material['teacher_id'] === (int)$teacherId)): ?>
                                <form method="POST">
                                    <?= csrfTokenInput() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                                    <button class="btn btn-outline-secondary" type="submit" title="Toggle status"><i class="fas fa-toggle-on"></i></button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Delete this LMS material?');">
                                    <?= csrfTokenInput() ?>
                                    <input type="hidden" name="action" value="delete_material">
                                    <input type="hidden" name="material_id" value="<?= (int)$material['id'] ?>">
                                    <button class="btn btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="uploadMaterialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="upload_material">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Study Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Class *</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">Select class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= lms_h($class['id']) ?>"><?= lms_h($class['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Section</label>
                        <select name="section_id" class="form-select">
                            <option value="">All sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= lms_h($section['id']) ?>"><?= lms_h($section['section_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Subject *</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= lms_h($subject['id']) ?>"><?= lms_h($subject['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div class="col-md-6">
                            <label class="form-label">Teacher</label>
                            <select name="teacher_id" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int)$teacher['id'] ?>"><?= lms_h($teacher['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="<?= $isAdmin ? 'col-md-6' : 'col-md-12' ?>">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Material File *</label>
                        <input type="file" name="material_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx" required>
                        <div class="form-text">Allowed: PDF, DOC, DOCX, PPT, PPTX. Maximum size: 20 MB.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload Material</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    .file-icon { width:44px; height:44px; display:grid; place-items:center; border-radius:8px; background:#f8fafc; font-size:1.5rem; flex:0 0 auto; }
    .material-card { border-radius:8px; }
    .material-card .badge { font-weight:600; }
</style>

<?php include '../../includes/footer.php'; ?>
