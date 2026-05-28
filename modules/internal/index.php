<?php
// File: modules/internal/index.php
require_once '../../config/db.php';

requireRole(['admin', 'owner', 'staff', 'teacher', 'hr', 'accounts', 'accountant']);

$db = (new Database())->getConnection();
$role = getUserRole();
$userId = (int)getUserId();
$isAdmin = in_array($role, ['admin', 'owner'], true);
$types = ['note', 'announcement', 'file'];
$statuses = ['active', 'inactive', 'archived'];
$visibilities = ['private', 'shared', 'all_staff'];
$defaultCategories = ['General', 'Office', 'Academic', 'Finance', 'HR', 'Meeting', 'Policy', 'Forms'];
$uploadRoot = __DIR__ . '/../../uploads/internal';
$uploadPublicRoot = 'uploads/internal';
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'txt'];
$maxFileSize = 10 * 1024 * 1024;

function internal_h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function internal_date($date): string {
    return $date ? date('d M Y, h:i A', strtotime((string)$date)) : '-';
}

function internal_excerpt($value, int $limit = 110): string {
    $text = trim((string)($value ?? ''));
    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
}

function internal_current_staff_id(PDO $db, int $userId): ?int {
    if (!$userId || !tableExists($db, 'staff') || !columnExists($db, 'staff', 'user_id')) {
        return null;
    }
    $stmt = $db->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function internal_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS internal_management (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(220) NOT NULL,
        description TEXT DEFAULT NULL,
        type VARCHAR(30) NOT NULL DEFAULT 'note',
        category VARCHAR(120) DEFAULT 'General',
        file_path VARCHAR(255) DEFAULT NULL,
        visibility VARCHAR(30) NOT NULL DEFAULT 'private',
        created_by INT DEFAULT NULL,
        assigned_to INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        INDEX idx_internal_type (type),
        INDEX idx_internal_category (category),
        INDEX idx_internal_status (status),
        INDEX idx_internal_assigned_to (assigned_to),
        INDEX idx_internal_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'title' => "ALTER TABLE internal_management ADD COLUMN title VARCHAR(220) NOT NULL",
        'description' => "ALTER TABLE internal_management ADD COLUMN description TEXT DEFAULT NULL",
        'type' => "ALTER TABLE internal_management ADD COLUMN type VARCHAR(30) NOT NULL DEFAULT 'note'",
        'category' => "ALTER TABLE internal_management ADD COLUMN category VARCHAR(120) DEFAULT 'General'",
        'file_path' => "ALTER TABLE internal_management ADD COLUMN file_path VARCHAR(255) DEFAULT NULL",
        'visibility' => "ALTER TABLE internal_management ADD COLUMN visibility VARCHAR(30) NOT NULL DEFAULT 'private'",
        'created_by' => "ALTER TABLE internal_management ADD COLUMN created_by INT DEFAULT NULL",
        'assigned_to' => "ALTER TABLE internal_management ADD COLUMN assigned_to INT DEFAULT NULL",
        'created_at' => "ALTER TABLE internal_management ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'status' => "ALTER TABLE internal_management ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'internal_management', $column)) {
            $db->exec($sql);
        }
    }
}

function internal_store_file(array $file, string $uploadRoot, string $uploadPublicRoot, array $allowedExtensions, int $maxFileSize): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('Please upload a valid file.');
    }
    if (($file['size'] ?? 0) > $maxFileSize) {
        throw new Exception('File size must not exceed 10 MB.');
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Allowed files: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, TXT.');
    }

    $allowedMimes = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg', 'image/png', 'text/plain',
    ];
    $mime = function_exists('mime_content_type') ? (string)@mime_content_type((string)$file['tmp_name']) : '';
    if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
        throw new Exception('Uploaded file content does not match the allowed file types.');
    }

    if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
        throw new Exception('Internal upload directory is not writable.');
    }
    $storedName = 'internal_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = rtrim($uploadRoot, '/\\') . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new Exception('Could not save uploaded file.');
    }
    return $uploadPublicRoot . '/' . $storedName;
}

