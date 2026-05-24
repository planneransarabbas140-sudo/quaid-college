<?php
// File: vouchers.php
// EXTENSIBILITY NOTES:
// To add new fee heads: update fee_structure table — vouchers pick up automatically
// To add SMS on voucher generation: call sendVoucherSMS() after createVoucher()
// To add bank payment QR: add qr_code column to vouchers table and generate in print view
// To add bulk PDF export: call vouchers-print.php for each ID in a loop

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/voucher_functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$conn = $database->getConnection();

// Safe Auto-migration on first load
try {
    if (!tableExists($conn, 'vouchers')) {
        $sqlPath = __DIR__ . '/database/vouchers.sql';
        if (is_file($sqlPath)) {
            $conn->exec(file_get_contents($sqlPath));
        }
    }
} catch (Exception $e) {
    error_log('Vouchers Migration Error: ' . $e->getMessage());
}

$msg = (string)($_GET['msg'] ?? '');
$errors = $_SESSION['voucher_errors'] ?? [];
unset($_SESSION['voucher_errors']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        redirect('vouchers.php?msg=error');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'generate') {
        $validation = validateVoucherInput($_POST);
        if (!$validation['valid']) {
            $_SESSION['voucher_errors'] = $validation['errors'];
            redirect('vouchers.php?msg=error');
        }

        $type = $_POST['voucher_type'];
        $issue_date = $_POST['issue_date'];
        $due_date = $_POST['due_date'];
        $note = sanitizeInput($_POST['note'] ?? '');
        
        $ok = false;
        
        if ($type === 'individual') {
            $student_id = (int)$_POST['student_id'];
            
            // Build items list from POST or fall back to student default fee structures
            $items = [];
            if (isset($_POST['item_heads']) && is_array($_POST['item_heads'])) {
                foreach ($_POST['item_heads'] as $index => $head) {
                    $amount = floatval($_POST['item_amounts'][$index] ?? 0);
                    if ($amount > 0 && trim($head) !== '') {
                        $items[] = [
                            'fee_head' => sanitizeInput($head),
                            'amount' => $amount
                        ];
                    }
                }
            } else {
                $items = getStudentFeeBreakdown($conn, $student_id);
            }
            
            $total = array_sum(array_column($items, 'amount'));
            
            // Get class
            $stmt = $conn->prepare("SELECT class FROM students WHERE id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            $studentClass = $stmt->fetchColumn();
            
            $data = [
                'student_id' => $student_id,
                'class' => $studentClass ?: null,
                        'voucher_type' => 'bulk',
                'issue_date' => $issue_date,
                'due_date' => $due_date,
                'total_amount' => $total,
                'note' => $note
            ];
            
            $ok = createVoucher($conn, $data, $items) !== false;
            
        } elseif ($type === 'family') {
            $family_id = trim((string)$_POST['family_id']); // guardian phone
            
            $items = [];
            if (isset($_POST['item_heads']) && is_array($_POST['item_heads'])) {
                foreach ($_POST['item_heads'] as $index => $head) {
                    $amount = floatval($_POST['item_amounts'][$index] ?? 0);
                    if ($amount > 0 && trim($head) !== '') {
                        $items[] = [
                            'fee_head' => sanitizeInput($head),
                            'amount' => $amount
                        ];
                    }
                }
            } else {
                $items = getFamilyFeeBreakdown($conn, $family_id);
            }
            
            $total = array_sum(array_column($items, 'amount'));
            
            $data = [
                'family_id' => $family_id,
                'voucher_type' => 'family',
                'issue_date' => $issue_date,
                'due_date' => $due_date,
                'total_amount' => $total,
                'note' => $note
            ];
            
            $ok = createVoucher($conn, $data, $items) !== false;
            
        } elseif ($type === 'bulk') {
            $bulk_class = trim((string)$_POST['bulk_class']);
            
            // Fetch students
            $students = getStudentsForVoucher($conn, $bulk_class !== '' ? $bulk_class : null);
            
            if (!empty($students)) {
                $ok = true;
                foreach ($students as $student) {
                    $items = getStudentFeeBreakdown($conn, $student['id']);
                    if (!empty($items)) {
                        $total = array_sum(array_column($items, 'amount'));
                        $data = [
                            'student_id' => $student['id'],
                            'class' => $student['class'],
                            'voucher_type' => 'individual',
                            'issue_date' => $issue_date,
                            'due_date' => $due_date,
                            'total_amount' => $total,
                            'note' => $note
                        ];
                        if (createVoucher($conn, $data, $items) === false) {
                            $ok = false;
                        }
                    }
                }
            }
        }
        
        redirect('vouchers.php?msg=' . ($ok ? 'generated' : 'error'));
    }

    if ($action === 'mark_paid') {
        $voucher_id = (int)$_POST['voucher_id'];
        $ok = updateVoucherStatus($conn, $voucher_id, 'paid');
        redirect('vouchers.php?msg=' . ($ok ? 'paid' : 'error'));
    }

    if ($action === 'cancel') {
        $voucher_id = (int)$_POST['voucher_id'];
        $ok = updateVoucherStatus($conn, $voucher_id, 'cancelled');
        redirect('vouchers.php?msg=' . ($ok ? 'cancelled' : 'error'));
    }

    if ($action === 'delete') {
        $voucher_id = (int)$_POST['voucher_id'];
        $ok = deleteVoucher($conn, $voucher_id);
        redirect('vouchers.php?msg=' . ($ok ? 'deleted' : 'error'));
    }
}

