<?php
// File: modules/tasks/index.php
require_once '../../config/db.php';
require_once '../../includes/shared_functions.php';

if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$db = (new Database())->getConnection();
$role = getUserRole();
$userId = getUserId();
$isAdmin = in_array($role, ['admin', 'owner'], true);
$statusOptions = ['pending', 'in_progress', 'completed', 'cancelled'];
$priorityOptions = ['low', 'medium', 'high', 'urgent'];

function task_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function task_valid_date($date): bool {
    $date = (string)$date;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function task_staff_id(PDO $db): ?int {
    if (tableExists($db, 'staff') && columnExists($db, 'staff', 'user_id')) {
        $stmt = $db->prepare('SELECT id FROM staff WHERE user_id = ? LIMIT 1');
        $stmt->execute([getUserId()]);
        $staffId = $stmt->fetchColumn();
        if ($staffId) {
            return (int)$staffId;
        }
    }
    return null;
}

function task_status_key($status): string {
    $status = strtolower(str_replace([' ', '-'], '_', (string)$status));
    if ($status === 'completed') return 'completed';
    if ($status === 'cancelled' || $status === 'canceled') return 'cancelled';
    if ($status === 'in_progress' || $status === 'progress') return 'in_progress';
    return 'pending';
}

function task_priority_key($priority): string {
    $priority = strtolower((string)$priority);
    return in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
}

function task_badge(string $type, string $value): string {
    $classes = [
        'pending' => 'bg-warning text-dark',
        'in_progress' => 'bg-primary',
        'completed' => 'bg-success',
        'cancelled' => 'bg-secondary',
        'low' => 'bg-info text-dark',
        'medium' => 'bg-warning text-dark',
        'high' => 'bg-danger',
        'urgent' => 'bg-dark',
    ];
    $label = ucwords(str_replace('_', ' ', $value));
    return '<span class="badge ' . ($classes[$value] ?? 'bg-secondary') . '">' . task_h($label) . '</span>';
}

function task_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT NULL,
        task_title VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        assigned_to INT DEFAULT NULL,
        assigned_by INT DEFAULT NULL,
        priority VARCHAR(20) NOT NULL DEFAULT 'medium',
        due_date DATE DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tasks_assigned_to (assigned_to),
        INDEX idx_tasks_status (status),
        INDEX idx_tasks_priority (priority),
        INDEX idx_tasks_due_date (due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'title' => "ALTER TABLE tasks ADD COLUMN title VARCHAR(255) DEFAULT NULL",
        'task_title' => "ALTER TABLE tasks ADD COLUMN task_title VARCHAR(255) DEFAULT NULL",
        'description' => "ALTER TABLE tasks ADD COLUMN description TEXT DEFAULT NULL",
        'assigned_to' => "ALTER TABLE tasks ADD COLUMN assigned_to INT DEFAULT NULL",
        'assigned_by' => "ALTER TABLE tasks ADD COLUMN assigned_by INT DEFAULT NULL",
        'priority' => "ALTER TABLE tasks ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'medium'",
        'due_date' => "ALTER TABLE tasks ADD COLUMN due_date DATE DEFAULT NULL",
        'status' => "ALTER TABLE tasks ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'",
        'created_at' => "ALTER TABLE tasks ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ALTER TABLE tasks ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
    ];
    foreach ($columns as $column => $sql) {
        if (!columnExists($db, 'tasks', $column)) {
            $db->exec($sql);
        }
    }
    $db->exec("UPDATE tasks SET title = task_title WHERE (title IS NULL OR title = '') AND task_title IS NOT NULL");
    $db->exec("UPDATE tasks SET task_title = title WHERE (task_title IS NULL OR task_title = '') AND title IS NOT NULL");
    $db->exec("UPDATE tasks SET status = CASE LOWER(REPLACE(status, ' ', '_')) WHEN 'completed' THEN 'completed' WHEN 'cancelled' THEN 'cancelled' WHEN 'canceled' THEN 'cancelled' WHEN 'in_progress' THEN 'in_progress' ELSE 'pending' END");
    $db->exec("UPDATE tasks SET priority = CASE LOWER(priority) WHEN 'low' THEN 'low' WHEN 'high' THEN 'high' WHEN 'urgent' THEN 'urgent' ELSE 'medium' END");
}

