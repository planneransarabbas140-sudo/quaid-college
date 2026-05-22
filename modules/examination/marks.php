<?php
/**
 * File: modules/examination/marks.php
 * Examination Marks Entry System for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$db = (new Database())->getConnection();

// --- ENSURE TABLE EXISTS ---
$db->exec("CREATE TABLE IF NOT EXISTS exam_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    exam_schedule_id INT,
    subject VARCHAR(100),
    class VARCHAR(100),
    section VARCHAR(50),
    total_marks INT,
    obtained_marks INT,
    grade VARCHAR(5),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_marks (student_id, exam_schedule_id)
)");

// --- HANDLE POST ACTIONS (Save Marks) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_marks') {
    try {
        $exam_id = $_POST['exam_id'];
        $subject = $_POST['subject'];
        $class = $_POST['class'];
        $section = $_POST['section'];
        $total_marks = $_POST['total_marks'];
        
        $marks_data = $_POST['marks']; // Array of student_id => obtained_marks
        $remarks_data = $_POST['remarks']; // Array of student_id => remarks

        foreach ($marks_data as $student_id => $obtained) {
            if ($obtained === '') continue; // Skip empty entries
            
            $obtained = (int)$obtained;
            $percentage = ($obtained / $total_marks) * 100;
            
            // Grade calculation
            $grade = 'F';
            if ($percentage >= 90) $grade = 'A+';
            elseif ($percentage >= 80) $grade = 'A';
            elseif ($percentage >= 70) $grade = 'B';
            elseif ($percentage >= 60) $grade = 'C';
            elseif ($percentage >= 50) $grade = 'D';

            $remarks = sanitizeInput($remarks_data[$student_id] ?? '');

            $sql = "INSERT INTO exam_marks (student_id, exam_schedule_id, subject, class, section, total_marks, obtained_marks, grade, remarks) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE obtained_marks = VALUES(obtained_marks), grade = VALUES(grade), remarks = VALUES(remarks)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$student_id, $exam_id, $subject, $class, $section, $total_marks, $obtained, $grade, $remarks]);
        }
        setFlashMessage('success', "Marks saved successfully!");
    } catch (Exception $e) {
        setFlashMessage('error', "Error: " . $e->getMessage());
    }
    // Stay on same page with filters
    $redirect_url = "marks.php?class=" . urlencode($_POST['class']) . "&section=" . urlencode($_POST['section']) . "&exam_id=" . $_POST['exam_id'];
    header("Location: $redirect_url");
    exit;
}

// --- FETCH DATA FOR FILTERS ---
$classes_list = [
    'Intermediate' => ['FSc Pre-Medical', 'FSc Pre-Engineering', 'ICS', 'I.Com', 'FA', 'Taleem-ul-Islam'],
    'Degree' => ['ADP Arts', 'ADP Science', 'BSCS', 'BS IT', 'BS Zoology', 'BS Mathematics', 'BS Urdu', 'BS Chemistry'],
    'NAVTTC' => ['CCA', 'Web Development', 'Graphic Designing', 'Digital Marketing', 'UX/UI Design', 'AI', 'Vibe Coding']
];
$sections_list = ['A', 'B', 'C', 'D', 'Morning', 'Evening', 'Weekend', 'Batch 1', 'Batch 2', 'Batch 3', 'Batch 4'];

// Fetch available exams from schedule
$exams = $db->query("SELECT id, exam_title, subject, class, section, total_marks FROM exam_schedule ORDER BY exam_date DESC")->fetchAll();

// --- FILTER LOGIC ---
$selected_class = $_GET['class'] ?? '';
$selected_section = $_GET['section'] ?? '';
$selected_exam_id = $_GET['exam_id'] ?? '';

$students = [];
$exam_info = null;

if ($selected_class && $selected_section && $selected_exam_id) {
    // Get exam details
    $stmt = $db->prepare("SELECT * FROM exam_schedule WHERE id = ?");
    $stmt->execute([$selected_exam_id]);
    $exam_info = $stmt->fetch();

    if ($exam_info) {
        // Fetch students and their existing marks
        $sql = "SELECT s.id, s.student_id as roll_no, CONCAT(s.first_name, ' ', s.last_name) as full_name, 
                       em.obtained_marks, em.grade, em.remarks
                FROM students s
                LEFT JOIN exam_marks em ON s.id = em.student_id AND em.exam_schedule_id = ?
                WHERE s.class = ? AND s.section = ?
                ORDER BY s.first_name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$selected_exam_id, $selected_class, $selected_section]);
        $students = $stmt->fetchAll();
    }
}

$page_title = "Marks Entry";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <a href="index.php" class="btn btn-sm mb-3 back-btn">
                <i class="fas fa-arrow-left"></i> Back to Examination
            </a>
            <h2 class="page-title mb-0"><i class="fas fa-edit me-2" style="color: var(--teal);"></i>Marks Entry System</h2>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form action="" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Select Class</label>
                    <select name="class" class="form-select" required>
                        <option value="">Choose Class...</option>
                        <?php foreach ($classes_list as $group => $list): ?>
                            <optgroup label="<?= $group ?>">
                                <?php foreach ($list as $c): ?>
                                    <option value="<?= $c ?>" <?= $selected_class == $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">Select Section</label>
                    <select name="section" class="form-select" required>
                        <option value="">Choose...</option>
                        <?php foreach ($sections_list as $s): ?>
                            <option value="<?= $s ?>" <?= $selected_section == $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold small">Select Exam / Subject</label>
                    <select name="exam_id" class="form-select" required>
                        <option value="">Choose Exam...</option>
                        <?php foreach ($exams as $ex): ?>
                            <option value="<?= $ex['id'] ?>" <?= $selected_exam_id == $ex['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['exam_title']) ?> - <?= htmlspecialchars($ex['subject']) ?> (<?= $ex['class'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Filter Students
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($exam_info && !empty($students)): ?>
    <form action="" method="POST">
        <input type="hidden" name="action" value="save_marks">
        <input type="hidden" name="exam_id" value="<?= $exam_info['id'] ?>">
        <input type="hidden" name="subject" value="<?= $exam_info['subject'] ?>">
        <input type="hidden" name="class" value="<?= $selected_class ?>">
        <input type="hidden" name="section" value="<?= $selected_section ?>">
        <input type="hidden" name="total_marks" value="<?= $exam_info['total_marks'] ?>">

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-navy text-white p-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Students for: <?= htmlspecialchars($exam_info['exam_title']) ?> - <?= htmlspecialchars($exam_info['subject']) ?>
                </h5>
                <span class="badge bg-teal">Total Marks: <?= $exam_info['total_marks'] ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-uppercase small fw-bold">
                            <tr>
                                <th style="width: 150px;">Roll No</th>
                                <th>Student Name</th>
                                <th style="width: 150px;">Obtained Marks</th>
                                <th style="width: 100px;">Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><span class="badge bg-light text-navy"><?= $student['roll_no'] ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></td>
                                <td>
                                    <input type="number" name="marks[<?= $student['id'] ?>]" 
                                           class="form-control form-control-sm marks-input" 
                                           value="<?= $student['obtained_marks'] ?>" 
                                           max="<?= $exam_info['total_marks'] ?>" min="0" 
                                           onchange="calculateGrade(this, <?= $exam_info['total_marks'] ?>)">
                                </td>
                                <td>
                                    <span class="badge grade-badge <?= $student['grade'] == 'F' ? 'bg-danger' : 'bg-success' ?>" id="grade_<?= $student['id'] ?>">
                                        <?= $student['grade'] ?: '-' ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?= $student['id'] ?>]" 
                                           class="form-control form-control-sm" 
                                           value="<?= htmlspecialchars($student['remarks']) ?>" 
                                           placeholder="Notes...">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 text-end">
                <button type="submit" class="btn btn-success px-5 py-2 fw-bold shadow-sm">
                    <i class="fas fa-save me-2"></i>Save All Marks
                </button>
            </div>
        </div>
    </form>
    <?php elseif ($selected_class): ?>
    <div class="alert alert-info text-center p-5 rounded-4 border-0 shadow-sm">
        <i class="fas fa-info-circle fa-3x mb-3"></i>
        <h4>No Students Found</h4>
        <p>No students were found matching the selected class and section, or no exam is scheduled for them.</p>
    </div>
    <?php endif; ?>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
    .bg-teal { background-color: var(--teal) !important; }
    .back-btn {
        background: rgba(78,194,181,.1);
        border: 1px solid rgba(78,194,181,.3);
        color: #4ec2b5;
        border-radius: 99px;
        padding: 6px 18px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .page-title { font-family: 'Playfair Display', serif; font-weight: 700; color: var(--navy); }
    .btn-primary { background-color: var(--teal); border-color: var(--teal); color: var(--navy); font-weight: 600; }
    .btn-primary:hover { background-color: #3da89c; border-color: #3da89c; color: white; }
    .marks-input { font-weight: bold; text-align: center; color: var(--navy); }
    .grade-badge { min-width: 40px; }
</style>

<script>
function calculateGrade(input, total) {
    const obtained = parseFloat(input.value);
    const row = input.closest('tr');
    const gradeSpan = row.querySelector('.grade-badge');
    
    if (isNaN(obtained) || obtained < 0) {
        gradeSpan.innerText = '-';
        gradeSpan.className = 'badge grade-badge bg-light text-dark';
        return;
    }

    const percentage = (obtained / total) * 100;
    let grade = 'F';
    let bg = 'bg-danger';

    if (percentage >= 90) { grade = 'A+'; bg = 'bg-success'; }
    else if (percentage >= 80) { grade = 'A'; bg = 'bg-success'; }
    else if (percentage >= 70) { grade = 'B'; bg = 'bg-success'; }
    else if (percentage >= 60) { grade = 'C'; bg = 'bg-warning text-dark'; }
    else if (percentage >= 50) { grade = 'D'; bg = 'bg-info text-dark'; }
    else { grade = 'F'; bg = 'bg-danger'; }

    gradeSpan.innerText = grade;
    gradeSpan.className = 'badge grade-badge ' + bg;
}
</script>

<?php include '../../includes/footer.php'; ?>
