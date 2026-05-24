<?php
// File: modules/fee_management/structure.php - Manage Fee Types
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$fee_types_missing   = false;
$schema_incomplete   = false;  // true when description/is_active columns are absent
$has_description_col = false;
$has_is_active_col   = false;

// ── Ensure fee_types table exists before any DML ─────────────────────────
try {
    $db->query("SELECT 1 FROM fee_types LIMIT 1");
} catch (PDOException $e) {
    $fee_types_missing = true;
}

// ── Detect optional columns via information_schema ────────────────────────
if (!$fee_types_missing) {
    $colCheck = $db->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fee_types'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $has_description_col = in_array('description', $colCheck);
    $has_is_active_col   = in_array('is_active',   $colCheck);
    $schema_incomplete   = !$has_description_col || !$has_is_active_col;
}

// Handle form submission (only if table exists)
if (!$fee_types_missing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (isset($_POST['add_fee'])) {
        $fee_name    = sanitizeInput($_POST['fee_name']);
        $amount      = floatval($_POST['amount']);

        try {
            if ($has_description_col) {
                $description = sanitizeInput($_POST['description'] ?? '');
                $stmt = $db->prepare("INSERT INTO fee_types (fee_name, amount, description) VALUES (?, ?, ?)");
                $stmt->execute([$fee_name, $amount, $description]);
            } else {
                $stmt = $db->prepare("INSERT INTO fee_types (fee_name, amount) VALUES (?, ?)");
                $stmt->execute([$fee_name, $amount]);
            }
            $success = 'Fee type added successfully!';
        } catch (Exception $e) {
            error_log('Fee type add failed: ' . $e->getMessage());
            $error = 'Fee type could not be added. Please try again.';
        }
    } elseif (isset($_POST['update_fee'])) {
        $id       = (int)$_POST['fee_id'];
        $fee_name = sanitizeInput($_POST['fee_name']);
        $amount   = floatval($_POST['amount']);

        try {
            if ($has_description_col) {
                $description = sanitizeInput($_POST['description'] ?? '');
                $stmt = $db->prepare("UPDATE fee_types SET fee_name = ?, amount = ?, description = ? WHERE id = ?");
                $stmt->execute([$fee_name, $amount, $description, $id]);
            } else {
                $stmt = $db->prepare("UPDATE fee_types SET fee_name = ?, amount = ? WHERE id = ?");
                $stmt->execute([$fee_name, $amount, $id]);
            }
            $success = 'Fee type updated successfully!';
        } catch (Exception $e) {
            error_log('Fee type update failed: ' . $e->getMessage());
            $error = 'Fee type could not be updated. Please try again.';
        }
    } elseif (isset($_POST['delete_fee'])) {
        try {
            $stmt = $db->prepare("DELETE FROM fee_types WHERE id = ?");
            $stmt->execute([(int)($_POST['fee_id'] ?? 0)]);
            setFlashMessage('success', 'Fee type deleted successfully!');
        } catch (Exception $e) {
            error_log('Fee type delete failed: ' . $e->getMessage());
            setFlashMessage('error', 'Fee type could not be deleted. Please try again.');
        }
        redirect('structure.php');
    }
}

// Get all fee types — only request columns that actually exist
$fee_types = [];
if (!$fee_types_missing) {
    try {
        $selectCols = 'id, fee_name, amount';
        if ($has_description_col) $selectCols .= ', description';
        if ($has_is_active_col)   $selectCols .= ', is_active';
        $selectCols .= ', created_at';
        $fee_types = $db->query("SELECT {$selectCols} FROM fee_types ORDER BY fee_name ASC")->fetchAll();
    } catch (PDOException $e) {
        $error = 'Could not load fee types: ' . $e->getMessage();
    }
}

