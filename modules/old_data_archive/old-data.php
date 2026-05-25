<?php
// File: modules/old_data_archive/old-data.php
// Old Data Archive (Session-based)

$page_title = 'Old Data Archive';
require_once __DIR__ . '/../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$pdo = $database->getConnection();

$csrf_token = getCsrfToken();

// As required: selector options (derive presence from DB, but keep exact labels)
$allowedSessions = ['2023-24', '2024-25', '2025-26', '2026-27'];

function oda_normalize_session_label($raw) {
    $raw = trim((string)$raw);
    if (!preg_match('/^\d{4}-\d{2}$/', $raw)) {
        return null;
    }
    return $raw;
}

function oda_session_from_year($year) {
    $year = (int)$year;
    if ($year < 2000 || $year > 2100) {
        return null;
    }
    return sprintf('%d-%02d', $year, ($year + 1) % 100);
}

// Derive sessions from DB (requested sources)
$derivedSessions = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT academic_year
        FROM fee_collections
        WHERE academic_year IS NOT NULL AND academic_year <> ''
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $label) {
        $label = oda_normalize_session_label($label);
        if ($label) {
            $derivedSessions[$label] = true;
        }
    }

    $stmt = $pdo->query("
        SELECT DISTINCT YEAR(created_at) as year FROM students
        UNION SELECT DISTINCT YEAR(attendance_date) as year FROM student_attendance
        UNION SELECT DISTINCT YEAR(created_at) as year FROM exam_marks
        UNION SELECT DISTINCT YEAR(created_at) as year FROM income
        UNION SELECT DISTINCT YEAR(created_at) as year FROM expenses
        UNION SELECT DISTINCT YEAR(transaction_date) as year FROM accounts_transactions
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $year) {
        $label = oda_session_from_year($year);
        if ($label) {
            $derivedSessions[$label] = true;
        }
    }
} catch (Exception $e) {
    error_log('Old Data Archive sessions load failed: ' . $e->getMessage());
}

$defaultSession = '2026-27';
$selectedSession = oda_normalize_session_label($_GET['session'] ?? '') ?: $defaultSession;
if (!in_array($selectedSession, $allowedSessions, true)) {
    $selectedSession = $defaultSession;
}

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>modules/old_data_archive/old-data.css?v=2">

<div class="container-fluid px-4 py-4 old-data-archive" data-base-url="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Old Data Archive</h2>
            <p class="text-muted mb-0">View, filter, and download historical session-based school data</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <label for="sessionSelect" class="mb-0 fw-semibold text-muted">Academic Year:</label>
            <select id="sessionSelect" class="form-select form-select-sm old-data-select" style="min-width: 160px;">
                <?php foreach ($allowedSessions as $session): ?>
                    <option value="<?= htmlspecialchars($session) ?>" <?= $session === $selectedSession ? 'selected' : '' ?>>
                        <?= htmlspecialchars($session) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button id="downloadBtn" class="btn btn-primary btn-sm old-data-download">
                <i class="fas fa-download me-2"></i>Download Session Data
            </button>
        </div>
    </div>

    <div class="cards-grid mb-4">
        <div class="stat-card border-student">
            <div class="stat-label">Students</div>
            <div class="card-value" data-card="students"><span class="skeleton skeleton-lg"></span></div>
        </div>
        <div class="stat-card border-attendance">
            <div class="stat-label">Attendance Records</div>
            <div class="card-value" data-card="attendance"><span class="skeleton skeleton-lg"></span></div>
        </div>
        <div class="stat-card border-fee">
            <div class="stat-label">Fee Records</div>
            <div class="card-value" data-card="fee"><span class="skeleton skeleton-lg"></span></div>
        </div>
        <div class="stat-card border-transaction">
            <div class="stat-label">Income + Expense</div>
            <div class="card-value" data-card="financial"><span class="skeleton skeleton-lg"></span></div>
        </div>
        <div class="stat-card border-exam">
            <div class="stat-label">Exam Data</div>
            <div class="card-value" data-card="exam"><span class="skeleton skeleton-lg"></span></div>
        </div>
    </div>

    <div class="search-filters mb-3">
        <div class="d-flex flex-column flex-lg-row gap-3 align-items-stretch align-items-lg-center">
            <input type="text" id="searchInput" class="form-control old-data-search" placeholder="Search by name, source, or category...">
            <div class="type-tabs">
                <button class="filter-tab active" data-type="all">All</button>
                <button class="filter-tab" data-type="student">Students</button>
                <button class="filter-tab" data-type="attendance">Attendance</button>
                <button class="filter-tab" data-type="fee">Fee</button>
                <button class="filter-tab" data-type="exam">Exam</button>
                <button class="filter-tab" data-type="income">Income</button>
                <button class="filter-tab" data-type="expense">Expense</button>
                <button class="filter-tab" data-type="transaction">Transaction</button>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive old-data-table-wrap">
            <table class="table old-data-table mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name / Ref</th>
                        <th>Class</th>
                        <th>Amount / Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
        <div class="table-footer">
            <div id="recordsInfo" class="text-muted small">Showing 0 records</div>
            <div class="d-flex gap-2">
                <button id="prevBtn" class="btn btn-outline-secondary btn-sm" disabled>← Previous</button>
                <button id="nextBtn" class="btn btn-outline-secondary btn-sm">Next →</button>
            </div>
        </div>
    </div>
</div>

<script>
    const CONFIG = {
        csrfToken: <?= json_encode($csrf_token) ?>,
        currentSession: <?= json_encode($selectedSession) ?>,
        baseUrl: <?= json_encode(BASE_URL) ?>,
    };

    let state = {
        currentPage: 0,
        currentSession: CONFIG.currentSession,
        currentFilter: 'all',
        searchQuery: '',
        recordsPerPage: 50,
        totalRecords: 0,
    };
</script>

<script src="<?= BASE_URL ?>modules/old_data_archive/old-data.js?v=2"></script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
