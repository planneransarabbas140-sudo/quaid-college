<?php
/**
 * File: modules/library/index.php
 * Library Management System for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'librarian', 'library']);

$db = (new Database())->getConnection();
$message = '';
$error = '';

// --- HANDLE POST ACTIONS ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_book':
                    // Auto-generate Book ID (LIB-001, etc.)
                    $stmt = $db->query("SELECT MAX(id) as last_id FROM library_books");
                    $last_id = $stmt->fetch()['last_id'] ?? 0;
                    $book_id = 'LIB-' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);

                    $sql = "INSERT INTO library_books (book_id, title, author, isbn, category, publisher, publication_year, total_copies, available_copies, shelf_number, description) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $total_copies = (int)$_POST['total_copies'];
                    $stmt->execute([
                        $book_id,
                        sanitizeInput($_POST['title']),
                        sanitizeInput($_POST['author']),
                        sanitizeInput($_POST['isbn']),
                        $_POST['category'],
                        sanitizeInput($_POST['publisher']),
                        (int)$_POST['publication_year'],
                        $total_copies,
                        $total_copies, // Initially available = total
                        sanitizeInput($_POST['shelf_number']),
                        sanitizeInput($_POST['description'])
                    ]);

                    $purchaseAmount = (float)($_POST['purchase_amount'] ?? 0);
                    if ($purchaseAmount > 0) {
                        recordExpense($db, [
                            'module_name' => 'library',
                            'reference_id' => (int)$db->lastInsertId(),
                            'campus' => sanitizeInput($_POST['campus'] ?? ''),
                            'category' => 'Library Books',
                            'description' => 'Book purchase: ' . sanitizeInput($_POST['title']) . ' (' . $book_id . ')',
                            'amount' => $purchaseAmount,
                            'expense_type' => 'auto',
                            'status' => 'pending',
                            'created_by' => getUserId()
                        ]);
                    }
                    setFlashMessage('success', "Book added successfully with ID: $book_id");
                    break;

                case 'edit_book':
                    $sql = "UPDATE library_books SET title=?, author=?, isbn=?, category=?, publisher=?, publication_year=?, total_copies=?, shelf_number=?, description=? WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $stmt_old = $db->prepare("SELECT total_copies, available_copies FROM library_books WHERE id=?");
                    $stmt_old->execute([$_POST['id']]);
                    $old_data = $stmt_old->fetch();
                    
                    $new_total = (int)$_POST['total_copies'];
                    $diff = $new_total - $old_data['total_copies'];
                    $new_available = $old_data['available_copies'] + $diff;

                    $stmt->execute([
                        sanitizeInput($_POST['title']),
                        sanitizeInput($_POST['author']),
                        sanitizeInput($_POST['isbn']),
                        $_POST['category'],
                        sanitizeInput($_POST['publisher']),
                        (int)$_POST['publication_year'],
                        $new_total,
                        sanitizeInput($_POST['shelf_number']),
                        sanitizeInput($_POST['description']),
                        $_POST['id']
                    ]);
                    
                    $db->prepare("UPDATE library_books SET available_copies = ? WHERE id = ?")->execute([$new_available, $_POST['id']]);
                    setFlashMessage('success', "Book updated successfully.");
                    break;

                case 'delete_book':
                    $stmt = $db->prepare("SELECT COUNT(*) FROM library_issues WHERE book_id=? AND status='Issued'");
                    $stmt->execute([$_POST['id']]);
                    if ($stmt->fetchColumn() > 0) {
                        setFlashMessage('error', "Cannot delete book. It is currently issued to a student.");
                    } else {
                        $stmt = $db->prepare("DELETE FROM library_books WHERE id=?");
                        $stmt->execute([$_POST['id']]);
                        setFlashMessage('success', "Book deleted successfully.");
                    }
                    break;

                case 'issue_book':
                    $book_id_pk = $_POST['book_id'];
                    $student_id = $_POST['student_id'];
                    $return_date = $_POST['return_date'];
                    $issue_date = date('Y-m-d');

                    $stmt = $db->prepare("SELECT available_copies FROM library_books WHERE id=?");
                    $stmt->execute([$book_id_pk]);
                    $available = $stmt->fetchColumn();

                    if ($available > 0) {
                        $db->beginTransaction();
                        $sql = "INSERT INTO library_issues (book_id, student_id, issue_date, return_date, status) VALUES (?, ?, ?, ?, 'Issued')";
                        $db->prepare($sql)->execute([$book_id_pk, $student_id, $issue_date, $return_date]);
                        
                        $db->prepare("UPDATE library_books SET available_copies = available_copies - 1, status = IF(available_copies - 1 = 0, 'Issued', 'Available') WHERE id=?")->execute([$book_id_pk]);
                        
                        $db->commit();
                        setFlashMessage('success', "Book issued successfully.");
                    } else {
                        setFlashMessage('error', "Book is not available for issue.");
                    }
                    break;
            }
            redirect('index.php');
        } catch (Exception $e) {
            setFlashMessage('error', "Error: " . $e->getMessage());
            redirect('index.php');
        }
    }
}

// --- HANDLE GET ACTIONS (Return Book) ---
if (isset($_GET['action']) && $_GET['action'] == 'return') {
    $issue_id = $_GET['issue_id'];
    
    // Get issue details
    $stmt = $db->prepare("SELECT * FROM library_issues WHERE id = ?");
    $stmt->execute([$issue_id]);
    $issue = $stmt->fetch();
    
    if ($issue) {
        $today = date('Y-m-d');
        $return_date = $issue['return_date'];
        $fine = 0;
        
        // Calculate fine if overdue
        if ($today > $return_date) {
            $overdue_days = (strtotime($today) - strtotime($return_date)) / 86400;
            $fine = ceil($overdue_days) * 10; // PKR 10 per day
        }
        
        // Update issue record
        $db->prepare("
            UPDATE library_issues 
            SET actual_return = ?, 
                fine_amount = ?, 
                status = 'Returned' 
            WHERE id = ?
        ")->execute([$today, $fine, $issue_id]);
        
        // Update available copies
        $db->prepare("
            UPDATE library_books 
            SET available_copies = available_copies + 1,
                status = 'Available'
            WHERE id = ?
        ")->execute([$issue['book_id']]);
        
        setFlashMessage('success', 'Book returned successfully!' . ($fine > 0 ? ' Fine: PKR ' . $fine : ' No fine.'));
        header('Location: index.php');
        exit;
    }
}


// Helper to format currency/number
function number_get_format($num) {
    return number_format($num, 2);
}

// --- FETCH DATA ---

// Stats
$total_books = $db->query("SELECT SUM(total_copies) FROM library_books")->fetchColumn() ?? 0;
$issued_books = $db->query("SELECT COUNT(*) FROM library_issues WHERE status='Issued'")->fetchColumn() ?? 0;
$available_books = $db->query("SELECT SUM(available_copies) FROM library_books")->fetchColumn() ?? 0;
$overdue_books = $db->query("SELECT COUNT(*) FROM library_issues WHERE status='Issued' AND return_date < CURDATE()")->fetchColumn() ?? 0;

// Books
$books = $db->query("SELECT * FROM library_books ORDER BY id DESC")->fetchAll();

// Issued Books (Only currently issued)
$issued_list = $db->query("
    SELECT li.*, 
           lb.title as book_title,
           lb.author,
           CONCAT(s.first_name, ' ', s.last_name) as student_name,
           COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), CONCAT('STD-', s.id)) as student_no,
           DATEDIFF(CURDATE(), li.return_date) as overdue_days
    FROM library_issues li
    JOIN library_books lb ON li.book_id = lb.id
    JOIN students s ON li.student_id = s.id
    WHERE li.status = 'Issued'
    ORDER BY li.issue_date DESC
")->fetchAll();

// Returned Books (History)
$returned_list = $db->query("
    SELECT li.*, 
           lb.title as book_title,
           lb.book_id as lib_id,
           CONCAT(s.first_name, ' ', s.last_name) as student_name,
           COALESCE(NULLIF(s.roll_number, ''), NULLIF(s.registration_number, ''), CONCAT('STD-', s.id)) as student_no
    FROM library_issues li
    JOIN library_books lb ON li.book_id = lb.id
    JOIN students s ON li.student_id = s.id
    WHERE li.status = 'Returned'
    ORDER BY li.actual_return DESC
")->fetchAll();


// Students for Dropdown
$students = $db->query("
    SELECT id,
           COALESCE(NULLIF(roll_number, ''), NULLIF(registration_number, ''), CONCAT('STD-', id)) as student_code,
           first_name,
           last_name
    FROM students
    ORDER BY first_name ASC
")->fetchAll();

$page_title = "Library Management";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <h2 class="page-title mb-0"><i class="fas fa-book-reader me-2" style="color: var(--teal);"></i>Library Management</h2>
            <div class="d-flex flex-wrap gap-2">
                <a href="issue.php" class="btn btn-outline-primary"><i class="fas fa-hand-holding me-1"></i>Issue</a>
                <a href="return.php" class="btn btn-outline-primary"><i class="fas fa-undo me-1"></i>Return</a>
                <a href="fines.php" class="btn btn-outline-primary"><i class="fas fa-coins me-1"></i>Fines</a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="fas fa-plus me-2"></i>Add New Book
                </button>
            </div>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                        <i class="fas fa-books fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Total Books</p>
                        <h3 class="mb-0 fw-bold"><?= $total_books ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-info bg-opacity-10 text-info rounded-3 p-3 me-3">
                        <i class="fas fa-hand-holding-bookmark fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Issued Books</p>
                        <h3 class="mb-0 fw-bold text-info"><?= $issued_books ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Available</p>
                        <h3 class="mb-0 fw-bold text-success"><?= $available_books ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Overdue</p>
                        <h3 class="mb-0 fw-bold text-danger"><?= $overdue_books ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="libraryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-uppercase small" id="books-tab" data-bs-toggle="tab" data-bs-target="#books-content" type="button" role="tab">All Books</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-uppercase small" id="issued-tab" data-bs-toggle="tab" data-bs-target="#issued-content" type="button" role="tab">Issued Books</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-uppercase small" id="overdue-tab" data-bs-toggle="tab" data-bs-target="#overdue-content" type="button" role="tab">Overdue Books</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-uppercase small" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-content" type="button" role="tab">Return History</button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="libraryTabsContent">
                
                <!-- All Books Tab -->
                <div class="tab-pane fade show active" id="books-content" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Category</th>
                                    <th>ISBN</th>
                                    <th>Copies</th>
                                    <th>Avail</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><span class="badge bg-navy-light text-navy"><?= $book['book_id'] ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($book['title']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($book['publisher']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($book['author']) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= $book['category'] ?></span></td>
                                    <td><small><?= htmlspecialchars($book['isbn']) ?></small></td>
                                    <td><?= $book['total_copies'] ?></td>
                                    <td><?= $book['available_copies'] ?></td>
                                    <td>
                                        <?php if ($book['available_copies'] > 0): ?>
                                            <span class="badge bg-success-subtle text-success">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Issued</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="issueBook(<?= $book['id'] ?>, '<?= htmlspecialchars(addslashes($book['title'])) ?>')" <?= $book['available_copies'] == 0 ? 'disabled' : '' ?> title="Issue Book">
                                                <i class="fas fa-hand-holding"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="editBook(<?= htmlspecialchars(json_encode($book)) ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteBook(<?= $book['id'] ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Issued Books Tab -->
                <div class="tab-pane fade" id="issued-content" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Book Title</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issued_list as $issue): 
                                    $is_overdue = $issue['overdue_days'] > 0;
                                    $fine = $is_overdue ? $issue['overdue_days'] * 10 : 0;
                                ?>
                                <tr class="<?= $is_overdue ? 'table-danger' : '' ?>">
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($issue['student_name']) ?></div>
                                        <small class="text-muted"><?= $issue['student_no'] ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($issue['book_title']) ?></div>
                                        <small class="text-muted"><?= $issue['author'] ?></small>
                                    </td>
                                    <td><?= date('d M Y', strtotime($issue['issue_date'])) ?></td>
                                    <td>
                                        <div class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>"><?= date('d M Y', strtotime($issue['return_date'])) ?></div>
                                        <?php if ($is_overdue): ?> <small class="text-danger">Overdue!</small> <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($is_overdue): ?>
                                            <span class='badge bg-danger'>
                                                <?= $issue['overdue_days'] ?> days overdue
                                            </span><br>
                                            <small class='text-danger fw-bold'>Fine: PKR <?= $fine ?></small>
                                        <?php else: ?>
                                            <span class='badge bg-success'>On Time</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class='btn btn-sm btn-success' 
                                                onclick='openReturnModal(
                                                    <?= $issue["id"] ?>,
                                                    "<?= addslashes($issue["book_title"]) ?>",
                                                    "<?= addslashes($issue["student_name"]) ?>",
                                                    "<?= $issue["issue_date"] ?>",
                                                    "<?= $issue["return_date"] ?>"
                                                )'>
                                            <i class='fas fa-undo me-1'></i> Return
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Overdue Books Tab -->
                <div class="tab-pane fade" id="overdue-content" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Book Title</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Fine Amount</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issued_list as $issue): 
                                    if ($issue['overdue_days'] <= 0) continue;
                                    $fine = $issue['overdue_days'] * 10;
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-danger"><?= htmlspecialchars($issue['student_name']) ?></div>
                                        <small class="text-muted"><?= $issue['student_no'] ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($issue['book_title']) ?></td>
                                    <td><?= date('d M Y', strtotime($issue['issue_date'])) ?></td>
                                    <td><span class="text-danger fw-bold"><?= date('d M Y', strtotime($issue['return_date'])) ?></span></td>
                                    <td><span class="badge bg-danger"><?= $issue['overdue_days'] ?> Days</span></td>
                                    <td><span class="fw-bold">PKR <?= number_get_format($fine) ?></span></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-danger" onclick='openReturnModal(
                                                    <?= $issue["id"] ?>,
                                                    "<?= addslashes($issue["book_title"]) ?>",
                                                    "<?= addslashes($issue["student_name"]) ?>",
                                                    "<?= $issue["issue_date"] ?>",
                                                    "<?= $issue["return_date"] ?>"
                                                )'>
                                            Process Return
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Return History Tab -->
                <div class="tab-pane fade" id="history-content" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Book Title</th>
                                    <th>Issue Date</th>
                                    <th>Return Date</th>
                                    <th>Actual Return</th>
                                    <th>Fine Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returned_list as $ret): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($ret['student_name']) ?></div>
                                        <small class="text-muted"><?= $ret['student_no'] ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($ret['book_title']) ?></div>
                                        <small class="badge bg-light text-dark"><?= $ret['lib_id'] ?></small>
                                    </td>
                                    <td><?= date('d M Y', strtotime($ret['issue_date'])) ?></td>
                                    <td><?= date('d M Y', strtotime($ret['return_date'])) ?></td>
                                    <td><span class="fw-bold text-success"><?= date('d M Y', strtotime($ret['actual_return'])) ?></span></td>
                                    <td>
                                        <span class="<?= $ret['fine_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                            PKR <?= number_get_format($ret['fine_amount']) ?>
                                        </span>
                                    </td>
                                    <td><span class="badge bg-success-subtle text-success">Returned</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title"><i class="fas fa-book me-2"></i>Add New Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_book">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Book Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="Enter book title">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="Science">Science</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="English">English</option>
                                <option value="Urdu">Urdu</option>
                                <option value="Islamic Studies">Islamic Studies</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Reference">Reference</option>
                                <option value="Fiction">Fiction</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Author Name</label>
                            <input type="text" name="author" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">ISBN</label>
                            <input type="text" name="isbn" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Publisher</label>
                            <input type="text" name="publisher" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Pub. Year</label>
                            <input type="number" name="publication_year" class="form-control" value="<?= date('Y') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Total Copies</label>
                            <input type="number" name="total_copies" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Campus</label>
                            <select name="campus" class="form-select">
                                <?php renderCampusOptions($_SESSION['user_campus'] ?? ''); ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Purchase Cost (optional)</label>
                            <input type="number" step="0.01" name="purchase_amount" class="form-control" placeholder="Creates pending finance expense">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Shelf Number</label>
                            <input type="text" name="shelf_number" class="form-control" placeholder="e.g. S-102">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Book Modal -->
<div class="modal fade" id="editBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="editBookForm">
                <input type="hidden" name="action" value="edit_book">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Book Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Category</label>
                            <select name="category" id="edit_category" class="form-select" required>
                                <option value="Science">Science</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="English">English</option>
                                <option value="Urdu">Urdu</option>
                                <option value="Islamic Studies">Islamic Studies</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Reference">Reference</option>
                                <option value="Fiction">Fiction</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Author Name</label>
                            <input type="text" name="author" id="edit_author" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">ISBN</label>
                            <input type="text" name="isbn" id="edit_isbn" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Publisher</label>
                            <input type="text" name="publisher" id="edit_publisher" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Pub. Year</label>
                            <input type="number" name="publication_year" id="edit_year" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Total Copies</label>
                            <input type="number" name="total_copies" id="edit_copies" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Shelf Number</label>
                            <input type="text" name="shelf_number" id="edit_shelf" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info px-4 text-white">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Issue Book Modal -->
<div class="modal fade" id="issueBookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-hand-holding me-2"></i>Issue Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="issue_book">
                <input type="hidden" name="book_id" id="issue_book_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Book Selected</label>
                        <input type="text" id="issue_book_title" class="form-control bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Student</label>
                        <select name="student_id" class="form-select select2" required>
                            <option value="">Choose Student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['student_code']) ?> - <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Issue Date</label>
                            <input type="date" class="form-control bg-light" value="<?= date('Y-m-d') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Return Date</label>
                            <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                        </div>
                    </div>
                    <div class="mt-3 p-2 bg-warning bg-opacity-10 rounded border border-warning border-opacity-25">
                        <small class="text-warning-emphasis"><i class="fas fa-info-circle me-1"></i> Fine of PKR 10/day will be applied after the return date.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Confirm Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Book Modal -->
<div class='modal fade' id='returnModal' tabindex='-1'>
    <div class='modal-dialog'>
        <div class='modal-content border-0 shadow'>
            <div class='modal-header' style='background:#0f2d48;color:white;'>
                <h5 class='modal-title'>
                    <i class='fas fa-undo me-2'></i>Return Book
                </h5>
                <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
            </div>
            <div class='modal-body p-4'>
                <div class='row g-3'>
                    <div class='col-12'>
                        <div class='alert alert-info border-0 shadow-sm'>
                            <i class='fas fa-info-circle me-2'></i>
                            <strong>Book:</strong> <span id='returnBookTitle'></span><br>
                            <strong>Student:</strong> <span id='returnStudentName'></span><br>
                            <strong>Issue Date:</strong> <span id='returnIssueDate'></span><br>
                            <strong>Due Date:</strong> <span id='returnDueDate'></span>
                        </div>
                    </div>
                    <div class='col-12'>
                        <label class='form-label fw-bold small'>Actual Return Date</label>
                        <input type='date' 
                               id='actualReturnDate' 
                               class='form-control' 
                               value='<?= date("Y-m-d") ?>'>
                    </div>
                    <div class='col-12' id='fineSection' style='display:none;'>
                        <div class='alert alert-warning border-0 shadow-sm'>
                            <i class='fas fa-exclamation-triangle me-2'></i>
                            <strong>Overdue Fine:</strong> 
                            PKR <span id='fineAmount'>0</span>
                            <br>
                            <small>PKR 10 per day overdue</small>
                        </div>
                    </div>
                    <div class='col-12'>
                        <label class='form-label fw-bold small'>Remarks (Optional)</label>
                        <textarea name='remarks' 
                                  class='form-control' 
                                  rows='2' 
                                  placeholder='Book condition, notes...'></textarea>
                    </div>
                </div>
            </div>
            <div class='modal-footer bg-light'>
                <button type='button' class='btn btn-secondary px-4' data-bs-dismiss='modal'>Cancel</button>
                <button type='button' 
                        class='btn btn-success px-4' 
                        id='confirmReturn'
                        style='background:#4ec2b5;border:none;color:#0f2d48;font-weight:700;'>
                    <i class='fas fa-check me-2'></i>Confirm Return
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBookModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete_book">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                    <h5>Are you sure?</h5>
                    <p class="small text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
        --navy-light: #e7eaed;
    }
    .bg-navy { background-color: var(--navy) !important; }
    .bg-navy-light { background-color: var(--navy-light) !important; }
    .text-navy { color: var(--navy) !important; }
    
    .nav-tabs .nav-link {
        color: #64748b;
        border: none;
        padding: 1rem 1.5rem;
        border-bottom: 2px solid transparent;
    }
    .nav-tabs .nav-link.active {
        color: var(--teal);
        background: transparent;
        border-bottom-color: var(--teal);
    }
    .stats-icon {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .page-title {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        color: var(--navy);
    }
    .btn-primary { background-color: var(--teal); border-color: var(--teal); }
    .btn-primary:hover { background-color: #3da89c; border-color: #3da89c; }
    
    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
        color: #64748b;
        border-top: none;
    }

    /* Fix modal overlay */
    .modal {
        z-index: 99999 !important;
    }
    .modal-backdrop {
        z-index: 99998 !important;
    }
    .modal-dialog {
        z-index: 100000 !important;
    }

    /* Fix modal position */
    .modal {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
    }

    /* Dark overlay behind modal */
    .modal-backdrop {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0,0,0,.6) !important;
    }

    /* Modal content centered */
    .modal-dialog {
        margin: 30px auto !important;
        max-width: 700px !important;
    }

    /* Modal should cover sidebar */
    .modal.show {
        display: flex !important;
        align-items: flex-start !important;
        justify-content: center !important;
        padding: 20px !important;
    }
