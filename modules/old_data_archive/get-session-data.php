<?php
// File: modules/old_data_archive/get-session-data.php
// AJAX endpoint: summary cards + preview table (session based)

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
requireRole(['admin', 'owner']);

$database = new Database();
$pdo = $database->getConnection();

$action = $_GET['action'] ?? '';
$session = trim((string)($_GET['session'] ?? ''));
$page = (int)($_GET['page'] ?? 0);
$type = trim((string)($_GET['type'] ?? 'all'));
$search = trim((string)($_GET['search'] ?? ''));

$allowedSessions = ['2023-24', '2024-25', '2025-26', '2026-27'];

if (!preg_match('/^\d{4}-\d{2}$/', $session) || !in_array($session, $allowedSessions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit;
}

$yearStart = (int)substr($session, 0, 4);
if ($yearStart < 2000 || $yearStart > (int)date('Y') + 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid year']);
    exit;
}

$page = max(0, $page);
$perPage = 50;
$offset = $page * $perPage;

$validTypes = ['all', 'student', 'attendance', 'fee', 'exam', 'income', 'expense', 'transaction'];
if (!in_array($type, $validTypes, true)) {
    $type = 'all';
}

try {
    if ($action === 'cards') {
        echo json_encode(['success' => true, 'cards' => oda_get_cards($pdo, $yearStart, $session)]);
        exit;
    }

    if ($action === 'table') {
        $result = oda_get_table($pdo, $yearStart, $session, $type, $search, $perPage, $offset, $page);
        echo json_encode($result);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
} catch (Exception $e) {
    error_log('Old Data Archive endpoint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

function oda_fetch_count(PDO $pdo, string $sql, array $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return (int)($value === false ? 0 : $value);
}

function oda_get_cards(PDO $pdo, int $yearStart, string $session) {
    // Exact queries required in prompt (prepared statements everywhere).
    $cards = [];

    $cards['students'] = oda_fetch_count($pdo, "SELECT COUNT(*) FROM students WHERE YEAR(created_at) = ?", [$yearStart]);
    $cards['attendance'] = oda_fetch_count($pdo, "SELECT COUNT(*) FROM student_attendance WHERE YEAR(attendance_date) = ?", [$yearStart]);
    $cards['fee'] = oda_fetch_count($pdo, "SELECT COUNT(*) FROM fee_collections WHERE academic_year = ?", [$session]);
    $cards['exam'] = oda_fetch_count($pdo, "SELECT COUNT(*) FROM exam_marks WHERE YEAR(created_at) = ?", [$yearStart]);

    $incomeCount = oda_fetch_count($pdo, "SELECT COUNT(*) FROM income WHERE YEAR(created_at) = ?", [$yearStart]);
    $expenseCount = oda_fetch_count($pdo, "SELECT COUNT(*) FROM expenses WHERE YEAR(created_at) = ?", [$yearStart]);
    $txCount = oda_fetch_count($pdo, "SELECT COUNT(*) FROM accounts_transactions WHERE YEAR(transaction_date) = ?", [$yearStart]);
    $cards['financial'] = $incomeCount + $expenseCount + $txCount;

    return $cards;
}

function oda_like_param(string $search) {
    return '%' . $search . '%';
}

function oda_get_table(PDO $pdo, int $yearStart, string $session, string $type, string $search, int $limit, int $offset, int $page) {
    $clauses = [];
    $params = [];
    $countTotal = 0;
    $searchLike = $search !== '' ? oda_like_param($search) : null;

    // Build SELECT blocks based on type filter
    if ($type === 'all' || $type === 'student') {
        $sql = "
            SELECT
              'student' AS type,
              CONCAT(first_name,' ',last_name) AS name_ref,
              class AS class,
              status AS amount_status,
              DATE(created_at) AS date
            FROM students
            WHERE YEAR(created_at) = ?
        ";
        $blockParams = [$yearStart];
        if ($searchLike) {
            $sql .= " AND CONCAT(first_name,' ',last_name) LIKE ? ";
            $blockParams[] = $searchLike;
        }
        $clauses[] = $sql;
        $countTotal += oda_fetch_count($pdo, "SELECT COUNT(*) FROM students WHERE YEAR(created_at) = ?" . ($searchLike ? " AND CONCAT(first_name,' ',last_name) LIKE ?" : ""), $blockParams);
        array_push($params, ...$blockParams);
    }

    if ($type === 'all' || $type === 'fee') {
        $sql = "
            SELECT
              'fee' AS type,
              CONCAT(s.first_name,' ',s.last_name) AS name_ref,
              s.class AS class,
              CONCAT('Rs.',fc.amount,' - ',fc.status) AS amount_status,
              DATE(COALESCE(fc.payment_date, fc.created_at)) AS date
            FROM fee_collections fc
            JOIN students s ON fc.student_id = s.id
            WHERE fc.academic_year = ?
        ";
        $blockParams = [$session];
        if ($searchLike) {
            $sql .= " AND (CONCAT(s.first_name,' ',s.last_name) LIKE ? OR fc.fee_type LIKE ?) ";
            $blockParams[] = $searchLike;
            $blockParams[] = $searchLike;
        }
        $clauses[] = $sql;
        $countTotal += oda_fetch_count(
            $pdo,
            "SELECT COUNT(*) FROM fee_collections fc JOIN students s ON fc.student_id = s.id WHERE fc.academic_year = ?"
                . ($searchLike ? " AND (CONCAT(s.first_name,' ',s.last_name) LIKE ? OR fc.fee_type LIKE ?)" : ""),
            $blockParams
        );
        array_push($params, ...$blockParams);
    }

    if ($type === 'all' || $type === 'attendance') {
        $sql = "
            SELECT
              'attendance' AS type,
              CONCAT(s.first_name,' ',s.last_name) AS name_ref,
              s.class AS class,
              sa.status AS amount_status,
              sa.attendance_date AS date
            FROM student_attendance sa
            JOIN students s ON sa.student_id = s.id
            WHERE YEAR(sa.attendance_date) = ?
        ";
        $blockParams = [$yearStart];
        if ($searchLike) {
            $sql .= " AND CONCAT(s.first_name,' ',s.last_name) LIKE ? ";
            $blockParams[] = $searchLike;
        }
        $clauses[] = $sql;
        $countTotal += oda_fetch_count(
            $pdo,
            "SELECT COUNT(*) FROM student_attendance sa JOIN students s ON sa.student_id = s.id WHERE YEAR(sa.attendance_date) = ?"
                . ($searchLike ? " AND CONCAT(s.first_name,' ',s.last_name) LIKE ?" : ""),
            $blockParams
        );
        array_push($params, ...$blockParams);
    }

    if ($type === 'all' || $type === 'exam') {
        $sql = "
            SELECT
              'exam' AS type,
              CONCAT(s.first_name,' ',s.last_name) AS name_ref,
              em.class AS class,
              CONCAT(em.obtained_marks,'/',em.total_marks,' (',em.grade,')') AS amount_status,
              DATE(em.created_at) AS date
            FROM exam_marks em
            JOIN students s ON em.student_id = s.id
            WHERE YEAR(em.created_at) = ?
        ";
        $blockParams = [$yearStart];
        if ($searchLike) {
            $sql .= " AND CONCAT(s.first_name,' ',s.last_name) LIKE ? ";
            $blockParams[] = $searchLike;
        }
        $clauses[] = $sql;
        $countTotal += oda_fetch_count(
            $pdo,
            "SELECT COUNT(*) FROM exam_marks em JOIN students s ON em.student_id = s.id WHERE YEAR(em.created_at) = ?"
                . ($searchLike ? " AND CONCAT(s.first_name,' ',s.last_name) LIKE ?" : ""),
            $blockParams
        );
        array_push($params, ...$blockParams);
    }

    if ($type === 'all' || $type === 'income') {
        $sql = "
            SELECT
              'income' AS type,
              source AS name_ref,
              campus AS class,
              CONCAT('Rs.',amount) AS amount_status,
              DATE(created_at) AS date
            FROM income
            WHERE YEAR(created_at) = ?
        ";
        $blockParams = [$yearStart];
        if ($searchLike) {
            $sql .= " AND (source LIKE ? OR description LIKE ?) ";
            $blockParams[] = $searchLike;
            $blockParams[] = $searchLike;
        }
        $clauses[] = $sql;
        $countTotal += oda_fetch_count(
            $pdo,
            "SELECT COUNT(*) FROM income WHERE YEAR(created_at) = ?"
                . ($searchLike ? " AND (source LIKE ? OR description LIKE ?)" : ""),
            $blockParams
        );
        array_push($params, ...$blockParams);
    }

    if ($type === 'all' || $type === 'expense') {
        $sql = "
            SELECT
              'expense' AS type,
              category AS name_ref,
              campus AS class,
              CONCAT('Rs.',amount,' - ',status) AS amount_status,
              DATE(created_at) AS date
            FROM expenses
            WHERE YEAR(created_at) = ?
        ";
        $blockParams = [$yearStart];
        if ($searchLike) {
            $sql .= " AND (category LIKE ? OR description LIKE ?) ";
            $blockParams[] = $searchLike;
            $blockParams[] = $searchLike;
        }
        $clauses[] = $sql;
        $countTotal += oda_fetch_count(
            $pdo,
            "SELECT COUNT(*) FROM expenses WHERE YEAR(created_at) = ?"
                . ($searchLike ? " AND (category LIKE ? OR description LIKE ?)" : ""),
            $blockParams
        );
        array_push($params, ...$blockParams);
    }

    if ($type === 'all' || $type === 'transaction') {
        $sql = "
            SELECT
              'transaction' AS type,
              category AS name_ref,
              transaction_type AS class,
              CONCAT('Rs.',amount,' (',payment_method,')') AS amount_status,
              transaction_date AS date
            FROM accounts_transactions
            WHERE YEAR(transaction_date) = ?
        ";
        $blockParams = [$yearStart];
        if ($searchLike) {
            $sql .= " AND (category LIKE ? OR payment_method LIKE ?) ";
            $blockParams[] = $searchLike;
            $blockParams[] = $searchLike;
        }
        $clauses[] = $sql;
        $countTotal += oda_fetch_count(
            $pdo,
            "SELECT COUNT(*) FROM accounts_transactions WHERE YEAR(transaction_date) = ?"
                . ($searchLike ? " AND (category LIKE ? OR payment_method LIKE ?)" : ""),
            $blockParams
        );
        array_push($params, ...$blockParams);
    }

    if (!$clauses) {
        return [
            'success' => true,
            'records' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $limit,
        ];
    }

    // Final UNION query
    $sql = '(' . implode(') UNION ALL (', $clauses) . ') ORDER BY date DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // XSS safety: the page escapes; still normalize output types
    foreach ($rows as &$r) {
        $r['type'] = (string)($r['type'] ?? '');
        $r['name_ref'] = (string)($r['name_ref'] ?? '');
        $r['class'] = (string)($r['class'] ?? '');
        $r['amount_status'] = (string)($r['amount_status'] ?? '');
        $r['date'] = (string)($r['date'] ?? '');
    }

    return [
        'success' => true,
        'records' => $rows,
        'total' => $countTotal,
        'page' => $page,
        'perPage' => $limit,
    ];
}

