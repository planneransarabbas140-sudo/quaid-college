<?php
// File: modules/accounts/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$paymentMethods = ['Cash', 'Bank Transfer', 'Online Payment', 'Cheque', 'EasyPaisa', 'JazzCash', 'Card/ATM'];
$bankMethods = ['Bank Transfer', 'Online Payment', 'Cheque', 'Card/ATM'];
$categoryGroups = [
    'Income Categories' => [
        'Admission Fee',
        'Tuition Fee',
        'Monthly Fee',
        'Annual Charges',
        'Registration Fee',
        'Examination Fee',
        'Hostel Fee',
        'Transport Fee',
        'Library Fee',
        'Lab Fee',
        'Fine / Penalty',
        'Certificate Fee',
        'Prospectus Sale',
        'Donations',
        'Grants',
        'POS Sale',
        'Other Income',
    ],
    'Expense Categories' => [
        'Staff Salary',
        'Teacher Salary',
        'Administrative Salary',
        'Utility Bills',
        'Electricity Bill',
        'Gas Bill',
        'Water Bill',
        'Internet / Phone',
        'Building Rent',
        'Maintenance & Repairs',
        'Stationery & Printing',
        'Transport Fuel',
        'Vehicle Maintenance',
        'Library Books',
        'Lab Equipment',
        'LMS / Software',
        'Marketing & Ads',
        'Events & Functions',
        'Security',
        'Cleaning & Sanitation',
        'Furniture & Fixtures',
        'Bank Charges',
        'Taxes',
        'Miscellaneous Expense',
    ],
    'Adjustments' => [
        'Opening Balance',
        'Balance Adjustment',
        'Refund',
        'Internal Transfer',
        'Other',
    ],
];

function accounts_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function accounts_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function accounts_clean($value) {
    return trim(strip_tags((string)($value ?? '')));
}

