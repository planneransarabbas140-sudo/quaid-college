<?php
// File: modules/complaints/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();
$role = getUserRole();
$isAdmin = $role === 'admin';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_complaint') {
    $subject = sanitizeInput($_POST['subject']);
    $complainant_name = sanitizeInput($_POST['complainant_name']);
    $complaint_number = "CMP-" . date('Ymd') . "-" . rand(1000, 9999);
    
    try {
        if ($role === 'student') {
            createApprovalRequest($db, 'complaints', 'create', [
                'complaint_number' => $complaint_number,
                'subject' => $subject,
                'complainant_name' => $complainant_name
            ]);
            $success = "Complaint submitted for admin approval! ID: " . $complaint_number;
        } else {
            $stmt = $db->prepare("INSERT INTO complaints (complaint_number, subject, complainant_name) VALUES (:complaint_number, :subject, :complainant_name)");
            $stmt->execute([
                ':complaint_number' => $complaint_number,
                ':subject' => $subject,
                ':complainant_name' => $complainant_name
            ]);
            $success = "Complaint registered successfully! ID: " . $complaint_number;
        }
    } catch (PDOException $e) {
        $error = "Error logging complaint: " . $e->getMessage();
    }
}

if (isset($_GET['resolve_id'])) {
    if (!$isAdmin) {
        setFlashMessage('error', 'Only admin can resolve complaints.');
        redirect('index.php');
    }
    $stmt = $db->prepare("UPDATE complaints SET status = 'Resolved' WHERE id = :id");
    $stmt->execute([':id' => $_GET['resolve_id']]);
    redirect('index.php');
}

$complaints = $db->query("SELECT * FROM complaints ORDER BY created_at DESC")->fetchAll();

$page_title = "Helpdesk & Complaints";
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">Helpdesk & Complaints</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addComplaintModal"><i class="fas fa-plus"></i> New Complaint</button>
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
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Complaints Log</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Subject</th>
                                <th>Complainant Name</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($c['complaint_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['subject']); ?></td>
                                <td><?php echo htmlspecialchars($c['complainant_name']); ?></td>
                                <td><?php echo date('d M Y h:i A', strtotime($c['created_at'])); ?></td>
                                <td>
                                    <?php if ($c['status'] === 'Open'): ?>
                                        <span class="badge bg-danger">Open</span>
                                    <?php elseif ($c['status'] === 'In Progress'): ?>
                                        <span class="badge bg-warning text-dark">In Progress</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Resolved</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isAdmin && $c['status'] !== 'Resolved'): ?>
                                        <a href="index.php?resolve_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Mark as Resolved?');"><i class="fas fa-check"></i> Resolve</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-check-double"></i></button>
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

<!-- Add Complaint Modal -->
<div class="modal fade" id="addComplaintModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_complaint">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Log New Complaint</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Complainant Name *</label>
                        <input type="text" class="form-control" name="complainant_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject / Issue *</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