// Fetch filters
$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'class' => trim((string)($_GET['class'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? ''))
];

// Load page lists
$classes = getClassesForVoucher($conn);
$students = getStudentsForVoucher($conn);
$families = getFamiliesForVoucher($conn);
$summary = getVoucherSummary($conn);
$vouchers = getVoucherList($conn, $filters);
$school_info = getSchoolInfoForVoucher($conn);

$page_title = 'Vouchers Management';
include __DIR__ . '/includes/header.php';

$adminName = htmlspecialchars((string)($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
$preselectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
?>

<div class="container-fluid px-4 py-4">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Vouchers</h2>
            <p class="text-muted mb-0">Generate, manage and print student fee payment vouchers</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#userGuideCollapse">
                <i class="fas fa-info-circle me-2"></i>User Guide
            </button>
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                    <i class="fas fa-user text-secondary"></i>
                </div>
                <div class="small">
                    <div class="fw-semibold"><?= $adminName ?></div>
                    <div class="text-muted">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <!-- COLLAPSIBLE USER GUIDE -->
    <div class="collapse mb-4" id="userGuideCollapse">
        <div class="card card-body bg-light border-0 shadow-sm">
            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-book me-2"></i>How to use Vouchers Module</h6>
            <p class="small text-muted mb-0">
                1. <strong>Individual Voucher</strong>: Select a student to generate a customized fee receipt. Sibling accounts remain independent.<br>
                2. <strong>Family Voucher</strong>: Combines all siblings linked with the same guardian phone number into a single printed payment slip.<br>
                3. <strong>Bulk Voucher</strong>: Generates individual vouchers for all active students in a selected class or the entire school in one click.<br>
                4. <strong>Payment Sync</strong>: Unpaid vouchers are automatically marked as "Paid" once a matching payment is collected inside the Collect Fee page.
            </p>
        </div>
    </div>

    <!-- ALERT MESSAGES -->
    <?php if ($msg === 'generated'): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>Voucher generated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($msg === 'paid'): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>Voucher marked as paid.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($msg === 'cancelled'): ?>
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>Voucher cancelled.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-trash me-2"></i>Voucher deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($msg === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fas fa-times-circle me-2"></i>Something went wrong. Please try again.
            <?php if (!empty($errors)): ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-md col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Vouchers</p>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($summary['total_vouchers']) ?></h4>
                    </div>
                    <div class="bg-light text-primary rounded p-2"><i class="fas fa-file-invoice fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Paid</p>
                        <h4 class="fw-bold mb-0 text-success"><?= number_format($summary['total_paid']) ?></h4>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded p-2"><i class="fas fa-check-circle fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Unpaid</p>
                        <h4 class="fw-bold mb-0 text-warning"><?= number_format($summary['total_unpaid']) ?></h4>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning rounded p-2"><i class="fas fa-clock fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md col-sm-6">
            <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Cancelled</p>
                        <h4 class="fw-bold mb-0 text-danger"><?= number_format($summary['total_cancelled']) ?></h4>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded p-2"><i class="fas fa-times-circle fa-lg"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md col-sm-12">
            <div class="card border-0 shadow-sm p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Collected</p>
                        <h4 class="fw-bold mb-0 text-primary">Rs. <?= number_format($summary['total_amount_collected'], 0) ?></h4>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded p-2"><i class="fas fa-wallet fa-lg"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- GENERATOR & FORM -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-cogs text-primary me-2"></i>Generate New Voucher</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="vouchers.php" id="voucherGenForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="generate">

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Voucher Type</label>
                        <select name="voucher_type" id="vTypeSelect" class="form-select" required>
                            <option value="individual" selected>Individual Student</option>
                            <option value="family">Family (Sibling Grouped)</option>
                            <option value="bulk">Bulk Generator</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>

                <!-- SELECTOR ROWS (SWITCH VIA JS) -->
                <div class="row g-3 mb-3">
                    <!-- Individual Student -->
                    <div class="col-md-6 form-group-target" id="group_individual">
                        <label class="form-label fw-bold">Select Student</label>
                        <select name="student_id" id="studentSelect" class="form-select">
                            <option value="">Choose Student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= (int)$student['id'] ?>" <?= $preselectedStudentId === (int)$student['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . ($student['student_id'] ?: 'N/A') . ' - ' . $student['class'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Family Group -->
                    <div class="col-md-6 form-group-target d-none" id="group_family">
                        <label class="form-label fw-bold">Select Guardian Family</label>
                        <select name="family_id" id="familySelect" class="form-select">
                            <option value="">Choose Guardian...</option>
                            <?php foreach ($families as $fam): ?>
                                <option value="<?= htmlspecialchars($fam['guardian_phone']) ?>">
                                    <?= htmlspecialchars($fam['guardian_name'] . ' (Phone: ' . $fam['guardian_phone'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Bulk Class filter -->
                    <div class="col-md-6 form-group-target d-none" id="group_bulk">
                        <label class="form-label fw-bold">Target Class (Optional)</label>
                        <select name="bulk_class" id="bulkClassSelect" class="form-select">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $cl): ?>
                                <option value="<?= htmlspecialchars($cl) ?>"><?= htmlspecialchars($cl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1">Generates individual vouchers for all students matching selection.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Note / Description</label>
                        <input type="text" name="note" class="form-control" placeholder="E.g., May 2026 Tuition Fee + Development Charges">
                    </div>
                </div>

                <!-- FEE BREAKDOWN TABLE (DYNAMICS GENERATION VIA AJAX) -->
                <div class="card border-0 bg-light p-3 mb-3 d-none animate-fade" id="feeBreakdownCard">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-list text-primary me-2"></i>Itemized Fee Preview</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-2 align-middle bg-white" id="feeBreakdownTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Fee Head</th>
                                    <th style="width: 250px;" class="text-end">Amount (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody id="feeItemsContainer">
                                <!-- Loaded dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold bg-light">
                                    <td class="text-end">Total Amount Due</td>
                                    <td class="text-end" id="feeTotalDisplay">Rs. 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <input type="hidden" name="total_amount" id="totalAmountInput" value="0.00">
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                        <i class="fas fa-print me-2"></i>Generate Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Voucher #, student name, guardian...">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-bold small">Class</label>
                    <select name="class" class="form-select form-select-sm">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cl): ?>
                            <option value="<?= htmlspecialchars($cl) ?>" <?= $filters['class'] === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4">
                    <label class="form-label fw-bold small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="unpaid" <?= $filters['status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                        <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4">
                    <label class="form-label fw-bold small">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <label class="form-label fw-bold small">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLE LIST -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-list me-2 text-primary"></i>All Vouchers</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Voucher No</th>
                            <th>Voucher Type</th>
                            <th>Student / Family</th>
                            <th>Class</th>
                            <th>Dates</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="mb-3 text-muted"><i class="fas fa-file-invoice-dollar fa-3x"></i></div>
                                    <h6 class="fw-bold text-muted">No vouchers generated yet</h6>
                                    <p class="small text-muted mb-0">Generate your first voucher using the form above.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vouchers as $voucher): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary">
                                        <?= htmlspecialchars($voucher['voucher_number']) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark text-capitalize"><?= htmlspecialchars($voucher['voucher_type']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($voucher['voucher_type'] === 'individual'): ?>
                                            <div class="fw-bold"><?= htmlspecialchars($voucher['first_name'] . ' ' . $voucher['last_name']) ?></div>
                                            <small class="text-muted">Student ID: <?= htmlspecialchars($voucher['display_student_id']) ?></small>
                                        <?php elseif ($voucher['voucher_type'] === 'family'): ?>
                                            <div class="fw-bold"><?= htmlspecialchars($voucher['family_guardian_name'] ?: 'Family Voucher') ?></div>
                                            <small class="text-muted">Guardian Phone: <?= htmlspecialchars($voucher['family_id']) ?></small>
                                        <?php else: ?>
                                            <div class="fw-bold">Bulk Generated</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($voucher['class'] ?: '-') ?>
                                    </td>
                                    <td>
                                        <div><small class="fw-semibold">Issued: </small><?= date('d M Y', strtotime($voucher['issue_date'])) ?></div>
                                        <div><small class="fw-semibold">Due: </small><?= date('d M Y', strtotime($voucher['due_date'])) ?></div>
                                    </td>
                                    <td class="text-end fw-bold text-dark">
                                        Rs. <?= number_format($voucher['total_amount'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($voucher['status'] === 'paid'): ?>
                                            <span class="badge bg-success py-2 px-3 rounded-pill">Paid</span>
                                        <?php elseif ($voucher['status'] === 'cancelled'): ?>
                                            <span class="badge bg-danger py-2 px-3 rounded-pill">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark py-2 px-3 rounded-pill">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="vouchers-print.php?id=<?= (int)$voucher['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print/Download PDF">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                            
                                            <?php if ($voucher['status'] === 'unpaid'): ?>
                                                <form method="POST" action="vouchers.php" class="d-inline" onsubmit="return confirm('Mark Voucher <?= htmlspecialchars($voucher['voucher_number']) ?> as Paid?');">
                                                    <?= csrfTokenInput() ?>
                                                    <input type="hidden" name="action" value="mark_paid">
                                                    <input type="hidden" name="voucher_id" value="<?= (int)$voucher['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Mark Paid">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>

                                                <form method="POST" action="vouchers.php" class="d-inline" onsubmit="return confirm('Are you sure you want to Cancel Voucher <?= htmlspecialchars($voucher['voucher_number']) ?>?');">
                                                    <?= csrfTokenInput() ?>
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="voucher_id" value="<?= (int)$voucher['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning text-dark" title="Cancel Voucher">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>

                                                <form method="POST" action="vouchers.php" class="d-inline" onsubmit="return confirm('Are you sure you want to DELETE Voucher <?= htmlspecialchars($voucher['voucher_number']) ?>? This cannot be undone.');">
                                                    <?= csrfTokenInput() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="voucher_id" value="<?= (int)$voucher['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- AJAX INTERACTIVE SCRIPT -->
<script>
$(document).ready(function() {
    // Select VType handle show/hide
    $('#vTypeSelect').on('change', function() {
        const type = $(this).val();
        $('.form-group-target').addClass('d-none');
        $('#feeBreakdownCard').addClass('d-none');
        $('#feeItemsContainer').html('');
        
        if (type === 'individual') {
            $('#group_individual').removeClass('d-none');
            // Trigger load if student is selected
            if ($('#studentSelect').val() !== '') {
                loadStudentFees($('#studentSelect').val());
            }
        } else if (type === 'family') {
            $('#group_family').removeClass('d-none');
            // Trigger load if family is selected
            if ($('#familySelect').val() !== '') {
                loadFamilyFees($('#familySelect').val());
            }
        } else if (type === 'bulk') {
            $('#group_bulk').removeClass('d-none');
        }
    });

    // Student Select change
    $('#studentSelect').on('change', function() {
        const studentId = $(this).val();
        if (studentId !== '') {
            loadStudentFees(studentId);
        } else {
            $('#feeBreakdownCard').addClass('d-none');
        }
    });

    // Family Select change
    $('#familySelect').on('change', function() {
        const familyId = $(this).val();
        if (familyId !== '') {
            loadFamilyFees(familyId);
        } else {
            $('#feeBreakdownCard').addClass('d-none');
        }
    });

    // Pre-trigger change logic on page load (handles preselected student_id)
    if ($('#studentSelect').val() !== '') {
        $('#studentSelect').trigger('change');
    }

    function loadStudentFees(studentId) {
        $('#feeBreakdownCard').removeClass('d-none');
        $('#feeItemsContainer').html('<tr><td colspan="2" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading fee items...</td></tr>');
        
        $.ajax({
            url: 'vouchers-ajax.php',
            type: 'GET',
            data: { action: 'get_student_fees', student_id: studentId },
            success: function(response) {
                renderFeeTable(response);
            },
            error: function() {
                $('#feeItemsContainer').html('<tr><td colspan="2" class="text-center text-danger py-3">Failed to load fee items.</td></tr>');
            }
        });
    }

    function loadFamilyFees(familyId) {
        $('#feeBreakdownCard').removeClass('d-none');
        $('#feeItemsContainer').html('<tr><td colspan="2" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading family sibling fee items...</td></tr>');
        
        $.ajax({
            url: 'vouchers-ajax.php',
            type: 'GET',
            data: { action: 'get_family_fees', family_id: familyId },
            success: function(response) {
                renderFeeTable(response);
            },
            error: function() {
                $('#feeItemsContainer').html('<tr><td colspan="2" class="text-center text-danger py-3">Failed to load fee items.</td></tr>');
            }
        });
    }

    function renderFeeTable(items) {
        if (!items || items.length === 0) {
            $('#feeItemsContainer').html('<tr><td colspan="2" class="text-center text-muted py-3">No active fee structure mappings found for this selection. Vouchers can still be generated.</td></tr>');
            updateTotal();
            return;
        }

        let html = '';
        items.forEach((item, idx) => {
            const feeHead = $('<div>').text(item.fee_head || '').html();
            const amount = Number.parseFloat(item.amount || 0);
            html += `
                <tr>
                    <td>
                        <input type="text" name="item_heads[]" class="form-control form-control-sm border-0" value="${feeHead}" readonly>
                    </td>
                    <td>
                        <input type="number" name="item_amounts[]" class="form-control form-control-sm text-end fee-amount-input border-0 bg-light" step="0.01" min="0" value="${Number.isFinite(amount) ? amount.toFixed(2) : '0.00'}" style="font-weight: 600;">
                    </td>
                </tr>
            `;
        });
        
        $('#feeItemsContainer').html(html);
        updateTotal();

        // Listen for amount edit changes to update total dynamically
        $('.fee-amount-input').on('input', function() {
            updateTotal();
        });
    }

    function updateTotal() {
        let total = 0.00;
        $('.fee-amount-input').each(function() {
            const val = parseFloat($(this).val());
            if (!isNaN(val)) {
                total += val;
            }
        });
        $('#feeTotalDisplay').text('Rs. ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#totalAmountInput').val(total.toFixed(2));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
