<?php
// File: modules/downloads/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$db = (new Database())->getConnection();
$role = getUserRole();
$userId = getUserId();
$isAdmin = in_array($role, ['admin', 'owner'], true);
$uploadRoot = __DIR__ . '/../../uploads/downloads';
$uploadPublicRoot = 'uploads/downloads';
$allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'ppt', 'pptx'];
$maxFileSize = 10 * 1024 * 1024;

function dl_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function dl_format_bytes(int $bytes): string {
    return $bytes >= 1048576 ? round($bytes / 1048576, 2) . ' MB' : round(max($bytes, 0) / 1024, 2) . ' KB';
}

function dl_icon(string $type): array {
    $type = strtolower($type);
    if ($type === 'pdf') return ['fa-file-pdf', 'text-danger'];
    if (in_array($type, ['doc', 'docx'], true)) return ['fa-file-word', 'text-primary'];
    if (in_array($type, ['ppt', 'pptx'], true)) return ['fa-file-powerpoint', 'text-warning'];
    if (in_array($type, ['jpg', 'jpeg', 'png'], true)) return ['fa-file-image', 'text-success'];
    return ['fa-file', 'text-secondary'];
}

function dl_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS downloads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(220) NOT NULL,
        description TEXT DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(20) DEFAULT NULL,
        file_size VARCHAR(40) DEFAULT '0',
        uploaded_by INT DEFAULT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        visibility VARCHAR(20) NOT NULL DEFAULT 'public',
        download_count INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_downloads_category (category),
        INDEX idx_downloads_status (status),
        INDEX idx_downloads_uploaded_at (uploaded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'title' => "ALTER TABLE downloads ADD COLUMN title VARCHAR(220) DEFAULT NULL",
        'description' => "ALTER TABLE downloads ADD COLUMN description TEXT DEFAULT NULL",
        'category' => "ALTER TABLE downloads ADD COLUMN category VARCHAR(100) DEFAULT NULL",
        'file_path' => "ALTER TABLE downloads ADD COLUMN file_path VARCHAR(255) DEFAULT NULL",
        'file_name' => "ALTER TABLE downloads ADD COLUMN file_name VARCHAR(255) DEFAULT NULL",
        'file_type' => "ALTER TABLE downloads ADD COLUMN file_type VARCHAR(20) DEFAULT NULL",
        'file_size' => "ALTER TABLE downloads ADD COLUMN file_size VARCHAR(40) DEFAULT '0'",
        'uploaded_by' => "ALTER TABLE downloads ADD COLUMN uploaded_by INT DEFAULT NULL",
        'uploaded_at' => "ALTER TABLE downloads ADD COLUMN uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'status' => "ALTER TABLE downloads ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
        'visibility' => "ALTER TABLE downloads ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'public'",
        'download_count' => "ALTER TABLE downloads ADD COLUMN download_count INT NOT NULL DEFAULT 0",
        'created_at' => "ALTER TABLE downloads ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'downloads', $column)) {
            $db->exec($sql);
        }
    }
    try {
        $db->exec("UPDATE downloads SET uploaded_at = created_at WHERE uploaded_at IS NULL AND created_at IS NOT NULL");
    } catch (Exception $e) {
        error_log('downloads schema sync skipped: ' . $e->getMessage());
    }
}

function dl_store_file(array $file, string $uploadRoot, string $uploadPublicRoot, array $allowedExtensions, int $maxFileSize): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Please select a valid file.');
    }
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxFileSize) {
        throw new Exception('File size must be between 1 byte and 10 MB.');
    }
    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG.');
    }
    if (in_array($extension, ['jpg', 'jpeg', 'png'], true) && !@getimagesize((string)$file['tmp_name'])) {
        throw new Exception('Image file is not valid.');
    }
    if (function_exists('finfo_open')) {
        $info = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $info ? (string)finfo_file($info, (string)$file['tmp_name']) : '';
        if ($info) finfo_close($info);
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/octet-stream',
            'application/zip',
            'image/jpeg',
            'image/png',
        ];
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            throw new Exception('Uploaded file content does not match the allowed types.');
        }
    }
    if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
        throw new Exception('Downloads upload directory is not writable.');
    }
    $storedName = 'download_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = rtrim($uploadRoot, '/\\') . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new Exception('Could not save uploaded file.');
    }
    return [
        'file_path' => $uploadPublicRoot . '/' . $storedName,
        'file_name' => $originalName,
        'file_type' => $extension,
        'file_size' => (int)$file['size'],
    ];
}

