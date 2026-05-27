<?php
// File: modules/hr/payroll.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'hr']);

$database = new Database();
$db = $database->getConnection();

$hasStaffTable = tableExists($db, 'staff');
$hasPayrollTable = tableExists($db, 'payroll');
$selectedMonth = $_GET['month'] ?? $_POST['salary_month'] ?? date('Y-m');
$monthObj = DateTime::createFromFormat('Y-m', (string)$selectedMonth);
if (!$monthObj || $monthObj->format('Y-m') !== $selectedMonth) {
    $selectedMonth = date('Y-m');
}

$staffRows = [];
$payrollRows = [];
$payrollByStaff = [];
$summary = [
    'staff_count' => 0,
    'processed_count' => 0,
    'pending_count' => 0,
    'paid_count' => 0,
    'gross_total' => 0,
    'tax_total' => 0,
    'deduction_total' => 0,
    'net_total' => 0,
];

function hr_payroll_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function hr_payroll_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function hr_payroll_valid_month($month) {
    $month = (string)$month;
    $parsed = DateTime::createFromFormat('Y-m', $month);
    return $parsed && $parsed->format('Y-m') === $month;
}

function hr_payroll_storage_status(PDO $db, $status) {
    $status = strtolower((string)$status);
    try {
        $stmt = $db->query("SHOW COLUMNS FROM payroll LIKE 'status'");
        $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $type = (string)($column['Type'] ?? '');
        if (stripos($type, 'enum') === 0 && strpos($type, "'" . ucfirst($status) . "'") !== false && strpos($type, "'" . $status . "'") === false) {
            return ucfirst($status);
        }
    } catch (Exception $e) {
        return $status;
    }
    return $status;
}

function hr_payroll_badge($status) {
    $status = strtolower((string)$status);
    if ($status === 'paid') {
        return '<span class="badge bg-success">Paid</span>';
    }
    return '<span class="badge bg-warning text-dark">Pending</span>';
}

