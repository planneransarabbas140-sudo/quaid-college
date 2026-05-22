<?php
// File: modules/fee_management/add_collection.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$fee_types_missing = false;

// Get students for dropdown (graceful fallback)
try {
    $students = $db->query("SELECT id, student_id, first_name, last_name, class, section FROM students ORDER BY first_name, last_name")->fetchAll();
} catch (PDOException $e) {
    $students = [];
}

// Get fee types — gracefully handle missing table
try {
    $fee_types = $db->query("SELECT * FROM fee_types ORDER BY fee_name ASC")->fetchAll();
} catch (PDOException $e) {
    $fee_types = [];
    $fee_types_missing = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $fee_type_name = sanitizeInput($_POST['fee_type']);
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = sanitizeInput($_POST['payment_method']);
    $transaction_id = sanitizeInput($_POST['transaction_id']);
    $remarks = sanitizeInput($_POST['remarks']);
    $academic_year = sanitizeInput($_POST['academic_year']);

    // Validate student
    $stmt = $db->prepare("SELECT id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    if (!$stmt->fetch()) {
        $error = 'Invalid student selected.';
    }

    if (empty($error)) {
        try {
            $db->beginTransaction();

            // Insert into fee_collections
            // Note: Keeping existing table structure if it exists. 
            // If it doesn't, this might fail, but the user asked to fix the dropdown issue.
            $stmt = $db->prepare("INSERT INTO fee_collections (student_id, fee_type, amount, paid_amount, payment_date, payment_method, transaction_id, status, remarks, academic_year, collected_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Paid', ?, ?, ?)");
            $stmt->execute([$student_id, $fee_type_name, $paid_amount, $paid_amount, $payment_date, $payment_method, $transaction_id, $remarks, $academic_year, getUserId()]);

            $collection_id = $db->lastInsertId();

            // Insert transaction record
            $stmt = $db->prepare("INSERT INTO fee_transactions (fee_collection_id, transaction_type, amount, description, processed_by) VALUES (?, 'Payment', ?, ?, ?)");
            $stmt->execute([$collection_id, $paid_amount, "Fee payment: {$fee_type_name}", getUserId()]);

            recordIncome($db, [
                'source' => 'fee_management',
                'reference_id' => $collection_id,
                'campus' => getCampusNameByStudent($db, $student_id),
                'description' => "Fee payment: {$fee_type_name}",
                'amount' => $paid_amount
            ]);

            $db->commit();
            $success = 'Fee collected successfully!';
            $_POST = []; // Clear form
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error collecting fee: ' . $e->getMessage();
        }
    }
}

