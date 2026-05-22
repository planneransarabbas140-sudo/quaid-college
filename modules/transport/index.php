<?php
/**
 * File: modules/transport/index.php
 * Transport Management System for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$db = (new Database())->getConnection();

// --- ENSURE TABLES EXIST ---
$db->exec("CREATE TABLE IF NOT EXISTS transport_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(20) UNIQUE,
    vehicle_type VARCHAR(50),
    model VARCHAR(100),
    capacity INT,
    driver_name VARCHAR(100),
    driver_phone VARCHAR(20),
    route VARCHAR(100),
    status ENUM('Active','Inactive','Maintenance') DEFAULT 'Active',
    campus VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS transport_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(200),
    start_point VARCHAR(100),
    end_point VARCHAR(100),
    stops TEXT,
    distance VARCHAR(50),
    departure_time TIME,
    return_time TIME,
    monthly_fee DECIMAL(10,2),
    vehicle_id INT,
    campus VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Ensure missing columns exist (migration)
try {
    $db->exec("ALTER TABLE transport_routes ADD COLUMN IF NOT EXISTS vehicle_id INT AFTER monthly_fee");
    $db->exec("ALTER TABLE transport_routes ADD COLUMN IF NOT EXISTS campus VARCHAR(50) AFTER vehicle_id");
} catch (Exception $e) {}


$db->exec("CREATE TABLE IF NOT EXISTS transport_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    route_id INT,
    pickup_point VARCHAR(100),
    monthly_fee DECIMAL(10,2),
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS transport_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100),
    cnic VARCHAR(20),
    phone VARCHAR(20),
    license_number VARCHAR(50),
    license_expiry DATE,
    address TEXT,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- HANDLE POST ACTIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            // Vehicles
            case 'add_vehicle':
            case 'edit_vehicle':
                $num = sanitizeInput($_POST['vehicle_number']);
                $type = $_POST['vehicle_type'];
                $model = sanitizeInput($_POST['model']);
                $capacity = (int)$_POST['capacity'];
                $driver = sanitizeInput($_POST['driver_name']);
                $phone = sanitizeInput($_POST['driver_phone']);
                $route = sanitizeInput($_POST['route']);
                $status = $_POST['status'];
                $campus = $_POST['campus'];

                if ($_POST['action'] === 'add_vehicle') {
                    $sql = "INSERT INTO transport_vehicles (vehicle_number, vehicle_type, model, capacity, driver_name, driver_phone, route, status, campus) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->prepare($sql)->execute([$num, $type, $model, $capacity, $driver, $phone, $route, $status, $campus]);
                    setFlashMessage('success', "Vehicle $num added successfully!");
                } else {
                    $id = $_POST['id'];
                    $sql = "UPDATE transport_vehicles SET vehicle_number=?, vehicle_type=?, model=?, capacity=?, driver_name=?, driver_phone=?, route=?, status=?, campus=? WHERE id=?";
                    $db->prepare($sql)->execute([$num, $type, $model, $capacity, $driver, $phone, $route, $status, $campus, $id]);
                    setFlashMessage('success', "Vehicle $num updated successfully!");
                }
                break;

            case 'delete_vehicle':
                $db->prepare("DELETE FROM transport_vehicles WHERE id = ?")->execute([$_POST['id']]);
                setFlashMessage('success', "Vehicle deleted successfully!");
                break;

            // Routes
            case 'add_route':
            case 'edit_route':
                $name = sanitizeInput($_POST['route_name']);
                $start = sanitizeInput($_POST['start_point']);
                $end = sanitizeInput($_POST['end_point']);
                $stops = sanitizeInput($_POST['stops']);
                $dist = sanitizeInput($_POST['distance']);
                $dep = $_POST['departure_time'];
                $ret = $_POST['return_time'];
                $fee = $_POST['monthly_fee'];
                $vid = $_POST['vehicle_id'];
                $campus = $_POST['campus'];

                if ($_POST['action'] === 'add_route') {
                    $sql = "INSERT INTO transport_routes (route_name, start_point, end_point, stops, distance, departure_time, return_time, monthly_fee, vehicle_id, campus) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->prepare($sql)->execute([$name, $start, $end, $stops, $dist, $dep, $ret, $fee, $vid, $campus]);
                    setFlashMessage('success', "Route $name added successfully!");
                } else {
                    $id = $_POST['id'];
                    $sql = "UPDATE transport_routes SET route_name=?, start_point=?, end_point=?, stops=?, distance=?, departure_time=?, return_time=?, monthly_fee=?, vehicle_id=?, campus=? WHERE id=?";
                    $db->prepare($sql)->execute([$name, $start, $end, $stops, $dist, $dep, $ret, $fee, $vid, $campus, $id]);
                    setFlashMessage('success', "Route $name updated successfully!");
                }
                break;

            case 'delete_route':
                $db->prepare("DELETE FROM transport_routes WHERE id = ?")->execute([$_POST['id']]);
                setFlashMessage('success', "Route deleted successfully!");
                break;

            // Students
            case 'assign_student':
                $sid = $_POST['student_id'];
                $rid = $_POST['route_id'];
                $point = sanitizeInput($_POST['pickup_point']);
                $fee = $_POST['monthly_fee'];
                
                $sql = "INSERT INTO transport_students (student_id, route_id, pickup_point, monthly_fee) VALUES (?, ?, ?, ?)";
                $db->prepare($sql)->execute([$sid, $rid, $point, $fee]);
                setFlashMessage('success', "Student assigned to transport!");
                break;

            case 'remove_student':
                $db->prepare("DELETE FROM transport_students WHERE id = ?")->execute([$_POST['id']]);
                setFlashMessage('success', "Student removed from transport.");
                break;

            // Drivers
            case 'add_driver':
            case 'edit_driver':
                $name = sanitizeInput($_POST['full_name']);
                $cnic = sanitizeInput($_POST['cnic']);
                $phone = sanitizeInput($_POST['phone']);
                $lic = sanitizeInput($_POST['license_number']);
                $exp = $_POST['license_expiry'];
                $addr = sanitizeInput($_POST['address']);
                $status = $_POST['status'];

                if ($_POST['action'] === 'add_driver') {
                    $sql = "INSERT INTO transport_drivers (full_name, cnic, phone, license_number, license_expiry, address, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $db->prepare($sql)->execute([$name, $cnic, $phone, $lic, $exp, $addr, $status]);
                    setFlashMessage('success', "Driver $name added successfully!");
                } else {
                    $id = $_POST['id'];
                    $sql = "UPDATE transport_drivers SET full_name=?, cnic=?, phone=?, license_number=?, license_expiry=?, address=?, status=? WHERE id=?";
                    $db->prepare($sql)->execute([$name, $cnic, $phone, $lic, $exp, $addr, $status, $id]);
                    setFlashMessage('success', "Driver $name updated successfully!");
                }
                break;

            case 'delete_driver':
                $db->prepare("DELETE FROM transport_drivers WHERE id = ?")->execute([$_POST['id']]);
                setFlashMessage('success', "Driver deleted successfully!");
                break;

            case 'add_transport_expense':
                $amount = (float)($_POST['amount'] ?? 0);
                if ($amount <= 0) {
                    throw new Exception('Please enter a valid expense amount.');
                }
                recordExpense($db, [
                    'module_name' => 'transport',
                    'reference_id' => null,
                    'campus' => sanitizeInput($_POST['campus'] ?? ''),
                    'category' => sanitizeInput($_POST['category'] ?? 'Transportation'),
                    'description' => sanitizeInput($_POST['description'] ?? ''),
                    'amount' => $amount,
                    'expense_type' => 'auto',
                    'status' => 'pending',
                    'created_by' => getUserId(),
                    'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
                    'payment_method' => sanitizeInput($_POST['payment_method'] ?? 'Cash')
                ]);
                setFlashMessage('success', 'Transport expense submitted for admin approval.');
                break;
        }
        redirect('index.php');
    } catch (Exception $e) {
        setFlashMessage('error', "Error: " . $e->getMessage());
        redirect('index.php');
    }
}

// --- FETCH DATA ---

// Stats
$total_vehicles = $db->query("SELECT COUNT(*) FROM transport_vehicles")->fetchColumn() ?? 0;
$total_routes = $db->query("SELECT COUNT(*) FROM transport_routes")->fetchColumn() ?? 0;
$total_students = $db->query("SELECT COUNT(*) FROM transport_students WHERE status='Active'")->fetchColumn() ?? 0;
$active_drivers = $db->query("SELECT COUNT(*) FROM transport_drivers WHERE status='Active'")->fetchColumn() ?? 0;

// Lists
$vehicles = $db->query("SELECT * FROM transport_vehicles ORDER BY campus ASC, vehicle_number ASC")->fetchAll();
$routes = $db->query("SELECT r.*, v.vehicle_number FROM transport_routes r LEFT JOIN transport_vehicles v ON r.vehicle_id = v.id ORDER BY r.campus ASC, r.route_name ASC")->fetchAll();
$trans_students = $db->query("SELECT ts.*, s.first_name, s.last_name,
                              COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), CONCAT('STD-', s.id)) as reg_no,
                              s.class, r.route_name 
                              FROM transport_students ts 
                              JOIN students s ON ts.student_id = s.id 
                              JOIN transport_routes r ON ts.route_id = r.id 
                              ORDER BY r.route_name ASC")->fetchAll();
$drivers = $db->query("SELECT * FROM transport_drivers ORDER BY full_name ASC")->fetchAll();
$transport_expenses = [];
if (tableExists($db, 'expenses')) {
    $transport_expenses = $db->query("
        SELECT *
        FROM expenses
        WHERE module_name = 'transport'
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll();
}

// Dropdowns
$all_students = $db->query("SELECT id,
                            COALESCE(NULLIF(roll_number, ''), NULLIF(registration_number, ''), CONCAT('STD-', id)) as student_code,
                            first_name, last_name, class
                            FROM students
                            ORDER BY first_name ASC")->fetchAll();
$all_routes = $db->query("SELECT id, route_name, monthly_fee, campus FROM transport_routes ORDER BY route_name ASC")->fetchAll();
$all_vehicles = $db->query("SELECT id, vehicle_number FROM transport_vehicles WHERE status='Active' ORDER BY vehicle_number ASC")->fetchAll();

$page_title = "Transport Management";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Breadcrumb & Back Button -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb" style="font-size:.82rem;font-family:'Space Mono',monospace;">
                    <li class="breadcrumb-item"><a href="../../dashboard.php" style="color:#4ec2b5;text-decoration:none;"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Transport</li>
                </ol>
            </nav>
            <a href="../../dashboard.php" class="btn btn-sm back-btn mb-3">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <h2 class="page-title mb-0"><i class="fas fa-bus-alt me-2" style="color: var(--teal);"></i>Transport Management System</h2>
                <button class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#transportExpenseModal">
                    <i class="fas fa-gas-pump me-2"></i>Add Fuel / Maintenance Expense
                </button>
            </div>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4 text-center">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-4">
                    <div class="stats-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3">
                        <i class="fas fa-bus fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $total_vehicles ?></h3>
                    <p class="text-muted small text-uppercase mb-0 fw-bold">Vehicles</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-4">
                    <div class="stats-icon bg-info bg-opacity-10 text-info mx-auto mb-3">
                        <i class="fas fa-route fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $total_routes ?></h3>
                    <p class="text-muted small text-uppercase mb-0 fw-bold">Routes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-4">
                    <div class="stats-icon bg-success bg-opacity-10 text-success mx-auto mb-3">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $total_students ?></h3>
                    <p class="text-muted small text-uppercase mb-0 fw-bold">Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-body p-4">
                    <div class="stats-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3">
                        <i class="fas fa-id-card fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?= $active_drivers ?></h3>
                    <p class="text-muted small text-uppercase mb-0 fw-bold">Active Drivers</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($transport_expenses): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-bold text-navy">Recent Transport Expense Requests</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-4">Category</th><th>Campus</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($transport_expenses as $expense): ?>
                            <tr>
                                <td class="ps-4"><?= htmlspecialchars($expense['category']) ?></td>
                                <td><?= htmlspecialchars($expense['campus'] ?? '-') ?></td>
                                <td class="fw-bold">PKR <?= number_format((float)$expense['amount'], 2) ?></td>
                                <td><span class="badge bg-warning text-dark text-capitalize"><?= htmlspecialchars($expense['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Tabs -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="transportTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold text-uppercase small" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles-pane" type="button" role="tab">Vehicles</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-uppercase small" id="routes-tab" data-bs-toggle="tab" data-bs-target="#routes-pane" type="button" role="tab">Routes</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-uppercase small" id="students-tab" data-bs-toggle="tab" data-bs-target="#students-pane" type="button" role="tab">Students</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-uppercase small" id="drivers-tab" data-bs-toggle="tab" data-bs-target="#drivers-pane" type="button" role="tab">Drivers</button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="transportTabsContent">
                
                <!-- Vehicles Tab -->
                <div class="tab-pane fade show active" id="vehicles-pane" role="tabpanel">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">Vehicle Fleet</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vehicleModal"><i class="fas fa-plus me-1"></i> Add Vehicle</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Vehicle No</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Driver</th>
                                    <th>Route</th>
                                    <th>Campus</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td><span class="badge bg-navy-light text-navy fw-bold"><?= $v['vehicle_number'] ?></span></td>
                                    <td><?= $v['vehicle_type'] ?></td>
                                    <td><?= $v['capacity'] ?> Seats</td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($v['driver_name']) ?></div>
                                        <small class="text-muted"><?= $v['driver_phone'] ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($v['route']) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= $v['campus'] ?></span></td>
                                    <td>
                                        <?php 
                                            $badge = 'bg-success';
                                            if($v['status'] == 'Inactive') $badge = 'bg-danger';
                                            if($v['status'] == 'Maintenance') $badge = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= $v['status'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick='editVehicle(<?= json_encode($v) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-outline-danger" onclick="deleteVehicle(<?= $v['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Routes Tab -->
                <div class="tab-pane fade" id="routes-pane" role="tabpanel">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">Transport Routes</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#routeModal"><i class="fas fa-plus me-1"></i> Add Route</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Route Name</th>
                                    <th>Start-End</th>
                                    <th>Departure-Return</th>
                                    <th>Fee</th>
                                    <th>Vehicle</th>
                                    <th>Campus</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routes as $r): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($r['route_name']) ?></td>
                                    <td>
                                        <small class="text-muted">From:</small> <?= htmlspecialchars($r['start_point']) ?><br>
                                        <small class="text-muted">To:</small> <?= htmlspecialchars($r['end_point']) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><i class="fas fa-clock me-1 text-teal"></i><?= date('h:i A', strtotime($r['departure_time'])) ?></span>
                                        <span class="badge bg-light text-dark"><i class="fas fa-undo me-1 text-danger"></i><?= date('h:i A', strtotime($r['return_time'])) ?></span>
                                    </td>
                                    <td class="fw-bold text-success">PKR <?= number_format($r['monthly_fee']) ?></td>
                                    <td><?= $r['vehicle_number'] ?: 'Not Assigned' ?></td>
                                    <td><span class="badge bg-light text-dark"><?= $r['campus'] ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick='editRoute(<?= json_encode($r) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-outline-danger" onclick="deleteRoute(<?= $r['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Students Tab -->
                <div class="tab-pane fade" id="students-pane" role="tabpanel">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">Student Transport List</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignStudentModal"><i class="fas fa-user-plus me-1"></i> Assign Transport</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Roll No</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Route</th>
                                    <th>Pick Up Point</th>
                                    <th>Monthly Fee</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trans_students as $ts): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark"><?= $ts['reg_no'] ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($ts['first_name'] . ' ' . $ts['last_name']) ?></td>
                                    <td><?= $ts['class'] ?></td>
                                    <td><?= htmlspecialchars($ts['route_name']) ?></td>
                                    <td><?= htmlspecialchars($ts['pickup_point']) ?></td>
                                    <td class="text-success fw-bold">PKR <?= number_format($ts['monthly_fee']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger" onclick="removeStudent(<?= $ts['id'] ?>)" title="Remove"><i class="fas fa-user-minus"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Drivers Tab -->
                <div class="tab-pane fade" id="drivers-pane" role="tabpanel">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">Driver Directory</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#driverModal"><i class="fas fa-plus me-1"></i> Add Driver</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Full Name</th>
                                    <th>CNIC</th>
                                    <th>Phone</th>
                                    <th>License Info</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($drivers as $d): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($d['full_name']) ?></td>
                                    <td><?= $d['cnic'] ?></td>
                                    <td><?= $d['phone'] ?></td>
                                    <td>
                                        <div class="small fw-bold text-navy"><?= $d['license_number'] ?></div>
                                        <small class="text-muted">Exp: <?= date('d M Y', strtotime($d['license_expiry'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $d['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= $d['status'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick='editDriver(<?= json_encode($d) ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-outline-danger" onclick="deleteDriver(<?= $d['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                        </div>
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

<!-- --- MODALS --- -->

<!-- Vehicle Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title" id="vehicleModalTitle"><i class="fas fa-bus me-2"></i>Add Vehicle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="vehicleForm">
                <input type="hidden" name="action" id="vehicleAction" value="add_vehicle">
                <input type="hidden" name="id" id="vehicle_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Vehicle Number</label>
                            <input type="text" name="vehicle_number" id="v_num" class="form-control" placeholder="RJN-123" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Vehicle Type</label>
                            <select name="vehicle_type" id="v_type" class="form-select" required>
                                <option value="Bus">Bus</option>
                                <option value="Van">Van</option>
                                <option value="Coaster">Coaster</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Campus</label>
                            <select name="campus" id="v_campus" class="form-select" required>
                                <option value="Rajanpur">Rajanpur</option>
                                <option value="Fazilpur">Fazilpur</option>
                                <option value="Kot Mithan">Kot Mithan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Vehicle Model</label>
                            <input type="text" name="model" id="v_model" class="form-control" placeholder="Toyota Coaster 2023">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Capacity (Seats)</label>
                            <input type="number" name="capacity" id="v_cap" class="form-control" value="30">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Status</label>
                            <select name="status" id="v_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Driver Name</label>
                            <input type="text" name="driver_name" id="v_driver" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Driver Phone</label>
                            <input type="text" name="driver_phone" id="v_phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Assigned Route (Info Only)</label>
                            <input type="text" name="route" id="v_route" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="vehicleSubmitBtn">Save Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Route Modal -->
<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title" id="routeModalTitle"><i class="fas fa-route me-2"></i>Add Route</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="routeForm">
                <input type="hidden" name="action" id="routeAction" value="add_route">
                <input type="hidden" name="id" id="route_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Route Name</label>
                            <input type="text" name="route_name" id="r_name" class="form-control" placeholder="Rajanpur to Jampur" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Distance</label>
                            <input type="text" name="distance" id="r_dist" class="form-control" placeholder="45 km">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Campus</label>
                            <select name="campus" id="r_campus" class="form-select" required>
                                <option value="Rajanpur">Rajanpur</option>
                                <option value="Fazilpur">Fazilpur</option>
                                <option value="Kot Mithan">Kot Mithan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Start Point</label>
                            <input type="text" name="start_point" id="r_start" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">End Point</label>
                            <input type="text" name="end_point" id="r_end" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Departure Time</label>
                            <input type="time" name="departure_time" id="r_dep" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Return Time</label>
                            <input type="time" name="return_time" id="r_ret" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Monthly Fee (PKR)</label>
                            <input type="number" name="monthly_fee" id="r_fee" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Vehicle Assigned</label>
                            <select name="vehicle_id" id="r_vehicle" class="form-select">
                                <option value="">None</option>
                                <?php foreach ($all_vehicles as $av): ?>
                                    <option value="<?= $av['id'] ?>"><?= $av['vehicle_number'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Major Stops (Comma separated)</label>
                            <textarea name="stops" id="r_stops" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="routeSubmitBtn">Save Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Student Modal -->
<div class="modal fade" id="assignStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Assign Transport to Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="assign_student">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Student</label>
                        <select name="student_id" class="form-select select2" required>
                            <option value="">Choose Student...</option>
                            <?php foreach ($all_students as $as): ?>
                                <option value="<?= $as['id'] ?>"><?= htmlspecialchars($as['student_code']) ?> - <?= htmlspecialchars($as['first_name'].' '.$as['last_name']) ?> (<?= htmlspecialchars($as['class'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Route</label>
                        <select name="route_id" id="assign_route_id" class="form-select" required onchange="updateFee(this)">
                            <option value="">Choose Route...</option>
                            <?php foreach ($all_routes as $ar): ?>
                                <option value="<?= $ar['id'] ?>" data-fee="<?= $ar['monthly_fee'] ?>"><?= htmlspecialchars($ar['route_name']) ?> (<?= $ar['campus'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Pick Up Point</label>
                        <input type="text" name="pickup_point" class="form-control" required placeholder="Main Chowk, Station, etc.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Monthly Fee (PKR)</label>
                        <input type="number" name="monthly_fee" id="assign_fee" class="form-control" readonly>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 text-white">Confirm Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Driver Modal -->
<div class="modal fade" id="driverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title" id="driverModalTitle"><i class="fas fa-id-card me-2"></i>Add Driver</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="driverForm">
                <input type="hidden" name="action" id="driverAction" value="add_driver">
                <input type="hidden" name="id" id="driver_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Full Name</label>
                            <input type="text" name="full_name" id="d_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Phone Number</label>
                            <input type="text" name="phone" id="d_phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">CNIC Number</label>
                            <input type="text" name="cnic" id="d_cnic" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">License Number</label>
                            <input type="text" name="license_number" id="d_lic" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">License Expiry</label>
                            <input type="date" name="license_expiry" id="d_exp" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Status</label>
                            <select name="status" id="d_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Home Address</label>
                            <textarea name="address" id="d_addr" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="driverSubmitBtn">Save Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Transport Expense Modal -->
<div class="modal fade" id="transportExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Fuel / Maintenance Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_transport_expense">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Expense Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Campus</label>
                        <select name="campus" class="form-select" required>
                            <?php renderCampusOptions($_SESSION['user_campus'] ?? ''); ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="Transportation">Fuel</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Vehicle Repair">Vehicle Repair</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Amount (PKR)</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold small">Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit for Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generic Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form action="" method="POST" id="deleteForm">
                <input type="hidden" name="action" id="deleteAction">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5>Are you sure?</h5>
                    <p class="small text-muted">This action will permanently delete this record.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
        --navy-light: #e7eaed;
    }
    .bg-navy { background-color: var(--navy) !important; }
    .bg-navy-light { background-color: var(--navy-light) !important; }
    .text-navy { color: var(--navy) !important; }
    .text-teal { color: var(--teal) !important; }
    
    .page-title { font-family: 'Playfair Display', serif; font-weight: 700; color: var(--navy); }
    .btn-primary { background-color: var(--teal); border-color: var(--teal); color: var(--navy); font-weight: 600; }
    .btn-primary:hover { background-color: #3da89c; border-color: #3da89c; color: white; }
    
    .stats-icon {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }
    
    .back-btn {
        background: rgba(78,194,181,.1);
        border: 1px solid rgba(78,194,181,.3);
        color: #4ec2b5;
        border-radius: 99px;
        padding: 6px 18px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all .3s ease;
    }
    .back-btn:hover { background: rgba(78,194,181,.2); color: #3da89c; }

    .nav-tabs .nav-link {
        color: #64748b;
        border: none;
        padding: 1rem 1.5rem;
        border-bottom: 2px solid transparent;
    }
    .nav-tabs .nav-link.active {
        color: var(--teal);
        background: transparent;
        border-bottom-color: var(--teal);
    }
    
    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        color: #64748b;
        border-top: none;
    }

    /* Modal Fixes */
    .modal { z-index: 99999 !important; }
    .modal-backdrop { z-index: 99998 !important; }
    .modal-dialog { z-index: 100000 !important; }
