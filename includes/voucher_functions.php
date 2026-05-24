<?php
// Vouchers Module - Functions
// Version: 1.0
// Depends on: students, fee_structure tables; family vouchers use guardian_phone if no families table exists
// Used by: vouchers.php, vouchers-print.php, vouchers-ajax.php,
//          collect-fee.php / modules/fee_management/collect.php (status sync)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/shared_functions.php';
require_once __DIR__ . '/school_growth_functions.php';

function voucherSafeText($value) {
    return trim((string)$value);
}

function generateVoucherNumber($conn) {
    $prefix = 'VCH-' . date('Ym') . '-';

    try {
        $stmt = $conn->prepare("SELECT voucher_number FROM vouchers WHERE voucher_number LIKE ? ORDER BY voucher_number DESC LIMIT 1 FOR UPDATE");
        $stmt->execute([$prefix . '%']);
        $lastVoucher = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        $stmt = $conn->prepare("SELECT voucher_number FROM vouchers WHERE voucher_number LIKE ? ORDER BY voucher_number DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $lastVoucher = $stmt->fetchColumn();
    }

    $nextNum = 1;
    if ($lastVoucher) {
        $parts = explode('-', (string)$lastVoucher);
        $nextNum = ((int)end($parts)) + 1;
    }

    return $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
}

function getStudentsForVoucher($conn, $class = null) {
    if (function_exists('getAllStudents')) {
        return getAllStudents($conn, $class !== null && $class !== '' ? ['class_id' => voucherSafeText($class)] : []);
    }

    if (!tableExists($conn, 'students')) {
        return [];
    }

    $statusWhere = columnExists($conn, 'students', 'status') ? "WHERE status = 'Active'" : "WHERE 1=1";
    $sql = "SELECT id, first_name, last_name, student_id, class, section, guardian_name, guardian_phone FROM students $statusWhere";
    $params = [];

    if ($class !== null && $class !== '') {
        $sql .= " AND class = ?";
        $params[] = voucherSafeText($class);
    }

    $sql .= " ORDER BY first_name ASC, last_name ASC";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return [];
    }
}

function getClassesForVoucher($conn) {
    if (function_exists('getAllClasses')) {
        return array_values(array_map(fn($class) => $class['class_name'], getAllClasses($conn)));
    }

    if (!tableExists($conn, 'students')) {
        return [];
    }

    try {
        $stmt = $conn->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class <> '' ORDER BY class ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return [];
    }
}

