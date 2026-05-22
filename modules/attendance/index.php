<?php
// File: modules/attendance/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();
$role = getUserRole();
$canMarkAttendance = in_array($role, ['admin', 'teacher'], true);

$class_groups = [
    '── Intermediate Programs ──' => [
        '11th Pre-Medical','12th Pre-Medical',
        '11th Pre-Engineering','12th Pre-Engineering',
        '11th ICS','12th ICS',
        '11th I.Com','12th I.Com',
        '11th FA','12th FA',
    ],
    '── Degree Programs ──' => [
        'ADP Arts','ADP Science',
        'BSCS','BS IT','BS Zoology','BS Mathematics',
    ],
    '── NAVTTC Short Courses ──' => [
        'CCA Computer Applications','Web Development','Graphic Designing',
    ],
];

$selected_class = $_GET['class'] ?? '';
$selected_section = $_GET['section'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');
$students = [];

// 1. Fetch students based on class and section
if ($selected_class && $selected_section) {
    $stmt = $db->prepare("
        SELECT * FROM students 
        WHERE (
            class = ? 
            OR class = REPLACE(?, ' ', '')
            OR class LIKE ?
        )
        AND (
            section = ? 
            OR section = CONCAT('Section ', ?)
            OR section LIKE ?
        )
        AND status = 'Active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([
        $selected_class, $selected_class, '%'.$selected_class.'%',
        $selected_section, $selected_section, '%'.$selected_section.'%'
    ]);
    $students = $stmt->fetchAll();
}

$page_title = "Student Attendance";
include '../../includes/header.php';
?>

<style>
    :root { --teal: #4ec2b5; --navy: #0f2d48; }
    .page-header { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 2rem; }
    .card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .btn-navy { background: var(--navy); color: white; }
    .btn-navy:hover { background: #1a4060; color: white; }
    .btn-teal { background: var(--teal); color: var(--navy); fw-bold; }
    .btn-wa { background: #25d366; color: white; border-radius: 50px; padding: 5px 15px; font-size: 0.85rem; text-decoration: none; display: inline-block; }
    .btn-wa:hover { background: #128c7e; color: white; }
    .mark-table thead { background: var(--navy); color: white; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="fw-bold text-navy mb-1"><i class="fas fa-calendar-check me-2 text-teal"></i>Student Attendance</h2>
        <p class="text-muted mb-0"><?= $role === 'teacher' ? 'Teacher attendance is submitted for admin approval before it goes live.' : 'Mark daily attendance and send WhatsApp alerts' ?></p>
    </div>
    <div class="text-end">
        <h5 class="fw-bold mb-0"><?= date('d M Y', strtotime($date)) ?></h5>
        <span class="badge bg-light text-navy border"><?= date('l') ?></span>
    </div>
</div>

<?php displayFlashMessage(); ?>

<!-- ── WhatsApp Alerts Panel ── -->
<?php if(isset($_GET['saved']) && !empty($_SESSION['absent_students'])): ?>
    <div class="card mb-4 border-teal" style="border: 1px solid var(--teal) !important;">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold text-navy"><i class="fab fa-whatsapp me-2 text-success"></i>Send Absent Alerts</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach($_SESSION['absent_students'] as $student): 
                    $phone = '92' . ltrim($student['guardian_phone'], '0');
                    $message = urlencode(
                        'Assalam-o-Alaikum,' . PHP_EOL .
                        'Quaid-e-Azam Group of Colleges' . PHP_EOL .
                        'Dear Parent,' . PHP_EOL .
                        'Your child *' . $student['first_name'] . ' ' . $student['last_name'] . '* ' . 
                        'was ABSENT on ' . $_SESSION['attendance_date'] . 
                        ' in ' . $_SESSION['attendance_class'] . '.' . PHP_EOL .
                        'Please ensure regular attendance.' . PHP_EOL .
                        'Principal: Mr. Zafar Iqbal' . PHP_EOL .
                        'Contact: +923338879961'
                    );
                ?>
                <div class="col-md-4">
                    <div class="p-3 border rounded-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($student['guardian_phone']) ?></small>
                        </div>
                        <a href="https://wa.me/<?= $phone ?>?text=<?= $message ?>" target="_blank" class="btn-wa">
                            <i class="fab fa-whatsapp me-1"></i> Send Alert
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['absent_students']); // Clear after showing ?>
<?php endif; ?>

<!-- ── Filters ── -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Select Class</label>
                <select name="class" class="form-select" required>
                    <option value="">Choose Class...</option>
                    <?php foreach($class_groups as $group => $list): ?>
                        <optgroup label="<?= $group ?>">
                            <?php foreach($list as $cls): ?>
                                <option value="<?= $cls ?>" <?= $selected_class == $cls ? 'selected' : '' ?>><?= $cls ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Section</label>
                <select name="section" class="form-select" required>
                    <option value="">Choose Section...</option>
                    <option value="A" <?= $selected_section == 'A' ? 'selected' : '' ?>>Section A</option>
                    <option value="B" <?= $selected_section == 'B' ? 'selected' : '' ?>>Section B</option>
                    <option value="C" <?= $selected_section == 'C' ? 'selected' : '' ?>>Section C</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Date</label>
                <input type="date" name="date" class="form-control" value="<?= $date ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-navy w-100 py-2 fw-bold">Load Students</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Attendance Table ── -->
<?php if ($selected_class && $selected_section): ?>
    <div class="card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-0">
            <h5 class="mb-0 fw-bold text-navy">Marking: <?= htmlspecialchars($selected_class) ?> - Section <?= htmlspecialchars($selected_section) ?></h5>
            <span class="badge bg-teal-light text-navy border px-3"><?= count($students) ?> Students</span>
        </div>
        <div class="card-body p-0">
            <?php if(!empty($students)): ?>
                <?php if ($canMarkAttendance): ?>
                <form method="POST" action="save_attendance.php">
                    <input type="hidden" name="class" value="<?= htmlspecialchars($selected_class) ?>">
                    <input type="hidden" name="section" value="<?= htmlspecialchars($selected_section) ?>">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover mark-table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th class="text-center">Present</th>
                                    <th class="text-center">Absent</th>
                                    <th class="text-center">Late</th>
                                    <th class="text-center">Leave</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($students as $i => $student): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $i+1 ?></td>
                                    <td><code class="text-navy fw-bold"><?= htmlspecialchars($student['student_id']) ?></code></td>
                                    <td class="fw-bold"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></td>
                                    <td class="text-center"><input type="radio" name="attendance[<?= $student['id'] ?>]" value="Present" class="form-check-input" checked></td>
                                    <td class="text-center"><input type="radio" name="attendance[<?= $student['id'] ?>]" value="Absent" class="form-check-input"></td>
                                    <td class="text-center"><input type="radio" name="attendance[<?= $student['id'] ?>]" value="Late" class="form-check-input"></td>
                                    <td class="text-center"><input type="radio" name="attendance[<?= $student['id'] ?>]" value="Leave" class="form-check-input"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-4 border-top text-end">
                        <button type="submit" class="btn btn-navy btn-lg px-5 rounded-pill shadow-sm">
                            <i class="fas fa-save me-2"></i>Save Attendance
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">Students can view attendance records but cannot edit or mark attendance.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="p-5 text-center">
                    <i class="fas fa-users-slash fa-4x text-light mb-3"></i>
                    <h5 class="text-muted">No students found in <?= htmlspecialchars($selected_class) ?> — <?= htmlspecialchars($selected_section) ?></h5>
                    <p class="text-muted small">Please verify the class and section values in student records.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
