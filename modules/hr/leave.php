<?php
// File: modules/hr/leave.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'hr', 'staff', 'teacher']);

$database = new Database();
$db = $database->getConnection();

$role = getUserRole();
$isLeaveManager = in_array($role, ['admin', 'owner', 'hr'], true);
$hasStaffTable = tableExists($db, 'staff');
$hasLeaveTable = tableExists($db, 'leave_applications');
$error = '';
$staffList = [];
$leaveRows = [];
$currentStaffId = null;
$statusCounts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total' => 0,
];

function hr_leave_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function hr_leave_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function hr_leave_total_days($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    return ((int)$start->diff($end)->format('%a')) + 1;
}

function hr_leave_badge($status) {
    $status = strtolower((string)$status);
    $classes = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
    ];
    return '<span class="badge ' . ($classes[$status] ?? 'bg-secondary') . '">' . ucfirst($status ?: 'pending') . '</span>';
}

function hr_leave_storage_status(PDO $db, $status) {
    $status = strtolower((string)$status);
    try {
        $stmt = $db->query("SHOW COLUMNS FROM leave_applications LIKE 'status'");
        $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $type = (string)($column['Type'] ?? '');
        if (stripos($type, 'enum') === 0 && strpos($type, "'" . ucfirst($status) . "'") !== false && strpos($type, "'" . $status . "'") === false) {
            return ucfirst($status);
        }
    } catch (Exception $e) {
        return $status;
    }
    return $status;
}