function getFamiliesForVoucher($conn) {
    if (function_exists('getAllFamilies')) {
        $families = [];
        foreach (getAllFamilies($conn) as $family) {
            $families[] = [
                'guardian_phone' => $family['phone'] ?? $family['id'],
                'guardian_name' => $family['family_name'] ?? '',
                'student_count' => $family['student_count'] ?? 0,
            ];
        }
        return $families;
    }

    if (!tableExists($conn, 'students')) {
        return [];
    }

    try {
        $statusWhere = columnExists($conn, 'students', 'status') ? "AND status = 'Active'" : "";
        $stmt = $conn->query("
            SELECT guardian_phone, MIN(NULLIF(guardian_name, '')) AS guardian_name, COUNT(*) AS student_count
            FROM students
            WHERE guardian_phone IS NOT NULL AND guardian_phone <> '' $statusWhere
            GROUP BY guardian_phone
            ORDER BY guardian_name ASC, guardian_phone ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return [];
    }
}

function getStudentFeeBreakdown($conn, $student_id) {
    if (!tableExists($conn, 'students') || !tableExists($conn, 'fee_structure')) {
        return [];
    }

    try {
        $stmt = $conn->prepare("SELECT class FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$student_id]);
        $class = $stmt->fetchColumn();
        if (!$class) {
            return [];
        }

        $stmt = $conn->prepare("SELECT fee_type AS fee_head, amount FROM fee_structure WHERE class = ? AND is_active = 1 ORDER BY fee_type ASC");
        $stmt->execute([$class]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return [];
    }
}

function getFamilyFeeBreakdown($conn, $family_id) {
    if (!tableExists($conn, 'students')) {
        return [];
    }

    try {
        $statusWhere = columnExists($conn, 'students', 'status') ? "AND status = 'Active'" : "";
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE guardian_phone = ? $statusWhere ORDER BY first_name ASC, last_name ASC");
        $stmt->execute([voucherSafeText($family_id)]);
        $siblings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($siblings as $sibling) {
            foreach (getStudentFeeBreakdown($conn, (int)$sibling['id']) as $fee) {
                $items[] = [
                    'fee_head' => voucherSafeText($sibling['first_name'] . ' ' . $sibling['last_name'] . ' - ' . $fee['fee_head']),
                    'amount' => (float)$fee['amount']
                ];
            }
        }
        return $items;
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return [];
    }
}

function createVoucher($conn, $data, $items) {
    if (!tableExists($conn, 'vouchers') || !tableExists($conn, 'voucher_items')) {
        return false;
    }

    try {
        $ownsTransaction = !$conn->inTransaction();
        if ($ownsTransaction) {
            $conn->beginTransaction();
        }

        $voucherNumber = generateVoucherNumber($conn);
        $voucherType = in_array($data['voucher_type'] ?? '', ['individual', 'family', 'bulk'], true)
            ? $data['voucher_type']
            : 'individual';

        $stmt = $conn->prepare("
            INSERT INTO vouchers (voucher_number, student_id, family_id, class, voucher_type, issue_date, due_date, total_amount, status, note, generated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?, ?)
        ");

        $stmt->execute([
            $voucherNumber,
            !empty($data['student_id']) ? (int)$data['student_id'] : null,
            !empty($data['family_id']) ? voucherSafeText($data['family_id']) : null,
            !empty($data['class']) ? voucherSafeText($data['class']) : null,
            $voucherType,
            voucherSafeText($data['issue_date'] ?? ''),
            voucherSafeText($data['due_date'] ?? ''),
            (float)($data['total_amount'] ?? 0),
            isset($data['note']) ? voucherSafeText($data['note']) : null,
            getUserId()
        ]);

        $voucherId = (int)$conn->lastInsertId();
        $stmtItem = $conn->prepare("INSERT INTO voucher_items (voucher_id, fee_head, amount) VALUES (?, ?, ?)");
        foreach ($items as $item) {
            $head = voucherSafeText($item['fee_head'] ?? '');
            $amount = (float)($item['amount'] ?? 0);
            if ($head === '' || $amount < 0) {
                continue;
            }
            $stmtItem->execute([$voucherId, $head, $amount]);
        }

        if ($ownsTransaction) {
            $conn->commit();
        }

        return $voucherId;
    } catch (Exception $e) {
        if (isset($ownsTransaction) && $ownsTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Vouchers Error: " . $e->getMessage());
        return false;
    }
}

function getVoucherList($conn, $filters = []) {
    if (!tableExists($conn, 'vouchers')) {
        return [];
    }

    try {
        $sql = "
            SELECT v.*,
                   s.first_name, s.last_name, s.student_id AS display_student_id,
                   u.full_name AS generated_by_name,
                   (
                       SELECT MIN(NULLIF(fs.guardian_name, ''))
                       FROM students fs
                       WHERE CONVERT(fs.guardian_phone USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(v.family_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   ) AS family_guardian_name
            FROM vouchers v
            LEFT JOIN students s ON s.id = v.student_id
            LEFT JOIN users u ON u.id = v.generated_by
            WHERE 1=1
        ";
        $params = [];

        $search = voucherSafeText($filters['search'] ?? '');
        if ($search !== '') {
            $sql .= " AND (v.voucher_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR v.family_id LIKE ?)";
            $searchParam = '%' . $search . '%';
            array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
        }

        $class = voucherSafeText($filters['class'] ?? '');
        if ($class !== '') {
            $sql .= " AND (v.class = ? OR s.class = ?)";
            array_push($params, $class, $class);
        }

        $status = voucherSafeText($filters['status'] ?? '');
        if (in_array($status, ['unpaid', 'paid', 'cancelled'], true)) {
            $sql .= " AND v.status = ?";
            $params[] = $status;
        }

        $dateFrom = voucherSafeText($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $sql .= " AND v.issue_date >= ?";
            $params[] = $dateFrom;
        }

        $dateTo = voucherSafeText($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $sql .= " AND v.issue_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY v.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return [];
    }
}

function getVoucherById($conn, $voucher_id) {
    if (!tableExists($conn, 'vouchers')) {
        return null;
    }

    try {
        $stmt = $conn->prepare("
            SELECT v.*,
                   s.first_name, s.last_name, s.student_id AS display_student_id, s.section, s.roll_number, s.guardian_name, s.guardian_phone,
                   u.full_name AS generated_by_name,
                   (
                       SELECT MIN(NULLIF(fs.guardian_name, ''))
                       FROM students fs
                       WHERE CONVERT(fs.guardian_phone USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(v.family_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   ) AS family_guardian_name
            FROM vouchers v
            LEFT JOIN students s ON s.id = v.student_id
            LEFT JOIN users u ON u.id = v.generated_by
            WHERE v.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$voucher_id]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voucher) {
            return null;
        }

        $stmtItems = $conn->prepare("SELECT * FROM voucher_items WHERE voucher_id = ? ORDER BY id ASC");
        $stmtItems->execute([(int)$voucher_id]);
        $voucher['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $voucher;
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return null;
    }
}

function updateVoucherStatus($conn, $voucher_id, $status) {
    if (!in_array($status, ['paid', 'cancelled'], true)) {
        return false;
    }

    try {
        $voucher = getVoucherById($conn, (int)$voucher_id);
        if (!$voucher || $voucher['status'] !== 'unpaid') {
            return false;
        }

        $stmt = $conn->prepare("UPDATE vouchers SET status = ? WHERE id = ? AND status = 'unpaid'");
        return $stmt->execute([$status, (int)$voucher_id]);
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return false;
    }
}

function deleteVoucher($conn, $voucher_id) {
    try {
        $voucher = getVoucherById($conn, (int)$voucher_id);
        if (!$voucher || $voucher['status'] !== 'unpaid') {
            return false;
        }

        $stmt = $conn->prepare("DELETE FROM vouchers WHERE id = ? AND status = 'unpaid'");
        return $stmt->execute([(int)$voucher_id]);
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return false;
    }
}

function getSchoolInfoForVoucher($conn) {
    $campusName = $_SESSION['user_campus'] ?? 'Rajanpur';
    $schoolName = 'Quaid-e-Azam Group of Colleges';
    $defaultAddress = 'College Road, Rajanpur, Punjab';
    $defaultPhone = '+923338879961';
    $email = 'info@qgc.edu.pk';

    $siteStatsPath = __DIR__ . '/site-stats.php';
    if (is_file($siteStatsPath)) {
        require $siteStatsPath;
        $schoolName = trim((string)($public_brand_title ?? $schoolName));
        $defaultPhone = trim((string)($site_phone ?? $defaultPhone));
        $email = trim((string)($site_email ?? $email));
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM campuses WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $campusName . '%']);
        $campus = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        $campus = null;
    }

    return [
        'name' => $schoolName,
        'campus' => $campus['name'] ?? $campusName,
        'address' => $campus['address'] ?? $defaultAddress,
        'phone' => $campus['phone'] ?? $defaultPhone,
        'email' => $email,
        'logo' => 'assets/images/qgc-logo.png'
    ];
}

function getVoucherSummary($conn) {
    $summary = [
        'total_vouchers' => 0,
        'total_paid' => 0,
        'total_unpaid' => 0,
        'total_cancelled' => 0,
        'total_amount_generated' => 0.00,
        'total_amount_collected' => 0.00
    ];

    if (!tableExists($conn, 'vouchers')) {
        return $summary;
    }

    try {
        $summary['total_vouchers'] = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM vouchers");
        $summary['total_paid'] = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM vouchers WHERE status = 'paid'");
        $summary['total_unpaid'] = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM vouchers WHERE status = 'unpaid'");
        $summary['total_cancelled'] = (int)sgf_fetchScalar($conn, "SELECT COUNT(*) FROM vouchers WHERE status = 'cancelled'");
        $summary['total_amount_generated'] = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM vouchers");
        $summary['total_amount_collected'] = (float)sgf_fetchScalar($conn, "SELECT COALESCE(SUM(total_amount), 0) FROM vouchers WHERE status = 'paid'");
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
    }

    return $summary;
}

function validateVoucherInput($data) {
    $errors = [];
    $voucher_type = voucherSafeText($data['voucher_type'] ?? '');

    if (!in_array($voucher_type, ['individual', 'family', 'bulk'], true)) {
        $errors[] = 'Please select a valid voucher type.';
    }

    if ($voucher_type === 'individual' && (int)($data['student_id'] ?? 0) <= 0) {
        $errors[] = 'Please select a student for individual voucher.';
    }

    if ($voucher_type === 'family' && voucherSafeText($data['family_id'] ?? '') === '') {
        $errors[] = 'Please select a family for family voucher.';
    }

    $issue_date = voucherSafeText($data['issue_date'] ?? '');
    $due_date = voucherSafeText($data['due_date'] ?? '');

    if ($issue_date === '') {
        $errors[] = 'Issue date is required.';
    }
    if ($due_date === '') {
        $errors[] = 'Due date is required.';
    }
    if ($issue_date !== '' && $due_date !== '' && strtotime($due_date) < strtotime($issue_date)) {
        $errors[] = 'Due date cannot be before issue date.';
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

function getVoucherByStudentAndMonth($conn, $student_id, $year_month) {
    if (!tableExists($conn, 'vouchers')) {
        return null;
    }

    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM vouchers
            WHERE student_id = ?
              AND DATE_FORMAT(issue_date, '%Y-%m') = ?
              AND status = 'unpaid'
            LIMIT 1
        ");
        $stmt->execute([(int)$student_id, voucherSafeText($year_month)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Vouchers Error: " . $e->getMessage());
        return null;
    }
}