</style>

<script>
function editBook(book) {
    document.getElementById('edit_id').value = book.id;
    document.getElementById('edit_title').value = book.title;
    document.getElementById('edit_author').value = book.author;
    document.getElementById('edit_isbn').value = book.isbn;
    document.getElementById('edit_category').value = book.category;
    document.getElementById('edit_publisher').value = book.publisher;
    document.getElementById('edit_year').value = book.publication_year;
    document.getElementById('edit_copies').value = book.total_copies;
    document.getElementById('edit_shelf').value = book.shelf_number;
    document.getElementById('edit_desc').value = book.description;
    new bootstrap.Modal(document.getElementById('editBookModal')).show();
}

function deleteBook(id) {
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteBookModal')).show();
}

function issueBook(id, title) {
    document.getElementById('issue_book_id').value = id;
    document.getElementById('issue_book_title').value = title;
    new bootstrap.Modal(document.getElementById('issueBookModal')).show();
}

function openReturnModal(issueId, bookTitle, studentName, issueDate, dueDate){
    document.getElementById('returnBookTitle').textContent = bookTitle;
    document.getElementById('returnStudentName').textContent = studentName;
    document.getElementById('returnIssueDate').textContent = issueDate;
    document.getElementById('returnDueDate').textContent = dueDate;
    
    const actualReturnInput = document.getElementById('actualReturnDate');
    const fineSection = document.getElementById('fineSection');
    const fineAmountSpan = document.getElementById('fineAmount');

    function calculateFine() {
        const returnDate = new Date(actualReturnInput.value);
        const due = new Date(dueDate);
        
        if(returnDate > due){
            const diffTime = returnDate - due;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const fine = diffDays * 10;
            
            fineAmountSpan.textContent = fine;
            fineSection.style.display = 'block';
        } else {
            fineSection.style.display = 'none';
        }
    }

    // Initial calculation
    calculateFine();
    
    // Calculate fine on date change
    actualReturnInput.onchange = calculateFine;
    
    document.getElementById('confirmReturn').onclick = function(){
        window.location.href = 'index.php?action=return&issue_id=' + issueId;
    };
    
    new bootstrap.Modal(document.getElementById('returnModal')).show();
}


// Fix modal on show
document.addEventListener('DOMContentLoaded', function(){
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal){
        modal.addEventListener('show.bs.modal', function(){
            document.body.style.overflow = 'hidden';
            this.style.zIndex = '99999';
        });
        modal.addEventListener('hide.bs.modal', function(){
            document.body.style.overflow = '';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
