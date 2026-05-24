<?php
// File: dashboard.php - Role-based ERP Dashboard
// INTEGRATION DEPENDENCIES:
// Reads from: students, staff, fee_collections, approval_requests, income, expenses, campuses, fee_structure, exam_schedule
// Writes to: approval_requests
// Shared functions: includes/shared_functions.php
// AJAX: ajax/shared-ajax.php
// JS: assets/js/shared.js
require_once 'config/db.php';
require_once 'includes/shared_functions.php';

if (!isLoggedIn()) {
    header("Location: modules/auth/login.php");
    exit();
}

enforcePasswordChange();

$database = new Database();
$db = $database->getConnection();
ensureApprovalSystem($db);
ensureAdmissionApplicationsTable($db);

$role = getUserRole();
$userId = getUserId();
$username = $_SESSION['username'] ?? 'User';
$isAdminRole = in_array($role, ['admin', 'owner'], true);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getStat(PDO $db, $sql, $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        return 0;
    }
}

function safeSumColumn(PDO $db, $table, array $columns, $where = '1=1') {
    if (!tableExists($db, $table)) {
        return 0;
    }
    $column = firstExistingColumn($db, $table, $columns);
    if (!$column) {
        return 0;
    }
    return getStat($db, "SELECT COALESCE(SUM(`$column`), 0) FROM `$table` WHERE $where");
}

