<?php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
ensureAdmissionApplicationsTable($db);

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM admission_applications WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    setFlashMessage('error', 'Admission application not found.');
    redirect('index.php');
}

function app_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$page_title = "View Admission Application";
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="page-title mb-1">Admission Application</h2>
            <p class="text-muted mb-0"><?= app_h($app['application_id']) ?> · <?= app_h(ucfirst($app['status'])) ?></p>
        </div>
        <a href="index.php" class="btn btn-secondary rounded-pill px-4"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
</div>

<?php displayFlashMessage(); ?>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-4"><small class="text-muted">Student Name</small><div class="fw-bold"><?= app_h($app['full_name']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Father Name</small><div class="fw-bold"><?= app_h($app['father_name']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">CNIC / B-Form</small><div class="fw-bold"><?= app_h($app['cnic']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Program</small><div class="fw-bold"><?= app_h($app['program']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Campus</small><div class="fw-bold"><?= app_h($app['campus']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Session</small><div class="fw-bold"><?= app_h($app['session']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Phone</small><div class="fw-bold"><?= app_h($app['phone']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">WhatsApp</small><div class="fw-bold"><?= app_h($app['whatsapp']) ?></div></div>
            <div class="col-md-4"><small class="text-muted">Email</small><div class="fw-bold"><?= app_h($app['email']) ?></div></div>
            <div class="col-12"><small class="text-muted">Address</small><div class="fw-bold"><?= app_h($app['address']) ?></div></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 p-4"><h5 class="fw-bold mb-0">Academic Details</h5></div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6"><small class="text-muted">Previous Institution</small><div><?= app_h($app['prev_institution']) ?></div></div>
                    <div class="col-md-6"><small class="text-muted">Board</small><div><?= app_h($app['board_name']) ?></div></div>
                    <div class="col-md-6"><small class="text-muted">Roll Number</small><div><?= app_h($app['matric_roll']) ?></div></div>
                    <div class="col-md-6"><small class="text-muted">Passing Year</small><div><?= app_h($app['matric_year']) ?></div></div>
                    <div class="col-md-6"><small class="text-muted">Marks</small><div><?= app_h($app['matric_obtained']) ?> / <?= app_h($app['matric_total']) ?></div></div>
                    <div class="col-md-6"><small class="text-muted">Grade</small><div><?= app_h($app['matric_grade']) ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 p-4"><h5 class="fw-bold mb-0">Review Action</h5></div>
            <div class="card-body p-4">
                <?php if ($app['status'] === 'pending'): ?>
                    <form method="POST" action="index.php" class="mb-3">
                        <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                        <label class="form-label small fw-bold">Student Password</label>
                        <input type="password" name="student_password" class="form-control mb-3" placeholder="Leave blank to auto-generate">
                        <button name="application_action" value="approve" class="btn btn-success rounded-pill px-4" onclick="return confirm('Approve and create student login?')">
                            <i class="fas fa-check me-1"></i> Approve Application
                        </button>
                    </form>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                        <label class="form-label small fw-bold">Rejection Remarks</label>
                        <textarea name="remarks" class="form-control mb-3" rows="3" required></textarea>
                        <button name="application_action" value="reject" class="btn btn-outline-danger rounded-pill px-4" onclick="return confirm('Reject this application?')">
                            <i class="fas fa-times me-1"></i> Reject Application
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        This application was <?= app_h($app['status']) ?> on <?= app_h($app['reviewed_at'] ?? '') ?>.
                        <?php if (!empty($app['remarks'])): ?><br><strong>Remarks:</strong> <?= app_h($app['remarks']) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