$page_title = "Fee Type Management";
include '../../includes/header.php';
?>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
    .card-header { background-color: var(--navy); color: white; }
    .btn-teal { background-color: var(--teal); color: white; border: none; }
    .btn-teal:hover { background-color: #3da89b; color: white; }
    .table thead { background-color: #f8f9fc; color: var(--navy); }
</style>

<?php include 'fee_tabs.php'; ?>

<div class="container-fluid py-4">
    <div class="card shadow mb-4 border-0">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>Fee Type Structure</h6>
            <button class="btn btn-teal btn-sm" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                <i class="fas fa-plus me-1"></i> Add New Fee Type
            </button>
        </div>
        <div class="card-body">
            <?php displayFlashMessage(); ?>

            <?php if ($fee_types_missing): ?>
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3" role="alert" style="border-left:4px solid #f59e0b !important;">
                    <i class="fas fa-exclamation-triangle fa-lg text-warning"></i>
                    <div>
                        <strong>Fee Types table not found!</strong>
                        The <code>fee_types</code> table does not exist in the database yet.<br>
                        <a href="../../setup_fee_types.php" class="btn btn-sm btn-warning mt-2">
                            <i class="fas fa-database me-1"></i> Run Setup to Create &amp; Seed Table
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($schema_incomplete): ?>
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3" role="alert" style="border-left:4px solid #3b82f6 !important;">
                    <i class="fas fa-database fa-lg text-info"></i>
                    <div>
                        <strong>Schema update required.</strong>
                        The <code>fee_types</code> table is missing some columns
                        (<?php echo !$has_description_col ? '<code>description</code> ' : ''; ?><?php echo !$has_is_active_col ? '<code>is_active</code>' : ''; ?>).
                        Add &amp; Edit will still work for existing columns, but run the migration to unlock full features.<br>
                        <a href="../../fix_fee_types.php" class="btn btn-sm btn-info text-white mt-2">
                            <i class="fas fa-wrench me-1"></i> Run Schema Fix Now (1-click)
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fee Name</th>
                            <th>Standard Amount (PKR)</th>
                            <?php if ($has_description_col): ?><th>Description</th><?php endif; ?>
                            <?php if ($has_is_active_col):   ?><th>Status</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $colSpan = 3 + ($has_description_col ? 1 : 0) + ($has_is_active_col ? 1 : 0) + 1;
                        ?>
                        <?php if (empty($fee_types)): ?>
                            <tr>
                                <td colspan="<?php echo $colSpan; ?>" class="text-center py-5 text-muted">
                                    <?php if ($fee_types_missing): ?>
                                        <i class="fas fa-database fa-2x mb-2 d-block text-warning"></i>
                                        Table not set up yet.
                                    <?php else: ?>
                                        <i class="fas fa-list fa-2x mb-2 d-block text-muted"></i>
                                        No fee types found. Click &ldquo;Add New Fee Type&rdquo; to start.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fee_types as $fee): ?>
                            <tr>
                                <td><span class="badge" style="background:#0f2d48;"><?php echo (int)$fee['id']; ?></span></td>
                                <td class="fw-bold" style="color:var(--navy);"><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                <td><span class="text-success fw-semibold">PKR <?php echo number_format((float)$fee['amount'], 2); ?></span></td>
                                <?php if ($has_description_col): ?>
                                <td class="text-muted small"><?php echo htmlspecialchars($fee['description'] ?? '—'); ?></td>
                                <?php endif; ?>
                                <?php if ($has_is_active_col): ?>
                                <td>
                                    <?php if ($fee['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="editFee(
                                            <?php echo (int)$fee['id']; ?>,
                                            '<?php echo addslashes(htmlspecialchars($fee['fee_name'])); ?>',
                                            '<?php echo htmlspecialchars($fee['amount']); ?>',
                                            '<?php echo addslashes(htmlspecialchars($fee['description'] ?? '')); ?>'
                                        )">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this fee type? This will not affect existing collection records but will remove it from future selections.');">
                                        <?= csrfTokenInput() ?>
                                        <input type="hidden" name="fee_id" value="<?php echo (int)$fee['id']; ?>">
                                        <button type="submit" name="delete_fee" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
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

<!-- Add Fee Modal -->
<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title">Add New Fee Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrfTokenInput() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fee Name *</label>
                        <input type="text" name="fee_name" class="form-control" placeholder="e.g., Monthly Tuition Fee" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (PKR) *</label>
                        <div class="input-group">
                            <span class="input-group-text">PKR</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="description" class="form-control" placeholder="Short description of this fee">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fee" class="btn btn-teal">Add Fee Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Fee Modal -->
<div class="modal fade" id="editFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title">Edit Fee Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrfTokenInput() ?>
                <div class="modal-body p-4">
                    <input type="hidden" name="fee_id" id="edit_fee_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fee Name *</label>
                        <input type="text" name="fee_name" id="edit_fee_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (PKR) *</label>
                        <div class="input-group">
                            <span class="input-group-text">PKR</span>
                            <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="description" id="edit_description" class="form-control" placeholder="Short description of this fee">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_fee" class="btn btn-teal">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editFee(id, name, amount, description) {
    document.getElementById('edit_fee_id').value          = id;
    document.getElementById('edit_fee_name').value        = name;
    document.getElementById('edit_amount').value          = amount;
    document.getElementById('edit_description').value     = description || '';
    new bootstrap.Modal(document.getElementById('editFeeModal')).show();
}
</script>

<?php include '../../includes/footer.php'; ?>
