<?php
// File: modules/fee_management/index.php - Fee Management Dashboard
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Today's collections
$stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total 
                      FROM fee_collections 
                      WHERE payment_date = CURDATE() AND status = 'Paid'");
$stmt->execute();
$stats['today'] = $stmt->fetch();

// This month collections
$stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total 
                      FROM fee_collections 
                      WHERE MONTH(payment_date) = MONTH(CURDATE()) 
                      AND YEAR(payment_date) = YEAR(CURDATE())
                      AND status = 'Paid'");
$stmt->execute();
$stats['month'] = $stmt->fetch();

// Total outstanding dues
$stmt = $db->prepare("SELECT COUNT(DISTINCT student_id) as students, 
                      COALESCE(SUM(amount - paid_amount), 0) as total 
                      FROM fee_collections 
                      WHERE status IN ('Pending', 'Partially Paid') 
                      AND due_date < CURDATE()");
$stmt->execute();
$stats['outstanding'] = $stmt->fetch();

// Recent collections
$recent = $db->query("SELECT fc.*, s.first_name, s.last_name, s.student_id as student_no, s.class 
                      FROM fee_collections fc 
                      JOIN students s ON fc.student_id = s.id 
                      ORDER BY fc.created_at DESC LIMIT 10")->fetchAll();

$page_title = "Fee Management";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <!-- <div class="card border-left-primary shadow h-100 py-2"> -->
                <img src="logo.png" style="width: 100%; max-width: 150px; height: auto;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Today's Collection</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                PKR <?php echo number_format($stats['today']['total'], 2); ?>
                            </div>
                            <div class="small text-muted"><?php echo $stats['today']['count']; ?> transactions</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                This Month's Collection</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                PKR <?php echo number_format($stats['month']['total'], 2); ?>
                            </div>
                            <div class="small text-muted"><?php echo $stats['month']['count']; ?> transactions</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Outstanding Dues</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                PKR <?php echo number_format($stats['outstanding']['total'], 2); ?>
                            </div>
                            <div class="small text-muted"><?php echo $stats['outstanding']['students']; ?> students</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $stmt = $db->query("SELECT COUNT(*) as total FROM students");
                                echo $stmt->fetch()['total'];
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <h6 class="m-0 font-weight-bold mb-3">Quick Actions</h6>
                    <a href="add_collection.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Record Payment
                    </a>
                    <a href="structure.php" class="btn btn-success">
                        <i class="fas fa-cog"></i> Fee Structure
                    </a>
                    <a href="dues_report.php" class="btn btn-warning">
                        <i class="fas fa-file-alt"></i> Dues Report
                    </a>
                    <a href="collections.php" class="btn btn-info">
                        <i class="fas fa-list"></i> All Collections
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Collections -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Recent Fee Collections</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered datatable">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $collection): ?>
                                <tr>
                                    <td><?php echo $collection['receipt_number']; ?></td>
                                    <td><?php echo $collection['first_name'] . ' ' . $collection['last_name']; ?><br>
                                        <small class="text-muted"><?php echo $collection['student_no']; ?></small>
                                    </td>
                                    <td><?php echo $collection['class']; ?></td>
                                    <td>PKR <?php echo number_format($collection['paid_amount'], 2); ?></td>
                                    <td><?php echo date('d M Y', strtotime($collection['payment_date'])); ?></td>
                                    <td><?php echo $collection['payment_method']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $collection['status'] == 'Paid' ? 'success' : 
                                                ($collection['status'] == 'Partially Paid' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo $collection['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="receipt.php?id=<?php echo $collection['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                            <i class="fas fa-print"></i> Receipt
                                        </a>
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
</div>

<?php include '../../includes/footer.php'; ?>