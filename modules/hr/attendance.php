<?php
// File: modules/hr/attendance.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'hr']);

$database = new Database();
$db = $database->getConnection();

$page_title = 'Staff Attendance';
$attendanceDate = $_GET['date'] ?? $_POST['attendance_date'] ?? date('Y-m-d');
$dateObj = DateTime::createFromFormat('Y-m-d', (string)$attendanceDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $attendanceDate) {
    $attendanceDate = date('Y-m-d');
}

$allowedStatuses = ['present', 'absent', 'leave'];
$hasStaffTable = tableExists($db, 'staff');
$hasAttendanceTable = tableExists($db, 'staff_attendance');
$staffRows = [];
$attendanceMap = [];
$stats = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'total' => 0,
];

function hr_attendance_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function hr_attendance_status_badge($status) {
    $classes = [
        'present' => 'bg-success',
        'absent' => 'bg-danger',
        'leave' => 'bg-warning text-dark',
    ];
    $status = strtolower((string)$status);
    return '<span class="badge ' . ($classes[$status] ?? 'bg-secondary') . '">' . ucfirst($status ?: 'pending') . '</span>';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();

        if (!$hasStaffTable || !$hasAttendanceTable) {
            throw new Exception('Staff attendance table is missing. Please run the staff attendance migration first.');
        }

        $attendanceDate = $_POST['attendance_date'] ?? date('Y-m-d');
        $dateObj = DateTime::createFromFormat('Y-m-d', (string)$attendanceDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $attendanceDate) {
            throw new Exception('Please select a valid attendance date.');
        }

        $attendance = is_array($_POST['attendance'] ?? null) ? $_POST['attendance'] : [];
        if (!$attendance) {
            throw new Exception('No staff attendance was submitted.');
        }

        $insertColumns = ['staff_id', 'attendance_date', 'status'];
        $insertValues = ['?', '?', '?'];
        $updateParts = ['status = VALUES(status)'];

        if (columnExists($db, 'staff_attendance', 'marked_by')) {
            $insertColumns[] = 'marked_by';
            $insertValues[] = '?';
            $updateParts[] = 'marked_by = VALUES(marked_by)';
        }
        if (columnExists($db, 'staff_attendance', 'updated_at')) {
            $updateParts[] = 'updated_at = NOW()';
        }

        $sql = 'INSERT INTO staff_attendance (`' . implode('`, `', $insertColumns) . '`) VALUES (' . implode(', ', $insertValues) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
        $stmt = $db->prepare($sql);

        $db->beginTransaction();
        foreach ($attendance as $staffId => $status) {
            $staffId = (int)$staffId;
            $status = strtolower(trim((string)$status));
            if ($staffId <= 0 || !in_array($status, $allowedStatuses, true)) {
                continue;
            }

            $params = [$staffId, $attendanceDate, $status];
            if (in_array('marked_by', $insertColumns, true)) {
                $params[] = getUserId();
            }
            $stmt->execute($params);
        }
        $db->commit();

        setFlashMessage('success', 'Staff attendance saved successfully.');
        redirect('attendance.php?date=' . urlencode($attendanceDate));
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('attendance.php?date=' . urlencode($attendanceDate));
    }
}

