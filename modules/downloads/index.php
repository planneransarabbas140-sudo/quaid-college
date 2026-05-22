<?php
/**
 * File: modules/downloads/index.php
 * Description: Complete Downloads Center with Admin and Student views.
 */

require_once '../../config/db.php';

// Session Check
if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$userRole = getUserRole();
$isManager = ($userRole === 'admin' || $userRole === 'teacher');

$message = '';
$messageType = '';

// Handle File Download (Increment Counter)
if (isset($_GET['download_id'])) {
    $id = (int)$_GET['download_id'];
    $stmt = $db->prepare("SELECT file_path, file_name FROM downloads WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $file = $stmt->fetch();
    
    if ($file && file_exists('../../' . $file['file_path'])) {
        // Increment counter
        $db->prepare("UPDATE downloads SET download_count = download_count + 1 WHERE id = :id")->execute([':id' => $id]);
        
        // Serve file
        $filePath = '../../' . $file['file_path'];
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        $message = "File not found.";
        $messageType = "danger";
    }
}

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isManager) {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'upload') {
                $title = sanitizeInput($_POST['title']);
                $description = sanitizeInput($_POST['description']);
                $subject = sanitizeInput($_POST['subject']);
                $class = sanitizeInput($_POST['class']);
                $section = sanitizeInput($_POST['section']);
                $campus = sanitizeInput($_POST['campus']);
                
                if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
                    $file = $_FILES['file'];
                    $allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    $uniqueName = saveUploadedFile(
                        $file,
                        __DIR__ . '/../../uploads/downloads/',
                        'DL',
                        $allowedTypes,
                        10 * 1024 * 1024
                    );
                    $uploadPath = 'uploads/downloads/' . $uniqueName;

                    $stmt = $db->prepare("INSERT INTO downloads (title, description, subject, class, section, campus, file_name, file_path, file_type, file_size, uploaded_by) 
                                          VALUES (:title, :description, :subject, :class, :section, :campus, :file_name, :file_path, :file_type, :file_size, :uploaded_by)");

                    $fileSize = round($file['size'] / 1024, 2) . ' KB';
                    if ($file['size'] > 1024 * 1024) {
                        $fileSize = round($file['size'] / (1024 * 1024), 2) . ' MB';
                    }

                    $stmt->execute([
                        ':title' => $title,
                        ':description' => $description,
                        ':subject' => $subject,
                        ':class' => $class,
                        ':section' => $section,
                        ':campus' => $campus,
                        ':file_name' => $file['name'],
                        ':file_path' => $uploadPath,
                        ':file_type' => $fileExt,
                        ':file_size' => $fileSize,
                        ':uploaded_by' => $_SESSION['username'] ?? 'Admin'
                    ]);
                    $message = "File uploaded successfully!";
                    $messageType = "success";
                } else {
                    throw new Exception("Please select a file to upload.");
                }
            } elseif ($_POST['action'] === 'edit') {
                $stmt = $db->prepare("UPDATE downloads SET title = :title, description = :description, subject = :subject, class = :class, section = :section, campus = :campus WHERE id = :id");
                $stmt->execute([
                    ':title' => sanitizeInput($_POST['title']),
                    ':description' => sanitizeInput($_POST['description']),
                    ':subject' => sanitizeInput($_POST['subject']),
                    ':class' => sanitizeInput($_POST['class']),
                    ':section' => sanitizeInput($_POST['section']),
                    ':campus' => sanitizeInput($_POST['campus']),
                    ':id' => $_POST['id']
                ]);
                $message = "Download details updated successfully!";
                $messageType = "success";
            } elseif ($_POST['action'] === 'delete') {
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("SELECT file_path FROM downloads WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $file = $stmt->fetch();
                
                if ($file) {
                    if (file_exists('../../' . $file['file_path'])) {
                        unlink('../../' . $file['file_path']);
                    }
                    $db->prepare("DELETE FROM downloads WHERE id = :id")->execute([':id' => $id]);
                    $message = "File deleted successfully!";
                    $messageType = "success";
                }
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Filter and Search Logic
$search = $_GET['search'] ?? '';
$filterClass = $_GET['class'] ?? '';
$filterSection = $_GET['section'] ?? '';
$filterSubject = $_GET['subject'] ?? '';

$query = "SELECT * FROM downloads WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (title LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($filterClass) {
    $query .= " AND class = :class";
    $params[':class'] = $filterClass;
}
if ($filterSection) {
    $query .= " AND section = :section";
    $params[':section'] = $filterSection;
}
if ($filterSubject) {
    $query .= " AND subject = :subject";
    $params[':subject'] = $filterSubject;
}

$query .= " ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$downloads = $stmt->fetchAll();

// Constants
$programs = [
    'Intermediate' => ['FSc Pre-Medical', 'FSc Pre-Engineering', 'ICS', 'I.Com', 'FA', 'Taleem-ul-Islam'],
    'Degree' => ['ADP Arts', 'ADP Science', 'BSCS', 'BS IT', 'BS Zoology', 'BS Mathematics', 'BS Urdu', 'BS Chemistry'],
    'NAVTTC' => ['CCA', 'Web Development', 'Graphic Designing', 'Digital Marketing', 'UX/UI Design', 'AI', 'Vibe Coding']
];
$sections = ['A', 'B', 'C', 'D', 'Morning', 'Evening', 'Weekend', 'Batch 1', 'Batch 2', 'Batch 3', 'Batch 4'];
$campuses = ['Rajanpur', 'Fazilpur', 'Kot Mithan'];

// Helper for file icons
function getFileIcon($type) {
    switch (strtolower($type)) {
        case 'pdf': return ['icon' => 'fa-file-pdf', 'color' => '#e74c3c'];
        case 'doc':
        case 'docx': return ['icon' => 'fa-file-word', 'color' => '#3498db'];
        case 'ppt':
        case 'pptx': return ['icon' => 'fa-file-powerpoint', 'color' => '#e67e22'];
        case 'zip': return ['icon' => 'fa-file-archive', 'color' => '#f1c40f'];
        case 'jpg':
        case 'jpeg':
        case 'png': return ['icon' => 'fa-file-image', 'color' => '#2ecc71'];
        default: return ['icon' => 'fa-file', 'color' => '#95a5a6'];
    }
}

$page_title = "Downloads Center";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title text-navy mb-0">Downloads Center</h2>
        <?php if ($isManager): ?>
            <button class="btn btn-teal text-white" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-cloud-upload-alt me-2"></i>Upload Study Material
            </button>
        <?php endif; ?>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search & Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="class" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($programs as $cat => $list): ?>
                            <optgroup label="<?php echo $cat; ?>">
                                <?php foreach ($list as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo $filterClass === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="section" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $filterSection === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" name="subject" class="form-control" placeholder="Subject..." value="<?php echo htmlspecialchars($filterSubject); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-navy w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Downloads List -->
    <div class="row g-4">
        <?php if (empty($downloads)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No files found matching your criteria.</h4>
            </div>
        <?php else: ?>
            <?php foreach ($downloads as $dl): 
                $fileInfo = getFileIcon($dl['file_type']);
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card h-100 shadow-sm border-0 download-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <i class="fas <?php echo $fileInfo['icon']; ?> fa-3x" style="color: <?php echo $fileInfo['color']; ?>"></i>
                                <span class="badge bg-light text-muted border"><?php echo strtoupper($dl['file_type']); ?></span>
                            </div>
                            <h5 class="card-title text-navy mb-1 text-truncate" title="<?php echo htmlspecialchars($dl['title']); ?>">
                                <?php echo htmlspecialchars($dl['title']); ?>
                            </h5>
                            <p class="card-text text-muted small mb-3 line-clamp-2" style="height: 40px;">
                                <?php echo htmlspecialchars($dl['description']); ?>
                            </p>
                            
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-1 small text-muted">
                                    <i class="fas fa-book-open me-2 text-teal"></i>
                                    <span><?php echo htmlspecialchars($dl['subject']); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-1 small text-muted">
                                    <i class="fas fa-graduation-cap me-2 text-teal"></i>
                                    <span><?php echo htmlspecialchars($dl['class'] . ' (' . $dl['section'] . ')'); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-1 small text-muted">
                                    <i class="fas fa-hdd me-2 text-teal"></i>
                                    <span><?php echo $dl['file_size']; ?></span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                                <small class="text-muted"><?php echo date('d M Y', strtotime($dl['created_at'])); ?></small>
                                <div class="text-teal small">
                                    <i class="fas fa-download me-1"></i><?php echo $dl['download_count']; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 p-3 pt-0">
                            <div class="d-grid gap-2">
                                <a href="?download_id=<?php echo $dl['id']; ?>" class="btn btn-teal text-white">
                                    <i class="fas fa-download me-2"></i>Download
                                </a>
                                <?php if ($isManager): ?>
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-sm btn-outline-warning w-100" onclick="editDownload(<?php echo htmlspecialchars(json_encode($dl)); ?>)">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                        <form method="POST" class="w-100" onsubmit="return confirm('Delete this file permanently?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $dl['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title">Upload Study Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Physics Chapter 1 Notes">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief details about the material..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject *</label>
                            <input type="text" name="subject" class="form-control" required placeholder="e.g. Physics">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class *</label>
                            <select name="class" class="form-select" required>
                                <?php foreach ($programs as $cat => $list): ?>
                                    <optgroup label="<?php echo $cat; ?>">
                                        <?php foreach ($list as $p): ?>
                                            <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Section *</label>
                            <select name="section" class="form-select" required>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campus *</label>
                            <select name="campus" class="form-select" required>
                                <?php foreach ($campuses as $c): ?>
                                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">File * (Max 10MB)</label>
                            <input type="file" name="file" class="form-control" required>
                            <div class="form-text">Allowed: PDF, DOC, PPT, JPG, PNG, ZIP</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-teal text-white">Upload Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title">Edit Download Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject *</label>
                            <input type="text" name="subject" id="edit_subject" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class *</label>
                            <select name="class" id="edit_class" class="form-select" required>
                                <?php foreach ($programs as $cat => $list): ?>
                                    <optgroup label="<?php echo $cat; ?>">
                                        <?php foreach ($list as $p): ?>
                                            <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Section *</label>
                            <select name="section" id="edit_section" class="form-select" required>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campus *</label>
                            <select name="campus" id="edit_campus" class="form-select" required>
                                <?php foreach ($campuses as $c): ?>
                                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy text-white">Update Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .text-navy { color: var(--navy); }
    .bg-navy { background-color: var(--navy) !important; }
    .bg-teal { background-color: var(--teal) !important; }
    .btn-teal { background-color: var(--teal); border-color: var(--teal); }
    .btn-teal:hover { background-color: var(--teal-dark); border-color: var(--teal-dark); color: white; }
    .btn-navy { background-color: var(--navy); border-color: var(--navy); color: white; }
    .btn-navy:hover { background-color: var(--navy-mid); border-color: var(--navy-mid); color: white; }
    
    .download-card { transition: all 0.3s; border-radius: 12px; }
    .download-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; }
    
    .text-teal { color: var(--teal); }
    
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<script>
function editDownload(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_title').value = data.title;
    document.getElementById('edit_description').value = data.description;
    document.getElementById('edit_subject').value = data.subject;
    document.getElementById('edit_class').value = data.class;
    document.getElementById('edit_section').value = data.section;
    document.getElementById('edit_campus').value = data.campus;
    
    var editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
