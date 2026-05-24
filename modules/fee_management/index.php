<?php
// File: modules/fee_management/index.php - Fee Management Dashboard
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();
$role = getUserRole();

if ($role === 'student') {
    $student = null;
    $my_payments = [];
    $my_challans = [];
    $fee_heads = [];
    $total_paid = 0;
    $currentMonth = date('Y-m');
    $currentMonthStart = date('Y-m-01');
    $currentDueDate = date('Y-m-t');
    $academicYear = function_exists('getCurrentAcademicYear') ? getCurrentAcademicYear() : date('Y') . '-' . ((int)date('Y') + 1);

    if (columnExists($db, 'students', 'user_id')) {
        $stmt = $db->prepare("SELECT id, first_name, last_name, student_id, roll_number, class, section FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([getUserId()]);
        $student = $stmt->fetch();
    }

    if ($student && tableExists($db, 'fee_structure')) {
        $stmt = $db->prepare("SELECT * FROM fee_structure WHERE class = ? AND is_active = 1 ORDER BY fee_type ASC");
        $stmt->execute([$student['class']]);
        $fee_heads = $stmt->fetchAll();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_student_challan') {
        try {
            requireCsrfToken();
            if (!$student) {
                throw new Exception('Your student profile is not linked to this portal account yet.');
            }
            if (empty($fee_heads)) {
                throw new Exception('No fee policy is configured for your class yet.');
            }

            $created = 0;
            $db->beginTransaction();
            foreach ($fee_heads as $fee) {
                $checkSql = "SELECT id FROM fee_collections WHERE student_id = ? AND fee_type = ? AND DATE_FORMAT(payment_date, '%Y-%m') = ? LIMIT 1";
                $checkParams = [(int)$student['id'], $fee['fee_type'], $currentMonth];
                if (columnExists($db, 'fee_collections', 'fee_structure_id')) {
                    $checkSql = "SELECT id FROM fee_collections WHERE student_id = ? AND fee_structure_id = ? AND DATE_FORMAT(payment_date, '%Y-%m') = ? LIMIT 1";
                    $checkParams = [(int)$student['id'], (int)$fee['id'], $currentMonth];
                }
                $check = $db->prepare($checkSql);
                $check->execute($checkParams);
                if ($check->fetchColumn()) {
                    continue;
                }

                $columns = [];
                $params = [];
                $addColumn = static function ($column, $value) use ($db, &$columns, &$params) {
                    if (columnExists($db, 'fee_collections', $column)) {
                        $columns[] = "`$column`";
                        $params[] = $value;
                    }
                };

                $addColumn('student_id', (int)$student['id']);
                $addColumn('fee_structure_id', (int)$fee['id']);
                $addColumn('fee_type', $fee['fee_type']);
                $addColumn('amount', (float)$fee['amount']);
                $addColumn('amount_paid', 0);
                $addColumn('paid_amount', 0);
                $addColumn('payment_date', $currentMonthStart);
                $addColumn('payment_method', 'Challan');
                $addColumn('transaction_id', 'CH-' . date('Ym') . '-' . (int)$student['id'] . '-' . (int)$fee['id']);
                $addColumn('status', 'Pending');
                $addColumn('due_date', $currentDueDate);
                $addColumn('academic_year', $academicYear);
                $addColumn('remarks', 'Self-generated student fee challan');
                $addColumn('collected_by', null);

                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $stmt = $db->prepare("INSERT INTO fee_collections (" . implode(', ', $columns) . ") VALUES ($placeholders)");
                $stmt->execute($params);
                $created++;
            }
            $db->commit();

            setFlashMessage($created > 0 ? 'success' : 'info', $created > 0 ? 'Fee challan generated successfully.' : 'Current month challan already exists.');
            redirect('index.php');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Student Challan Error: ' . $e->getMessage());
            setFlashMessage('error', $e->getMessage());
            redirect('index.php');
        }
    }

    if ($student && tableExists($db, 'fee_collections')) {
        $studentFeeAmountColumn = firstExistingColumn($db, 'fee_collections', ['amount_paid', 'paid_amount', 'amount']) ?: 'amount_paid';
        $studentFeeTypeColumn = firstExistingColumn($db, 'fee_collections', ['fee_type', 'fee_name']);
        $studentFeeLabelExpr = $studentFeeTypeColumn ? "fc.`$studentFeeTypeColumn`" : "'Fee'";
        $debitColumn = firstExistingColumn($db, 'fee_collections', ['amount']) ?: $studentFeeAmountColumn;
        $paidColumn = firstExistingColumn($db, 'fee_collections', ['paid_amount', 'amount_paid']) ?: $studentFeeAmountColumn;

        $stmt = $db->prepare("
            SELECT fc.payment_date, fc.payment_method, fc.status,
                   $studentFeeLabelExpr AS fee_label,
                   fc.`$studentFeeAmountColumn` AS collected_amount
            FROM fee_collections fc
            WHERE fc.student_id = ?
            ORDER BY fc.payment_date DESC, fc.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([(int)$student['id']]);
        $my_payments = $stmt->fetchAll();

        foreach ($my_payments as $payment) {
            if (($payment['status'] ?? '') === 'Paid') {
                $total_paid += (float)($payment['collected_amount'] ?? 0);
            }
        }

        $stmt = $db->prepare("
            SELECT fc.id, fc.payment_date, fc.due_date, fc.payment_method, fc.status,
                   $studentFeeLabelExpr AS fee_label,
                   fc.`$debitColumn` AS challan_amount,
                   fc.`$paidColumn` AS paid_amount
            FROM fee_collections fc
            WHERE fc.student_id = ?
            ORDER BY fc.payment_date DESC, fc.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([(int)$student['id']]);
        $my_challans = $stmt->fetchAll();
    }

    $page_title = "My Fee Status";
    include '../../includes/header.php';
    ?>

    <div class="row mb-4">
        <div class="col-12">
            <h2 class="page-title">My Fee Status</h2>
        </div>
    </div>

    <?php if (!$student): ?>
        <div class="alert alert-warning">Your student profile is not linked to this portal account yet.</div>
    <?php else: ?>
        <div class="row mb-4">
            <div class="col-md-6 col-xl-4">
                <div class="dashboard-card">
                    <div class="card-info">
                        <p>Student</p>
                        <h3><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])); ?></h3>
                    </div>
                    <div class="card-icon blue">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="dashboard-card">
                    <div class="card-info">
                        <p>Total Paid</p>
                        <h3>PKR <?php echo number_format($total_paid, 2); ?></h3>
                    </div>
                    <div class="card-icon green">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 border-0 rounded-4" id="generate-challan">
            <div class="card-header bg-white border-0 p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1 fw-bold">Generate Fee Challan</h5>
                    <div class="text-muted small">Create your current month fee challan from the active fee policy for your class.</div>
                </div>
                <form method="POST" class="d-inline">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="generate_student_challan">
                    <button type="submit" class="btn btn-primary" <?= empty($fee_heads) ? 'disabled' : '' ?>>
                        <i class="fas fa-file-invoice me-1"></i> Generate Challan
                    </button>
                </form>
            </div>
            <div class="card-body p-4">
                <?php if (empty($fee_heads)): ?>
                    <div class="alert alert-warning mb-0">No fee policy is configured for your class yet. Please contact accounts office.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($fee_heads as $fee): ?>
                            <div class="col-md-4">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="fw-bold"><?php echo htmlspecialchars($fee['fee_type']); ?></div>
                                    <div class="fs-5 fw-bold text-navy">PKR <?php echo number_format((float)$fee['amount'], 2); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($fee['frequency'] ?? 'Monthly'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a class="btn btn-outline-primary mt-3" target="_blank" href="challan.php?month=<?php echo urlencode($currentMonth); ?>">
                        <i class="fas fa-print me-1"></i> Print Current Month Challan
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">My Fee Challans & Payments</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Due Date</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th class="text-end">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_challans as $payment): ?>
                                <?php
                                    $paymentMonth = !empty($payment['payment_date']) ? date('Y-m', strtotime($payment['payment_date'])) : $currentMonth;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['fee_label'] ?? 'Fee'); ?></td>
                                    <td>PKR <?php echo number_format((float)($payment['challan_amount'] ?? 0), 2); ?></td>
                                    <td>PKR <?php echo number_format((float)($payment['paid_amount'] ?? 0), 2); ?></td>
                                    <td><?php echo !empty($payment['due_date']) ? date('d M Y', strtotime($payment['due_date'])) : '-'; ?></td>
                                    <td><?php echo !empty($payment['payment_date']) ? date('d M Y', strtotime($payment['payment_date'])) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method'] ?? '-'); ?></td>
                                    <td><span class="badge bg-<?php echo ($payment['status'] ?? '') === 'Paid' ? 'success' : 'warning text-dark'; ?>"><?php echo htmlspecialchars($payment['status'] ?? 'Pending'); ?></span></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="challan.php?month=<?php echo urlencode($paymentMonth); ?>">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($my_challans)): ?>
                                <tr><td colspan="8" class="text-center text-muted">No fee challans or collections found for your profile.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    include '../../includes/footer.php';
    exit;
}

