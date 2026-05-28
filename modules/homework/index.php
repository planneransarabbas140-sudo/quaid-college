<?php
// File: modules/homework/index.php
require_once '../../config/db.php';
require_once '../../includes/shared_functions.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$db = (new Database())->getConnection();
$role = getUserRole();
$canManage = in_array($role, ['admin', 'owner', 'teacher'], true);
$isAdmin = in_array($role, ['admin', 'owner'], true);
$uploadRoot = __DIR__ . '/../../uploads/homework';
$uploadPublicRoot = 'uploads/homework';

function homework_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function homework_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function homework_status_key($status, $dueDate) {
    $status = strtolower((string)$status);
    if ($status === 'submitted' || $status === 'completed') {
        return 'submitted';
    }
    if ($status === 'expired' || $status === 'archived') {
        return 'expired';
    }
    if ($dueDate && $dueDate < date('Y-m-d')) {
        return 'expired';
    }
    return 'active';
}

function homework_legacy_status($status) {
    $status = strtolower((string)$status);
    if ($status === 'submitted') {
        return 'Completed';
    }
    if ($status === 'expired') {
        return 'Archived';
    }
    return 'Assigned';
}

function homework_status_badge($status, $dueDate) {
    $key = homework_status_key($status, $dueDate);
    $classes = [
        'active' => 'bg-success',
        'submitted' => 'bg-primary',
        'expired' => 'bg-danger',
    ];
    return '<span class="badge ' . $classes[$key] . '">' . ucfirst($key) . '</span>';
}