function accounts_is_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function accounts_fetch_all(PDO $db, $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function accounts_fetch_value(PDO $db, $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? 0 : $value;
}

function accounts_ensure_transaction_columns(PDO $db) {
    if (!columnExists($db, 'accounts_transactions', 'payment_method')) {
        $stmt = $db->prepare("ALTER TABLE accounts_transactions ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash'");
        $stmt->execute();
    }

    if (!columnExists($db, 'accounts_transactions', 'transaction_id')) {
        $stmt = $db->prepare("ALTER TABLE accounts_transactions ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL");
        $stmt->execute();
    }
}

function accounts_render_category_options(array $categoryGroups, $selected = '') {
    $hasSelected = false;
    echo '<option value="">Select Category</option>';
    foreach ($categoryGroups as $groupLabel => $categories) {
        echo '<optgroup label="' . accounts_h($groupLabel) . '">';
        foreach ($categories as $category) {
            $isSelected = (string)$selected === (string)$category;
            $hasSelected = $hasSelected || $isSelected;
            echo '<option value="' . accounts_h($category) . '"' . ($isSelected ? ' selected' : '') . '>' . accounts_h($category) . '</option>';
        }
        echo '</optgroup>';
    }

    if ($selected !== '' && !$hasSelected) {
        echo '<option value="' . accounts_h($selected) . '" selected>' . accounts_h($selected) . '</option>';
    }
}

function accounts_validate_transaction(array $data, array $paymentMethods) {
    $type = $data['transaction_type'] ?? '';
    if (!in_array($type, ['Income', 'Expense'], true)) {
        throw new Exception('Please select a valid transaction type.');
    }

    $amount = (float)($data['amount'] ?? 0);
    if ($amount <= 0) {
        throw new Exception('Please enter a valid amount.');
    }

    $category = accounts_clean($data['category'] ?? '');
    if ($category === '') {
        throw new Exception('Please enter a category.');
    }

    $paymentMethod = accounts_clean($data['payment_method'] ?? 'Cash');
    if (!in_array($paymentMethod, $paymentMethods, true)) {
        throw new Exception('Please select a valid payment method.');
    }

    $transactionDate = $data['transaction_date'] ?? date('Y-m-d');
    if (!accounts_is_valid_date($transactionDate)) {
        throw new Exception('Please select a valid transaction date.');
    }

    return [
        'transaction_type' => $type,
        'amount' => $amount,
        'category' => $category,
        'description' => accounts_clean($data['description'] ?? ''),
        'transaction_date' => $transactionDate,
        'payment_method' => $paymentMethod,
        'transaction_id' => $paymentMethod === 'Cash' ? null : accounts_clean($data['transaction_id'] ?? ''),
    ];
}

try {
    accounts_ensure_transaction_columns($db);
} catch (Exception $e) {
    $error = 'Accounts table update failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        requireCsrfToken();

        if ($action === 'add_transaction') {
            $transaction = accounts_validate_transaction($_POST, $paymentMethods);
            $stmt = $db->prepare("
                INSERT INTO accounts_transactions
                    (transaction_type, amount, category, description, transaction_date, payment_method, transaction_id, recorded_by)
                VALUES
                    (:transaction_type, :amount, :category, :description, :transaction_date, :payment_method, :transaction_id, :recorded_by)
            ");
            $stmt->execute([
                ':transaction_type' => $transaction['transaction_type'],
                ':amount' => $transaction['amount'],
                ':category' => $transaction['category'],
                ':description' => $transaction['description'],
                ':transaction_date' => $transaction['transaction_date'],
                ':payment_method' => $transaction['payment_method'],
                ':transaction_id' => $transaction['transaction_id'],
                ':recorded_by' => getUserId(),
            ]);
            $success = 'Transaction added successfully.';
        }

        if ($action === 'edit_transaction') {
            $transactionId = (int)($_POST['id'] ?? 0);
            if ($transactionId <= 0) {
                throw new Exception('Invalid transaction selected.');
            }

            $transaction = accounts_validate_transaction($_POST, $paymentMethods);
            $stmt = $db->prepare("
                UPDATE accounts_transactions
                SET transaction_type = :transaction_type,
                    amount = :amount,
                    category = :category,
                    description = :description,
                    transaction_date = :transaction_date,
                    payment_method = :payment_method,
                    transaction_id = :transaction_id
                WHERE id = :id
            ");
            $stmt->execute([
                ':transaction_type' => $transaction['transaction_type'],
                ':amount' => $transaction['amount'],
                ':category' => $transaction['category'],
                ':description' => $transaction['description'],
                ':transaction_date' => $transaction['transaction_date'],
                ':payment_method' => $transaction['payment_method'],
                ':transaction_id' => $transaction['transaction_id'],
                ':id' => $transactionId,
            ]);
            $success = 'Transaction updated successfully.';
        }

        if ($action === 'delete_transaction') {
            $transactionId = (int)($_POST['id'] ?? 0);
            if ($transactionId <= 0) {
                throw new Exception('Invalid transaction selected.');
            }

            $stmt = $db->prepare("DELETE FROM accounts_transactions WHERE id = :id");
            $stmt->execute([':id' => $transactionId]);
            $success = 'Transaction deleted successfully.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$filterStart = accounts_is_valid_date($_GET['start_date'] ?? '') ? $_GET['start_date'] : '';
$filterEnd = accounts_is_valid_date($_GET['end_date'] ?? '') ? $_GET['end_date'] : '';
$filterType = in_array($_GET['type'] ?? '', ['Income', 'Expense'], true) ? $_GET['type'] : '';
$filterMethod = in_array($_GET['payment_method'] ?? '', $paymentMethods, true) ? $_GET['payment_method'] : '';

$stats = [
    'total_income' => 0,
    'total_expense' => 0,
    'net_balance' => 0,
    'cash_balance' => 0,
    'bank_balance' => 0,
];
$transactions = [];
$incomeTransactions = [];
$expenseTransactions = [];
$incomeByCategory = [];
$expenseByCategory = [];
$monthlyReport = [];
$chartLabels = [];
$chartIncome = [];
$chartExpense = [];
$paymentSummary = [];

try {
    $stats['total_income'] = (float)accounts_fetch_value($db, "SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE transaction_type = 'Income'");
    $stats['total_expense'] = (float)accounts_fetch_value($db, "SELECT COALESCE(SUM(amount), 0) FROM accounts_transactions WHERE transaction_type = 'Expense'");
    $stats['net_balance'] = $stats['total_income'] - $stats['total_expense'];
    $stats['cash_balance'] = (float)accounts_fetch_value($db, "
        SELECT COALESCE(SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE -amount END), 0)
        FROM accounts_transactions
        WHERE payment_method = 'Cash'
    ");
    $stats['bank_balance'] = (float)accounts_fetch_value($db, "
        SELECT COALESCE(SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE -amount END), 0)
        FROM accounts_transactions
        WHERE payment_method IN ('Bank Transfer', 'Online Payment', 'Cheque', 'Card/ATM')
    ");

    $paymentRows = accounts_fetch_all($db, "
        SELECT
            COALESCE(NULLIF(payment_method, ''), 'Cash') AS payment_method,
            SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END) AS income_amount,
            SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END) AS expense_amount,
            SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE -amount END) AS net_amount
        FROM accounts_transactions
        GROUP BY COALESCE(NULLIF(payment_method, ''), 'Cash')
        ORDER BY payment_method ASC
    ");

    foreach ($paymentRows as $row) {
        $paymentSummary[$row['payment_method']] = $row;
    }
    foreach ($paymentMethods as $method) {
        if (!isset($paymentSummary[$method])) {
            $paymentSummary[$method] = [
                'payment_method' => $method,
                'income_amount' => 0,
                'expense_amount' => 0,
                'net_amount' => 0,
            ];
        }
    }

    $transactionWhere = ['1=1'];
    $transactionParams = [];
    if ($filterStart !== '') {
        $transactionWhere[] = 't.transaction_date >= :start_date';
        $transactionParams[':start_date'] = $filterStart;
    }
    if ($filterEnd !== '') {
        $transactionWhere[] = 't.transaction_date <= :end_date';
        $transactionParams[':end_date'] = $filterEnd;
    }
    if ($filterType !== '') {
        $transactionWhere[] = 't.transaction_type = :transaction_type';
        $transactionParams[':transaction_type'] = $filterType;
    }
    if ($filterMethod !== '') {
        $transactionWhere[] = 't.payment_method = :payment_method';
        $transactionParams[':payment_method'] = $filterMethod;
    }

    $transactions = accounts_fetch_all($db, "
        SELECT t.*, u.full_name AS recorded_by_name
        FROM accounts_transactions t
        LEFT JOIN users u ON u.id = t.recorded_by
        WHERE " . implode(' AND ', $transactionWhere) . "
        ORDER BY t.transaction_date DESC, t.id DESC
    ", $transactionParams);

    $incomeTransactions = accounts_fetch_all($db, "
        SELECT t.*, u.full_name AS recorded_by_name
        FROM accounts_transactions t
        LEFT JOIN users u ON u.id = t.recorded_by
        WHERE t.transaction_type = 'Income'
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT 200
    ");
    $expenseTransactions = accounts_fetch_all($db, "
        SELECT t.*, u.full_name AS recorded_by_name
        FROM accounts_transactions t
        LEFT JOIN users u ON u.id = t.recorded_by
        WHERE t.transaction_type = 'Expense'
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT 200
    ");

    $incomeByCategory = accounts_fetch_all($db, "
        SELECT COALESCE(NULLIF(category, ''), 'Uncategorized') AS category, COUNT(*) AS entry_count, SUM(amount) AS total_amount
        FROM accounts_transactions
        WHERE transaction_type = 'Income'
        GROUP BY COALESCE(NULLIF(category, ''), 'Uncategorized')
        ORDER BY total_amount DESC
    ");
    $expenseByCategory = accounts_fetch_all($db, "
        SELECT COALESCE(NULLIF(category, ''), 'Uncategorized') AS category, COUNT(*) AS entry_count, SUM(amount) AS total_amount
        FROM accounts_transactions
        WHERE transaction_type = 'Expense'
        GROUP BY COALESCE(NULLIF(category, ''), 'Uncategorized')
        ORDER BY total_amount DESC
    ");

    $monthlyReport = accounts_fetch_all($db, "
        SELECT
            DATE_FORMAT(transaction_date, '%Y-%m') AS month_key,
            SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE 0 END) AS income_amount,
            SUM(CASE WHEN transaction_type = 'Expense' THEN amount ELSE 0 END) AS expense_amount,
            SUM(CASE WHEN transaction_type = 'Income' THEN amount ELSE -amount END) AS net_amount
        FROM accounts_transactions
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month_key DESC
        LIMIT 24
    ");

    $chartRows = array_reverse(array_slice($monthlyReport, 0, 12));
    foreach ($chartRows as $row) {
        $chartLabels[] = date('M Y', strtotime($row['month_key'] . '-01'));
        $chartIncome[] = (float)$row['income_amount'];
        $chartExpense[] = (float)$row['expense_amount'];
    }
} catch (Exception $e) {
    $error = $error ?: 'Unable to load account data: ' . $e->getMessage();
}

$page_title = 'Accounts & Finance';
include '../../includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= accounts_h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= accounts_h($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        <h2 class="page-title mb-1">Accounts & Finance</h2>
        <div class="text-muted">Manual income, expenses, balances, and finance reports.</div>
    </div>
    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="offcanvas" data-bs-target="#addTransactionModal" aria-controls="addTransactionModal">
        <i class="fas fa-plus me-2"></i>Add Transaction
    </button>
</div>

<ul class="nav nav-tabs mb-4" id="accountsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Overview</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">Transactions</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="income-tab" data-bs-toggle="tab" data-bs-target="#income" type="button" role="tab">Income</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses" type="button" role="tab">Expenses</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="balance-sheet-tab" data-bs-toggle="tab" data-bs-target="#balance-sheet" type="button" role="tab">Balance Sheet</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">Reports</button>
    </li>
</ul>

<div class="tab-content" id="accountsTabsContent">
    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab" tabindex="0">
        <div class="row g-3 mb-4">
            <div class="col-xl col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Total Income</div>
                            <h4 class="text-success fw-bold mb-0"><?= accounts_money($stats['total_income']) ?></h4>
                        </div>
                        <i class="fas fa-arrow-trend-up text-success fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Total Expense</div>
                            <h4 class="text-danger fw-bold mb-0"><?= accounts_money($stats['total_expense']) ?></h4>
                        </div>
                        <i class="fas fa-arrow-trend-down text-danger fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Net Balance</div>
                            <h4 class="<?= $stats['net_balance'] >= 0 ? 'text-primary' : 'text-danger' ?> fw-bold mb-0"><?= accounts_money($stats['net_balance']) ?></h4>
                        </div>
                        <i class="fas fa-scale-balanced text-primary fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Cash Balance</div>
                            <h4 class="<?= $stats['cash_balance'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold mb-0"><?= accounts_money($stats['cash_balance']) ?></h4>
                        </div>
                        <i class="fas fa-money-bill-wave text-success fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Bank Balance</div>
                            <h4 class="<?= $stats['bank_balance'] >= 0 ? 'text-info' : 'text-danger' ?> fw-bold mb-0"><?= accounts_money($stats['bank_balance']) ?></h4>
                        </div>
                        <i class="fas fa-building-columns text-info fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Income vs Expense</h5>
            </div>
            <div class="card-body">
                <div class="ratio ratio-21x9">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($paymentSummary as $summary): ?>
                <div class="col-xl-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0"><?= accounts_h($summary['payment_method']) ?></h6>
                                <i class="fas fa-wallet text-muted"></i>
                            </div>
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Income</span>
                                <strong class="text-success"><?= accounts_money($summary['income_amount']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Expense</span>
                                <strong class="text-danger"><?= accounts_money($summary['expense_amount']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span class="fw-bold">Net</span>
                                <strong class="<?= (float)$summary['net_amount'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= accounts_money($summary['net_amount']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab" tabindex="0">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="index.php#transactions" class="row g-3 align-items-end">
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= accounts_h($filterStart) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= accounts_h($filterEnd) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold">Type</label>
                        <select name="type" class="form-select">
                            <option value="">All</option>
                            <option value="Income" <?= $filterType === 'Income' ? 'selected' : '' ?>>Income</option>
                            <option value="Expense" <?= $filterType === 'Expense' ? 'selected' : '' ?>>Expense</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-bold">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= accounts_h($method) ?>" <?= $filterMethod === $method ? 'selected' : '' ?>><?= accounts_h($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-2"></i>Apply</button>
                        <a href="index.php#transactions" class="btn btn-outline-secondary flex-fill"><i class="fas fa-rotate-right me-2"></i>Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Transactions</h5>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="offcanvas" data-bs-target="#addTransactionModal" aria-controls="addTransactionModal">
                    <i class="fas fa-plus me-1"></i>Add Transaction
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle <?= $transactions ? 'datatable' : '' ?>">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Payment Method</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Recorded By</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$transactions): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No transactions found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?= accounts_h(date('d M Y', strtotime($transaction['transaction_date']))) ?></td>
                                    <td>
                                        <span class="badge <?= $transaction['transaction_type'] === 'Income' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= accounts_h($transaction['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= accounts_h($transaction['category']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= accounts_h($transaction['payment_method'] ?: 'Cash') ?></span></td>
                                    <td><?= accounts_h($transaction['description']) ?></td>
                                    <td class="text-end fw-bold <?= $transaction['transaction_type'] === 'Income' ? 'text-success' : 'text-danger' ?>"><?= accounts_money($transaction['amount']) ?></td>
                                    <td><?= accounts_h($transaction['recorded_by_name'] ?: 'System') ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#editTransactionModal"
                                                aria-controls="editTransactionModal"
                                                data-edit-transaction
                                                data-id="<?= (int)$transaction['id'] ?>"
                                                data-type="<?= accounts_h($transaction['transaction_type']) ?>"
                                                data-category="<?= accounts_h($transaction['category']) ?>"
                                                data-amount="<?= accounts_h($transaction['amount']) ?>"
                                                data-method="<?= accounts_h($transaction['payment_method'] ?: 'Cash') ?>"
                                                data-reference="<?= accounts_h($transaction['transaction_id']) ?>"
                                                data-description="<?= accounts_h($transaction['description']) ?>"
                                                data-date="<?= accounts_h($transaction['transaction_date']) ?>"
                                                title="Edit transaction"
                                            >
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <form method="POST" class="delete-transaction-form">
                                                <?= csrfTokenInput() ?>
                                                <input type="hidden" name="action" value="delete_transaction">
                                                <input type="hidden" name="id" value="<?= (int)$transaction['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete transaction">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="income" role="tabpanel" aria-labelledby="income-tab" tabindex="0">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Income by Category</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$incomeByCategory): ?>
                            <div class="text-muted">No income recorded.</div>
                        <?php endif; ?>
                        <?php foreach ($incomeByCategory as $category): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <div class="fw-bold"><?= accounts_h($category['category']) ?></div>
                                    <div class="small text-muted"><?= (int)$category['entry_count'] ?> entries</div>
                                </div>
                                <strong class="text-success"><?= accounts_money($category['total_amount']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Income Transactions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle <?= $incomeTransactions ? 'datatable' : '' ?>">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Payment Method</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$incomeTransactions): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No income transactions found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($incomeTransactions as $transaction): ?>
                                        <tr>
                                            <td><?= accounts_h(date('d M Y', strtotime($transaction['transaction_date']))) ?></td>
                                            <td><?= accounts_h($transaction['category']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= accounts_h($transaction['payment_method'] ?: 'Cash') ?></span></td>
                                            <td><?= accounts_h($transaction['description']) ?></td>
                                            <td class="text-end fw-bold text-success"><?= accounts_money($transaction['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="expenses" role="tabpanel" aria-labelledby="expenses-tab" tabindex="0">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Expenses by Category</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$expenseByCategory): ?>
                            <div class="text-muted">No expenses recorded.</div>
                        <?php endif; ?>
                        <?php foreach ($expenseByCategory as $category): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <div>
                                    <div class="fw-bold"><?= accounts_h($category['category']) ?></div>
                                    <div class="small text-muted"><?= (int)$category['entry_count'] ?> entries</div>
                                </div>
                                <strong class="text-danger"><?= accounts_money($category['total_amount']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Expense Transactions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle <?= $expenseTransactions ? 'datatable' : '' ?>">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Payment Method</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$expenseTransactions): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No expense transactions found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($expenseTransactions as $transaction): ?>
                                        <tr>
                                            <td><?= accounts_h(date('d M Y', strtotime($transaction['transaction_date']))) ?></td>
                                            <td><?= accounts_h($transaction['category']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= accounts_h($transaction['payment_method'] ?: 'Cash') ?></span></td>
                                            <td><?= accounts_h($transaction['description']) ?></td>
                                            <td class="text-end fw-bold text-danger"><?= accounts_money($transaction['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="balance-sheet" role="tabpanel" aria-labelledby="balance-sheet-tab" tabindex="0">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Assets</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Income Source</th><th class="text-end">Amount</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (!$incomeByCategory): ?>
                                        <tr><td colspan="2" class="text-center text-muted py-4">No assets recorded.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($incomeByCategory as $category): ?>
                                        <tr>
                                            <td><?= accounts_h($category['category']) ?></td>
                                            <td class="text-end fw-bold text-success"><?= accounts_money($category['total_amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Total Assets</th>
                                        <th class="text-end"><?= accounts_money($stats['total_income']) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Liabilities</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Expense Category</th><th class="text-end">Amount</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (!$expenseByCategory): ?>
                                        <tr><td colspan="2" class="text-center text-muted py-4">No liabilities recorded.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($expenseByCategory as $category): ?>
                                        <tr>
                                            <td><?= accounts_h($category['category']) ?></td>
                                            <td class="text-end fw-bold text-danger"><?= accounts_money($category['total_amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Total Liabilities</th>
                                        <th class="text-end"><?= accounts_money($stats['total_expense']) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
                <span class="fw-bold">Net Balance</span>
                <strong class="<?= $stats['net_balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= accounts_money($stats['net_balance']) ?></strong>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab" tabindex="0">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h5 class="mb-0">Monthly Summary</h5>
                <button type="button" class="btn btn-outline-primary" id="printReportButton">
                    <i class="fas fa-file-pdf me-2"></i>Export to PDF
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Income</th>
                                <th class="text-end">Expense</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$monthlyReport): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No monthly summary found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($monthlyReport as $month): ?>
                                <tr>
                                    <td><?= accounts_h(date('M Y', strtotime($month['month_key'] . '-01'))) ?></td>
                                    <td class="text-end text-success fw-bold"><?= accounts_money($month['income_amount']) ?></td>
                                    <td class="text-end text-danger fw-bold"><?= accounts_money($month['expense_amount']) ?></td>
                                    <td class="text-end fw-bold <?= (float)$month['net_amount'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= accounts_money($month['net_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end w-75" tabindex="-1" id="addTransactionModal" aria-labelledby="addTransactionModalLabel">
    <form method="POST" class="d-flex flex-column h-100">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="add_transaction">
                <div class="offcanvas-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center p-3">
                            <i class="fas fa-receipt"></i>
                        </span>
                        <div>
                            <h5 class="offcanvas-title fw-bold mb-1" id="addTransactionModalLabel">Add Transaction</h5>
                            <div class="text-muted small">Record income, expenses, payment method, and reference details.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body bg-light p-4 overflow-auto">
                    <div class="bg-white border rounded-3 p-3 p-md-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-file-invoice-dollar text-primary"></i>
                            <h6 class="fw-bold mb-0">Transaction Details</h6>
                        </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Transaction Type</label>
                            <select class="form-select form-select-lg" name="transaction_type" id="add_transaction_type" required>
                                <option value="Income">Income</option>
                                <option value="Expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Date</label>
                            <input type="date" class="form-control form-control-lg" name="transaction_date" value="<?= accounts_h(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Category</label>
                            <select class="form-select form-select-lg" name="category" id="add_transaction_category" required>
                                <?php accounts_render_category_options($categoryGroups); ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Amount</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">Rs</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Payment Method</label>
                            <select name="payment_method" id="add_payment_method" class="form-select form-select-lg" required>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= accounts_h($method) ?>"><?= accounts_h($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="add_transaction_reference_wrap">
                            <label class="form-label small text-uppercase fw-bold text-muted">Transaction ID</label>
                            <input type="text" class="form-control form-control-lg" name="transaction_id" id="add_transaction_reference" placeholder="Bank, cheque, wallet, or card reference">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-uppercase fw-bold text-muted">Description</label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Add notes, payer/vendor name, voucher number, or any finance remarks."></textarea>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="bg-white border-top p-4 d-flex flex-column flex-md-row justify-content-between gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="offcanvas">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Transaction</button>
                </div>
            </form>
</div>

<div class="offcanvas offcanvas-end w-75" tabindex="-1" id="editTransactionModal" aria-labelledby="editTransactionModalLabel">
    <form method="POST" class="d-flex flex-column h-100">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="edit_transaction">
                <input type="hidden" name="id" id="edit_transaction_id">
                <div class="offcanvas-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center p-3">
                            <i class="fas fa-pen-to-square"></i>
                        </span>
                        <div>
                            <h5 class="offcanvas-title fw-bold mb-1" id="editTransactionModalLabel">Edit Transaction</h5>
                            <div class="text-muted small">Update category, amount, payment details, and notes.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body bg-light p-4 overflow-auto">
                    <div class="bg-white border rounded-3 p-3 p-md-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fas fa-file-invoice-dollar text-primary"></i>
                            <h6 class="fw-bold mb-0">Transaction Details</h6>
                        </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Transaction Type</label>
                            <select class="form-select form-select-lg" name="transaction_type" id="edit_transaction_type" required>
                                <option value="Income">Income</option>
                                <option value="Expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Date</label>
                            <input type="date" class="form-control form-control-lg" name="transaction_date" id="edit_transaction_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Category</label>
                            <select class="form-select form-select-lg" name="category" id="edit_transaction_category" required>
                                <?php accounts_render_category_options($categoryGroups); ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Amount</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">Rs</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="edit_transaction_amount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-uppercase fw-bold text-muted">Payment Method</label>
                            <select name="payment_method" id="edit_payment_method" class="form-select form-select-lg" required>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= accounts_h($method) ?>"><?= accounts_h($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="edit_transaction_reference_wrap">
                            <label class="form-label small text-uppercase fw-bold text-muted">Transaction ID</label>
                            <input type="text" class="form-control form-control-lg" name="transaction_id" id="edit_transaction_reference" placeholder="Bank, cheque, wallet, or card reference">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-uppercase fw-bold text-muted">Description</label>
                            <textarea class="form-control" name="description" id="edit_transaction_description" rows="4" placeholder="Add notes, payer/vendor name, voucher number, or any finance remarks."></textarea>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="bg-white border-top p-4 d-flex flex-column flex-md-row justify-content-between gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="offcanvas">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Update Transaction</button>
                </div>
            </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartElement = document.getElementById('incomeExpenseChart');
    if (chartElement && typeof Chart !== 'undefined') {
        new Chart(chartElement, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                datasets: [
                    {
                        label: 'Income',
                        data: <?= json_encode($chartIncome, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                        backgroundColor: 'rgba(25, 135, 84, 0.75)'
                    },
                    {
                        label: 'Expense',
                        data: <?= json_encode($chartExpense, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.75)'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function toggleReference(methodSelect, wrapper, input) {
        if (!methodSelect || !wrapper) {
            return;
        }

        const showReference = methodSelect.value !== 'Cash';
        wrapper.classList.toggle('d-none', !showReference);
        if (!showReference && input) {
            input.value = '';
        }
    }

    function setSelectValue(select, value) {
        if (!select) {
            return;
        }

        const normalizedValue = value || '';
        const hasOption = Array.from(select.options).some(function (option) {
            return option.value === normalizedValue;
        });

        if (normalizedValue && !hasOption) {
            const option = new Option(normalizedValue, normalizedValue, true, true);
            select.add(option);
        }

        select.value = normalizedValue;
    }

    const addMethod = document.getElementById('add_payment_method');
    const addReferenceWrap = document.getElementById('add_transaction_reference_wrap');
    const addReferenceInput = document.getElementById('add_transaction_reference');
    if (addMethod) {
        addMethod.addEventListener('change', function () {
            toggleReference(addMethod, addReferenceWrap, addReferenceInput);
        });
        toggleReference(addMethod, addReferenceWrap, addReferenceInput);
    }

    const editMethod = document.getElementById('edit_payment_method');
    const editReferenceWrap = document.getElementById('edit_transaction_reference_wrap');
    const editReferenceInput = document.getElementById('edit_transaction_reference');
    if (editMethod) {
        editMethod.addEventListener('change', function () {
            toggleReference(editMethod, editReferenceWrap, editReferenceInput);
        });
    }

    const editPanel = document.getElementById('editTransactionModal');
    if (editPanel) {
        editPanel.addEventListener('show.bs.offcanvas', function (event) {
            const button = event.relatedTarget;
            if (!button) {
                return;
            }

            document.getElementById('edit_transaction_id').value = button.dataset.id || '';
            document.getElementById('edit_transaction_type').value = button.dataset.type || 'Income';
            setSelectValue(document.getElementById('edit_transaction_category'), button.dataset.category || '');
            document.getElementById('edit_transaction_amount').value = button.dataset.amount || '';
            setSelectValue(document.getElementById('edit_payment_method'), button.dataset.method || 'Cash');
            document.getElementById('edit_transaction_reference').value = button.dataset.reference || '';
            document.getElementById('edit_transaction_description').value = button.dataset.description || '';
            document.getElementById('edit_transaction_date').value = button.dataset.date || '';
            toggleReference(editMethod, editReferenceWrap, editReferenceInput);
        });
    }

    document.querySelectorAll('.delete-transaction-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm('Delete this transaction?')) {
                event.preventDefault();
            }
        });
    });

    const printReportButton = document.getElementById('printReportButton');
    if (printReportButton) {
        printReportButton.addEventListener('click', function () {
            window.print();
        });
    }

    if (window.location.hash) {
        const trigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
        if (trigger && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(trigger).show();
        }
    }

    document.querySelectorAll('#accountsTabs [data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            history.replaceState(null, '', event.target.dataset.bsTarget);
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