requireRole(['admin', 'owner']);

// Get fee statistics
$stats = [];
$feeAmountColumn = firstExistingColumn($db, 'fee_collections', ['amount_paid', 'paid_amount', 'amount']) ?: 'amount_paid';
$feeTypeColumn = firstExistingColumn($db, 'fee_collections', ['fee_type']);
$feeCollectionStructureColumn = columnExists($db, 'fee_collections', 'fee_structure_id') ? 'fee_structure_id' : null;
$studentCodeExpr = columnExists($db, 'students', 'registration_number')
    ? "COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), CONCAT('STD-', s.id))"
    : (columnExists($db, 'students', 'student_id') ? "COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.student_id, ''), CONCAT('STD-', s.id))" : "CONCAT('STD-', s.id)");
$feeJoin = $feeCollectionStructureColumn && tableExists($db, 'fee_structure') ? "LEFT JOIN fee_structure fs ON fs.id = fc.`$feeCollectionStructureColumn`" : "";
$feeLabelExpr = $feeTypeColumn
    ? "fc.`$feeTypeColumn`"
    : ($feeJoin ? "COALESCE(fs.fee_type, 'Fee')" : "'Fee'");

// Total fee collected this month
$stmt = $db->prepare("SELECT SUM(`$feeAmountColumn`) as total FROM fee_collections WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status = 'Paid'");
$stmt->execute();
$stats['monthly_collection'] = $stmt->fetch()['total'] ?? 0;

