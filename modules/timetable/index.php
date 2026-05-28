<?php
// File: modules/timetable/index.php
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
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function tt_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tt_valid_time($time): bool {
    $time = (string)$time;
    return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time);
}

function tt_teacher_id(PDO $db): ?int {
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

function tt_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS timetables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id VARCHAR(100) DEFAULT NULL,
        section_id VARCHAR(50) DEFAULT NULL,
        subject_id VARCHAR(120) DEFAULT NULL,
        teacher_id INT DEFAULT NULL,
        day_of_week VARCHAR(20) DEFAULT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        room_no VARCHAR(50) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        legacy_timetable_id INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tt_class (class_id, section_id),
        INDEX idx_tt_teacher (teacher_id),
        INDEX idx_tt_day_time (day_of_week, start_time, end_time),
        INDEX idx_tt_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'class_id' => "ALTER TABLE timetables ADD COLUMN class_id VARCHAR(100) DEFAULT NULL",
        'section_id' => "ALTER TABLE timetables ADD COLUMN section_id VARCHAR(50) DEFAULT NULL",
        'subject_id' => "ALTER TABLE timetables ADD COLUMN subject_id VARCHAR(120) DEFAULT NULL",
        'teacher_id' => "ALTER TABLE timetables ADD COLUMN teacher_id INT DEFAULT NULL",
        'day_of_week' => "ALTER TABLE timetables ADD COLUMN day_of_week VARCHAR(20) DEFAULT NULL",
        'start_time' => "ALTER TABLE timetables ADD COLUMN start_time TIME DEFAULT NULL",
        'end_time' => "ALTER TABLE timetables ADD COLUMN end_time TIME DEFAULT NULL",
        'room_no' => "ALTER TABLE timetables ADD COLUMN room_no VARCHAR(50) DEFAULT NULL",
        'status' => "ALTER TABLE timetables ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
        'legacy_timetable_id' => "ALTER TABLE timetables ADD COLUMN legacy_timetable_id INT DEFAULT NULL",
        'created_by' => "ALTER TABLE timetables ADD COLUMN created_by INT DEFAULT NULL",
        'updated_at' => "ALTER TABLE timetables ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'timetables', $column)) {
            $db->exec($sql);
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS timetable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class VARCHAR(100) DEFAULT NULL,
        section VARCHAR(50) DEFAULT NULL,
        day_of_week VARCHAR(20) DEFAULT NULL,
        day VARCHAR(20) DEFAULT NULL,
        period_number INT DEFAULT 1,
        subject VARCHAR(120) DEFAULT NULL,
        teacher_id INT DEFAULT NULL,
        teacher_name VARCHAR(180) DEFAULT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        room_number VARCHAR(50) DEFAULT NULL,
        room VARCHAR(50) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $legacyColumns = [
        'day' => "ALTER TABLE timetable ADD COLUMN day VARCHAR(20) DEFAULT NULL",
        'day_of_week' => "ALTER TABLE timetable ADD COLUMN day_of_week VARCHAR(20) DEFAULT NULL",
        'period_number' => "ALTER TABLE timetable ADD COLUMN period_number INT DEFAULT 1",
        'teacher_name' => "ALTER TABLE timetable ADD COLUMN teacher_name VARCHAR(180) DEFAULT NULL",
        'room' => "ALTER TABLE timetable ADD COLUMN room VARCHAR(50) DEFAULT NULL",
        'room_number' => "ALTER TABLE timetable ADD COLUMN room_number VARCHAR(50) DEFAULT NULL",
        'status' => "ALTER TABLE timetable ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
    ];
    foreach ($legacyColumns as $column => $sql) {
        if (!columnExists($db, 'timetable', $column)) {
            $db->exec($sql);
        }
    }

    $db->exec("INSERT INTO timetables (class_id, section_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no, status, legacy_timetable_id, created_by)
        SELECT t.class, t.section, t.subject, t.teacher_id, COALESCE(NULLIF(t.day_of_week, ''), t.day), t.start_time, t.end_time, COALESCE(t.room_number, t.room), COALESCE(t.status, 'active'), t.id, NULL
        FROM timetable t
        WHERE NOT EXISTS (SELECT 1 FROM timetables tt WHERE tt.legacy_timetable_id = t.id)");
}

function tt_teacher_name(PDO $db, ?int $teacherId): string {
    if (!$teacherId || !tableExists($db, 'staff')) {
        return '';
    }
    $stmt = $db->prepare('SELECT full_name FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([$teacherId]);
    return (string)($stmt->fetchColumn() ?: '');
}

function tt_legacy_save(PDO $db, array $data, ?int $legacyId = null): int {
    $teacherName = tt_teacher_name($db, $data['teacher_id']);
    if ($legacyId) {
        $stmt = $db->prepare("UPDATE timetable SET class = ?, section = ?, subject = ?, teacher_id = ?, teacher_name = ?, day_of_week = ?, day = ?, start_time = ?, end_time = ?, room_number = ?, room = ?, status = ? WHERE id = ?");
        $stmt->execute([$data['class_id'], $data['section_id'], $data['subject_id'], $data['teacher_id'], $teacherName, $data['day_of_week'], $data['day_of_week'], $data['start_time'], $data['end_time'], $data['room_no'], $data['room_no'], $data['status'], $legacyId]);
        return $legacyId;
    }

    $stmt = $db->prepare("INSERT INTO timetable (class, section, subject, teacher_id, teacher_name, day_of_week, day, period_number, start_time, end_time, room_number, room, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)");
    $stmt->execute([$data['class_id'], $data['section_id'], $data['subject_id'], $data['teacher_id'], $teacherName, $data['day_of_week'], $data['day_of_week'], $data['start_time'], $data['end_time'], $data['room_no'], $data['room_no'], $data['status']]);
    return (int)$db->lastInsertId();
}

function tt_has_clash(PDO $db, array $data, ?int $excludeId, string $type): bool {
    $params = [$data['day_of_week'], $data['end_time'], $data['start_time']];
    $where = "day_of_week = ? AND start_time < ? AND end_time > ? AND LOWER(status) = 'active'";
    if ($type === 'teacher') {
        if (empty($data['teacher_id'])) {
            return false;
        }
        $where .= ' AND teacher_id = ?';
        $params[] = $data['teacher_id'];
    } else {
        $where .= ' AND class_id = ? AND COALESCE(section_id, "") = ?';
        $params[] = $data['class_id'];
        $params[] = $data['section_id'];
    }
    if ($excludeId) {
        $where .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM timetables WHERE $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

tt_ensure_schema($db);
$currentTeacherId = tt_teacher_id($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        if (!$isAdmin) {
            throw new Exception('Only admin can manage timetable entries.');
        }

        $action = $_POST['action'] ?? '';
        if (in_array($action, ['add', 'edit'], true)) {
            $id = (int)($_POST['id'] ?? 0);
            if ($action === 'edit' && $id <= 0) {
                throw new Exception('Invalid timetable entry selected.');
            }

            $data = [
                'class_id' => sanitizeInput($_POST['class_id'] ?? ''),
                'section_id' => sanitizeInput($_POST['section_id'] ?? ''),
                'subject_id' => sanitizeInput($_POST['subject_id'] ?? ''),
                'teacher_id' => (int)($_POST['teacher_id'] ?? 0) ?: null,
                'day_of_week' => sanitizeInput($_POST['day_of_week'] ?? ''),
                'start_time' => sanitizeInput($_POST['start_time'] ?? ''),
                'end_time' => sanitizeInput($_POST['end_time'] ?? ''),
                'room_no' => sanitizeInput($_POST['room_no'] ?? ''),
                'status' => in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
            ];

            if ($data['class_id'] === '' || $data['subject_id'] === '' || $data['day_of_week'] === '' || !in_array($data['day_of_week'], $GLOBALS['days'], true)) {
                throw new Exception('Please fill class, subject, and day.');
            }
            if (!tt_valid_time($data['start_time']) || !tt_valid_time($data['end_time']) || $data['start_time'] >= $data['end_time']) {
                throw new Exception('Please enter a valid start and end time.');
            }
            if ($data['status'] === 'active' && tt_has_clash($db, $data, $action === 'edit' ? $id : null, 'teacher')) {
                throw new Exception('Teacher time clash detected for this day and time.');
            }
            if ($data['status'] === 'active' && tt_has_clash($db, $data, $action === 'edit' ? $id : null, 'class')) {
                throw new Exception('Class time clash detected for this day and time.');
            }

            if ($action === 'add') {
                $legacyId = tt_legacy_save($db, $data);
                $stmt = $db->prepare("INSERT INTO timetables (class_id, section_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no, status, legacy_timetable_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$data['class_id'], $data['section_id'], $data['subject_id'], $data['teacher_id'], $data['day_of_week'], $data['start_time'], $data['end_time'], $data['room_no'], $data['status'], $legacyId, $userId]);
                setFlashMessage('success', 'Timetable entry added successfully.');
            } else {
                $stmt = $db->prepare('SELECT legacy_timetable_id FROM timetables WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $legacyId = (int)($stmt->fetchColumn() ?: 0);
                $legacyId = tt_legacy_save($db, $data, $legacyId ?: null);
                $stmt = $db->prepare("UPDATE timetables SET class_id = ?, section_id = ?, subject_id = ?, teacher_id = ?, day_of_week = ?, start_time = ?, end_time = ?, room_no = ?, status = ?, legacy_timetable_id = ? WHERE id = ?");
                $stmt->execute([$data['class_id'], $data['section_id'], $data['subject_id'], $data['teacher_id'], $data['day_of_week'], $data['start_time'], $data['end_time'], $data['room_no'], $data['status'], $legacyId, $id]);
                setFlashMessage('success', 'Timetable entry updated successfully.');
            }
            redirect('index.php');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT legacy_timetable_id FROM timetables WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $legacyId = (int)($stmt->fetchColumn() ?: 0);
            $db->prepare('DELETE FROM timetables WHERE id = ?')->execute([$id]);
            if ($legacyId) {
                $db->prepare('DELETE FROM timetable WHERE id = ?')->execute([$legacyId]);
            }
            setFlashMessage('success', 'Timetable entry deleted successfully.');
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
$subjects = getAllSubjects($db);
foreach (['English', 'Urdu', 'Islamiyat', 'Pakistan Studies', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science'] as $subject) {
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

$filterClass = trim((string)($_GET['class_id'] ?? ''));
$filterSection = trim((string)($_GET['section_id'] ?? ''));
$filterTeacher = (int)($_GET['teacher_id'] ?? 0);
$filterDay = in_array($_GET['day'] ?? '', $days, true) ? $_GET['day'] : '';

$where = [];
$params = [];
if ($filterClass !== '') {
    $where[] = 'tt.class_id = ?';
    $params[] = $filterClass;
}
if ($filterSection !== '') {
    $where[] = 'tt.section_id = ?';
    $params[] = $filterSection;
}
if ($filterTeacher > 0) {
    $where[] = 'tt.teacher_id = ?';
    $params[] = $filterTeacher;
}
if ($filterDay !== '') {
    $where[] = 'tt.day_of_week = ?';
    $params[] = $filterDay;
}
if ($isTeacher && !$isAdmin) {
    $where[] = 'tt.teacher_id = ?';
    $params[] = $currentTeacherId ?: 0;
}
if (!$isAdmin && !$isTeacher) {
    $where[] = "LOWER(tt.status) = 'active'";
}
if ($role === 'student' && tableExists($db, 'students') && columnExists($db, 'students', 'user_id')) {
    $student = sharedFetchOne($db, 'SELECT class, section FROM students WHERE user_id = ? LIMIT 1', [$userId]);
    if ($student) {
        $where[] = 'tt.class_id = ?';
        $params[] = $student['class'];
        if (!empty($student['section'])) {
            $where[] = "(tt.section_id = ? OR tt.section_id = '')";
            $params[] = $student['section'];
        }
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $db->prepare("
    SELECT tt.*, COALESCE(s.full_name, 'Unassigned') AS teacher_name
    FROM timetables tt
    LEFT JOIN staff s ON s.id = tt.teacher_id
    $whereSql
    ORDER BY FIELD(tt.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), tt.start_time ASC
");
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$timeSlots = [];
foreach ($entries as $entry) {
    $key = substr((string)$entry['start_time'], 0, 5) . '-' . substr((string)$entry['end_time'], 0, 5);
    $timeSlots[$key] = ['start' => $entry['start_time'], 'end' => $entry['end_time']];
}
uasort($timeSlots, static fn($a, $b) => strcmp((string)$a['start'], (string)$b['start']));

$page_title = 'Timetable Management';
include '../../includes/header.php';
?>

<div class="container-fluid timetable-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-calendar-alt me-2" style="color:var(--teal);"></i>Timetable Management</h2>
            <div class="text-muted">Create, filter, print, and review weekly class schedules.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            <?php if ($isAdmin): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#entryModal"><i class="fas fa-plus me-1"></i>Add Entry</button>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Class</label>
                    <select name="class_id" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?><option value="<?= tt_h($class['id']) ?>" <?= $filterClass === (string)$class['id'] ? 'selected' : '' ?>><?= tt_h($class['class_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Section</label>
                    <select name="section_id" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?><option value="<?= tt_h($section['id']) ?>" <?= $filterSection === (string)$section['id'] ? 'selected' : '' ?>><?= tt_h($section['section_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Teacher</label>
                    <select name="teacher_id" class="form-select" <?= $isTeacher && !$isAdmin ? 'disabled' : '' ?>>
                        <option value="">All Teachers</option>
                        <?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>" <?= $filterTeacher === (int)$teacher['id'] ? 'selected' : '' ?>><?= tt_h($teacher['full_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Day</label>
                    <select name="day" class="form-select">
                        <option value="">All Days</option>
                        <?php foreach ($days as $day): ?><option value="<?= tt_h($day) ?>" <?= $filterDay === $day ? 'selected' : '' ?>><?= tt_h($day) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Weekly Timetable</h5>
            <span class="badge bg-light text-dark border"><?= count($entries) ?> entries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered text-center align-middle mb-0 timetable-grid">
                    <thead class="table-light">
                        <tr>
                            <th class="time-col">Time</th>
                            <?php foreach ($days as $day): ?>
                                <?php if ($filterDay !== '' && $filterDay !== $day) continue; ?>
                                <th><?= tt_h($day) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$timeSlots): ?>
                            <tr><td colspan="<?= ($filterDay ? 2 : 7) ?>" class="py-5 text-muted">No timetable entries found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($timeSlots as $slot): ?>
                            <tr>
                                <td class="fw-bold bg-light"><?= tt_h(substr((string)$slot['start'], 0, 5) . ' - ' . substr((string)$slot['end'], 0, 5)) ?></td>
                                <?php foreach ($days as $day): ?>
                                    <?php if ($filterDay !== '' && $filterDay !== $day) continue; ?>
                                    <?php $matches = array_values(array_filter($entries, static fn($e) => $e['day_of_week'] === $day && $e['start_time'] === $slot['start'] && $e['end_time'] === $slot['end'])); ?>
                                    <td>
                                        <?php foreach ($matches as $entry): ?>
                                            <div class="slot-card text-start">
                                                <div class="fw-bold"><?= tt_h($entry['subject_id']) ?></div>
                                                <div class="small text-muted"><?= tt_h($entry['class_id']) ?><?= $entry['section_id'] ? ' - ' . tt_h($entry['section_id']) : '' ?></div>
                                                <div class="small"><i class="fas fa-user-tie me-1"></i><?= tt_h($entry['teacher_name']) ?></div>
                                                <div class="small"><i class="fas fa-door-open me-1"></i><?= tt_h($entry['room_no'] ?: '-') ?></div>
                                                <div class="mt-2 d-flex gap-1 no-print">
                                                    <span class="badge <?= strtolower((string)$entry['status']) === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= tt_h(ucfirst((string)$entry['status'])) ?></span>
                                                    <?php if ($isAdmin): ?>
                                                        <button class="btn btn-sm btn-outline-primary edit-entry"
                                                            data-bs-toggle="modal" data-bs-target="#editEntryModal"
                                                            data-entry='<?= tt_h(json_encode($entry)) ?>'><i class="fas fa-pen"></i></button>
                                                        <form method="POST" onsubmit="return confirm('Delete this timetable entry?');">
                                                            <?= csrfTokenInput() ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
                                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<?php
$formFields = function (?array $entry = null, string $prefix = '') use ($classes, $sections, $subjects, $teachers, $days) {
?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Class *</label>
            <select name="class_id" id="<?= $prefix ?>class_id" class="form-select" required>
                <option value="">Select class</option>
                <?php foreach ($classes as $class): ?><option value="<?= tt_h($class['id']) ?>"><?= tt_h($class['class_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Section</label>
            <select name="section_id" id="<?= $prefix ?>section_id" class="form-select">
                <option value="">All sections</option>
                <?php foreach ($sections as $section): ?><option value="<?= tt_h($section['id']) ?>"><?= tt_h($section['section_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Subject *</label>
            <select name="subject_id" id="<?= $prefix ?>subject_id" class="form-select" required>
                <option value="">Select subject</option>
                <?php foreach ($subjects as $subject): ?><option value="<?= tt_h($subject['id']) ?>"><?= tt_h($subject['subject_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Teacher</label>
            <select name="teacher_id" id="<?= $prefix ?>teacher_id" class="form-select">
                <option value="">Unassigned</option>
                <?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>"><?= tt_h($teacher['full_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Day *</label>
            <select name="day_of_week" id="<?= $prefix ?>day_of_week" class="form-select" required>
                <?php foreach ($days as $day): ?><option value="<?= tt_h($day) ?>"><?= tt_h($day) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label">Start Time *</label><input type="time" name="start_time" id="<?= $prefix ?>start_time" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">End Time *</label><input type="time" name="end_time" id="<?= $prefix ?>end_time" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Room No</label><input type="text" name="room_no" id="<?= $prefix ?>room_no" class="form-control"></div>
        <div class="col-md-12">
            <label class="form-label">Status</label>
            <select name="status" id="<?= $prefix ?>status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>
<?php
};
?>
<div class="modal fade no-print" id="entryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Add Timetable Entry</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><?php $formFields(null, 'add_'); ?></div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Entry</button></div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="editEntryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content border-0 shadow" method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit Timetable Entry</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><?php $formFields(null, 'edit_'); ?></div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Entry</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    .time-col { min-width:130px; }
    .slot-card { border-left:4px solid var(--teal); background:#f8fafc; border-radius:8px; padding:.65rem; margin:.25rem 0; min-width:170px; }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop, .btn, form { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; } .slot-card { break-inside:avoid; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-entry').forEach(function (button) {
        button.addEventListener('click', function () {
            const entry = JSON.parse(button.dataset.entry || '{}');
            const fields = ['id', 'class_id', 'section_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time', 'room_no', 'status'];
            fields.forEach(function (field) {
                const el = document.getElementById('edit_' + field);
                if (el) el.value = (field === 'start_time' || field === 'end_time') && entry[field] ? entry[field].slice(0, 5) : (entry[field] || '');
            });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
