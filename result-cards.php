<?php
// File: result-cards.php
// EXTENSIBILITY NOTES:
// To add GPA system: add gpa column to result_cards,
//   update calculateGrade() to also return GPA
// To add subject-wise pass threshold: add pass_marks column
//   to subjects table and check in getStudentMarksForExam()
// To add SMS on publish: call sendResultSMS() after publishResultCards()
// To add PDF download: use existing PDF Generator module,
//   pass result-cards-print.php URL to it

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/result_card_functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
requireRole(['admin', 'owner', 'teacher']);

$database = new Database();
$conn = $database->getConnection();

try {
    $sqlPath = __DIR__ . '/database/result_cards.sql';
    if (is_file($sqlPath)) {
        $conn->exec(file_get_contents($sqlPath));
    }
    if (tableExists($conn, 'result_cards') && !columnExists($conn, 'result_cards', 'campus_id')) {
        $conn->exec("ALTER TABLE result_cards ADD COLUMN campus_id INT DEFAULT NULL AFTER class_id");
    }
} catch (Exception $e) {
    error_log('Result Cards Migration Error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        redirect('result-cards.php?msg=error');
    }

    $action = (string)($_POST['action'] ?? '');
    $msg = 'error';

    if ($action === 'generate_individual' || $action === 'generate_class') {
        $post = $_POST;
        $post['generate_type'] = $action === 'generate_individual' ? 'individual' : 'class';
        $validation = validateResultCardInput($post);
        if (!$validation['valid']) {
            $_SESSION['result_card_errors'] = $validation['errors'];
            redirect('result-cards.php?msg=error');
        }

        $examId = (int)$_POST['exam_id'];
        $classId = trim((string)$_POST['class_id']);
        $session = trim((string)$_POST['session_year']);
        $remarks = trim((string)($_POST['teacher_remarks'] ?? ''));

        if ($action === 'generate_individual') {
            $ok = generateResultCard($conn, (int)$_POST['student_id'], $examId, $classId, $session, $remarks);
            $msg = $ok ? 'generated' : 'error';
        } else {
            $result = generateClassResultCards($conn, $classId, $examId, $session, $_POST['campus_id'] ?? null, $remarks);
            $msg = $result['success'] > 0 ? 'generated_bulk' : 'error';
        }
        redirect('result-cards.php?msg=' . $msg);
    }

    if ($action === 'publish') {
        $ok = publishResultCards($conn, (int)($_POST['exam_id'] ?? 0), trim((string)($_POST['class_id'] ?? '')));
        redirect('result-cards.php?msg=' . ($ok ? 'published' : 'error'));
    }

    if ($action === 'delete') {
        $ok = deleteResultCard($conn, (int)($_POST['result_card_id'] ?? 0));
        redirect('result-cards.php?msg=' . ($ok ? 'deleted' : 'protected'));
    }

    if ($action === 'recalculate') {
        $card = getResultCardForPrint($conn, (int)($_POST['student_id'] ?? 0), (int)($_POST['exam_id'] ?? 0));
        $ok = $card ? generateResultCard($conn, (int)$_POST['student_id'], (int)$_POST['exam_id'], $card['class_id'], $card['session_year'], $card['teacher_remarks']) : false;
        redirect('result-cards.php?msg=' . ($ok ? 'recalculated' : 'error'));
    }

    if ($action === 'recalculate_positions') {
        $count = recalculateClassPositions($conn, (int)($_POST['exam_id'] ?? 0), trim((string)($_POST['class_id'] ?? '')));
        redirect('result-cards.php?msg=' . ($count >= 0 ? 'positions' : 'error'));
    }
}

