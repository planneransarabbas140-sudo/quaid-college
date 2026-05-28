<?php
// File: modules/accounts/ledger.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'accounts', 'accountant']);

$db = (new Database())->getConnection();
$hasTransactions = tableExists($db, 'accounts_transactions');
$hasPaymentMethod = $hasTransactions && columnExists($db, 'accounts_transactions', 'payment_method');

function acc_ledger_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function acc_ledger_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function acc_ledger_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

$startDate = acc_ledger_valid_date($_GET['start_date'] ?? '') ? $_GET['start_date'] : '';
$endDate = acc_ledger_valid_date($_GET['end_date'] ?? '') ? $_GET['end_date'] : '';
$category = trim((string)($_GET['category'] ?? ''));
$categories = [];
$entries = [];
$monthlySummary = [];
$closingBalance = 0;
$totalDebit = 0;
$totalCredit = 0;

if ($hasTransactions) {
    $categories = $db->query("SELECT DISTINCT category FROM accounts_transactions WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

    $where = ['1=1'];
    $params = [];
    if ($startDate !== '') {
        $where[] = 'transaction_date >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $where[] = 'transaction_date <= ?';
        $params[] = $endDate;
    }
    if ($category !== '') {
        $where[] = 'category = ?';
        $params[] = $category;
    }
    $whereSql = implode(' AND ', $where);
    $paymentSelect = $hasPaymentMethod ? "COALESCE(payment_method, 'Cash') AS payment_method" : "'Cash' AS payment_method";

    $stmt = $db->prepare("
        SELECT id, transaction_date, transaction_type, category, description, amount, $paymentSelect
        FROM accounts_transactions
        WHERE $whereSql
        ORDER BY transaction_date ASC, id ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $debit = $row['transaction_type'] === 'Expense' ? (float)$row['amount'] : 0;
        $credit = $row['transaction_type'] === 'Income' ? (float)$row['amount'] : 0;
        $closingBalance += $credit - $debit;
        $totalDebit += $debit;
        $totalCredit += $credit;
        $row['debit'] = $debit;
        $row['credit'] = $credit;
        $row['running_balance'] = $closingBalance;
        $entries[] = $row;
    }

    $stmt = $db->prepare("
        SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
               SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END) AS debit,
               SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END) AS credit,
               SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE -amount END) AS balance
        FROM accounts_transactions
        WHERE $whereSql
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month_key DESC
    ");
    $stmt->execute($params);
    $monthlySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Accounts Ledger';
include '../../includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
    <div>
        <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Accounts</a>
        <h2 class="page-title mb-1">Ledger Summary</h2>
        <div class="text-muted">Running debit, credit, and balance ledger for manual account transactions.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="reports.php" class="btn btn-outline-primary"><i class="fas fa-chart-column me-1"></i>Reports</a>
        <a href="balance-sheet.php" class="btn btn-outline-primary"><i class="fas fa-scale-balanced me-1"></i>Balance Sheet</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<?php if (!$hasTransactions): ?><div class="alert alert-warning">The <code>accounts_transactions</code> table is missing.</div><?php endif; ?>

<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label small fw-bold">Start Date</label><input type="date" name="start_date" value="<?= acc_ledger_h($startDate) ?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label small fw-bold">End Date</label><input type="date" name="end_date" value="<?= acc_ledger_h($endDate) ?>" class="form-control"></div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?><option value="<?= acc_ledger_h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= acc_ledger_h($cat) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit">Apply</button><a href="ledger.php" class="btn btn-outline-secondary flex-fill">Reset</a></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total Debit</div><h4 class="text-danger fw-bold mb-0"><?= acc_ledger_money($totalDebit) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total Credit</div><h4 class="text-success fw-bold mb-0"><?= acc_ledger_money($totalCredit) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Closing Balance</div><h4 class="<?= $closingBalance >= 0 ? 'text-primary' : 'text-danger' ?> fw-bold mb-0"><?= acc_ledger_money($closingBalance) ?></h4></div></div></div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Monthly Ledger Summary</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light"><tr><th>Month</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Net</th></tr></thead>
                <tbody>
                    <?php if (!$monthlySummary): ?><tr><td colspan="4" class="text-center text-muted py-4">No monthly entries found.</td></tr><?php endif; ?>
                    <?php foreach ($monthlySummary as $row): ?><tr><td><?= acc_ledger_h(date('M Y', strtotime($row['month_key'] . '-01'))) ?></td><td class="text-end text-danger fw-bold"><?= acc_ledger_money($row['debit']) ?></td><td class="text-end text-success fw-bold"><?= acc_ledger_money($row['credit']) ?></td><td class="text-end fw-bold <?= (float)$row['balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= acc_ledger_money($row['balance']) ?></td></tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Ledger Entries</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $entries ? 'datatable' : '' ?>">
                <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Description</th><th>Method</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Running Balance</th></tr></thead>
                <tbody>
                    <?php if (!$entries): ?><tr><td colspan="7" class="text-center text-muted py-4">No ledger entries found.</td></tr><?php endif; ?>
                    <?php foreach ($entries as $entry): ?>
                        <tr><td><?= acc_ledger_h(date('d M Y', strtotime($entry['transaction_date']))) ?></td><td><?= acc_ledger_h($entry['category']) ?></td><td><?= acc_ledger_h($entry['description']) ?></td><td><?= acc_ledger_h($entry['payment_method']) ?></td><td class="text-end text-danger fw-bold"><?= $entry['debit'] > 0 ? acc_ledger_money($entry['debit']) : '-' ?></td><td class="text-end text-success fw-bold"><?= $entry['credit'] > 0 ? acc_ledger_money($entry['credit']) : '-' ?></td><td class="text-end fw-bold <?= $entry['running_balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= acc_ledger_money($entry['running_balance']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:#0f2d48; }
    .btn-primary { background:#4ec2b5; border-color:#4ec2b5; color:#0f2d48; font-weight:600; }
    .btn-outline-primary { color:#0f2d48; border-color:#4ec2b5; }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; } }
</style>

<?php include '../../includes/footer.php'; ?>