function hr_payroll_insert(PDO $db, array $data) {
    $columns = ['staff_id', 'salary_month', 'basic_salary', 'allowances', 'deductions', 'net_salary', 'status'];
    $params = [
        $data['staff_id'],
        $data['salary_month'],
        $data['basic_salary'],
        $data['allowances'],
        $data['deductions'],
        $data['net_salary'],
        hr_payroll_storage_status($db, 'pending'),
    ];

    if (columnExists($db, 'payroll', 'tax_amount')) {
        array_splice($columns, 5, 0, 'tax_amount');
        array_splice($params, 5, 0, $data['tax_amount']);
    }
    if (columnExists($db, 'payroll', 'gross_salary')) {
        array_splice($columns, 5, 0, 'gross_salary');
        array_splice($params, 5, 0, $data['gross_salary']);
    }
    if (columnExists($db, 'payroll', 'generated_by')) {
        $columns[] = 'generated_by';
        $params[] = getUserId();
    }

    $sql = 'INSERT INTO payroll (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        requireCsrfToken();

        if (!$hasStaffTable || !$hasPayrollTable) {
            throw new Exception('Payroll table is missing. Please run the HR payroll migration first.');
        }

        if ($action === 'generate_payroll') {
            if (!columnExists($db, 'staff', 'salary')) {
                throw new Exception('Staff salary column is missing. Please run the HR payroll migration first.');
            }

            $salaryMonth = $_POST['salary_month'] ?? date('Y-m');
            if (!hr_payroll_valid_month($salaryMonth)) {
                throw new Exception('Please select a valid salary month.');
            }

            $selectedStaff = is_array($_POST['staff_ids'] ?? null) ? $_POST['staff_ids'] : [];
            if (!$selectedStaff) {
                throw new Exception('Please select at least one staff member.');
            }

            $allowances = is_array($_POST['allowances'] ?? null) ? $_POST['allowances'] : [];
            $deductions = is_array($_POST['deductions'] ?? null) ? $_POST['deductions'] : [];
            $taxRates = is_array($_POST['tax_rate'] ?? null) ? $_POST['tax_rate'] : [];

            $db->beginTransaction();
            $created = 0;
            $skipped = 0;

            foreach ($selectedStaff as $staffIdRaw) {
                $staffId = (int)$staffIdRaw;
                if ($staffId <= 0) {
                    continue;
                }

                $staffStmt = $db->prepare('SELECT id, full_name, salary FROM staff WHERE id = ? LIMIT 1');
                $staffStmt->execute([$staffId]);
                $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
                if (!$staff) {
                    continue;
                }

                $exists = $db->prepare('SELECT id FROM payroll WHERE staff_id = ? AND salary_month = ? LIMIT 1');
                $exists->execute([$staffId, $salaryMonth]);
                if ($exists->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                $basicSalary = max(0, (float)($staff['salary'] ?? 0));
                $allowance = max(0, (float)($allowances[$staffId] ?? 0));
                $manualDeduction = max(0, (float)($deductions[$staffId] ?? 0));
                $taxRate = max(0, (float)($taxRates[$staffId] ?? 5));
                $grossSalary = $basicSalary + $allowance;
                $taxAmount = round($grossSalary * ($taxRate / 100), 2);
                $totalDeductions = $manualDeduction + $taxAmount;
                $netSalary = max(0, $grossSalary - $totalDeductions);

                hr_payroll_insert($db, [
                    'staff_id' => $staffId,
                    'salary_month' => $salaryMonth,
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowance,
                    'gross_salary' => $grossSalary,
                    'tax_amount' => $taxAmount,
                    'deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                ]);
                $created++;
            }

            $db->commit();
            setFlashMessage('success', 'Payroll saved. Created: ' . $created . ($skipped ? '. Duplicates skipped: ' . $skipped . '.' : '.'));
            redirect('payroll.php?month=' . urlencode($salaryMonth));
        }

        if ($action === 'mark_paid') {
            $payrollId = (int)($_POST['payroll_id'] ?? 0);
            if ($payrollId <= 0) {
                throw new Exception('Invalid payroll record selected.');
            }

            $sets = ['status = ?'];
            $params = [hr_payroll_storage_status($db, 'paid')];
            if (columnExists($db, 'payroll', 'payment_date')) {
                $sets[] = 'payment_date = CURDATE()';
            }
            $params[] = $payrollId;

            $stmt = $db->prepare('UPDATE payroll SET ' . implode(', ', $sets) . " WHERE id = ? AND LOWER(status) = 'pending'");
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new Exception('Payroll record is not pending or could not be found.');
            }

            setFlashMessage('success', 'Payroll marked as paid.');
            redirect('payroll.php?month=' . urlencode($selectedMonth));
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('payroll.php?month=' . urlencode($selectedMonth));
    }
}

$slipId = (int)($_GET['slip_id'] ?? 0);
if ($slipId > 0 && $hasPayrollTable && $hasStaffTable) {
    $grossExpr = columnExists($db, 'payroll', 'gross_salary') ? 'p.gross_salary' : '(p.basic_salary + COALESCE(p.allowances, 0))';
    $taxExpr = columnExists($db, 'payroll', 'tax_amount') ? 'p.tax_amount' : '0';
    $designationExpr = columnExists($db, 'staff', 'designation') ? 's.designation' : (columnExists($db, 'staff', 'role') ? 's.role' : "''");
    $employeeExpr = columnExists($db, 'staff', 'employee_code') ? 's.employee_code' : "CONCAT('STAFF-', s.id)";

    $stmt = $db->prepare("
        SELECT p.*, $grossExpr AS calculated_gross, $taxExpr AS calculated_tax,
               s.full_name, $designationExpr AS designation, $employeeExpr AS employee_code
        FROM payroll p
        JOIN staff s ON s.id = p.staff_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$slipId]);
    $slip = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($slip) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Salary Slip | <?= hr_payroll_h($slip['full_name']) ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background:#f8fafc; color:#0f2d48; }
                .slip { max-width: 820px; margin: 32px auto; background:#fff; border:1px solid #e8eef5; padding:32px; }
                @media print { .no-print { display:none !important; } body { background:#fff; } .slip { margin:0; max-width:none; border:0; } }
            </style>
        </head>
        <body>
            <div class="slip shadow-sm">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-1">Quaid-e-Azam Group of Colleges</h3>
                        <div class="text-muted">Salary Slip - <?= hr_payroll_h(date('F Y', strtotime($slip['salary_month'] . '-01'))) ?></div>
                    </div>
                    <div class="text-end no-print">
                        <button onclick="window.print()" class="btn btn-primary">Print</button>
                        <a href="payroll.php?month=<?= urlencode($slip['salary_month']) ?>" class="btn btn-outline-secondary">Back</a>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><strong>Employee:</strong> <?= hr_payroll_h($slip['full_name']) ?></div>
                    <div class="col-md-6"><strong>Employee Code:</strong> <?= hr_payroll_h($slip['employee_code']) ?></div>
                    <div class="col-md-6"><strong>Designation:</strong> <?= hr_payroll_h($slip['designation'] ?: 'Staff') ?></div>
                    <div class="col-md-6"><strong>Status:</strong> <?= hr_payroll_h(ucfirst(strtolower($slip['status']))) ?></div>
                </div>
                <table class="table table-bordered">
                    <tbody>
                        <tr><th>Basic Salary</th><td class="text-end"><?= hr_payroll_money($slip['basic_salary']) ?></td></tr>
                        <tr><th>Allowances</th><td class="text-end"><?= hr_payroll_money($slip['allowances']) ?></td></tr>
                        <tr><th>Gross Salary</th><td class="text-end"><?= hr_payroll_money($slip['calculated_gross']) ?></td></tr>
                        <tr><th>Tax</th><td class="text-end"><?= hr_payroll_money($slip['calculated_tax']) ?></td></tr>
                        <tr><th>Total Deductions</th><td class="text-end"><?= hr_payroll_money($slip['deductions']) ?></td></tr>
                        <tr class="table-light"><th>Net Salary</th><td class="text-end fw-bold"><?= hr_payroll_money($slip['net_salary']) ?></td></tr>
                    </tbody>
                </table>
                <div class="row mt-5 pt-4">
                    <div class="col-6"><div class="border-top pt-2 text-center">Prepared By</div></div>
                    <div class="col-6"><div class="border-top pt-2 text-center">Received By</div></div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

if ($hasStaffTable) {
    $designationExpr = columnExists($db, 'staff', 'designation') ? 'designation' : (columnExists($db, 'staff', 'role') ? 'role' : "''");
    $employeeExpr = columnExists($db, 'staff', 'employee_code') ? 'employee_code' : "CONCAT('STAFF-', id)";
    $salaryExpr = columnExists($db, 'staff', 'salary') ? 'salary' : '0';
    $where = '1=1';
    if (columnExists($db, 'staff', 'status')) {
        $where .= " AND LOWER(COALESCE(status, 'active')) = 'active'";
    } elseif (columnExists($db, 'staff', 'is_active')) {
        $where .= ' AND COALESCE(is_active, 1) = 1';
    }

    $staffRows = $db->query("
        SELECT id, full_name, $designationExpr AS designation, $employeeExpr AS employee_code, $salaryExpr AS salary
        FROM staff
        WHERE $where
        ORDER BY full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if ($hasPayrollTable && $hasStaffTable) {
    $grossExpr = columnExists($db, 'payroll', 'gross_salary') ? 'p.gross_salary' : '(p.basic_salary + COALESCE(p.allowances, 0))';
    $taxExpr = columnExists($db, 'payroll', 'tax_amount') ? 'p.tax_amount' : '0';
    $designationExpr = columnExists($db, 'staff', 'designation') ? 's.designation' : (columnExists($db, 'staff', 'role') ? 's.role' : "''");
    $employeeExpr = columnExists($db, 'staff', 'employee_code') ? 's.employee_code' : "CONCAT('STAFF-', s.id)";

    $stmt = $db->prepare("
        SELECT p.*, LOWER(p.status) AS status_key, $grossExpr AS calculated_gross, $taxExpr AS calculated_tax,
               s.full_name, $designationExpr AS designation, $employeeExpr AS employee_code
        FROM payroll p
        JOIN staff s ON s.id = p.staff_id
        WHERE p.salary_month = ?
        ORDER BY s.full_name ASC
    ");
    $stmt->execute([$selectedMonth]);
    $payrollRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($payrollRows as $row) {
        $payrollByStaff[(int)$row['staff_id']] = $row;
        $summary['processed_count']++;
        $summary['gross_total'] += (float)$row['calculated_gross'];
        $summary['tax_total'] += (float)$row['calculated_tax'];
        $summary['deduction_total'] += (float)$row['deductions'];
        $summary['net_total'] += (float)$row['net_salary'];
        if (($row['status_key'] ?? '') === 'paid') {
            $summary['paid_count']++;
        } else {
            $summary['pending_count']++;
        }
    }
}
$summary['staff_count'] = count($staffRows);

$page_title = 'Payroll Management';
include '../../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:.82rem;font-family:'Space Mono',monospace;">
        <li class="breadcrumb-item">
            <a href="../../dashboard.php" style="color:#4ec2b5;text-decoration:none;"><i class="fas fa-home me-1"></i>Dashboard</a>
        </li>
        <li class="breadcrumb-item">
            <a href="index.php" style="color:#4ec2b5;text-decoration:none;">HR Management</a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Payroll Management</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <a href="index.php" class="btn btn-sm mb-3" style="background:rgba(78,194,181,.1);border:1px solid rgba(78,194,181,.3);color:#4ec2b5;border-radius:99px;padding:6px 18px;font-size:.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
            <i class="fas fa-arrow-left"></i> Back to HR Dashboard
        </a>
    </div>
    <div class="col-12 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h2 class="page-title mb-1"><i class="fas fa-file-invoice-dollar me-2" style="color:var(--teal);"></i>Payroll Management</h2>
            <div class="text-muted">Generate monthly payroll, calculate tax and deductions, then print salary slips.</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input type="month" name="month" class="form-control" value="<?= hr_payroll_h($selectedMonth) ?>" required>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Load</button>
        </form>
    </div>
</div>

<?php displayFlashMessage(); ?>

<?php if (!$hasStaffTable || !$hasPayrollTable): ?>
    <div class="alert alert-warning">
        <strong>Database migration required.</strong>
        <?= !$hasStaffTable ? 'The staff table is missing. ' : '' ?>
        <?= !$hasPayrollTable ? 'The payroll table is missing. ' : '' ?>
        Run <code>database/hr_payroll.sql</code> before using payroll.
    </div>
<?php elseif (!columnExists($db, 'staff', 'salary')): ?>
    <div class="alert alert-warning">
        Staff salary column is missing. Run <code>database/hr_payroll.sql</code> so staff salaries can be stored.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Active Staff', $summary['staff_count'], 'fas fa-users', 'text-primary', 'bg-primary'],
        ['Processed', $summary['processed_count'], 'fas fa-list-check', 'text-info', 'bg-info'],
        ['Pending', $summary['pending_count'], 'fas fa-clock', 'text-warning', 'bg-warning'],
        ['Paid', $summary['paid_count'], 'fas fa-circle-check', 'text-success', 'bg-success'],
    ];
    ?>
    <?php foreach ($cards as [$label, $value, $icon, $textClass, $bgClass]): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="<?= $bgClass ?> bg-opacity-10 <?= $textClass ?> rounded-3 p-3 me-3">
                        <i class="<?= $icon ?> fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold"><?= hr_payroll_h($label) ?></p>
                        <h3 class="mb-0 fw-bold"><?= (int)$value ?></h3>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold">Payroll Summary - <?= hr_payroll_h(date('F Y', strtotime($selectedMonth . '-01'))) ?></h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="p-3 bg-light rounded-3"><div class="text-muted small">Gross Salary</div><strong><?= hr_payroll_money($summary['gross_total']) ?></strong></div></div>
            <div class="col-md-3"><div class="p-3 bg-light rounded-3"><div class="text-muted small">Tax</div><strong class="text-danger"><?= hr_payroll_money($summary['tax_total']) ?></strong></div></div>
            <div class="col-md-3"><div class="p-3 bg-light rounded-3"><div class="text-muted small">Total Deductions</div><strong class="text-danger"><?= hr_payroll_money($summary['deduction_total']) ?></strong></div></div>
            <div class="col-md-3"><div class="p-3 bg-light rounded-3"><div class="text-muted small">Net Payable</div><strong class="text-success"><?= hr_payroll_money($summary['net_total']) ?></strong></div></div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Generate Payroll</h5>
        <span class="badge bg-light text-dark border">Duplicate staff/month rows are skipped</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$staffRows): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                <h5 class="fw-bold">No active staff found</h5>
                <p class="mb-0">Add staff and salary values before generating payroll.</p>
            </div>
        <?php else: ?>
            <form method="POST">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="generate_payroll">
                <input type="hidden" name="salary_month" value="<?= hr_payroll_h($selectedMonth) ?>">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="payrollGenerateTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Run</th>
                                <th>Staff</th>
                                <th>Designation</th>
                                <th class="text-end">Salary</th>
                                <th class="text-end">Allowance</th>
                                <th class="text-end">Tax %</th>
                                <th class="text-end">Other Deduction</th>
                                <th class="text-end">Net Preview</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffRows as $staff): ?>
                                <?php
                                $staffId = (int)$staff['id'];
                                $salary = (float)($staff['salary'] ?? 0);
                                $existing = $payrollByStaff[$staffId] ?? null;
                                ?>
                                <tr data-payroll-row data-salary="<?= hr_payroll_h($salary) ?>">
                                    <td class="ps-4">
                                        <input type="checkbox" class="form-check-input" name="staff_ids[]" value="<?= $staffId ?>" <?= $existing || !$hasPayrollTable || $salary <= 0 ? 'disabled' : 'checked' ?>>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= hr_payroll_h($staff['full_name']) ?></div>
                                        <small class="text-muted"><?= hr_payroll_h($staff['employee_code']) ?></small>
                                    </td>
                                    <td><span class="badge bg-info text-dark"><?= hr_payroll_h($staff['designation'] ?: 'Staff') ?></span></td>
                                    <td class="text-end fw-bold"><?= hr_payroll_money($salary) ?></td>
                                    <td><input type="number" min="0" step="0.01" class="form-control text-end" name="allowances[<?= $staffId ?>]" value="0" data-allowance <?= $existing ? 'disabled' : '' ?>></td>
                                    <td><input type="number" min="0" step="0.01" class="form-control text-end" name="tax_rate[<?= $staffId ?>]" value="5" data-tax-rate <?= $existing ? 'disabled' : '' ?>></td>
                                    <td><input type="number" min="0" step="0.01" class="form-control text-end" name="deductions[<?= $staffId ?>]" value="0" data-deduction <?= $existing ? 'disabled' : '' ?>></td>
                                    <td class="text-end fw-bold text-success" data-net-preview><?= $existing ? hr_payroll_money($existing['net_salary']) : hr_payroll_money($salary - ($salary * 0.05)) ?></td>
                                    <td><?= $existing ? hr_payroll_badge($existing['status_key']) : '<span class="badge bg-light text-dark border">Not generated</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-top d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4" <?= (!$hasPayrollTable || !columnExists($db, 'staff', 'salary')) ? 'disabled' : '' ?>>
                        <i class="fas fa-save me-2"></i>Save Monthly Payroll
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0 fw-bold">Payroll Records</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $payrollRows ? 'datatable' : '' ?>">
                <thead class="table-light">
                    <tr>
                        <th>Staff</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$payrollRows): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No payroll generated for this month.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($payrollRows as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= hr_payroll_h($row['full_name']) ?></div>
                                <small class="text-muted"><?= hr_payroll_h($row['employee_code']) ?> | <?= hr_payroll_h($row['designation'] ?: 'Staff') ?></small>
                            </td>
                            <td class="text-end"><?= hr_payroll_money($row['calculated_gross']) ?></td>
                            <td class="text-end text-danger"><?= hr_payroll_money($row['calculated_tax']) ?></td>
                            <td class="text-end text-danger"><?= hr_payroll_money($row['deductions']) ?></td>
                            <td class="text-end fw-bold text-success"><?= hr_payroll_money($row['net_salary']) ?></td>
                            <td><?= hr_payroll_badge($row['status_key']) ?></td>
                            <td><?= !empty($row['payment_date']) ? hr_payroll_h(date('d M Y', strtotime($row['payment_date']))) : '-' ?></td>
                            <td class="text-end">
                                <a href="payroll.php?slip_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white" target="_blank"><i class="fas fa-print me-1"></i>Slip</a>
                                <?php if (($row['status_key'] ?? '') !== 'paid'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfTokenInput() ?>
                                        <input type="hidden" name="action" value="mark_paid">
                                        <input type="hidden" name="payroll_id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Paid</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    :root { --teal: #4ec2b5; --navy: #0f2d48; }
    .page-title {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        color: var(--navy);
    }
    .btn-primary {
        background-color: var(--teal);
        border-color: var(--teal);
        color: var(--navy);
        font-weight: 600;
    }
    .btn-primary:hover {
        background-color: #3da89c;
        border-color: #3da89c;
        color: #fff;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function money(amount) {
        return 'Rs ' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    document.querySelectorAll('[data-payroll-row]').forEach(function (row) {
        const salary = Number(row.dataset.salary || 0);
        const allowance = row.querySelector('[data-allowance]');
        const taxRate = row.querySelector('[data-tax-rate]');
        const deduction = row.querySelector('[data-deduction]');
        const preview = row.querySelector('[data-net-preview]');

        function refresh() {
            const gross = salary + Number(allowance && allowance.value ? allowance.value : 0);
            const tax = gross * (Number(taxRate && taxRate.value ? taxRate.value : 0) / 100);
            const otherDeduction = Number(deduction && deduction.value ? deduction.value : 0);
            const net = Math.max(0, gross - tax - otherDeduction);
            if (preview) preview.textContent = money(net);
        }

        [allowance, taxRate, deduction].forEach(function (input) {
            if (input) input.addEventListener('input', refresh);
        });
        refresh();
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
