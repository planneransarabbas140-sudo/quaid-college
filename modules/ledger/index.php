<?php
// File: modules/ledger/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$bankMethods = ['Bank Transfer', 'Cheque', 'Card/ATM', 'Online Payment'];

function ledger_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function ledger_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function ledger_is_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function ledger_fetch_all(PDO $db, $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ledger_ensure_transaction_columns(PDO $db) {
    if (!columnExists($db, 'accounts_transactions', 'payment_method')) {
        $stmt = $db->prepare("ALTER TABLE accounts_transactions ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash'");
        $stmt->execute();
    }

    if (!columnExists($db, 'accounts_transactions', 'transaction_id')) {
        $stmt = $db->prepare("ALTER TABLE accounts_transactions ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL");
        $stmt->execute();
    }
}

function ledger_add_date_filter($dateExpression, array &$where, array &$params, $startDate, $endDate) {
    if ($startDate !== '') {
        $where[] = "DATE($dateExpression) >= ?";
        $params[] = $startDate;
    }

    if ($endDate !== '') {
        $where[] = "DATE($dateExpression) <= ?";
        $params[] = $endDate;
    }
}

function ledger_normalize_entries(array $rows) {
    $entries = [];
    foreach ($rows as $row) {
        $row['entry_date'] = $row['entry_date'] ? date('Y-m-d', strtotime($row['entry_date'])) : date('Y-m-d');
        $row['debit'] = (float)($row['debit'] ?? 0);
        $row['credit'] = (float)($row['credit'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 99);
        $entries[] = $row;
    }
    return $entries;
}

function ledger_sort_entries(array $entries) {
    usort($entries, function ($left, $right) {
        return [
            strtotime($left['entry_date']),
            (int)$left['sort_order'],
            (int)$left['id']
        ] <=> [
            strtotime($right['entry_date']),
            (int)$right['sort_order'],
            (int)$right['id']
        ];
    });

    return $entries;
}

function ledger_with_running_balance(array $entries, $openingBalance = 0) {
    $balance = (float)$openingBalance;
    $balancedEntries = [];

    foreach ($entries as $entry) {
        $balance += (float)$entry['credit'] - (float)$entry['debit'];
        $entry['running_balance'] = $balance;
        $balancedEntries[] = $entry;
    }

    return $balancedEntries;
}

function ledger_daily_closings(array $entries, $openingBalance = 0) {
    $balance = (float)$openingBalance;
    $closings = [];

    foreach ($entries as $entry) {
        $balance += (float)$entry['credit'] - (float)$entry['debit'];
        $closings[$entry['entry_date']] = $balance;
    }

    $rows = [];
    foreach ($closings as $date => $closingBalance) {
        $rows[] = [
            'entry_date' => $date,
            'closing_balance' => $closingBalance,
        ];
    }

    return $rows;
}

function ledger_monthly_summary(array $entries) {
    $summary = [];

    foreach ($entries as $entry) {
        $month = date('Y-m', strtotime($entry['entry_date']));
        if (!isset($summary[$month])) {
            $summary[$month] = [
                'month_key' => $month,
                'debit' => 0,
                'credit' => 0,
                'net' => 0,
                'cumulative_balance' => 0,
            ];
        }

        $summary[$month]['debit'] += (float)$entry['debit'];
        $summary[$month]['credit'] += (float)$entry['credit'];
    }

    ksort($summary);
    $cumulativeBalance = 0;
    foreach ($summary as $month => $row) {
        $summary[$month]['net'] = $row['credit'] - $row['debit'];
        $cumulativeBalance += $summary[$month]['net'];
        $summary[$month]['cumulative_balance'] = $cumulativeBalance;
    }

    return array_values($summary);
}

function ledger_source_badge_class($source) {
    $classes = [
        'Manual' => 'bg-primary',
        'Fee' => 'bg-success',
        'Payroll' => 'bg-warning text-dark',
        'Expense' => 'bg-danger',
    ];

    return $classes[$source] ?? 'bg-secondary';
}

function ledger_expense_date_expression(PDO $db) {
    return columnExists($db, 'expenses', 'expense_date') ? 'COALESCE(e.expense_date, DATE(e.created_at))' : 'DATE(e.created_at)';
}

function ledger_fetch_manual_entries(PDO $db, $startDate = '', $endDate = '', $paymentMethod = null, array $paymentMethods = []) {
    $where = ['1=1'];
    $params = [];
    ledger_add_date_filter('t.transaction_date', $where, $params, $startDate, $endDate);

    if ($paymentMethod !== null) {
        $where[] = 'COALESCE(t.payment_method, ?) = ?';
        $params[] = 'Cash';
        $params[] = $paymentMethod;
    }

    if ($paymentMethods) {
        $where[] = 't.payment_method IN (' . implode(', ', array_fill(0, count($paymentMethods), '?')) . ')';
        foreach ($paymentMethods as $method) {
            $params[] = $method;
        }
    }

    return ledger_normalize_entries(ledger_fetch_all($db, "
        SELECT
            t.id,
            t.transaction_date AS entry_date,
            'Manual' AS source,
            t.category,
            COALESCE(t.description, '') AS description,
            COALESCE(t.payment_method, 'Cash') AS payment_method,
            CASE WHEN t.transaction_type = 'Expense' THEN t.amount ELSE 0 END AS debit,
            CASE WHEN t.transaction_type = 'Income' THEN t.amount ELSE 0 END AS credit,
            t.transaction_type,
            1 AS sort_order
        FROM accounts_transactions t
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.transaction_date ASC, t.id ASC
    ", $params));
}

function ledger_fetch_all_entries(PDO $db, $startDate = '', $endDate = '') {
    $entries = ledger_fetch_manual_entries($db, $startDate, $endDate);

    if (tableExists($db, 'fee_collections')) {
        $feeAmountColumn = firstExistingColumn($db, 'fee_collections', ['amount_paid', 'paid_amount', 'amount']);
        if ($feeAmountColumn) {
            $where = ["fc.status = 'Paid'", "COALESCE(fc.`$feeAmountColumn`, 0) > 0"];
            $params = [];
            ledger_add_date_filter('fc.payment_date', $where, $params, $startDate, $endDate);
            $feePaymentColumn = columnExists($db, 'fee_collections', 'payment_method') ? "COALESCE(fc.payment_method, 'Cash')" : "'Cash'";

            $entries = array_merge($entries, ledger_normalize_entries(ledger_fetch_all($db, "
                SELECT
                    fc.id,
                    fc.payment_date AS entry_date,
                    'Fee' AS source,
                    'Fee Collection' AS category,
                    CONCAT('Fee collection for student #', fc.student_id) AS description,
                    $feePaymentColumn AS payment_method,
                    0 AS debit,
                    fc.`$feeAmountColumn` AS credit,
                    'Income' AS transaction_type,
                    2 AS sort_order
                FROM fee_collections fc
                WHERE " . implode(' AND ', $where) . "
                ORDER BY fc.payment_date ASC, fc.id ASC
            ", $params)));
        }
    }

    if (tableExists($db, 'payroll')) {
        $payrollDateExpression = columnExists($db, 'payroll', 'payment_date') ? 'COALESCE(p.payment_date, DATE(p.created_at))' : 'DATE(p.created_at)';
        $where = ["p.status = 'Paid'", 'COALESCE(p.net_salary, 0) > 0'];
        $params = [];
        ledger_add_date_filter($payrollDateExpression, $where, $params, $startDate, $endDate);

        $entries = array_merge($entries, ledger_normalize_entries(ledger_fetch_all($db, "
            SELECT
                p.id,
                $payrollDateExpression AS entry_date,
                'Payroll' AS source,
                'Staff Salary' AS category,
                CONCAT('Salary payment for staff #', p.staff_id, ' - ', p.salary_month) AS description,
                NULL AS payment_method,
                p.net_salary AS debit,
                0 AS credit,
                'Expense' AS transaction_type,
                3 AS sort_order
            FROM payroll p
            WHERE " . implode(' AND ', $where) . "
            ORDER BY entry_date ASC, p.id ASC
        ", $params)));
    }

    if (tableExists($db, 'expenses')) {
        $expenseDateExpression = ledger_expense_date_expression($db);
        $where = ["LOWER(e.status) IN ('approved', 'paid')", 'COALESCE(e.amount, 0) > 0'];
        $params = [];
        ledger_add_date_filter($expenseDateExpression, $where, $params, $startDate, $endDate);

        $entries = array_merge($entries, ledger_normalize_entries(ledger_fetch_all($db, "
            SELECT
                e.id,
                $expenseDateExpression AS entry_date,
                'Expense' AS source,
                COALESCE(NULLIF(e.category, ''), 'Expense') AS category,
                COALESCE(e.description, '') AS description,
                NULL AS payment_method,
                e.amount AS debit,
                0 AS credit,
                'Expense' AS transaction_type,
                4 AS sort_order
            FROM expenses e
            WHERE " . implode(' AND ', $where) . "
            ORDER BY entry_date ASC, e.id ASC
        ", $params)));
    }

    return ledger_sort_entries($entries);
}

function ledger_render_source_badge($source) {
    return '<span class="badge ' . ledger_source_badge_class($source) . '">' . ledger_h($source) . '</span>';
}

function ledger_render_entries_table(array $entries, $emptyText) {
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle <?= $entries ? 'datatable' : '' ?>">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th class="text-end">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$entries): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><?= ledger_h($emptyText) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= ledger_h(date('d M Y', strtotime($entry['entry_date']))) ?></td>
                        <td><?= ledger_render_source_badge($entry['source']) ?></td>
                        <td><?= ledger_h($entry['category']) ?></td>
                        <td><?= ledger_h($entry['description']) ?></td>
                        <td class="text-end fw-bold text-danger"><?= (float)$entry['debit'] > 0 ? ledger_money($entry['debit']) : '-' ?></td>
                        <td class="text-end fw-bold text-success"><?= (float)$entry['credit'] > 0 ? ledger_money($entry['credit']) : '-' ?></td>
                        <td class="text-end fw-bold <?= (float)$entry['running_balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= ledger_money($entry['running_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function ledger_render_closing_table(array $closings, $emptyText) {
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th class="text-end">Closing Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$closings): ?>
                    <tr><td colspan="2" class="text-center text-muted py-4"><?= ledger_h($emptyText) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($closings as $closing): ?>
                    <tr>
                        <td><?= ledger_h(date('d M Y', strtotime($closing['entry_date']))) ?></td>
                        <td class="text-end fw-bold <?= (float)$closing['closing_balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= ledger_money($closing['closing_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

$filterStart = ledger_is_valid_date($_GET['start_date'] ?? '') ? $_GET['start_date'] : '';
$filterEnd = ledger_is_valid_date($_GET['end_date'] ?? '') ? $_GET['end_date'] : '';

$generalEntries = [];
$cashEntries = [];
$bankEntries = [];
$dayEntries = [];
$cashClosings = [];
$bankClosings = [];
$monthlySummary = [];
$dayDebit = 0;
$dayCredit = 0;
$dayNet = 0;

try {
    ledger_ensure_transaction_columns($db);

    $generalEntries = ledger_with_running_balance(ledger_fetch_all_entries($db, $filterStart, $filterEnd));
    $cashEntries = ledger_with_running_balance(ledger_sort_entries(ledger_fetch_manual_entries($db, $filterStart, $filterEnd, 'Cash')));
    $bankEntries = ledger_with_running_balance(ledger_sort_entries(ledger_fetch_manual_entries($db, $filterStart, $filterEnd, null, $bankMethods)));
    $cashClosings = ledger_daily_closings($cashEntries);
    $bankClosings = ledger_daily_closings($bankEntries);

    $dayStart = $filterStart !== '' ? $filterStart : ($filterEnd !== '' ? $filterEnd : date('Y-m-d'));
    $dayEnd = $filterEnd !== '' ? $filterEnd : $dayStart;
    $dayEntries = ledger_with_running_balance(ledger_fetch_all_entries($db, $dayStart, $dayEnd));

    foreach ($dayEntries as $entry) {
        $dayDebit += (float)$entry['debit'];
        $dayCredit += (float)$entry['credit'];
    }
    $dayNet = $dayCredit - $dayDebit;

    $monthlySummary = ledger_monthly_summary($generalEntries);
} catch (Exception $e) {
    $error = 'Unable to load ledger data: ' . $e->getMessage();
}

$page_title = 'Ledger';
include '../../includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= ledger_h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h2 class="page-title mb-1">Ledger</h2>
        <div class="text-muted">General ledger, cash book, bank book, day book, and monthly balances.</div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-5">
                <label class="form-label small fw-bold">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= ledger_h($filterStart) ?>">
            </div>
            <div class="col-lg-3 col-md-5">
                <label class="form-label small fw-bold">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= ledger_h($filterEnd) ?>">
            </div>
            <div class="col-lg-3 col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Apply</button>
            </div>
            <div class="col-lg-3 col-md-12">
                <a href="index.php" class="btn btn-outline-secondary w-100"><i class="fas fa-rotate-right me-2"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-4" id="ledgerTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="general-ledger-tab" data-bs-toggle="tab" data-bs-target="#general-ledger" type="button" role="tab">General Ledger</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="cash-book-tab" data-bs-toggle="tab" data-bs-target="#cash-book" type="button" role="tab">Cash Book</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="bank-book-tab" data-bs-toggle="tab" data-bs-target="#bank-book" type="button" role="tab">Bank Book</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="day-book-tab" data-bs-toggle="tab" data-bs-target="#day-book" type="button" role="tab">Day Book</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">Summary</button>
    </li>
</ul>

<div class="tab-content" id="ledgerTabsContent">
    <div class="tab-pane fade show active" id="general-ledger" role="tabpanel" aria-labelledby="general-ledger-tab" tabindex="0">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">General Ledger</h5>
            </div>
            <div class="card-body">
                <?php ledger_render_entries_table($generalEntries, 'No ledger entries found.'); ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="cash-book" role="tabpanel" aria-labelledby="cash-book-tab" tabindex="0">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Opening Balance</div>
                        <h4 class="fw-bold mb-0"><?= ledger_money(0) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Total Debit</div>
                        <h4 class="text-danger fw-bold mb-0"><?= ledger_money(array_sum(array_column($cashEntries, 'debit'))) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Total Credit</div>
                        <h4 class="text-success fw-bold mb-0"><?= ledger_money(array_sum(array_column($cashEntries, 'credit'))) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Cash Entries</h5>
            </div>
            <div class="card-body">
                <?php ledger_render_entries_table($cashEntries, 'No cash entries found.'); ?>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">Daily Closing Balance</h5>
            </div>
            <div class="card-body">
                <?php ledger_render_closing_table($cashClosings, 'No cash closing balances found.'); ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="bank-book" role="tabpanel" aria-labelledby="bank-book-tab" tabindex="0">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Opening Balance</div>
                        <h4 class="fw-bold mb-0"><?= ledger_money(0) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Total Debit</div>
                        <h4 class="text-danger fw-bold mb-0"><?= ledger_money(array_sum(array_column($bankEntries, 'debit'))) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-bold">Total Credit</div>
                        <h4 class="text-success fw-bold mb-0"><?= ledger_money(array_sum(array_column($bankEntries, 'credit'))) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Bank Entries</h5>
            </div>
            <div class="card-body">
                <?php ledger_render_entries_table($bankEntries, 'No bank entries found.'); ?>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">Daily Closing Balance</h5>
            </div>
            <div class="card-body">
                <?php ledger_render_closing_table($bankClosings, 'No bank closing balances found.'); ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="day-book" role="tabpanel" aria-labelledby="day-book-tab" tabindex="0">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Total Debit</div>
                            <h4 class="text-danger fw-bold mb-0"><?= ledger_money($dayDebit) ?></h4>
                        </div>
                        <i class="fas fa-arrow-trend-down text-danger fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Total Credit</div>
                            <h4 class="text-success fw-bold mb-0"><?= ledger_money($dayCredit) ?></h4>
                        </div>
                        <i class="fas fa-arrow-trend-up text-success fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Net</div>
                            <h4 class="<?= $dayNet >= 0 ? 'text-primary' : 'text-danger' ?> fw-bold mb-0"><?= ledger_money($dayNet) ?></h4>
                        </div>
                        <i class="fas fa-scale-balanced text-primary fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">Day Book</h5>
            </div>
            <div class="card-body">
                <?php ledger_render_entries_table($dayEntries, 'No day book entries found.'); ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab" tabindex="0">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">Monthly Ledger Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle <?= $monthlySummary ? 'datatable' : '' ?>">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Total Debit</th>
                                <th class="text-end">Total Credit</th>
                                <th class="text-end">Net</th>
                                <th class="text-end">Cumulative Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$monthlySummary): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No monthly ledger summary found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($monthlySummary as $month): ?>
                                <tr>
                                    <td><?= ledger_h(date('M Y', strtotime($month['month_key'] . '-01'))) ?></td>
                                    <td class="text-end fw-bold text-danger"><?= ledger_money($month['debit']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= ledger_money($month['credit']) ?></td>
                                    <td class="text-end fw-bold <?= (float)$month['net'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= ledger_money($month['net']) ?></td>
                                    <td class="text-end fw-bold <?= (float)$month['cumulative_balance'] >= 0 ? 'text-primary' : 'text-danger' ?>"><?= ledger_money($month['cumulative_balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash) {
        const trigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
        if (trigger && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(trigger).show();
        }
    }

    document.querySelectorAll('#ledgerTabs [data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            history.replaceState(null, '', event.target.dataset.bsTarget);
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