</style>

<script>
function updateFee(select) {
    const fee = select.options[select.selectedIndex].getAttribute('data-fee');
    document.getElementById('assign_fee').value = fee;
}

// Vehicle Edit
function editVehicle(v) {
    document.getElementById('vehicleModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Vehicle';
    document.getElementById('vehicleAction').value = 'edit_vehicle';
    document.getElementById('vehicle_id').value = v.id;
    document.getElementById('v_num').value = v.vehicle_number;
    document.getElementById('v_type').value = v.vehicle_type;
    document.getElementById('v_campus').value = v.campus;
    document.getElementById('v_model').value = v.model;
    document.getElementById('v_cap').value = v.capacity;
    document.getElementById('v_status').value = v.status;
    document.getElementById('v_driver').value = v.driver_name;
    document.getElementById('v_phone').value = v.driver_phone;
    document.getElementById('v_route').value = v.route;
    document.getElementById('vehicleSubmitBtn').innerText = 'Update Vehicle';
    new bootstrap.Modal(document.getElementById('vehicleModal')).show();
}

function deleteVehicle(id) {
    document.getElementById('deleteAction').value = 'delete_vehicle';
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Route Edit
function editRoute(r) {
    document.getElementById('routeModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Route';
    document.getElementById('routeAction').value = 'edit_route';
    document.getElementById('route_id').value = r.id;
    document.getElementById('r_name').value = r.route_name;
    document.getElementById('r_start').value = r.start_point;
    document.getElementById('r_end').value = r.end_point;
    document.getElementById('r_stops').value = r.stops;
    document.getElementById('r_dist').value = r.distance;
    document.getElementById('r_dep').value = r.departure_time;
    document.getElementById('r_ret').value = r.return_time;
    document.getElementById('r_fee').value = r.monthly_fee;
    document.getElementById('r_vehicle').value = r.vehicle_id;
    document.getElementById('r_campus').value = r.campus;
    document.getElementById('routeSubmitBtn').innerText = 'Update Route';
    new bootstrap.Modal(document.getElementById('routeModal')).show();
}

function deleteRoute(id) {
    document.getElementById('deleteAction').value = 'delete_route';
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Driver Edit
function editDriver(d) {
    document.getElementById('driverModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Driver';
    document.getElementById('driverAction').value = 'edit_driver';
    document.getElementById('driver_id').value = d.id;
    document.getElementById('d_name').value = d.full_name;
    document.getElementById('d_cnic').value = d.cnic;
    document.getElementById('d_phone').value = d.phone;
    document.getElementById('d_lic').value = d.license_number;
    document.getElementById('d_exp').value = d.license_expiry;
    document.getElementById('d_addr').value = d.address;
    document.getElementById('d_status').value = d.status;
    document.getElementById('driverSubmitBtn').innerText = 'Update Driver';
    new bootstrap.Modal(document.getElementById('driverModal')).show();
}

function deleteDriver(id) {
    document.getElementById('deleteAction').value = 'delete_driver';
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Student Removal
function removeStudent(id) {
    document.getElementById('deleteAction').value = 'remove_student';
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Reset modals on close
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('hidden.bs.modal', function () {
        const form = this.querySelector('form');
        if(form) form.reset();
        // Reset titles/buttons if they were changed for edit
        if(this.id === 'vehicleModal') {
            document.getElementById('vehicleModalTitle').innerHTML = '<i class="fas fa-bus me-2"></i>Add Vehicle';
            document.getElementById('vehicleAction').value = 'add_vehicle';
            document.getElementById('vehicleSubmitBtn').innerText = 'Save Vehicle';
        }
        if(this.id === 'routeModal') {
            document.getElementById('routeModalTitle').innerHTML = '<i class="fas fa-route me-2"></i>Add Route';
            document.getElementById('routeAction').value = 'add_route';
            document.getElementById('routeSubmitBtn').innerText = 'Save Route';
        }
        if(this.id === 'driverModal') {
            document.getElementById('driverModalTitle').innerHTML = '<i class="fas fa-id-card me-2"></i>Add Driver';
            document.getElementById('driverAction').value = 'add_driver';
            document.getElementById('driverSubmitBtn').innerText = 'Save Driver';
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
