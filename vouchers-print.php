<?php
// File: vouchers-print.php - Clean Printable Fee Voucher Layout
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/voucher_functions.php';

// Session verification to protect paid/unpaid invoice records
if (!isLoggedIn()) {
    redirect('modules/auth/login.php');
}

if (!in_array(getUserRole(), ['admin', 'owner'], true)) {
    setFlashMessage('error', 'You are not allowed to print vouchers.');
    redirect('dashboard.php');
}

$voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($voucher_id <= 0) {
    redirect('vouchers.php');
}

$database = new Database();
$conn = $database->getConnection();

$voucher = getVoucherById($conn, $voucher_id);
if (!$voucher) {
    redirect('vouchers.php');
}

$school = getSchoolInfoForVoucher($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Voucher - <?= htmlspecialchars($voucher['voucher_number']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/print.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 0;
            font-size: 13px;
            line-height: 1.4;
        }

        .print-voucher {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
        }

        .voucher-box {
            border: 1px solid #000;
            padding: 15px;
            margin-bottom: 25px;
            position: relative;
            box-sizing: border-box;
            background-color: #fff;
        }

        .divider {
            border-top: 2px dashed #000;
            margin: 25px 0;
            text-align: center;
            position: relative;
        }

        .divider span {
            background: #fff;
            padding: 0 10px;
            position: relative;
            top: -10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: bold;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .logo-cell {
            width: 70px;
            vertical-align: middle;
        }

        .logo-cell img {
            max-height: 60px;
            max-width: 60px;
            display: block;
        }

        .school-info-cell {
            vertical-align: middle;
            padding-left: 10px;
        }

        .school-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }

        .school-details {
            font-size: 11px;
            color: #333;
            margin: 2px 0 0 0;
        }

        .copy-tag {
            text-align: right;
            vertical-align: top;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title-bar {
            background-color: #f0f0f0;
            border: 1px solid #000;
            text-align: center;
            font-weight: bold;
            padding: 5px;
            font-size: 14px;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .meta-table td {
            padding: 4px;
            vertical-align: top;
        }

        .meta-label {
            font-weight: bold;
            width: 15%;
        }

        .meta-value {
            width: 35%;
            border-bottom: 1px solid #ddd;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 6px 10px;
            text-align: left;
        }

        .items-table th {
            background-color: #f9f9f9;
            font-weight: bold;
        }

        .items-table .text-right {
            text-align: right;
        }

        .instructions-box {
            font-size: 11px;
            border: 1px solid #000;
            padding: 8px 12px;
            margin-top: 15px;
            background-color: #fcfcfc;
        }

        .instructions-title {
            font-weight: bold;
            margin-bottom: 4px;
            text-decoration: underline;
        }

        .footer-signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
        }

        .footer-signatures td {
            width: 50%;
            text-align: center;
            font-size: 11px;
        }

        .sig-line {
            width: 180px;
            margin: 0 auto 5px auto;
            border-bottom: 1px dashed #000;
        }

        /* PRINT MEDIA RULES */
        @media print {
            body {
                background: #fff;
                color: #000;
                margin: 0;
                padding: 0;
            }
            .print-voucher {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .voucher-box {
                border: 1px solid #000;
                page-break-inside: avoid;
            }
            .no-print {
                display: none;
            }
            .title-bar {
                background-color: #eaeaea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .items-table th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        .no-print-bar {
            background-color: #f8fafc;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background-color: #0f2d48;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-print:hover {
            background-color: #1f4f72;
        }
    </style>
</head>
<body>

    <div class="no-print no-print-bar">
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Voucher</button>
    </div>

    <div class="print-voucher">
        <?php
        // We render 2 copies: Top (School Copy) and Bottom (Student Copy)
        $copies = ['School Copy', 'Student/Parent Copy'];
        foreach ($copies as $index => $copyTitle):
        ?>
            <div class="voucher-box">
                <table class="header-table">
                    <tr>
                        <td class="logo-cell">
                            <img src="<?= htmlspecialchars(BASE_URL . $school['logo']) ?>" alt="Logo">
                        </td>
                        <td class="school-info-cell">
                            <div class="school-name"><?= htmlspecialchars($school['name']) ?></div>
                            <div class="school-details">
                                <?= htmlspecialchars($school['campus']) ?><br>
                                <?= htmlspecialchars($school['address']) ?> | Tel: <?= htmlspecialchars($school['phone']) ?>
                            </div>
                        </td>
                        <td class="copy-tag">
                            <?= htmlspecialchars($copyTitle) ?>
                        </td>
                    </tr>
                </table>

                <div class="title-bar">FEE PAYMENT VOUCHER</div>

                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Voucher No:</td>
                        <td class="meta-value"><strong><?= htmlspecialchars($voucher['voucher_number']) ?></strong></td>
                        <td class="meta-label">Issue Date:</td>
                        <td class="meta-value"><?= date('d-M-Y', strtotime($voucher['issue_date'])) ?></td>
                    </tr>
                    <tr>
                        <td class="meta-label">
                            <?= $voucher['voucher_type'] === 'family' ? 'Guardian Phone:' : 'Student Name:' ?>
                        </td>
                        <td class="meta-value">
                            <strong>
                                <?php if ($voucher['voucher_type'] === 'individual'): ?>
                                    <?= htmlspecialchars($voucher['first_name'] . ' ' . $voucher['last_name']) ?>
                                <?php elseif ($voucher['voucher_type'] === 'family'): ?>
                                    <?= htmlspecialchars(($voucher['family_guardian_name'] ?: 'Family Voucher') . ' (' . $voucher['family_id'] . ')') ?>
                                <?php else: ?>
                                    <?= htmlspecialchars(trim(($voucher['first_name'] ?? '') . ' ' . ($voucher['last_name'] ?? '')) ?: 'Bulk Generated Student') ?>
                                <?php endif; ?>
                            </strong>
                        </td>
                        <td class="meta-label">Due Date:</td>
                        <td class="meta-value"><strong><?= date('d-M-Y', strtotime($voucher['due_date'])) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="meta-label">Class:</td>
                        <td class="meta-value"><?= htmlspecialchars($voucher['class'] ?: '-') ?></td>
                        <td class="meta-label">Status:</td>
                        <td class="meta-value" style="text-transform: uppercase;"><strong><?= htmlspecialchars($voucher['status']) ?></strong></td>
                    </tr>
                </table>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Fee Description / Particulars</th>
                            <th class="text-right" style="width: 150px;">Amount (Rs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voucher['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['fee_head']) ?></td>
                                <td class="text-right"><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold" style="font-weight: bold; background-color: #f0f0f0;">
                            <td class="text-right" style="text-align: right; font-weight: bold;">TOTAL PAYABLE AMOUNT:</td>
                            <td class="text-right" style="font-weight: bold; font-size: 14px;">Rs. <?= number_format($voucher['total_amount'], 2) ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="instructions-box">
                    <div class="instructions-title">Payment Instructions & Guidelines:</div>
                    1. Please deposit fee in the school accounts office or via bank transfer.<br>
                    2. Bank Details: Habib Bank Limited (HBL) | Account Number: 1234-5678-9012.<br>
                    3. After due date, a late fee fine of Rs. 100/- per day may be charged.<br>
                    <?php if ($voucher['note']): ?>
                        4. <strong>Note:</strong> <?= htmlspecialchars($voucher['note']) ?><br>
                    <?php endif; ?>
                </div>

                <table class="footer-signatures">
                    <tr>
                        <td>
                            <div class="sig-line"></div>
                            Depositor Signature
                        </td>
                        <td>
                            <div class="sig-line"></div>
                            Authorized School Officer
                        </td>
                    </tr>
                </table>
            </div>

            <?php if ($index === 0): ?>
                <div class="divider">
                    <span>Cut Here</span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Auto-trigger print -->
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
