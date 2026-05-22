<?php
/**
 * File: modules/examination/results.php
 * Examination Results & Report Cards for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$db = (new Database())->getConnection();

// --- FETCH DATA FOR FILTERS ---
$classes_list = [
    'Intermediate' => ['FSc Pre-Medical', 'FSc Pre-Engineering', 'ICS', 'I.Com', 'FA', 'Taleem-ul-Islam'],
    'Degree' => ['ADP Arts', 'ADP Science', 'BSCS', 'BS IT', 'BS Zoology', 'BS Mathematics', 'BS Urdu', 'BS Chemistry'],
    'NAVTTC' => ['CCA', 'Web Development', 'Graphic Designing', 'Digital Marketing', 'UX/UI Design', 'AI', 'Vibe Coding']
];
$sections_list = ['A', 'B', 'C', 'D', 'Morning', 'Evening', 'Weekend', 'Batch 1', 'Batch 2', 'Batch 3', 'Batch 4'];

// Fetch unique Exam Titles from schedule
$exam_titles = $db->query("SELECT DISTINCT exam_title FROM exam_schedule ORDER BY created_at DESC")->fetchAll(PDO::FETCH_COLUMN);

// --- FILTER LOGIC ---
$selected_class = $_GET['class'] ?? '';
$selected_section = $_GET['section'] ?? '';
$selected_exam = $_GET['exam'] ?? '';

$results_data = [];
$subjects = [];

if ($selected_class && $selected_section && $selected_exam) {
    // 1. Get all subjects scheduled for this exam/class/section
    $stmt = $db->prepare("SELECT id, subject, total_marks FROM exam_schedule WHERE exam_title = ? AND class = ? AND section = ?");
    $stmt->execute([$selected_exam, $selected_class, $selected_section]);
    $subjects = $stmt->fetchAll();
    
    if (!empty($subjects)) {
        $schedule_ids = array_column($subjects, 'id');
        $in_placeholder = implode(',', array_fill(0, count($schedule_ids), '?'));

        // 2. Fetch all students in this class/section
        $stmt = $db->prepare("SELECT id, student_id as roll_no, CONCAT(first_name, ' ', last_name) as full_name, campus FROM students WHERE class = ? AND section = ? ORDER BY first_name ASC");
        $stmt->execute([$selected_class, $selected_section]);
        $students = $stmt->fetchAll();

        // 3. Fetch marks for these students and subjects
        $marks_map = [];
        $sql = "SELECT student_id, exam_schedule_id, obtained_marks, grade FROM exam_marks WHERE exam_schedule_id IN ($in_placeholder)";
        $stmt = $db->prepare($sql);
        $stmt->execute($schedule_ids);
        while($m = $stmt->fetch()) {
            $marks_map[$m['student_id']][$m['exam_schedule_id']] = $m;
        }

        // 4. Process Results
        foreach ($students as $student) {
            $row = [
                'student' => $student,
                'marks' => [],
                'grand_total' => 0,
                'total_obtained' => 0,
                'status' => 'PASS',
                'overall_grade' => '-'
            ];

            foreach ($subjects as $subj) {
                $m = $marks_map[$student['id']][$subj['id']] ?? null;
                $obt = $m ? $m['obtained_marks'] : 0;
                $row['marks'][$subj['id']] = $obt;
                $row['grand_total'] += $subj['total_marks'];
                $row['total_obtained'] += $obt;
                
                if (!$m || $m['grade'] == 'F') {
                    $row['status'] = 'FAIL';
                }
            }

            if ($row['grand_total'] > 0) {
                $perc = ($row['total_obtained'] / $row['grand_total']) * 100;
                $row['percentage'] = round($perc, 2);
                
                // Overall Grade
                if ($perc >= 90) $row['overall_grade'] = 'A+';
                elseif ($perc >= 80) $row['overall_grade'] = 'A';
                elseif ($perc >= 70) $row['overall_grade'] = 'B';
                elseif ($perc >= 60) $row['overall_grade'] = 'C';
                elseif ($perc >= 50) $row['overall_grade'] = 'D';
                else $row['overall_grade'] = 'F';
            } else {
                $row['percentage'] = 0;
            }

            $results_data[] = $row;
        }

        // 5. Calculate Ranks
        usort($results_data, function($a, $b) {
            return $b['total_obtained'] <=> $a['total_obtained'];
        });

        $rank = 1;
        for ($i = 0; $i < count($results_data); $i++) {
            if ($i > 0 && $results_data[$i]['total_obtained'] < $results_data[$i-1]['total_obtained']) {
                $rank = $i + 1;
            }
            $results_data[$i]['rank'] = $rank;
        }
    }
}

$page_title = "Examination Results";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <a href="index.php" class="btn btn-sm mb-3 back-btn">
                <i class="fas fa-arrow-left"></i> Back to Examination
            </a>
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="page-title mb-0"><i class="fas fa-award me-2" style="color: var(--teal);"></i>Results & Report Cards</h2>
                <?php if (!empty($results_data)): ?>
                <button class="btn btn-outline-navy" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print All Results
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
        <div class="card-body p-4">
            <form action="" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Class</label>
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
                    <label class="form-label fw-bold small">Section</label>
                    <select name="section" class="form-select" required>
                        <option value="">Choose...</option>
                        <?php foreach ($sections_list as $s): ?>
                            <option value="<?= $s ?>" <?= $selected_section == $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small">Examination</label>
                    <select name="exam" class="form-select" required>
                        <option value="">Select Exam Title...</option>
                        <?php foreach ($exam_titles as $title): ?>
                            <option value="<?= $title ?>" <?= $selected_exam == $title ? 'selected' : '' ?>><?= htmlspecialchars($title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-file-invoice me-2"></i>Generate Results
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($results_data)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-navy text-white p-3">
            <h5 class="mb-0 text-center text-uppercase"><?= htmlspecialchars($selected_exam) ?> - Result Sheet (<?= $selected_class ?> - <?= $selected_section ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="bg-light text-uppercase small fw-bold">
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <?php foreach ($subjects as $subj): ?>
                                <th><?= htmlspecialchars($subj['subject']) ?></th>
                            <?php endforeach; ?>
                            <th class="bg-navy-light">Total</th>
                            <th class="bg-navy-light">%</th>
                            <th class="bg-navy-light">Grade</th>
                            <th class="bg-navy-light">Status</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results_data as $row): ?>
                        <tr>
                            <td class="fw-bold"><?= $row['rank'] ?></td>
                            <td class="text-start">
                                <div class="fw-bold"><?= htmlspecialchars($row['student']['full_name']) ?></div>
                                <small class="text-muted"><?= $row['student']['roll_no'] ?></small>
                            </td>
                            <?php foreach ($subjects as $subj): ?>
                                <td><?= $row['marks'][$subj['id']] ?></td>
                            <?php endforeach; ?>
                            <td class="fw-bold bg-light"><?= $row['total_obtained'] ?> / <?= $row['grand_total'] ?></td>
                            <td class="bg-light"><?= $row['percentage'] ?>%</td>
                            <td class="bg-light"><span class="badge bg-navy text-navy bg-opacity-10"><?= $row['overall_grade'] ?></span></td>
                            <td class="bg-light">
                                <span class="badge <?= $row['status'] == 'PASS' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <button class="btn btn-sm btn-outline-primary" onclick="printReportCard(<?= htmlspecialchars(json_encode($row)) ?>, '<?= addslashes($selected_exam) ?>')">
                                    <i class="fas fa-print me-1"></i>Report Card
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Report Card Template (Hidden) -->
    <div id="reportCardTemplate" style="display:none;">
        <div class="report-card p-5 border shadow-sm mx-auto" style="max-width: 800px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #000; background: #fff;">
            <div class="text-center mb-4 pb-3 border-bottom">
                <h2 class="mb-1" style="color:#0f2d48;">Quaid-e-Azam Group of Colleges</h2>
                <h5 class="text-uppercase" id="rc_campus">[Campus Name]</h5>
                <h4 class="mt-3 badge bg-dark p-2 fs-6 text-uppercase" id="rc_exam_title">Student Report Card — [Exam Title]</h4>
            </div>
            
            <div class="row mb-4">
                <div class="col-6">
                    <p class="mb-1"><strong>Student Name:</strong> <span id="rc_name"></span></p>
                    <p class="mb-1"><strong>Father Name:</strong> -</p>
                </div>
                <div class="col-6 text-end">
                    <p class="mb-1"><strong>Roll Number:</strong> <span id="rc_roll"></span></p>
                    <p class="mb-1"><strong>Class:</strong> <span id="rc_class"></span> (Section: <span id="rc_section"></span>)</p>
                </div>
            </div>

            <table class="table table-bordered mb-4">
                <thead class="bg-light">
                    <tr>
                        <th>Subject</th>
                        <th class="text-center">Total Marks</th>
                        <th class="text-center">Obtained</th>
                        <th class="text-center">Grade</th>
                    </tr>
                </thead>
                <tbody id="rc_marks_body">
                </tbody>
            </table>

            <div class="row g-3">
                <div class="col-md-3">
                    <div class="p-3 border rounded text-center">
                        <small class="text-muted d-block uppercase small">Total Obtained</small>
                        <h4 class="mb-0" id="rc_total">0 / 0</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded text-center">
                        <small class="text-muted d-block uppercase small">Percentage</small>
                        <h4 class="mb-0" id="rc_perc">0%</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded text-center">
                        <small class="text-muted d-block uppercase small">Final Grade</small>
                        <h4 class="mb-0" id="rc_grade">F</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded text-center">
                        <small class="text-muted d-block uppercase small">Status</small>
                        <h4 class="mb-0" id="rc_status">FAIL</h4>
                    </div>
                </div>
            </div>

            <div class="mt-5 d-flex justify-content-between align-items-end pt-5">
                <div class="text-center">
                    <p class="mb-0 fw-bold border-top border-dark pt-2 px-4">Class Teacher</p>
                </div>
                <div class="text-center">
                    <p class="mb-0 fw-bold border-top border-dark pt-2 px-4">Controller Exam</p>
                </div>
                <div class="text-center">
                    <p class="mb-0 fw-bold border-top border-dark pt-2 px-4">Principal<br><small class="fw-normal">Mr. Zafar Iqbal</small></p>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($selected_class): ?>
    <div class="alert alert-info text-center p-5 rounded-4 border-0 shadow-sm">
        <i class="fas fa-search fa-3x mb-3 text-muted"></i>
        <h4>No results generated yet.</h4>
        <p>Please ensure marks are entered for the selected exam, class, and section.</p>
    </div>
    <?php endif; ?>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
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
    .bg-navy-light { background-color: #f1f3f5 !important; }
    
    @media print {
        .no-print, .back-btn, .btn, .dataTables_wrapper { display: none !important; }
        .container-fluid { padding: 0 !important; margin: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header { background: #000 !important; color: #fff !important; -webkit-print-color-adjust: exact; }
    }
</style>

<script>
function printReportCard(row, examTitle) {
    const student = row.student;
    const subjects = <?= json_encode($subjects) ?>;
    
    document.getElementById('rc_campus').innerText = student.campus + " Campus";
    document.getElementById('rc_exam_title').innerText = "Student Report Card — " + examTitle;
    document.getElementById('rc_name').innerText = student.full_name;
    document.getElementById('rc_roll').innerText = student.roll_no;
    document.getElementById('rc_class').innerText = "<?= $selected_class ?>";
    document.getElementById('rc_section').innerText = "<?= $selected_section ?>";
    
    let marksHtml = '';
    subjects.forEach(subj => {
        const obt = row.marks[subj.id] || 0;
        const perc = (obt / subj.total_marks) * 100;
        let grade = 'F';
        if (perc >= 90) grade = 'A+';
        else if (perc >= 80) grade = 'A';
        else if (perc >= 70) grade = 'B';
        else if (perc >= 60) grade = 'C';
        else if (perc >= 50) grade = 'D';

        marksHtml += `
            <tr>
                <td>${subj.subject}</td>
                <td class="text-center">${subj.total_marks}</td>
                <td class="text-center">${obt}</td>
                <td class="text-center fw-bold">${grade}</td>
            </tr>
        `;
    });
    
    document.getElementById('rc_marks_body').innerHTML = marksHtml;
    document.getElementById('rc_total').innerText = row.total_obtained + " / " + row.grand_total;
    document.getElementById('rc_perc').innerText = row.percentage + "%";
    document.getElementById('rc_grade').innerText = row.overall_grade;
    document.getElementById('rc_status').innerText = row.status;
    document.getElementById('rc_status').className = "mb-0 fw-bold " + (row.status == 'PASS' ? 'text-success' : 'text-danger');

    const content = document.getElementById('reportCardTemplate').innerHTML;
    const printWindow = window.open('', '', 'height=800,width=1000');
    printWindow.document.write('<html><head><title>Report Card - ' + student.full_name + '</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.print();
    }, 500);
}
</script>

<?php include '../../includes/footer.php'; ?>
