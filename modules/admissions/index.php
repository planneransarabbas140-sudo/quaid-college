<?php
// File: modules/admissions/index.php
require_once '../../config/db.php';
require_once '../../includes/whatsapp_helper.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . 'modules/auth/login.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
ensureAdmissionApplicationsTable($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_action'])) {
    try {
        requireCsrfToken();

        $applicationId = (int)($_POST['application_id'] ?? 0);
        $action = $_POST['application_action'];

        if ($action === 'approve') {
            $db->beginTransaction();
            $result = approveAdmissionApplication($db, $applicationId, getUserId(), trim($_POST['student_password'] ?? ''));
            $db->commit();
            $whatsAppSent = sendWhatsAppMessage($result['phone'], $result['student_name'], $result['email'], $result['password']);
            $whatsAppStatus = $whatsAppSent ? ' WhatsApp message sent.' : ' WhatsApp message could not be sent; check WhatsApp logs.';
            setFlashMessage('success', "Application approved. Student ID: {$result['student_code']}. Login username: {$result['username']}. Temporary password: {$result['password']}." . $whatsAppStatus);
        } elseif ($action === 'reject') {
            $remarks = trim($_POST['remarks'] ?? '');
            if ($remarks === '') {
                throw new Exception('Please enter rejection remarks.');
            }
            $stmt = $db->prepare("UPDATE admission_applications SET status = 'rejected', remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
            $stmt->execute([$remarks, getUserId(), $applicationId]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('Admission application not found or already reviewed.');
            }
            setFlashMessage('success', 'Application rejected and remarks saved.');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', "Error: " . $e->getMessage());
    }
    header("Location: index.php");
    exit();
}
// ─── FETCH DATA ───────────────────────────────────────────────
// Tab 1: Recent Admissions (Online Applications)
$online_apps = $db->query("SELECT * FROM admission_applications ORDER BY created_at DESC LIMIT 50")->fetchAll();
$pendingAdmissions = $db->query("SELECT COUNT(*) FROM admission_applications WHERE status = 'pending'")->fetchColumn();

// Tab 2: Enrolled Students
$campusJoin = tableExists($db, 'campuses') && columnExists($db, 'students', 'campus_id')
    ? "LEFT JOIN campuses c ON c.id = s.campus_id"
    : "";
$campusSelect = tableExists($db, 'campuses') && columnExists($db, 'students', 'campus_id')
    ? "COALESCE(c.name, 'Main Campus') AS display_campus"
    : (columnExists($db, 'students', 'campus') ? "COALESCE(s.campus, 'Main Campus') AS display_campus" : "'Main Campus' AS display_campus");
$enrolled = $db->query("SELECT s.*, {$campusSelect} FROM students s {$campusJoin} ORDER BY s.created_at DESC LIMIT 50")->fetchAll();

$page_title = "Admissions Management";
include '../../includes/header.php';
?>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
    .nav-tabs .nav-link {
        color: var(--navy);
        font-weight: 600;
        border: none;
        padding: 1rem 1.5rem;
        transition: all 0.3s ease;
    }
    .nav-tabs .nav-link.active {
        color: var(--teal);
        border-bottom: 3px solid var(--teal);
        background: transparent;
    }
    .btn-approve {
        background-color: var(--teal);
        color: white;
        border: none;
    }
    .btn-approve:hover {
        background-color: #3ba398;
        color: white;
    }
    .badge-pending { background-color: #f0b429; color: #fff; }
    .badge-approved { background-color: var(--teal); color: #fff; }
    .badge-rejected { background-color: #dc3545; color: #fff; }
    .page-header {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="fw-bold text-navy mb-1">Admissions Management</h2>
        <p class="text-muted mb-0">Manage online applications and manual enrollments</p>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-warning text-dark rounded-pill px-3 py-2">Pending Admissions: <?= (int)$pendingAdmissions ?></span>
        <a href="create.php" class="btn btn-primary rounded-pill px-4 shadow-sm" style="background: var(--navy); border: none;">
            <i class="fas fa-plus me-2"></i> New Admission
        </a>
    </div>
</div>

<?php displayFlashMessage(); ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white p-0">
        <ul class="nav nav-tabs" id="admissionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="online-tab" data-bs-toggle="tab" data-bs-target="#online-content" type="button" role="tab">
                    <i class="fas fa-globe me-2"></i> Recent Admissions (Online)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="enrolled-tab" data-bs-toggle="tab" data-bs-target="#enrolled-content" type="button" role="tab">
                    <i class="fas fa-user-check me-2"></i> Enrolled Students
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="admissionTabsContent">
            
            <!-- TAB 1: ONLINE APPLICATIONS -->
            <div class="tab-pane fade show active p-4" id="online-content" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Campus</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($online_apps as $app): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($app['full_name']) ?></td>
                                <td><?= htmlspecialchars($app['program']) ?></td>
                                <td><?= htmlspecialchars($app['campus']) ?></td>
                                <td><?= htmlspecialchars($app['phone']) ?></td>
                                <td>
                                    <?php
                                        $status = strtolower($app['status']);
                                        $badgeClass = $status === 'pending' ? 'badge-pending' : ($status === 'approved' ? 'badge-approved' : 'badge-rejected');
                                    ?>
                                    <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2"><?= htmlspecialchars(ucfirst($status)) ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" class="d-inline-flex gap-1 align-items-center mb-1">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                                            <input type="password" name="student_password" class="form-control form-control-sm" placeholder="Password optional" style="width:150px;">
                                            <button name="application_action" value="approve" type="submit" class="btn btn-sm btn-approve rounded-pill px-3" onclick="return confirm('Approve this application and create student login?')">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline-flex gap-1 align-items-center">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                                            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Reject remarks" style="width:150px;" required>
                                            <button name="application_action" value="reject" type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Reject this admission application?')">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="view_application.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-light rounded-pill px-3 ms-1">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($online_apps)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No online applications found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: ENROLLED STUDENTS -->
            <div class="tab-pane fade p-4" id="enrolled-content" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Campus</th>
                                <th>Guardian Phone</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolled as $st): ?>
                            <tr>
                                <td><span class="badge bg-navy text-white rounded-pill px-3" style="background:var(--navy)"><?= htmlspecialchars(getStudentDisplayId($st)) ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></td>
                                <td><?= htmlspecialchars($st['class']) ?></td>
                                <td><?= htmlspecialchars($st['display_campus']) ?></td>
                                <td><?= htmlspecialchars($st['guardian_phone']) ?></td>
                                <td class="text-end">
                                    <a href="../student_profile/view.php?id=<?= $st['id'] ?>" class="btn btn-sm btn-light rounded-pill px-3">
                                        <i class="fas fa-eye me-1"></i> View Profile
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($enrolled)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No enrolled students found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