function dl_file_size_value($value): int {
    if (is_numeric($value)) {
        return (int)$value;
    }
    $value = strtoupper(trim((string)$value));
    if (preg_match('/([0-9.]+)\s*MB/', $value, $m)) {
        return (int)round((float)$m[1] * 1024 * 1024);
    }
    if (preg_match('/([0-9.]+)\s*KB/', $value, $m)) {
        return (int)round((float)$m[1] * 1024);
    }
    return 0;
}

dl_ensure_schema($db);

if (isset($_GET['download_id'])) {
    $id = (int)$_GET['download_id'];
    $where = 'id = ?';
    $params = [$id];
    if (!$isAdmin) {
        $where .= " AND LOWER(status) = 'active' AND LOWER(visibility) = 'public'";
    }
    $stmt = $db->prepare("SELECT file_path, file_name FROM downloads WHERE $where LIMIT 1");
    $stmt->execute($params);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    $downloadsRoot = realpath($uploadRoot);
    $filePath = $file ? realpath(__DIR__ . '/../../' . $file['file_path']) : false;
    if ($file && $downloadsRoot && $filePath && strpos($filePath, rtrim($downloadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0 && is_file($filePath)) {
        $db->prepare('UPDATE downloads SET download_count = download_count + 1 WHERE id = ?')->execute([$id]);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['file_name'] ?: $file['file_path']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($filePath);
        exit;
    }
    setFlashMessage('error', 'The requested file could not be found.');
    redirect('index.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        if (!$isAdmin) {
            throw new Exception('Only admin can manage downloads.');
        }
        $action = $_POST['action'] ?? '';

        if ($action === 'upload') {
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $category = sanitizeInput($_POST['category'] ?? '');
            $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
            $visibility = in_array($_POST['visibility'] ?? 'public', ['public', 'private'], true) ? $_POST['visibility'] : 'public';
            if ($title === '' || $category === '') {
                throw new Exception('Please enter title and category.');
            }
            $fileData = dl_store_file($_FILES['file'] ?? [], $uploadRoot, $uploadPublicRoot, $allowedExtensions, $maxFileSize);
            $stmt = $db->prepare("INSERT INTO downloads (title, description, category, file_path, file_name, file_type, file_size, uploaded_by, status, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $category, $fileData['file_path'], $fileData['file_name'], $fileData['file_type'], $fileData['file_size'], $userId, $status, $visibility]);
            setFlashMessage('success', 'File uploaded successfully.');
            redirect('index.php');
        }

        if ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT status FROM downloads WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $current = strtolower((string)$stmt->fetchColumn());
            if ($current === '') {
                throw new Exception('Download not found.');
            }
            $db->prepare('UPDATE downloads SET status = ? WHERE id = ?')->execute([$current === 'active' ? 'inactive' : 'active', $id]);
            setFlashMessage('success', 'Download status updated.');
            redirect('index.php');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT file_path FROM downloads WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('Download not found.');
            }
            $downloadsRoot = realpath($uploadRoot);
            $filePath = realpath(__DIR__ . '/../../' . $row['file_path']);
            if ($downloadsRoot && $filePath && strpos($filePath, rtrim($downloadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0 && is_file($filePath)) {
                @unlink($filePath);
            }
            $db->prepare('DELETE FROM downloads WHERE id = ?')->execute([$id]);
            setFlashMessage('success', 'Download deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

$filterCategory = trim((string)($_GET['category'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$where = [];
$params = [];
if (!$isAdmin) {
    $where[] = "LOWER(status) = 'active' AND LOWER(visibility) = 'public'";
}
if ($filterCategory !== '') {
    $where[] = 'category = ?';
    $params[] = $filterCategory;
}
if ($search !== '') {
    $where[] = '(title LIKE ? OR description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $db->prepare("SELECT * FROM downloads $whereSql ORDER BY uploaded_at DESC, id DESC");
$stmt->execute($params);
$downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $db->query("SELECT DISTINCT category FROM downloads WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$defaultCategories = ['Notice', 'Admission', 'Exam', 'Fee', 'Academic', 'Form', 'Policy', 'General'];
$categories = array_values(array_unique(array_merge($defaultCategories, $categories ?: [])));

$page_title = 'Downloads / Notices';
include '../../includes/header.php';
?>

<div class="container-fluid downloads-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-download me-2" style="color:var(--teal);"></i>Downloads / Notices</h2>
            <div class="text-muted">Category-wise notices, forms, and downloadable files.</div>
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fas fa-cloud-upload-alt me-1"></i>Upload File</button>
        <?php endif; ?>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" class="form-control" value="<?= dl_h($search) ?>" placeholder="Title or description">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?><option value="<?= dl_h($category) ?>" <?= $filterCategory === $category ? 'selected' : '' ?>><?= dl_h($category) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Files</h5>
            <span class="badge bg-light text-dark border"><?= count($downloads) ?> records</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>File</th><th>Category</th><th>Uploaded</th><th>Status</th><th>Downloads</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        <?php if (!$downloads): ?><tr><td colspan="6" class="text-center text-muted py-4">No downloads found.</td></tr><?php endif; ?>
                        <?php foreach ($downloads as $download): ?>
                            <?php [$icon, $iconClass] = dl_icon((string)$download['file_type']); ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="file-icon <?= dl_h($iconClass) ?>"><i class="fas <?= dl_h($icon) ?>"></i></div>
                                        <div>
                                            <div class="fw-bold"><?= dl_h($download['title']) ?></div>
                                            <div class="small text-muted"><?= dl_h($download['description']) ?></div>
                                            <div class="small text-muted"><?= dl_h(strtoupper((string)$download['file_type'])) ?> - <?= dl_h(dl_format_bytes(dl_file_size_value($download['file_size']))) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= dl_h($download['category'] ?: 'General') ?></span></td>
                                <td><?= dl_h(date('d M Y', strtotime($download['uploaded_at'] ?: $download['created_at']))) ?></td>
                                <td>
                                    <span class="badge <?= strtolower((string)$download['status']) === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= dl_h(ucfirst((string)$download['status'])) ?></span>
                                    <span class="badge <?= strtolower((string)$download['visibility']) === 'public' ? 'bg-primary' : 'bg-warning text-dark' ?>"><?= dl_h(ucfirst((string)$download['visibility'])) ?></span>
                                </td>
                                <td><?= (int)$download['download_count'] ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-sm btn-success" href="index.php?download_id=<?= (int)$download['id'] ?>"><i class="fas fa-download"></i></a>
                                        <?php if ($isAdmin): ?>
                                            <form method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$download['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Toggle active/inactive"><i class="fas fa-toggle-on"></i></button></form>
                                            <form method="POST" onsubmit="return confirm('Delete this file permanently?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$download['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="upload">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Upload Download / Notice</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-8"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Category *</label><input list="downloadCategories" name="category" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="col-md-4"><label class="form-label">Visibility</label><select name="visibility" class="form-select"><option value="public">Public</option><option value="private">Private</option></select></div>
                <div class="col-md-4"><label class="form-label">File *</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" required></div>
                <div class="col-12"><div class="form-text">Allowed: PDF, DOC, DOCX, JPG, PNG. Maximum size: 10 MB.</div></div>
            </div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button></div>
        </form>
    </div>
</div>
<datalist id="downloadCategories">
    <?php foreach ($categories as $category): ?><option value="<?= dl_h($category) ?>"></option><?php endforeach; ?>
</datalist>
<?php endif; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    .file-icon { width:42px; height:42px; display:grid; place-items:center; border-radius:8px; background:#f8fafc; font-size:1.4rem; flex:0 0 auto; }
</style>

<?php include '../../includes/footer.php'; ?>