function fetchRows(PDO $db, $sql, $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function formatRequestLabel($module, $action) {
    return ucwords(str_replace('_', ' ', $module . ' ' . $action));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_action'])) {
    try {
        requireCsrfToken();
        $action = $_POST['dashboard_action'];

        if (!$isAdminRole && in_array($action, ['approve_request', 'reject_request'], true)) {
            throw new Exception('Only admin can approve or reject requests.');
        }

        if ($action === 'approve_request' || $action === 'reject_request') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $remarks = trim($_POST['admin_remarks'] ?? '');
            $stmt = $db->prepare("SELECT * FROM approval_requests WHERE id = ? AND status = 'pending' LIMIT 1");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Approval request not found or already reviewed.');
            }

            if ($action === 'approve_request') {
                $db->beginTransaction();
                applyApprovalRequest($db, $request);
                $stmt = $db->prepare("UPDATE approval_requests SET status = 'approved', admin_remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$remarks, $userId, $requestId]);
                $db->commit();
                setFlashMessage('success', 'Request approved successfully.');
            } else {
                $stmt = $db->prepare("UPDATE approval_requests SET status = 'rejected', admin_remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$remarks, $userId, $requestId]);
                setFlashMessage('success', 'Request rejected.');
            }

            redirect('dashboard.php');
        }

        if ($role === 'teacher' && in_array($action, ['teacher_homework', 'teacher_diary', 'teacher_lms', 'teacher_attendance'], true)) {
            if ($action === 'teacher_homework' || $action === 'teacher_diary') {
                $module = $action === 'teacher_homework' ? 'homework' : 'daily_diary';
                createApprovalRequest($db, $module, 'create', [
                    'diary_date' => $_POST['diary_date'] ?? date('Y-m-d'),
                    'due_date' => $_POST['due_date'] ?: null,
                    'class' => sanitizeInput($_POST['class'] ?? ''),
                    'section' => sanitizeInput($_POST['section'] ?? ''),
                    'subject' => sanitizeInput($_POST['subject'] ?? ''),
                    'title' => sanitizeInput($_POST['title'] ?? ''),
                    'homework' => trim($_POST['details'] ?? ''),
                    'instructions' => trim($_POST['instructions'] ?? '')
                ]);
                setFlashMessage('success', 'Submitted for admin approval.');
            }

            if ($action === 'teacher_lms') {
                createApprovalRequest($db, 'lms', 'upload_material', [
                    'title' => sanitizeInput($_POST['title'] ?? ''),
                    'course_name' => sanitizeInput($_POST['course_name'] ?? ''),
                    'class' => sanitizeInput($_POST['class'] ?? ''),
                    'subject' => sanitizeInput($_POST['subject'] ?? ''),
                    'campus' => sanitizeInput($_POST['campus'] ?? ($_SESSION['user_campus'] ?? '')),
                    'description' => trim($_POST['description'] ?? ''),
                    'original_file_name' => 'dashboard-note.txt',
                    'stored_file_name' => 'pending-dashboard-note.txt',
                    'file_path' => '',
                    'file_type' => 'note',
                    'file_size' => 0
                ]);
                setFlashMessage('success', 'LMS material request submitted for approval.');
            }

            if ($action === 'teacher_attendance') {
                createApprovalRequest($db, 'attendance', 'mark_attendance', [
                    'class' => sanitizeInput($_POST['class'] ?? ''),
                    'section' => sanitizeInput($_POST['section'] ?? ''),
                    'date' => $_POST['attendance_date'] ?? date('Y-m-d'),
                    'attendance' => []
                ]);
                setFlashMessage('success', 'Attendance action submitted for approval. Use the Attendance module for full student marking.');
            }

            redirect('dashboard.php');
        }

        if ($role === 'student' && $action === 'student_complaint') {
            createApprovalRequest($db, 'complaints', 'create', [
                'complaint_number' => 'CMP-' . date('Ymd') . '-' . random_int(1000, 9999),
                'subject' => sanitizeInput($_POST['subject'] ?? ''),
                'complainant_name' => $username
            ]);
            setFlashMessage('success', 'Complaint submitted for admin review.');
            redirect('dashboard.php');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('dashboard.php');
    }
}

$pendingRequests = fetchRows($db, "
    SELECT ar.*, COALESCE(u.full_name, u.username, 'Unknown User') AS requester_name
    FROM approval_requests ar
    LEFT JOIN users u ON u.id = ar.requested_by
    WHERE ar.status = 'pending'
    ORDER BY ar.created_at DESC
    LIMIT 12
");

$myRequests = fetchRows($db, "
    SELECT * FROM approval_requests
    WHERE requested_by = ?
    ORDER BY created_at DESC
    LIMIT 8
", [$userId]);

$recentActivity = fetchRows($db, "
    SELECT ar.*, COALESCE(u.full_name, u.username, 'Unknown User') AS requester_name
    FROM approval_requests ar
    LEFT JOIN users u ON u.id = ar.requested_by
    ORDER BY ar.created_at DESC
    LIMIT 8
");

$teacherRoleColumn = tableExists($db, 'staff') && columnExists($db, 'staff', 'role') ? 'role' : null;
$teacherCountSql = $teacherRoleColumn
    ? "SELECT COUNT(*) FROM staff WHERE LOWER(COALESCE(`$teacherRoleColumn`, '')) LIKE '%teacher%' AND COALESCE(is_active, 1) = 1"
    : "SELECT COUNT(*) FROM staff WHERE COALESCE(is_active, 1) = 1";

$adminStats = [
    'students' => tableExists($db, 'students') ? getStat($db, "SELECT COUNT(*) FROM students") : 0,
    'teachers' => tableExists($db, 'staff') ? getStat($db, $teacherCountSql) : 0,
    'fees' => safeSumColumn($db, 'fee_collections', ['paid_amount', 'amount_paid', 'amount']),
    'complaints' => tableExists($db, 'complaints') ? getStat($db, "SELECT COUNT(*) FROM complaints") : 0,
    'lms_uploads' => tableExists($db, 'course_materials') ? getStat($db, "SELECT COUNT(*) FROM course_materials") : 0,
    'pending' => count($pendingRequests),
    'pending_admissions' => tableExists($db, 'admission_applications') ? getStat($db, "SELECT COUNT(*) FROM admission_applications WHERE status = 'pending'") : 0,
];

$setupChecklist = [];
if ($isAdminRole) {
    $schoolInfo = getSchoolInfo($db);
    $classesForSetup = getAllClasses($db);
    $classesMissingSections = 0;
    foreach ($classesForSetup as $classForSetup) {
        if (count(getSectionsByClass($db, $classForSetup['id'])) === 0) {
            $classesMissingSections++;
        }
    }
    $setupChecklist = [
        [
            'label' => 'School info configured',
            'done' => !empty($schoolInfo['name']),
            'url' => 'dashboard.php',
        ],
        [
            'label' => 'Active academic session available',
            'done' => (bool)getCurrentSessionYear($db),
            'url' => 'dashboard.php',
        ],
        [
            'label' => 'Campus records available',
            'done' => count(getAllCampuses($db)) > 0,
            'url' => 'dashboard.php',
        ],
        [
            'label' => 'At least one class added',
            'done' => count($classesForSetup) > 0,
            'url' => 'modules/student_profile/add.php',
        ],
        [
            'label' => 'Sections found for configured classes',
            'done' => count($classesForSetup) === 0 || $classesMissingSections === 0,
            'url' => 'modules/student_profile/add.php',
        ],
        [
            'label' => 'Fee heads configured',
            'done' => count(getAllFeeHeads($db)) > 0,
            'url' => 'modules/fee_management/structure.php',
        ],
        [
            'label' => 'Staff or teachers added',
            'done' => count(getAllStaff($db)) > 0,
            'url' => 'modules/hr/staff.php',
        ],
    ];
}

ensureFinanceTables($db);
syncFinancialModuleData($db);
$financialStats = [
    'income' => (float)getStat($db, "SELECT COALESCE(SUM(amount), 0) FROM income"),
    'expenses' => (float)getStat($db, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status IN ('approved','paid')"),
    'pending_expenses' => (float)getStat($db, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'pending'"),
    'approved_expenses' => (float)getStat($db, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'approved'"),
];
$financialStats['net'] = $financialStats['income'] - $financialStats['expenses'];

$financeCampusRows = fetchRows($db, "
    SELECT COALESCE(NULLIF(campus, ''), 'Unassigned') AS campus, COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE status IN ('approved','paid')
    GROUP BY COALESCE(NULLIF(campus, ''), 'Unassigned')
    ORDER BY total DESC
    LIMIT 4
");
$financeCategoryRows = fetchRows($db, "
    SELECT category, COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE status IN ('approved','paid')
    GROUP BY category
    ORDER BY total DESC
    LIMIT 4
");

$teacherStats = [
    'classes' => tableExists($db, 'timetable') ? getStat($db, "SELECT COUNT(DISTINCT class) FROM timetable WHERE teacher_id = ?", [$userId]) : 0,
    'students' => tableExists($db, 'students') ? getStat($db, "SELECT COUNT(*) FROM students WHERE COALESCE(status, 'Active') = 'Active'") : 0,
    'subjects' => tableExists($db, 'timetable') ? getStat($db, "SELECT COUNT(DISTINCT subject) FROM timetable WHERE teacher_id = ?", [$userId]) : 0,
    'pending' => getStat($db, "SELECT COUNT(*) FROM approval_requests WHERE requested_by = ? AND status = 'pending'", [$userId]),
];

$studentUserId = $userId;
$studentRecord = fetchRows($db, "SELECT * FROM students WHERE user_id = ? LIMIT 1", [$studentUserId]);
$studentRecord = $studentRecord[0] ?? null;
$studentId = $studentRecord['id'] ?? 0;
$studentStats = [
    'attendance' => $studentId && tableExists($db, 'student_attendance') ? getStat($db, "SELECT COUNT(*) FROM student_attendance WHERE student_id = ?", [$studentId]) : 0,
    'homework' => tableExists($db, 'homework_diary') ? getStat($db, "SELECT COUNT(*) FROM homework_diary") : 0,
    'lms' => tableExists($db, 'course_materials') ? getStat($db, "SELECT COUNT(*) FROM course_materials") : 0,
    'fee_paid' => $studentId ? safeSumColumn($db, 'fee_collections', ['paid_amount', 'amount_paid', 'amount'], "student_id = " . (int)$studentId) : 0,
];

$classes = tableExists($db, 'students') ? fetchRows($db, "SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class <> '' ORDER BY class ASC LIMIT 30") : [];
$subjects = tableExists($db, 'timetable') ? fetchRows($db, "SELECT DISTINCT subject FROM timetable WHERE subject IS NOT NULL AND subject <> '' ORDER BY subject ASC LIMIT 30") : [];
if (!$subjects) {
    $subjects = [['subject' => 'English'], ['subject' => 'Mathematics'], ['subject' => 'Computer Science']];
}

$page_title = "Dashboard";
include 'includes/header.php';
?>

<style>
    .role-hero {
        background: linear-gradient(135deg, #0f2d48, #123a5a);
        border-radius: 18px;
        padding: 28px;
        color: #fff;
        box-shadow: 0 20px 55px rgba(15,45,72,.18);
    }
    .role-hero p { color: rgba(255,255,255,.72); }
    .role-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: rgba(78,194,181,.14);
        color: #4ec2b5;
        padding: 7px 12px;
        font-weight: 800;
        text-transform: capitalize;
    }
    .mini-card {
        background:#fff;
        border:1px solid #e7edf5;
        border-radius:16px;
        padding:20px;
        box-shadow:0 12px 34px rgba(15,45,72,.07);
        min-height:126px;
    }
    .mini-card i {
        width:44px;
        height:44px;
        display:inline-grid;
        place-items:center;
        border-radius:12px;
        background:rgba(78,194,181,.13);
        color:#35a99c;
        margin-bottom:12px;
    }
    .mini-card span {
        display:block;
        color:#64748b;
        font-size:.78rem;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.04em;
    }
    .mini-card strong {
        display:block;
        font-size:1.55rem;
        color:#0f2d48;
        line-height:1.1;
    }
    .module-chip {
        display:inline-flex;
        align-items:center;
        border-radius:999px;
        padding:6px 10px;
        background:#f1f5f9;
        color:#0f2d48;
        font-size:.72rem;
        font-weight:800;
    }
    .approval-data {
        max-width:380px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        color:#64748b;
        font-size:.82rem;
    }
</style>

<?php displayFlashMessage(); ?>

<div class="role-hero mb-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
            <span class="role-badge"><i class="fas fa-user-shield"></i><?= h($role) ?> Dashboard</span>
            <h2 class="mt-3 mb-2 fw-bold">Welcome, <?= h($username) ?></h2>
            <p class="mb-0">Your dashboard now shows only the actions and information available for your role.</p>
        </div>
        <?php if ($isAdminRole): ?>
            <div class="d-flex flex-wrap gap-2">
                <a href="#pending-approvals" class="btn btn-light fw-bold rounded-pill px-4">Review Pending Approvals</a>
                <a href="modules/expenses/index.php" class="btn btn-outline-light fw-bold rounded-pill px-4">Financial Overview</a>
            </div>
        <?php elseif ($role === 'teacher'): ?>
            <a href="#teacher-actions" class="btn btn-light fw-bold rounded-pill px-4">Submit Work for Approval</a>
        <?php else: ?>
            <a href="#student-actions" class="btn btn-light fw-bold rounded-pill px-4">Student Actions</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdminRole): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-2"><div class="mini-card"><i class="fas fa-users"></i><span>Total Students</span><strong><?= number_format($adminStats['students']) ?></strong></div></div>
        <div class="col-md-6 col-xl-2"><div class="mini-card"><i class="fas fa-chalkboard-teacher"></i><span>Total Teachers</span><strong><?= number_format($adminStats['teachers']) ?></strong></div></div>
        <div class="col-md-6 col-xl-2"><div class="mini-card"><i class="fas fa-money-bill"></i><span>Total Fees</span><strong>Rs <?= number_format($adminStats['fees']) ?></strong></div></div>
        <div class="col-md-6 col-xl-2"><div class="mini-card"><i class="fas fa-exclamation-circle"></i><span>Complaints</span><strong><?= number_format($adminStats['complaints']) ?></strong></div></div>
        <div class="col-md-6 col-xl-2"><div class="mini-card"><i class="fas fa-cloud-upload-alt"></i><span>LMS Uploads</span><strong><?= number_format($adminStats['lms_uploads']) ?></strong></div></div>
        <div class="col-md-6 col-xl-2"><div class="mini-card"><i class="fas fa-hourglass-half"></i><span>Pending</span><strong><?= number_format($adminStats['pending']) ?></strong></div></div>
        <div class="col-md-6 col-xl-2"><div class="mini-card"><i class="fas fa-user-clock"></i><span>Pending Admissions</span><strong><?= number_format($adminStats['pending_admissions']) ?></strong></div></div>
    </div>

    <?php if ($setupChecklist && count(array_filter($setupChecklist, fn($item) => !$item['done'])) > 0): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 p-4">
                <h5 class="mb-1 fw-bold">Setup Checklist</h5>
                <p class="text-muted mb-0 small">These shared data sources must exist before every module can show complete records.</p>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <?php foreach ($setupChecklist as $item): ?>
                        <div class="col-md-6 col-xl-4">
                            <a class="d-flex align-items-center gap-3 text-decoration-none border rounded-3 p-3 h-100 <?= $item['done'] ? 'border-success-subtle bg-success-subtle' : 'border-warning-subtle bg-warning-subtle' ?>" href="<?= h($item['url']) ?>">
                                <i class="fas <?= $item['done'] ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning' ?>"></i>
                                <span class="fw-semibold text-dark"><?= h($item['label']) ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4" id="financial-overview">
        <div class="card-header bg-white border-0 p-4 d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h5 class="mb-1 fw-bold">Financial Overview</h5>
                <p class="text-muted mb-0 small">Income from fees/POS and approved expenses from HR, transport, library, LMS, POS, and manual requests.</p>
            </div>
            <a href="modules/expenses/index.php" class="btn btn-primary rounded-pill px-4 align-self-lg-center">
                <i class="fas fa-chart-line me-2"></i>Financial Overview
            </a>
        </div>
        <div class="card-body p-4">
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="mini-card"><i class="fas fa-arrow-trend-up"></i><span>Total Income</span><strong>Rs <?= number_format($financialStats['income']) ?></strong></div></div>
                <div class="col-md-3"><div class="mini-card"><i class="fas fa-arrow-trend-down"></i><span>Total Expenses</span><strong>Rs <?= number_format($financialStats['expenses']) ?></strong></div></div>
                <div class="col-md-3"><div class="mini-card"><i class="fas fa-scale-balanced"></i><span>Net Profit/Loss</span><strong class="<?= $financialStats['net'] >= 0 ? 'text-success' : 'text-danger' ?>">Rs <?= number_format($financialStats['net']) ?></strong></div></div>
                <div class="col-md-3"><div class="mini-card"><i class="fas fa-receipt"></i><span>Pending Expenses</span><strong>Rs <?= number_format($financialStats['pending_expenses']) ?></strong></div></div>
            </div>
            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-bold mb-3">Campus-wise Expenses</h6>
                    <?php foreach ($financeCampusRows as $row): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?= h($row['campus']) ?></span>
                            <strong>Rs <?= number_format((float)$row['total']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$financeCampusRows): ?><p class="text-muted small mb-0">No approved campus expenses yet.</p><?php endif; ?>
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-bold mb-3">Category-wise Expenses</h6>
                    <?php foreach ($financeCategoryRows as $row): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?= h($row['category']) ?></span>
                            <strong>Rs <?= number_format((float)$row['total']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$financeCategoryRows): ?><p class="text-muted small mb-0">No approved category expenses yet.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4" id="pending-approvals">
        <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1 fw-bold">Pending Approvals</h5>
                <p class="text-muted mb-0 small">Approve or reject homework, LMS material, complaints, attendance, and requests.</p>
            </div>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><?= count($pendingRequests) ?> Pending</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Request</th>
                        <th>Requested By</th>
                        <th>Data</th>
                        <th>Date</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$pendingRequests): ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">No pending approvals.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pendingRequests as $request): ?>
                        <tr>
                            <td class="ps-4"><span class="module-chip"><?= h(formatRequestLabel($request['module_name'], $request['action_type'])) ?></span></td>
                            <td>
                                <div class="fw-bold"><?= h($request['requester_name']) ?></div>
                                <small class="text-muted text-capitalize"><?= h($request['role']) ?></small>
                            </td>
                            <td><div class="approval-data"><?= h($request['request_data']) ?></div></td>
                            <td><?= date('d M Y', strtotime($request['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <form method="POST" class="d-inline-flex gap-2 align-items-center">
                                    <?= csrfTokenInput() ?>
                                    <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                    <input type="text" name="admin_remarks" class="form-control form-control-sm" placeholder="Remarks" style="width:150px;">
                                    <button name="dashboard_action" value="approve_request" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Approve</button>
                                    <button name="dashboard_action" value="reject_request" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($role === 'teacher'): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-school"></i><span>My Classes</span><strong><?= number_format($teacherStats['classes']) ?></strong></div></div>
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-users"></i><span>My Students</span><strong><?= number_format($teacherStats['students']) ?></strong></div></div>
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-book"></i><span>My Subjects</span><strong><?= number_format($teacherStats['subjects']) ?></strong></div></div>
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-hourglass-half"></i><span>Pending Approval</span><strong><?= number_format($teacherStats['pending']) ?></strong></div></div>
    </div>

    <div class="row g-4 mb-4" id="teacher-actions">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4"><h5 class="mb-0 fw-bold">Add Homework / Daily Diary</h5></div>
                <div class="card-body p-4">
                    <form method="POST" class="row g-3">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="dashboard_action" value="teacher_homework">
                        <div class="col-md-6"><label class="form-label small fw-bold">Class</label><input name="class" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Subject</label><input name="subject" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Diary Date</label><input type="date" name="diary_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Due Date</label><input type="date" name="due_date" class="form-control"></div>
                        <div class="col-12"><label class="form-label small fw-bold">Title</label><input name="title" class="form-control" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">Details</label><textarea name="details" class="form-control" rows="4" required></textarea></div>
                        <div class="col-12 text-end"><button class="btn btn-primary rounded-pill px-4">Submit for Approval</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4"><h5 class="mb-0 fw-bold">My Tasks</h5></div>
                <div class="card-body p-4">
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary text-start" href="modules/attendance/index.php"><i class="fas fa-calendar-check me-2"></i>Mark Attendance</a>
                        <a class="btn btn-outline-primary text-start" href="modules/lms/index.php"><i class="fas fa-cloud-upload-alt me-2"></i>Upload LMS Material</a>
                        <a class="btn btn-outline-primary text-start" href="modules/diary_homework/index.php"><i class="fas fa-book-open me-2"></i>Add Daily Diary</a>
                    </div>
                    <form method="POST" class="mt-4">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="dashboard_action" value="teacher_lms">
                        <label class="form-label small fw-bold">Quick LMS Material Note</label>
                        <input name="title" class="form-control mb-2" placeholder="Material title" required>
                        <textarea name="description" class="form-control mb-3" rows="3" placeholder="Short material description"></textarea>
                        <button class="btn btn-success rounded-pill px-4">Send LMS Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-id-card"></i><span>My Profile</span><strong><?= $studentRecord ? 'Ready' : 'Missing' ?></strong></div></div>
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-calendar-check"></i><span>Attendance</span><strong><?= number_format($studentStats['attendance']) ?></strong></div></div>
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-book-open"></i><span>Homework</span><strong><?= number_format($studentStats['homework']) ?></strong></div></div>
        <div class="col-md-3"><div class="mini-card"><i class="fas fa-money-bill"></i><span>Fee Paid</span><strong>Rs <?= number_format($studentStats['fee_paid']) ?></strong></div></div>
    </div>

    <div class="row g-4 mb-4" id="student-actions">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4"><h5 class="mb-0 fw-bold">Student Access</h5></div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start" href="modules/student_profile/index.php"><i class="fas fa-user me-2"></i>My Profile</a></div>
                        <div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start" href="modules/attendance/index.php"><i class="fas fa-calendar me-2"></i>My Attendance</a></div>
                        <div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start" href="modules/diary_homework/index.php"><i class="fas fa-book-open me-2"></i>My Homework</a></div>
                        <div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start" href="modules/lms/index.php"><i class="fas fa-folder-open me-2"></i>My LMS Material</a></div>
                        <div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start" href="modules/fee_management/index.php"><i class="fas fa-money-bill me-2"></i>My Fee Status</a></div>
                        <div class="col-md-6"><a class="btn btn-outline-primary w-100 text-start" href="modules/fee_management/index.php#generate-challan"><i class="fas fa-file-invoice me-2"></i>Generate Fee Challan</a></div>
                    </div>
                    <div class="alert alert-info mt-4 mb-0">Students have view, download, assignment submission, and complaint submission access only. Edit and delete actions are hidden.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4"><h5 class="mb-0 fw-bold">Send Complaint</h5></div>
                <div class="card-body p-4">
                    <form method="POST">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="dashboard_action" value="student_complaint">
                        <label class="form-label small fw-bold">Subject / Issue</label>
                        <textarea name="subject" class="form-control mb-3" rows="5" required></textarea>
                        <button class="btn btn-primary rounded-pill px-4">Submit Complaint</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 p-4">
                <h5 class="mb-0 fw-bold"><?= $isAdminRole ? 'Recent Activity' : 'My Approval Requests' ?></h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th class="ps-4">Request</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $rows = $isAdminRole ? $recentActivity : $myRequests; ?>
                        <?php if (!$rows): ?><tr><td colspan="3" class="text-center text-muted py-4">No activity found.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="ps-4"><?= h(formatRequestLabel($row['module_name'], $row['action_type'])) ?></td>
                                <td><span class="badge rounded-pill bg-<?= $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning text-dark') ?>"><?= h($row['status']) ?></span></td>
                                <td><?= date('d M', strtotime($row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3">Role Access Summary</h5>
            <?php if ($isAdminRole): ?>
                <p class="text-muted mb-2">Admin has full module access and can approve or reject all pending requests.</p>
                <div class="module-chip">Full Access</div>
                <div class="module-chip">Approval Control</div>
                <div class="module-chip">Reports</div>
            <?php elseif ($role === 'teacher'): ?>
                <p class="text-muted mb-2">Teacher actions are submitted as pending requests before they go live.</p>
                <div class="module-chip">My Classes</div>
                <div class="module-chip">My Students</div>
                <div class="module-chip">Pending Submissions</div>
            <?php else: ?>
                <p class="text-muted mb-2">Student access is read-only except assignment submission and complaint requests.</p>
                <div class="module-chip">My Profile</div>
                <div class="module-chip">My LMS</div>
                <div class="module-chip">No Edit/Delete</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
