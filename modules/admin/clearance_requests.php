<?php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php?role=admin");
    exit();
}
if (!in_array(getUserRole(), ['admin', 'owner'], true)) {
    header("Location: ../../dashboard.php");
    exit();
}

$db = (new Database())->getConnection();
$page_title = 'Degree Clearance Requests';

function acr_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function acr_status_class($status) {
    return [
        'pending' => 'warning text-dark',
        'under_review' => 'warning text-dark',
        'approved' => 'success',
        'rejected' => 'danger',
        'clear' => 'success',
        'not_clear' => 'danger',
        'waived' => 'secondary',
    ][$status] ?? 'secondary';
}

function acr_money($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (function_exists('requireCsrfToken')) {
            requireCsrfToken();
        }

        if (isset($_POST['update_checkpoint'])) {
            $cp_id = (int)$_POST['cp_id'];
            $cp_status = sanitizeInput($_POST['cp_status'] ?? '');
            $cp_remark = sanitizeInput($_POST['cp_remark'] ?? '');
            if (!in_array($cp_status, ['clear', 'not_clear', 'waived'], true)) {
                throw new Exception('Invalid checkpoint status.');
            }
            $db->prepare("
                UPDATE clearance_checkpoints
                SET status = :s, remarks = :r, checked_by = :by, checked_at = NOW()
                WHERE id = :id
            ")->execute([':s' => $cp_status, ':r' => $cp_remark, ':by' => getUserId(), ':id' => $cp_id]);
            setFlashMessage('success', 'Checkpoint updated.');
            redirect('clearance_requests.php');
        }

        if (isset($_POST['final_approve'])) {
            $clr_id = (int)$_POST['clearance_id'];
            $remarks = sanitizeInput($_POST['remarks'] ?? '');
            $pending = $db->prepare("SELECT COUNT(*) FROM clearance_checkpoints WHERE clearance_id = :id AND status NOT IN ('clear','waived')");
            $pending->execute([':id' => $clr_id]);
            if ((int)$pending->fetchColumn() > 0) {
                throw new Exception('Sare checkpoints clear nahi hain. Pehle sab approve karein.');
            }
            $db->prepare("
                UPDATE degree_clearance
                SET status = 'approved', approved_at = NOW(), approved_by = :by, remarks = :rem
                WHERE id = :id
            ")->execute([':by' => getUserId(), ':rem' => $remarks, ':id' => $clr_id]);
            setFlashMessage('success', 'Clearance approved. Certificate generate ho sakti hai.');
            redirect('clearance_requests.php');
        }

        if (isset($_POST['final_reject'])) {
            $clr_id = (int)$_POST['clearance_id'];
            $reason = sanitizeInput($_POST['reason'] ?? '');
            if ($reason === '') {
                throw new Exception('Rejection reason is required.');
            }
            $db->prepare("UPDATE degree_clearance SET status = 'rejected', rejection_reason = :reason WHERE id = :id")
                ->execute([':reason' => $reason, ':id' => $clr_id]);
            setFlashMessage('success', 'Application rejected.');
            redirect('clearance_requests.php');
        }
    }
} catch (Exception $e) {
    error_log('Admin Clearance Error: ' . $e->getMessage());
    setFlashMessage('error', $e->getMessage());
    redirect('clearance_requests.php');
}

$selectProgram = columnExists($db, 'students', 'program_id') && tableExists($db, 'programs') ? 'p.name' : "COALESCE(s.class, 'Program')";
$programJoin = columnExists($db, 'students', 'program_id') && tableExists($db, 'programs') ? 'LEFT JOIN programs p ON s.program_id = p.id' : '';
$campusSelect = columnExists($db, 'students', 'campus_id') ? 'c.name' : "COALESCE(s.campus, 'Rajanpur')";
$campusJoin = columnExists($db, 'students', 'campus_id') ? 'LEFT JOIN campuses c ON s.campus_id = c.id' : '';

$requests = $db->query("
    SELECT dc.*, CONCAT(s.first_name,' ',s.last_name) AS student_name,
           COALESCE(NULLIF(s.roll_number,''), NULLIF(s.student_id,''), CONCAT('STD-', s.id)) AS roll_number,
           $selectProgram AS program_name,
           $campusSelect AS campus_name,
           u.full_name AS approved_by_name
    FROM degree_clearance dc
    JOIN students s ON dc.student_id = s.id
    $programJoin
    $campusJoin
    LEFT JOIN users u ON dc.approved_by = u.id
    ORDER BY dc.applied_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$counts = $db->query("SELECT status, COUNT(*) AS cnt FROM degree_clearance GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum(array_map('intval', $counts ?: []));
$requestMeta = [];
foreach ($requests as $request) {
    $cid = (int)$request['id'];
    $cp = $db->prepare("SELECT * FROM clearance_checkpoints WHERE clearance_id = ? ORDER BY id ASC");
    $cp->execute([$cid]);
    $checkpoints = $cp->fetchAll(PDO::FETCH_ASSOC);
    $cleared = 0;
    foreach ($checkpoints as $checkpoint) {
        if (in_array($checkpoint['status'], ['clear', 'waived'], true)) {
            $cleared++;
        }
    }
    $requestMeta[$cid] = [
        'checkpoints' => $checkpoints,
        'cleared' => $cleared,
        'total' => count($checkpoints),
        'all_clear' => count($checkpoints) > 0 && $cleared === count($checkpoints),
    ];
}

include '../../includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700;800;900&family=Space+Mono:wght@400;700&display=swap');
    .clearance-admin { font-family:'DM Sans', system-ui, sans-serif; }
    .clearance-admin h1, .clearance-admin h2, .clearance-admin h3, .clearance-admin h4, .clearance-admin h5 { font-family:'Playfair Display', Georgia, serif; }
    .mono { font-family:'Space Mono', monospace; }
</style>

<div class="container-fluid clearance-admin">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title mb-1"><i class="fas fa-graduation-cap me-2" style="color:#4ec2b5;"></i>Degree Clearance Requests</h2>
            <p class="text-muted mb-0">Review student degree clearance checkpoints and final approval.</p>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><i class="fas fa-hourglass-half text-warning fs-3"></i><p class="text-muted mb-1 mt-3">Under Review</p><h3><?= (int)($counts['under_review'] ?? 0) ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><i class="fas fa-check-circle text-success fs-3"></i><p class="text-muted mb-1 mt-3">Approved</p><h3><?= (int)($counts['approved'] ?? 0) ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><i class="fas fa-times-circle text-danger fs-3"></i><p class="text-muted mb-1 mt-3">Rejected</p><h3><?= (int)($counts['rejected'] ?? 0) ?></h3></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4"><i class="fas fa-list fs-3" style="color:#0f2d48;"></i><p class="text-muted mb-1 mt-3">Total</p><h3><?= (int)$totalCount ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>App No</th>
                            <th>Student</th>
                            <th>Roll No</th>
                            <th>Program</th>
                            <th>Campus</th>
                            <th>Applied Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                                $cid = (int)$request['id'];
                                $meta = $requestMeta[$cid];
                                $pct = $meta['total'] > 0 ? round(($meta['cleared'] / $meta['total']) * 100) : 0;
                            ?>
                            <tr>
                                <td><span class="mono"><?= acr_h($request['application_number']) ?></span></td>
                                <td class="fw-bold"><?= acr_h($request['student_name']) ?></td>
                                <td><?= acr_h($request['roll_number']) ?></td>
                                <td><?= acr_h($request['program_name']) ?></td>
                                <td><?= acr_h($request['campus_name']) ?></td>
                                <td><?= date('d M Y', strtotime($request['applied_at'])) ?></td>
                                <td style="min-width:130px;">
                                    <div class="small mb-1"><?= (int)$meta['cleared'] ?>/<?= (int)$meta['total'] ?></div>
                                    <div class="progress rounded-pill" style="height:7px;"><div class="progress-bar" style="width:<?= (int)$pct ?>%;background:#4ec2b5;"></div></div>
                                </td>
                                <td><span class="badge bg-<?= acr_status_class($request['status']) ?>"><?= acr_h(ucwords(str_replace('_', ' ', $request['status']))) ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $cid ?>">Review</button>
                                    <?php if ($meta['all_clear'] && $request['status'] !== 'approved'): ?><button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $cid ?>">Approve</button><?php endif; ?>
                                    <?php if ($request['status'] !== 'rejected' && $request['status'] !== 'approved'): ?><button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $cid ?>">Reject</button><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?><tr><td colspan="9" class="text-center text-muted py-5">No clearance requests found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php foreach ($requests as $request): ?>
    <?php $cid = (int)$request['id']; $meta = $requestMeta[$cid]; ?>
    <div class="modal fade" id="reviewModal<?= $cid ?>" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header" style="background:#0f2d48;color:#fff;">
                    <h5 class="modal-title">Review: <?= acr_h($request['application_number']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <?php foreach ($meta['checkpoints'] as $cp): ?>
                            <div class="col-lg-6">
                                <div class="border rounded-4 p-3 h-100">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <strong><?= acr_h($cp['checkpoint_name']) ?></strong>
                                        <span class="badge bg-<?= acr_status_class($cp['status']) ?>"><?= acr_h(ucwords(str_replace('_', ' ', $cp['status']))) ?></span>
                                    </div>
                                    <p class="text-muted small mb-2"><?= acr_h($cp['remarks']) ?></p>
                                    <?php if ((float)$cp['amount_due'] > 0): ?><div class="text-danger fw-bold small mb-2">Amount Due: <?= acr_money($cp['amount_due']) ?></div><?php endif; ?>
                                    <?php if (in_array($cp['status'], ['pending', 'not_clear'], true)): ?>
                                        <form method="POST" class="row g-2">
                                            <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                                            <input type="hidden" name="cp_id" value="<?= (int)$cp['id'] ?>">
                                            <div class="col-md-4">
                                                <select name="cp_status" class="form-select form-select-sm">
                                                    <option value="clear">Clear</option>
                                                    <option value="not_clear">Not Clear</option>
                                                    <option value="waived">Waived</option>
                                                </select>
                                            </div>
                                            <div class="col-md-5"><input name="cp_remark" class="form-control form-control-sm" placeholder="Remarks"></div>
                                            <div class="col-md-3"><button name="update_checkpoint" class="btn btn-sm btn-primary w-100">Update</button></div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="approveModal<?= $cid ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form method="POST">
                    <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                    <input type="hidden" name="clearance_id" value="<?= $cid ?>">
                    <div class="modal-header bg-success text-white"><h5 class="modal-title">Approve Clearance</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4">
                        <p class="mb-3">Approve clearance for <strong><?= acr_h($request['student_name']) ?></strong>?</p>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Remarks (optional)"></textarea>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button name="final_approve" class="btn btn-success">Confirm Approve</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal<?= $cid ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form method="POST">
                    <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                    <input type="hidden" name="clearance_id" value="<?= $cid ?>">
                    <div class="modal-header bg-danger text-white"><h5 class="modal-title">Reject Application</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4">
                        <label class="form-label fw-bold">Rejection reason</label>
                        <textarea name="reason" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button name="final_reject" class="btn btn-danger">Confirm Reject</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include '../../includes/footer.php'; ?>
