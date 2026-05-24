<?php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit();
}

$clr_id = (int)($_GET['id'] ?? 0);
$db = (new Database())->getConnection();

function cert_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$selectProgram = columnExists($db, 'students', 'program_id') && tableExists($db, 'programs') ? 'p.name' : "COALESCE(s.class, 'Program')";
$programType = columnExists($db, 'students', 'program_id') && tableExists($db, 'programs') ? 'p.type' : "'Academic'";
$programJoin = columnExists($db, 'students', 'program_id') && tableExists($db, 'programs') ? 'LEFT JOIN programs p ON s.program_id = p.id' : '';
$campusSelect = columnExists($db, 'students', 'campus_id') ? 'c.name' : "COALESCE(s.campus, 'Rajanpur')";
$campusJoin = columnExists($db, 'students', 'campus_id') ? 'LEFT JOIN campuses c ON s.campus_id = c.id' : '';
$sessionExpr = columnExists($db, 'students', 'session') ? 's.session' : "COALESCE(YEAR(s.admission_date), YEAR(CURDATE()))";
$semesterExpr = columnExists($db, 'students', 'current_semester') ? 's.current_semester' : '1';
$cnicExpr = columnExists($db, 'students', 'cnic') ? 's.cnic' : "''";

$stmt = $db->prepare("
    SELECT dc.*, CONCAT(s.first_name,' ',s.last_name) AS student_name,
           COALESCE(NULLIF(s.roll_number,''), NULLIF(s.student_id,''), CONCAT('STD-', s.id)) AS roll_number,
           s.father_name, $cnicExpr AS cnic, $sessionExpr AS session, $semesterExpr AS current_semester,
           $selectProgram AS program_name, $programType AS program_type,
           $campusSelect AS campus_name, u.full_name AS approved_by_name
    FROM degree_clearance dc
    JOIN students s ON dc.student_id = s.id
    $programJoin
    $campusJoin
    LEFT JOIN users u ON dc.approved_by = u.id
    WHERE dc.id = :id AND dc.status = 'approved'
");
$stmt->execute([':id' => $clr_id]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    die('Certificate not found or not yet approved.');
}

if (getUserRole() === 'student') {
    $own = $db->prepare("SELECT id FROM students WHERE user_id = :uid AND id = :sid");
    $own->execute([':uid' => getUserId(), ':sid' => $cert['student_id']]);
    if (!$own->fetch()) {
        die('Access denied.');
    }
}

$cps = $db->prepare("SELECT * FROM clearance_checkpoints WHERE clearance_id = :id ORDER BY id ASC");
$cps->execute([':id' => $clr_id]);
$cert_checkpoints = $cps->fetchAll(PDO::FETCH_ASSOC);

$db->prepare("UPDATE degree_clearance SET certificate_generated = 1 WHERE id = :id")->execute([':id' => $clr_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Degree Clearance Certificate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { margin:0; background:#eef2f6; font-family:'DM Sans', sans-serif; color:#374151; }
        @media print { .no-print { display:none!important; } body { background:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; } .certificate-page { box-shadow:none!important; margin:0!important; } }
        .certificate-page { max-width:800px; margin:28px auto; padding:40px; background:#fff; min-height:100vh; position:relative; box-shadow:0 24px 70px rgba(15,45,72,.14); }
        .cert-border { border:8px solid #0f2d48; border-radius:16px; padding:40px; position:relative; overflow:hidden; }
        .cert-border::before { content:''; position:absolute; inset:6px; border:2px solid #4ec2b5; border-radius:10px; pointer-events:none; }
        .watermark { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-30deg); font-size:80px; color:rgba(78,194,181,.05); font-family:'Playfair Display',serif; font-weight:900; white-space:nowrap; pointer-events:none; z-index:0; }
        .cert-content { position:relative; z-index:1; }
        .playfair { font-family:'Playfair Display',serif; }
        .mono { font-family:'Space Mono',monospace; }
        .cert-line { height:3px; background:linear-gradient(to right,#0f2d48,#4ec2b5,#f0b429); border-radius:99px; margin:16px 0; }
    </style>
</head>
<body>
    <div class="no-print text-center py-3" style="background:#0f2d48;">
        <button onclick="window.print()" class="btn me-2" style="background:#4ec2b5;color:#0f2d48;font-weight:700;">
            <i class="fas fa-print me-2"></i>Print Certificate
        </button>
        <a href="clearance.php" class="btn btn-outline-light"><i class="fas fa-arrow-left me-2"></i>Back</a>
    </div>

    <div class="certificate-page">
        <div class="cert-border">
            <div class="watermark">QGC</div>
            <div class="cert-content">
                <div class="text-center mb-4">
                    <img src="../../assets/images/qgc-logo.png" height="70" alt="QGC Logo">
                    <h2 class="playfair mt-3" style="color:#0f2d48;">Quaid-e-Azam Group of Colleges</h2>
                    <p class="mono" style="color:#4ec2b5;font-size:.8rem;letter-spacing:.1em;"><?= cert_h($cert['campus_name']) ?> Campus</p>
                    <div class="cert-line"></div>
                    <h3 class="playfair" style="color:#0f2d48;font-size:1.6rem;">DEGREE CLEARANCE CERTIFICATE</h3>
                    <p class="mono" style="font-size:.75rem;color:#94a3b8;">Application No: <?= cert_h($cert['application_number']) ?></p>
                </div>

                <p style="font-size:1rem;line-height:1.8;text-align:justify;">
                    This is to certify that <strong style="color:#0f2d48;"><?= cert_h($cert['student_name']) ?></strong>
                    son/daughter of <strong><?= cert_h($cert['father_name'] ?: 'N/A') ?></strong>,
                    CNIC: <span class="mono"><?= cert_h($cert['cnic'] ?: 'N/A') ?></span>,
                    Roll No: <span class="mono"><?= cert_h($cert['roll_number']) ?></span>
                    has successfully completed all clearance requirements for the degree of
                    <strong style="color:#0f2d48;"><?= cert_h($cert['program_name']) ?></strong>
                    from <?= cert_h($cert['campus_name']) ?> Campus, Session <?= cert_h($cert['session']) ?>.
                    All dues have been cleared and the student is hereby granted this clearance certificate for degree issuance.
                </p>

                <table style="width:100%;border-collapse:collapse;margin:24px 0;">
                    <thead><tr style="background:#0f2d48;color:#fff;"><th style="padding:10px 14px;font-size:.82rem;">Department</th><th style="padding:10px 14px;font-size:.82rem;text-align:center;">Status</th><th style="padding:10px 14px;font-size:.82rem;">Remarks</th></tr></thead>
                    <tbody>
                        <?php foreach ($cert_checkpoints as $cp): ?>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:10px 14px;font-size:.85rem;"><?= cert_h($cp['checkpoint_name']) ?></td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <?php if (in_array($cp['status'], ['clear', 'waived'], true)): ?>
                                        <span style="color:#16a34a;font-weight:700;">Clear</span>
                                    <?php else: ?>
                                        <span style="color:#dc2626;font-weight:700;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 14px;font-size:.82rem;color:#6b7280;"><?= cert_h($cp['remarks'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:32px;">
                    <div>
                        <p style="font-size:.8rem;color:#94a3b8;margin-bottom:4px;">Approved By</p>
                        <p style="font-weight:700;color:#0f2d48;"><?= cert_h($cert['approved_by_name'] ?: 'Admin') ?></p>
                        <p style="font-size:.8rem;color:#6b7280;">Date: <?= cert_h(date('d F Y', strtotime($cert['approved_at']))) ?></p>
                    </div>
                    <div style="text-align:right;">
                        <div style="display:inline-block;border-top:2px solid #0f2d48;padding-top:8px;min-width:180px;">
                            <p style="font-size:.8rem;color:#0f2d48;font-weight:600;">Authorized Signature</p>
                            <p style="font-size:.75rem;color:#94a3b8;">Principal / Admin</p>
                        </div>
                    </div>
                </div>

                <div style="position:absolute;bottom:60px;right:60px;width:100px;height:100px;border:2px dashed rgba(78,194,181,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <span class="mono" style="font-size:.6rem;color:rgba(0,0,0,.2);text-align:center;">OFFICIAL<br>STAMP</span>
                </div>

                <div style="text-align:center;margin-top:32px;padding-top:16px;border-top:1px solid #e2e8f0;">
                    <p class="mono" style="font-size:.72rem;color:#94a3b8;">
                        This certificate is computer generated and valid without signature if digitally issued.<br>
                        Issued on: <?= cert_h(date('d F Y H:i')) ?> | QGC ERP System
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