function internal_can_access(array $row, bool $isAdmin, int $userId, ?int $staffId): bool {
    if ($isAdmin) {
        return true;
    }
    if ((int)($row['created_by'] ?? 0) === $userId) {
        return true;
    }
    if (($row['visibility'] ?? '') === 'all_staff') {
        return true;
    }
    return $staffId && (int)($row['assigned_to'] ?? 0) === $staffId;
}

internal_ensure_schema($db);
$currentStaffId = internal_current_staff_id($db, $userId);
$staffList = [];
if (tableExists($db, 'staff')) {
    $designationExpr = columnExists($db, 'staff', 'designation') ? 'designation' : (columnExists($db, 'staff', 'role') ? 'role' : "''");
    $where = columnExists($db, 'staff', 'status') ? "WHERE LOWER(COALESCE(status, 'active')) IN ('active', 'staff', '')" : '';
    $staffList = $db->query("SELECT id, full_name, $designationExpr AS designation FROM staff $where ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if (in_array($action, ['add', 'edit'], true)) {
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $type = in_array($_POST['type'] ?? 'note', $types, true) ? $_POST['type'] : 'note';
            $category = sanitizeInput($_POST['category'] ?? 'General') ?: 'General';
            $visibility = in_array($_POST['visibility'] ?? 'private', $visibilities, true) ? $_POST['visibility'] : 'private';
            $status = in_array($_POST['status'] ?? 'active', $statuses, true) ? $_POST['status'] : 'active';
            $assignedTo = (int)($_POST['assigned_to'] ?? 0) ?: null;

            if ($title === '') {
                throw new Exception('Please enter a title.');
            }
            if (!$isAdmin && $type === 'announcement') {
                throw new Exception('Only admin users can post internal announcements.');
            }
            if ($visibility === 'shared' && !$assignedTo) {
                throw new Exception('Please select a staff member for shared visibility.');
            }

            $filePath = internal_store_file($_FILES['file'] ?? [], $uploadRoot, $uploadPublicRoot, $allowedExtensions, $maxFileSize);

            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO internal_management (title, description, type, category, file_path, visibility, created_by, assigned_to, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $type, $category, $filePath, $visibility, $userId, $assignedTo, $status]);
                setFlashMessage('success', 'Internal record created successfully.');
            } else {
                if ($id <= 0) {
                    throw new Exception('Invalid record selected.');
                }
                $stmt = $db->prepare('SELECT * FROM internal_management WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing || (!$isAdmin && (int)$existing['created_by'] !== $userId)) {
                    throw new Exception('You can only edit records you created.');
                }
                $filePath = $filePath ?: ($existing['file_path'] ?? null);
                $stmt = $db->prepare("UPDATE internal_management SET title = ?, description = ?, type = ?, category = ?, file_path = ?, visibility = ?, assigned_to = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $description, $type, $category, $filePath, $visibility, $assignedTo, $status, $id]);
                setFlashMessage('success', 'Internal record updated successfully.');
            }
            redirect('index.php');
        }

        if ($action === 'delete') {
            if ($id <= 0) {
                throw new Exception('Invalid record selected.');
            }
            $stmt = $db->prepare('SELECT * FROM internal_management WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record || (!$isAdmin && (int)$record['created_by'] !== $userId)) {
                throw new Exception('You can only delete records you created.');
            }
            $stmt = $db->prepare('DELETE FROM internal_management WHERE id = ?');
            $stmt->execute([$id]);
            setFlashMessage('success', 'Internal record deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    $stmt = $db->prepare('SELECT * FROM internal_management WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record || !internal_can_access($record, $isAdmin, $userId, $currentStaffId) || empty($record['file_path'])) {
        http_response_code(404);
        exit('File not found.');
    }
    $root = realpath($uploadRoot);
    $filePath = realpath(__DIR__ . '/../../' . $record['file_path']);
    if (!$root || !$filePath || strpos($filePath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
        http_response_code(404);
        exit('File not found.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

$filterType = in_array($_GET['type'] ?? '', $types, true) ? $_GET['type'] : '';
$filterCategory = sanitizeInput($_GET['category'] ?? '');
$filterStatus = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$where = [];
$params = [];

if ($filterType !== '') {
    $where[] = 'im.type = ?';
    $params[] = $filterType;
}
if ($filterCategory !== '') {
    $where[] = 'im.category = ?';
    $params[] = $filterCategory;
}
if ($filterStatus !== '') {
    $where[] = 'im.status = ?';
    $params[] = $filterStatus;
}
if (!$isAdmin) {
    $visibilitySql = ['im.created_by = ?', "im.visibility = 'all_staff'"];
    $params[] = $userId;
    if ($currentStaffId) {
        $visibilitySql[] = 'im.assigned_to = ?';
        $params[] = $currentStaffId;
    }
    $where[] = '(' . implode(' OR ', $visibilitySql) . ')';
    $where[] = "im.status = 'active'";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT im.*, u.full_name AS created_by_name, s.full_name AS assigned_staff_name
    FROM internal_management im
    LEFT JOIN users u ON u.id = im.created_by
    LEFT JOIN staff s ON s.id = im.assigned_to
    $whereSql
    ORDER BY im.created_at DESC, im.id DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['note' => 0, 'announcement' => 0, 'file' => 0];
foreach ($records as $record) {
    if (isset($stats[$record['type']])) {
        $stats[$record['type']]++;
    }
}
$categoryRows = $db->query("SELECT DISTINCT category FROM internal_management WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$categories = array_values(array_unique(array_merge($defaultCategories, $categoryRows ?: [])));

$page_title = 'Internal Management';
include '../../includes/header.php';
?>

<style>
@media print {
    .no-print, .sidebar, .navbar, .main-header { display: none !important; }
    .content-wrapper, .main-content { margin: 0 !important; padding: 0 !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
}
</style>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
    <div>
        <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
        <h2 class="page-title mb-1">Internal Management</h2>
        <div class="text-muted">Internal notes, staff announcements, and office files.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary rounded-pill px-3" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#internalModal"><i class="fas fa-plus me-1"></i>Add Record</button>
    </div>
</div>

<?php displayFlashMessage(); ?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small text-uppercase fw-bold">Internal Notes</div><h3 class="fw-bold mb-0"><?= (int)$stats['note'] ?></h3></div><i class="fas fa-note-sticky text-primary fs-2"></i></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small text-uppercase fw-bold">Announcements</div><h3 class="fw-bold mb-0"><?= (int)$stats['announcement'] ?></h3></div><i class="fas fa-bullhorn text-success fs-2"></i></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small text-uppercase fw-bold">Internal Files</div><h3 class="fw-bold mb-0"><?= (int)$stats['file'] ?></h3></div><i class="fas fa-folder-open text-warning fs-2"></i></div></div></div>
</div>

<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-md-3"><label class="form-label small fw-bold">Type</label><select name="type" class="form-select"><option value="">All Types</option><?php foreach ($types as $type): ?><option value="<?= internal_h($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= internal_h(ucfirst($type)) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label small fw-bold">Category</label><select name="category" class="form-select"><option value="">All Categories</option><?php foreach ($categories as $category): ?><option value="<?= internal_h($category) ?>" <?= $filterCategory === $category ? 'selected' : '' ?>><?= internal_h($category) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label small fw-bold">Status</label><select name="status" class="form-select"><option value="">All Status</option><?php foreach ($statuses as $status): ?><option value="<?= internal_h($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= internal_h(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fas fa-filter me-1"></i>Filter</button><a href="index.php" class="btn btn-outline-secondary flex-fill"><i class="fas fa-rotate-right me-1"></i>Reset</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Internal Records</h5>
        <span class="badge bg-light text-dark border"><?= count($records) ?> records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Title</th><th>Type</th><th>Category</th><th>Visibility</th><th>Created</th><th>Status</th><th class="text-end no-print">Actions</th></tr></thead>
                <tbody>
                    <?php if (!$records): ?><tr><td colspan="7" class="text-center text-muted py-4">No internal records found.</td></tr><?php endif; ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= internal_h($record['title']) ?></div>
                                <?php if ($record['description']): ?><div class="small text-muted"><?= internal_h(internal_excerpt($record['description'])) ?></div><?php endif; ?>
                                <?php if ($record['file_path']): ?><a class="small" href="?download=<?= (int)$record['id'] ?>"><i class="fas fa-paperclip me-1"></i>Download attachment</a><?php endif; ?>
                            </td>
                            <td><span class="badge <?= $record['type'] === 'announcement' ? 'bg-success' : ($record['type'] === 'file' ? 'bg-warning text-dark' : 'bg-primary') ?>"><?= internal_h(ucfirst($record['type'])) ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?= internal_h($record['category'] ?: 'General') ?></span></td>
                            <td>
                                <div><?= internal_h(str_replace('_', ' ', ucfirst($record['visibility']))) ?></div>
                                <?php if ($record['assigned_staff_name']): ?><small class="text-muted"><?= internal_h($record['assigned_staff_name']) ?></small><?php endif; ?>
                            </td>
                            <td><div><?= internal_date($record['created_at']) ?></div><small class="text-muted"><?= internal_h($record['created_by_name'] ?: 'System') ?></small></td>
                            <td><span class="badge <?= $record['status'] === 'active' ? 'bg-success' : ($record['status'] === 'archived' ? 'bg-dark' : 'bg-secondary') ?>"><?= internal_h(ucfirst($record['status'])) ?></span></td>
                            <td class="text-end no-print">
                                <?php if ($isAdmin || (int)$record['created_by'] === $userId): ?>
                                    <button class="btn btn-sm btn-outline-primary edit-internal" data-bs-toggle="modal" data-bs-target="#editInternalModal" data-record='<?= internal_h(json_encode($record)) ?>'><i class="fas fa-pen"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this internal record?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$record['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                <?php else: ?>
                                    <span class="text-muted small">View only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$renderForm = function (string $prefix) use ($types, $statuses, $visibilities, $categories, $staffList, $isAdmin) {
?>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Title *</label><input type="text" name="title" id="<?= $prefix ?>title" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Type</label><select name="type" id="<?= $prefix ?>type" class="form-select"><?php foreach ($types as $type): ?><option value="<?= internal_h($type) ?>" <?= (!$isAdmin && $type === 'announcement') ? 'disabled' : '' ?>><?= internal_h(ucfirst($type)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Category</label><input list="internalCategories" name="category" id="<?= $prefix ?>category" class="form-control" value="General"></div>
        <div class="col-md-4"><label class="form-label">Visibility</label><select name="visibility" id="<?= $prefix ?>visibility" class="form-select"><?php foreach ($visibilities as $visibility): ?><option value="<?= internal_h($visibility) ?>"><?= internal_h(ucwords(str_replace('_', ' ', $visibility))) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Assign To</label><select name="assigned_to" id="<?= $prefix ?>assigned_to" class="form-select"><option value="">None</option><?php foreach ($staffList as $staff): ?><option value="<?= (int)$staff['id'] ?>"><?= internal_h($staff['full_name'] . ' - ' . ($staff['designation'] ?: 'Staff')) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="<?= $prefix ?>status" class="form-select"><?php foreach ($statuses as $status): ?><option value="<?= internal_h($status) ?>"><?= internal_h(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">File Attachment</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt"><small class="text-muted">Max 10 MB.</small></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="<?= $prefix ?>description" class="form-control" rows="4"></textarea></div>
    </div>
<?php
};
?>

<datalist id="internalCategories"><?php foreach ($categories as $category): ?><option value="<?= internal_h($category) ?>"><?php endforeach; ?></datalist>

<div class="modal fade no-print" id="internalModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST" enctype="multipart/form-data">
            <?= csrfTokenInput() ?><input type="hidden" name="action" value="add">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Add Internal Record</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><?php $renderForm('add_'); ?></div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Record</button></div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="editInternalModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST" enctype="multipart/form-data">
            <?= csrfTokenInput() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit Internal Record</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><?php $renderForm('edit_'); ?></div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Record</button></div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.edit-internal').forEach(function (button) {
    button.addEventListener('click', function () {
        const record = JSON.parse(button.dataset.record || '{}');
        ['id', 'title', 'type', 'category', 'visibility', 'assigned_to', 'status', 'description'].forEach(function (field) {
            const el = document.getElementById('edit_' + field);
            if (el) el.value = record[field] || '';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
