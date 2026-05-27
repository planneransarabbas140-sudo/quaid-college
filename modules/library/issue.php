<?php
// File: modules/library/issue.php
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
    && columnExists($db, 'library_books', 'available_copies')
    && columnExists($db, 'library_issues', 'actual_return')
    && columnExists($db, 'library_issues', 'fine_amount');
$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);

function lib_issue_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function lib_issue_valid_date($date) {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function lib_issue_status(PDO $db, $status) {
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
            throw new Exception('Library issue tables are missing. Please run database/library_circulation.sql first.');
        }

        $bookId = (int)($_POST['book_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
        $dueDate = $_POST['due_date'] ?? '';

        if ($bookId <= 0 || $studentId <= 0) {
            throw new Exception('Please select a book and student.');
        }
        if (!lib_issue_valid_date($issueDate) || !lib_issue_valid_date($dueDate)) {
            throw new Exception('Please select valid issue and due dates.');
        }
        if (strtotime($dueDate) < strtotime($issueDate)) {
            throw new Exception('Due date cannot be before issue date.');
        }

        $studentCheck = $db->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
        $studentCheck->execute([$studentId]);
        if (!$studentCheck->fetchColumn()) {
            throw new Exception('Selected student was not found.');
        }

        $db->beginTransaction();
        $bookStmt = $db->prepare('SELECT id, title, available_copies FROM library_books WHERE id = ? FOR UPDATE');
        $bookStmt->execute([$bookId]);
        $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
        if (!$book || (int)$book['available_copies'] <= 0) {
            throw new Exception('Selected book has no copies available.');
        }

        $stmt = $db->prepare('INSERT INTO library_issues (book_id, student_id, issue_date, return_date, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$bookId, $studentId, $issueDate, $dueDate, lib_issue_status($db, 'issued')]);

        $sets = ['available_copies = GREATEST(available_copies - 1, 0)'];
        if (columnExists($db, 'library_books', 'available')) {
            $sets[] = 'available = GREATEST(available - 1, 0)';
        }
        if (columnExists($db, 'library_books', 'status')) {
            $sets[] = "status = IF(available_copies - 1 <= 0, 'Issued', 'Available')";
        }
        $db->prepare('UPDATE library_books SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute([$bookId]);
        $db->commit();

        setFlashMessage('success', 'Book issued successfully.');
        redirect('issue.php' . ($studentId ? '?student_id=' . $studentId : ''));
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('issue.php' . ($studentId ? '?student_id=' . $studentId : ''));
    }
}

$selectedStudent = null;
if ($studentId > 0 && $hasStudents) {
    $stmt = $db->prepare("
        SELECT id, student_id, registration_number, roll_number, first_name, last_name, class, section
        FROM students
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $selectedStudent = $stmt->fetch(PDO::FETCH_ASSOC);
}

$books = [];
$students = [];
$issued = [];
$overdue = [];
if ($hasCirculationSchema) {
    $books = $db->query("
        SELECT id, book_id, title, author, available_copies
        FROM library_books
        WHERE available_copies > 0
        ORDER BY title ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
if ($hasStudents) {
    $students = $db->query("
        SELECT id, student_id, registration_number, roll_number, first_name, last_name, class, section
        FROM students
        ORDER BY first_name ASC, last_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
if ($hasCirculationSchema) {
    $issuedWhere = ["LOWER(li.status) = 'issued'"];
    $issuedParams = [];
    if ($studentId > 0) {
        $issuedWhere[] = 'li.student_id = ?';
        $issuedParams[] = $studentId;
    }
    $stmt = $db->prepare("
        SELECT li.*, lb.title, lb.author, lb.book_id AS library_code,
               CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), NULLIF(s.student_id, ''), CONCAT('STD-', s.id)) AS student_code,
               DATEDIFF(CURDATE(), li.return_date) AS overdue_days
        FROM library_issues li
        JOIN library_books lb ON lb.id = li.book_id
        JOIN students s ON s.id = li.student_id
        WHERE " . implode(' AND ', $issuedWhere) . "
        ORDER BY li.issue_date DESC, li.id DESC
    ");
    $stmt->execute($issuedParams);
    $issued = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $overdue = array_values(array_filter($issued, static function ($row) {
        return (int)($row['overdue_days'] ?? 0) > 0;
    }));
}

$page_title = 'Issue Books';
include '../../includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <a href="index.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Library</a>
        <h2 class="page-title mb-1"><i class="fas fa-hand-holding me-2" style="color:var(--teal);"></i>Issue Books</h2>
        <div class="text-muted">Issue available books to students and track due dates.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="return.php" class="btn btn-outline-primary"><i class="fas fa-undo me-1"></i>Returns</a>
        <a href="fines.php" class="btn btn-outline-primary"><i class="fas fa-coins me-1"></i>Fines</a>
    </div>
</div>

<?php displayFlashMessage(); ?>
<?php if (!$hasCirculationSchema): ?>
    <div class="alert alert-warning">Library circulation tables are missing. Run <code>database/library_circulation.sql</code>.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Available Titles</div><h4 class="fw-bold mb-0"><?= count($books) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Issued Books</div><h4 class="fw-bold text-warning mb-0"><?= count($issued) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Overdue</div><h4 class="fw-bold text-danger mb-0"><?= count($overdue) ?></h4></div></div></div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Issue Book</h5></div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end">
            <?= csrfTokenInput() ?>
            <div class="col-lg-4">
                <label class="form-label small fw-bold">Book</label>
                <select name="book_id" class="form-select" required>
                    <option value="">Select available book...</option>
                    <?php foreach ($books as $book): ?>
                        <option value="<?= (int)$book['id'] ?>"><?= lib_issue_h(($book['book_id'] ?: 'Book #' . $book['id']) . ' - ' . $book['title'] . ' (' . $book['available_copies'] . ' available)') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label small fw-bold">Student</label>
                <select name="student_id" class="form-select" required>
                    <option value="">Select student...</option>
                    <?php foreach ($students as $student): ?>
                        <?php $code = $student['roll_number'] ?: ($student['registration_number'] ?: ($student['student_id'] ?: 'STD-' . $student['id'])); ?>
                        <option value="<?= (int)$student['id'] ?>" <?= $studentId === (int)$student['id'] ? 'selected' : '' ?>><?= lib_issue_h($code . ' - ' . trim($student['first_name'] . ' ' . $student['last_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small fw-bold">Issue Date</label><input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-2"><label class="form-label small fw-bold">Due Date</label><input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required></div>
            <div class="col-12 text-end"><button class="btn btn-primary" type="submit" <?= (!$hasCirculationSchema || !$books) ? 'disabled' : '' ?>><i class="fas fa-save me-1"></i>Issue Book</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Issued Books</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle <?= $issued ? 'datatable' : '' ?>">
                <thead class="table-light"><tr><th>Book</th><th>Student</th><th>Issue Date</th><th>Due Date</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    <?php if (!$issued): ?><tr><td colspan="6" class="text-center text-muted py-4">No issued books found.</td></tr><?php endif; ?>
                    <?php foreach ($issued as $row): ?>
                        <?php $isOverdue = (int)$row['overdue_days'] > 0; ?>
                        <tr>
                            <td><strong><?= lib_issue_h($row['title']) ?></strong><div class="small text-muted"><?= lib_issue_h($row['library_code'] ?: 'Book #' . $row['book_id']) ?> | <?= lib_issue_h($row['author']) ?></div></td>
                            <td><?= lib_issue_h($row['student_code'] . ' - ' . $row['student_name']) ?></td>
                            <td><?= lib_issue_h(date('d M Y', strtotime($row['issue_date']))) ?></td>
                            <td class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>"><?= lib_issue_h(date('d M Y', strtotime($row['return_date']))) ?></td>
                            <td><span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= $isOverdue ? 'Overdue' : 'Issued' ?></span></td>
                            <td class="text-end"><a href="return.php?issue_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-undo me-1"></i>Return</a></td>
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
