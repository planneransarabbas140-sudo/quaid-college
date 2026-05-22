<?php
/**
 * File: modules/hr/index.php
 * HR Management Dashboard for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

// --- FETCH HR STATS ---

// Total active staff
$total_staff = $db->query("SELECT COUNT(*) FROM staff WHERE COALESCE(status, 'Active') = 'Active'")->fetchColumn();

// Total active teachers
$total_teachers = $db->query("SELECT COUNT(*) FROM staff WHERE designation = 'Teacher' AND COALESCE(status, 'Active') = 'Active'")->fetchColumn();

// Pending leave applications
$pending_leaves = $db->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Pending'")->fetchColumn();

// This month payroll total (paid and pending)
$month_payroll = $db->query("SELECT COALESCE(SUM(net_salary),0) FROM payroll WHERE salary_month = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
if (!$month_payroll) {
    // Fallback if salary_month format is different or using created_at
    $month_payroll = $db->query("SELECT COALESCE(SUM(net_salary),0) FROM payroll WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
}

// --- FETCH LISTS ---

// Recent staff members
$staff_list = $db->query("SELECT * FROM staff ORDER BY id DESC LIMIT 5")->fetchAll();

// Pending leave applications with staff names
$leave_list = $db->query("
    SELECT l.*, s.full_name 
    FROM leave_applications l 
    JOIN staff s ON l.staff_id = s.id 
    WHERE l.status = 'Pending' 
    ORDER BY l.created_at DESC 
    LIMIT 5
")->fetchAll();

// Payroll summary for current month
$payroll_paid = $db->query("SELECT COALESCE(SUM(net_salary),0) FROM payroll WHERE status = 'Paid' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$payroll_pending = $db->query("SELECT COALESCE(SUM(net_salary),0) FROM payroll WHERE status = 'Pending' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();

$page_title = "HR Management Dashboard";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h2 class="page-title mb-0"><i class="fas fa-users-cog me-2" style="color: var(--teal);"></i>HR Management</h2>
            <div class="btn-group">
                <a href="staff.php" class="btn btn-outline-primary"><i class="fas fa-user-plus me-1"></i> Add Staff</a>
                <a href="leave.php" class="btn btn-outline-primary"><i class="fas fa-calendar-alt me-1"></i> Leaves</a>
                <a href="payroll.php" class="btn btn-outline-primary"><i class="fas fa-file-invoice-dollar me-1"></i> Payroll</a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                        <i class="fas fa-user-tie fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Total Staff</p>
                        <h3 class="mb-0 fw-bold"><?= $total_staff ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Teachers</p>
                        <h3 class="mb-0 fw-bold text-success"><?= $total_teachers ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                        <i class="fas fa-calendar-times fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Pending Leaves</p>
                        <h3 class="mb-0 fw-bold text-warning"><?= $pending_leaves ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-info bg-opacity-10 text-info rounded-3 p-3 me-3">
                        <i class="fas fa-money-check-alt fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Payroll Total</p>
                        <h3 class="mb-0 fw-bold text-info">PKR <?= number_format($month_payroll) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Staff -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Recent Staff</h5>
                    <a href="staff.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staff_list)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No staff found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($staff_list as $staff): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($staff['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($staff['email']) ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark"><?= htmlspecialchars($staff['designation'] ?? 'Staff') ?></span></td>
                                        <td>
                                            <?php if (($staff['status'] ?? 'Active') === 'Active'): ?>
                                                <span class="badge bg-success-subtle text-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="staff.php?id=<?= $staff['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Payroll Summary -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold">Payroll Summary (<?= date('F Y') ?>)</h5>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3">
                                <p class="text-muted small mb-1 text-uppercase">Paid</p>
                                <h4 class="fw-bold text-success mb-0">PKR <?= number_format($payroll_paid) ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3">
                                <p class="text-muted small mb-1 text-uppercase">Pending</p>
                                <h4 class="fw-bold text-warning mb-0">PKR <?= number_format($payroll_pending) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="payroll.php" class="btn btn-primary w-100 py-2">Generate / View Detailed Payroll</a>
                    </div>
                </div>
            </div>

            <!-- Pending Leaves -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Leave Requests</h5>
                    <a href="leave.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($leave_list)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-calendar-check fa-3x mb-3 text-light"></i>
                            <p>No pending leave requests.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($leave_list as $leave): ?>
                        <div class="d-flex align-items-center mb-3 p-3 border rounded-3 hover-shadow">
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?= htmlspecialchars($leave['full_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($leave['leave_type']) ?> (<?= date('d M', strtotime($leave['start_date'])) ?> - <?= date('d M', strtotime($leave['end_date'])) ?>)</div>
                            </div>
                            <div class="ms-2">
                                <a href="leave.php?action=approve&id=<?= $leave['id'] ?>" class="btn btn-sm btn-success rounded-circle" title="Approve"><i class="fas fa-check"></i></a>
                                <a href="leave.php?action=reject&id=<?= $leave['id'] ?>" class="btn btn-sm btn-danger rounded-circle" title="Reject"><i class="fas fa-times"></i></a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
    .page-title {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        color: var(--navy);
    }
    .stats-icon {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .hover-shadow {
        transition: all 0.3s;
    }
    .hover-shadow:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,0.05);
        background-color: #f8fafc !important;
    }
    .btn-primary { background-color: var(--teal); border-color: var(--teal); color: var(--navy); font-weight: 600; }
    .btn-primary:hover { background-color: #3da89c; border-color: #3da89c; color: white; }
    .btn-outline-primary { color: var(--navy); border-color: var(--teal); }
    .btn-outline-primary:hover { background-color: var(--teal); border-color: var(--teal); color: var(--navy); }
</style>

<?php include '../../includes/footer.php'; ?>