// Total outstanding fees
$stats['outstanding'] = 0;
if (tableExists($db, 'fee_structure') && tableExists($db, 'students')) {
    $paidTotalStmt = $db->prepare("SELECT COALESCE(SUM(`$feeAmountColumn`), 0) FROM fee_collections WHERE status = 'Paid'");
    $paidTotalStmt->execute();
    $paidTotal = (float)$paidTotalStmt->fetchColumn();

    $potentialStmt = $db->prepare("
        SELECT COALESCE(SUM(fs.amount), 0) * (SELECT COUNT(*) FROM students) AS total_potential
        FROM fee_structure fs
    ");
    $potentialStmt->execute();
    $stats['outstanding'] = max(0, (float)$potentialStmt->fetchColumn() - $paidTotal);
}

// Today's collections
$stmt = $db->prepare("SELECT SUM(`$feeAmountColumn`) as total FROM fee_collections WHERE payment_date = CURDATE() AND status = 'Paid'");
$stmt->execute();
$stats['today_collection'] = $stmt->fetch()['total'] ?? 0;

// Recent payments
$stmt = $db->prepare("
    SELECT fc.*, s.first_name, s.last_name,
           $studentCodeExpr as student_code,
           s.class, s.section, $feeLabelExpr AS fee_label, fc.`$feeAmountColumn` AS collected_amount
    FROM fee_collections fc
    JOIN students s ON fc.student_id = s.id
    $feeJoin
    WHERE fc.status = 'Paid'
    ORDER BY fc.payment_date DESC, fc.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_payments = $stmt->fetchAll();

// Pending fees by class
$pending_fees = [];
if (tableExists($db, 'fee_structure') && tableExists($db, 'students')) {
    $feeMatch = $feeCollectionStructureColumn
        ? "fc.`$feeCollectionStructureColumn` = fs.id"
        : ($feeTypeColumn ? "fc.`$feeTypeColumn` = fs.fee_type" : "1=0");
    $stmt = $db->prepare("
        SELECT s.class, COUNT(DISTINCT s.id) as pending_count, SUM(fs.amount) as total_amount
        FROM students s
        CROSS JOIN fee_structure fs
        LEFT JOIN fee_collections fc ON s.id = fc.student_id AND $feeMatch AND fc.status = 'Paid'
        WHERE fc.id IS NULL
        GROUP BY s.class
        ORDER BY s.class
    ");
    $stmt->execute();
    $pending_fees = $stmt->fetchAll();
}

$page_title = "Fee Management";
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="page-title">Fee Management Overview</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-6 col-xl-3">
        <div class="dashboard-card">
            <div class="card-info">
                <p>Monthly Collection</p>
                <h3>Rs <?php echo number_format($stats['monthly_collection']); ?></h3>
            </div>
            <div class="card-icon green">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="dashboard-card">
            <div class="card-info">
                <p>Outstanding Fees</p>
                <h3>Rs <?php echo number_format($stats['outstanding']); ?></h3>
            </div>
            <div class="card-icon red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="dashboard-card">
            <div class="card-info">
                <p>Today's Collection</p>
                <h3>Rs <?php echo number_format($stats['today_collection']); ?></h3>
            </div>
            <div class="card-icon yellow">
                <i class="fas fa-money-bill-wave"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="dashboard-card">
            <div class="card-info">
                <p>Total Students</p>
                <h3><?php echo $db->query("SELECT COUNT(*) FROM students")->fetchColumn(); ?></h3>
            </div>
            <div class="card-icon blue">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
</div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="collect.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Collect Fee
                        </a>
                        <a href="structure.php" class="btn btn-primary">
                            <i class="fas fa-cogs"></i> Manage Fee Structure
                        </a>
                        <a href="total_transactions.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-right-arrow-left"></i> Total Transactions
                        </a>
                        <a href="reports.php" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> View Reports
                        </a>
                        <a href="pending.php" class="btn btn-warning">
                            <i class="fas fa-clock"></i> Pending Fees
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Fee Collections</h6>
                    <a href="collections.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($payment['student_code']); ?></small></td>
                                    <td><?php echo $payment['class'] . '-' . $payment['section']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['fee_label']); ?></td>
                                    <td>PKR <?php echo number_format($payment['collected_amount'], 2); ?></td>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><span class="badge bg-success"><?php echo $payment['status']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No fee collections found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Fees by Class -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pending Fees by Class</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Students with Pending Fees</th>
                                    <th>Total Outstanding Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_fees as $pending): ?>
                                <tr>
                                    <td><?php echo $pending['class']; ?></td>
                                    <td><?php echo $pending['pending_count']; ?> students</td>
                                    <td>PKR <?php echo number_format($pending['total_amount'], 2); ?></td>
                                    <td>
                                        <a href="pending.php?class=<?php echo urlencode($pending['class']); ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($pending_fees)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No pending fees found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