if ($hasStaffTable) {
    $designationExpr = columnExists($db, 'staff', 'designation') ? 'designation' : (columnExists($db, 'staff', 'role') ? 'role' : "''");
    $emailExpr = columnExists($db, 'staff', 'email') ? 'email' : "''";
    $phoneExpr = columnExists($db, 'staff', 'phone') ? 'phone' : "''";
    $employeeExpr = columnExists($db, 'staff', 'employee_code') ? 'employee_code' : "CONCAT('STAFF-', id)";
    $where = '1=1';
    if (columnExists($db, 'staff', 'status')) {
        $where .= " AND LOWER(COALESCE(status, 'active')) = 'active'";
    } elseif (columnExists($db, 'staff', 'is_active')) {
        $where .= ' AND COALESCE(is_active, 1) = 1';
    }

    $staffRows = $db->query("
        SELECT id, full_name, $designationExpr AS designation, $emailExpr AS email, $phoneExpr AS phone, $employeeExpr AS employee_code
        FROM staff
        WHERE $where
        ORDER BY full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

if ($hasAttendanceTable && $staffRows) {
    $stmt = $db->prepare('SELECT staff_id, LOWER(status) AS status FROM staff_attendance WHERE attendance_date = ?');
    $stmt->execute([$attendanceDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attendanceMap[(int)$row['staff_id']] = strtolower((string)$row['status']);
    }
}

$stats['total'] = count($staffRows);
foreach ($staffRows as $row) {
    $status = $attendanceMap[(int)$row['id']] ?? '';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}

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
            <a href="index.php" style="color:#4ec2b5;text-decoration:none;">HR Management</a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Staff Attendance</li>
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
            <h2 class="page-title mb-1"><i class="fas fa-calendar-check me-2" style="color:var(--teal);"></i>Staff Attendance</h2>
            <div class="text-muted">Mark daily attendance for active staff members.</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="date" class="form-control" value="<?= hr_attendance_h($attendanceDate) ?>" required>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Load</button>
        </form>
    </div>
</div>

<?php displayFlashMessage(); ?>

<?php if (!$hasStaffTable || !$hasAttendanceTable): ?>
    <div class="alert alert-warning">
        <strong>Database migration required.</strong>
        <?= !$hasStaffTable ? 'The staff table is missing. ' : '' ?>
        <?= !$hasAttendanceTable ? 'The staff_attendance table is missing. ' : '' ?>
        Run <code>database/hr_staff_attendance.sql</code> before saving attendance.
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Staff', $stats['total'], 'fas fa-users', 'text-primary', 'bg-primary'],
        ['Present', $stats['present'], 'fas fa-user-check', 'text-success', 'bg-success'],
        ['Absent', $stats['absent'], 'fas fa-user-xmark', 'text-danger', 'bg-danger'],
        ['On Leave', $stats['leave'], 'fas fa-calendar-minus', 'text-warning', 'bg-warning'],
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
                        <p class="text-muted mb-0 small text-uppercase fw-bold"><?= hr_attendance_h($label) ?></p>
                        <h3 class="mb-0 fw-bold"><?= (int)$value ?></h3>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0 fw-bold">Attendance for <?= hr_attendance_h(date('d M Y', strtotime($attendanceDate))) ?></h5>
        <span class="badge bg-light text-dark border"><?= (int)$stats['total'] ?> staff members</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$staffRows): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                <h5 class="fw-bold">No active staff found</h5>
                <p class="mb-0">Add staff members first from the HR staff module.</p>
            </div>
        <?php else: ?>
            <form method="POST">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="attendance_date" value="<?= hr_attendance_h($attendanceDate) ?>">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Staff</th>
                                <th>Designation</th>
                                <th>Contact</th>
                                <th class="text-center">Current</th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Leave</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffRows as $staff): ?>
                                <?php $current = $attendanceMap[(int)$staff['id']] ?? 'present'; ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= hr_attendance_h($staff['full_name']) ?></div>
                                        <small class="text-muted"><?= hr_attendance_h($staff['employee_code']) ?></small>
                                    </td>
                                    <td><span class="badge bg-info text-dark"><?= hr_attendance_h($staff['designation'] ?: 'Staff') ?></span></td>
                                    <td>
                                        <div><?= hr_attendance_h($staff['phone']) ?: '-' ?></div>
                                        <?php if (!empty($staff['email'])): ?>
                                            <small class="text-muted"><?= hr_attendance_h($staff['email']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= hr_attendance_status_badge($attendanceMap[(int)$staff['id']] ?? '') ?></td>
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <td class="text-center">
                                            <input type="radio" class="form-check-input" name="attendance[<?= (int)$staff['id'] ?>]" value="<?= hr_attendance_h($status) ?>" <?= $current === $status ? 'checked' : '' ?> <?= !$hasAttendanceTable ? 'disabled' : '' ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-top d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4" <?= !$hasAttendanceTable ? 'disabled' : '' ?>>
                        <i class="fas fa-save me-2"></i>Save Attendance
                    </button>
                </div>
            </form>
        <?php endif; ?>
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

<?php include '../../includes/footer.php'; ?>
