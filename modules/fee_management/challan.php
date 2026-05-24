<?php
// Printable self-service fee challan.
require_once '../../config/db.php';
require_once '../../includes/shared_functions.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$db = (new Database())->getConnection();
$role = getUserRole();
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$studentId = (int)($_GET['student_id'] ?? 0);

if ($role === 'student') {
    $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([getUserId()]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        setFlashMessage('error', 'Your student profile is not linked to this portal account yet.');
        redirect('index.php');
    }
    $studentId = (int)$student['id'];
} else {
    requireRole(['admin', 'owner']);
    if ($studentId <= 0) {
        setFlashMessage('error', 'Invalid student selected.');
        redirect('index.php');
    }
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        setFlashMessage('error', 'Student not found.');
        redirect('index.php');
    }
}

$feeLabelColumn = firstExistingColumn($db, 'fee_collections', ['fee_type', 'fee_name']);
$feeLabelExpr = $feeLabelColumn ? "fc.`$feeLabelColumn`" : "'Fee'";
$amountColumn = firstExistingColumn($db, 'fee_collections', ['amount']) ?: 'amount_paid';
$paidColumn = firstExistingColumn($db, 'fee_collections', ['paid_amount', 'amount_paid']) ?: $amountColumn;

$stmt = $db->prepare("
    SELECT fc.id, fc.payment_date, fc.due_date, fc.status,
           $feeLabelExpr AS fee_label,
           fc.`$amountColumn` AS amount,
           fc.`$paidColumn` AS paid_amount
    FROM fee_collections fc
    WHERE fc.student_id = ?
      AND DATE_FORMAT(fc.payment_date, '%Y-%m') = ?
    ORDER BY fc.id ASC
");
$stmt->execute([$studentId, $month]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    setFlashMessage('error', 'No challan found for selected month. Generate challan first.');
    redirect('index.php');
}

$school = getSchoolInfo($db);
$total = 0;
$paid = 0;
foreach ($items as $item) {
    $total += (float)$item['amount'];
    $paid += (float)$item['paid_amount'];
}
$balance = max($total - $paid, 0);
$studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$studentCode = $student['roll_number'] ?: ($student['registration_number'] ?: ($student['student_id'] ?: ('STD-' . $studentId)));
$dueDate = $items[0]['due_date'] ?: date('Y-m-t', strtotime($month . '-01'));
$voucherNo = 'CH-' . str_replace('-', '', $month) . '-' . str_pad((string)$studentId, 4, '0', STR_PAD_LEFT);

function challanH($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fee Challan <?= challanH($voucherNo) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; color: #111; background: #f3f4f6; }
        .toolbar { padding: 14px; text-align: right; }
        .btn { display: inline-block; padding: 9px 14px; border-radius: 6px; background: #0f2d48; color: #fff; text-decoration: none; border: 0; cursor: pointer; font: inherit; }
        .page { max-width: 900px; margin: 0 auto 24px; background: #fff; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .copies { display: grid; gap: 18px; }
        .challan { border: 1px solid #111; padding: 16px; }
        .top { display: flex; align-items: center; gap: 14px; border-bottom: 1px solid #111; padding-bottom: 10px; margin-bottom: 12px; }
        .logo { width: 58px; height: 58px; object-fit: contain; }
        h1 { font-size: 22px; margin: 0; }
        h2 { text-align: center; font-size: 16px; margin: 10px 0; letter-spacing: .04em; }
        .muted { color: #444; font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 20px; margin: 12px 0; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background: #f1f1f1; }
        .total-row td { font-weight: bold; }
        .instructions { margin-top: 12px; font-size: 12px; line-height: 1.5; }
        .copy-label { text-align: right; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .cut { border-top: 1px dashed #555; margin: 4px 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { max-width: none; margin: 0; padding: 0; box-shadow: none; }
            .challan { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="btn" href="index.php">Back</a>
        <button class="btn" onclick="downloadChallan()">Download</button>
        <button class="btn" onclick="window.print()">Print Challan</button>
    </div>
    <main class="page">
        <div class="copies">
            <?php foreach (['Student Copy', 'Office Copy'] as $copy): ?>
                <section class="challan">
                    <div class="copy-label"><?= challanH($copy) ?></div>
                    <div class="top">
                        <img class="logo" src="../../assets/images/qgc-logo.png" alt="Logo">
                        <div>
                            <h1><?= challanH($school['name'] ?? 'Quaid-e-Azam Group of Colleges') ?></h1>
                            <div class="muted"><?= challanH($school['address'] ?? 'Rajanpur') ?> | <?= challanH($school['phone'] ?? '') ?></div>
                        </div>
                    </div>
                    <h2>FEE PAYMENT CHALLAN</h2>
                    <div class="grid">
                        <div><strong>Challan No:</strong> <?= challanH($voucherNo) ?></div>
                        <div><strong>Month:</strong> <?= challanH(date('F Y', strtotime($month . '-01'))) ?></div>
                        <div><strong>Student:</strong> <?= challanH($studentName) ?></div>
                        <div><strong>Student ID:</strong> <?= challanH($studentCode) ?></div>
                        <div><strong>Class:</strong> <?= challanH($student['class'] ?? '') ?><?= !empty($student['section']) ? ' - ' . challanH($student['section']) : '' ?></div>
                        <div><strong>Due Date:</strong> <?= challanH(date('d M Y', strtotime($dueDate))) ?></div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Fee Head</th>
                                <th style="width: 150px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= challanH($item['fee_label'] ?: 'Fee') ?></td>
                                    <td>PKR <?= number_format((float)$item['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td>Total Payable</td>
                                <td>PKR <?= number_format($balance, 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="instructions">
                        <strong>Payment Instructions:</strong> Pay at the school accounts office before the due date.
                        Late payments may be subject to school policy. Keep the student copy for your record.
                    </div>
                </section>
                <?php if ($copy === 'Student Copy'): ?><div class="cut"></div><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </main>
    <script>
        function downloadChallan() {
            const title = document.title;
            document.title = '<?= challanH($voucherNo) ?>';
            window.print();
            setTimeout(function () { document.title = title; }, 500);
        }
    </script>
</body>
</html>
