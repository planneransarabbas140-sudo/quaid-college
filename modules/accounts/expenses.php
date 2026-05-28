<?php
// File: modules/accounts/expenses.php
require_once '../../config/db.php';

requireRole(['admin', 'owner', 'accounts', 'accountant']);

$db = (new Database())->getConnection();
$paymentMethods = ['cash', 'bank', 'easypaisa', 'jazzcash', 'other'];
$statusOptions = ['pending', 'paid', 'cancelled'];
$defaultCategories = ['Utilities', 'Stationery', 'Maintenance', 'Salary', 'Transport', 'Library', 'Exam', 'Office', 'Miscellaneous'];

function acc_exp_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function acc_exp_money($amount): string {
    return 'PKR ' . number_format((float)$amount, 2);
}

function acc_exp_valid_date($date): bool {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function acc_exp_method_label(string $method): string {
    return [
        'cash' => 'Cash',
        'bank' => 'Bank Transfer',
        'easypaisa' => 'EasyPaisa',
        'jazzcash' => 'JazzCash',
        'other' => 'Other',
    ][$method] ?? 'Cash';
}

function acc_exp_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS account_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_title VARCHAR(220) NOT NULL,
        expense_category VARCHAR(120) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        expense_date DATE NOT NULL,
        payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
        paid_to VARCHAR(180) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL DEFAULT 'paid',
        account_transaction_id INT DEFAULT NULL,
        INDEX idx_account_expenses_date (expense_date),
        INDEX idx_account_expenses_category (expense_category),
        INDEX idx_account_expenses_status (status),
        INDEX idx_account_expenses_tx (account_transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'expense_title' => "ALTER TABLE account_expenses ADD COLUMN expense_title VARCHAR(220) DEFAULT NULL",
        'expense_category' => "ALTER TABLE account_expenses ADD COLUMN expense_category VARCHAR(120) DEFAULT NULL",
        'amount' => "ALTER TABLE account_expenses ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'expense_date' => "ALTER TABLE account_expenses ADD COLUMN expense_date DATE DEFAULT NULL",
        'payment_method' => "ALTER TABLE account_expenses ADD COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'cash'",
        'paid_to' => "ALTER TABLE account_expenses ADD COLUMN paid_to VARCHAR(180) DEFAULT NULL",
        'description' => "ALTER TABLE account_expenses ADD COLUMN description TEXT DEFAULT NULL",
        'created_by' => "ALTER TABLE account_expenses ADD COLUMN created_by INT DEFAULT NULL",
        'created_at' => "ALTER TABLE account_expenses ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'status' => "ALTER TABLE account_expenses ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'paid'",
        'account_transaction_id' => "ALTER TABLE account_expenses ADD COLUMN account_transaction_id INT DEFAULT NULL",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'account_expenses', $column)) {
            $db->exec($sql);
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS accounts_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_type ENUM('Income','Expense') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT DEFAULT NULL,
        transaction_date DATE NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        transaction_id VARCHAR(100) DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!columnExists($db, 'accounts_transactions', 'payment_method')) {
        $db->exec("ALTER TABLE accounts_transactions ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash'");
    }
    if (!columnExists($db, 'accounts_transactions', 'transaction_id')) {
        $db->exec("ALTER TABLE accounts_transactions ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL");
    }
}

function acc_exp_validate(array $data, array $paymentMethods, array $statusOptions): array {
    $title = sanitizeInput($data['expense_title'] ?? '');
    $category = sanitizeInput($data['expense_category'] ?? '');
    $amount = (float)($data['amount'] ?? 0);
    $date = $data['expense_date'] ?? date('Y-m-d');
    $method = in_array($data['payment_method'] ?? 'cash', $paymentMethods, true) ? $data['payment_method'] : 'cash';
    $status = in_array($data['status'] ?? 'paid', $statusOptions, true) ? $data['status'] : 'paid';

    if ($title === '' || $category === '') {
        throw new Exception('Please enter expense title and category.');
    }
    if ($amount <= 0) {
        throw new Exception('Please enter a valid expense amount.');
    }
    if (!acc_exp_valid_date($date)) {
        throw new Exception('Please select a valid expense date.');
    }

    return [
        'expense_title' => $title,
        'expense_category' => $category,
        'amount' => $amount,
        'expense_date' => $date,
        'payment_method' => $method,
        'paid_to' => sanitizeInput($data['paid_to'] ?? ''),
        'description' => trim((string)($data['description'] ?? '')),
        'status' => $status,
    ];
}

