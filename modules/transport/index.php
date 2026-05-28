<?php
// File: modules/transport/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$db = (new Database())->getConnection();

function transport_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function transport_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

function transport_valid_month($month) {
    $month = (string)$month;
    $parsed = DateTime::createFromFormat('Y-m', $month);
    return $parsed && $parsed->format('Y-m') === $month;
}

function transport_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS transport_routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        route_name VARCHAR(150) NOT NULL,
        start_point VARCHAR(150) DEFAULT NULL,
        end_point VARCHAR(150) DEFAULT NULL,
        monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        vehicle_id INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transport_routes_name (route_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        'start_point' => "ALTER TABLE transport_routes ADD COLUMN start_point VARCHAR(150) DEFAULT NULL",
        'end_point' => "ALTER TABLE transport_routes ADD COLUMN end_point VARCHAR(150) DEFAULT NULL",
        'monthly_fee' => "ALTER TABLE transport_routes ADD COLUMN monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0",
        'vehicle_id' => "ALTER TABLE transport_routes ADD COLUMN vehicle_id INT DEFAULT NULL",
    ] as $column => $sql) {
        if (!columnExists($db, 'transport_routes', $column)) {
            $db->exec($sql);
        }
    }
    foreach ([
        'vehicle_number' => "ALTER TABLE transport_routes MODIFY vehicle_number VARCHAR(50) DEFAULT NULL",
        'driver_name' => "ALTER TABLE transport_routes MODIFY driver_name VARCHAR(150) DEFAULT NULL",
    ] as $column => $sql) {
        if (columnExists($db, 'transport_routes', $column)) {
            try { $db->exec($sql); } catch (Exception $e) {}
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS transport_vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_no VARCHAR(50) NOT NULL UNIQUE,
        driver_name VARCHAR(150) NOT NULL,
        capacity INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transport_vehicles_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        'vehicle_no' => "ALTER TABLE transport_vehicles ADD COLUMN vehicle_no VARCHAR(50) DEFAULT NULL",
        'driver_name' => "ALTER TABLE transport_vehicles ADD COLUMN driver_name VARCHAR(150) DEFAULT NULL",
        'capacity' => "ALTER TABLE transport_vehicles ADD COLUMN capacity INT NOT NULL DEFAULT 0",
        'status' => "ALTER TABLE transport_vehicles ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
    ] as $column => $sql) {
        if (!columnExists($db, 'transport_vehicles', $column)) {
            $db->exec($sql);
        }
    }
    if (columnExists($db, 'transport_vehicles', 'vehicle_number')) {
        $db->exec("UPDATE transport_vehicles SET vehicle_no = vehicle_number WHERE (vehicle_no IS NULL OR vehicle_no = '') AND vehicle_number IS NOT NULL");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS transport_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        route_id INT NOT NULL,
        monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        fee_month VARCHAR(7) NOT NULL,
        fee_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_transport_student_month (student_id, fee_month),
        INDEX idx_transport_assignments_route (route_id),
        INDEX idx_transport_assignments_fee (fee_month, fee_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

transport_ensure_schema($db);

$selectedMonth = $_GET['fee_month'] ?? $_POST['fee_month'] ?? date('Y-m');
if (!transport_valid_month($selectedMonth)) {
    $selectedMonth = date('Y-m');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        requireCsrfToken();

        if ($action === 'add_route' || $action === 'edit_route') {
            $routeName = sanitizeInput($_POST['route_name'] ?? '');
            $startPoint = sanitizeInput($_POST['start_point'] ?? '');
            $endPoint = sanitizeInput($_POST['end_point'] ?? '');
            $monthlyFee = max(0, (float)($_POST['monthly_fee'] ?? 0));
            $vehicleId = (int)($_POST['vehicle_id'] ?? 0) ?: null;

            if ($routeName === '' || $startPoint === '' || $endPoint === '') {
                throw new Exception('Route name, start point, and end point are required.');
            }

            if ($action === 'add_route') {
                $stmt = $db->prepare('INSERT INTO transport_routes (route_name, start_point, end_point, monthly_fee, vehicle_id) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$routeName, $startPoint, $endPoint, $monthlyFee, $vehicleId]);
                setFlashMessage('success', 'Route added successfully.');
            } else {
                $routeId = (int)($_POST['id'] ?? 0);
                if ($routeId <= 0) {
                    throw new Exception('Invalid route selected.');
                }
                $stmt = $db->prepare('UPDATE transport_routes SET route_name = ?, start_point = ?, end_point = ?, monthly_fee = ?, vehicle_id = ? WHERE id = ?');
                $stmt->execute([$routeName, $startPoint, $endPoint, $monthlyFee, $vehicleId, $routeId]);
                setFlashMessage('success', 'Route updated successfully.');
            }
            redirect('index.php#routes');
        }

        if ($action === 'delete_route') {
            $routeId = (int)($_POST['id'] ?? 0);
            if ($routeId <= 0) {
                throw new Exception('Invalid route selected.');
            }
            $db->beginTransaction();
            $db->prepare('DELETE FROM transport_assignments WHERE route_id = ?')->execute([$routeId]);
            $db->prepare('DELETE FROM transport_routes WHERE id = ?')->execute([$routeId]);
            $db->commit();
            setFlashMessage('success', 'Route deleted successfully.');
            redirect('index.php#routes');
        }

        if ($action === 'add_vehicle' || $action === 'edit_vehicle') {
            $vehicleNo = strtoupper(sanitizeInput($_POST['vehicle_no'] ?? ''));
            $driverName = sanitizeInput($_POST['driver_name'] ?? '');
            $capacity = max(0, (int)($_POST['capacity'] ?? 0));
            $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive', 'maintenance'], true) ? $_POST['status'] : 'active';

            if ($vehicleNo === '' || $driverName === '' || $capacity <= 0) {
                throw new Exception('Vehicle number, driver name, and capacity are required.');
            }

            if ($action === 'add_vehicle') {
                $stmt = $db->prepare('INSERT INTO transport_vehicles (vehicle_no, driver_name, capacity, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$vehicleNo, $driverName, $capacity, $status]);
                setFlashMessage('success', 'Vehicle added successfully.');
            } else {
                $vehicleId = (int)($_POST['id'] ?? 0);
                if ($vehicleId <= 0) {
                    throw new Exception('Invalid vehicle selected.');
                }
                $stmt = $db->prepare('UPDATE transport_vehicles SET vehicle_no = ?, driver_name = ?, capacity = ?, status = ? WHERE id = ?');
                $stmt->execute([$vehicleNo, $driverName, $capacity, $status, $vehicleId]);
                setFlashMessage('success', 'Vehicle updated successfully.');
            }
            redirect('index.php#vehicles');
        }

        if ($action === 'delete_vehicle') {
            $vehicleId = (int)($_POST['id'] ?? 0);
            if ($vehicleId <= 0) {
                throw new Exception('Invalid vehicle selected.');
            }
            $db->beginTransaction();
            $db->prepare('UPDATE transport_routes SET vehicle_id = NULL WHERE vehicle_id = ?')->execute([$vehicleId]);
            $db->prepare('DELETE FROM transport_vehicles WHERE id = ?')->execute([$vehicleId]);
            $db->commit();
            setFlashMessage('success', 'Vehicle deleted successfully.');
            redirect('index.php#vehicles');
        }

        if ($action === 'assign_student') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $routeId = (int)($_POST['route_id'] ?? 0);
            $feeMonth = $_POST['fee_month'] ?? date('Y-m');
            $monthlyFee = max(0, (float)($_POST['monthly_fee'] ?? 0));
            $feeStatus = in_array($_POST['fee_status'] ?? 'unpaid', ['paid', 'unpaid'], true) ? $_POST['fee_status'] : 'unpaid';

            if ($studentId <= 0 || $routeId <= 0 || !transport_valid_month($feeMonth)) {
                throw new Exception('Please select a student, route, and valid fee month.');
            }

            $stmt = $db->prepare('
                INSERT INTO transport_assignments (student_id, route_id, monthly_fee, fee_month, fee_status, status)
                VALUES (?, ?, ?, ?, ?, "active")
                ON DUPLICATE KEY UPDATE route_id = VALUES(route_id), monthly_fee = VALUES(monthly_fee), fee_status = VALUES(fee_status), status = "active"
            ');
            $stmt->execute([$studentId, $routeId, $monthlyFee, $feeMonth, $feeStatus]);
            setFlashMessage('success', 'Student transport assignment saved.');
            redirect('index.php?fee_month=' . urlencode($feeMonth) . '#assignments');
        }

        if ($action === 'delete_assignment') {
            $assignmentId = (int)($_POST['id'] ?? 0);
            if ($assignmentId <= 0) {
                throw new Exception('Invalid assignment selected.');
            }
            $db->prepare('DELETE FROM transport_assignments WHERE id = ?')->execute([$assignmentId]);
            setFlashMessage('success', 'Transport assignment removed.');
            redirect('index.php?fee_month=' . urlencode($selectedMonth) . '#assignments');
        }

        if ($action === 'mark_fee') {
            $assignmentId = (int)($_POST['id'] ?? 0);
            $feeStatus = in_array($_POST['fee_status'] ?? 'unpaid', ['paid', 'unpaid'], true) ? $_POST['fee_status'] : 'unpaid';
            if ($assignmentId <= 0) {
                throw new Exception('Invalid assignment selected.');
            }
            $db->prepare('UPDATE transport_assignments SET fee_status = ? WHERE id = ?')->execute([$feeStatus, $assignmentId]);
            setFlashMessage('success', 'Transport fee status updated.');
            redirect('index.php?fee_month=' . urlencode($selectedMonth) . '#assignments');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('index.php?fee_month=' . urlencode($selectedMonth));
    }
}

$vehicles = $db->query("SELECT * FROM transport_vehicles ORDER BY status ASC, vehicle_no ASC")->fetchAll(PDO::FETCH_ASSOC);
$routes = $db->query("
    SELECT r.*, v.vehicle_no, v.driver_name, v.capacity
    FROM transport_routes r
    LEFT JOIN transport_vehicles v ON v.id = r.vehicle_id
    ORDER BY r.route_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$students = [];
if (tableExists($db, 'students')) {
    $students = $db->query("
        SELECT id,
               COALESCE(NULLIF(roll_number, ''), NULLIF(registration_number, ''), NULLIF(student_id, ''), CONCAT('STD-', id)) AS student_code,
               first_name, last_name, class, section
        FROM students
        ORDER BY first_name ASC, last_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $db->prepare("
    SELECT ta.*, r.route_name, r.start_point, r.end_point, v.vehicle_no,
           CONCAT(s.first_name, ' ', s.last_name) AS student_name,
           COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), NULLIF(s.student_id, ''), CONCAT('STD-', s.id)) AS student_code,
           s.class, s.section
    FROM transport_assignments ta
    JOIN transport_routes r ON r.id = ta.route_id
    LEFT JOIN transport_vehicles v ON v.id = r.vehicle_id
    JOIN students s ON s.id = ta.student_id
    WHERE ta.fee_month = ?
    ORDER BY r.route_name ASC, s.first_name ASC
");
$stmt->execute([$selectedMonth]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$routeStudentMap = [];
$paidTotal = 0;
$unpaidTotal = 0;
foreach ($assignments as $assignment) {
    $routeStudentMap[$assignment['route_name']][] = $assignment;
    if ($assignment['fee_status'] === 'paid') {
        $paidTotal += (float)$assignment['monthly_fee'];
    } else {
        $unpaidTotal += (float)$assignment['monthly_fee'];
    }
}

$page_title = 'Transport Management';
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-bus me-2" style="color:var(--teal);"></i>Transport Management</h2>
            <div class="text-muted">Manage routes, vehicles, student assignments, and monthly transport fees.</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input type="month" name="fee_month" class="form-control" value="<?= transport_h($selectedMonth) ?>" required>
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Load</button>
        </form>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Routes</div><h3 class="fw-bold mb-0"><?= count($routes) ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Vehicles</div><h3 class="fw-bold mb-0"><?= count($vehicles) ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Paid Fees</div><h4 class="fw-bold text-success mb-0"><?= transport_money($paidTotal) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Unpaid Fees</div><h4 class="fw-bold text-danger mb-0"><?= transport_money($unpaidTotal) ?></h4></div></div></div>
    </div>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#routes" type="button">Routes</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#vehicles" type="button">Vehicles</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignments" type="button">Assignments</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#routeStudents" type="button">Route-wise Students</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="routes">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Add Route</h5></div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="add_route">
                                <div class="col-12"><label class="form-label">Route Name</label><input name="route_name" class="form-control" required></div>
                                <div class="col-12"><label class="form-label">Start Point</label><input name="start_point" class="form-control" required></div>
                                <div class="col-12"><label class="form-label">End Point</label><input name="end_point" class="form-control" required></div>
                                <div class="col-12"><label class="form-label">Monthly Fee</label><input type="number" min="0" step="0.01" name="monthly_fee" class="form-control" value="0"></div>
                                <div class="col-12">
                                    <label class="form-label">Vehicle</label>
                                    <select name="vehicle_id" class="form-select">
                                        <option value="">No vehicle assigned</option>
                                        <?php foreach ($vehicles as $vehicle): ?><option value="<?= (int)$vehicle['id'] ?>"><?= transport_h($vehicle['vehicle_no'] . ' - ' . $vehicle['driver_name']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Save Route</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Routes</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle <?= $routes ? 'datatable' : '' ?>">
                                    <thead class="table-light"><tr><th>Route</th><th>Path</th><th>Vehicle</th><th class="text-end">Fee</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        <?php if (!$routes): ?><tr><td colspan="5" class="text-center text-muted py-4">No routes found.</td></tr><?php endif; ?>
                                        <?php foreach ($routes as $route): ?>
                                            <tr>
                                                <td class="fw-bold"><?= transport_h($route['route_name']) ?></td>
                                                <td><?= transport_h($route['start_point'] . ' to ' . $route['end_point']) ?></td>
                                                <td><?= transport_h($route['vehicle_no'] ?: 'Unassigned') ?></td>
                                                <td class="text-end"><?= transport_money($route['monthly_fee']) ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRoute<?= (int)$route['id'] ?>"><i class="fas fa-pen"></i></button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this route and its assignments?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete_route"><input type="hidden" name="id" value="<?= (int)$route['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
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

        <div class="tab-pane fade" id="vehicles">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Add Vehicle</h5></div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="add_vehicle">
                                <div class="col-12"><label class="form-label">Vehicle No</label><input name="vehicle_no" class="form-control" required></div>
                                <div class="col-12"><label class="form-label">Driver Name</label><input name="driver_name" class="form-control" required></div>
                                <div class="col-12"><label class="form-label">Capacity</label><input type="number" min="1" name="capacity" class="form-control" required></div>
                                <div class="col-12"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="maintenance">Maintenance</option><option value="inactive">Inactive</option></select></div>
                                <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Save Vehicle</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Vehicles</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle <?= $vehicles ? 'datatable' : '' ?>">
                                    <thead class="table-light"><tr><th>Vehicle No</th><th>Driver</th><th class="text-end">Capacity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        <?php if (!$vehicles): ?><tr><td colspan="5" class="text-center text-muted py-4">No vehicles found.</td></tr><?php endif; ?>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td class="fw-bold"><?= transport_h($vehicle['vehicle_no']) ?></td>
                                                <td><?= transport_h($vehicle['driver_name']) ?></td>
                                                <td class="text-end"><?= (int)$vehicle['capacity'] ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= transport_h(ucfirst($vehicle['status'])) ?></span></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editVehicle<?= (int)$vehicle['id'] ?>"><i class="fas fa-pen"></i></button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this vehicle?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete_vehicle"><input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
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

        <div class="tab-pane fade" id="assignments">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Assign Student to Transport</h5></div>
                <div class="card-body">
                    <form method="POST" class="row g-3 align-items-end">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="action" value="assign_student">
                        <div class="col-lg-4">
                            <label class="form-label">Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Select student...</option>
                                <?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= transport_h($student['student_code'] . ' - ' . trim($student['first_name'] . ' ' . $student['last_name'])) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Route</label>
                            <select name="route_id" class="form-select" required>
                                <option value="">Select route...</option>
                                <?php foreach ($routes as $route): ?><option value="<?= (int)$route['id'] ?>" data-fee="<?= transport_h($route['monthly_fee']) ?>"><?= transport_h($route['route_name'] . ' - ' . transport_money($route['monthly_fee'])) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><label class="form-label">Month</label><input type="month" name="fee_month" class="form-control" value="<?= transport_h($selectedMonth) ?>" required></div>
                        <div class="col-md-1"><label class="form-label">Fee</label><input type="number" min="0" step="0.01" name="monthly_fee" class="form-control" value="0"></div>
                        <div class="col-md-1"><label class="form-label">Status</label><select name="fee_status" class="form-select"><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select></div>
                        <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Save</button></div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Assignments - <?= transport_h(date('F Y', strtotime($selectedMonth . '-01'))) ?></h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle <?= $assignments ? 'datatable' : '' ?>">
                            <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Route</th><th>Vehicle</th><th class="text-end">Fee</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                                <?php if (!$assignments): ?><tr><td colspan="7" class="text-center text-muted py-4">No assignments found for this month.</td></tr><?php endif; ?>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><?= transport_h($assignment['student_code'] . ' - ' . $assignment['student_name']) ?></td>
                                        <td><?= transport_h($assignment['class'] . ($assignment['section'] ? ' - ' . $assignment['section'] : '')) ?></td>
                                        <td><?= transport_h($assignment['route_name']) ?></td>
                                        <td><?= transport_h($assignment['vehicle_no'] ?: 'Unassigned') ?></td>
                                        <td class="text-end"><?= transport_money($assignment['monthly_fee']) ?></td>
                                        <td><span class="badge <?= $assignment['fee_status'] === 'paid' ? 'bg-success' : 'bg-danger' ?>"><?= transport_h(ucfirst($assignment['fee_status'])) ?></span></td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline"><?= csrfTokenInput() ?><input type="hidden" name="action" value="mark_fee"><input type="hidden" name="id" value="<?= (int)$assignment['id'] ?>"><input type="hidden" name="fee_month" value="<?= transport_h($selectedMonth) ?>"><input type="hidden" name="fee_status" value="<?= $assignment['fee_status'] === 'paid' ? 'unpaid' : 'paid' ?>"><button class="btn btn-sm btn-outline-success"><?= $assignment['fee_status'] === 'paid' ? 'Unpay' : 'Paid' ?></button></form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this assignment?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete_assignment"><input type="hidden" name="id" value="<?= (int)$assignment['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="routeStudents">
            <?php if (!$routeStudentMap): ?>
                <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No route-wise student assignments found for this month.</div></div>
            <?php endif; ?>
            <?php foreach ($routeStudentMap as $routeName => $rows): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white d-flex justify-content-between"><h5 class="mb-0 fw-bold"><?= transport_h($routeName) ?></h5><span class="badge bg-light text-dark border"><?= count($rows) ?> students</span></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Vehicle</th><th class="text-end">Fee</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr><td><?= transport_h($row['student_code'] . ' - ' . $row['student_name']) ?></td><td><?= transport_h($row['class'] . ($row['section'] ? ' - ' . $row['section'] : '')) ?></td><td><?= transport_h($row['vehicle_no'] ?: 'Unassigned') ?></td><td class="text-end"><?= transport_money($row['monthly_fee']) ?></td><td><?= transport_h(ucfirst($row['fee_status'])) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php foreach ($routes as $route): ?>
<div class="modal fade" id="editRoute<?= (int)$route['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="edit_route"><input type="hidden" name="id" value="<?= (int)$route['id'] ?>"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit Route</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-12"><label class="form-label">Route Name</label><input name="route_name" class="form-control" value="<?= transport_h($route['route_name']) ?>" required></div><div class="col-12"><label class="form-label">Start Point</label><input name="start_point" class="form-control" value="<?= transport_h($route['start_point']) ?>" required></div><div class="col-12"><label class="form-label">End Point</label><input name="end_point" class="form-control" value="<?= transport_h($route['end_point']) ?>" required></div><div class="col-12"><label class="form-label">Monthly Fee</label><input type="number" min="0" step="0.01" name="monthly_fee" class="form-control" value="<?= transport_h($route['monthly_fee']) ?>"></div><div class="col-12"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select"><option value="">No vehicle assigned</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int)$vehicle['id'] ?>" <?= (int)$route['vehicle_id'] === (int)$vehicle['id'] ? 'selected' : '' ?>><?= transport_h($vehicle['vehicle_no'] . ' - ' . $vehicle['driver_name']) ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update</button></div></form></div></div>
</div>
<?php endforeach; ?>

<?php foreach ($vehicles as $vehicle): ?>
<div class="modal fade" id="editVehicle<?= (int)$vehicle['id'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="edit_vehicle"><input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit Vehicle</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-12"><label class="form-label">Vehicle No</label><input name="vehicle_no" class="form-control" value="<?= transport_h($vehicle['vehicle_no']) ?>" required></div><div class="col-12"><label class="form-label">Driver Name</label><input name="driver_name" class="form-control" value="<?= transport_h($vehicle['driver_name']) ?>" required></div><div class="col-12"><label class="form-label">Capacity</label><input type="number" min="1" name="capacity" class="form-control" value="<?= (int)$vehicle['capacity'] ?>" required></div><div class="col-12"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= $vehicle['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="maintenance" <?= $vehicle['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option><option value="inactive" <?= $vehicle['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update</button></div></form></div></div>
</div>
<?php endforeach; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    .btn-primary:hover { background:#3da89c; border-color:#3da89c; color:#fff; }
    .btn-outline-primary { color:var(--navy); border-color:var(--teal); }
    .btn-outline-primary:hover { background:var(--teal); border-color:var(--teal); color:var(--navy); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const routeSelect = document.querySelector('select[name="route_id"]');
    const feeInput = document.querySelector('input[name="monthly_fee"]');
    if (routeSelect && feeInput) {
        routeSelect.addEventListener('change', function () {
            const selected = routeSelect.options[routeSelect.selectedIndex];
            if (selected && selected.dataset.fee) {
                feeInput.value = selected.dataset.fee;
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