task_ensure_schema($db);
$currentStaffId = task_staff_id($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';

        if (in_array($action, ['add', 'edit'], true)) {
            if (!$isAdmin) {
                throw new Exception('Only admin can create or edit tasks.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $assignedTo = (int)($_POST['assigned_to'] ?? 0);
            $priority = task_priority_key($_POST['priority'] ?? 'medium');
            $dueDate = sanitizeInput($_POST['due_date'] ?? '');
            $status = task_status_key($_POST['status'] ?? 'pending');

            if ($title === '' || $assignedTo <= 0 || !task_valid_date($dueDate)) {
                throw new Exception('Please fill title, assigned person, and a valid due date.');
            }

            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO tasks (title, task_title, description, assigned_to, assigned_by, priority, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $title, $description, $assignedTo, $userId, $priority, $dueDate, $status]);
                setFlashMessage('success', 'Task created successfully.');
            } else {
                if ($id <= 0) {
                    throw new Exception('Invalid task selected.');
                }
                $stmt = $db->prepare("UPDATE tasks SET title = ?, task_title = ?, description = ?, assigned_to = ?, priority = ?, due_date = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $title, $description, $assignedTo, $priority, $dueDate, $status, $id]);
                setFlashMessage('success', 'Task updated successfully.');
            }
            redirect('index.php');
        }

        if ($action === 'update_status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = task_status_key($_POST['status'] ?? 'pending');
            $where = 'id = ?';
            $params = [$id];
            if (!$isAdmin) {
                $where .= ' AND assigned_to = ?';
                $params[] = $currentStaffId ?: 0;
            }
            $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE $where");
            $stmt->execute(array_merge([$status], $params));
            if ($stmt->rowCount() === 0) {
                throw new Exception('Task not found or not assigned to you.');
            }
            setFlashMessage('success', 'Task status updated.');
            redirect('index.php');
        }

        if ($action === 'delete') {
            if (!$isAdmin) {
                throw new Exception('Only admin can delete tasks.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
            setFlashMessage('success', 'Task deleted successfully.');
            redirect('index.php');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

$staffList = getAllStaff($db);
$filterStatus = in_array($_GET['status'] ?? '', $statusOptions, true) ? $_GET['status'] : '';
$filterPriority = in_array($_GET['priority'] ?? '', $priorityOptions, true) ? $_GET['priority'] : '';
$filterAssigned = (int)($_GET['assigned_to'] ?? 0);

$where = [];
$params = [];
if ($filterStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}
if ($filterPriority !== '') {
    $where[] = 'priority = ?';
    $params[] = $filterPriority;
}
if ($filterAssigned > 0) {
    $where[] = 'assigned_to = ?';
    $params[] = $filterAssigned;
}
if (!$isAdmin) {
    $where[] = 'assigned_to = ?';
    $params[] = $currentStaffId ?: 0;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $db->prepare("
    SELECT t.*, COALESCE(NULLIF(t.title, ''), t.task_title) AS display_title,
           assignee.full_name AS assignee_name,
           assigner.full_name AS assigned_by_name,
           u.full_name AS assigned_by_user_name,
           u.username AS assigned_by_username
    FROM tasks t
    LEFT JOIN staff assignee ON assignee.id = t.assigned_to
    LEFT JOIN staff assigner ON assigner.user_id = t.assigned_by
    LEFT JOIN users u ON u.id = t.assigned_by
    $whereSql
    ORDER BY FIELD(t.status, 'pending','in_progress','completed','cancelled'), t.due_date ASC, t.id DESC
");
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$stats = ['total' => count($tasks), 'overdue' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($tasks as $task) {
    $key = task_status_key($task['status']);
    $stats[$key]++;
    if (!in_array($key, ['completed', 'cancelled'], true) && $task['due_date'] && $task['due_date'] < $today) {
        $stats['overdue']++;
    }
}

$page_title = 'Task Management';
include '../../includes/header.php';
?>

<div class="container-fluid tasks-module">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-tasks me-2" style="color:var(--teal);"></i>Tasks / Work Management</h2>
            <div class="text-muted">Assign staff work, track progress, and review overdue tasks.</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            <?php if ($isAdmin): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal"><i class="fas fa-plus me-1"></i>New Task</button><?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Total</div><h3 class="fw-bold mb-0"><?= (int)$stats['total'] ?></h3></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Pending</div><h3 class="fw-bold text-warning mb-0"><?= (int)$stats['pending'] ?></h3></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">In Progress</div><h3 class="fw-bold text-primary mb-0"><?= (int)$stats['in_progress'] ?></h3></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Completed</div><h3 class="fw-bold text-success mb-0"><?= (int)$stats['completed'] ?></h3></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Cancelled</div><h3 class="fw-bold text-secondary mb-0"><?= (int)$stats['cancelled'] ?></h3></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Overdue</div><h3 class="fw-bold text-danger mb-0"><?= (int)$stats['overdue'] ?></h3></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label small fw-bold">Status</label><select name="status" class="form-select"><option value="">All Status</option><?php foreach ($statusOptions as $status): ?><option value="<?= task_h($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= task_h(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small fw-bold">Priority</label><select name="priority" class="form-select"><option value="">All Priority</option><?php foreach ($priorityOptions as $priority): ?><option value="<?= task_h($priority) ?>" <?= $filterPriority === $priority ? 'selected' : '' ?>><?= task_h(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label small fw-bold">Assigned Person</label><select name="assigned_to" class="form-select" <?= !$isAdmin ? 'disabled' : '' ?>><option value="">All Staff</option><?php foreach ($staffList as $staff): ?><option value="<?= (int)$staff['id'] ?>" <?= $filterAssigned === (int)$staff['id'] ? 'selected' : '' ?>><?= task_h($staff['full_name'] . ' - ' . ($staff['designation'] ?? 'Staff')) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0">Task List</h5><span class="badge bg-light text-dark border"><?= count($tasks) ?> records</span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>Task</th><th>Assigned To</th><th>Priority</th><th>Due</th><th>Status</th><th class="text-end no-print">Actions</th></tr></thead>
                    <tbody>
                        <?php if (!$tasks): ?><tr><td colspan="6" class="text-center text-muted py-4">No tasks found.</td></tr><?php endif; ?>
                        <?php foreach ($tasks as $task): ?>
                            <?php $status = task_status_key($task['status']); $priority = task_priority_key($task['priority']); $overdue = !in_array($status, ['completed','cancelled'], true) && $task['due_date'] && $task['due_date'] < $today; ?>
                            <tr class="<?= $overdue ? 'table-danger' : '' ?>">
                                <td><div class="fw-bold"><?= task_h($task['display_title']) ?></div><div class="small text-muted"><?= nl2br(task_h($task['description'])) ?></div><div class="small text-muted">Assigned by: <?= task_h($task['assigned_by_name'] ?: ($task['assigned_by_user_name'] ?: $task['assigned_by_username'] ?: 'Admin')) ?></div></td>
                                <td><?= task_h($task['assignee_name'] ?: 'Unassigned') ?></td>
                                <td><?= task_badge('priority', $priority) ?></td>
                                <td><?= $task['due_date'] ? task_h(date('d M Y', strtotime($task['due_date']))) : '-' ?><?= $overdue ? '<div class="small text-danger fw-bold">Overdue</div>' : '' ?></td>
                                <td><?= task_badge('status', $status) ?></td>
                                <td class="text-end no-print">
                                    <div class="d-inline-flex gap-1">
                                        <form method="POST">
                                            <?= csrfTokenInput() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <?php foreach ($statusOptions as $option): ?><option value="<?= task_h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= task_h(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?>
                                            </select>
                                        </form>
                                        <?php if ($isAdmin): ?>
                                            <button class="btn btn-sm btn-outline-primary edit-task" data-bs-toggle="modal" data-bs-target="#editTaskModal" data-task='<?= task_h(json_encode($task)) ?>'><i class="fas fa-pen"></i></button>
                                            <form method="POST" onsubmit="return confirm('Delete this task?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<?php
$renderTaskForm = function (string $prefix) use ($staffList, $priorityOptions, $statusOptions) {
?>
    <div class="row g-3">
        <div class="col-12"><label class="form-label">Title *</label><input type="text" name="title" id="<?= $prefix ?>title" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="<?= $prefix ?>description" class="form-control" rows="4"></textarea></div>
        <div class="col-md-6"><label class="form-label">Assign To *</label><select name="assigned_to" id="<?= $prefix ?>assigned_to" class="form-select" required><option value="">Select staff</option><?php foreach ($staffList as $staff): ?><option value="<?= (int)$staff['id'] ?>"><?= task_h($staff['full_name'] . ' - ' . ($staff['designation'] ?? 'Staff')) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Due Date *</label><input type="date" name="due_date" id="<?= $prefix ?>due_date" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Priority</label><select name="priority" id="<?= $prefix ?>priority" class="form-select"><?php foreach ($priorityOptions as $priority): ?><option value="<?= task_h($priority) ?>"><?= task_h(ucfirst($priority)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="<?= $prefix ?>status" class="form-select"><?php foreach ($statusOptions as $status): ?><option value="<?= task_h($status) ?>"><?= task_h(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
    </div>
<?php
};
?>
<div class="modal fade no-print" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content border-0 shadow" method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="add"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Create Task</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php $renderTaskForm('add_'); ?></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Task</button></div></form></div>
</div>
<div class="modal fade no-print" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content border-0 shadow" method="POST"><?= csrfTokenInput() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit_id"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit Task</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php $renderTaskForm('edit_'); ?></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Task</button></div></form></div>
</div>
<?php endif; ?>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop, .btn, form { display:none !important; } #content { margin-left:0 !important; width:100% !important; } .card { box-shadow:none !important; border:1px solid #ddd !important; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.edit-task').forEach(function (button) {
        button.addEventListener('click', function () {
            const task = JSON.parse(button.dataset.task || '{}');
            const map = {
                id: task.id,
                title: task.display_title || task.title || task.task_title || '',
                description: task.description || '',
                assigned_to: task.assigned_to || '',
                due_date: task.due_date || '',
                priority: task.priority || 'medium',
                status: task.status || 'pending'
            };
            Object.keys(map).forEach(function (field) {
                const el = document.getElementById('edit_' + field);
                if (el) el.value = map[field];
            });
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
