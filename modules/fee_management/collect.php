<?php
// File: modules/fee_management/collect.php - Collect Fee Payment
require_once '../../config/db.php';
require_once '../../includes/voucher_functions.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get active students for dropdown through the shared student helper.
$students = getStudentList(null, $db);

// Get active fee structures
$fee_structures = $db->query("SELECT * FROM fee_structure WHERE is_active = 1 ORDER BY class, fee_type")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
    $error = 'Security check failed. Please refresh the page and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $fee_type = sanitizeInput($_POST['fee_type']);
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = sanitizeInput($_POST['payment_method']);
    $transaction_id = sanitizeInput($_POST['transaction_id']);
    $remarks = sanitizeInput($_POST['remarks']);
    $academic_year = sanitizeInput($_POST['academic_year']);

    // Validate student exists
    $stmt = $db->prepare("SELECT id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    if (!$stmt->fetch()) {
        $error = 'Invalid student selected.';
    }

    // Validate fee structure exists and get amount
    $stmt = $db->prepare("SELECT * FROM fee_structure WHERE fee_type = ? AND class = (SELECT class FROM students WHERE id = ?) AND is_active = 1");
    $stmt->execute([$fee_type, $student_id]);
    $fee_structure = $stmt->fetch();
    if (!$fee_structure) {
        $error = 'Invalid fee type selected for this student.';
    }

    if (empty($error)) {
        try {
            $db->beginTransaction();

            // Insert against the fee collection columns present in the installed schema.
            $collectionColumns = [];
            $collectionParams = [];
            $addCollectionColumn = static function ($column, $value) use ($db, &$collectionColumns, &$collectionParams) {
                if (columnExists($db, 'fee_collections', $column)) {
                    $collectionColumns[] = "`$column`";
                    $collectionParams[] = $value;
                }
            };

            $addCollectionColumn('student_id', $student_id);
            $addCollectionColumn('fee_structure_id', (int)$fee_structure['id']);
            $addCollectionColumn('fee_type', $fee_type);
            $addCollectionColumn('amount_paid', $paid_amount);
            $addCollectionColumn('amount', (float)$fee_structure['amount']);
            $addCollectionColumn('paid_amount', $paid_amount);
            $addCollectionColumn('payment_date', $payment_date);
            $addCollectionColumn('payment_method', $payment_method);
            $addCollectionColumn('transaction_id', $transaction_id);
            $addCollectionColumn('status', 'Paid');
            $addCollectionColumn('academic_year', $academic_year);
            $addCollectionColumn('remarks', $remarks);
            $addCollectionColumn('collected_by', getUserId());

            $placeholders = implode(', ', array_fill(0, count($collectionColumns), '?'));
            $stmt = $db->prepare("INSERT INTO fee_collections (" . implode(', ', $collectionColumns) . ") VALUES ($placeholders)");
            $stmt->execute($collectionParams);

            $collection_id = $db->lastInsertId();

            // Insert transaction record
            $stmt = $db->prepare("INSERT INTO fee_transactions (fee_collection_id, transaction_type, amount, description, processed_by) VALUES (?, 'Payment', ?, ?, ?)");
            $stmt->execute([$collection_id, $paid_amount, "Fee payment: {$fee_type}", getUserId()]);

            // Non-breaking voucher sync: mark this month's unpaid voucher as paid when a fee is collected.
            $voucher = getVoucherByStudentAndMonth($db, $student_id, date('Y-m', strtotime($payment_date ?: date('Y-m-d'))));
            if ($voucher && $voucher['status'] === 'unpaid') {
                updateVoucherStatus($db, (int)$voucher['id'], 'paid');
            }

            $db->commit();
            $success = 'Fee collected successfully!';

            // Clear form data
            $_POST = [];
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Fee collection failed: ' . $e->getMessage());
            $error = 'Fee collection could not be saved. Please try again.';
        }
    }
}

$selectedStudentId = isset($_POST['student_id'])
    ? (int)$_POST['student_id']
    : (int)($_GET['student_id'] ?? 0);

$page_title = "Collect Fee Payment";
include '../../includes/header.php';
?>

<?php include 'fee_tabs.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Collect Fee Payment</h6>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfTokenInput() ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Student *</label>
                                <select name="student_id" class="form-select" id="student_select" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo (int)$student['id']; ?>" <?php echo $selectedStudentId === (int)$student['id'] ? 'selected' : ''; ?>>
                                            <?php
                                            echo htmlspecialchars(
                                                ($student['student_name'] ?? trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')))
                                                . ' (' . ($student['display_student_id'] ?? $student['student_id'] ?? ('STU-' . $student['id']))
                                                . ' - ' . ($student['class_name'] ?? $student['class'] ?? '-')
                                                . '-' . ($student['section'] ?? '-') . ')'
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Fee Type *</label>
                                <select name="fee_type" class="form-select" id="fee_select" required>
                                    <option value="">Select Fee Type</option>
                                    <?php foreach ($fee_structures as $fee): ?>
                                        <option value="<?php echo $fee['fee_type']; ?>" data-amount="<?php echo $fee['amount']; ?>" data-class="<?php echo $fee['class']; ?>" <?php echo (isset($_POST['fee_type']) && $_POST['fee_type'] == $fee['fee_type']) ? 'selected' : ''; ?>>
                                            <?php echo $fee['class'] . ' - ' . $fee['fee_type'] . ' (PKR ' . number_format($fee['amount'], 2) . ' - ' . $fee['frequency'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Amount Paid (PKR) *</label>
                                <input type="number" name="paid_amount" id="paid_amount" class="form-control" step="0.01" min="0" value="<?php echo $_POST['paid_amount'] ?? ''; ?>" required>
                                <small class="text-muted">Suggested amount will be filled based on fee type</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Payment Date *</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo $_POST['payment_date'] ?? date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Payment Method *</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="Cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Bank Transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="Online" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Online') ? 'selected' : ''; ?>>Online Payment</option>
                                    <option value="Cheque" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Transaction ID/Reference</label>
                                <input type="text" name="transaction_id" class="form-control" value="<?php echo $_POST['transaction_id'] ?? ''; ?>" placeholder="Optional">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Academic Year *</label>
                                <input type="text" name="academic_year" class="form-control" value="<?php echo $_POST['academic_year'] ?? getCurrentAcademicYear(); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"><?php echo $_POST['remarks'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">Collect Fee</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-fill amount when fee type is selected
document.getElementById('fee_select').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const amount = selectedOption.getAttribute('data-amount');
    if (amount) {
        document.getElementById('paid_amount').value = amount;
    }
});

// Filter fee types based on selected student class
document.getElementById('student_select').addEventListener('change', function() {
    const studentId = this.value;
    const feeSelect = document.getElementById('fee_select');

    if (!studentId) {
        // Show all options
        Array.from(feeSelect.options).forEach(option => {
            option.style.display = '';
        });
        return;
    }

    // Get student class (this would need AJAX in real implementation)
    // For now, we'll just show all options
    Array.from(feeSelect.options).forEach(option => {
        option.style.display = '';
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