function homework_teacher_id(PDO $db) {
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

function homework_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS homework_diary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id VARCHAR(100) DEFAULT NULL,
        section_id VARCHAR(50) DEFAULT NULL,
        subject_id VARCHAR(120) DEFAULT NULL,
        teacher_id INT DEFAULT NULL,
        homework_title VARCHAR(180) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        attachment VARCHAR(255) DEFAULT NULL,
        diary_date DATE DEFAULT NULL,
        class VARCHAR(100) DEFAULT NULL,
        section VARCHAR(50) DEFAULT NULL,
        subject VARCHAR(120) DEFAULT NULL,
        title VARCHAR(180) DEFAULT NULL,
        homework TEXT DEFAULT NULL,
        instructions TEXT DEFAULT NULL,
        assigned_by INT DEFAULT NULL,
        status ENUM('Assigned','Completed','Archived') DEFAULT 'Assigned',
        homework_status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_homework_class (class_id, section_id),
        INDEX idx_homework_due (due_date),
        INDEX idx_homework_status (homework_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'class_id' => "ALTER TABLE homework_diary ADD COLUMN class_id VARCHAR(100) DEFAULT NULL",
        'section_id' => "ALTER TABLE homework_diary ADD COLUMN section_id VARCHAR(50) DEFAULT NULL",
        'subject_id' => "ALTER TABLE homework_diary ADD COLUMN subject_id VARCHAR(120) DEFAULT NULL",
        'teacher_id' => "ALTER TABLE homework_diary ADD COLUMN teacher_id INT DEFAULT NULL",
        'homework_title' => "ALTER TABLE homework_diary ADD COLUMN homework_title VARCHAR(180) DEFAULT NULL",
        'description' => "ALTER TABLE homework_diary ADD COLUMN description TEXT DEFAULT NULL",
        'attachment' => "ALTER TABLE homework_diary ADD COLUMN attachment VARCHAR(255) DEFAULT NULL",
        'diary_date' => "ALTER TABLE homework_diary ADD COLUMN diary_date DATE DEFAULT NULL",
        'class' => "ALTER TABLE homework_diary ADD COLUMN class VARCHAR(100) DEFAULT NULL",
        'section' => "ALTER TABLE homework_diary ADD COLUMN section VARCHAR(50) DEFAULT NULL",
        'subject' => "ALTER TABLE homework_diary ADD COLUMN subject VARCHAR(120) DEFAULT NULL",
        'title' => "ALTER TABLE homework_diary ADD COLUMN title VARCHAR(180) DEFAULT NULL",
        'homework' => "ALTER TABLE homework_diary ADD COLUMN homework TEXT DEFAULT NULL",
        'instructions' => "ALTER TABLE homework_diary ADD COLUMN instructions TEXT DEFAULT NULL",
        'assigned_by' => "ALTER TABLE homework_diary ADD COLUMN assigned_by INT DEFAULT NULL",
        'homework_status' => "ALTER TABLE homework_diary ADD COLUMN homework_status VARCHAR(20) NOT NULL DEFAULT 'active'",
        'created_by' => "ALTER TABLE homework_diary ADD COLUMN created_by INT DEFAULT NULL",
        'updated_at' => "ALTER TABLE homework_diary ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'homework_diary', $column)) {
            $db->exec($sql);
        }
    }
    foreach ([
        "ALTER TABLE homework_diary MODIFY class_id VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE homework_diary MODIFY subject_id VARCHAR(120) DEFAULT NULL",
        "ALTER TABLE homework_diary MODIFY homework_title VARCHAR(180) DEFAULT NULL",
        "ALTER TABLE homework_diary MODIFY description TEXT DEFAULT NULL",
    ] as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            error_log('homework schema compatibility update skipped: ' . $e->getMessage());
        }
    }
    if (!columnExists($db, 'homework_diary', 'status')) {
        $db->exec("ALTER TABLE homework_diary ADD COLUMN status ENUM('Assigned','Completed','Archived') DEFAULT 'Assigned'");
    }
    $db->exec("UPDATE homework_diary SET status = CASE LOWER(COALESCE(status, 'assigned')) WHEN 'submitted' THEN 'Completed' WHEN 'completed' THEN 'Completed' WHEN 'archived' THEN 'Archived' WHEN 'expired' THEN 'Archived' ELSE 'Assigned' END WHERE status IS NULL OR status NOT IN ('Assigned','Completed','Archived')");

    if (columnExists($db, 'homework_diary', 'class')) {
        $db->exec("UPDATE homework_diary SET class_id = class WHERE (class_id IS NULL OR class_id = '') AND class IS NOT NULL");
    }
    if (columnExists($db, 'homework_diary', 'section')) {
        $db->exec("UPDATE homework_diary SET section_id = section WHERE (section_id IS NULL OR section_id = '') AND section IS NOT NULL");
    }
    if (columnExists($db, 'homework_diary', 'subject')) {
        $db->exec("UPDATE homework_diary SET subject_id = subject WHERE (subject_id IS NULL OR subject_id = '') AND subject IS NOT NULL");
    }
    if (columnExists($db, 'homework_diary', 'title')) {
        $db->exec("UPDATE homework_diary SET homework_title = title WHERE (homework_title IS NULL OR homework_title = '') AND title IS NOT NULL");
    }
    if (columnExists($db, 'homework_diary', 'homework')) {
        $db->exec("UPDATE homework_diary SET description = homework WHERE (description IS NULL OR description = '') AND homework IS NOT NULL");
    }
    if (columnExists($db, 'homework_diary', 'assigned_by')) {
        $db->exec("UPDATE homework_diary SET created_by = assigned_by WHERE created_by IS NULL AND assigned_by IS NOT NULL");
    }
    $db->exec("UPDATE homework_diary SET homework_status = CASE LOWER(COALESCE(homework_status, status, 'active')) WHEN 'submitted' THEN 'submitted' WHEN 'completed' THEN 'submitted' WHEN 'archived' THEN 'expired' WHEN 'expired' THEN 'expired' ELSE 'active' END WHERE homework_status IS NULL OR homework_status = '' OR homework_status NOT IN ('active','submitted','expired')");
}

function homework_store_attachment(array $file, string $uploadRoot, string $uploadPublicRoot): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('Attachment upload failed.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new Exception('Attachment must be 5MB or smaller.');
    }
    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
    if (!in_array($extension, $allowed, true)) {
        throw new Exception('Attachment type is not allowed. Allowed: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG.');
    }
    if (in_array($extension, ['jpg', 'jpeg', 'png'], true) && !@getimagesize((string)$file['tmp_name'])) {
        throw new Exception('Image attachment is not valid.');
    }
    if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
        throw new Exception('Homework upload directory is not writable.');
    }
    $filename = 'homework_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = rtrim($uploadRoot, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new Exception('Could not save homework attachment.');
    }
    return $uploadPublicRoot . '/' . $filename;
}

