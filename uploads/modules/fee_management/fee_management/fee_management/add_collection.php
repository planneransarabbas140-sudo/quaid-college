<?php
// File: modules/fee_management/add_collection.php - Record Fee Payment
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student = null;

if ($student_id) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receipt_number = generateUniqueId('RCP');
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $fee_type = sanitizeInput($_POST['fee_type']);
    $payment_method = $_POST['payment_method'];
    $transaction_id = sanitizeInput($_POST['transaction_id']);
    $cheque_number = sanitizeInput($_POST['cheque_number']);
    $bank_name = sanitizeInput($_POST['bank_name']);
    $remarks = sanitizeInput($_POST['remarks']);
    $payment_date = $_POST['payment_date'];
    
    // Calculate due date (end of current month)
    $due_date = date('Y-m-t', strtotime($payment_date));
    
    // Check if student has any pending fees
    $stmt = $db->prepare("SELECT SUM(amount - paid_amount) as total_due 
                          FROM fee_collections 
                          WHERE student_id = :student_id AND status IN ('Pending', 'Partially Paid')");
    $stmt->execute([':student_id' => $student_id]);
    $due = $stmt->fetch();
    
    $stmt = $db->prepare("INSERT INTO fee_collections 
                          (receipt_number, student_id, fee_type, amount, paid_amount, due_date, 
                           payment_date, payment_method, transaction_id, cheque_number, bank_name, 
                           remarks, collected_by, status) 
                          VALUES 
                          (:receipt_number, :student_id, :fee_type, :amount, :paid_amount, :due_date,
                           :payment_date, :payment_method, :transaction_id, :cheque_number, :bank_name,
                           :remarks, :collected_by, 'Paid')");
    
    $result = $stmt->execute([
        ':receipt_number' => $receipt_number,
        ':student_id' => $student_id,
        ':fee_type' => $fee_type,
        ':amount' => $amount,
        ':paid_amount' => $amount,
        ':due_date' => $due_date,
        ':payment_date' => $payment_date,
        ':payment_method' => $payment_method,
        ':transaction_id' => $transaction_id,
        ':cheque_number' => $cheque_number,
        ':bank_name' => $bank_name,
        ':remarks' => $remarks,
        ':collected_by' => getUserId()
    ]);
    
    if ($result) {
        setFlashMessage('success', 'Payment recorded successfully! Receipt #: ' . $receipt_number);
        redirect('receipt.php?id=' . $db->lastInsertId());
    } else {
        setFlashMessage('error', 'Failed to record payment.');
    }
}

// Get all students for dropdown
$students = $db->query("SELECT id, student_id, first_name, last_name, class, section 
                        FROM students ORDER BY first_name")->fetchAll();

$page_title = "Record Fee Payment";
include '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Record Fee Payment</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="paymentForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Select Student *</label>
                            <select name="student_id" class="form-select" required <?php echo $student_id ? 'disabled' : ''; ?>>
                                <option value="">-- Select Student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo $s['first_name'] . ' ' . $s['last_name']; ?> (<?php echo $s['student_id']; ?>) - <?php echo $s['class'] . '-' . $s['section']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($student_id): ?>
                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label>Fee Type *</label>
                            <select name="fee_type" class="form-select" required>
                                <option value="Tuition">Tuition Fee</option>
                                <option value="Admission">Admission Fee</option>
                                <option value="Exam">Examination Fee</option>
                                <option value="Library">Library Fee</option>
                                <option value="Transport">Transport Fee</option>
                                <option value="Sports">Sports Fee</option>
                                <option value="Lab">Lab Fee</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label>Amount (PKR) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label>Payment Method *</label>
                            <select name="payment_method" class="form-select" required id="paymentMethod">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Online">Online</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3" id="transactionIdDiv" style="display: none;">
                            <label>Transaction ID</label>
                            <input type="text" name="transaction_id" class="form-control" placeholder="Transaction/Reference ID">
                        </div>
                        
                        <div class="col-md-6 mb-3" id="chequeDiv" style="display: none;">
                            <label>Cheque Number</label>
                            <input type="text" name="cheque_number" class="form-control" placeholder="Cheque Number">
                        </div>
                        
                        <div class="col-md-6 mb-3" id="bankDiv" style="display: none;">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="Bank Name">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label>Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Record Payment & Generate Receipt</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($student): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Student Fee History</h6>
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->prepare("SELECT * FROM fee_collections WHERE student_id = :student_id ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([':student_id' => $student_id]);
                $history = $stmt->fetchAll();
                ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Date</th><th>Receipt #</th><th>Type</th><th>Amount</th><th>Method</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($h['payment_date'])); ?></td>
                                <td><?php echo $h['receipt_number']; ?></td>
                                <td><?php echo $h['fee_type']; ?></td>
                                <td>PKR <?php echo number_format($h['paid_amount'], 2); ?></td>
                                <td><?php echo $h['payment_method']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('paymentMethod').addEventListener('change', function() {
    var method = this.value;
    var transactionDiv = document.getElementById('transactionIdDiv');
    var chequeDiv = document.getElementById('chequeDiv');
    var bankDiv = document.getElementById('bankDiv');
    
    if (method === 'Bank Transfer' || method === 'Online') {
        transactionDiv.style.display = 'block';
        chequeDiv.style.display = 'none';
        bankDiv.style.display = 'block';
    } else if (method === 'Cheque') {
        transactionDiv.style.display = 'none';
        chequeDiv.style.display = 'block';
        bankDiv.style.display = 'block';
    } else {
        transactionDiv.style.display = 'none';
        chequeDiv.style.display = 'none';
        bankDiv.style.display = 'none';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>