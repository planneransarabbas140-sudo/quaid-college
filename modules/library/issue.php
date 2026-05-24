<?php
/**
 * Student library issue/history page.
 * Keeps old links like modules/library/issue.php?student_id=4 working.
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$db = (new Database())->getConnection();
$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);

if ($studentId <= 0) {
    setFlashMessage('error', 'Invalid student selected.');
    redirect('../student_profile/index.php');
}

$studentStmt = $db->prepare("
    SELECT id, student_id, registration_number, roll_number, first_name, last_name, class, section
    FROM students
    WHERE id = ?
    LIMIT 1
");
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    setFlashMessage('error', 'Student record not found.');
    redirect('../student_profile/index.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'issue_book') {
            $bookId = (int)($_POST['book_id'] ?? 0);
            $returnDate = trim((string)($_POST['return_date'] ?? ''));

            if ($bookId <= 0 || $returnDate === '') {
                throw new Exception('Please select a book and return date.');
            }

            $bookStmt = $db->prepare("SELECT id, available_copies FROM library_books WHERE id = ? LIMIT 1");
            $bookStmt->execute([$bookId]);
            $book = $bookStmt->fetch(PDO::FETCH_ASSOC);

            if (!$book || (int)$book['available_copies'] <= 0) {
                throw new Exception('Selected book is not available.');
            }

            $db->beginTransaction();
            $db->prepare("
                INSERT INTO library_issues (book_id, student_id, issue_date, return_date, status)
                VALUES (?, ?, ?, ?, 'Issued')
            ")->execute([$bookId, $studentId, date('Y-m-d'), $returnDate]);

            $db->prepare("
                UPDATE library_books
                SET available_copies = available_copies - 1,
                    available = GREATEST(available - 1, 0),
                    status = IF(available_copies - 1 <= 0, 'Issued', 'Available')
                WHERE id = ?
            ")->execute([$bookId]);
            $db->commit();

            setFlashMessage('success', 'Book issued successfully.');
            redirect('issue.php?student_id=' . $studentId);
        }

        if ($action === 'return_book') {
            $issueId = (int)($_POST['issue_id'] ?? 0);
            $issueStmt = $db->prepare("
                SELECT *
                FROM library_issues
                WHERE id = ? AND student_id = ? AND status = 'Issued'
                LIMIT 1
            ");
            $issueStmt->execute([$issueId, $studentId]);
            $issue = $issueStmt->fetch(PDO::FETCH_ASSOC);

            if (!$issue) {
                throw new Exception('Issued book record not found.');
            }

            $today = date('Y-m-d');
            $fine = 0;
            if ($today > $issue['return_date']) {
                $fine = (int)ceil((strtotime($today) - strtotime($issue['return_date'])) / 86400) * 10;
            }

            $db->beginTransaction();
            $db->prepare("
                UPDATE library_issues
                SET actual_return = ?, fine_amount = ?, status = 'Returned'
                WHERE id = ?
            ")->execute([$today, $fine, $issueId]);

            $db->prepare("
                UPDATE library_books
                SET available_copies = available_copies + 1,
                    available = available + 1,
                    status = 'Available'
                WHERE id = ?
            ")->execute([(int)$issue['book_id']]);
            $db->commit();

            setFlashMessage('success', 'Book returned successfully.' . ($fine > 0 ? ' Fine: PKR ' . number_format($fine, 2) : ''));
            redirect('issue.php?student_id=' . $studentId);
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Library Issue Error: ' . $e->getMessage());
        setFlashMessage('error', $e->getMessage());
        redirect('issue.php?student_id=' . $studentId);
    }
}

$booksStmt = $db->query("
    SELECT id, book_id, title, author, available_copies
    FROM library_books
    WHERE available_copies > 0
    ORDER BY title ASC
");
$books = $booksStmt->fetchAll(PDO::FETCH_ASSOC);

$historyStmt = $db->prepare("
    SELECT li.*, lb.title, lb.author, lb.book_id AS library_code,
           DATEDIFF(CURDATE(), li.return_date) AS overdue_days
    FROM library_issues li
    JOIN library_books lb ON lb.id = li.book_id
    WHERE li.student_id = ?
    ORDER BY li.issue_date DESC, li.id DESC
");
$historyStmt->execute([$studentId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$displayCode = $student['roll_number'] ?: ($student['registration_number'] ?: ($student['student_id'] ?: ('STD-' . $student['id'])));
$studentName = trim($student['first_name'] . ' ' . $student['last_name']);
$page_title = 'Library History';
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="page-title mb-1"><i class="fas fa-book-reader me-2" style="color: var(--teal);"></i>Library History</h2>
            <div class="text-muted">
                <?= htmlspecialchars($studentName) ?> | <?= htmlspecialchars($displayCode) ?> | <?= htmlspecialchars(($student['class'] ?? '') . (($student['section'] ?? '') ? ' - ' . $student['section'] : '')) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="../student_profile/view.php?id=<?= (int)$studentId ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#issueBookModal" <?= empty($books) ? 'disabled' : '' ?>>
                <i class="fas fa-plus me-1"></i> Issue Book
            </button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white">
            <h5 class="mb-0 fw-bold">Issued & Returned Books</h5>
        </div>
        <div class="card-body">
            <?php if (empty($history)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-book-open fa-3x mb-3 opacity-50"></i>
                    <h5 class="fw-bold">No library history yet</h5>
                    <p class="mb-0">Issue a book to this student using the button above.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Returned</th>
                                <th>Fine</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <?php
                                    $isIssued = $row['status'] === 'Issued';
                                    $isOverdue = $isIssued && (int)$row['overdue_days'] > 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['title']) ?></strong>
                                        <div class="small text-muted"><?= htmlspecialchars($row['library_code'] ?: 'Book #' . $row['book_id']) ?> | <?= htmlspecialchars($row['author']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime($row['issue_date']))) ?></td>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime($row['return_date']))) ?></td>
                                    <td><?= $row['actual_return'] ? htmlspecialchars(date('d M Y', strtotime($row['actual_return']))) : '-' ?></td>
                                    <td>PKR <?= number_format((float)$row['fine_amount'], 2) ?></td>
                                    <td>
                                        <?php if ($isIssued): ?>
                                            <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-warning' ?>"><?= $isOverdue ? 'Overdue' : 'Issued' ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isIssued): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Return this book?');">
                                                <input type="hidden" name="action" value="return_book">
                                                <input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
                                                <input type="hidden" name="issue_id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Return</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Done</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="issueBookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-hand-holding me-2"></i>Issue Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="issue_book">
                <input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Student</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($studentName . ' (' . $displayCode . ')') ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Book</label>
                        <select name="book_id" class="form-select" required>
                            <option value="">Choose book...</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?= (int)$book['id'] ?>">
                                    <?= htmlspecialchars(($book['book_id'] ?: 'Book #' . $book['id']) . ' - ' . $book['title'] . ' (' . $book['available_copies'] . ' available)') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Return Date</label>
                        <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                    </div>
                    <div class="alert alert-warning mb-0">
                        Fine of PKR 10/day will apply after the due date.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
