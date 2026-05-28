<?php
// File: modules/accounts/reports.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'accounts', 'accountant']);

$db = (new Database())->getConnection();
$hasTransactions = tableExists($db, 'accounts_transactions');
$hasPaymentMethod = $hasTransactions && columnExists($db, 'accounts_transactions', 'payment_method');
$hasTransactionId = $hasTransactions && columnExists($db, 'accounts_transactions', 'transaction_id');

function acc_report_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function acc_report_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function acc_report_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

$startDate = acc_report_valid_date($_GET['start_date'] ?? '') ? $_GET['start_date'] : '';
$endDate = acc_report_valid_date($_GET['end_date'] ?? '') ? $_GET['end_date'] : '';
$category = trim((string)($_GET['category'] ?? ''));
$type = in_array($_GET['type'] ?? '', ['Income', 'Expense'], true) ? $_GET['type'] : '';

$categories = [];
$transactions = [];
$categorySummary = [];
$summary = ['debit' => 0, 'credit' => 0, 'balance' => 0, 'entries' => 0];

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
    if ($type !== '') {
        $where[] = 'transaction_type = ?';
        $params[] = $type;
    }
    $whereSql = implode(' AND ', $where);
    $paymentSelect = $hasPaymentMethod ? "COALESCE(payment_method, 'Cash') AS payment_method" : "'Cash' AS payment_method";
    $referenceSelect = $hasTransactionId ? 'transaction_id' : "'' AS transaction_id";

    $stmt = $db->prepare("
        SELECT id, transaction_date, transaction_type, category, description, amount,
               $paymentSelect, $referenceSelect
        FROM accounts_transactions
        WHERE $whereSql
        ORDER BY transaction_date DESC, id DESC
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END) AS debit,
            SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END) AS credit,
            COUNT(*) AS entries
        FROM accounts_transactions
        WHERE $whereSql
    ");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['debit'] = (float)($row['debit'] ?? 0);
    $summary['credit'] = (float)($row['credit'] ?? 0);
    $summary['balance'] = $summary['credit'] - $summary['debit'];
    $summary['entries'] = (int)($row['entries'] ?? 0);

    $stmt = $db->prepare("
        SELECT category,
               SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END) AS credit,
               SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END) AS debit,
               SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE -amount END) AS balance
        FROM accounts_transactions
        WHERE $whereSql
        GROUP BY category
        ORDER BY ABS(balance) DESC, category ASC
    ");
    $stmt->execute($params);
    $categorySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Accounts Reports';
include '../../includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
    <div>
        <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Accounts</a>
        <h2 class="page-title mb-1">Debit / Credit Reports</h2>
        <div class="text-muted">Filter transaction reports by date range, category, and type.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="ledger.php" class="btn btn-outline-primary"><i class="fas fa-book me-1"></i>Ledger</a>
        <a href="balance-sheet.php" class="btn btn-outline-primary"><i class="fas fa-scale-balanced me-1"></i>Balance Sheet</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<?php if (!$hasTransactions): ?>
    <div class="alert alert-warning">The <code>accounts_transactions</code> table is missing.</div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label small fw-bold">Start Date</label><input type="date" name="start_date" value="<?= acc_report_h($startDate) ?>" class="form-control"></div>
            <div class="col-md-2"><label class="form-label small fw-bold">End Date</label><input type="date" name="end_date" value="<?= acc_report_h($endDate) ?>" class="form-control"></div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= acc_report_h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= acc_report_h($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="Income" <?= $type === 'Income' ? 'selected' : '' ?>>Credit / Income</option>
                    <option value="Expense" <?= $type === 'Expense' ? 'selected' : '' ?>>Debit / Expense</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
                <a href="reports.php" class="btn btn-outline-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Entries</div><h4 class="fw-bold mb-0"><?= (int)$summary['entries'] ?></h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Credit</div><h4 class="fw-bold text-success mb-0"><?= acc_report_money($summary['credit']) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Debit</div><h4 class="fw-bold text-danger mb-0"><?= acc_report_money($summary['debit']) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Balance</div><h4 class="fw-bold <?= $summary['balance'] >= 0 ? 'text-primary' : 'text-danger' ?> mb-0"><?= acc_report_money($summary['balance']) ?></h4></div></div></div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Category Summary</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light"><tr><th>Category</th><th class="text-end">Credit</th><th class="text-end">Debit</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    <?php if (!$categorySummary): ?><tr><td colspan="4" class="text-center text-muted py-4">No category summary found.</td></tr><?php endif; ?>
                    <?php foreach ($categorySummary as $row): ?>
                        <tr>
                            <td><?= acc_report_h($row['category']) ?></td>
                            <td class="text-end text-success fw-bold"><?= acc_report_money($row['credit']) ?></td>
                            <td class="text-end text-danger fw-bold"><?= acc_report_money($row['debit']) ?></td>
                            <td class="text-end fw-bold <?= (float)$row['balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= acc_report_money($row['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Transactions</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $transactions ? 'datatable' : '' ?>">
                <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Category</th><th>Method</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                    <?php if (!$transactions): ?><tr><td colspan="7" class="text-center text-muted py-4">No transactions found.</td></tr><?php endif; ?>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?= acc_report_h(date('d M Y', strtotime($tx['transaction_date']))) ?></td>
                            <td><span class="badge <?= $tx['transaction_type'] === 'Income' ? 'bg-success' : 'bg-danger' ?>"><?= acc_report_h($tx['transaction_type']) ?></span></td>
                            <td><?= acc_report_h($tx['category']) ?></td>
                            <td><?= acc_report_h($tx['payment_method']) ?></td>
                            <td><?= acc_report_h($tx['description']) ?></td>
                            <td class="text-end text-danger fw-bold"><?= $tx['transaction_type'] === 'Expense' ? acc_report_money($tx['amount']) : '-' ?></td>
                            <td class="text-end text-success fw-bold"><?= $tx['transaction_type'] === 'Income' ? acc_report_money($tx['amount']) : '-' ?></td>
                        </tr>
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
