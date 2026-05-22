<?php
// File: modules/tasks/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    $task_title = sanitizeInput($_POST['task_title']);
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    
    try {
        $stmt = $db->prepare("INSERT INTO tasks (task_title, due_date, priority) VALUES (:task_title, :due_date, :priority)");
        $stmt->execute([
            ':task_title' => $task_title,
            ':due_date' => $due_date,
            ':priority' => $priority
        ]);
        $success = "Task created successfully!";
    } catch (PDOException $e) {
        $error = "Error creating task: " . $e->getMessage();
    }
}

if (isset($_GET['complete_id'])) {
    $stmt = $db->prepare("UPDATE tasks SET status = 'Completed' WHERE id = :id");
    $stmt->execute([':id' => $_GET['complete_id']]);
    redirect('index.php');
}

$tasks = $db->query("SELECT * FROM tasks ORDER BY status DESC, due_date ASC")->fetchAll();

$page_title = "Task Management";
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">Internal Tasks</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal"><i class="fas fa-plus"></i> New Task</button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Task List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Due Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['task_title']); ?></td>
                                <td><?php echo date('d M Y', strtotime($t['due_date'])); ?></td>
                                <td>
                                    <?php 
                                    $p_class = 'info';
                                    if ($t['priority'] === 'Urgent' || $t['priority'] === 'High') $p_class = 'danger';
                                    if ($t['priority'] === 'Medium') $p_class = 'warning text-dark';
                                    ?>
                                    <span class="badge bg-<?php echo $p_class; ?>"><?php echo htmlspecialchars($t['priority']); ?></span>
                                </td>
                                <td>
                                    <?php if ($t['status'] === 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($t['status'] === 'Pending'): ?>
                                        <a href="index.php?complete_id=<?php echo $t['id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Complete</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-check-double"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_task">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Create New Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task Title *</label>
                        <input type="text" class="form-control" name="task_title" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date *</label>
                            <input type="date" class="form-control" name="due_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority *</label>
                            <select class="form-select" name="priority" required>
                                <option value="Normal">Normal</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