$page_title = "Add Fee Collection";
include '../../includes/header.php';
?>
<script src="../../assets/js/payment_helper.js"></script>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
    .card-header { background-color: var(--navy); color: white; }
    .btn-teal { background-color: var(--teal); color: white; border: none; }
    .btn-teal:hover { background-color: #3da89b; color: white; }
    .form-label { color: var(--navy); font-weight: 600; }
    .text-navy { color: var(--navy); }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg border-0">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold"><i class="fas fa-money-bill-wave me-2"></i>Add Fee Collection</h5>
                    <a href="index.php" class="btn btn-sm btn-light text-navy">Back to List</a>
                </div>
                <div class="card-body p-4">
                    <?php if ($fee_types_missing): ?>
                        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3" role="alert" style="border-left:4px solid #f59e0b !important;">
                            <i class="fas fa-exclamation-triangle fa-lg text-warning"></i>
                            <div>
                                <strong>Fee Types table not found!</strong>
                                Please run the setup first to create the table and seed default fee data.<br>
                                <a href="../../setup_fee_types.php" class="btn btn-sm btn-warning mt-2">
                                    <i class="fas fa-database me-1"></i> Run Setup Now
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

                    <form method="POST" action="" id="feeForm">
                        <div class="row g-3">
                            <!-- Student Selection -->
                            <div class="col-md-6">
                                <label class="form-label">Student Name / ID *</label>
                                <select name="student_id" class="form-select select2" required>
                                    <option value="">Search Student...</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ' - ' . $student['class'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Fee Type -->
                            <div class="col-md-6">
                                <label class="form-label">Fee Type *</label>
                                <select name="fee_type" id="fee_type_select" class="form-select" <?php echo $fee_types_missing ? 'disabled' : 'required'; ?>>
                                    <option value=""><?php echo $fee_types_missing ? '— Run setup first —' : 'Select Fee Type'; ?></option>
                                    <?php foreach ($fee_types as $type): ?>
                                        <option
                                            value="<?php echo htmlspecialchars($type['fee_name']); ?>"
                                            data-amount="<?php echo htmlspecialchars($type['amount']); ?>"
                                            data-desc="<?php echo htmlspecialchars($type['description'] ?? ''); ?>"
                                            <?php echo (isset($_POST['fee_type']) && $_POST['fee_type'] === $type['fee_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['fee_name'] . ' — PKR ' . number_format($type['amount'], 0)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="fee_desc_note" class="form-text text-muted mt-1" style="min-height:1.2em;display:block;"></small>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-4">
                                <label class="form-label">Amount Paid (PKR) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" name="paid_amount" id="paid_amount" class="form-control" step="0.01" min="0" required>
                                </div>
                            </div>

                            <!-- Date -->
                            <div class="col-md-4">
                                <label class="form-label">Payment Date *</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <!-- Academic Year -->
                            <div class="col-md-4">
                                <label class="form-label">Academic Year *</label>
                                <input type="text" name="academic_year" class="form-control" value="<?php echo getCurrentAcademicYear(); ?>" required>
                            </div>

                            <!-- Payment Method -->
                            <div class="col-md-6">
                                <label class="form-label">Payment Method *</label>
                                <select name='payment_method' id="payment_method_select" class='form-select' required>
                                    <option value=''>Select Payment Method</option>
                                    <option value='Cash'>Cash</option>
                                    <option value='Bank Transfer'>Bank Transfer</option>
                                    <option value='Online Payment'>Online Payment</option>
                                    <option value='Cheque'>Cheque</option>
                                    <option value='EasyPaisa'>EasyPaisa</option>
                                    <option value='JazzCash'>JazzCash</option>
                                    <option value='Card/ATM'>Card/ATM</option>
                                </select>
                            </div>

                            <!-- Transaction ID -->
                            <div class="col-md-6" id="transaction_id_field" style="display: none;">
                                <label class="form-label">Transaction ID / Ref *</label>
                                <input type="text" name="transaction_id" class="form-control" placeholder="Enter transaction reference">
                            </div>

                            <!-- Remarks -->
                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>

                            <div class="col-12 mt-4 text-end">
                                <button type="reset" class="btn btn-secondary px-4 me-2">Reset</button>
                                <button type="submit" class="btn btn-teal px-5">Submit Collection</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const feeSelect  = document.getElementById('fee_type_select');
    const amountInput = document.getElementById('paid_amount');
    const descNote   = document.getElementById('fee_desc_note');
    const paymentMethodSelect = document.getElementById('payment_method_select');
    const transactionField = document.getElementById('transaction_id_field');

    function syncFeeDetails() {
        const opt    = feeSelect.options[feeSelect.selectedIndex];
        const amount = opt ? opt.getAttribute('data-amount') : null;
        const desc   = opt ? opt.getAttribute('data-desc')   : null;

        if (amount !== null && amount !== '') {
            amountInput.value = parseFloat(amount).toFixed(2);
        } else {
            amountInput.value = '';
        }

        if (descNote) {
            descNote.textContent = (desc && desc.trim()) ? '📝 ' + desc : '';
        }
    }

    // Auto-fill on change
    feeSelect.addEventListener('change', syncFeeDetails);

    // Initialize payment helper
    setupPaymentMethod('payment_method_select', 'transaction_id_field');

    // Trigger on page load
    if (feeSelect.value) syncFeeDetails();
});
</script>

<?php include '../../includes/footer.php'; ?>