function homework_save(PDO $db, array $data, ?int $id = null): void {
    $columns = [
        'class_id' => $data['class_id'],
        'section_id' => $data['section_id'],
        'subject_id' => $data['subject_id'],
        'teacher_id' => $data['teacher_id'],
        'homework_title' => $data['homework_title'],
        'description' => $data['description'],
        'due_date' => $data['due_date'],
        'homework_status' => $data['status'],
        'created_by' => $data['created_by'],
    ];
    if ($data['attachment'] !== null) {
        $columns['attachment'] = $data['attachment'];
    }
    $legacyMap = [
        'diary_date' => $data['diary_date'],
        'class' => $data['class_id'],
        'section' => $data['section_id'],
        'subject' => $data['subject_id'],
        'title' => $data['homework_title'],
        'homework' => $data['description'],
        'instructions' => '',
        'assigned_by' => $data['created_by'],
        'status' => homework_legacy_status($data['status']),
    ];
    foreach ($legacyMap as $column => $value) {
        if (columnExists($db, 'homework_diary', $column)) {
            $columns[$column] = $value;
        }
    }

    if ($id === null) {
        $sql = 'INSERT INTO homework_diary (`' . implode('`, `', array_keys($columns)) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($columns));
        return;
    }

    unset($columns['created_by']);
    $sets = [];
    foreach (array_keys($columns) as $column) {
        $sets[] = "`$column` = ?";
    }
    $values = array_values($columns);
    $values[] = $id;
    $stmt = $db->prepare('UPDATE homework_diary SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);
}

homework_ensure_schema($db);

