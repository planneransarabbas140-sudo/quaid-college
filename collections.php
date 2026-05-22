<?php
// File: modules/fee_management/collections.php - All Fee Collections
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

$query = "SELECT fc.*, s.first_name, s.last_name, s.student_id as student_no, s.class 
          FROM fee_collections fc
          JOIN students s ON fc.student_id = s.id
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (fc.receipt_number LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($from_date) {
    $query .= " AND fc.payment_date >= :from_date";
    $params[':from_date'] = $from_date;
}

if ($to_date) {
    $query .= " AND fc.payment_date <= :to_date";
    $params[':to_date'] = $to_date;
}

$query .= " ORDER BY fc.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$collections = $stmt->fetchAll();

// Get totals
$total_collected = array_sum(array_column($collections, 'paid_amount'));

$page_title = "Fee Collections";
include '../../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">Fee Collections</h6>
        <a href="add_collection.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> New Payment
        </a>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by receipt or student name" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="from_date" class="form-control" placeholder="From Date" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="to_date" class="form-control" placeholder="To Date" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="collections.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
        
        <!-- Summary -->
        <div class="alert alert-info mb-4">
            <strong>Total Collection:</strong> PKR <?php echo number_format($total_collected, 2); ?>
            <strong class="ms-4">Total Transactions:</strong> <?php echo count($collections); ?>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Fee Type</th>
                        <th>Amount (PKR)</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections as $collection): ?>
                    <tr>
                        <td><?php echo $collection['receipt_number']; ?></td>
                        <td>
                            <?php echo $collection['first_name'] . ' ' . $collection['last_name']; ?><br>
                            <small class="text-muted"><?php echo $collection['student_no']; ?></small>
                        </td>
                        <td><?php echo $collection['class']; ?></td>
                        <td><?php echo $collection['fee_type']; ?></td>
                        <td><?php echo number_format($collection['paid_amount'], 2); ?></td>
                        <td><?php echo date('d M Y', strtotime($collection['payment_date'])); ?></td>
                        <td><?php echo $collection['payment_method']; ?></td>
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

<?php include '../../includes/footer.php'; ?>