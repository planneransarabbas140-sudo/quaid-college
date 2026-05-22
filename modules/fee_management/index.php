<?php
// File: modules/fee_management/index.php - Fee Management Dashboard
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

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
