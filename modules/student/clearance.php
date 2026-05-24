<?php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php?role=student");
    exit();
}
if (getUserRole() !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$page_title = 'Degree Clearance';

function dc_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dc_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function dc_status_class($status) {
    return [
        'pending' => 'warning text-dark',
        'under_review' => 'warning text-dark',
        'approved' => 'success',
        'rejected' => 'danger',
        'clear' => 'success',
        'not_clear' => 'danger',
        'waived' => 'secondary',
    ][$status] ?? 'secondary';
}

function dc_checkpoint_icon($key) {
    return [
        'fee' => 'fas fa-rupee-sign',
        'exam' => 'fas fa-file-alt',
        'library' => 'fas fa-book',
        'admin' => 'fas fa-building',
        'attendance' => 'fas fa-calendar-check',
        'sports' => 'fas fa-running',
        'principal' => 'fas fa-user-tie',
    ][$key] ?? 'fas fa-check-circle';
}

function dc_table_exists(PDO $db, $table) {
    return function_exists('tableExists') && tableExists($db, $table);
}

function dc_column_exists(PDO $db, $table, $column) {
    return function_exists('columnExists') && columnExists($db, $table, $column);
}

try {
    $select = [
        's.*',
        "CONCAT(s.first_name,' ',s.last_name) AS full_name",
        dc_column_exists($db, 'campuses', 'name') && dc_column_exists($db, 'students', 'campus_id')
            ? 'c.name AS campus_name'
            : "COALESCE(s.campus, 'Rajanpur') AS campus_name",
    ];
    $joins = [];
    if (dc_column_exists($db, 'students', 'campus_id')) {
        $joins[] = 'LEFT JOIN campuses c ON s.campus_id = c.id';
    }
    if (dc_table_exists($db, 'programs') && dc_column_exists($db, 'students', 'program_id')) {
        $select[] = 'p.name AS program_name';
        $select[] = 'p.type AS program_type';
        $select[] = 'p.total_semesters';
        $joins[] = 'LEFT JOIN programs p ON s.program_id = p.id';
    } else {
        $select[] = "COALESCE(s.class, 'Program') AS program_name";
        $select[] = "'Academic' AS program_type";
        $select[] = '1 AS total_semesters';
    }
    $select[] = dc_column_exists($db, 'students', 'current_semester') ? 's.current_semester' : '1 AS current_semester';
    $select[] = dc_column_exists($db, 'students', 'session') ? 's.session' : "COALESCE(YEAR(s.admission_date), YEAR(CURDATE())) AS session";

    $stmt = $db->prepare('SELECT ' . implode(', ', $select) . ' FROM students s ' . implode(' ', $joins) . ' WHERE s.user_id = :uid LIMIT 1');
    $stmt->execute([':uid' => getUserId()]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Degree Clearance Student Load Error: ' . $e->getMessage());
    $student = null;
}

if (!$student) {
    include '../../includes/header.php';
    echo '<div class="alert alert-warning">Your student profile is not linked to this portal account yet.</div>';
    include '../../includes/footer.php';
    exit;
}

$student_id = (int)$student['id'];
$existing = $db->prepare("SELECT * FROM degree_clearance WHERE student_id = :sid ORDER BY applied_at DESC LIMIT 1");
$existing->execute([':sid' => $student_id]);
$clearance = $existing->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_clearance'])) {
    try {
        if (function_exists('requireCsrfToken')) {
            requireCsrfToken();
        }
        $is_eligible = ((int)$student['current_semester'] >= (int)$student['total_semesters']);
        if (!$is_eligible) {
            $apply_error = 'Aap abhi final semester mein nahi hain. Clearance sirf final semester students apply kar sakte hain.';
        } elseif ($clearance && in_array($clearance['status'], ['pending', 'under_review', 'approved'], true)) {
            $apply_error = 'Aapki request pehle se submit hai. Status check karein.';
        } else {
            $db->beginTransaction();
            $year = date('Y');
            $seq = (int)$db->query("SELECT COUNT(*) + 1 FROM degree_clearance")->fetchColumn();
            $app_no = 'QGC-CLR-' . $year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

            $ins = $db->prepare("INSERT INTO degree_clearance (student_id, application_number, status) VALUES (:sid, :appno, 'pending')");
            $ins->execute([':sid' => $student_id, ':appno' => $app_no]);
            $clearance_id = (int)$db->lastInsertId();

            $fee_dues = 0;
            if (dc_table_exists($db, 'fee_challan')) {
                $q = $db->prepare("SELECT COALESCE(SUM(total_payable),0) FROM fee_challan WHERE student_id = :sid AND status != 'paid'");
                $q->execute([':sid' => $student_id]);
                $fee_dues = (float)$q->fetchColumn();
            } elseif (dc_table_exists($db, 'fee_collections')) {
                $amountCol = firstExistingColumn($db, 'fee_collections', ['amount']) ?: 'amount_paid';
                $paidCol = firstExistingColumn($db, 'fee_collections', ['paid_amount', 'amount_paid']) ?: $amountCol;
                $q = $db->prepare("SELECT COALESCE(SUM(GREATEST(`$amountCol` - COALESCE(`$paidCol`,0),0)),0) FROM fee_collections WHERE student_id = :sid AND COALESCE(status,'Pending') != 'Paid'");
                $q->execute([':sid' => $student_id]);
                $fee_dues = (float)$q->fetchColumn();
            }

            $failed_exams = 0;
            if (dc_table_exists($db, 'marks') && dc_table_exists($db, 'exams')) {
                $q = $db->prepare("SELECT COUNT(*) FROM marks m JOIN exams e ON m.exam_id = e.id WHERE m.student_id = :sid AND m.obtained_marks < e.passing_marks AND e.type = 'final'");
                $q->execute([':sid' => $student_id]);
                $failed_exams = (int)$q->fetchColumn();
            } elseif (dc_table_exists($db, 'exam_marks')) {
                $q = $db->prepare("SELECT COUNT(*) FROM exam_marks WHERE student_id = :sid AND obtained_marks < (total_marks * 0.40)");
                $q->execute([':sid' => $student_id]);
                $failed_exams = (int)$q->fetchColumn();
            }

            $lib_books = 0;
            $lib_fines = 0;
            if (dc_table_exists($db, 'library_dues')) {
                $q = $db->prepare("SELECT COUNT(*) AS books_issued, COALESCE(SUM(fine_amount),0) AS fines FROM library_dues WHERE student_id = :sid AND status IN ('issued','overdue')");
                $q->execute([':sid' => $student_id]);
                $lib = $q->fetch(PDO::FETCH_ASSOC) ?: ['books_issued' => 0, 'fines' => 0];
                $lib_books = (int)$lib['books_issued'];
                $lib_fines = (float)$lib['fines'];
            }

            $admin_dues = 0;
            if (dc_table_exists($db, 'admin_dues')) {
                $q = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM admin_dues WHERE student_id = :sid AND status = 'pending'");
                $q->execute([':sid' => $student_id]);
                $admin_dues = (float)$q->fetchColumn();
            }

            $att_pct = 100;
            if (dc_table_exists($db, 'attendance')) {
                $q = $db->prepare("SELECT ROUND(COUNT(CASE WHEN status='present' THEN 1 END) * 100.0 / NULLIF(COUNT(*),0), 1) FROM attendance WHERE student_id = :sid");
                $q->execute([':sid' => $student_id]);
                $att_pct = (float)($q->fetchColumn() ?: 0);
            } elseif (dc_table_exists($db, 'student_attendance')) {
                $q = $db->prepare("SELECT ROUND(COUNT(CASE WHEN LOWER(status) IN ('present','p','attended') THEN 1 END) * 100.0 / NULLIF(COUNT(*),0), 1) FROM student_attendance WHERE student_id = :sid");
                $q->execute([':sid' => $student_id]);
                $att_pct = (float)($q->fetchColumn() ?: 0);
            }

            $checkpoints = [
                ['Fee Clearance', 'fee', $fee_dues <= 0 ? 'clear' : 'not_clear', 1, $fee_dues, $fee_dues <= 0 ? 'No pending fee dues' : dc_money($fee_dues) . ' pending'],
                ['Examination Clearance', 'exam', $failed_exams === 0 ? 'clear' : 'not_clear', 1, 0, $failed_exams === 0 ? 'All exams passed' : $failed_exams . ' exam(s) failed'],
                ['Library Clearance', 'library', ($lib_books === 0 && $lib_fines <= 0) ? 'clear' : 'not_clear', 1, $lib_fines, $lib_books > 0 ? $lib_books . ' book(s) not returned' : ($lib_fines > 0 ? dc_money($lib_fines) . ' fine pending' : 'No library dues')],
                ['Admin Office Clearance', 'admin', $admin_dues <= 0 ? 'clear' : 'not_clear', 1, $admin_dues, $admin_dues <= 0 ? 'No admin dues' : dc_money($admin_dues) . ' dues pending'],
                ['Attendance Clearance', 'attendance', $att_pct >= 75 ? 'clear' : 'not_clear', 1, 0, 'Attendance: ' . $att_pct . '%' . ($att_pct < 75 ? ' (min 75% required)' : ' clear')],
                ['Sports & Activities', 'sports', 'pending', 0, 0, 'Manual check required'],
                ['Principal Approval', 'principal', 'pending', 0, 0, 'Awaiting principal'],
            ];

            $cp_ins = $db->prepare("INSERT INTO clearance_checkpoints (clearance_id, checkpoint_name, checkpoint_key, status, auto_checked, amount_due, remarks) VALUES (:cid, :name, :ckey, :status, :auto, :amt, :rem)");
            foreach ($checkpoints as $cp) {
                $cp_ins->execute([':cid' => $clearance_id, ':name' => $cp[0], ':ckey' => $cp[1], ':status' => $cp[2], ':auto' => $cp[3], ':amt' => $cp[4], ':rem' => $cp[5]]);
            }
            $db->prepare("UPDATE degree_clearance SET status = 'under_review' WHERE id = :id")->execute([':id' => $clearance_id]);
            $db->commit();
            $existing->execute([':sid' => $student_id]);
            $clearance = $existing->fetch(PDO::FETCH_ASSOC);
            $apply_success = 'Application submitted! Number: ' . $app_no;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Degree Clearance Apply Error: ' . $e->getMessage());
        $apply_error = 'Unable to submit clearance application. Please try again.';
    }
}

$checkpoints_data = [];
if ($clearance) {
    $cp = $db->prepare("SELECT * FROM clearance_checkpoints WHERE clearance_id = :cid ORDER BY id ASC");
    $cp->execute([':cid' => $clearance['id']]);
    $checkpoints_data = $cp->fetchAll(PDO::FETCH_ASSOC);
}

$total_cp = count($checkpoints_data);
$cleared_cp = 0;
$blocked_cp = 0;
foreach ($checkpoints_data as $cp) {
    if (in_array($cp['status'], ['clear', 'waived'], true)) {
        $cleared_cp++;
    }
    if ($cp['status'] === 'not_clear') {
        $blocked_cp++;
    }
}
$progress_pct = $total_cp > 0 ? round(($cleared_cp / $total_cp) * 100) : 0;
$eligible = ((int)$student['current_semester'] >= (int)$student['total_semesters']);

include '../../includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700;800;900&family=Space+Mono:wght@400;700&display=swap');
    .clearance-page { font-family: 'DM Sans', system-ui, sans-serif; }
    .clearance-page h1, .clearance-page h2, .clearance-page h3, .clearance-page h4, .clearance-page h5 { font-family: 'Playfair Display', Georgia, serif; }
    .mono { font-family: 'Space Mono', monospace; }
    .qgc-teal { color:#4ec2b5; }
    .qgc-navy { color:#0f2d48; }
    .clearance-hero { border-left: 5px solid #4ec2b5; }
</style>

<div class="container-fluid clearance-page">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title mb-1"><i class="fas fa-graduation-cap me-2 qgc-teal"></i>Degree Clearance Application</h2>
            <p class="text-muted mb-0">Degree lene ke liye tamam dues clear karwana zaruri hai</p>
        </div>
    </div>

    <?php if (!empty($apply_success)): ?><div class="alert alert-success"><?= dc_h($apply_success) ?></div><?php endif; ?>
    <?php if (!empty($apply_error)): ?><div class="alert alert-danger"><?= dc_h($apply_error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted small">Full Name</div><div class="fw-bold"><?= dc_h($student['full_name']) ?></div></div>
                <div class="col-md-2"><div class="text-muted small">Roll Number</div><div class="fw-bold mono"><?= dc_h($student['roll_number'] ?: ($student['student_id'] ?? $student['id'])) ?></div></div>
                <div class="col-md-3"><div class="text-muted small">Program</div><div class="fw-bold"><?= dc_h($student['program_name']) ?></div></div>
                <div class="col-md-3"><div class="text-muted small">Campus</div><div class="fw-bold"><?= dc_h($student['campus_name']) ?></div></div>
                <div class="col-md-2"><div class="text-muted small">Session</div><div class="fw-bold"><?= dc_h($student['session']) ?></div></div>
                <div class="col-md-3"><div class="text-muted small">Semester</div><div class="fw-bold"><?= (int)$student['current_semester'] ?> / <?= (int)$student['total_semesters'] ?></div></div>
                <div class="col-md-2"><span class="badge bg-<?= strtolower((string)$student['status']) === 'active' ? 'success' : 'secondary' ?>"><?= dc_h($student['status']) ?></span></div>
            </div>
        </div>
    </div>

    <?php if (!$eligible): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background:rgba(251,191,36,.1);border:1px solid #f0b429!important;">
            <div class="card-body p-4 d-flex gap-3 align-items-start">
                <i class="fas fa-exclamation-triangle fa-3x" style="color:#f0b429;"></i>
                <div>
                    <h4 class="fw-bold qgc-navy">Abhi Eligible Nahi</h4>
                    <p class="mb-0">Degree clearance sirf final semester students apply kar sakte hain. Aap Semester <?= (int)$student['current_semester'] ?> mein hain. Final semester: <?= (int)$student['total_semesters'] ?>.</p>
                </div>
            </div>
        </div>
    <?php elseif (!$clearance): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 clearance-hero" style="background:rgba(78,194,181,.05);">
            <div class="card-body p-5 text-center">
                <i class="fas fa-file-alt mb-3" style="font-size:4rem;color:#4ec2b5;"></i>
                <h3 class="qgc-navy fw-bold">Degree Clearance Apply Karein</h3>
                <p class="text-muted">Apply karne par system automatically fee dues, exam results, library status, admin dues, aur attendance check karega.</p>
                <form method="POST">
                    <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                    <button name="apply_clearance" class="btn px-5 py-3" style="background:#0f2d48;color:#fff;font-size:1rem;border-radius:12px;">
                        <i class="fas fa-paper-plane me-2"></i>Apply for Degree Clearance
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                    <div><span class="text-muted small">Application No</span><div class="badge bg-light text-dark mono p-2"><?= dc_h($clearance['application_number']) ?></div></div>
                    <div><span class="text-muted small">Applied</span><div class="fw-bold"><?= date('d M Y', strtotime($clearance['applied_at'])) ?></div></div>
                    <div><span class="badge bg-<?= dc_status_class($clearance['status']) ?>"><?= dc_h(ucwords(str_replace('_', ' ', $clearance['status']))) ?></span></div>
                </div>
                <div class="progress rounded-pill mb-2" style="height:12px;"><div class="progress-bar" style="width:<?= (int)$progress_pct ?>%;background:#4ec2b5;"></div></div>
                <div class="small text-muted"><?= (int)$cleared_cp ?> of <?= (int)$total_cp ?> checkpoints clear</div>
                <?php if ($blocked_cp > 0): ?><div class="alert alert-danger mt-3 mb-0"><?= (int)$blocked_cp ?> checkpoint(s) require attention. Please clear dues.</div><?php endif; ?>
            </div>
        </div>

        <?php if ($clearance['status'] === 'approved'): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background:linear-gradient(135deg,rgba(78,194,181,.1),rgba(15,45,72,.05));border:2px solid #4ec2b5!important;">
                <div class="card-body p-5 text-center">
                    <i class="fas fa-check-circle mb-3" style="font-size:4rem;color:#4ade80;"></i>
                    <h3 class="fw-bold qgc-navy">Mubarak Ho!</h3>
                    <p class="lead">Aapka Degree Clearance Approved Hai</p>
                    <a href="clearance_certificate.php?id=<?= (int)$clearance['id'] ?>" class="btn btn-lg px-5" style="background:#4ec2b5;color:#0f2d48;font-weight:700;">
                        <i class="fas fa-download me-2"></i>Download Clearance Certificate
                    </a>
                </div>
            </div>
        <?php elseif ($clearance['status'] === 'rejected'): ?>
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-danger bg-opacity-10">
                <div class="card-body p-4">
                    <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                    <h4 class="fw-bold">Application Rejected</h4>
                    <p><?= dc_h($clearance['rejection_reason'] ?: 'No reason provided.') ?></p>
                    <form method="POST"><?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?><button name="apply_clearance" class="btn btn-danger">Apply Again</button></form>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <?php foreach ($checkpoints_data as $cp): ?>
                <?php $status = $cp['status']; ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm rounded-4 h-100 border-<?= dc_status_class($status) ?> bg-<?= dc_status_class($status) ?> bg-opacity-10">
                        <div class="card-body p-4">
                            <div class="d-flex gap-3">
                                <i class="<?= dc_h(dc_checkpoint_icon($cp['checkpoint_key'])) ?> fa-2x qgc-teal"></i>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <h5 class="fw-bold mb-1"><?= dc_h($cp['checkpoint_name']) ?></h5>
                                        <span class="badge bg-<?= dc_status_class($status) ?>"><?= dc_h(ucwords(str_replace('_', ' ', $status))) ?></span>
                                    </div>
                                    <p class="text-muted mb-2"><?= dc_h($cp['remarks']) ?></p>
                                    <?php if ((float)$cp['amount_due'] > 0): ?><div class="text-danger fw-bold mb-2">Amount Due: <?= dc_money($cp['amount_due']) ?> <a href="../fee_management/index.php" class="ms-2">Pay/View</a></div><?php endif; ?>
                                    <span class="badge <?= (int)$cp['auto_checked'] ? 'text-bg-info' : 'text-bg-secondary' ?>"><?= (int)$cp['auto_checked'] ? 'Auto Checked' : 'Manual Review' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
