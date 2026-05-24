<?php
// File: modules/hr/staff.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle add staff
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !verifyCsrfToken()) {
    $error = 'Security check failed. Please refresh the page and try again.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $full_name = sanitizeInput($_POST['full_name']);
    $designation = sanitizeInput($_POST['role']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $campus = sanitizeInput($_POST['campus'] ?? '');
    
    try {
        if ($full_name === '' || $designation === '' || $phone === '') {
            throw new Exception('Please fill all required staff fields.');
        }

        $db->beginTransaction();

        $emailForUser = $email !== '' ? $email : strtolower(preg_replace('/[^a-z0-9]+/i', '.', $full_name)) . '.' . time() . '@qgc.local';
        $baseUsername = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '.', $full_name), '.'));
        $baseUsername = $baseUsername !== '' ? $baseUsername : 'staff';
        $username = $baseUsername;
        $suffix = 1;
        while (true) {
            $check = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $check->execute([$username]);
            if (!$check->fetch()) {
                break;
            }
            $username = $baseUsername . $suffix++;
        }

        $existingUser = null;
        if ($email !== '') {
            $userStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $userStmt->execute([$email]);
            $existingUser = $userStmt->fetchColumn();
        }

        $createdUserCredentials = null;
        if ($existingUser) {
            $userId = (int)$existingUser;
        } else {
            $temporaryPassword = 'Qgc@' . bin2hex(random_bytes(6));
            $defaultPassword = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            $roleForUser = strtolower($designation) === 'teacher' ? 'teacher' : 'staff';
            $stmt = $db->prepare("INSERT INTO users (full_name, username, email, password, role, phone, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
            $stmt->execute([$full_name, $username, $emailForUser, $defaultPassword, $roleForUser, $phone]);
            $userId = (int)$db->lastInsertId();
            $createdUserCredentials = [
                'username' => $username,
                'password' => $temporaryPassword,
            ];
        }

        $year = date('Y');
        $count = (int)$db->query("SELECT COUNT(*) FROM staff")->fetchColumn() + 1;
        do {
            $employeeCode = 'EMP-' . $year . '-' . str_pad($count++, 4, '0', STR_PAD_LEFT);
            $checkCode = $db->prepare("SELECT id FROM staff WHERE employee_code = ? LIMIT 1");
            $checkCode->execute([$employeeCode]);
        } while ($checkCode->fetch());

        $campusId = $campus !== '' ? getOrCreateCampusId($db, $campus) : null;

        $stmt = $db->prepare("INSERT INTO staff (user_id, employee_code, full_name, designation, email, phone, campus_id, joining_date, status) VALUES (:user_id, :employee_code, :full_name, :designation, :email, :phone, :campus_id, CURDATE(), 'active')");
        $stmt->execute([
            ':user_id' => $userId,
            ':employee_code' => $employeeCode,
            ':full_name' => $full_name,
            ':designation' => $designation,
            ':email' => $email,
            ':phone' => $phone,
            ':campus_id' => $campusId
        ]);

        $db->commit();
        $success = "Staff member added successfully! Employee Code: " . $employeeCode;
        if ($createdUserCredentials) {
            $success .= " Portal username: " . $createdUserCredentials['username'] . ". Temporary password: " . $createdUserCredentials['password'];
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error adding staff: " . $e->getMessage();
    }
}

// Fetch all staff
$staff_list = $db->query("SELECT s.*, c.name AS campus_name FROM staff s LEFT JOIN campuses c ON c.id = s.campus_id ORDER BY s.full_name ASC")->fetchAll();

$page_title = "Staff Management";
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
            Staff Directory
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
        <h2 class="page-title mb-0">Staff Directory</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="fas fa-plus"></i> Add Staff</button>
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
                                <th>ID</th>
                                <th>Employee Code</th>
                                <th>Full Name</th>
                                <th>Designation</th>
                                <th>Campus</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_list as $staff): ?>
                            <tr>
                                <td><?php echo $staff['id']; ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($staff['employee_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($staff['designation'] ?? 'Staff'); ?></span></td>
                                <td><?php echo htmlspecialchars($staff['campus_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                <td><?php echo htmlspecialchars($staff['phone']); ?></td>
                                <td>
                                    <?php if (strtolower($staff['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></button>
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

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add New Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select class="form-select" name="role" required>
                            <option value="">-- Select --</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Principal">Principal</option>
                            <option value="Accountant">Accountant</option>
                            <option value="Clerk">Clerk</option>
                            <option value="Security">Security</option>
                            <option value="Janitor">Janitor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                        <small class="text-muted">If empty, a local portal email will be generated.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" class="form-control" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Campus</label>
                        <select class="form-select" name="campus">
                            <?php renderCampusOptions($_POST['campus'] ?? ''); ?>
                        </select>
                    </div>
                    <div class="alert alert-info small mb-0">
                        A portal user is created automatically. New users receive a one-time temporary password after save.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
