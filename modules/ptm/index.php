<?php
// File: modules/ptm/index.php
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

function ptm_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function ptm_valid_date($date): bool {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function ptm_valid_time($time): bool {
    return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', (string)$time);
}

function ptm_teacher_id(PDO $db): ?int {
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

function ptm_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS ptm_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(220) NOT NULL,
        class_id VARCHAR(100) DEFAULT NULL,
        section_id VARCHAR(50) DEFAULT NULL,
        teacher_id INT DEFAULT NULL,
        meeting_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        venue VARCHAR(180) DEFAULT NULL,
        agenda TEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ptm_class (class_id, section_id),
        INDEX idx_ptm_teacher (teacher_id),
        INDEX idx_ptm_date (meeting_date),
        INDEX idx_ptm_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'title' => "ALTER TABLE ptm_meetings ADD COLUMN title VARCHAR(220) DEFAULT NULL",
        'class_id' => "ALTER TABLE ptm_meetings ADD COLUMN class_id VARCHAR(100) DEFAULT NULL",
        'section_id' => "ALTER TABLE ptm_meetings ADD COLUMN section_id VARCHAR(50) DEFAULT NULL",
        'teacher_id' => "ALTER TABLE ptm_meetings ADD COLUMN teacher_id INT DEFAULT NULL",
        'meeting_date' => "ALTER TABLE ptm_meetings ADD COLUMN meeting_date DATE DEFAULT NULL",
        'start_time' => "ALTER TABLE ptm_meetings ADD COLUMN start_time TIME DEFAULT NULL",
        'end_time' => "ALTER TABLE ptm_meetings ADD COLUMN end_time TIME DEFAULT NULL",
        'venue' => "ALTER TABLE ptm_meetings ADD COLUMN venue VARCHAR(180) DEFAULT NULL",
        'agenda' => "ALTER TABLE ptm_meetings ADD COLUMN agenda TEXT DEFAULT NULL",
        'status' => "ALTER TABLE ptm_meetings ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'scheduled'",
        'created_by' => "ALTER TABLE ptm_meetings ADD COLUMN created_by INT DEFAULT NULL",
        'created_at' => "ALTER TABLE ptm_meetings ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ALTER TABLE ptm_meetings ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'ptm_meetings', $column)) {
            $db->exec($sql);
        }
    }

    if (columnExists($db, 'ptm_meetings', 'class_name')) {
        $db->exec("UPDATE ptm_meetings SET class_id = class_name WHERE (class_id IS NULL OR class_id = '') AND class_name IS NOT NULL");
    }
    if (columnExists($db, 'ptm_meetings', 'remarks')) {
        $db->exec("UPDATE ptm_meetings SET agenda = remarks WHERE (agenda IS NULL OR agenda = '') AND remarks IS NOT NULL");
    }
}

ptm_ensure_schema($db);
$currentTeacherId = ptm_teacher_id($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        if (!$isAdmin) {
            throw new Exception('Only admin can manage PTM meetings.');
        }

        $action = $_POST['action'] ?? '';
        if (in_array($action, ['add', 'edit'], true)) {
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                'title' => sanitizeInput($_POST['title'] ?? ''),
                'class_id' => sanitizeInput($_POST['class_id'] ?? ''),
                'section_id' => sanitizeInput($_POST['section_id'] ?? ''),
                'teacher_id' => (int)($_POST['teacher_id'] ?? 0) ?: null,
                'meeting_date' => sanitizeInput($_POST['meeting_date'] ?? ''),
                'start_time' => sanitizeInput($_POST['start_time'] ?? ''),
                'end_time' => sanitizeInput($_POST['end_time'] ?? ''),
                'venue' => sanitizeInput($_POST['venue'] ?? ''),
                'agenda' => trim((string)($_POST['agenda'] ?? '')),
                'status' => in_array($_POST['status'] ?? 'scheduled', ['scheduled', 'completed', 'cancelled'], true) ? $_POST['status'] : 'scheduled',
            ];

            if ($data['title'] === '' || $data['class_id'] === '' || !ptm_valid_date($data['meeting_date'])) {
                throw new Exception('Please fill title, class, and a valid meeting date.');
            }
            if (!ptm_valid_time($data['start_time']) || !ptm_valid_time($data['end_time']) || $data['start_time'] >= $data['end_time']) {
                throw new Exception('Please enter a valid PTM time range.');
            }

            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO ptm_meetings (title, class_id, section_id, teacher_id, meeting_date, start_time, end_time, venue, agenda, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$data['title'], $data['class_id'], $data['section_id'], $data['teacher_id'], $data['meeting_date'], $data['start_time'], $data['end_time'], $data['venue'], $data['agenda'], $data['status'], $userId]);
                setFlashMessage('success', 'PTM meeting scheduled successfully.');
            } else {
                if ($id <= 0) {
                    throw new Exception('Invalid PTM meeting selected.');
                }
                $stmt = $db->prepare("UPDATE ptm_meetings SET title = ?, class_id = ?, section_id = ?, teacher_id = ?, meeting_date = ?, start_time = ?, end_time = ?, venue = ?, agenda = ?, status = ? WHERE id = ?");
                $stmt->execute([$data['title'], $data['class_id'], $data['section_id'], $data['teacher_id'], $data['meeting_date'], $data['start_time'], $data['end_time'], $data['venue'], $data['agenda'], $data['status'], $id]);
                setFlashMessage('success', 'PTM meeting updated successfully.');
            }
            redirect('index.php');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM ptm_meetings WHERE id = ?')->execute([$id]);
            setFlashMessage('success', 'PTM meeting deleted successfully.');
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
$teachers = getTeachers($db);

