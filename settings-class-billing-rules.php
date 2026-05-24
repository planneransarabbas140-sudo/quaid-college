<?php
// File: settings-class-billing-rules.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/class_billing_functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$conn = $database->getConnection();

// Ensure table exists (migration-safe; avoids breaking first open)
try {
    $sqlPath = __DIR__ . '/database/class_billing_rules.sql';
    if (is_file($sqlPath)) {
        $conn->exec(file_get_contents($sqlPath));
    }
} catch (Exception $e) {
    error_log('Class Billing Rules DB Error: ' . $e->getMessage());
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        redirect('settings-class-billing-rules.php?msg=error');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $validation = validateBillingRuleInput($_POST);
        if (!$validation['valid']) {
            $_SESSION['class_billing_errors'] = $validation['errors'];
            redirect('settings-class-billing-rules.php?msg=error');
        }

        $clean = $validation['clean'];
        $editId = (int)($_POST['edit_id'] ?? 0);

        // Prevent duplicates per class: if exists, update; else insert
        $existing = getExistingRuleForClass($conn, $clean['class_id']);
        $ok = false;

        if ($editId > 0) {
            $ok = updateClassBillingRule($conn, $editId, $clean['class_id'], $clean['billing_frequency'], $clean['billing_type'], $clean['rule_note']);
        } elseif ($existing) {
            $ok = updateClassBillingRule($conn, (int)$existing['id'], $clean['class_id'], $clean['billing_frequency'], $clean['billing_type'], $clean['rule_note']);
        } else {
            $ok = insertClassBillingRule($conn, $clean['class_id'], $clean['billing_frequency'], $clean['billing_type'], $clean['rule_note']) !== false;
        }

        redirect('settings-class-billing-rules.php?msg=' . ($ok ? 'saved' : 'error'));
    }

    if ($action === 'delete') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $ok = $ruleId > 0 ? deleteClassBillingRule($conn, $ruleId) : false;
        redirect('settings-class-billing-rules.php?msg=' . ($ok ? 'deleted' : 'error'));
    }
}

// Fetch data for page
$classes = getClassListForBillingRules($conn);
$saved_rules = getSavedClassBillingRules($conn);
$edit_rule = null;

if (isset($_GET['edit_id'])) {
    $edit_rule = getBillingRuleById($conn, (int)$_GET['edit_id']);
}

$page_title = 'Class Billing Rules';
include __DIR__ . '/includes/header.php';

$msg = (string)($_GET['msg'] ?? '');
$errors = $_SESSION['class_billing_errors'] ?? [];
unset($_SESSION['class_billing_errors']);

$adminName = htmlspecialchars((string)($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8');
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Class Billing Rules</h2>
            <p class="text-muted mb-0">Override default fee policy class-wise without changing the billing workspace flow</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="#" class="btn btn-outline-primary btn-sm"><i class="fas fa-book me-2"></i>User Guide</a>
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                    <i class="fas fa-user text-secondary"></i>
                </div>
                <div class="small">
                    <div class="fw-semibold"><?= $adminName ?></div>
                    <div class="text-muted">Admin</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($msg === 'saved'): ?>
        <div class="alert alert-success">Rule saved successfully.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-success">Rule deleted.</div>
    <?php elseif ($msg === 'error'): ?>
        <div class="alert alert-danger">
            Something went wrong. Please try again.
            <?php if (!empty($errors)): ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($edit_rule): ?>
                    <input type="hidden" name="edit_id" value="<?= (int)$edit_rule['id'] ?>">
                <?php endif; ?>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Class</label>
                    <select name="class_id" id="classSelect" class="form-select" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?>
                            <?php
                            $selected = $edit_rule && (string)$edit_rule['class_name'] === (string)$c ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars((string)$c, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>><?= htmlspecialchars((string)$c, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Billing Frequency</label>
                    <?php $freq = $edit_rule['billing_frequency'] ?? 'monthly'; ?>
                    <select name="billing_frequency" class="form-select" required>
                        <option value="monthly" <?= $freq === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="quarterly" <?= $freq === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                        <option value="yearly" <?= $freq === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                        <option value="one-time" <?= $freq === 'one-time' ? 'selected' : '' ?>>One-Time</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Billing Type</label>
                    <?php $bt = $edit_rule['billing_type'] ?? 'individual'; ?>
                    <select name="billing_type" class="form-select" required>
                        <option value="individual" <?= $bt === 'individual' ? 'selected' : '' ?>>Individual</option>
                        <option value="family" <?= $bt === 'family' ? 'selected' : '' ?>>Family</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Rule Note</label>
                    <input type="text" name="rule_note" class="form-control" maxlength="255" placeholder="Rule note (optional)" value="<?= htmlspecialchars((string)($edit_rule['rule_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary"><?= $edit_rule ? 'Update Rule' : 'Save Rule' ?></button>
                </div>

                <?php if ($edit_rule): ?>
                    <div class="col-12">
                        <a href="settings-class-billing-rules.php" class="small">Cancel Edit</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fw-bold mb-0">Saved Class Rules</h5>
    </div>

    <?php if (empty($saved_rules)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <div class="mb-2"><i class="fas fa-file-alt fa-2x text-muted"></i></div>
                <div class="fw-bold">No class billing rules yet</div>
                <div class="text-muted mb-3">Default fee policy sab classes par lage gi jab tak aap exception define nahi karte.</div>
                <button type="button" class="btn btn-primary btn-sm" id="addFirstRuleBtn">Add First Rule</button>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Class</th>
                                <th>Billing Frequency</th>
                                <th>Billing Type</th>
                                <th>Rule Note</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saved_rules as $i => $r): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars((string)$r['class_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$r['billing_frequency'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$r['billing_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($r['rule_note'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <a href="settings-class-billing-rules.php?edit_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete billing rule for <?= htmlspecialchars((string)$r['class_name'], ENT_QUOTES, 'UTF-8') ?>?');">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="rule_id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        const btn = document.getElementById('addFirstRuleBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => {
                const sel = document.getElementById('classSelect');
                if (sel) sel.focus();
            }, 350);
        });
    })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

