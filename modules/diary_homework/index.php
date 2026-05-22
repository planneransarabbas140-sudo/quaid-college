<?php
// File: modules/diary_homework/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$db = (new Database())->getConnection();
$page_title = "Homework Diary";
$role = strtolower(getUserRole() ?? '');
$isAdmin = in_array($role, ['admin', 'super_admin'], true);
$isTeacher = $role === 'teacher';
$canManageHomework = $isAdmin || $isTeacher;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeDateOrNull($value) {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function homeworkStatusClass($status, $dueDate) {
    if ($status === 'Completed') {
        return 'success';
    }
    if ($status === 'Archived') {
        return 'secondary';
    }
    if (!empty($dueDate) && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        return 'danger';
    }
    return 'primary';
}

$db->exec("CREATE TABLE IF NOT EXISTS homework_diary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diary_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    class VARCHAR(100) NOT NULL,
    section VARCHAR(50) DEFAULT NULL,
    subject VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    homework TEXT NOT NULL,
    instructions TEXT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    status ENUM('Assigned','Completed','Archived') DEFAULT 'Assigned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_diary_class (class),
    INDEX idx_diary_date (diary_date),
    INDEX idx_diary_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];

        if (in_array($action, ['add_homework', 'edit_homework'], true)) {
            if (!$canManageHomework) {
                throw new Exception('You are not allowed to manage homework entries.');
            }

            $diaryDate = normalizeDateOrNull($_POST['diary_date'] ?? date('Y-m-d'));
            $dueDate = normalizeDateOrNull($_POST['due_date'] ?? null);
            $class = sanitizeInput($_POST['class'] ?? '');
            $section = sanitizeInput($_POST['section'] ?? '');
            $subject = sanitizeInput($_POST['subject'] ?? '');
            $title = sanitizeInput($_POST['title'] ?? '');
            $homework = trim($_POST['homework'] ?? '');
            $instructions = trim($_POST['instructions'] ?? '');

            if (!$diaryDate || $class === '' || $subject === '' || $title === '' || $homework === '') {
                throw new Exception('Please fill all required homework fields.');
            }

            if ($action === 'add_homework' && $isTeacher) {
                createApprovalRequest($db, 'homework', 'create', [
                    'diary_date' => $diaryDate,
                    'due_date' => $dueDate,
                    'class' => $class,
                    'section' => $section,
                    'subject' => $subject,
                    'title' => $title,
                    'homework' => $homework,
                    'instructions' => $instructions
                ]);
                setFlashMessage('success', 'Homework submitted for admin approval.');
            } elseif ($action === 'add_homework') {
                $stmt = $db->prepare("INSERT INTO homework_diary
                    (diary_date, due_date, class, section, subject, title, homework, instructions, assigned_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $diaryDate,
                    $dueDate,
                    $class,
                    $section,
                    $subject,
                    $title,
                    $homework,
                    $instructions,
                    $_SESSION['user_id'] ?? null
                ]);
                setFlashMessage('success', 'Homework assigned successfully.');
            } else {
                if (!$isAdmin) {
                    throw new Exception('Only admin can edit live homework entries.');
                }
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Invalid homework entry.');
                }

                $stmt = $db->prepare("UPDATE homework_diary
                    SET diary_date = ?, due_date = ?, class = ?, section = ?, subject = ?, title = ?, homework = ?, instructions = ?
                    WHERE id = ?");
                $stmt->execute([$diaryDate, $dueDate, $class, $section, $subject, $title, $homework, $instructions, $id]);
                setFlashMessage('success', 'Homework updated successfully.');
            }

            redirect('index.php');
        }

        if ($action === 'update_status') {
            if (!$isAdmin) {
                throw new Exception('Only admin can update live homework status.');
            }

            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'Assigned';
            if ($id <= 0 || !in_array($status, ['Assigned', 'Completed', 'Archived'], true)) {
                throw new Exception('Invalid status update.');
            }

            $stmt = $db->prepare("UPDATE homework_diary SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            setFlashMessage('success', 'Homework status updated.');
            redirect('index.php');
        }

        if ($action === 'delete_homework') {
            if (!$isAdmin) {
                throw new Exception('Only admin can delete live homework entries.');
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Invalid homework entry.');
            }

            $stmt = $db->prepare("DELETE FROM homework_diary WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('success', 'Homework deleted successfully.');
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
$existingSubjects = $db->query("SELECT DISTINCT subject FROM homework_diary WHERE subject IS NOT NULL AND subject <> '' ORDER BY subject ASC")->fetchAll(PDO::FETCH_COLUMN);
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
$filterStatus = trim($_GET['status'] ?? '');
$filterDate = trim($_GET['date'] ?? '');

$where = [];
$params = [];

if ($filterClass !== '') {
    $where[] = 'hd.class = ?';
    $params[] = $filterClass;
}
if ($filterSubject !== '') {
    $where[] = 'hd.subject = ?';
    $params[] = $filterSubject;
}
if ($filterStatus !== '') {
    $where[] = 'hd.status = ?';
    $params[] = $filterStatus;
}
if ($filterDate !== '') {
    $where[] = 'hd.diary_date = ?';
    $params[] = $filterDate;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT hd.*, COALESCE(u.full_name, u.username, 'Admin') as assigned_by_name
    FROM homework_diary hd
    LEFT JOIN users u ON hd.assigned_by = u.id
    $whereSql
    ORDER BY hd.diary_date DESC, hd.id DESC");
$stmt->execute($params);
$homeworkEntries = $stmt->fetchAll();

$todayHomework = (int)$db->query("SELECT COUNT(*) FROM homework_diary WHERE diary_date = CURDATE()")->fetchColumn();
$activeHomework = (int)$db->query("SELECT COUNT(*) FROM homework_diary WHERE status = 'Assigned'")->fetchColumn();
$overdueHomework = (int)$db->query("SELECT COUNT(*) FROM homework_diary WHERE status = 'Assigned' AND due_date IS NOT NULL AND due_date < CURDATE()")->fetchColumn();
$coveredClasses = (int)$db->query("SELECT COUNT(DISTINCT class) FROM homework_diary")->fetchColumn();

include '../../includes/header.php';
?>

<div class="container-fluid homework-module">
    <div class="row mb-4">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb" style="font-size:.82rem;font-family:'Space Mono',monospace;">
                        <li class="breadcrumb-item"><a href="../../dashboard.php" style="color:#4ec2b5;text-decoration:none;"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Diary & Homework</li>
                    </ol>
                </nav>
                <h2 class="page-title mb-1">Student Homework Diary</h2>
                <p class="text-muted mb-0"><?= $isTeacher ? 'Teacher homework is submitted for admin approval before it goes live.' : 'Assign, track, and review daily homework by class, subject, and due date.' ?></p>
            </div>
            <?php if ($canManageHomework): ?>
                <button class="btn btn-primary btn-lg rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#homeworkModal">
                    <i class="fas fa-pen me-2"></i>Assign Homework
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="diary-stat-card">
                <span class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-calendar-day"></i></span>
                <div>
                    <p>Today</p>
                    <h3><?= $todayHomework ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="diary-stat-card">
                <span class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-book-open"></i></span>
                <div>
                    <p>Active</p>
                    <h3><?= $activeHomework ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="diary-stat-card">
                <span class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-clock"></i></span>
                <div>
                    <p>Overdue</p>
                    <h3><?= $overdueHomework ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="diary-stat-card">
                <span class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-users"></i></span>
                <div>
                    <p>Classes</p>
                    <h3><?= $coveredClasses ?></h3>
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
                            <option value="<?= h($class) ?>" <?= $filterClass === $class ? 'selected' : '' ?>><?= h($class) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Subject</label>
                    <select name="subject" class="form-select">
                        <option value="">All subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= h($subject) ?>" <?= $filterSubject === $subject ? 'selected' : '' ?>><?= h($subject) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All status</option>
                        <?php foreach (['Assigned', 'Completed', 'Archived'] as $status): ?>
                            <option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">Diary Date</label>
                    <input type="date" name="date" class="form-control" value="<?= h($filterDate) ?>">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-navy"><i class="fas fa-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-navy">Homework Diary</h5>
            <a href="index.php" class="btn btn-sm btn-light rounded-pill px-3"><i class="fas fa-rotate me-1"></i> Reset</a>
        </div>
        <div class="card-body p-4">
            <?php if (empty($homeworkEntries)): ?>
                <div class="empty-diary-state text-center py-5">
                    <i class="fas fa-book fa-4x text-muted mb-3"></i>
                    <h4 class="fw-bold">No homework entries found</h4>
                    <p class="text-muted mb-0">Use the Assign Homework button to add the first diary entry.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead class="bg-light">
                            <tr>
                                <th>Date</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Homework</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th>Assigned By</th>
                                <?php if ($canManageHomework): ?>
                                    <th class="text-end">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($homeworkEntries as $entry): ?>
                                <?php $badgeClass = homeworkStatusClass($entry['status'], $entry['due_date']); ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= date('d M Y', strtotime($entry['diary_date'])) ?></div>
                                        <small class="text-muted"><?= date('l', strtotime($entry['diary_date'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= h($entry['class']) ?></span>
                                        <?php if (!empty($entry['section'])): ?>
                                            <span class="badge bg-light text-dark border">Sec <?= h($entry['section']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="subject-pill"><?= h($entry['subject']) ?></span></td>
                                    <td style="min-width: 320px;">
                                        <div class="fw-bold text-navy"><?= h($entry['title']) ?></div>
                                        <div class="text-muted small diary-preview"><?= nl2br(h($entry['homework'])) ?></div>
                                        <?php if (!empty($entry['instructions'])): ?>
                                            <div class="small mt-2 text-secondary"><i class="fas fa-circle-info me-1"></i><?= nl2br(h($entry['instructions'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($entry['due_date'])): ?>
                                            <span class="fw-bold"><?= date('d M Y', strtotime($entry['due_date'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?= $badgeClass ?>"><?= h($entry['status']) ?></span></td>
                                    <td><?= h($entry['assigned_by_name']) ?></td>
                                    <?php if ($canManageHomework): ?>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <?php if ($isAdmin): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary edit-homework-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editHomeworkModal"
                                                    data-id="<?= (int)$entry['id'] ?>"
                                                    data-diary-date="<?= h($entry['diary_date']) ?>"
                                                    data-due-date="<?= h($entry['due_date']) ?>"
                                                    data-class="<?= h($entry['class']) ?>"
                                                    data-section="<?= h($entry['section']) ?>"
                                                    data-subject="<?= h($entry['subject']) ?>"
                                                    data-title="<?= h($entry['title']) ?>"
                                                    data-homework="<?= h($entry['homework']) ?>"
                                                    data-instructions="<?= h($entry['instructions']) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
                                                    <input type="hidden" name="status" value="<?= $entry['status'] === 'Completed' ? 'Assigned' : 'Completed' ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Toggle completed">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this homework entry?');">
                                                    <input type="hidden" name="action" value="delete_homework">
                                                    <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Live edits require admin</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManageHomework): ?>
<datalist id="subjectOptions">
    <?php foreach ($subjects as $subject): ?>
        <option value="<?= h($subject) ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="modal fade" id="homeworkModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Assign Homework</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_homework">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Diary Date *</label>
                            <input type="date" name="diary_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Class / Program *</label>
                            <select name="class" class="form-select" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= h($class) ?>"><?= h($class) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Section</label>
                            <select name="section" class="form-select">
                                <option value="">All sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= h($section) ?>"><?= h($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Subject *</label>
                            <input list="subjectOptions" name="subject" class="form-control" placeholder="e.g. Mathematics" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Homework Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 3 Exercise" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Homework Details *</label>
                            <textarea name="homework" class="form-control" rows="5" placeholder="Write the homework, diary note, or class task..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Extra Instructions</label>
                            <textarea name="instructions" class="form-control" rows="2" placeholder="Optional notes for students or parents"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> <?= $isTeacher ? 'Submit for Approval' : 'Save Homework' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editHomeworkModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Homework</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_homework">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Diary Date *</label>
                            <input type="date" name="diary_date" id="edit_diary_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Due Date</label>
                            <input type="date" name="due_date" id="edit_due_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Class / Program *</label>
                            <select name="class" id="edit_class" class="form-select" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= h($class) ?>"><?= h($class) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Section</label>
                            <select name="section" id="edit_section" class="form-select">
                                <option value="">All sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= h($section) ?>"><?= h($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Subject *</label>
                            <input list="subjectOptions" name="subject" id="edit_subject" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Homework Title *</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Homework Details *</label>
                            <textarea name="homework" id="edit_homework" class="form-control" rows="5" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Extra Instructions</label>
                            <textarea name="instructions" id="edit_instructions" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4"><i class="fas fa-save me-1"></i> Update Homework</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    .homework-module .page-title {
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

    .diary-stat-card {
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

    .diary-stat-card .stat-icon {
        width: 58px;
        height: 58px;
        display: inline-grid;
        place-items: center;
        border-radius: 16px;
        font-size: 1.55rem;
        flex: 0 0 58px;
    }

    .diary-stat-card p {
        margin: 0 0 4px;
        color: #64748b;
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .diary-stat-card h3 {
        margin: 0;
        color: #0f2d48;
        font-weight: 800;
    }

    .subject-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 7px 12px;
        background: rgba(78, 194, 181, 0.12);
        color: #0f766e;
        font-size: .82rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .diary-preview {
        max-width: 540px;
        max-height: 72px;
        overflow: hidden;
        line-height: 1.55;
    }

    .empty-diary-state {
        border: 1px dashed #dbe4ef;
        border-radius: 18px;
        background: #f8fafc;
    }

    .homework-module .table > :not(caption) > * > * {
        padding: 1rem .85rem;
    }

    .modal {
        z-index: 1305 !important;
    }

    .modal-backdrop {
        z-index: 1290 !important;
    }

    @media (max-width: 767px) {
        .diary-stat-card {
            min-height: 96px;
            padding: 18px;
        }

        .homework-module .btn-lg {
            width: 100%;
        }
    }
</style>

<?php if ($canManageHomework): ?>
<script>
document.querySelectorAll('.edit-homework-btn').forEach((button) => {
    button.addEventListener('click', () => {
        const fields = {
            edit_id: 'id',
            edit_diary_date: 'diaryDate',
            edit_due_date: 'dueDate',
            edit_class: 'class',
            edit_section: 'section',
            edit_subject: 'subject',
            edit_title: 'title',
            edit_homework: 'homework',
            edit_instructions: 'instructions'
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