$filterClass = trim((string)($_GET['class_id'] ?? ''));
$filterSection = trim((string)($_GET['section_id'] ?? ''));
$filterTeacher = (int)($_GET['teacher_id'] ?? 0);
$filterDate = ptm_valid_date($_GET['date'] ?? '') ? $_GET['date'] : '';

$where = [];
$params = [];
if ($filterClass !== '') {
    $where[] = 'p.class_id = ?';
    $params[] = $filterClass;
}
if ($filterSection !== '') {
    $where[] = 'p.section_id = ?';
    $params[] = $filterSection;
}
if ($filterTeacher > 0) {
    $where[] = 'p.teacher_id = ?';
    $params[] = $filterTeacher;
}
if ($filterDate !== '') {
    $where[] = 'p.meeting_date = ?';
    $params[] = $filterDate;
}
if ($isTeacher && !$isAdmin) {
    $where[] = 'p.teacher_id = ?';
    $params[] = $currentTeacherId ?: 0;
}
if ($role === 'student' && tableExists($db, 'students') && columnExists($db, 'students', 'user_id')) {
    $student = sharedFetchOne($db, 'SELECT class, section FROM students WHERE user_id = ? LIMIT 1', [$userId]);
    if ($student) {
        $where[] = 'p.class_id = ?';
        $params[] = $student['class'];
        if (!empty($student['section'])) {
            $where[] = "(p.section_id = ? OR p.section_id = '')";
            $params[] = $student['section'];
        }
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $db->prepare("
    SELECT p.*, COALESCE(s.full_name, 'All Teachers') AS teacher_name
    FROM ptm_meetings p
    LEFT JOIN staff s ON s.id = p.teacher_id
    $whereSql
    ORDER BY p.meeting_date DESC, p.start_time ASC
");
$stmt->execute($params);
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$upcoming = array_values(array_filter($meetings, static fn($m) => strtolower((string)$m['status']) === 'scheduled' && (string)$m['meeting_date'] >= $today));
$completed = array_values(array_filter($meetings, static fn($m) => strtolower((string)$m['status']) === 'completed' || (string)$m['meeting_date'] < $today));

$page_title = 'Parent Teacher Meetings';
include '../../includes/header.php';
?>

<div class="container-fluid ptm-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-users me-2" style="color:var(--teal);"></i>Parent Teacher Meetings</h2>
            <div class="text-muted">Schedule PTM notices and review meetings by class, section, teacher, and date.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print Notice</button>
            <?php if ($isAdmin): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ptmModal"><i class="fas fa-plus me-1"></i>Schedule PTM</button><?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total</div><h3 class="fw-bold mb-0"><?= count($meetings) ?></h3></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Upcoming</div><h3 class="fw-bold text-success mb-0"><?= count($upcoming) ?></h3></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Completed / Past</div><h3 class="fw-bold text-secondary mb-0"><?= count($completed) ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label small fw-bold">Class</label><select name="class_id" class="form-select"><option value="">All Classes</option><?php foreach ($classes as $class): ?><option value="<?= ptm_h($class['id']) ?>" <?= $filterClass === (string)$class['id'] ? 'selected' : '' ?>><?= ptm_h($class['class_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label small fw-bold">Section</label><select name="section_id" class="form-select"><option value="">All Sections</option><?php foreach ($sections as $section): ?><option value="<?= ptm_h($section['id']) ?>" <?= $filterSection === (string)$section['id'] ? 'selected' : '' ?>><?= ptm_h($section['section_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small fw-bold">Teacher</label><select name="teacher_id" class="form-select" <?= $isTeacher && !$isAdmin ? 'disabled' : '' ?>><option value="">All Teachers</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>" <?= $filterTeacher === (int)$teacher['id'] ? 'selected' : '' ?>><?= ptm_h($teacher['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label small fw-bold">Date</label><input type="date" name="date" class="form-control" value="<?= ptm_h($filterDate) ?>"></div>
                <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>

    <?php foreach ([['Upcoming PTM Notices', $upcoming], ['Completed PTM Meetings', $completed]] as [$heading, $rows]): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h5 class="fw-bold mb-0"><?= ptm_h($heading) ?></h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Meeting</th><th>Class</th><th>Teacher</th><th>Date/Time</th><th>Venue</th><th>Status</th><?php if ($isAdmin): ?><th class="text-end no-print">Actions</th><?php endif; ?></tr></thead>
                        <tbody>
                            <?php if (!$rows): ?><tr><td colspan="<?= $isAdmin ? 7 : 6 ?>" class="text-center text-muted py-4">No PTM records found.</td></tr><?php endif; ?>
                            <?php foreach ($rows as $meeting): ?>
                                <tr>
                                    <td><div class="fw-bold"><?= ptm_h($meeting['title']) ?></div><div class="small text-muted"><?= nl2br(ptm_h($meeting['agenda'])) ?></div></td>
                                    <td><?= ptm_h($meeting['class_id']) ?><div class="small text-muted"><?= ptm_h($meeting['section_id'] ?: 'All Sections') ?></div></td>
                                    <td><?= ptm_h($meeting['teacher_name']) ?></td>
                                    <td><?= ptm_h(date('d M Y', strtotime($meeting['meeting_date']))) ?><div class="small text-muted"><?= ptm_h(substr((string)$meeting['start_time'], 0, 5) . ' - ' . substr((string)$meeting['end_time'], 0, 5)) ?></div></td>
                                    <td><?= ptm_h($meeting['venue'] ?: '-') ?></td>
                                    <td><span class="badge <?= strtolower((string)$meeting['status']) === 'completed' ? 'bg-secondary' : (strtolower((string)$meeting['status']) === 'cancelled' ? 'bg-danger' : 'bg-success') ?>"><?= ptm_h(ucfirst((string)$meeting['status'])) ?></span></td>
                                    <?php if ($isAdmin): ?>
                                        <td class="text-end no-print">
                                            <button class="btn btn-sm btn-outline-primary edit-ptm" data-bs-toggle="modal" data-bs-target="#editPtmModal" data-meeting='<?= ptm_h(json_encode($meeting)) ?>'><i class="fas fa-pen"></i></button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this PTM meeting?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($isAdmin): ?>
<?php
$renderForm = function (string $prefix) use ($classes, $sections, $teachers) {
?>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Title *</label><input type="text" name="title" id="<?= $prefix ?>title" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Date *</label><input type="date" name="meeting_date" id="<?= $prefix ?>meeting_date" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Class *</label><select name="class_id" id="<?= $prefix ?>class_id" class="form-select" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= ptm_h($class['id']) ?>"><?= ptm_h($class['class_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Section</label><select name="section_id" id="<?= $prefix ?>section_id" class="form-select"><option value="">All sections</option><?php foreach ($sections as $section): ?><option value="<?= ptm_h($section['id']) ?>"><?= ptm_h($section['section_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Teacher</label><select name="teacher_id" id="<?= $prefix ?>teacher_id" class="form-select"><option value="">All Teachers</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>"><?= ptm_h($teacher['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Start Time *</label><input type="time" name="start_time" id="<?= $prefix ?>start_time" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">End Time *</label><input type="time" name="end_time" id="<?= $prefix ?>end_time" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" id="<?= $prefix ?>status" class="form-select"><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
        <div class="col-12"><label class="form-label">Venue</label><input type="text" name="venue" id="<?= $prefix ?>venue" class="form-control"></div>
        <div class="col-12"><label class="form-label">Agenda</label><textarea name="agenda" id="<?= $prefix ?>agenda" class="form-control" rows="4"></textarea></div>
    </div>
<?php
};
?>
<div class="modal fade no-print" id="ptmModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content border-0 shadow" method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="add"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Schedule PTM</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php $renderForm('add_'); ?></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save PTM</button></div></form></div>
</div>
<div class="modal fade no-print" id="editPtmModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content border-0 shadow" method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit_id"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit PTM</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php $renderForm('edit_'); ?></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update PTM</button></div></form></div>
</div>
<?php endif; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop, .btn, form { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; break-inside:avoid; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-ptm').forEach(function (button) {
        button.addEventListener('click', function () {
            const meeting = JSON.parse(button.dataset.meeting || '{}');
            ['id', 'title', 'class_id', 'section_id', 'teacher_id', 'meeting_date', 'start_time', 'end_time', 'venue', 'agenda', 'status'].forEach(function (field) {
                const el = document.getElementById('edit_' + field);
                if (el) el.value = (field === 'start_time' || field === 'end_time') && meeting[field] ? meeting[field].slice(0, 5) : (meeting[field] || '');
            });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
