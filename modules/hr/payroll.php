<?php
// File: modules/hr/payroll.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Generate payroll
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $staff_id = $_POST['staff_id'];
    $salary_month = $_POST['salary_month'];
    $basic_salary = $_POST['basic_salary'];
    $allowances = $_POST['allowances'] ?: 0;
    $deductions = $_POST['deductions'] ?: 0;
    $net_salary = $basic_salary + $allowances - $deductions;
    
    try {
        $stmt = $db->prepare("INSERT INTO payroll (staff_id, salary_month, basic_salary, allowances, deductions, net_salary) VALUES (:staff_id, :salary_month, :basic_salary, :allowances, :deductions, :net_salary)");
        $stmt->execute([
            ':staff_id' => $staff_id,
            ':salary_month' => $salary_month,
            ':basic_salary' => $basic_salary,
            ':allowances' => $allowances,
            ':deductions' => $deductions,
            ':net_salary' => $net_salary
        ]);
        $payrollId = (int)$db->lastInsertId();

        $staffStmt = $db->prepare("
            SELECT s.full_name, c.name AS campus
            FROM staff s
            LEFT JOIN campuses c ON c.id = s.campus_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $staffStmt->execute([$staff_id]);
        $staff = $staffStmt->fetch() ?: [];

        recordExpense($db, [
            'module_name' => 'hr_payroll',
            'reference_id' => $payrollId,
            'campus' => $staff['campus'] ?? null,
            'category' => 'Salaries',
            'description' => 'Salary for ' . ($staff['full_name'] ?? 'Staff') . ' - ' . $salary_month,
            'amount' => $net_salary,
            'expense_type' => 'auto',
            'status' => 'pending',
            'created_by' => getUserId()
        ]);

        $success = "Payroll generated successfully and salary expense sent for approval.";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Payroll for this month already exists for the selected staff.";
        } else {
            $error = "Error generating payroll: " . $e->getMessage();
        }
    }
}

// Mark as paid
if (isset($_GET['action']) && $_GET['action'] === 'pay' && isset($_GET['id'])) {
    $stmt = $db->prepare("UPDATE payroll SET status = 'Paid', payment_date = NOW() WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    updateExpenseStatusByReference($db, 'hr_payroll', (int)$_GET['id'], 'paid', getUserId());
    redirect('payroll.php');
}

$payroll_records = $db->query("
    SELECT p.*, s.full_name, s.designation 
    FROM payroll p 
    JOIN staff s ON p.staff_id = s.id 
    ORDER BY p.created_at DESC
")->fetchAll();

$staff_list = $db->query("SELECT id, full_name, designation, salary FROM staff WHERE COALESCE(status, 'Active') = 'Active' ORDER BY full_name ASC")->fetchAll();

$page_title = "Payroll Management";
include '../../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:.82rem;font-family:'Space Mono',monospace;">
        <li class="breadcrumb-item">
            <a href="../../dashboard.php" style="color:#4ec2b5;text-decoration:none;">
                <i class="fas fa-home me-1"></i>Dashboard
            </a>
        </li>
        <li class="breadcrumb-item">
            <a href="index.php" style="color:#4ec2b5;text-decoration:none;">
                HR Management
            </a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
            Payroll Management
        </li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <a href="index.php" class="btn btn-sm mb-3" 
           style="background:rgba(78,194,181,.1);
                  border:1px solid rgba(78,194,181,.3);
                  color:#4ec2b5;
                  border-radius:99px;
                  padding:6px 18px;
                  font-size:.85rem;
                  text-decoration:none;
                  display:inline-flex;
                  align-items:center;
                  gap:8px;
                  transition:all .3s ease;">
            <i class="fas fa-arrow-left"></i> Back to HR Dashboard
        </a>
    </div>
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">Payroll Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generatePayrollModal"><i class="fas fa-file-invoice-dollar"></i> Generate Payroll</button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Month</th>
                                <th>Basic Salary</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payroll_records as $record): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['full_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['designation'] ?? 'Staff'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($record['salary_month']); ?></td>
                                <td>Rs <?php echo number_format($record['basic_salary'], 2); ?></td>
                                <td><strong>Rs <?php echo number_format($record['net_salary'], 2); ?></strong></td>
                                <td>
                                    <?php if ($record['status'] === 'Paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $record['payment_date'] ? date('d M Y', strtotime($record['payment_date'])) : '-'; ?></td>
                                <td>
                                    <?php if ($record['status'] === 'Pending'): ?>
                                        <a href="payroll.php?action=pay&id=<?php echo $record['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Mark as Paid?');"><i class="fas fa-check"></i> Pay Now</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-info text-white"><i class="fas fa-print"></i> Payslip</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate Payroll Modal -->
<div class="modal fade" id="generatePayrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="generate">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Generate Payroll</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Staff Member *</label>
                        <select class="form-select" name="staff_id" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-salary="<?php echo htmlspecialchars($s['salary'] ?? ''); ?>"><?php echo $s['full_name'] . ' (' . ($s['designation'] ?? 'Staff') . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Salary Month *</label>
                        <input type="month" class="form-control" name="salary_month" value="<?php echo date('Y-m'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Basic Salary (Rs) *</label>
                        <input type="number" step="0.01" class="form-control" name="basic_salary" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Allowances (Rs)</label>
                            <input type="number" step="0.01" class="form-control" name="allowances" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Deductions (Rs)</label>
                            <input type="number" step="0.01" class="form-control" name="deductions" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
