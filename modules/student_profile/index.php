<?php
/**
 * File: modules/student_profile/index.php
 * Description: Student Profile Dashboard - Lists all students with quick actions.
 */

require_once '../../config/db.php';

// Session Check
if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Fetch students using columns that exist in the active database.
$studentCodeParts = [];
foreach (['student_id', 'registration_number', 'roll_number'] as $codeColumn) {
    if (columnExists($db, 'students', $codeColumn)) {
        $studentCodeParts[] = "NULLIF(s.`{$codeColumn}`, '')";
    }
}
$studentCodeExpr = $studentCodeParts
    ? 'COALESCE(' . implode(', ', $studentCodeParts) . ", CONCAT('STU-', LPAD(s.id, 4, '0')))"
    : "CONCAT('STU-', LPAD(s.id, 4, '0'))";

$joins = [];
$campusExpr = "'Main Campus'";
if (tableExists($db, 'campuses') && columnExists($db, 'students', 'campus_id')) {
    $joins[] = "LEFT JOIN campuses c ON c.id = s.campus_id";
    $campusExpr = "COALESCE(NULLIF(c.name, ''), 'Main Campus')";
} elseif (columnExists($db, 'students', 'campus')) {
    $campusExpr = "COALESCE(NULLIF(s.campus, ''), 'Main Campus')";
}

$studentFilter = '';
$params = [];
if (getUserRole() === 'student') {
    $studentFilter = 'WHERE s.user_id = :user_id';
    $params[':user_id'] = getUserId();
}

$query = "SELECT s.*, {$studentCodeExpr} AS display_student_id, {$campusExpr} AS display_campus
          FROM students s
          " . implode("\n          ", $joins) . "
          {$studentFilter}
          ORDER BY s.created_at DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $query = "SELECT s.*, CONCAT('STU-', LPAD(s.id, 4, '0')) AS display_student_id, 'Main Campus' AS display_campus
              FROM students s
              {$studentFilter}
              ORDER BY s.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

$isStudent = getUserRole() === 'student';

$page_title = "Student Profiles";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title text-navy mb-0">Student Profile Dashboard</h2>
        <?php if (!$isStudent): ?>
            <a href="add.php" class="btn btn-teal text-white">
                <i class="fas fa-plus me-2"></i>Add New Student
            </a>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-navy text-white">
                        <tr>
                            <th class="ps-4 py-3">Student ID</th>
                            <th class="py-3">Name</th>
                            <th class="py-3">Class</th>
                            <th class="py-3">Section</th>
                            <th class="py-3">Campus</th>
                            <th class="py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-user-slash d-block fs-1 mb-3"></i>
                                    No students found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-bold text-navy"><?php echo htmlspecialchars($student['display_student_id'] ?? getStudentDisplayId($student)); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($student['photo'])): ?>
                                                <img src="../../uploads/<?php echo htmlspecialchars($student['photo']); ?>" 
                                                     alt="" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle me-3 bg-teal d-flex align-items-center justify-content-center text-white fw-bold" 
                                                     style="width: 40px; height: 40px; font-size: 0.9rem;">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['guardian_phone'] ?? ''); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['class'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge bg-light text-navy border"><?php echo htmlspecialchars($student['section'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-info-subtle text-info border border-info px-3">
                                            <?php echo htmlspecialchars($student['display_campus'] ?? 'Main Campus'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                            <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-white text-info border-end" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!$isStudent): ?>
                                                <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-white text-warning border-end" title="Edit Profile">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="id_card.php?id=<?php echo $student['id']; ?>" target="_blank" class="btn btn-sm btn-white text-success" title="ID Card">
                                                <i class="fas fa-id-card"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .text-navy { color: var(--navy); }
    .bg-navy { background-color: var(--navy); }
    .btn-teal { background-color: var(--teal); border-color: var(--teal); }
    .btn-teal:hover { background-color: var(--teal-dark); border-color: var(--teal-dark); color: white; }
    .btn-white { background-color: #fff; border: 1px solid #eee; }
    .btn-white:hover { background-color: #f8fafc; }
    .table thead th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
    .table tbody tr { transition: all 0.2s; }
    .table tbody tr:hover { background-color: rgba(78, 194, 181, 0.03); }
    .badge.bg-info-subtle { background-color: rgba(13, 202, 240, 0.1) !important; color: #0dcaf0 !important; }
</style>

<?php include '../../includes/footer.php'; ?>
