<?php
// File: modules/library/fines.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'librarian', 'library']);

$db = (new Database())->getConnection();
$hasBooks = tableExists($db, 'library_books');
$hasIssues = tableExists($db, 'library_issues');
$hasStudents = tableExists($db, 'students');
$hasCirculationSchema = $hasBooks && $hasIssues && $hasStudents
    && columnExists($db, 'library_books', 'book_id')
    && columnExists($db, 'library_issues', 'actual_return')
    && columnExists($db, 'library_issues', 'fine_amount');
$finePerDay = 10;

function lib_fines_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function lib_fines_money($amount) {
    return 'PKR ' . number_format((float)$amount, 2);
}

$overdueRows = [];
$fineRows = [];
$totalEstimated = 0;
$totalRecorded = 0;
if ($hasCirculationSchema) {
    $overdueRows = $db->query("
        SELECT li.*, lb.title, lb.author, lb.book_id AS library_code,
               CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), NULLIF(s.student_id, ''), CONCAT('STD-', s.id)) AS student_code,
               GREATEST(DATEDIFF(CURDATE(), li.return_date), 0) AS overdue_days
        FROM library_issues li
        JOIN library_books lb ON lb.id = li.book_id
        JOIN students s ON s.id = li.student_id
        WHERE LOWER(li.status) = 'issued' AND li.return_date < CURDATE()
        ORDER BY li.return_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $fineRows = $db->query("
        SELECT li.*, lb.title, lb.book_id AS library_code,
               CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), NULLIF(s.student_id, ''), CONCAT('STD-', s.id)) AS student_code
        FROM library_issues li
        JOIN library_books lb ON lb.id = li.book_id
        JOIN students s ON s.id = li.student_id
        WHERE COALESCE(li.fine_amount, 0) > 0
        ORDER BY li.actual_return DESC, li.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($overdueRows as $row) {
        $totalEstimated += ((int)$row['overdue_days']) * $finePerDay;
    }
    foreach ($fineRows as $row) {
        $totalRecorded += (float)$row['fine_amount'];
    }
}

$page_title = 'Library Fines';
include '../../includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Library</a>
        <h2 class="page-title mb-1"><i class="fas fa-coins me-2" style="color:var(--teal);"></i>Library Fines</h2>
        <div class="text-muted">Review overdue books and fines recorded on returned books.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="issue.php" class="btn btn-outline-primary"><i class="fas fa-hand-holding me-1"></i>Issue</a>
        <a href="return.php" class="btn btn-outline-primary"><i class="fas fa-undo me-1"></i>Returns</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<?php if (!$hasCirculationSchema): ?><div class="alert alert-warning">Library circulation tables are missing. Run <code>database/library_circulation.sql</code>.</div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Overdue Books</div><h4 class="fw-bold text-danger mb-0"><?= count($overdueRows) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Estimated Fine</div><h4 class="fw-bold text-warning mb-0"><?= lib_fines_money($totalEstimated) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Recorded Fine</div><h4 class="fw-bold text-primary mb-0"><?= lib_fines_money($totalRecorded) ?></h4></div></div></div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Overdue Books</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $overdueRows ? 'datatable' : '' ?>">
                <thead class="table-light"><tr><th>Book</th><th>Student</th><th>Due Date</th><th class="text-end">Overdue Days</th><th class="text-end">Estimated Fine</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php if (!$overdueRows): ?><tr><td colspan="6" class="text-center text-muted py-4">No overdue books found.</td></tr><?php endif; ?>
                    <?php foreach ($overdueRows as $row): ?>
                        <?php $fine = ((int)$row['overdue_days']) * $finePerDay; ?>
                        <tr>
                            <td><strong><?= lib_fines_h($row['title']) ?></strong><div class="small text-muted"><?= lib_fines_h($row['library_code'] ?: 'Book #' . $row['book_id']) ?> | <?= lib_fines_h($row['author']) ?></div></td>
                            <td><?= lib_fines_h($row['student_code'] . ' - ' . $row['student_name']) ?></td>
                            <td class="text-danger fw-bold"><?= lib_fines_h(date('d M Y', strtotime($row['return_date']))) ?></td>
                            <td class="text-end fw-bold"><?= (int)$row['overdue_days'] ?></td>
                            <td class="text-end text-danger fw-bold"><?= lib_fines_money($fine) ?></td>
                            <td class="text-end"><a href="return.php?issue_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success">Return</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Recorded Fines</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $fineRows ? 'datatable' : '' ?>">
                <thead class="table-light"><tr><th>Book</th><th>Student</th><th>Due Date</th><th>Returned</th><th class="text-end">Fine</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (!$fineRows): ?><tr><td colspan="6" class="text-center text-muted py-4">No recorded fines found.</td></tr><?php endif; ?>
                    <?php foreach ($fineRows as $row): ?>
                        <tr>
                            <td><?= lib_fines_h(($row['library_code'] ?: 'Book #' . $row['book_id']) . ' - ' . $row['title']) ?></td>
                            <td><?= lib_fines_h($row['student_code'] . ' - ' . $row['student_name']) ?></td>
                            <td><?= lib_fines_h(date('d M Y', strtotime($row['return_date']))) ?></td>
                            <td><?= !empty($row['actual_return']) ? lib_fines_h(date('d M Y', strtotime($row['actual_return']))) : '-' ?></td>
                            <td class="text-end text-danger fw-bold"><?= lib_fines_money($row['fine_amount']) ?></td>
                            <td><span class="badge <?= strtolower((string)$row['status']) === 'returned' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= lib_fines_h(ucfirst(strtolower((string)$row['status']))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    .btn-outline-primary { color:var(--navy); border-color:var(--teal); }
    @media print { #sidebar, .topbar, .sidebar-backdrop, .btn { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; } }
</style>

<?php include '../../includes/footer.php'; ?>
