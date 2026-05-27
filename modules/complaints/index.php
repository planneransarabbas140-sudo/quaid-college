<?php
// File: modules/complaints/index.php
require_once '../../config/db.php';
require_once '../../includes/shared_functions.php';

if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$db = (new Database())->getConnection();
$role = getUserRole();
$userId = getUserId();
$isAdmin = in_array($role, ['admin', 'owner'], true);
$statusOptions = ['pending', 'under_review', 'resolved', 'rejected'];
$priorityOptions = ['low', 'medium', 'high', 'urgent'];

function comp_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function comp_status_key($status): string {
    $status = strtolower(str_replace([' ', '-'], '_', (string)$status));
    if ($status === 'open') return 'pending';
    if ($status === 'in_progress' || $status === 'progress') return 'under_review';
    if ($status === 'resolved') return 'resolved';
    if ($status === 'rejected') return 'rejected';
    return 'pending';
}

function comp_priority_key($priority): string {
    $priority = strtolower((string)$priority);
    return in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
}

function comp_badge(string $value): string {
    $classes = [
        'pending' => 'bg-warning text-dark',
        'under_review' => 'bg-primary',
        'resolved' => 'bg-success',
        'rejected' => 'bg-danger',
        'low' => 'bg-info text-dark',
        'medium' => 'bg-warning text-dark',
        'high' => 'bg-danger',
        'urgent' => 'bg-dark',
    ];
    return '<span class="badge ' . ($classes[$value] ?? 'bg-secondary') . '">' . comp_h(ucwords(str_replace('_', ' ', $value))) . '</span>';
}

function comp_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complaint_number VARCHAR(100) DEFAULT NULL,
        complainant_name VARCHAR(150) NOT NULL,
        complainant_type VARCHAR(50) NOT NULL DEFAULT 'student',
        contact_no VARCHAR(50) DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        subject VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        priority VARCHAR(20) NOT NULL DEFAULT 'medium',
        assigned_to INT DEFAULT NULL,
        response TEXT DEFAULT NULL,
        submitted_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME DEFAULT NULL,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_complaint_number (complaint_number),
        INDEX idx_complaints_status (status),
        INDEX idx_complaints_priority (priority),
        INDEX idx_complaints_category (category),
        INDEX idx_complaints_assigned_to (assigned_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'complaint_number' => "ALTER TABLE complaints ADD COLUMN complaint_number VARCHAR(100) DEFAULT NULL",
        'complainant_name' => "ALTER TABLE complaints ADD COLUMN complainant_name VARCHAR(150) DEFAULT NULL",
        'complainant_type' => "ALTER TABLE complaints ADD COLUMN complainant_type VARCHAR(50) NOT NULL DEFAULT 'student'",
        'contact_no' => "ALTER TABLE complaints ADD COLUMN contact_no VARCHAR(50) DEFAULT NULL",
        'category' => "ALTER TABLE complaints ADD COLUMN category VARCHAR(100) DEFAULT NULL",
        'subject' => "ALTER TABLE complaints ADD COLUMN subject VARCHAR(255) DEFAULT NULL",
        'description' => "ALTER TABLE complaints ADD COLUMN description TEXT DEFAULT NULL",
        'status' => "ALTER TABLE complaints ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'",
        'priority' => "ALTER TABLE complaints ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'medium'",
        'assigned_to' => "ALTER TABLE complaints ADD COLUMN assigned_to INT DEFAULT NULL",
        'response' => "ALTER TABLE complaints ADD COLUMN response TEXT DEFAULT NULL",
        'submitted_by' => "ALTER TABLE complaints ADD COLUMN submitted_by INT DEFAULT NULL",
        'created_at' => "ALTER TABLE complaints ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'resolved_at' => "ALTER TABLE complaints ADD COLUMN resolved_at DATETIME DEFAULT NULL",
        'updated_at' => "ALTER TABLE complaints ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'complaints', $column)) {
            $db->exec($sql);
        }
    }

    if (columnExists($db, 'complaints', 'student_name')) {
        $db->exec("UPDATE complaints SET complainant_name = student_name WHERE (complainant_name IS NULL OR complainant_name = '') AND student_name IS NOT NULL");
    }
    if (columnExists($db, 'complaints', 'complaint_type')) {
        $db->exec("UPDATE complaints SET category = complaint_type WHERE (category IS NULL OR category = '') AND complaint_type IS NOT NULL");
    }
    $db->exec("UPDATE complaints SET complaint_number = CONCAT('CMP-', DATE_FORMAT(COALESCE(created_at, NOW()), '%Y%m%d'), '-', LPAD(id, 4, '0')) WHERE complaint_number IS NULL OR complaint_number = ''");
    $db->exec("UPDATE complaints SET status = CASE LOWER(REPLACE(status, ' ', '_')) WHEN 'resolved' THEN 'resolved' WHEN 'rejected' THEN 'rejected' WHEN 'under_review' THEN 'under_review' WHEN 'in_progress' THEN 'under_review' ELSE 'pending' END");
    $db->exec("UPDATE complaints SET priority = CASE LOWER(priority) WHEN 'low' THEN 'low' WHEN 'high' THEN 'high' WHEN 'urgent' THEN 'urgent' ELSE 'medium' END");
}

