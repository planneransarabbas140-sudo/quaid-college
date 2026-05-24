<?php
// File: modules/fee_management/pending.php - Pending Fees
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$class_filter = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';
$academic_year = isset($_GET['academic_year']) ? sanitizeInput($_GET['academic_year']) : getCurrentAcademicYear();

// Build query for pending fees
$query = "
    SELECT s.id,
           COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), CONCAT('STD-', s.id)) as student_code,
           s.first_name, s.last_name, s.class, s.section,
           fs.fee_type, fs.amount, fs.frequency, fs.academic_year,
           COUNT(fc.id) as payments_made,
           COALESCE(SUM(fc.paid_amount), 0) as total_paid,
           (fs.amount - COALESCE(SUM(fc.paid_amount), 0)) as outstanding
    FROM students s
    CROSS JOIN fee_structure fs
    LEFT JOIN fee_collections fc ON s.id = fc.student_id
        AND fs.fee_type = fc.fee_type
        AND fc.status = 'Paid'
    WHERE 1=1
";

$params = [];

if ($class_filter) {
    $query .= " AND s.class = :class";
    $params[':class'] = $class_filter;
}

if ($academic_year) {
    $query .= " AND fs.academic_year = :academic_year";
    $params[':academic_year'] = $academic_year;
}

$query .= "
    GROUP BY s.id, s.roll_number, s.registration_number, s.first_name, s.last_name, s.class, s.section,
             fs.id, fs.fee_type, fs.amount, fs.frequency, fs.academic_year
    HAVING outstanding > 0
    ORDER BY s.class, s.section, s.first_name, s.last_name, fs.fee_type
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pending_fees = $stmt->fetchAll();

// Calculate totals
$total_students = count(array_unique(array_column($pending_fees, 'id')));
$total_outstanding = array_sum(array_column($pending_fees, 'outstanding'));

// Get filter options
$classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
$academic_years = $db->query("SELECT DISTINCT academic_year FROM fee_structure ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Pending Fees";
include '../../includes/header.php';
?>

<?php include 'fee_tabs.php'; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            Pending Fees (<?php echo $total_students; ?> students, Total Outstanding: PKR <?php echo number_format($total_outstanding, 2); ?>)
        </h6>
        <div>
            <a href="collect.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Collect Fee
            </a>
            <a href="reports.php" class="btn btn-info btn-sm">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <select name="class" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo $class_filter == $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="academic_year" class="form-select">
                        <option value="">All Academic Years</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $academic_year == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="pending.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Fee Type</th>
                        <th>Fee Amount</th>
                        <th>Paid Amount</th>
                        <th>Outstanding</th>
                        <th>Frequency</th>
                        <th>Academic Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_fees as $fee): ?>
                    <tr>
                        <td>
                            <?php echo $fee['first_name'] . ' ' . $fee['last_name']; ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($fee['student_code']); ?></small>
                        </td>
                        <td><?php echo $fee['class'] . '-' . $fee['section']; ?></td>
                        <td><?php echo $fee['fee_type']; ?></td>
                        <td>PKR <?php echo number_format($fee['amount'], 2); ?></td>
                        <td>PKR <?php echo number_format($fee['total_paid'], 2); ?></td>
                        <td>
                            <span class="badge bg-danger">
                                PKR <?php echo number_format($fee['outstanding'], 2); ?>
                            </span>
                        </td>
                        <td><?php echo $fee['frequency']; ?></td>
                        <td><?php echo $fee['academic_year']; ?></td>
                        <td>
                            <a href="collect.php?student_id=<?php echo $fee['id']; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-money-bill-wave"></i> Collect
                            </a>
                            <a href="../student_profile/view.php?id=<?php echo $fee['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> View Student
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pending_fees)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-success">
                            <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                            No pending fees found! All fees are collected.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row">
    <div class="col-md-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Students with Pending Fees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Outstanding Amount</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">PKR <?php echo number_format($total_outstanding, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average Outstanding per Student</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            PKR <?php echo $total_students > 0 ? number_format($total_outstanding / $total_students, 2) : '0.00'; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calculator fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
