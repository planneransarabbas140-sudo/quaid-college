<?php
// File: modules/fee_management/receipt.php - Generate Fee Receipt
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT fc.*, s.first_name, s.last_name, s.student_id as student_no, s.class, s.section, 
           s.guardian_name, s.address, u.full_name as collector_name
    FROM fee_collections fc
    JOIN students s ON fc.student_id = s.id
    LEFT JOIN users u ON fc.collected_by = u.id
    WHERE fc.id = :id
");
$stmt->execute([':id' => $id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die('Receipt not found!');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt - <?php echo $receipt['receipt_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
        }
        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .receipt-header {
            background: #1a3c5e;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .receipt-body {
            padding: 30px;
        }
        .receipt-footer {
            background: #f8f9fc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        .watermark {
            position: fixed;
            opacity: 0.1;
            font-size: 80px;
            transform: rotate(-45deg);
            pointer-events: none;
            z-index: 0;
        }
    </style>
</head>
<body>
    <div class="no-print text-center mt-4 mb-3">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
    
    <div class="receipt-container">
        <div class="receipt-header">
            <h3>Quaid-e-Azam Group of Colleges</h3>
            <p>Fee Payment Receipt</p>
        </div>
        
        <div class="receipt-body">
            <div class="row mb-4">
                <div class="col-6">
                    <strong>Receipt No:</strong> <?php echo $receipt['receipt_number']; ?>
                </div>
                <div class="col-6 text-end">
                    <strong>Date:</strong> <?php echo date('d M Y', strtotime($receipt['payment_date'])); ?>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-12">
                    <h6>Student Information</h6>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td width="30%"><strong>Student ID:</strong></td>
                            <td><?php echo $receipt['student_no']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Student Name:</strong></td>
                            <td><?php echo $receipt['first_name'] . ' ' . $receipt['last_name']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Class/Section:</strong></td>
                            <td><?php echo $receipt['class'] . ' - ' . $receipt['section']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Guardian Name:</strong></td>
                            <td><?php echo $receipt['guardian_name']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-12">
                    <h6>Payment Details</h6>
                    <table class="table table-bordered">
                        <thead>
                            <tr class="table-primary">
                                <th>Fee Type</th>
                                <th>Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo $receipt['fee_type']; ?> Fee</td>
                                <td class="text-end"><?php echo number_format($receipt['paid_amount'], 2); ?></td>
                            </tr>
                            <?php if ($receipt['discount_given'] > 0): ?>
                            <tr>
                                <td>Discount</td>
                                <td class="text-end">- <?php echo number_format($receipt['discount_given'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($receipt['late_fee_charged'] > 0): ?>
                            <tr>
                                <td>Late Fee</td>
                                <td class="text-end">+ <?php echo number_format($receipt['late_fee_charged'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-warning">
                                <td><strong>Total Paid</strong></td>
                                <td class="text-end"><strong>PKR <?php echo number_format($receipt['paid_amount'], 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-6">
                    <strong>Payment Method:</strong> <?php echo $receipt['payment_method']; ?>
                    <?php if ($receipt['transaction_id']): ?>
                        <br><strong>Transaction ID:</strong> <?php echo $receipt['transaction_id']; ?>
                    <?php endif; ?>
                    <?php if ($receipt['cheque_number']): ?>
                        <br><strong>Cheque Number:</strong> <?php echo $receipt['cheque_number']; ?>
                    <?php endif; ?>
                </div>
                <div class="col-6 text-end">
                    <strong>Amount in Words:</strong><br>
                    <?php 
                    function numberToWords($number) {
                        $words = array(
                            '0' => '', '1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four',
                            '5' => 'Five', '6' => 'Six', '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
                            '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve', '13' => 'Thirteen',
                            '14' => 'Fourteen', '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
                            '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty', '30' => 'Thirty',
                            '40' => 'Forty', '50' => 'Fifty', '60' => 'Sixty', '70' => 'Seventy',
                            '80' => 'Eighty', '90' => 'Ninety'
                        );
                        return ucfirst($words[(int)$number]) . ' Rupees Only';
                    }
                    echo numberToWords($receipt['paid_amount']); 
                    ?>
                </div>
            </div>
            
            <?php if ($receipt['remarks']): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <strong>Remarks:</strong><br>
                    <?php echo nl2br($receipt['remarks']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="receipt-footer">
            <div class="row">
                <div class="col-6 text-start">
                    <strong>Collected By:</strong><br>
                    <?php echo $receipt['collector_name']; ?>
                </div>
                <div class="col-6 text-end">
                    <strong>Authorized Signature</strong><br>
                    <br>
                    ____________________
                </div>
            </div>
            <div class="mt-3">
                <small>This is a computer generated receipt. No signature required.</small>
            </div>
        </div>
    </div>
    
    <script src="https://kit.fontawesome.com/your-kit.js"></script>
</body>
</html>