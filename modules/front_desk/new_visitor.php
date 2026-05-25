<?php
// File: modules/front_desk/new_visitor.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor_name = sanitizeInput($_POST['visitor_name']);
    $phone = sanitizeInput($_POST['phone']);
    $purpose = sanitizeInput($_POST['purpose']);
    $whom_to_meet = sanitizeInput($_POST['whom_to_meet']);
    $check_in = date('Y-m-d H:i:s');
    $payment_method = sanitizeInput($_POST['payment_method']);
    $transaction_id = sanitizeInput($_POST['transaction_id']);
    $fee_paid = floatval($_POST['fee_paid']);
    
    try {
        $stmt = $db->prepare("INSERT INTO visitors (visitor_name, phone, purpose, whom_to_meet, check_in, payment_method, transaction_id, fee_paid) VALUES (:visitor_name, :phone, :purpose, :whom_to_meet, :check_in, :payment_method, :transaction_id, :fee_paid)");
        $stmt->execute([
            ':visitor_name' => $visitor_name,
            ':phone' => $phone,
            ':purpose' => $purpose,
            ':whom_to_meet' => $whom_to_meet,
            ':check_in' => $check_in,
            ':payment_method' => $payment_method,
            ':transaction_id' => $transaction_id,
            ':fee_paid' => $fee_paid
        ]);

        $success = "Visitor checked in successfully!";
    } catch (PDOException $e) {
        $error = "Error adding visitor: " . $e->getMessage();
    }
}

$page_title = "New Visitor";
include '../../includes/header.php';
?>
<script src="../../assets/js/payment_helper.js?v=2"></script>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">Check-In Visitor</h2>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Visitor Name *</label>
                            <input type="text" class="form-control" name="visitor_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="text" class="form-control" name="phone" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Whom to Meet *</label>
                            <input type="text" class="form-control" name="whom_to_meet" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Purpose of Visit *</label>
                        <textarea class="form-control" name="purpose" rows="3" required></textarea>
                    </div>

                    <h5 class="text-primary mt-4 mb-3">Visitor Fee & Payment</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fee Paid (PKR)</label>
                            <input type="number" class="form-control" name="fee_paid" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" id="vis_pay_method" class="form-select">
                                <option value="N/A">N/A</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Online Payment">Online Payment</option>
                                <option value="Cheque">Cheque</option>
                                <option value="EasyPaisa">EasyPaisa</option>
                                <option value="JazzCash">JazzCash</option>
                                <option value="Card/ATM">Card/ATM</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="vis_tid_field" style="display: none;">
                            <label class="form-label">Transaction ID</label>
                            <input type="text" name="transaction_id" class="form-control" placeholder="Enter Reference ID">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Check-In Visitor</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setupPaymentMethod('vis_pay_method', 'vis_tid_field');
});
</script>

<?php include '../../includes/footer.php'; ?>