$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'class_id' => trim((string)($_GET['class_id'] ?? '')),
    'exam_id' => (int)($_GET['exam_id'] ?? 0),
    'session_year' => trim((string)($_GET['session_year'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'campus_id' => trim((string)($_GET['campus_id'] ?? '')),
];

$exams = getExamsForResultCard($conn);
$classes = getClassesForResultCard($conn);
$campuses = getCampusesForResultCard($conn);
$showCampusFilter = count($campuses) > 1;
$summary = getResultCardSummary($conn, $filters['campus_id'] ?: null);
$result_cards = getResultCardList($conn, $filters);

$msg = (string)($_GET['msg'] ?? '');
$errors = $_SESSION['result_card_errors'] ?? [];
unset($_SESSION['result_card_errors']);

$page_title = 'Result Cards';
include __DIR__ . '/includes/header.php';

$adminName = htmlspecialchars((string)($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
$preClass = trim((string)($_GET['class_id'] ?? ''));
$preExam = (int)($_GET['exam_id'] ?? 0);
$preStudent = (int)($_GET['student_id'] ?? 0);
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Result Cards</h2>
            <p class="text-muted mb-0">Generate, manage and print student academic result cards</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#resultGuide">
                <i class="fas fa-book me-2"></i>User Guide
            </button>
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                    <i class="fas fa-user text-secondary"></i>
                </div>
                <div class="small">
                    <div class="fw-semibold"><?= $adminName ?></div>
                    <div class="text-muted">Admin</div>
                </div>
            </div>
        </div>
    </div>

    <div class="collapse mb-4" id="resultGuide">
        <div class="card card-body bg-light border-0 shadow-sm small text-muted">
            Generate cards after marks are entered in Examination > Marks Entry. Individual generation creates one card; class generation creates or updates one card per student for the selected exam group.
        </div>
    </div>

    <?php if ($msg === 'generated'): ?>
        <div class="alert alert-success">Result card generated successfully.</div>
    <?php elseif ($msg === 'generated_bulk'): ?>
        <div class="alert alert-success">Result cards generated for entire class.</div>
    <?php elseif ($msg === 'published'): ?>
        <div class="alert alert-success">Result cards published successfully.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-success">Result card deleted.</div>
    <?php elseif ($msg === 'protected'): ?>
        <div class="alert alert-warning">Published result cards cannot be deleted without an admin override.</div>
    <?php elseif ($msg === 'recalculated'): ?>
        <div class="alert alert-success">Result card recalculated successfully.</div>
    <?php elseif ($msg === 'positions'): ?>
        <div class="alert alert-success">Class positions recalculated successfully.</div>
    <?php elseif ($msg === 'error'): ?>
        <div class="alert alert-danger">
            Something went wrong. Please try again.
            <?php if ($errors): ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Total Cards', $summary['total_cards'], 'file-alt', 'dark'],
            ['Published', $summary['published'], 'check-circle', 'success'],
            ['Draft', $summary['draft'], 'clock', 'warning'],
            ['Classes Covered', $summary['classes_covered'], 'school', 'primary'],
            ['Exams Covered', $summary['exams_covered'], 'award', 'purple'],
        ];
        ?>
        <?php foreach ($cards as $card): ?>
            <div class="col-md col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1"><?= htmlspecialchars($card[0]) ?></p>
                            <h4 class="fw-bold mb-0"><?= number_format((int)$card[1]) ?></h4>
                        </div>
                        <div class="rounded p-2 <?= $card[3] === 'purple' ? 'text-white' : 'text-' . $card[3] ?>" style="<?= $card[3] === 'purple' ? 'background:#6f42c1' : '' ?>">
                            <i class="fas fa-<?= htmlspecialchars($card[2]) ?> fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">Generate Result Cards</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="resultCardForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" id="actionInput" value="generate_individual">

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Generate Type</label>
                        <div class="d-flex gap-3">
                            <label class="form-check">
                                <input type="radio" class="form-check-input" name="generate_type_ui" value="individual" checked> Individual Student
                            </label>
                            <label class="form-check">
                                <input type="radio" class="form-check-input" name="generate_type_ui" value="class"> Entire Class
                            </label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Select Exam</label>
                        <select name="exam_id" id="examSelect" class="form-select" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?= (int)$exam['id'] ?>" data-class="<?= htmlspecialchars((string)$exam['class'], ENT_QUOTES, 'UTF-8') ?>" <?= $preExam === (int)$exam['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($exam['exam_title'] . ' - ' . $exam['class'] . ' (' . $exam['section'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Session/Year</label>
                        <input type="text" name="session_year" class="form-control" value="<?= htmlspecialchars(getCurrentAcademicYear(), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <?php if ($showCampusFilter): ?>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Campus</label>
                            <select name="campus_id" class="form-select">
                                <option value="">All Campuses</option>
                                <?php foreach ($campuses as $campus): ?>
                                    <option value="<?= (int)$campus['id'] ?>"><?= htmlspecialchars($campus['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Class</label>
                        <select name="class_id" id="classSelect" class="form-select" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= htmlspecialchars((string)$class['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $preClass === (string)$class['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$class['class_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4" id="studentGroup">
                        <label class="form-label fw-semibold">Student</label>
                        <select name="student_id" id="studentSelect" class="form-select" data-preselected="<?= $preStudent ?>">
                            <option value="">Select class first</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Teacher Remarks</label>
                        <textarea name="teacher_remarks" class="form-control" rows="2" placeholder="Optional remarks"></textarea>
                    </div>
                </div>

                <div class="card bg-light border-0 mb-3 d-none" id="marksPreviewCard">
                    <div class="card-body">
                        <div id="classCount" class="small text-muted mb-2"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr><th>Subject</th><th>Total</th><th>Obtained</th><th>%</th><th>Grade</th><th>Pass?</th></tr>
                                </thead>
                                <tbody id="marksPreviewBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="fas fa-award me-2"></i>Generate Result Card(s)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Student name or roll">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Class</label>
                    <select name="class_id" class="form-select form-select-sm">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= htmlspecialchars((string)$class['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $filters['class_id'] === (string)$class['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$class['class_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Exam</label>
                    <select name="exam_id" class="form-select form-select-sm">
                        <option value="">All Exams</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?= (int)$exam['id'] ?>" <?= (int)$filters['exam_id'] === (int)$exam['id'] ? 'selected' : '' ?>><?= htmlspecialchars($exam['exam_title'] . ' - ' . $exam['class']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">All Result Cards</h5>
            <?php if ($filters['class_id'] && $filters['exam_id']): ?>
                <form method="POST" class="d-inline">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="recalculate_positions">
                    <input type="hidden" name="class_id" value="<?= htmlspecialchars($filters['class_id'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="exam_id" value="<?= (int)$filters['exam_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Recalculate All Positions</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Exam</th>
                            <th>Session</th>
                            <th>%</th>
                            <th>Grade</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$result_cards): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <div><i class="fas fa-award fa-3x mb-3"></i></div>
                                    <div class="fw-bold">No result cards generated yet</div>
                                    <div class="small">Use the form above to generate result cards for students or entire classes.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($result_cards as $index => $card): ?>
                            <tr>
                                <td class="ps-4"><?= $index + 1 ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($card['student_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($card['student_code'] ?: $card['roll_number'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td><?= htmlspecialchars($card['class_id'] . ($card['section'] ? ' - ' . $card['section'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($card['exam_title'] ?: ('Exam #' . $card['exam_id']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($card['session_year'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fw-bold"><?= number_format((float)$card['percentage'], 2) ?>%</td>
                                <td><span class="badge bg-light text-dark"><?= htmlspecialchars($card['grade'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= $card['position_in_class'] ? (int)$card['position_in_class'] : '-' ?></td>
                                <td>
                                    <span class="badge <?= $card['status'] === 'published' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars(ucfirst($card['status']), ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="result-cards-print.php?student_id=<?= (int)$card['student_pk'] ?>&exam_id=<?= (int)$card['exam_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">Print</a>
                                    <form method="POST" class="d-inline">
                                        <?= csrfTokenInput() ?>
                                        <input type="hidden" name="action" value="recalculate">
                                        <input type="hidden" name="student_id" value="<?= (int)$card['student_pk'] ?>">
                                        <input type="hidden" name="exam_id" value="<?= (int)$card['exam_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Recalculate</button>
                                    </form>
                                    <?php if ($card['status'] === 'draft'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="action" value="publish">
                                            <input type="hidden" name="exam_id" value="<?= (int)$card['exam_id'] ?>">
                                            <input type="hidden" name="class_id" value="<?= htmlspecialchars($card['class_id'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-sm btn-success">Publish</button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this draft result card?');">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="result_card_id" value="<?= (int)$card['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
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

<script>
$(function () {
    function setMode(mode) {
        $('#studentGroup').toggleClass('d-none', mode === 'class');
        $('#actionInput').val(mode === 'class' ? 'generate_class' : 'generate_individual');
        $('#marksPreviewCard').addClass('d-none');
    }

    $('input[name="generate_type_ui"]').on('change', function () {
        setMode(this.value);
        if (this.value === 'class') loadClassCount();
    });

    $('#examSelect').on('change', function () {
        const cls = $('#examSelect option:selected').data('class');
        if (cls && !$('#classSelect').val()) $('#classSelect').val(cls);
        $('#classSelect').trigger('change');
    });

    $('#classSelect').on('change', function () {
        const classId = $(this).val();
        const mode = $('input[name="generate_type_ui"]:checked').val();
        if (!classId) return;
        if (mode === 'individual') loadStudents(classId);
        else loadClassCount();
    });

    $('#studentSelect, #examSelect').on('change', function () {
        if ($('input[name="generate_type_ui"]:checked').val() === 'individual') {
            loadMarksPreview();
        } else {
            loadClassCount();
        }
    });

    function loadStudents(classId) {
        $('#studentSelect').html('<option value="">Loading...</option>');
        $.getJSON('result-cards-ajax.php', { action: 'get_students_by_class', class_id: classId }, function (rows) {
            let html = '<option value="">Select Student</option>';
            const pre = $('#studentSelect').data('preselected').toString();
            rows.forEach(function (row) {
                const selected = pre === row.id.toString() ? ' selected' : '';
                html += `<option value="${row.id}"${selected}>${row.name} (${row.roll_no || row.student_id || row.id})</option>`;
            });
            $('#studentSelect').html(html);
            if (pre !== '0') $('#studentSelect').trigger('change');
        });
    }

    function loadMarksPreview() {
        const studentId = $('#studentSelect').val();
        const examId = $('#examSelect').val();
        if (!studentId || !examId) return;
        $('#marksPreviewCard').removeClass('d-none');
        $('#classCount').text('');
        $('#marksPreviewBody').html('<tr><td colspan="6" class="text-center text-muted">Loading marks...</td></tr>');
        $.getJSON('result-cards-ajax.php', { action: 'preview_marks', student_id: studentId, exam_id: examId }, function (rows) {
            if (!rows.length) {
                $('#marksPreviewBody').html('<tr><td colspan="6" class="text-center text-muted">No marks found for this student and exam.</td></tr>');
                return;
            }
            let html = '';
            rows.forEach(function (row) {
                html += `<tr><td>${row.subject_name}</td><td>${row.total_marks}</td><td>${row.obtained_marks}</td><td>${row.percentage}%</td><td>${row.grade}</td><td>${row.pass_fail}</td></tr>`;
            });
            $('#marksPreviewBody').html(html);
        });
    }

    function loadClassCount() {
        const classId = $('#classSelect').val();
        const examId = $('#examSelect').val();
        if (!classId || !examId) return;
        $('#marksPreviewCard').removeClass('d-none');
        $('#marksPreviewBody').html('');
        $.getJSON('result-cards-ajax.php', { action: 'get_class_students_count', class_id: classId, exam_id: examId }, function (row) {
            $('#classCount').text(row.message || '0 students found.');
        });
    }

    setMode($('input[name="generate_type_ui"]:checked').val());
    if ($('#classSelect').val()) $('#classSelect').trigger('change');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
