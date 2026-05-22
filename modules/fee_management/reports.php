<?php
// File: modules/fee_management/reports.php - Fee Reports
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

// Get report parameters
$report_type = isset($_GET['report_type']) ? sanitizeInput($_GET['report_type']) : 'monthly_collection';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$class_filter = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';
$academic_year = isset($_GET['academic_year']) ? sanitizeInput($_GET['academic_year']) : getCurrentAcademicYear();

$report_data = [];
$report_title = '';

// Generate report based on type
switch ($report_type) {
    case 'monthly_collection':
        $report_title = 'Monthly Fee Collection Report';
        $query = "
            SELECT DATE_FORMAT(fc.payment_date, '%Y-%m') as month,
                   SUM(fc.paid_amount) as total_collection,
                   COUNT(DISTINCT fc.student_id) as students_paid,
                   COUNT(fc.id) as transactions
            FROM fee_collections fc
            WHERE fc.status = 'Paid'
            AND fc.payment_date BETWEEN :date_from AND :date_to
        ";
        $params = [':date_from' => $date_from, ':date_to' => $date_to];

        if ($class_filter) {
            $query .= " AND EXISTS (SELECT 1 FROM students s WHERE s.id = fc.student_id AND s.class = :class)";
            $params[':class'] = $class_filter;
        }

        $query .= " GROUP BY DATE_FORMAT(fc.payment_date, '%Y-%m') ORDER BY month DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;

    case 'class_wise':
        $report_title = 'Class-wise Fee Collection Report';
        $query = "
            SELECT s.class, s.section,
                   COUNT(DISTINCT s.id) as total_students,
                   COUNT(DISTINCT CASE WHEN fc.status = 'Paid' THEN fc.student_id END) as students_paid,
                   COALESCE(SUM(fc.paid_amount), 0) as total_collected,
                   ROUND((COUNT(DISTINCT CASE WHEN fc.status = 'Paid' THEN fc.student_id END) / COUNT(DISTINCT s.id)) * 100, 2) as payment_percentage
            FROM students s
            LEFT JOIN fee_collections fc ON s.id = fc.student_id
                AND fc.status = 'Paid'
                AND fc.payment_date BETWEEN :date_from AND :date_to
        ";
        $params = [':date_from' => $date_from, ':date_to' => $date_to];

        if ($class_filter) {
            $query .= " WHERE s.class = :class";
            $params[':class'] = $class_filter;
        }

        $query .= " GROUP BY s.class, s.section ORDER BY s.class, s.section";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;

    case 'fee_type_wise':
        $report_title = 'Fee Type-wise Collection Report';
        $query = "
            SELECT fs.fee_type, fs.class,
                   SUM(fs.amount) as total_fee_amount,
                   COALESCE(SUM(fc.paid_amount), 0) as total_collected,
                   COUNT(DISTINCT fc.student_id) as students_paid,
                   ROUND((COALESCE(SUM(fc.paid_amount), 0) / SUM(fs.amount)) * 100, 2) as collection_percentage
            FROM fee_structure fs
            LEFT JOIN fee_collections fc ON fs.fee_type = fc.fee_type
                AND fc.status = 'Paid'
                AND fc.payment_date BETWEEN :date_from AND :date_to
            WHERE 1=1
        ";
        $params = [':date_from' => $date_from, ':date_to' => $date_to];

        if ($class_filter) {
            $query .= " AND fs.class = :class";
            $params[':class'] = $class_filter;
        }

        if ($academic_year) {
            $query .= " AND fs.academic_year = :academic_year";
            $params[':academic_year'] = $academic_year;
        }

        $query .= " GROUP BY fs.fee_type, fs.class ORDER BY fs.fee_type";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;

    case 'outstanding_fees':
        $report_title = 'Outstanding Fees Report';
        $query = "
            SELECT s.class, s.section,
                   COUNT(DISTINCT s.id) as total_students,
                   COUNT(DISTINCT CASE WHEN outstanding > 0 THEN s.id END) as students_with_pending,
                   SUM(outstanding) as total_outstanding
            FROM (
                SELECT s.id, s.class, s.section,
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
                GROUP BY s.id, s.class, s.section, fs.id, fs.amount
                HAVING outstanding > 0
            ) as pending_data
            GROUP BY class, section
            ORDER BY class, section
        ";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
        break;
}

