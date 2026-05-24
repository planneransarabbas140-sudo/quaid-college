<?php
/**
 * File: modules/examination/schedule.php
 * Examination Schedule Management for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'teacher']);

$db = (new Database())->getConnection();
$canManageSchedule = in_array(getUserRole(), ['admin', 'owner'], true);

// --- ENSURE TABLE EXISTS ---
$db->exec("CREATE TABLE IF NOT EXISTS exam_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_title VARCHAR(200),
    exam_type VARCHAR(50),
    class VARCHAR(100),
    section VARCHAR(50),
    subject VARCHAR(100),
    exam_date DATE,
    start_time TIME,
    end_time TIME,
    room VARCHAR(50),
    total_marks INT DEFAULT 100,
    campus VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- HANDLE POST ACTIONS ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            requireCsrfToken();
            if (!$canManageSchedule) {
                throw new Exception('You are not allowed to change exam schedules.');
            }

            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $exam_title = sanitizeInput($_POST['exam_title']);
                $exam_type = $_POST['exam_type'];
                $class = $_POST['class'];
                $section = $_POST['section'];
                $subject = sanitizeInput($_POST['subject']);
                $exam_date = $_POST['exam_date'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $room = sanitizeInput($_POST['room']);
                $total_marks = (int)$_POST['total_marks'];
                $campus = $_POST['campus'];

                if ($_POST['action'] === 'add') {
                    $sql = "INSERT INTO exam_schedule (exam_title, exam_type, class, section, subject, exam_date, start_time, end_time, room, total_marks, campus) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$exam_title, $exam_type, $class, $section, $subject, $exam_date, $start_time, $end_time, $room, $total_marks, $campus]);
                    setFlashMessage('success', "Exam schedule added successfully!");
                } else {
                    $id = $_POST['id'];
                    $sql = "UPDATE exam_schedule SET exam_title=?, exam_type=?, class=?, section=?, subject=?, exam_date=?, start_time=?, end_time=?, room=?, total_marks=?, campus=? WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$exam_title, $exam_type, $class, $section, $subject, $exam_date, $start_time, $end_time, $room, $total_marks, $campus, $id]);
                    setFlashMessage('success', "Exam schedule updated successfully!");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                $db->prepare("DELETE FROM exam_schedule WHERE id = ?")->execute([$id]);
                setFlashMessage('success', "Schedule deleted successfully!");
            }
            redirect('schedule.php');
        } catch (Exception $e) {
            setFlashMessage('error', "Error: " . $e->getMessage());
            redirect('schedule.php');
        }
    }
}

// --- FETCH DATA ---
$schedule = $db->query("SELECT * FROM exam_schedule ORDER BY exam_date ASC, start_time ASC")->fetchAll();

$page_title = "Exam Schedule";
include '../../includes/header.php';

// Class/Section Lists
$classes = [
    'Intermediate' => ['FSc Pre-Medical', 'FSc Pre-Engineering', 'ICS', 'I.Com', 'FA', 'Taleem-ul-Islam'],
    'Degree' => ['ADP Arts', 'ADP Science', 'BSCS', 'BS IT', 'BS Zoology', 'BS Mathematics', 'BS Urdu', 'BS Chemistry'],
    'NAVTTC' => ['CCA', 'Web Development', 'Graphic Designing', 'Digital Marketing', 'UX/UI Design', 'AI', 'Vibe Coding']
];
$sections = ['A', 'B', 'C', 'D', 'Morning', 'Evening', 'Weekend', 'Batch 1', 'Batch 2', 'Batch 3', 'Batch 4'];
$campuses = ['Rajanpur', 'Fazilpur', 'Kot Mithan'];
$exam_types = ['Mid Term', 'Final Term', 'Unit Test', 'Practical', 'Mock Test'];
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <a href="index.php" class="btn btn-sm mb-3 back-btn">
                <i class="fas fa-arrow-left"></i> Back to Examination
            </a>
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="page-title mb-0"><i class="fas fa-calendar-alt me-2" style="color: var(--teal);"></i>Exam Schedule</h2>
                <div class="btn-group">
                    <?php if ($canManageSchedule): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                        <i class="fas fa-plus me-2"></i>Add Schedule
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-navy" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 datatable">
                    <thead class="bg-light">
                        <tr>
                            <th>Exam Info</th>
                            <th>Class & Section</th>
                            <th>Subject</th>
                            <th>Date & Time</th>
                            <th>Room</th>
                            <th>Marks</th>
                            <th>Campus</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-navy"><?= htmlspecialchars($row['exam_title']) ?></div>
                                <span class="badge bg-light text-dark"><?= $row['exam_type'] ?></span>
                            </td>
                            <td>
                                <div><?= $row['class'] ?></div>
                                <small class="text-muted">Section: <?= $row['section'] ?></small>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($row['subject']) ?></td>
                            <td>
                                <div><i class="fas fa-calendar-day me-1 text-teal"></i> <?= date('d M Y', strtotime($row['exam_date'])) ?></div>
                                <small class="text-muted"><i class="fas fa-clock me-1"></i> <?= date('h:i A', strtotime($row['start_time'])) ?> - <?= date('h:i A', strtotime($row['end_time'])) ?></small>
                            </td>
                            <td><span class="badge bg-navy-light text-navy"><?= htmlspecialchars($row['room']) ?></span></td>
                            <td><span class="fw-bold"><?= $row['total_marks'] ?></span></td>
                            <td><span class="badge bg-info-subtle text-info"><?= $row['campus'] ?></span></td>
                            <td class="text-end">
                                <?php if ($canManageSchedule): ?>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" onclick='editSchedule(<?= json_encode($row) ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteSchedule(<?= $row['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
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
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-calendar-plus me-2"></i>Add Exam Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="scheduleForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="schedule_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Exam Title</label>
                            <input type="text" name="exam_title" id="exam_title" class="form-control" required placeholder="e.g. Mid Term 2026">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Exam Type</label>
                            <select name="exam_type" id="exam_type" class="form-select" required>
                                <?php foreach ($exam_types as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Class / Program</label>
                            <select name="class" id="class" class="form-select" required>
                                <?php foreach ($classes as $group => $list): ?>
                                    <optgroup label="<?= $group ?>">
                                        <?php foreach ($list as $c): ?>
                                            <option value="<?= $c ?>"><?= $c ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Section</label>
                            <select name="section" id="section" class="form-select" required>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Campus</label>
                            <select name="campus" id="campus" class="form-select" required>
                                <?php foreach ($campuses as $cp): ?>
                                    <option value="<?= $cp ?>"><?= $cp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Subject</label>
                            <input type="text" name="subject" id="subject" class="form-control" required placeholder="Enter subject name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Exam Date</label>
                            <input type="date" name="exam_date" id="exam_date" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Total Marks</label>
                            <input type="number" name="total_marks" id="total_marks" class="form-control" value="100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Start Time</label>
                            <input type="time" name="start_time" id="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">End Time</label>
                            <input type="time" name="end_time" id="end_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Room / Hall</label>
                            <input type="text" name="room" id="room" class="form-control" required placeholder="e.g. Hall-A, Room 10">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="submitBtn">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form action="" method="POST">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                    <h5>Are you sure?</h5>
                    <p class="small text-muted">This action will delete this exam schedule record.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
        --navy-light: #e7eaed;
    }
    .bg-navy { background-color: var(--navy) !important; }
    .text-navy { color: var(--navy) !important; }
    .text-teal { color: var(--teal) !important; }
    .back-btn {
        background: rgba(78,194,181,.1);
        border: 1px solid rgba(78,194,181,.3);
        color: #4ec2b5;
        border-radius: 99px;
        padding: 6px 18px;
    }
    .back-btn:hover {
        background: rgba(78,194,181,.2);
        color: #3da89c;
    }
    .page-title { font-family: 'Playfair Display', serif; font-weight: 700; color: var(--navy); }
    .btn-primary { background-color: var(--teal); border-color: var(--teal); }
    .btn-primary:hover { background-color: #3da89c; border-color: #3da89c; }
    .btn-outline-navy { color: var(--navy); border-color: var(--navy); }
    .btn-outline-navy:hover { background-color: var(--navy); color: white; }
    
    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        color: #64748b;
        border-top: none;
    }

    /* Modal Fixes */
    .modal { z-index: 99999 !important; }
    .modal-backdrop { z-index: 99998 !important; }
    .modal-dialog { z-index: 100000 !important; }
    
    @media print {
        .back-btn, .btn-group, .text-end, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
            display: none !important;
        }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        .page-title { text-align: center; margin-bottom: 20px; }
    }
</style>

<script>
function editSchedule(row) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Exam Schedule';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('schedule_id').value = row.id;
    document.getElementById('exam_title').value = row.exam_title;
    document.getElementById('exam_type').value = row.exam_type;
    document.getElementById('class').value = row.class;
    document.getElementById('section').value = row.section;
    document.getElementById('subject').value = row.subject;
    document.getElementById('exam_date').value = row.exam_date;
    document.getElementById('start_time').value = row.start_time;
    document.getElementById('end_time').value = row.end_time;
    document.getElementById('room').value = row.room;
    document.getElementById('total_marks').value = row.total_marks;
    document.getElementById('campus').value = row.campus;
    document.getElementById('submitBtn').innerText = 'Update Schedule';
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function deleteSchedule(id) {
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Reset modal on close
document.getElementById('scheduleModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('scheduleForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').innerText = 'Save Schedule';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-calendar-plus me-2"></i>Add Exam Schedule';
});
</script>

<?php include '../../includes/footer.php'; ?>