if ($hasStaffTable && !$isLeaveManager && columnExists($db, 'staff', 'user_id')) {
    $staffStmt = $db->prepare("SELECT id FROM staff WHERE user_id = ? LIMIT 1");
    $staffStmt->execute([getUserId()]);
    $currentStaffId = $staffStmt->fetchColumn();
    $currentStaffId = $currentStaffId ? (int)$currentStaffId : null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? 'submit_leave';

    try {
        requireCsrfToken();

        if (!$hasStaffTable || !$hasLeaveTable) {
            throw new Exception('Leave management table is missing. Please run the HR leave migration first.');
        }

        if ($action === 'submit_leave') {
            $staffId = $isLeaveManager ? (int)($_POST['staff_id'] ?? 0) : (int)$currentStaffId;
            $leaveType = trim(strip_tags((string)($_POST['leave_type'] ?? '')));
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $reason = trim(strip_tags((string)($_POST['reason'] ?? '')));

            if ($staffId <= 0) {
                throw new Exception('Please select a valid staff member.');
            }
            if ($leaveType === '') {
                throw new Exception('Please select a leave type.');
            }
            if (!hr_leave_valid_date($startDate) || !hr_leave_valid_date($endDate)) {
                throw new Exception('Please select valid leave dates.');
            }
            if (strtotime($endDate) < strtotime($startDate)) {
                throw new Exception('End date cannot be before start date.');
            }
            if ($reason === '') {
                throw new Exception('Please enter a reason for leave.');
            }

            $staffCheck = $db->prepare('SELECT id FROM staff WHERE id = ? LIMIT 1');
            $staffCheck->execute([$staffId]);
            if (!$staffCheck->fetchColumn()) {
                throw new Exception('Selected staff member was not found.');
            }

            $totalDays = hr_leave_total_days($startDate, $endDate);
            $columns = ['staff_id', 'leave_type', 'start_date', 'end_date', 'reason', 'status'];
            $placeholders = ['?', '?', '?', '?', '?', '?'];
            $params = [$staffId, $leaveType, $startDate, $endDate, $reason, hr_leave_storage_status($db, 'pending')];

            if (columnExists($db, 'leave_applications', 'total_days')) {
                array_splice($columns, 4, 0, 'total_days');
                array_splice($placeholders, 4, 0, '?');
                array_splice($params, 4, 0, $totalDays);
            }

            $sql = 'INSERT INTO leave_applications (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            setFlashMessage('success', 'Leave request submitted successfully.');
            redirect('leave.php');
        }

        if (in_array($action, ['approve_leave', 'reject_leave'], true)) {
            if (!$isLeaveManager) {
                throw new Exception('You are not allowed to approve or reject leave.');
            }

            $leaveId = (int)($_POST['leave_id'] ?? 0);
            $status = $action === 'approve_leave' ? 'approved' : 'rejected';
            $storageStatus = hr_leave_storage_status($db, $status);
            if ($leaveId <= 0) {
                throw new Exception('Invalid leave request selected.');
            }

            $sets = ['status = ?'];
            $params = [$storageStatus];
            if (columnExists($db, 'leave_applications', 'approved_by')) {
                $sets[] = 'approved_by = ?';
                $params[] = getUserId();
            }
            if (columnExists($db, 'leave_applications', 'reviewed_at')) {
                $sets[] = 'reviewed_at = NOW()';
            }
            $params[] = $leaveId;

            $stmt = $db->prepare('UPDATE leave_applications SET ' . implode(', ', $sets) . " WHERE id = ? AND LOWER(status) = 'pending'");
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new Exception('Leave request is not pending or could not be found.');
            }

            setFlashMessage('success', 'Leave request ' . $status . '.');
            redirect('leave.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('leave.php');
    }
}

if ($hasStaffTable) {
    $designationExpr = columnExists($db, 'staff', 'designation') ? 'designation' : (columnExists($db, 'staff', 'role') ? 'role' : "''");
    $employeeExpr = columnExists($db, 'staff', 'employee_code') ? 'employee_code' : "CONCAT('STAFF-', id)";
    $where = '1=1';
    if (columnExists($db, 'staff', 'status')) {
        $where .= " AND LOWER(COALESCE(status, 'active')) = 'active'";
    } elseif (columnExists($db, 'staff', 'is_active')) {
        $where .= ' AND COALESCE(is_active, 1) = 1';
    }

    $staffList = $db->query("
        SELECT id, full_name, $designationExpr AS designation, $employeeExpr AS employee_code
        FROM staff
        WHERE $where
        ORDER BY full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if ($hasLeaveTable && $hasStaffTable) {
    $totalDaysExpr = columnExists($db, 'leave_applications', 'total_days')
        ? 'l.total_days'
        : 'DATEDIFF(l.end_date, l.start_date) + 1';
    $approverJoin = columnExists($db, 'leave_applications', 'approved_by') ? 'LEFT JOIN users u ON u.id = l.approved_by' : '';
    $approverSelect = columnExists($db, 'leave_applications', 'approved_by') ? 'u.full_name AS approver_name' : "'' AS approver_name";
    $designationExpr = columnExists($db, 'staff', 'designation') ? 's.designation' : (columnExists($db, 'staff', 'role') ? 's.role' : "''");

    $where = [];
    $params = [];
    if (!$isLeaveManager) {
        $where[] = 'l.staff_id = ?';
        $params[] = (int)$currentStaffId;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $db->prepare("
        SELECT l.*, LOWER(l.status) AS status_key, $totalDaysExpr AS calculated_total_days,
               s.full_name, $designationExpr AS designation, $approverSelect
        FROM leave_applications l
        JOIN staff s ON s.id = l.staff_id
        $approverJoin
        $whereSql
        ORDER BY l.created_at DESC, l.id DESC
    ");
    $stmt->execute($params);
    $leaveRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($leaveRows as $leave) {
        $status = strtolower((string)($leave['status_key'] ?? 'pending'));
        if (isset($statusCounts[$status])) {
            $statusCounts[$status]++;
        }
        $statusCounts['total']++;
    }
}

$page_title = 'Leave Management';
include '../../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:.82rem;font-family:'Space Mono',monospace;">
        <li class="breadcrumb-item">
            <a href="../../dashboard.php" style="color:#4ec2b5;text-decoration:none;"><i class="fas fa-home me-1"></i>Dashboard</a>
        </li>
        <li class="breadcrumb-item">
            <a href="index.php" style="color:#4ec2b5;text-decoration:none;">HR Management</a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Leave Management</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <a href="index.php" class="btn btn-sm mb-3" style="background:rgba(78,194,181,.1);border:1px solid rgba(78,194,181,.3);color:#4ec2b5;border-radius:99px;padding:6px 18px;font-size:.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
            <i class="fas fa-arrow-left"></i> Back to HR Dashboard
        </a>
    </div>
    <div class="col-12 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h2 class="page-title mb-1"><i class="fas fa-calendar-alt me-2" style="color:var(--teal);"></i>Leave Management</h2>
            <div class="text-muted"><?= $isLeaveManager ? 'Submit, review, approve, and reject staff leave requests.' : 'Submit and track your leave requests.' ?></div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveRequestModal" <?= (!$hasLeaveTable || !$hasStaffTable || (!$isLeaveManager && !$currentStaffId)) ? 'disabled' : '' ?>>
            <i class="fas fa-calendar-plus me-2"></i>Apply Leave
        </button>
    </div>
</div>

<?php displayFlashMessage(); ?>

<?php if (!$hasStaffTable || !$hasLeaveTable): ?>
    <div class="alert alert-warning">
        <strong>Database migration required.</strong>
        <?= !$hasStaffTable ? 'The staff table is missing. ' : '' ?>
        <?= !$hasLeaveTable ? 'The leave_applications table is missing. ' : '' ?>
        Run <code>database/hr_leave_management.sql</code> before using leave management.
    </div>
<?php elseif (!$isLeaveManager && !$currentStaffId): ?>
    <div class="alert alert-warning">
        Your portal user is not linked with a staff profile. Ask admin to connect your staff record.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Requests', $statusCounts['total'], 'fas fa-list-check', 'text-primary', 'bg-primary'],
        ['Pending', $statusCounts['pending'], 'fas fa-hourglass-half', 'text-warning', 'bg-warning'],
        ['Approved', $statusCounts['approved'], 'fas fa-circle-check', 'text-success', 'bg-success'],
        ['Rejected', $statusCounts['rejected'], 'fas fa-circle-xmark', 'text-danger', 'bg-danger'],
    ];
    ?>
    <?php foreach ($cards as [$label, $value, $icon, $textClass, $bgClass]): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="<?= $bgClass ?> bg-opacity-10 <?= $textClass ?> rounded-3 p-3 me-3">
                        <i class="<?= $icon ?> fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold"><?= hr_leave_h($label) ?></p>
                        <h3 class="mb-0 fw-bold"><?= (int)$value ?></h3>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Leave History</h5>
        <span class="badge bg-light text-dark border"><?= count($leaveRows) ?> records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $leaveRows ? 'datatable' : '' ?>">
                <thead class="table-light">
                    <tr>
                        <th>Staff</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th class="text-center">Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Approver</th>
                        <?php if ($isLeaveManager): ?>
                            <th class="text-end">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$leaveRows): ?>
                        <tr>
                            <td colspan="<?= $isLeaveManager ? 8 : 7 ?>" class="text-center text-muted py-4">No leave records found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($leaveRows as $leave): ?>
                        <?php $status = strtolower((string)($leave['status_key'] ?? 'pending')); ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= hr_leave_h($leave['full_name']) ?></div>
                                <small class="text-muted"><?= hr_leave_h($leave['designation'] ?: 'Staff') ?></small>
                            </td>
                            <td><span class="badge bg-info text-dark"><?= hr_leave_h($leave['leave_type']) ?></span></td>
                            <td>
                                <?= hr_leave_h(date('d M Y', strtotime($leave['start_date']))) ?>
                                <span class="text-muted">to</span>
                                <?= hr_leave_h(date('d M Y', strtotime($leave['end_date']))) ?>
                            </td>
                            <td class="text-center fw-bold"><?= (int)$leave['calculated_total_days'] ?></td>
                            <td><?= hr_leave_h($leave['reason']) ?></td>
                            <td><?= hr_leave_badge($status) ?></td>
                            <td><?= hr_leave_h($leave['approver_name'] ?: '-') ?></td>
                            <?php if ($isLeaveManager): ?>
                                <td class="text-end">
                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="action" value="approve_leave">
                                            <input type="hidden" name="leave_id" value="<?= (int)$leave['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i></button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="action" value="reject_leave">
                                            <input type="hidden" name="leave_id" value="<?= (int)$leave['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Reject"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-lock"></i></button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="leaveRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="submit_leave">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Apply Leave</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($isLeaveManager): ?>
                        <div class="mb-3">
                            <label class="form-label">Staff Member *</label>
                            <select name="staff_id" class="form-select" required>
                                <option value="">Select staff...</option>
                                <?php foreach ($staffList as $staff): ?>
                                    <option value="<?= (int)$staff['id'] ?>">
                                        <?= hr_leave_h($staff['employee_code'] . ' - ' . $staff['full_name'] . ' (' . ($staff['designation'] ?: 'Staff') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Leave Type *</label>
                        <select name="leave_type" class="form-select" required>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Annual Leave">Annual Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Unpaid Leave">Unpaid Leave</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" id="leave_start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" id="leave_end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Days</label>
                        <input type="text" id="leave_total_days" class="form-control bg-light" value="0" readonly>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Reason *</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    :root { --teal: #4ec2b5; --navy: #0f2d48; }
    .page-title {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        color: var(--navy);
    }
    .btn-primary {
        background-color: var(--teal);
        border-color: var(--teal);
        color: var(--navy);
        font-weight: 600;
    }
    .btn-primary:hover {
        background-color: #3da89c;
        border-color: #3da89c;
        color: #fff;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const start = document.getElementById('leave_start_date');
    const end = document.getElementById('leave_end_date');
    const total = document.getElementById('leave_total_days');

    function refreshTotalDays() {
        if (!start || !end || !total || !start.value || !end.value) {
            if (total) total.value = '0';
            return;
        }
        const startDate = new Date(start.value + 'T00:00:00');
        const endDate = new Date(end.value + 'T00:00:00');
        if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime()) || endDate < startDate) {
            total.value = '0';
            return;
        }
        const days = Math.floor((endDate - startDate) / 86400000) + 1;
        total.value = String(days);
    }

    if (start) start.addEventListener('change', refreshTotalDays);
    if (end) end.addEventListener('change', refreshTotalDays);
});
</script>

<?php include '../../includes/footer.php'; ?>