// Get filter options
$classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
$academic_years = $db->query("SELECT DISTINCT academic_year FROM fee_structure ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Fee Reports";
include '../../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><?php echo $report_title; ?></h6>
    </div>
    <div class="card-body">
        <!-- Report Filters -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="monthly_collection" <?php echo $report_type == 'monthly_collection' ? 'selected' : ''; ?>>Monthly Collection</option>
                        <option value="class_wise" <?php echo $report_type == 'class_wise' ? 'selected' : ''; ?>>Class-wise</option>
                        <option value="fee_type_wise" <?php echo $report_type == 'fee_type_wise' ? 'selected' : ''; ?>>Fee Type-wise</option>
                        <option value="outstanding_fees" <?php echo $report_type == 'outstanding_fees' ? 'selected' : ''; ?>>Outstanding Fees</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Class</label>
                    <select name="class" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo $class_filter == $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Academic Year</label>
                    <select name="academic_year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $academic_year == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Report Data -->
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%" cellspacing="0">
                <thead>
                    <?php if ($report_type == 'monthly_collection'): ?>
                    <tr>
                        <th>Month</th>
                        <th>Total Collection</th>
                        <th>Students Paid</th>
                        <th>Transactions</th>
                    </tr>
                    <?php elseif ($report_type == 'class_wise'): ?>
                    <tr>
                        <th>Class</th>
                        <th>Total Students</th>
                        <th>Students Paid</th>
                        <th>Total Collected</th>
                        <th>Payment %</th>
                    </tr>
                    <?php elseif ($report_type == 'fee_type_wise'): ?>
                    <tr>
                        <th>Fee Type</th>
                        <th>Class</th>
                        <th>Total Fee Amount</th>
                        <th>Total Collected</th>
                        <th>Students Paid</th>
                        <th>Collection %</th>
                    </tr>
                    <?php elseif ($report_type == 'outstanding_fees'): ?>
                    <tr>
                        <th>Class</th>
                        <th>Total Students</th>
                        <th>Students with Pending</th>
                        <th>Total Outstanding</th>
                    </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                    <tr>
                        <?php if ($report_type == 'monthly_collection'): ?>
                        <td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                        <td>PKR <?php echo number_format($row['total_collection'], 2); ?></td>
                        <td><?php echo $row['students_paid']; ?></td>
                        <td><?php echo $row['transactions']; ?></td>
                        <?php elseif ($report_type == 'class_wise'): ?>
                        <td><?php echo $row['class'] . '-' . $row['section']; ?></td>
                        <td><?php echo $row['total_students']; ?></td>
                        <td><?php echo $row['students_paid']; ?></td>
                        <td>PKR <?php echo number_format($row['total_collected'], 2); ?></td>
                        <td><?php echo $row['payment_percentage']; ?>%</td>
                        <?php elseif ($report_type == 'fee_type_wise'): ?>
                        <td><?php echo $row['fee_type']; ?></td>
                        <td><?php echo $row['class']; ?></td>
                        <td>PKR <?php echo number_format($row['total_fee_amount'], 2); ?></td>
                        <td>PKR <?php echo number_format($row['total_collected'], 2); ?></td>
                        <td><?php echo $row['students_paid']; ?></td>
                        <td><?php echo $row['collection_percentage']; ?>%</td>
                        <?php elseif ($report_type == 'outstanding_fees'): ?>
                        <td><?php echo $row['class'] . '-' . $row['section']; ?></td>
                        <td><?php echo $row['total_students']; ?></td>
                        <td><?php echo $row['students_with_pending']; ?></td>
                        <td>PKR <?php echo number_format($row['total_outstanding'], 2); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($report_data)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No data found for the selected criteria</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Export Options -->
        <div class="mt-3">
            <button onclick="exportToCSV()" class="btn btn-success">
                <i class="fas fa-download"></i> Export to CSV
            </button>
            <button onclick="printReport()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    // Simple CSV export - in production, you'd generate proper CSV
    alert('CSV export functionality would be implemented here');
}

function printReport() {
    window.print();
}
</script>

<style>
@media print {
    .no-print { display: none; }
    .card { border: none; box-shadow: none; }
}
</style>

<?php include '../../includes/footer.php'; ?>
