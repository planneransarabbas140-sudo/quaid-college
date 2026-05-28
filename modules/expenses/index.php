<?php
// File: modules/expenses/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
ensureFinanceTables($db);
syncFinancialModuleData($db);

$role = getUserRole();
$isAdmin = in_array($role, ['admin', 'owner'], true);
$isTeacher = $role === 'teacher';

if (!$isAdmin && !$isTeacher) {
    setFlashMessage('error', 'Students cannot access financial data.');
    redirect('../../dashboard.php');
}

function money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function expenseDateColumn(PDO $db) {
    return columnExists($db, 'expenses', 'expense_date') ? 'COALESCE(expense_date, DATE(created_at))' : 'DATE(created_at)';
}

$categories = [
    'Salaries',
    'Utility Bills',
    'Maintenance',
    'Marketing & Ads',
    'Stationery & Printing',
    'Transportation',
    'Library Books',
    'LMS Content',
    'POS Stock',
    'Events & Functions',
    'Rents',
    'Miscellaneous'
];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_expense') {
            $amount = (float)($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new Exception('Please enter a valid expense amount.');
            }

            recordExpense($db, [
                'module_name' => 'manual',
                'reference_id' => null,
                'campus' => sanitizeInput($_POST['campus'] ?? ''),
                'category' => sanitizeInput($_POST['category'] ?? 'Miscellaneous'),
                'description' => sanitizeInput($_POST['description'] ?? ''),
                'amount' => $amount,
                'expense_type' => 'manual',
                'status' => 'pending',
                'created_by' => getUserId(),
                'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
                'payment_method' => sanitizeInput($_POST['payment_method'] ?? 'Cash'),
                'receipt_no' => sanitizeInput($_POST['receipt_no'] ?? ''),
                'month' => date('Y-m', strtotime($_POST['expense_date'] ?? date('Y-m-d')))
            ]);

            setFlashMessage('success', $isTeacher ? 'Expense request submitted for admin approval.' : 'Expense added as pending approval.');
            redirect('index.php');
        }

        if (!$isAdmin) {
            throw new Exception('Only admin/owner can review financial records.');
        }

        if ($action === 'update_status') {
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $status = normalizeFinanceStatus($_POST['status'] ?? 'pending');
            $stmt = $db->prepare("UPDATE expenses SET status = ?, approved_by = ? WHERE id = ?");
            $stmt->execute([$status, getUserId(), $expenseId]);
            setFlashMessage('success', 'Expense status updated.');
            redirect('index.php');
        }

        if ($action === 'delete_expense') {
            $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([(int)($_POST['expense_id'] ?? 0)]);
            setFlashMessage('success', 'Expense deleted.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($isTeacher) {
    $myExpenses = $db->prepare("SELECT * FROM expenses WHERE created_by = ? ORDER BY created_at DESC LIMIT 20");
    $myExpenses->execute([getUserId()]);
    $myExpenses = $myExpenses->fetchAll();
    $page_title = 'Expense Requests';
    include '../../includes/header.php';
    ?>
    <?php displayFlashMessage(); ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
            <h2 class="page-title mb-1">Expense Requests</h2>
            <p class="text-muted mb-0">Teacher expenses stay pending until admin approval.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="fas fa-rotate-right me-1"></i>Reset</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4"><h5 class="fw-bold mb-0">Submit Expense Request</h5></div>
                <div class="card-body p-4">
                    <form method="POST" class="row g-3">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="action" value="add_expense">
                        <div class="col-12">
                            <label class="form-label fw-bold small">Campus</label>
                            <select name="campus" class="form-select" required><?php renderCampusOptions($_SESSION['user_campus'] ?? ''); ?></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Category</label>
                            <select name="category" class="form-select" required>
                                <?php foreach ($categories as $category): ?><option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary rounded-pill px-4">Submit for Approval</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4"><h5 class="fw-bold mb-0">My Recent Requests</h5></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-4">Category</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (!$myExpenses): ?><tr><td colspan="4" class="text-center text-muted py-5">No requests yet.</td></tr><?php endif; ?>
                            <?php foreach ($myExpenses as $expense): ?>
                                <tr>
                                    <td class="ps-4"><?= htmlspecialchars($expense['category']) ?></td>
                                    <td class="fw-bold"><?= money($expense['amount']) ?></td>
                                    <td><span class="badge rounded-pill text-bg-warning text-capitalize"><?= htmlspecialchars($expense['status']) ?></span></td>
                                    <td><?= date('d M Y', strtotime($expense['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../includes/footer.php'; exit; ?>
<?php
}

$filterCampus = trim($_GET['campus'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterModule = trim($_GET['module'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];
if ($filterCampus !== '') {
    $where[] = 'campus = :campus';
    $params[':campus'] = $filterCampus;
}
if ($filterCategory !== '') {
    $where[] = 'category = :category';
    $params[':category'] = $filterCategory;
}
if ($filterStatus !== '') {
    $where[] = 'status = :status';
    $params[':status'] = normalizeFinanceStatus($filterStatus);
}
if ($filterModule !== '') {
    $where[] = 'module_name = :module_name';
    $params[':module_name'] = $filterModule;
}
if ($search !== '') {
    $where[] = '(description LIKE :search OR category LIKE :search OR module_name LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);
$expenseDateExpr = expenseDateColumn($db);

$stats = [
    'income' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM income")->fetchColumn(),
    'expenses' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status IN ('approved','paid')")->fetchColumn(),
    'all_expenses' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status <> 'rejected'")->fetchColumn(),
    'pending' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'pending'")->fetchColumn(),
    'approved' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'approved'")->fetchColumn(),
    'paid' => (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid'")->fetchColumn(),
];
$stats['net'] = $stats['income'] - $stats['expenses'];

$monthlyReport = $db->query("
    SELECT ym, SUM(income_amount) AS income_amount, SUM(expense_amount) AS expense_amount
    FROM (
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(amount) AS income_amount, 0 AS expense_amount
        FROM income
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        UNION ALL
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, 0 AS income_amount, SUM(amount) AS expense_amount
        FROM expenses
        WHERE status IN ('approved','paid')
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ) financials
    GROUP BY ym
    ORDER BY ym DESC
    LIMIT 6
")->fetchAll();
$monthlyChart = array_reverse($monthlyReport);

$campusReport = $db->query("SELECT COALESCE(NULLIF(campus, ''), 'Unassigned') AS label, SUM(amount) AS total FROM expenses WHERE status <> 'rejected' GROUP BY label ORDER BY total DESC LIMIT 8")->fetchAll();
$categoryReport = $db->query("SELECT category AS label, SUM(amount) AS total FROM expenses WHERE status <> 'rejected' GROUP BY category ORDER BY total DESC LIMIT 8")->fetchAll();
$moduleReport = $db->query("SELECT COALESCE(NULLIF(module_name, ''), 'manual') AS label, SUM(amount) AS total FROM expenses WHERE status IN ('approved','paid') GROUP BY label ORDER BY total DESC")->fetchAll();
$dailyReport = $db->query("SELECT $expenseDateExpr AS label, SUM(amount) AS total FROM expenses WHERE status IN ('approved','paid') GROUP BY $expenseDateExpr ORDER BY label DESC LIMIT 10")->fetchAll();

$moduleOptions = $db->query("SELECT DISTINCT module_name FROM expenses WHERE module_name IS NOT NULL AND module_name <> '' ORDER BY module_name")->fetchAll(PDO::FETCH_COLUMN);
$campusOptions = ['Rajanpur Campus', 'Fazilpur Campus', 'Kot Mithan Campus'];

$stmt = $db->prepare("SELECT * FROM expenses WHERE $whereSql ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$expenses = $stmt->fetchAll();

if ($isAdmin && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="financial-expenses-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Module', 'Campus', 'Category', 'Description', 'Amount', 'Status', 'Receipt']);
    foreach ($expenses as $expense) {
        fputcsv($output, [
            $expense['expense_date'] ?? date('Y-m-d', strtotime($expense['created_at'])),
            $expense['module_name'] ?? 'manual',
            $expense['campus'] ?? '',
            $expense['category'] ?? '',
            $expense['description'] ?? '',
            $expense['amount'] ?? 0,
            $expense['status'] ?? '',
            $expense['receipt_no'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

$recentTransactions = $db->query("
    SELECT
        CAST('Income' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS type,
        CAST(source AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS module_name,
        CAST(campus AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS campus,
        CAST(description AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS description,
        amount,
        CAST('posted' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS status,
        created_at
    FROM income
    UNION ALL
    SELECT
        CAST('Expense' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS type,
        CAST(module_name AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS module_name,
        CAST(campus AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS campus,
        CAST(description AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS description,
        amount,
        CAST(status AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS status,
        created_at
    FROM expenses
    ORDER BY created_at DESC
    LIMIT 12
")->fetchAll();

$page_title = 'Financial Overview';
include '../../includes/header.php';
?>

<style>
    .finance-page-head {
        background: linear-gradient(135deg, #0f2d48, #163f61);
        border-radius: 18px;
        padding: 24px;
        color: #fff;
        box-shadow: 0 18px 48px rgba(15,45,72,.16);
    }
    .finance-page-head p { color: rgba(255,255,255,.72); }
    .finance-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .kpi-grid {
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:16px;
    }
    .finance-card {
        background:#fff;
        border:1px solid #e7edf5;
        border-radius:14px;
        padding:18px;
        box-shadow:0 10px 28px rgba(15,45,72,.06);
        min-height:132px;
        display:flex;
        align-items:flex-start;
        gap:14px;
    }
    .finance-card i {
        flex:0 0 46px;
        width:46px;
        height:46px;
        display:inline-grid;
        place-items:center;
        border-radius:12px;
        background:rgba(78,194,181,.13);
        color:#2aa99b;
        font-size:1.1rem;
    }
    .finance-card span {
        display:block;
        color:#64748b;
        font-size:.74rem;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.04em;
        margin-bottom:8px;
    }
    .finance-card strong {
        display:block;
        font-size:clamp(1.35rem, 2vw, 1.9rem);
        color:#08243d;
        line-height:1.05;
        white-space:nowrap;
    }
    .finance-card .kpi-note {
        color:#94a3b8;
        font-size:.78rem;
        margin-top:8px;
    }
    .finance-card.is-good i { background:rgba(22,163,74,.12); color:#13864b; }
    .finance-card.is-good strong { color:#13864b; }
    .finance-card.is-warn i { background:rgba(245,158,11,.14); color:#b7791f; }
    .finance-card.is-bad i { background:rgba(239,68,68,.12); color:#dc2626; }
    .finance-panel {
        background:#fff;
        border:1px solid #e7edf5;
        border-radius:14px;
        box-shadow:0 10px 28px rgba(15,45,72,.06);
        overflow:hidden;
    }
    .finance-panel .card-header {
        border-bottom:1px solid #edf2f7 !important;
    }
    .bar-row { display:grid; grid-template-columns:120px 1fr 110px; gap:12px; align-items:center; margin-bottom:12px; }
    .bar-track { height:10px; border-radius:999px; background:#edf2f7; overflow:hidden; }
    .bar-fill { height:100%; border-radius:999px; background:#4ec2b5; }
    .mini-chart { display:flex; align-items:end; gap:12px; min-height:170px; padding-top:18px; }
    .mini-chart .month { flex:1; display:flex; flex-direction:column; justify-content:end; align-items:center; gap:6px; min-width:54px; }
    .mini-chart .income-bar { width:18px; background:#4ec2b5; border-radius:6px 6px 0 0; }
    .mini-chart .expense-bar { width:18px; background:#ef4444; border-radius:6px 6px 0 0; }
    .finance-modal .modal-content { border-radius: 18px; overflow: hidden; }
    .finance-modal .modal-header {
        background: #0f2d48;
        color: #fff;
        padding: 22px 26px;
    }
    .finance-modal .modal-body { padding: 26px; }
    .finance-modal .modal-footer { padding: 18px 26px; }
    .finance-modal .form-label {
        color: #0f2d48;
        font-size: .78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .03em;
        margin-bottom: 7px;
    }
    .finance-modal .form-control,
    .finance-modal .form-select {
        min-height: 48px;
        border-radius: 10px;
    }
    .finance-helper {
        background: #f8fafc;
        border: 1px solid #e7edf5;
        border-radius: 14px;
        padding: 14px;
        color: #64748b;
        font-size: .86rem;
    }
    @media (max-width: 575px) {
        .finance-page-head { padding: 18px; }
        .finance-actions .btn { width: 100%; }
        .bar-row { grid-template-columns: 1fr; gap: 6px; }
    }
    @media (max-width: 1200px) {
        .kpi-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 700px) {
        .kpi-grid { grid-template-columns:1fr; }
    }
</style>

<?php displayFlashMessage(); ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="finance-page-head mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-outline-light rounded-pill mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
            <h2 class="mb-1 fw-bold">Financial Overview</h2>
            <p class="mb-0">Track income, pending approvals, paid expenses, and campus/module reports from one place.</p>
        </div>
        <div class="finance-actions">
            <a href="index.php" class="btn btn-light rounded-pill px-4">
                <i class="fas fa-rotate-right me-2"></i>Reset
            </a>
            <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-outline-light rounded-pill px-4">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </a>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-plus me-2"></i>Add Manual Expense
            </button>
        </div>
    </div>
</div>

<div class="kpi-grid mb-4">
    <div class="finance-card">
        <i class="fas fa-arrow-trend-up"></i>
        <div><span>Total Income</span><strong><?= money($stats['income']) ?></strong><div class="kpi-note">Fee collections and POS sales</div></div>
    </div>
    <div class="finance-card">
        <i class="fas fa-arrow-trend-down"></i>
        <div><span>Approved/Paid Expenses</span><strong><?= money($stats['expenses']) ?></strong><div class="kpi-note">Used for profit/loss</div></div>
    </div>
    <div class="finance-card <?= $stats['net'] >= 0 ? 'is-good' : 'is-bad' ?>">
        <i class="fas fa-scale-balanced"></i>
        <div><span>Net Profit/Loss</span><strong><?= money($stats['net']) ?></strong><div class="kpi-note">Income minus approved/paid expenses</div></div>
    </div>
    <div class="finance-card is-warn">
        <i class="fas fa-hourglass-half"></i>
        <div><span>Pending Approval</span><strong><?= money($stats['pending']) ?></strong><div class="kpi-note">Not counted in profit yet</div></div>
    </div>
    <div class="finance-card">
        <i class="fas fa-receipt"></i>
        <div><span>All Expense Requests</span><strong><?= money($stats['all_expenses']) ?></strong><div class="kpi-note">Pending plus approved and paid</div></div>
    </div>
    <div class="finance-card">
        <i class="fas fa-money-check"></i>
        <div><span>Paid</span><strong><?= money($stats['paid']) ?></strong><div class="kpi-note">Completed payments</div></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="finance-panel h-100">
            <div class="card-header bg-white border-0 p-4"><h5 class="fw-bold mb-0">Monthly Summary</h5></div>
            <div class="card-body p-4">
                <?php $maxChart = max(array_map(fn($r) => max((float)$r['income_amount'], (float)$r['expense_amount']), $monthlyChart ?: [['income_amount' => 1, 'expense_amount' => 1]])); ?>
                <div class="mini-chart">
                    <?php foreach ($monthlyChart as $row): ?>
                        <div class="month">
                            <div class="d-flex gap-1 align-items-end" style="height:130px;">
                                <div class="income-bar" title="Income" style="height:<?= max(6, ((float)$row['income_amount'] / max($maxChart, 1)) * 120) ?>px"></div>
                                <div class="expense-bar" title="Expense" style="height:<?= max(6, ((float)$row['expense_amount'] / max($maxChart, 1)) * 120) ?>px"></div>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($row['ym']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted mt-3"><span class="badge" style="background:#4ec2b5;">&nbsp;</span> Income <span class="badge bg-danger ms-3">&nbsp;</span> Approved/Paid Expenses</div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="finance-panel h-100">
            <div class="card-header bg-white border-0 p-4">
                <h5 class="fw-bold mb-1">Top Expense Categories</h5>
                <p class="text-muted small mb-0">Includes pending requests so the owner can see real liability.</p>
            </div>
            <div class="card-body p-4">
                <?php $maxCategory = max(array_column($categoryReport ?: [['total' => 1]], 'total')); ?>
                <?php foreach ($categoryReport as $row): ?>
                    <div class="bar-row">
                        <span class="small fw-bold text-truncate"><?= htmlspecialchars($row['label']) ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= ((float)$row['total'] / max($maxCategory, 1)) * 100 ?>%"></div></div>
                        <span class="small text-end"><?= money($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$categoryReport): ?><p class="text-muted mb-0">No approved expenses yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="finance-panel h-100">
            <div class="card-header bg-white border-0 p-4">
                <h5 class="fw-bold mb-1">Campus Comparison</h5>
                <p class="text-muted small mb-0">Only Rajanpur, Fazilpur, and Kot Mithan campuses are used.</p>
            </div>
            <div class="card-body p-4">
                <?php $maxCampus = max(array_column($campusReport ?: [['total' => 1]], 'total')); ?>
                <?php foreach ($campusReport as $row): ?>
                    <div class="bar-row">
                        <span class="small fw-bold text-truncate"><?= htmlspecialchars($row['label']) ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= ((float)$row['total'] / max($maxCampus, 1)) * 100 ?>%"></div></div>
                        <span class="small text-end"><?= money($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$campusReport): ?><p class="text-muted mb-0">No campus expense data yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="finance-panel h-100">
            <div class="card-header bg-white border-0 p-4"><h5 class="fw-bold mb-0">Recent Transactions</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-4">Type</th><th>Module</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $row): ?>
                            <tr>
                                <td class="ps-4"><span class="badge <?= $row['type'] === 'Income' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($row['type']) ?></span></td>
                                <td><div class="fw-bold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['module_name'] ?? 'manual'))) ?></div><small class="text-muted"><?= htmlspecialchars($row['campus'] ?? '-') ?></small></td>
                                <td class="fw-bold"><?= money($row['amount']) ?></td>
                                <td class="text-capitalize"><?= htmlspecialchars($row['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="finance-panel mb-4">
    <div class="card-header bg-white border-0 p-4"><h5 class="fw-bold mb-0">Reports</h5></div>
    <div class="card-body p-4">
        <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#dailyReport">Daily</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#monthlyReport">Monthly</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#campusReport">Campus</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#categoryReport">Category</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#moduleReport">Module-wise</button></li>
        </ul>
        <div class="tab-content">
            <?php
            $reportSets = [
                'dailyReport' => $dailyReport,
                'monthlyReport' => $monthlyReport,
                'campusReport' => $campusReport,
                'categoryReport' => $categoryReport,
                'moduleReport' => $moduleReport
            ];
            ?>
            <?php foreach ($reportSets as $id => $rows): ?>
                <div class="tab-pane fade <?= $id === 'dailyReport' ? 'show active' : '' ?>" id="<?= $id ?>">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Label</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['label'] ?? $row['ym'] ?? '-') ?></td>
                                        <td class="text-end fw-bold"><?= money($row['total'] ?? $row['expense_amount'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?><tr><td colspan="2" class="text-center text-muted py-4">No report data yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="finance-panel mb-4">
    <div class="card-header bg-white border-0 p-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label small fw-bold">Search</label><input name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Description, module..."></div>
            <div class="col-md-2"><label class="form-label small fw-bold">Campus</label><select name="campus" class="form-select form-select-sm"><option value="">All</option><?php foreach ($campusOptions as $campus): ?><option value="<?= htmlspecialchars($campus) ?>" <?= $filterCampus === $campus ? 'selected' : '' ?>><?= htmlspecialchars($campus) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small fw-bold">Category</label><select name="category" class="form-select form-select-sm"><option value="">All</option><?php foreach ($categories as $category): ?><option value="<?= htmlspecialchars($category) ?>" <?= $filterCategory === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small fw-bold">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option><?php foreach (['pending','approved','paid','rejected'] as $status): ?><option value="<?= $status ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label small fw-bold">Module</label><select name="module" class="form-select form-select-sm"><option value="">All</option><?php foreach ($moduleOptions as $module): ?><option value="<?= htmlspecialchars($module) ?>" <?= $filterModule === $module ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $module))) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1"><button class="btn btn-navy btn-sm w-100">Go</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-4">Expense</th><th>Module</th><th>Campus</th><th>Amount</th><th>Status</th><th class="text-end pe-4">Action</th></tr></thead>
            <tbody>
                <?php if (!$expenses): ?><tr><td colspan="6" class="text-center text-muted py-5">No expenses found.</td></tr><?php endif; ?>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold"><?= htmlspecialchars($expense['category']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($expense['description'] ?? '') ?></small>
                        </td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $expense['module_name'] ?? 'manual'))) ?></td>
                        <td><?= htmlspecialchars($expense['campus'] ?? '-') ?></td>
                        <td class="fw-bold"><?= money($expense['amount']) ?></td>
                        <td><span class="badge rounded-pill text-capitalize <?= $expense['status'] === 'rejected' ? 'bg-danger' : ($expense['status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark') ?>"><?= htmlspecialchars($expense['status']) ?></span></td>
                        <td class="text-end pe-4">
                            <form method="POST" class="d-inline-flex gap-2">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="expense_id" value="<?= (int)$expense['id'] ?>">
                                <button name="action" value="update_status" formaction="?status_action=approve" class="btn btn-sm btn-outline-primary" onclick="this.form.status.value='approved'">Approve</button>
                                <button name="action" value="update_status" class="btn btn-sm btn-outline-success" onclick="this.form.status.value='paid'">Paid</button>
                                <button name="action" value="update_status" class="btn btn-sm btn-outline-danger" onclick="this.form.status.value='rejected'">Reject</button>
                                <input type="hidden" name="status" value="approved">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade finance-modal" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-1">Add Manual Expense</h5>
                        <p class="mb-0 small" style="color:rgba(255,255,255,.72);">This will enter Finance as pending until admin approval/payment.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Expense Date</label>
                                    <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Campus</label>
                                    <select name="campus" class="form-select" required><?php renderCampusOptions($_SESSION['user_campus'] ?? ''); ?></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select" required><?php foreach ($categories as $category): ?><option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option><?php endforeach; ?></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">PKR</span>
                                        <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select">
                                        <option>Cash</option>
                                        <option>Bank Transfer</option>
                                        <option>Cheque</option>
                                        <option>EasyPaisa/JazzCash</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Receipt / Reference</label>
                                    <input type="text" name="receipt_no" class="form-control" placeholder="Invoice, bill, voucher no.">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="4" placeholder="Write the purpose, vendor, and approval notes..." required></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="finance-helper h-100">
                                <h6 class="fw-bold text-navy mb-3"><i class="fas fa-shield-alt me-2 text-success"></i>Approval Flow</h6>
                                <p class="mb-3">Manual expenses are saved as pending. They appear in the owner/admin finance list and are counted only after approval or payment.</p>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Status</span><strong>Pending</strong></div>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Type</span><strong>Manual</strong></div>
                                <div class="d-flex justify-content-between py-2"><span>Reports</span><strong>After approval</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary rounded-pill px-4"><i class="fas fa-hourglass-half me-2"></i>Save as Pending</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
