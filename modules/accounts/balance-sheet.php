<?php
// File: modules/accounts/balance-sheet.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'accounts', 'accountant']);

$db = (new Database())->getConnection();
$hasTransactions = tableExists($db, 'accounts_transactions');

function acc_balance_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function acc_balance_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function acc_balance_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

$startDate = acc_balance_valid_date($_GET['start_date'] ?? '') ? $_GET['start_date'] : '';
$endDate = acc_balance_valid_date($_GET['end_date'] ?? '') ? $_GET['end_date'] : '';
$incomeRows = [];
$expenseRows = [];
$monthlyRows = [];
$income = 0;
$expense = 0;
$balance = 0;

if ($hasTransactions) {
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
    $whereSql = implode(' AND ', $where);

    $stmt = $db->prepare("SELECT category, SUM(amount) AS total FROM accounts_transactions WHERE transaction_type = 'Income' AND $whereSql GROUP BY category ORDER BY total DESC");
    $stmt->execute($params);
    $incomeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT category, SUM(amount) AS total FROM accounts_transactions WHERE transaction_type = 'Expense' AND $whereSql GROUP BY category ORDER BY total DESC");
    $stmt->execute($params);
    $expenseRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
               SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END) AS income,
               SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END) AS expense,
               SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE -amount END) AS balance
        FROM accounts_transactions
        WHERE $whereSql
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month_key DESC
    ");
    $stmt->execute($params);
    $monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($incomeRows as $row) {
        $income += (float)$row['total'];
    }
    foreach ($expenseRows as $row) {
        $expense += (float)$row['total'];
    }
    $balance = $income - $expense;
}

$page_title = 'Balance Sheet';
include '../../includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
    <div>
        <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Accounts</a>
        <h2 class="page-title mb-1">Balance Sheet</h2>
        <div class="text-muted">Income, expense, and net balance for the selected period.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="reports.php" class="btn btn-outline-primary"><i class="fas fa-chart-column me-1"></i>Reports</a>
        <a href="ledger.php" class="btn btn-outline-primary"><i class="fas fa-book me-1"></i>Ledger</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<?php if (!$hasTransactions): ?><div class="alert alert-warning">The <code>accounts_transactions</code> table is missing.</div><?php endif; ?>

<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label small fw-bold">Start Date</label><input type="date" name="start_date" value="<?= acc_balance_h($startDate) ?>" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small fw-bold">End Date</label><input type="date" name="end_date" value="<?= acc_balance_h($endDate) ?>" class="form-control"></div>
            <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit">Apply</button><a href="balance-sheet.php" class="btn btn-outline-secondary flex-fill">Reset</a></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Income</div><h4 class="text-success fw-bold mb-0"><?= acc_balance_money($income) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Expense</div><h4 class="text-danger fw-bold mb-0"><?= acc_balance_money($expense) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Net Balance</div><h4 class="<?= $balance >= 0 ? 'text-primary' : 'text-danger' ?> fw-bold mb-0"><?= acc_balance_money($balance) ?></h4></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Income</h5></div>
            <div class="card-body">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php if (!$incomeRows): ?><tr><td colspan="2" class="text-center text-muted py-4">No income found.</td></tr><?php endif; ?>
                        <?php foreach ($incomeRows as $row): ?><tr><td><?= acc_balance_h($row['category']) ?></td><td class="text-end text-success fw-bold"><?= acc_balance_money($row['total']) ?></td></tr><?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light"><tr><th>Total Income</th><th class="text-end"><?= acc_balance_money($income) ?></th></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Expenses</h5></div>
            <div class="card-body">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php if (!$expenseRows): ?><tr><td colspan="2" class="text-center text-muted py-4">No expenses found.</td></tr><?php endif; ?>
                        <?php foreach ($expenseRows as $row): ?><tr><td><?= acc_balance_h($row['category']) ?></td><td class="text-end text-danger fw-bold"><?= acc_balance_money($row['total']) ?></td></tr><?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light"><tr><th>Total Expense</th><th class="text-end"><?= acc_balance_money($expense) ?></th></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Monthly Balance</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light"><tr><th>Month</th><th class="text-end">Income</th><th class="text-end">Expense</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    <?php if (!$monthlyRows): ?><tr><td colspan="4" class="text-center text-muted py-4">No monthly balance found.</td></tr><?php endif; ?>
                    <?php foreach ($monthlyRows as $row): ?><tr><td><?= acc_balance_h(date('M Y', strtotime($row['month_key'] . '-01'))) ?></td><td class="text-end text-success fw-bold"><?= acc_balance_money($row['income']) ?></td><td class="text-end text-danger fw-bold"><?= acc_balance_money($row['expense']) ?></td><td class="text-end fw-bold <?= (float)$row['balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= acc_balance_money($row['balance']) ?></td></tr><?php endforeach; ?>
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
