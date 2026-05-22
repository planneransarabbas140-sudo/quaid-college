<?php
// File: modules/syllabus/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$db = (new Database())->getConnection();
$page_title = "Syllabus";
$role = strtolower(getUserRole() ?? '');
$canManageSyllabus = in_array($role, ['admin', 'teacher', 'staff', 'super_admin'], true);

function sy_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sy_formatFileSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function sy_fileIcon($extension) {
    $extension = strtolower((string)$extension);
    if ($extension === 'pdf') {
        return 'fa-file-pdf text-danger';
    }
    if (in_array($extension, ['doc', 'docx'], true)) {
        return 'fa-file-word text-primary';
    }
    if (in_array($extension, ['ppt', 'pptx'], true)) {
        return 'fa-file-powerpoint text-warning';
    }
    if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        return 'fa-file-image text-success';
    }
    if ($extension === 'zip') {
        return 'fa-file-zipper text-secondary';
    }
    return 'fa-file-lines text-muted';
}

$uploadDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'syllabus';
$uploadPathForDb = 'uploads/syllabus/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$db->exec("CREATE TABLE IF NOT EXISTS syllabus_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    class VARCHAR(100) NOT NULL,
    section VARCHAR(50) DEFAULT NULL,
    subject VARCHAR(120) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(80) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    file_size INT DEFAULT 0,
    uploaded_by INT DEFAULT NULL,
    status ENUM('Active','Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_syllabus_class (class),
    INDEX idx_syllabus_subject (subject),
    INDEX idx_syllabus_year (academic_year),
    INDEX idx_syllabus_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png'];
$maxFileSize = 25 * 1024 * 1024;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    try {
        if (!$canManageSyllabus) {
            throw new Exception('You are not allowed to manage syllabus files.');
        }

        $action = $_POST['action'];

        if ($action === 'upload_syllabus') {
            $title = sanitizeInput($_POST['title'] ?? '');
            $class = sanitizeInput($_POST['class'] ?? '');
            $section = sanitizeInput($_POST['section'] ?? '');
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $academicYear = sanitizeInput($_POST['academic_year'] ?? getCurrentAcademicYear());
            $term = sanitizeInput($_POST['term'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($title === '' || $class === '' || $subject === '' || $academicYear === '') {
                throw new Exception('Please fill all required syllabus fields.');
            }
            if (empty($_FILES['syllabus_file']['name']) || ($_FILES['syllabus_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception('Please select a valid syllabus file.');
            }

            $file = $_FILES['syllabus_file'];
            if ($file['size'] > $maxFileSize) {
                throw new Exception('File is too large. Maximum allowed size is 25 MB.');
            }

            $originalName = basename($file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new Exception('Invalid file type. Allowed: pdf, doc, docx, ppt, pptx, zip, jpg, png.');
            }

            $safeFileName = 'syllabus_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeFileName;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Could not upload the syllabus file. Please try again.');
            }

            $stmt = $db->prepare("INSERT INTO syllabus_files
                (title, class, section, subject, academic_year, term, description, file_path, original_file_name, file_type, file_size, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $class,
                $section,
                $subject,
                $academicYear,
                $term,
                $description,
                $uploadPathForDb . $safeFileName,
                $originalName,
                $extension,
                (int)$file['size'],
                $_SESSION['user_id'] ?? null
            ]);

            setFlashMessage('success', 'Syllabus uploaded successfully.');
            redirect('index.php');
        }

        if ($action === 'edit_syllabus') {
            $id = (int)($_POST['id'] ?? 0);
            $title = sanitizeInput($_POST['title'] ?? '');
            $class = sanitizeInput($_POST['class'] ?? '');
            $section = sanitizeInput($_POST['section'] ?? '');
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $academicYear = sanitizeInput($_POST['academic_year'] ?? getCurrentAcademicYear());
            $term = sanitizeInput($_POST['term'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($id <= 0 || $title === '' || $class === '' || $subject === '' || $academicYear === '') {
                throw new Exception('Please fill all required syllabus fields.');
            }

            $stmt = $db->prepare("UPDATE syllabus_files
                SET title = ?, class = ?, section = ?, subject = ?, academic_year = ?, term = ?, description = ?
                WHERE id = ?");
            $stmt->execute([$title, $class, $section, $subject, $academicYear, $term, $description, $id]);
            setFlashMessage('success', 'Syllabus details updated successfully.');
            redirect('index.php');
        }

        if ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'Active';
            if ($id <= 0 || !in_array($status, ['Active', 'Archived'], true)) {
                throw new Exception('Invalid syllabus status update.');
            }

            $stmt = $db->prepare("UPDATE syllabus_files SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            setFlashMessage('success', 'Syllabus status updated.');
            redirect('index.php');
        }

        if ($action === 'delete_syllabus') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid syllabus file.');
            }

            $stmt = $db->prepare("SELECT file_path FROM syllabus_files WHERE id = ?");
            $stmt->execute([$id]);
            $fileRow = $stmt->fetch();

            $db->prepare("DELETE FROM syllabus_files WHERE id = ?")->execute([$id]);

            if (!empty($fileRow['file_path'])) {
                $absoluteFile = realpath(__DIR__ . '/../../' . $fileRow['file_path']);
                $rootPath = realpath(__DIR__ . '/../../');
                if ($absoluteFile && $rootPath && strpos($absoluteFile, $rootPath) === 0 && is_file($absoluteFile)) {
                    unlink($absoluteFile);
                }
            }

            setFlashMessage('success', 'Syllabus deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

$defaultSubjects = [
    'English',
    'Urdu',
    'Islamiyat',
    'Pakistan Studies',
    'Mathematics',
    'Physics',
    'Chemistry',
    'Biology',
    'Computer Science',
    'General Science',
    'Web Development',
    'Digital Marketing',
    'Graphic Designing'
];

$classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class <> '' ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
$sections = $db->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND section <> '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
$existingSubjects = $db->query("SELECT DISTINCT subject FROM syllabus_files WHERE subject IS NOT NULL AND subject <> '' ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN);
$subjects = array_values(array_unique(array_merge($defaultSubjects, $existingSubjects)));
sort($subjects);

if (empty($classes)) {
    $classes = ['Matric', 'FA/FSc', 'ICS', 'ADP', 'BSCS', 'BSIT', 'B.Ed'];
}

if (empty($sections)) {
    $sections = ['A', 'B', 'C'];
}

$filterClass = trim($_GET['class'] ?? '');
$filterSubject = trim($_GET['subject'] ?? '');
$filterYear = trim($_GET['academic_year'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if ($filterClass !== '') {
    $where[] = 'sf.class = ?';
    $params[] = $filterClass;
}
if ($filterSubject !== '') {
    $where[] = 'sf.subject = ?';
    $params[] = $filterSubject;
}
if ($filterYear !== '') {
    $where[] = 'sf.academic_year = ?';
    $params[] = $filterYear;
}
if ($filterStatus !== '') {
    $where[] = 'sf.status = ?';
    $params[] = $filterStatus;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT sf.*, COALESCE(u.full_name, u.username, 'Admin') as uploaded_by_name
    FROM syllabus_files sf
    LEFT JOIN users u ON sf.uploaded_by = u.id
    $whereSql
    ORDER BY sf.created_at DESC, sf.id DESC");
$stmt->execute($params);
$syllabusFiles = $stmt->fetchAll();

$academicYears = $db->query("SELECT DISTINCT academic_year FROM syllabus_files WHERE academic_year IS NOT NULL AND academic_year <> '' ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$totalFiles = (int)$db->query("SELECT COUNT(*) FROM syllabus_files")->fetchColumn();
$activeFiles = (int)$db->query("SELECT COUNT(*) FROM syllabus_files WHERE status = 'Active'")->fetchColumn();
$subjectCount = (int)$db->query("SELECT COUNT(DISTINCT subject) FROM syllabus_files")->fetchColumn();
$classCount = (int)$db->query("SELECT COUNT(DISTINCT class) FROM syllabus_files")->fetchColumn();

include '../../includes/header.php';
?>

<div class="container-fluid syllabus-module">
    <div class="row mb-4">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb" style="font-size:.82rem;font-family:'Space Mono',monospace;">
                        <li class="breadcrumb-item"><a href="../../dashboard.php" style="color:#4ec2b5;text-decoration:none;"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Syllabus</li>
                    </ol>
                </nav>
                <h2 class="page-title mb-1">Syllabus Management</h2>
                <p class="text-muted mb-0">Upload, organize, and share class-wise syllabus files with students.</p>
            </div>
            <?php if ($canManageSyllabus): ?>
                <button class="btn btn-primary btn-lg rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadSyllabusModal">
                    <i class="fas fa-file-upload me-2"></i>Upload Syllabus
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="syllabus-stat-card">
                <span class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-folder-open"></i></span>
                <div>
                    <p>Total Files</p>
                    <h3><?= $totalFiles ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="syllabus-stat-card">
                <span class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle"></i></span>
                <div>
                    <p>Active</p>
                    <h3><?= $activeFiles ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="syllabus-stat-card">
                <span class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-book"></i></span>
                <div>
                    <p>Subjects</p>
                    <h3><?= $subjectCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="syllabus-stat-card">
                <span class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-users"></i></span>
                <div>
                    <p>Classes</p>
                    <h3><?= $classCount ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Class / Program</label>
                    <select name="class" class="form-select">
                        <option value="">All classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= sy_h($class) ?>" <?= $filterClass === $class ? 'selected' : '' ?>><?= sy_h($class) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Subject</label>
                    <select name="subject" class="form-select">
                        <option value="">All subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= sy_h($subject) ?>" <?= $filterSubject === $subject ? 'selected' : '' ?>><?= sy_h($subject) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">Academic Year</label>
                    <select name="academic_year" class="form-select">
                        <option value="">All years</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?= sy_h($year) ?>" <?= $filterYear === $year ? 'selected' : '' ?>><?= sy_h($year) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All status</option>
                        <option value="Active" <?= $filterStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Archived" <?= $filterStatus === 'Archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-navy"><i class="fas fa-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-navy">Syllabus Repository</h5>
            <a href="index.php" class="btn btn-sm btn-light rounded-pill px-3"><i class="fas fa-rotate me-1"></i> Reset</a>
        </div>
        <div class="card-body p-4">
            <?php if (empty($syllabusFiles)): ?>
                <div class="empty-syllabus-state text-center py-5">
                    <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                    <h4 class="fw-bold">No syllabus uploaded yet</h4>
                    <p class="text-muted mb-0">Use the Upload Syllabus button to add class-wise syllabus files.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($syllabusFiles as $file): ?>
                        <div class="col-xl-4 col-lg-6">
                            <div class="syllabus-card h-100">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="file-icon">
                                        <i class="fas <?= sy_fileIcon($file['file_type']) ?>"></i>
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="d-flex justify-content-between gap-2 align-items-start">
                                            <h5 class="mb-1"><?= sy_h($file['title']) ?></h5>
                                            <span class="badge <?= $file['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= sy_h($file['status']) ?></span>
                                        </div>
                                        <div class="small text-muted mb-2">
                                            <?= sy_h($file['class']) ?>
                                            <?php if (!empty($file['section'])): ?>
                                                · Section <?= sy_h($file['section']) ?>
                                            <?php endif; ?>
                                            · <?= sy_h($file['academic_year']) ?>
                                        </div>
                                        <span class="subject-pill"><?= sy_h($file['subject']) ?></span>
                                        <?php if (!empty($file['term'])): ?>
                                            <span class="term-pill"><?= sy_h($file['term']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($file['description'])): ?>
                                    <p class="syllabus-desc"><?= nl2br(sy_h($file['description'])) ?></p>
                                <?php endif; ?>

                                <div class="file-meta">
                                    <span><i class="fas fa-file me-1"></i><?= sy_h(strtoupper($file['file_type'])) ?></span>
                                    <span><i class="fas fa-hard-drive me-1"></i><?= sy_formatFileSize($file['file_size']) ?></span>
                                    <span><i class="fas fa-user me-1"></i><?= sy_h($file['uploaded_by_name']) ?></span>
                                </div>

                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <a href="<?= BASE_URL . sy_h($file['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3">
                                        <i class="fas fa-download me-1"></i> Download
                                    </a>
                                    <?php if ($canManageSyllabus): ?>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary rounded-pill px-3 edit-syllabus-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editSyllabusModal"
                                            data-id="<?= (int)$file['id'] ?>"
                                            data-title="<?= sy_h($file['title']) ?>"
                                            data-class="<?= sy_h($file['class']) ?>"
                                            data-section="<?= sy_h($file['section']) ?>"
                                            data-subject="<?= sy_h($file['subject']) ?>"
                                            data-academic-year="<?= sy_h($file['academic_year']) ?>"
                                            data-term="<?= sy_h($file['term']) ?>"
                                            data-description="<?= sy_h($file['description']) ?>">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int)$file['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $file['status'] === 'Active' ? 'Archived' : 'Active' ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                <?= $file['status'] === 'Active' ? 'Archive' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this syllabus file permanently?');">
                                            <input type="hidden" name="action" value="delete_syllabus">
                                            <input type="hidden" name="id" value="<?= (int)$file['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManageSyllabus): ?>
<datalist id="subjectOptions">
    <?php foreach ($subjects as $subject): ?>
        <option value="<?= sy_h($subject) ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="modal fade" id="uploadSyllabusModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-file-upload me-2"></i>Upload Syllabus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_syllabus">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Syllabus Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. BSCS Semester 1 Complete Syllabus" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Academic Year *</label>
                            <input type="text" name="academic_year" class="form-control" value="<?= sy_h(getCurrentAcademicYear()) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Class / Program *</label>
                            <select name="class" class="form-select" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= sy_h($class) ?>"><?= sy_h($class) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Section</label>
                            <select name="section" class="form-select">
                                <option value="">All sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= sy_h($section) ?>"><?= sy_h($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Subject *</label>
                            <input list="subjectOptions" name="subject" class="form-control" placeholder="e.g. Mathematics" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Term / Semester</label>
                            <input type="text" name="term" class="form-control" placeholder="e.g. Annual, Term 1, Semester 2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Syllabus File *</label>
                            <input type="file" name="syllabus_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.jpg,.jpeg,.png" required>
                            <small class="text-muted">Allowed: pdf, doc, docx, ppt, pptx, zip, jpg, png. Max 25 MB.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional short note for students"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-upload me-1"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSyllabusModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Syllabus Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_syllabus">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Syllabus Title *</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Academic Year *</label>
                            <input type="text" name="academic_year" id="edit_academic_year" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Class / Program *</label>
                            <select name="class" id="edit_class" class="form-select" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= sy_h($class) ?>"><?= sy_h($class) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Section</label>
                            <select name="section" id="edit_section" class="form-select">
                                <option value="">All sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= sy_h($section) ?>"><?= sy_h($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Subject *</label>
                            <input list="subjectOptions" name="subject" id="edit_subject" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Term / Semester</label>
                            <input type="text" name="term" id="edit_term" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4"><i class="fas fa-save me-1"></i> Update Details</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    .syllabus-module .page-title {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        color: #0f2d48;
    }

    .text-navy { color: #0f2d48 !important; }
    .bg-navy { background: #0f2d48 !important; }
    .btn-navy {
        background: #0f2d48;
        color: #fff;
        border: none;
    }
    .btn-navy:hover {
        background: #143b5f;
        color: #fff;
    }

    .syllabus-stat-card {
        min-height: 112px;
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 22px;
        border: 1px solid #e9eef5;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 10px 25px rgba(15, 45, 72, 0.06);
    }

    .syllabus-stat-card .stat-icon {
        width: 58px;
        height: 58px;
        display: inline-grid;
        place-items: center;
        border-radius: 16px;
        font-size: 1.55rem;
        flex: 0 0 58px;
    }

    .syllabus-stat-card p {
        margin: 0 0 4px;
        color: #64748b;
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .syllabus-stat-card h3 {
        margin: 0;
        color: #0f2d48;
        font-weight: 800;
    }

    .syllabus-card {
        padding: 22px;
        border: 1px solid #e9eef5;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 12px 28px rgba(15, 45, 72, 0.06);
        transition: all .25s ease;
    }

    .syllabus-card:hover {
        transform: translateY(-3px);
        border-color: rgba(78, 194, 181, .55);
        box-shadow: 0 18px 42px rgba(15, 45, 72, 0.12);
    }

    .syllabus-card h5 {
        color: #0f2d48;
        font-weight: 800;
        line-height: 1.35;
    }

    .file-icon {
        width: 52px;
        height: 52px;
        display: grid;
        place-items: center;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        font-size: 1.65rem;
        flex: 0 0 52px;
    }

    .subject-pill,
    .term-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: .8rem;
        font-weight: 800;
        margin: 2px 4px 2px 0;
    }

    .subject-pill {
        background: rgba(78, 194, 181, 0.12);
        color: #0f766e;
    }

    .term-pill {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .syllabus-desc {
        margin: 18px 0 0;
        color: #64748b;
        line-height: 1.65;
        max-height: 78px;
        overflow: hidden;
    }

    .file-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
        color: #64748b;
        font-size: .82rem;
        font-weight: 700;
    }

    .empty-syllabus-state {
        border: 1px dashed #dbe4ef;
        border-radius: 18px;
        background: #f8fafc;
    }

    #uploadSyllabusModal,
    #editSyllabusModal {
        z-index: 1305 !important;
    }

    #uploadSyllabusModal .modal-dialog,
    #editSyllabusModal .modal-dialog {
        margin-top: 18px;
        margin-bottom: 18px;
    }

    #uploadSyllabusModal .modal-content,
    #editSyllabusModal .modal-content {
        max-height: calc(100vh - 36px);
        border-radius: 18px;
        overflow: hidden;
    }

    #uploadSyllabusModal .modal-body,
    #editSyllabusModal .modal-body {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
    }

    #uploadSyllabusModal .modal-footer,
    #editSyllabusModal .modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 3;
        border-top: 1px solid #e2e8f0;
    }

    .modal-backdrop {
        z-index: 1290 !important;
    }

    @media (min-width: 993px) {
        #uploadSyllabusModal,
        #editSyllabusModal {
            left: 260px;
            width: calc(100% - 260px);
            padding-left: 24px !important;
            padding-right: 24px !important;
        }

        #uploadSyllabusModal .modal-dialog,
        #editSyllabusModal .modal-dialog {
            max-width: min(920px, calc(100vw - 340px));
            margin-left: auto;
            margin-right: auto;
        }

        body:has(#sidebar.active) #uploadSyllabusModal,
        body:has(#sidebar.active) #editSyllabusModal {
            left: 0;
            width: 100%;
        }

        body:has(#sidebar.active) #uploadSyllabusModal .modal-dialog,
        body:has(#sidebar.active) #editSyllabusModal .modal-dialog {
            max-width: min(920px, calc(100vw - 48px));
        }
    }

    @media (max-width: 992px) {
        #uploadSyllabusModal,
        #editSyllabusModal {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }

        #uploadSyllabusModal .modal-body,
        #editSyllabusModal .modal-body {
            max-height: calc(100vh - 190px);
        }
    }

    @media (max-width: 767px) {
        .syllabus-stat-card {
            min-height: 96px;
            padding: 18px;
        }

        .syllabus-module .btn-lg {
            width: 100%;
        }
    }
</style>

<?php if ($canManageSyllabus): ?>
<script>
document.querySelectorAll('.edit-syllabus-btn').forEach((button) => {
    button.addEventListener('click', () => {
        const fields = {
            edit_id: 'id',
            edit_title: 'title',
            edit_class: 'class',
            edit_section: 'section',
            edit_subject: 'subject',
            edit_academic_year: 'academicYear',
            edit_term: 'term',
            edit_description: 'description'
        };

        Object.entries(fields).forEach(([elementId, datasetKey]) => {
            const element = document.getElementById(elementId);
            if (element) {
                element.value = button.dataset[datasetKey] || '';
            }
        });
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
