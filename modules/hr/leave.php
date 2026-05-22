<?php
// File: modules/hr/leave.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle Leave Approval/Rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'] === 'approve' ? 'Approved' : 'Rejected';
    $id = $_GET['id'];
    
    $stmt = $db->prepare("UPDATE leave_applications SET status = :status, approved_by = :approved_by WHERE id = :id");
    $stmt->execute([
        ':status' => $action,
        ':approved_by' => $_SESSION['user_id'],
        ':id' => $id
    ]);
    redirect('leave.php');
}

// Handle Add Leave
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $staff_id = $_POST['staff_id'];
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = sanitizeInput($_POST['reason']);
    
    $stmt = $db->prepare("INSERT INTO leave_applications (staff_id, leave_type, start_date, end_date, reason) VALUES (:staff_id, :leave_type, :start_date, :end_date, :reason)");
    $stmt->execute([
        ':staff_id' => $staff_id,
        ':leave_type' => $leave_type,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':reason' => $reason
    ]);
    $success = "Leave application submitted!";
}

$leaves = $db->query("
    SELECT l.*, s.full_name, u.full_name as approver
    FROM leave_applications l
    JOIN staff s ON l.staff_id = s.id
    LEFT JOIN users u ON l.approved_by = u.id
    ORDER BY l.created_at DESC
")->fetchAll();

$staff_list = $db->query("SELECT id, full_name FROM staff WHERE COALESCE(status, 'Active') = 'Active' ORDER BY full_name ASC")->fetchAll();

$page_title = "Leave Management";
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
            Leave Management
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
        <h2 class="page-title mb-0">Leave Applications</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveModal"><i class="fas fa-calendar-plus"></i> Apply Leave</button>
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
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Approver</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaves as $leave): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($leave['leave_type']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($leave['start_date'])) . ' to ' . date('d M Y', strtotime($leave['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                <td>
                                    <?php if ($leave['status'] === 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($leave['status'] === 'Approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $leave['approver'] ? htmlspecialchars($leave['approver']) : '-'; ?></td>
                                <td>
                                    <?php if ($leave['status'] === 'Pending'): ?>
                                        <a href="leave.php?action=approve&id=<?php echo $leave['id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i></a>
                                        <a href="leave.php?action=reject&id=<?php echo $leave['id']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-lock"></i></button>
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

<!-- Apply Leave Modal -->
<div class="modal fade" id="addLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Apply for Leave</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Staff Member *</label>
                        <select class="form-select" name="staff_id" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['full_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Leave Type *</label>
                        <select class="form-select" name="leave_type" required>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Annual Leave">Annual Leave</option>
                            <option value="Unpaid Leave">Unpaid Leave</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason *</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
