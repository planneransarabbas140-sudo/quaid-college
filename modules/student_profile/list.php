<?php
// File: modules/student_profile/list.php - Student List
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM students WHERE id = :id");
    if ($stmt->execute([':id' => $_GET['delete']])) {
        setFlashMessage('success', 'Student deleted successfully!');
        redirect('list.php');
    }
}

// Get all students
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$class = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';

$query = "SELECT s.*, u.username FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE 1=1";
$params = [];

if ($search) {
    $searchColumns = ["s.first_name LIKE :search", "s.last_name LIKE :search"];
    foreach (['student_id', 'registration_number', 'roll_number'] as $codeColumn) {
        if (columnExists($db, 'students', $codeColumn)) {
            $searchColumns[] = "s.`{$codeColumn}` LIKE :search";
        }
    }
    $query .= " AND (" . implode(' OR ', $searchColumns) . ")";
    $params[':search'] = "%$search%";
}

if ($class) {
    $query .= " AND s.class = :class";
    $params[':class'] = $class;
}

$query .= " ORDER BY s.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get unique classes for filter
$classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetchAll();

$page_title = "Student Profiles";
include '../../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">Student List</h6>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add New Student
        </a>
    </div>
    <div class="card-body">
        <!-- Search Form -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or ID" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select name="class" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class']; ?>" <?php echo $class == $c['class'] ? 'selected' : ''; ?>>
                                <?php echo $c['class']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="list.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID Card</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Guardian</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td>
                            <?php if ($student['photo']): ?>
                                <img src="../../uploads/<?php echo $student['photo']; ?>" width="50" height="50" class="rounded-circle">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(getStudentDisplayId($student)); ?></td>
                        <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                        <td><?php echo $student['class'] . '-' . $student['section']; ?></td>
                        <td><?php echo $student['guardian_name']; ?></td>
                        <td><?php echo $student['guardian_phone']; ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $student['id']; ?>)" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </a>
                            <a href="id_card.php?id=<?php echo $student['id']; ?>" target="_blank" class="btn btn-sm btn-success">
                                <i class="fas fa-id-card"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        window.location.href = 'list.php?delete=' + id;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
