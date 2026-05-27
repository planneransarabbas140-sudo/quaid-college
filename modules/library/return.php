<?php
// File: modules/library/return.php
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
    && columnExists($db, 'library_books', 'available_copies')
    && columnExists($db, 'library_issues', 'actual_return')
    && columnExists($db, 'library_issues', 'fine_amount');
$finePerDay = 10;
$selectedIssueId = (int)($_GET['issue_id'] ?? $_POST['issue_id'] ?? 0);

function lib_return_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function lib_return_money($amount) {
    return 'PKR ' . number_format((float)$amount, 2);
}

function lib_return_status(PDO $db, $status) {
    $status = strtolower((string)$status);
    try {
        $stmt = $db->query("SHOW COLUMNS FROM library_issues LIKE 'status'");
        $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $type = (string)($column['Type'] ?? '');
        if (stripos($type, 'enum') === 0 && strpos($type, "'" . ucfirst($status) . "'") !== false && strpos($type, "'" . $status . "'") === false) {
            return ucfirst($status);
        }
    } catch (Exception $e) {
        return $status;
    }
    return $status;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        if (!$hasCirculationSchema) {
            throw new Exception('Library circulation tables are missing. Please run database/library_circulation.sql first.');
        }

        $issueId = (int)($_POST['issue_id'] ?? 0);
        $actualReturn = $_POST['actual_return'] ?? date('Y-m-d');
        $dateObj = DateTime::createFromFormat('Y-m-d', $actualReturn);
        if ($issueId <= 0 || !$dateObj || $dateObj->format('Y-m-d') !== $actualReturn) {
            throw new Exception('Please select a valid issued book and return date.');
        }

        $db->beginTransaction();
        $stmt = $db->prepare("SELECT * FROM library_issues WHERE id = ? AND LOWER(status) = 'issued' FOR UPDATE");
        $stmt->execute([$issueId]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$issue) {
            throw new Exception('Issued book record was not found.');
        }

        $overdueDays = max(0, (int)floor((strtotime($actualReturn) - strtotime($issue['return_date'])) / 86400));
        $fine = $overdueDays * $finePerDay;
        $stmt = $db->prepare('UPDATE library_issues SET actual_return = ?, fine_amount = ?, status = ? WHERE id = ?');
        $stmt->execute([$actualReturn, $fine, lib_return_status($db, 'returned'), $issueId]);

        $sets = ['available_copies = available_copies + 1'];
        if (columnExists($db, 'library_books', 'available')) {
            $sets[] = 'available = available + 1';
        }
        if (columnExists($db, 'library_books', 'status')) {
            $sets[] = "status = 'Available'";
        }
        $db->prepare('UPDATE library_books SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute([(int)$issue['book_id']]);
        $db->commit();

        setFlashMessage('success', 'Book returned successfully.' . ($fine > 0 ? ' Fine: ' . lib_return_money($fine) : ' No fine.'));
        redirect('return.php');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('return.php' . ($selectedIssueId ? '?issue_id=' . $selectedIssueId : ''));
    }
}

$issued = [];
$selectedIssue = null;
if ($hasCirculationSchema) {
    $issued = $db->query("
        SELECT li.*, lb.title, lb.author, lb.book_id AS library_code,
               CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), NULLIF(s.student_id, ''), CONCAT('STD-', s.id)) AS student_code,
               GREATEST(DATEDIFF(CURDATE(), li.return_date), 0) AS overdue_days
        FROM library_issues li
        JOIN library_books lb ON lb.id = li.book_id
        JOIN students s ON s.id = li.student_id
        WHERE LOWER(li.status) = 'issued'
        ORDER BY li.return_date ASC, li.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($issued as $row) {
        if ((int)$row['id'] === $selectedIssueId) {
            $selectedIssue = $row;
            break;
        }
    }
}

$page_title = 'Return Books';
include '../../includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Library</a>
        <h2 class="page-title mb-1"><i class="fas fa-undo me-2" style="color:var(--teal);"></i>Return Books</h2>
        <div class="text-muted">Return issued books and calculate overdue fines automatically.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="issue.php" class="btn btn-outline-primary"><i class="fas fa-hand-holding me-1"></i>Issue</a>
        <a href="fines.php" class="btn btn-outline-primary"><i class="fas fa-coins me-1"></i>Fines</a>
    </div>
</div>

<?php displayFlashMessage(); ?>
<?php if (!$hasCirculationSchema): ?><div class="alert alert-warning">Library circulation tables are missing. Run <code>database/library_circulation.sql</code>.</div><?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Process Return</h5></div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end">
            <?= csrfTokenInput() ?>
            <div class="col-lg-7">
                <label class="form-label small fw-bold">Issued Book</label>
                <select name="issue_id" class="form-select" required>
                    <option value="">Select issued book...</option>
                    <?php foreach ($issued as $row): ?>
                        <option value="<?= (int)$row['id'] ?>" <?= $selectedIssueId === (int)$row['id'] ? 'selected' : '' ?>>
                            <?= lib_return_h(($row['library_code'] ?: 'Book #' . $row['book_id']) . ' - ' . $row['title'] . ' | ' . $row['student_code'] . ' - ' . $row['student_name'] . ' | Due: ' . date('d M Y', strtotime($row['return_date']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label small fw-bold">Actual Return</label><input type="date" name="actual_return" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100" <?= (!$issued || !$hasIssues) ? 'disabled' : '' ?>>Return</button></div>
        </form>
        <?php if ($selectedIssue): ?>
            <div class="alert alert-info mt-3 mb-0">
                Overdue days today: <strong><?= (int)$selectedIssue['overdue_days'] ?></strong>.
                Estimated fine: <strong><?= lib_return_money(((int)$selectedIssue['overdue_days']) * $finePerDay) ?></strong>.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Issued / Overdue Books</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $issued ? 'datatable' : '' ?>">
                <thead class="table-light"><tr><th>Book</th><th>Student</th><th>Issue Date</th><th>Due Date</th><th>Fine Today</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php if (!$issued): ?><tr><td colspan="7" class="text-center text-muted py-4">No issued books pending return.</td></tr><?php endif; ?>
                    <?php foreach ($issued as $row): ?>
                        <?php $fine = ((int)$row['overdue_days']) * $finePerDay; ?>
                        <tr>
                            <td><strong><?= lib_return_h($row['title']) ?></strong><div class="small text-muted"><?= lib_return_h($row['library_code'] ?: 'Book #' . $row['book_id']) ?> | <?= lib_return_h($row['author']) ?></div></td>
                            <td><?= lib_return_h($row['student_code'] . ' - ' . $row['student_name']) ?></td>
                            <td><?= lib_return_h(date('d M Y', strtotime($row['issue_date']))) ?></td>
                            <td class="<?= (int)$row['overdue_days'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= lib_return_h(date('d M Y', strtotime($row['return_date']))) ?></td>
                            <td class="<?= $fine > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= lib_return_money($fine) ?></td>
                            <td><span class="badge <?= (int)$row['overdue_days'] > 0 ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= (int)$row['overdue_days'] > 0 ? 'Overdue' : 'Issued' ?></span></td>
                            <td class="text-end"><a href="return.php?issue_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success">Select</a></td>
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
</style>

<?php include '../../includes/footer.php'; ?>