$teacherId = homework_teacher_id($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        requireCsrfToken();

        if (in_array($action, ['add_homework', 'edit_homework'], true)) {
            if (!$canManage) {
                throw new Exception('You are not allowed to manage homework.');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($action === 'edit_homework' && $id <= 0) {
                throw new Exception('Invalid homework selected.');
            }
            if ($action === 'edit_homework' && !$isAdmin) {
                $stmt = $db->prepare('SELECT id FROM homework_diary WHERE id = ? AND (created_by = ? OR teacher_id = ?) LIMIT 1');
                $stmt->execute([$id, getUserId(), $teacherId]);
                if (!$stmt->fetchColumn()) {
                    throw new Exception('You can only edit homework assigned by you.');
                }
            }

            $classId = sanitizeInput($_POST['class_id'] ?? '');
            $sectionId = sanitizeInput($_POST['section_id'] ?? '');
            $subjectId = sanitizeInput($_POST['subject_id'] ?? '');
            $selectedTeacherId = $isAdmin ? (int)($_POST['teacher_id'] ?? 0) : (int)$teacherId;
            $title = sanitizeInput($_POST['homework_title'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $dueDate = $_POST['due_date'] ?? '';
            $status = in_array($_POST['status'] ?? 'active', ['active', 'submitted', 'expired'], true) ? $_POST['status'] : 'active';

            if ($classId === '' || $subjectId === '' || $title === '' || $description === '' || !homework_valid_date($dueDate)) {
                throw new Exception('Please fill all required homework fields.');
            }

            $attachment = homework_store_attachment($_FILES['attachment'] ?? [], $uploadRoot, $uploadPublicRoot);
            homework_save($db, [
                'class_id' => $classId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'teacher_id' => $selectedTeacherId ?: null,
                'homework_title' => $title,
                'description' => $description,
                'due_date' => $dueDate,
                'status' => $status,
                'attachment' => $attachment,
                'created_by' => getUserId(),
                'diary_date' => date('Y-m-d'),
            ], $action === 'edit_homework' ? $id : null);

            setFlashMessage('success', $action === 'edit_homework' ? 'Homework updated successfully.' : 'Homework added successfully.');
            redirect('index.php');
        }

        if ($action === 'delete_homework') {
            if (!$canManage) {
                throw new Exception('You are not allowed to delete homework.');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid homework selected.');
            }
            if ($isAdmin) {
                $stmt = $db->prepare('DELETE FROM homework_diary WHERE id = ?');
                $stmt->execute([$id]);
            } else {
                $stmt = $db->prepare('DELETE FROM homework_diary WHERE id = ? AND (created_by = ? OR teacher_id = ?)');
                $stmt->execute([$id, getUserId(), $teacherId]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('You can only delete homework assigned by you.');
                }
            }
            setFlashMessage('success', 'Homework deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

$classes = getAllClasses($db);
$sections = getAllSections($db);
$teachers = getTeachers($db);
$subjects = getAllSubjects($db);
if (empty($classes)) {
    foreach (['Matric', 'FA/FSc', 'ICS', 'ADP', 'BSCS', 'BSIT', 'B.Ed'] as $className) {
        $classes[] = ['id' => $className, 'class_name' => $className];
    }
}
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

$filterDate = homework_valid_date($_GET['date'] ?? '') ? $_GET['date'] : '';
$filterClass = trim((string)($_GET['class_id'] ?? ''));
$filterSection = trim((string)($_GET['section_id'] ?? ''));
$filterStatus = in_array($_GET['status'] ?? '', ['active', 'submitted', 'expired'], true) ? $_GET['status'] : '';

$where = [];
$params = [];
if ($filterDate !== '') {
    $where[] = 'hd.due_date = ?';
    $params[] = $filterDate;
}
if ($filterClass !== '') {
    $where[] = 'hd.class_id = ?';
    $params[] = $filterClass;
}
if ($filterSection !== '') {
    $where[] = 'hd.section_id = ?';
    $params[] = $filterSection;
}
if ($filterStatus === 'expired') {
    $where[] = "LOWER(COALESCE(hd.homework_status, 'active')) <> 'submitted' AND hd.due_date < CURDATE()";
} elseif ($filterStatus === 'active') {
    $where[] = "LOWER(COALESCE(hd.homework_status, 'active')) = 'active' AND (hd.due_date IS NULL OR hd.due_date >= CURDATE())";
} elseif ($filterStatus !== '') {
    $where[] = "LOWER(COALESCE(hd.homework_status, 'active')) = ?";
    $params[] = $filterStatus;
}
if ($role === 'student' && tableExists($db, 'students') && columnExists($db, 'students', 'user_id')) {
    $student = sharedFetchOne($db, 'SELECT class, section FROM students WHERE user_id = ? LIMIT 1', [getUserId()]);
    if ($student) {
        $where[] = 'hd.class_id = ?';
        $params[] = $student['class'];
        if (!empty($student['section'])) {
            $where[] = "(hd.section_id = ? OR hd.section_id = '')";
            $params[] = $student['section'];
        }
    }
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = $db->prepare("
    SELECT hd.*,
           COALESCE(NULLIF(hd.homework_status, ''), CASE LOWER(COALESCE(hd.status, 'Assigned')) WHEN 'completed' THEN 'submitted' WHEN 'archived' THEN 'expired' ELSE 'active' END) AS display_status,
           COALESCE(s.full_name, u.full_name, u.username, 'Teacher') AS teacher_name
    FROM homework_diary hd
    LEFT JOIN staff s ON s.id = hd.teacher_id
    LEFT JOIN users u ON u.id = hd.created_by
    $whereSql
    ORDER BY hd.due_date DESC, hd.id DESC
");
$stmt->execute($params);
$homeworkRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['active' => 0, 'submitted' => 0, 'expired' => 0, 'total' => count($homeworkRows)];
foreach ($homeworkRows as $row) {
    $stats[homework_status_key($row['display_status'], $row['due_date'])]++;
}

$page_title = 'Homework Diary';
include '../../includes/header.php';
?>

<div class="container-fluid homework-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-book-open me-2" style="color:var(--teal);"></i>Homework / Student Diary</h2>
            <div class="text-muted">Assign, review, print, and track homework by class and section.</div>
        </div>
        <?php if ($canManage): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#homeworkModal"><i class="fas fa-plus me-1"></i>Add Homework</button>
        <?php endif; ?>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total</div><h3 class="fw-bold mb-0"><?= (int)$stats['total'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Active</div><h3 class="fw-bold text-success mb-0"><?= (int)$stats['active'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Submitted</div><h3 class="fw-bold text-primary mb-0"><?= (int)$stats['submitted'] ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Expired</div><h3 class="fw-bold text-danger mb-0"><?= (int)$stats['expired'] ?></h3></div></div></div>
    </div>

    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Due Date</label>
                    <input type="date" name="date" class="form-control" value="<?= homework_h($filterDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Class</label>
                    <select name="class_id" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?><option value="<?= homework_h($class['id']) ?>" <?= $filterClass === (string)$class['id'] ? 'selected' : '' ?>><?= homework_h($class['class_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?><option value="<?= homework_h($section['id']) ?>" <?= $filterSection === (string)$section['id'] ? 'selected' : '' ?>><?= homework_h($section['section_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach (['active', 'submitted', 'expired'] as $status): ?><option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">Filter</button>
                    <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0 fw-bold">Homework List</h5><span class="badge bg-light text-dark border"><?= count($homeworkRows) ?> records</span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle <?= $homeworkRows ? 'datatable' : '' ?>">
                    <thead class="table-light"><tr><th>Class</th><th>Subject</th><th>Homework</th><th>Due Date</th><th>Status</th><th>Teacher</th><th>Attachment</th><?php if ($canManage): ?><th class="text-end no-print">Actions</th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if (!$homeworkRows): ?><tr><td colspan="<?= $canManage ? 8 : 7 ?>" class="text-center text-muted py-4">No homework records found.</td></tr><?php endif; ?>
                        <?php foreach ($homeworkRows as $row): ?>
                            <?php $isDueSoon = $row['due_date'] && $row['due_date'] >= date('Y-m-d') && $row['due_date'] <= date('Y-m-d', strtotime('+2 days')); ?>
                            <tr class="<?= homework_status_key($row['display_status'], $row['due_date']) === 'expired' ? 'table-danger' : ($isDueSoon ? 'table-warning' : '') ?>">
                                <td><strong><?= homework_h($row['class_id']) ?></strong><div class="small text-muted"><?= homework_h($row['section_id'] ?: 'All Sections') ?></div></td>
                                <td><?= homework_h($row['subject_id']) ?></td>
                                <td><strong><?= homework_h($row['homework_title']) ?></strong><div class="small text-muted"><?= nl2br(homework_h($row['description'])) ?></div></td>
                                <td><?= homework_h(date('d M Y', strtotime($row['due_date']))) ?></td>
                                <td><?= homework_status_badge($row['display_status'], $row['due_date']) ?></td>
                                <td><?= homework_h($row['teacher_name']) ?></td>
                                <td><?php if (!empty($row['attachment'])): ?><a href="<?= BASE_URL . homework_h($row['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open</a><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                <?php if ($canManage): ?>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-outline-primary edit-homework-btn"
                                            data-bs-toggle="modal" data-bs-target="#editHomeworkModal"
                                            data-id="<?= (int)$row['id'] ?>"
                                            data-class="<?= homework_h($row['class_id']) ?>"
                                            data-section="<?= homework_h($row['section_id']) ?>"
                                            data-subject="<?= homework_h($row['subject_id']) ?>"
                                            data-teacher="<?= (int)$row['teacher_id'] ?>"
                                            data-title="<?= homework_h($row['homework_title']) ?>"
                                            data-description="<?= homework_h($row['description']) ?>"
                                            data-due="<?= homework_h($row['due_date']) ?>"
                                            data-status="<?= homework_h(homework_status_key($row['display_status'], $row['due_date'])) ?>"><i class="fas fa-pen"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this homework?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete_homework"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<datalist id="homeworkSubjects">
    <?php foreach ($subjects as $subject): ?><option value="<?= homework_h($subject['id']) ?>"><?= homework_h($subject['subject_name']) ?></option><?php endforeach; ?>
</datalist>

<div class="modal fade" id="homeworkModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content border-0 shadow"><form method="POST" enctype="multipart/form-data"><?= csrfTokenInput() ?><input type="hidden" name="action" value="add_homework"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Add Homework</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-md-4"><label class="form-label">Class *</label><select name="class_id" class="form-select" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= homework_h($class['id']) ?>"><?= homework_h($class['class_name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Section</label><select name="section_id" class="form-select"><option value="">All sections</option><?php foreach ($sections as $section): ?><option value="<?= homework_h($section['id']) ?>"><?= homework_h($section['section_name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Subject *</label><input list="homeworkSubjects" name="subject_id" class="form-control" required></div><?php if ($isAdmin): ?><div class="col-md-6"><label class="form-label">Teacher</label><select name="teacher_id" class="form-select"><option value="">Unassigned</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>"><?= homework_h($teacher['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="<?= $isAdmin ? 'col-md-6' : 'col-md-12' ?>"><label class="form-label">Due Date *</label><input type="date" name="due_date" class="form-control" required></div><div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="submitted">Submitted</option><option value="expired">Expired</option></select></div><div class="col-md-6"><label class="form-label">Attachment</label><input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"></div><div class="col-12"><label class="form-label">Homework Title *</label><input name="homework_title" class="form-control" required></div><div class="col-12"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="5" required></textarea></div></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Homework</button></div></form></div></div>
</div>

<div class="modal fade" id="editHomeworkModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content border-0 shadow"><form method="POST" enctype="multipart/form-data"><?= csrfTokenInput() ?><input type="hidden" name="action" value="edit_homework"><input type="hidden" name="id" id="edit_id"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit Homework</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-md-4"><label class="form-label">Class *</label><select name="class_id" id="edit_class_id" class="form-select" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= homework_h($class['id']) ?>"><?= homework_h($class['class_name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Section</label><select name="section_id" id="edit_section_id" class="form-select"><option value="">All sections</option><?php foreach ($sections as $section): ?><option value="<?= homework_h($section['id']) ?>"><?= homework_h($section['section_name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Subject *</label><input list="homeworkSubjects" name="subject_id" id="edit_subject_id" class="form-control" required></div><?php if ($isAdmin): ?><div class="col-md-6"><label class="form-label">Teacher</label><select name="teacher_id" id="edit_teacher_id" class="form-select"><option value="">Unassigned</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>"><?= homework_h($teacher['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="<?= $isAdmin ? 'col-md-6' : 'col-md-12' ?>"><label class="form-label">Due Date *</label><input type="date" name="due_date" id="edit_due_date" class="form-control" required></div><div class="col-md-6"><label class="form-label">Status</label><select name="status" id="edit_status" class="form-select"><option value="active">Active</option><option value="submitted">Submitted</option><option value="expired">Expired</option></select></div><div class="col-md-6"><label class="form-label">Replace Attachment</label><input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"></div><div class="col-12"><label class="form-label">Homework Title *</label><input name="homework_title" id="edit_homework_title" class="form-control" required></div><div class="col-12"><label class="form-label">Description *</label><textarea name="description" id="edit_description" class="form-control" rows="5" required></textarea></div></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Homework</button></div></form></div></div>
</div>
<?php endif; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    .btn-outline-primary { color:var(--navy); border-color:var(--teal); }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop, .btn, form { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-homework-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const map = {
                id: 'edit_id',
                class: 'edit_class_id',
                section: 'edit_section_id',
                subject: 'edit_subject_id',
                teacher: 'edit_teacher_id',
                title: 'edit_homework_title',
                description: 'edit_description',
                due: 'edit_due_date',
                status: 'edit_status'
            };
            Object.keys(map).forEach(function (key) {
                const el = document.getElementById(map[key]);
                if (el) el.value = button.dataset[key] || '';
            });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
