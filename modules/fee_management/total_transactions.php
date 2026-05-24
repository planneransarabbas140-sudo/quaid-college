<?php
// File: modules/fee_management/total_transactions.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

function feeTransactionsH($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function feeTransactionsMoney($amount) {
    return 'PKR ' . number_format((float)$amount, 2);
}

function getStudentTransactionStatementMap(PDO $conn, array $studentIds) {
    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
    if (!$studentIds || !tableExists($conn, 'fee_collections')) {
        return [];
    }

    $debitExpr = feeTransactionDebitExpression($conn);
    $creditExpr = feeTransactionCreditExpression($conn);
    $structureJoin = feeTransactionStructureJoin($conn);
    $structureLabel = $structureJoin !== ''
        && columnExists($conn, 'fee_structure', 'fee_type')
        ? 'fs.fee_type'
        : "'Fee'";
    $feeLabelExpr = columnExists($conn, 'fee_collections', 'fee_type')
        ? "COALESCE(NULLIF(fc.fee_type, ''), $structureLabel, 'Fee')"
        : "COALESCE($structureLabel, 'Fee')";
    $dateExpr = columnExists($conn, 'fee_collections', 'payment_date') ? 'fc.payment_date' : 'DATE(fc.created_at)';
    $statusExpr = columnExists($conn, 'fee_collections', 'status') ? 'fc.status' : "'Posted'";
    $paymentExpr = columnExists($conn, 'fee_collections', 'payment_method') ? 'fc.payment_method' : "'-'";

    $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
    $stmt = $conn->prepare("
        SELECT fc.id,
               fc.student_id,
               $dateExpr AS entry_date,
               $feeLabelExpr AS fee_label,
               $paymentExpr AS payment_method,
               $statusExpr AS status,
               $debitExpr AS debit,
               $creditExpr AS credit
        FROM fee_collections fc
        JOIN students s ON s.id = fc.student_id
        $structureJoin
        WHERE fc.student_id IN ($placeholders)
        ORDER BY fc.student_id ASC, entry_date ASC, fc.id ASC
    ");
    $stmt->execute($studentIds);

    $map = [];
    $runningBalances = [];
    foreach ($stmt->fetchAll() as $row) {
        $studentId = (int)$row['student_id'];
        $runningBalances[$studentId] = ($runningBalances[$studentId] ?? 0)
            + (float)$row['debit']
            - (float)$row['credit'];
        $row['running_balance'] = $runningBalances[$studentId];
        $map[$studentId][] = $row;
    }

    return $map;
}

function groupStudentTransactionRows(array $rows, $familyGroupingEnabled) {
    $groups = [];
    foreach ($rows as $row) {
        $familyId = trim((string)($row['family_id'] ?? ''));
        $key = $familyGroupingEnabled && $familyId !== ''
            ? 'family-' . $familyId
            : 'student-' . (int)$row['student_pk'];
        $groups[$key][] = $row;
    }
    return $groups;
}

$selectedClass = trim((string)($_GET['class_id'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$summary = getTransactionSummary($db);
$transactionStudents = getTotalStudentsWithTransactions($db);
$registerRows = getStudentTransactionRegister($db, $selectedClass, $search);
$classList = getClassList($db);
$statementMap = getStudentTransactionStatementMap($db, array_column($registerRows, 'student_pk'));
$familyGroupingEnabled = columnExists($db, 'students', 'family_id')
    && (tableExists($db, 'family_accounts') || tableExists($db, 'families'));
$rowGroups = groupStudentTransactionRows($registerRows, $familyGroupingEnabled);
$filteredTotals = ['opening' => 0, 'debit' => 0, 'credit' => 0, 'pending' => 0, 'tx' => 0];

foreach ($registerRows as $row) {
    $filteredTotals['opening'] += (float)$row['opening_balance'];
    $filteredTotals['debit'] += (float)$row['total_debit'];
    $filteredTotals['credit'] += (float)$row['total_credit'];
    $filteredTotals['pending'] += (float)$row['live_pending'];
    $filteredTotals['tx'] += (int)$row['transaction_count'];
}

$page_title = 'Total Transactions';
include '../../includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Fee Management
        </a>
        <h2 class="page-title mb-1">Total Transactions</h2>
        <div class="text-muted">Student fee debit, collections, and live pending balances from the current fee records.</div>
    </div>
    <a href="collect.php" class="btn btn-success rounded-pill px-4">
        <i class="fas fa-money-bill-wave me-2"></i>Collect Fee
    </a>
</div>

<?php include 'fee_tabs.php'; ?>
<?php displayFlashMessage(); ?>

<div class="row g-3 mb-4">
    <div class="col-xl col-md-6">
        <div class="dashboard-card h-100">
            <div class="card-info">
                <p>Total Students</p>
                <h3><?php echo number_format($transactionStudents); ?></h3>
            </div>
            <div class="card-icon blue"><i class="fas fa-user-graduate"></i></div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="dashboard-card h-100">
            <div class="card-info">
                <p>Total Debit</p>
                <h3><?php echo feeTransactionsMoney($summary['debit']); ?></h3>
            </div>
            <div class="card-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="dashboard-card h-100">
            <div class="card-info">
                <p>Total Credit</p>
                <h3><?php echo feeTransactionsMoney($summary['credit']); ?></h3>
            </div>
            <div class="card-icon green"><i class="fas fa-circle-check"></i></div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="dashboard-card h-100">
            <div class="card-info">
                <p>Live Pending</p>
                <h3><?php echo feeTransactionsMoney($summary['pending']); ?></h3>
            </div>
            <div class="card-icon red"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="dashboard-card h-100">
            <div class="card-info">
                <p>Total Transactions</p>
                <h3><?php echo number_format($summary['total_tx']); ?></h3>
            </div>
            <div class="card-icon yellow"><i class="fas fa-arrow-right-arrow-left"></i></div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label fw-bold">All Classes</label>
                <select name="class_id" class="form-select">
                    <option value="">All Classes</option>
                    <?php foreach ($classList as $class): ?>
                        <option value="<?php echo feeTransactionsH($class['id']); ?>" <?php echo $selectedClass === (string)$class['id'] ? 'selected' : ''; ?>>
                            <?php echo feeTransactionsH($class['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-5">
                <label class="form-label fw-bold">Student Search</label>
                <input type="text" name="search" class="form-control" value="<?php echo feeTransactionsH($search); ?>" placeholder="Name, student ID, registration, or roll number">
            </div>
            <div class="col-lg-3">
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="total_transactions.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0 fw-bold">Student Transaction Register</h5>
        <span class="text-muted small"><?php echo number_format(count($registerRows)); ?> student rows in current filter</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Class</th>
                        <th>Opening / Admission</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Live Pending</th>
                        <th class="text-center">Tx</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$registerRows): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                No student fee transactions match the selected filters.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rowGroups as $groupRows): ?>
                        <?php $familyTotals = ['opening' => 0, 'debit' => 0, 'credit' => 0, 'pending' => 0, 'tx' => 0]; ?>
                        <?php foreach ($groupRows as $row): ?>
                            <?php
                            $studentPk = (int)$row['student_pk'];
                            $statementId = 'statement-' . $studentPk;
                            $familyTotals['opening'] += (float)$row['opening_balance'];
                            $familyTotals['debit'] += (float)$row['total_debit'];
                            $familyTotals['credit'] += (float)$row['total_credit'];
                            $familyTotals['pending'] += (float)$row['live_pending'];
                            $familyTotals['tx'] += (int)$row['transaction_count'];
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?php echo feeTransactionsH($row['student_name']); ?></div>
                                    <small class="text-muted"><?php echo feeTransactionsH($row['student_id']); ?></small>
                                </td>
                                <td>
                                    <div><?php echo feeTransactionsH($row['class_name'] ?: '-'); ?></div>
                                    <small class="text-muted">Section <?php echo feeTransactionsH($row['section'] ?: '-'); ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo feeTransactionsMoney($row['opening_balance']); ?></div>
                                    <small class="text-muted">
                                        Admitted <?php echo $row['admission_date'] ? feeTransactionsH(date('d M Y', strtotime($row['admission_date']))) : '-'; ?>
                                    </small>
                                </td>
                                <td class="text-end fw-bold text-danger"><?php echo feeTransactionsMoney($row['total_debit']); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo feeTransactionsMoney($row['total_credit']); ?></td>
                                <td class="text-end fw-bold <?php echo (float)$row['live_pending'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo feeTransactionsMoney($row['live_pending']); ?>
                                </td>
                                <td class="text-center"><?php echo number_format((int)$row['transaction_count']); ?></td>
                                <td class="text-end pe-4">
                                    <a href="collect.php?student_id=<?php echo $studentPk; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-money-bill-wave me-1"></i>Collect Fee
                                    </a>
                                    <a href="../../vouchers.php?student_id=<?php echo $studentPk; ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="fas fa-file-invoice-dollar me-1"></i>Generate Voucher
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo feeTransactionsH($statementId); ?>" aria-expanded="false" aria-controls="<?php echo feeTransactionsH($statementId); ?>">
                                        <i class="fas fa-scale-balanced me-1"></i>View Statement
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse bg-light" id="<?php echo feeTransactionsH($statementId); ?>">
                                <td colspan="8" class="px-4 py-3">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-white">
                                            <strong><?php echo feeTransactionsH($row['student_name']); ?></strong>
                                            <span class="text-muted ms-2">Statement register</span>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Fee Entry</th>
                                                            <th>Method</th>
                                                            <th class="text-end">Debit</th>
                                                            <th class="text-end">Credit</th>
                                                            <th class="text-end">Balance</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($statementMap[$studentPk] ?? [] as $entry): ?>
                                                            <tr>
                                                                <td><?php echo $entry['entry_date'] ? feeTransactionsH(date('d M Y', strtotime($entry['entry_date']))) : '-'; ?></td>
                                                                <td><?php echo feeTransactionsH($entry['fee_label']); ?></td>
                                                                <td><?php echo feeTransactionsH($entry['payment_method']); ?></td>
                                                                <td class="text-end text-danger"><?php echo feeTransactionsMoney($entry['debit']); ?></td>
                                                                <td class="text-end text-success"><?php echo feeTransactionsMoney($entry['credit']); ?></td>
                                                                <td class="text-end fw-bold"><?php echo feeTransactionsMoney($entry['running_balance']); ?></td>
                                                                <td><?php echo feeTransactionsH($entry['status']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($familyGroupingEnabled && !empty($groupRows[0]['family_id'])): ?>
                            <tr class="table-info fw-bold">
                                <td colspan="3" class="ps-4">Family Total - Family #<?php echo feeTransactionsH($groupRows[0]['family_id']); ?></td>
                                <td class="text-end"><?php echo feeTransactionsMoney($familyTotals['debit']); ?></td>
                                <td class="text-end"><?php echo feeTransactionsMoney($familyTotals['credit']); ?></td>
                                <td class="text-end"><?php echo feeTransactionsMoney($familyTotals['pending']); ?></td>
                                <td class="text-center"><?php echo number_format($familyTotals['tx']); ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($registerRows): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="ps-4">Filtered Total</td>
                            <td class="text-end"><?php echo feeTransactionsMoney($filteredTotals['debit']); ?></td>
                            <td class="text-end"><?php echo feeTransactionsMoney($filteredTotals['credit']); ?></td>
                            <td class="text-end"><?php echo feeTransactionsMoney($filteredTotals['pending']); ?></td>
                            <td class="text-center"><?php echo number_format($filteredTotals['tx']); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4 mb-0">
    Opening balance is shown from the existing student admission reference. The current schema has no separate opening-balance ledger table, so fee debit and credit totals come from the existing fee collection records.
</div>

<?php include '../../includes/footer.php'; ?>
