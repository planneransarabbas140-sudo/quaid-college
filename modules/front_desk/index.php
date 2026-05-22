<?php
// File: modules/front_desk/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

// Handle Check-out for visitor
if (isset($_GET['checkout_id'])) {
    $checkout_id = $_GET['checkout_id'];
    $stmt = $db->prepare("UPDATE visitors SET check_out = NOW() WHERE id = :id");
    $stmt->execute([':id' => $checkout_id]);
    redirect('index.php');
}

// Fetch inquiries
$inquiries = $db->query("
    SELECT i.*, u.full_name as assignee 
    FROM front_desk_inquiries i 
    LEFT JOIN users u ON i.assigned_to = u.id 
    ORDER BY i.created_at DESC
")->fetchAll();

// Fetch visitors
$visitors = $db->query("SELECT * FROM visitors ORDER BY check_in DESC")->fetchAll();

$page_title = "Front Desk";
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">Front Desk Management</h2>
        <div>
            <a href="new_inquiry.php" class="btn btn-primary"><i class="fas fa-question-circle"></i> New Inquiry</a>
            <a href="new_visitor.php" class="btn btn-secondary"><i class="fas fa-id-badge"></i> Add Visitor</a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Inquiries</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Purpose</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inquiries as $inq): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($inq['inquiry_date'])); ?></td>
                                <td><?php echo htmlspecialchars($inq['name']); ?></td>
                                <td><?php echo htmlspecialchars($inq['phone']); ?></td>
                                <td><?php echo htmlspecialchars($inq['purpose']); ?></td>
                                <td><?php echo htmlspecialchars($inq['assignee'] ?? 'Unassigned'); ?></td>
                                <td>
                                    <?php if ($inq['status'] === 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Resolved</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info text-white"><i class="fas fa-eye"></i></button>
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

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Visitor Logs</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Visitor Name</th>
                                <th>Phone</th>
                                <th>Whom to Meet</th>
                                <th>Purpose</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visitors as $v): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v['visitor_name']); ?></td>
                                <td><?php echo htmlspecialchars($v['phone']); ?></td>
                                <td><?php echo htmlspecialchars($v['whom_to_meet']); ?></td>
                                <td><?php echo htmlspecialchars($v['purpose']); ?></td>
                                <td><?php echo date('d M Y h:i A', strtotime($v['check_in'])); ?></td>
                                <td>
                                    <?php if ($v['check_out']): ?>
                                        <?php echo date('d M Y h:i A', strtotime($v['check_out'])); ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">In Building</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$v['check_out']): ?>
                                        <a href="index.php?checkout_id=<?php echo $v['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Check out this visitor?');">Check Out</a>
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

<?php include '../../includes/footer.php'; ?>