comp_ensure_schema($db);
$staffList = getAllStaff($db);
$complainantTypes = ['student', 'parent', 'staff', 'teacher', 'other'];
$categories = ['Complaint', 'Suggestion', 'Academic', 'Fee', 'Transport', 'Library', 'Discipline', 'Facilities', 'Other'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';

        if ($action === 'submit_complaint') {
            $name = sanitizeInput($_POST['complainant_name'] ?? '');
            $type = in_array($_POST['complainant_type'] ?? '', $complainantTypes, true) ? $_POST['complainant_type'] : 'other';
            $contact = sanitizeInput($_POST['contact_no'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $priority = $isAdmin ? comp_priority_key($_POST['priority'] ?? 'medium') : 'medium';
            $assignedTo = $isAdmin ? ((int)($_POST['assigned_to'] ?? 0) ?: null) : null;

            if ($name === '' || $category === '' || $subject === '' || $description === '') {
                throw new Exception('Please fill name, category, subject, and description.');
            }
            $number = 'CMP-' . date('Ymd') . '-' . random_int(1000, 9999);
            $stmt = $db->prepare("INSERT INTO complaints (complaint_number, complainant_name, complainant_type, contact_no, category, subject, description, status, priority, assigned_to, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
            $stmt->execute([$number, $name, $type, $contact, $category, $subject, $description, $priority, $assignedTo, $userId]);
            setFlashMessage('success', 'Complaint/suggestion submitted successfully. Ticket: ' . $number);
            redirect('index.php');
        }

        if ($action === 'admin_update') {
            if (!$isAdmin) {
                throw new Exception('Only admin can update complaints.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $status = comp_status_key($_POST['status'] ?? 'pending');
            $priority = comp_priority_key($_POST['priority'] ?? 'medium');
            $assignedTo = (int)($_POST['assigned_to'] ?? 0) ?: null;
            $response = trim((string)($_POST['response'] ?? ''));
            $resolvedAtSql = in_array($status, ['resolved', 'rejected'], true) ? 'COALESCE(resolved_at, NOW())' : 'NULL';
            $stmt = $db->prepare("UPDATE complaints SET status = ?, priority = ?, assigned_to = ?, response = ?, resolved_at = $resolvedAtSql WHERE id = ?");
            $stmt->execute([$status, $priority, $assignedTo, $response, $id]);
            setFlashMessage('success', 'Complaint updated successfully.');
            redirect('index.php');
        }

        if ($action === 'delete') {
            if (!$isAdmin) {
                throw new Exception('Only admin can delete complaints.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM complaints WHERE id = ?')->execute([$id]);
            setFlashMessage('success', 'Complaint deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

$filterStatus = in_array($_GET['status'] ?? '', $statusOptions, true) ? $_GET['status'] : '';
$filterPriority = in_array($_GET['priority'] ?? '', $priorityOptions, true) ? $_GET['priority'] : '';
$filterCategory = trim((string)($_GET['category'] ?? ''));

$where = [];
$params = [];
if ($filterStatus !== '') {
    $where[] = 'c.status = ?';
    $params[] = $filterStatus;
}
if ($filterPriority !== '') {
    $where[] = 'c.priority = ?';
    $params[] = $filterPriority;
}
if ($filterCategory !== '') {
    $where[] = 'c.category = ?';
    $params[] = $filterCategory;
}
if (!$isAdmin) {
    $where[] = 'c.submitted_by = ?';
    $params[] = $userId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $db->prepare("
    SELECT c.*, COALESCE(s.full_name, 'Unassigned') AS assigned_name
    FROM complaints c
    LEFT JOIN staff s ON s.id = c.assigned_to
    $whereSql
    ORDER BY FIELD(c.status, 'pending','under_review','resolved','rejected'), c.created_at DESC
");
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['total' => count($complaints), 'pending' => 0, 'under_review' => 0, 'resolved' => 0, 'rejected' => 0];
foreach ($complaints as $complaint) {
    $stats[comp_status_key($complaint['status'])]++;
}

$page_title = 'Complaints / Suggestions';
include '../../includes/header.php';
?>

<div class="container-fluid complaints-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-comment-dots me-2" style="color:var(--teal);"></i>Complaints / Suggestions</h2>
            <div class="text-muted">Submit, assign, review, resolve, and print complaint reports.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#complaintModal"><i class="fas fa-plus me-1"></i>Submit</button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Total</div><h3 class="fw-bold mb-0"><?= (int)$stats['total'] ?></h3></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Pending</div><h3 class="fw-bold text-warning mb-0"><?= (int)$stats['pending'] ?></h3></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Under Review</div><h3 class="fw-bold text-primary mb-0"><?= (int)$stats['under_review'] ?></h3></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Resolved</div><h3 class="fw-bold text-success mb-0"><?= (int)$stats['resolved'] ?></h3></div></div></div>
        <div class="col-md"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Rejected</div><h3 class="fw-bold text-danger mb-0"><?= (int)$stats['rejected'] ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label small fw-bold">Status</label><select name="status" class="form-select"><option value="">All Status</option><?php foreach ($statusOptions as $status): ?><option value="<?= comp_h($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= comp_h(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small fw-bold">Priority</label><select name="priority" class="form-select"><option value="">All Priority</option><?php foreach ($priorityOptions as $priority): ?><option value="<?= comp_h($priority) ?>" <?= $filterPriority === $priority ? 'selected' : '' ?>><?= comp_h(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label small fw-bold">Category</label><select name="category" class="form-select"><option value="">All Categories</option><?php foreach ($categories as $category): ?><option value="<?= comp_h($category) ?>" <?= $filterCategory === $category ? 'selected' : '' ?>><?= comp_h($category) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0">Complaint Report</h5><span class="badge bg-light text-dark border"><?= count($complaints) ?> records</span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>Ticket</th><th>Complainant</th><th>Category</th><th>Subject</th><th>Priority</th><th>Status</th><th>Assigned</th><th class="text-end no-print">Actions</th></tr></thead>
                    <tbody>
                        <?php if (!$complaints): ?><tr><td colspan="8" class="text-center text-muted py-4">No complaints found.</td></tr><?php endif; ?>
                        <?php foreach ($complaints as $complaint): ?>
                            <?php $status = comp_status_key($complaint['status']); $priority = comp_priority_key($complaint['priority']); ?>
                            <tr>
                                <td><div class="fw-bold"><?= comp_h($complaint['complaint_number']) ?></div><div class="small text-muted"><?= comp_h(date('d M Y', strtotime($complaint['created_at']))) ?></div></td>
                                <td><div><?= comp_h($complaint['complainant_name']) ?></div><div class="small text-muted"><?= comp_h(ucfirst((string)$complaint['complainant_type'])) ?> <?= $complaint['contact_no'] ? '- ' . comp_h($complaint['contact_no']) : '' ?></div></td>
                                <td><span class="badge bg-light text-dark border"><?= comp_h($complaint['category']) ?></span></td>
                                <td><div class="fw-bold"><?= comp_h($complaint['subject']) ?></div><div class="small text-muted"><?= nl2br(comp_h($complaint['description'])) ?></div><?php if (!empty($complaint['response'])): ?><div class="small mt-2"><strong>Response:</strong> <?= nl2br(comp_h($complaint['response'])) ?></div><?php endif; ?></td>
                                <td><?= comp_badge($priority) ?></td>
                                <td><?= comp_badge($status) ?><?php if ($complaint['resolved_at']): ?><div class="small text-muted"><?= comp_h(date('d M Y', strtotime($complaint['resolved_at']))) ?></div><?php endif; ?></td>
                                <td><?= comp_h($complaint['assigned_name']) ?></td>
                                <td class="text-end no-print">
                                    <?php if ($isAdmin): ?>
                                        <button class="btn btn-sm btn-outline-primary edit-complaint" data-bs-toggle="modal" data-bs-target="#adminUpdateModal" data-complaint='<?= comp_h(json_encode($complaint)) ?>'><i class="fas fa-pen"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this complaint?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$complaint['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                    <?php else: ?>
                                        <span class="text-muted small">Submitted</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="complaintModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="submit_complaint">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Submit Complaint / Suggestion</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label class="form-label">Complainant Name *</label><input type="text" name="complainant_name" class="form-control" value="<?= comp_h($_SESSION['full_name'] ?? '') ?>" required></div>
                <div class="col-md-3"><label class="form-label">Type *</label><select name="complainant_type" class="form-select"><?php foreach ($complainantTypes as $type): ?><option value="<?= comp_h($type) ?>" <?= $role === $type ? 'selected' : '' ?>><?= comp_h(ucfirst($type)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Contact No</label><input type="text" name="contact_no" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Category *</label><select name="category" class="form-select" required><?php foreach ($categories as $category): ?><option value="<?= comp_h($category) ?>"><?= comp_h($category) ?></option><?php endforeach; ?></select></div>
                <?php if ($isAdmin): ?>
                    <div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select"><?php foreach ($priorityOptions as $priority): ?><option value="<?= comp_h($priority) ?>"><?= comp_h(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Assign To</label><select name="assigned_to" class="form-select"><option value="">Unassigned</option><?php foreach ($staffList as $staff): ?><option value="<?= (int)$staff['id'] ?>"><?= comp_h($staff['full_name']) ?></option><?php endforeach; ?></select></div>
                <?php endif; ?>
                <div class="col-12"><label class="form-label">Subject *</label><input type="text" name="subject" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="5" required></textarea></div>
            </div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Submit</button></div>
        </form>
    </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal fade no-print" id="adminUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="admin_update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Update Complaint</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-4"><label class="form-label">Status</label><select name="status" id="edit_status" class="form-select"><?php foreach ($statusOptions as $status): ?><option value="<?= comp_h($status) ?>"><?= comp_h(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Priority</label><select name="priority" id="edit_priority" class="form-select"><?php foreach ($priorityOptions as $priority): ?><option value="<?= comp_h($priority) ?>"><?= comp_h(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Assign To</label><select name="assigned_to" id="edit_assigned_to" class="form-select"><option value="">Unassigned</option><?php foreach ($staffList as $staff): ?><option value="<?= (int)$staff['id'] ?>"><?= comp_h($staff['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Admin Response</label><textarea name="response" id="edit_response" class="form-control" rows="5"></textarea></div>
            </div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Update</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop, .btn, form { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-complaint').forEach(function (button) {
        button.addEventListener('click', function () {
            const complaint = JSON.parse(button.dataset.complaint || '{}');
            ['id', 'status', 'priority', 'assigned_to', 'response'].forEach(function (field) {
                const el = document.getElementById('edit_' + field);
                if (el) el.value = complaint[field] || '';
            });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
