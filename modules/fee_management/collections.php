<?php
// File: modules/fee_management/collections.php - Fee Collections List
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

// Handle filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$class = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$academic_year = isset($_GET['academic_year']) ? sanitizeInput($_GET['academic_year']) : '';

// Build query
$query = "
    SELECT fc.*,
           COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), CONCAT('STD-', s.id)) as student_code,
           s.first_name, s.last_name, s.class, s.section,
           fc.fee_type, fs.amount as fee_amount, u.full_name as collected_by_name
    FROM fee_collections fc
    JOIN students s ON fc.student_id = s.id
    LEFT JOIN fee_structure fs ON fc.fee_type = fs.fee_type AND fs.class = s.class
    LEFT JOIN users u ON fc.collected_by = u.id
    WHERE 1=1
";

$params = [];

if ($search) {
    $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.roll_number LIKE :search OR s.registration_number LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($class) {
    $query .= " AND s.class = :class";
    $params[':class'] = $class;
}

if ($status) {
    $query .= " AND fc.status = :status";
    $params[':status'] = $status;
}

if ($date_from) {
    $query .= " AND fc.payment_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND fc.payment_date <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($academic_year) {
    $query .= " AND fc.academic_year = :academic_year";
    $params[':academic_year'] = $academic_year;
}

$query .= " ORDER BY fc.payment_date DESC, fc.created_at DESC";

// Get collections
$stmt = $db->prepare($query);
$stmt->execute($params);
$collections = $stmt->fetchAll();

// Get filter options
$classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
$academic_years = $db->query("SELECT DISTINCT academic_year FROM fee_collections ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Calculate totals
$total_collections = count($collections);
$total_amount = array_sum(array_column($collections, 'paid_amount'));

$page_title = "Fee Collections";
include '../../includes/header.php';
?>

<?php include 'fee_tabs.php'; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Fee Collections (<?php echo $total_collections; ?> records, Total: PKR <?php echo number_format($total_amount, 2); ?>)</h6>
        <a href="collect.php" class="btn btn-success btn-sm">
            <i class="fas fa-plus"></i> Collect Fee
        </a>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or ID" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <select name="class" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo $class == $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="Paid" <?php echo $status == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Failed" <?php echo $status == 'Failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="Refunded" <?php echo $status == 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" placeholder="From Date">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" placeholder="To Date">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-3">
                    <select name="academic_year" class="form-select">
                        <option value="">All Academic Years</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $academic_year == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="collections.php" class="btn btn-secondary">Reset</a>
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
                        <th>Amount Paid</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Collected By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections as $collection): ?>
                    <tr>
                        <td>
                            <?php echo $collection['first_name'] . ' ' . $collection['last_name']; ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($collection['student_code']); ?></small>
                        </td>
                        <td><?php echo $collection['class'] . '-' . $collection['section']; ?></td>
                        <td><?php echo $collection['fee_type']; ?></td>
                        <td>PKR <?php echo number_format($collection['paid_amount'], 2); ?></td>
                        <td><?php echo date('d M Y', strtotime($collection['payment_date'])); ?></td>
                        <td><?php echo $collection['payment_method']; ?></td>
                        <td>
                            <span class="badge bg-<?php
                                echo $collection['status'] == 'Paid' ? 'success' :
                                     ($collection['status'] == 'Pending' ? 'warning' :
                                     ($collection['status'] == 'Failed' ? 'danger' : 'info'));
                            ?>">
                                <?php echo $collection['status']; ?>
                            </span>
                        </td>
                        <td><?php echo $collection['collected_by_name'] ?: 'System'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $collection['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($collection['status'] == 'Paid'): ?>
                            <button class="btn btn-sm btn-warning" onclick="refundFee(<?php echo $collection['id']; ?>)">
                                <i class="fas fa-undo"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Collection Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fee Collection Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewDetails(collectionId) {
    // In a real application, you'd make an AJAX call to get details
    // For now, we'll show a simple message
    document.getElementById('detailsContent').innerHTML = `
        <div class="text-center">
            <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
            <p>Detailed view for collection ID: ${collectionId}</p>
            <p class="text-muted">This would show full transaction details, payment proof, etc.</p>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function refundFee(collectionId) {
    if (confirm('Are you sure you want to process a refund for this fee collection?')) {
        // In a real application, you'd make an AJAX call to process refund
        alert('Refund processing would be implemented here. Collection ID: ' + collectionId);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