function acc_exp_sync_transaction(PDO $db, array $expense, ?int $transactionId, int $createdBy): ?int {
    if ($expense['status'] === 'cancelled') {
        if ($transactionId) {
            $db->prepare('DELETE FROM accounts_transactions WHERE id = ?')->execute([$transactionId]);
        }
        return null;
    }

    $description = trim($expense['expense_title'] . ($expense['paid_to'] ? ' | Paid to: ' . $expense['paid_to'] : '') . ($expense['description'] ? ' | ' . $expense['description'] : ''));
    $method = acc_exp_method_label($expense['payment_method']);

    if ($transactionId) {
        $stmt = $db->prepare("UPDATE accounts_transactions SET transaction_type = 'Expense', amount = ?, category = ?, description = ?, transaction_date = ?, payment_method = ? WHERE id = ?");
        $stmt->execute([$expense['amount'], $expense['expense_category'], $description, $expense['expense_date'], $method, $transactionId]);
        return $transactionId;
    }

    $stmt = $db->prepare("INSERT INTO accounts_transactions (transaction_type, amount, category, description, transaction_date, payment_method, transaction_id, recorded_by) VALUES ('Expense', ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$expense['amount'], $expense['expense_category'], $description, $expense['expense_date'], $method, 'EXP-' . date('YmdHis'), $createdBy]);
    return (int)$db->lastInsertId();
}

acc_exp_ensure_schema($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';

        if (in_array($action, ['add_expense', 'edit_expense'], true)) {
            $expense = acc_exp_validate($_POST, $paymentMethods, $statusOptions);
            $id = (int)($_POST['id'] ?? 0);
            $db->beginTransaction();

            if ($action === 'add_expense') {
                $transactionId = acc_exp_sync_transaction($db, $expense, null, (int)getUserId());
                $stmt = $db->prepare("INSERT INTO account_expenses (expense_title, expense_category, amount, expense_date, payment_method, paid_to, description, created_by, status, account_transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$expense['expense_title'], $expense['expense_category'], $expense['amount'], $expense['expense_date'], $expense['payment_method'], $expense['paid_to'], $expense['description'], getUserId(), $expense['status'], $transactionId]);
                setFlashMessage('success', 'Expense added successfully.');
            } else {
                if ($id <= 0) {
                    throw new Exception('Invalid expense selected.');
                }
                $stmt = $db->prepare('SELECT account_transaction_id FROM account_expenses WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $transactionId = (int)($stmt->fetchColumn() ?: 0);
                $transactionId = acc_exp_sync_transaction($db, $expense, $transactionId ?: null, (int)getUserId());
                $stmt = $db->prepare("UPDATE account_expenses SET expense_title = ?, expense_category = ?, amount = ?, expense_date = ?, payment_method = ?, paid_to = ?, description = ?, status = ?, account_transaction_id = ? WHERE id = ?");
                $stmt->execute([$expense['expense_title'], $expense['expense_category'], $expense['amount'], $expense['expense_date'], $expense['payment_method'], $expense['paid_to'], $expense['description'], $expense['status'], $transactionId, $id]);
                setFlashMessage('success', 'Expense updated successfully.');
            }

            $db->commit();
            redirect('expenses.php');
        }

        if ($action === 'delete_expense') {
            $id = (int)($_POST['id'] ?? 0);
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT account_transaction_id FROM account_expenses WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $transactionId = (int)($stmt->fetchColumn() ?: 0);
            if ($transactionId) {
                $db->prepare('DELETE FROM accounts_transactions WHERE id = ?')->execute([$transactionId]);
            }
            $db->prepare('DELETE FROM account_expenses WHERE id = ?')->execute([$id]);
            $db->commit();
            setFlashMessage('success', 'Expense deleted successfully.');
            redirect('expenses.php');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('expenses.php');
    }
}

$dateFrom = acc_exp_valid_date($_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-01');
$dateTo = acc_exp_valid_date($_GET['date_to'] ?? '') ? $_GET['date_to'] : date('Y-m-d');
$categoryFilter = trim((string)($_GET['category'] ?? ''));

$categories = $db->query("SELECT DISTINCT expense_category FROM account_expenses WHERE expense_category IS NOT NULL AND expense_category <> '' ORDER BY expense_category ASC")->fetchAll(PDO::FETCH_COLUMN);
$categories = array_values(array_unique(array_merge($defaultCategories, $categories ?: [])));

$where = ['expense_date BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];
if ($categoryFilter !== '') {
    $where[] = 'expense_category = ?';
    $params[] = $categoryFilter;
}
$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("SELECT e.*, u.full_name AS created_by_name FROM account_expenses e LEFT JOIN users u ON u.id = e.created_by WHERE $whereSql ORDER BY e.expense_date DESC, e.id DESC");
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM account_expenses WHERE $whereSql AND status <> 'cancelled'");
$stmt->execute($params);
$totalExpense = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT expense_category, SUM(amount) AS total, COUNT(*) AS entries FROM account_expenses WHERE $whereSql AND status <> 'cancelled' GROUP BY expense_category ORDER BY total DESC");
$stmt->execute($params);
$categorySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month_key, SUM(amount) AS total, COUNT(*) AS entries FROM account_expenses WHERE status <> 'cancelled' GROUP BY DATE_FORMAT(expense_date, '%Y-%m') ORDER BY month_key DESC LIMIT 12");
$stmt->execute();
$monthlySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Account Expenses';
include '../../includes/header.php';
?>

<div class="container-fluid account-expenses-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Accounts</a>
            <h2 class="page-title mb-1"><i class="fas fa-receipt me-2" style="color:var(--teal);"></i>Accounts Expenses</h2>
            <div class="text-muted">Record daily category-wise expenses and sync them with accounts reports.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="fas fa-plus me-1"></i>Add Expense</button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Filtered Total Expense</div><h3 class="fw-bold text-danger mb-0"><?= acc_exp_money($totalExpense) ?></h3></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Entries</div><h3 class="fw-bold mb-0"><?= count($expenses) ?></h3></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Categories</div><h3 class="fw-bold text-primary mb-0"><?= count($categorySummary) ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label small fw-bold">From</label><input type="date" name="date_from" class="form-control" value="<?= acc_exp_h($dateFrom) ?>"></div>
                <div class="col-md-3"><label class="form-label small fw-bold">To</label><input type="date" name="date_to" class="form-control" value="<?= acc_exp_h($dateTo) ?>"></div>
                <div class="col-md-4"><label class="form-label small fw-bold">Category</label><select name="category" class="form-select"><option value="">All Categories</option><?php foreach ($categories as $category): ?><option value="<?= acc_exp_h($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= acc_exp_h($category) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h5 class="fw-bold mb-0">Category Summary</h5></div><div class="card-body table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Category</th><th>Entries</th><th class="text-end">Amount</th></tr></thead><tbody><?php if (!$categorySummary): ?><tr><td colspan="3" class="text-muted text-center py-3">No category data.</td></tr><?php endif; ?><?php foreach ($categorySummary as $row): ?><tr><td><?= acc_exp_h($row['expense_category']) ?></td><td><?= (int)$row['entries'] ?></td><td class="text-end text-danger fw-bold"><?= acc_exp_money($row['total']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><h5 class="fw-bold mb-0">Monthly Expense Summary</h5></div><div class="card-body table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Month</th><th>Entries</th><th class="text-end">Amount</th></tr></thead><tbody><?php foreach ($monthlySummary as $row): ?><tr><td><?= acc_exp_h(date('M Y', strtotime($row['month_key'] . '-01'))) ?></td><td><?= (int)$row['entries'] ?></td><td class="text-end text-danger fw-bold"><?= acc_exp_money($row['total']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h5 class="fw-bold mb-0">Expense Report</h5></div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Date</th><th>Title</th><th>Category</th><th>Paid To</th><th>Method</th><th>Status</th><th class="text-end">Amount</th><th class="text-end no-print">Actions</th></tr></thead>
                <tbody>
                    <?php if (!$expenses): ?><tr><td colspan="8" class="text-center text-muted py-4">No expenses found.</td></tr><?php endif; ?>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= acc_exp_h(date('d M Y', strtotime($expense['expense_date']))) ?></td>
                            <td><div class="fw-bold"><?= acc_exp_h($expense['expense_title']) ?></div><div class="small text-muted"><?= nl2br(acc_exp_h($expense['description'])) ?></div></td>
                            <td><span class="badge bg-light text-dark border"><?= acc_exp_h($expense['expense_category']) ?></span></td>
                            <td><?= acc_exp_h($expense['paid_to']) ?></td>
                            <td><?= acc_exp_h(ucfirst($expense['payment_method'])) ?></td>
                            <td><span class="badge <?= $expense['status'] === 'cancelled' ? 'bg-secondary' : ($expense['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-success') ?>"><?= acc_exp_h(ucfirst($expense['status'])) ?></span></td>
                            <td class="text-end text-danger fw-bold"><?= acc_exp_money($expense['amount']) ?></td>
                            <td class="text-end no-print">
                                <button class="btn btn-sm btn-outline-primary edit-expense" data-bs-toggle="modal" data-bs-target="#editExpenseModal" data-expense='<?= acc_exp_h(json_encode($expense)) ?>'><i class="fas fa-pen"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this expense? This also removes synced accounts transaction.');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="id" value="<?= (int)$expense['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$renderExpenseForm = function (string $prefix) use ($categories, $paymentMethods, $statusOptions) {
?>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Expense Title *</label><input type="text" name="expense_title" id="<?= $prefix ?>expense_title" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Expense Date *</label><input type="date" name="expense_date" id="<?= $prefix ?>expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Category *</label><input list="expenseCategories" name="expense_category" id="<?= $prefix ?>expense_category" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Amount *</label><input type="number" step="0.01" min="0.01" name="amount" id="<?= $prefix ?>amount" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Payment Method</label><select name="payment_method" id="<?= $prefix ?>payment_method" class="form-select"><?php foreach ($paymentMethods as $method): ?><option value="<?= acc_exp_h($method) ?>"><?= acc_exp_h(ucfirst($method)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Paid To</label><input type="text" name="paid_to" id="<?= $prefix ?>paid_to" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" id="<?= $prefix ?>status" class="form-select"><?php foreach ($statusOptions as $status): ?><option value="<?= acc_exp_h($status) ?>"><?= acc_exp_h(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="<?= $prefix ?>description" class="form-control" rows="4"></textarea></div>
    </div>
<?php
};
?>
<datalist id="expenseCategories"><?php foreach ($categories as $category): ?><option value="<?= acc_exp_h($category) ?>"></option><?php endforeach; ?></datalist>

<div class="modal fade no-print" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content border-0 shadow" method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="add_expense"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Add Expense</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php $renderExpenseForm('add_'); ?></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Expense</button></div></form></div>
</div>
<div class="modal fade no-print" id="editExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content border-0 shadow" method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="edit_expense"><input type="hidden" name="id" id="edit_id"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit Expense</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php $renderExpenseForm('edit_'); ?></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Expense</button></div></form></div>
</div>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop, .btn, form { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; break-inside:avoid; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-expense').forEach(function (button) {
        button.addEventListener('click', function () {
            const expense = JSON.parse(button.dataset.expense || '{}');
            ['id', 'expense_title', 'expense_category', 'amount', 'expense_date', 'payment_method', 'paid_to', 'description', 'status'].forEach(function (field) {
                const el = document.getElementById('edit_' + field);
                if (el) el.value = expense[field] || '';
            });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
