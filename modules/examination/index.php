<?php
/**
 * File: modules/examination/index.php
 * Examination Module Dashboard for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner', 'teacher']);

$db = (new Database())->getConnection();

// --- ENSURE TABLES EXIST ---
$db->exec("CREATE TABLE IF NOT EXISTS exam_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_title VARCHAR(200),
    exam_type VARCHAR(50),
    class VARCHAR(100),
    section VARCHAR(50),
    subject VARCHAR(100),
    exam_date DATE,
    start_time TIME,
    end_time TIME,
    room VARCHAR(50),
    total_marks INT DEFAULT 100,
    campus VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS exam_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    exam_schedule_id INT,
    subject VARCHAR(100),
    class VARCHAR(100),
    section VARCHAR(50),
    total_marks INT,
    obtained_marks INT,
    grade VARCHAR(5),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_marks (student_id, exam_schedule_id)
)");

// --- FETCH STATS ---
// Total exams scheduled
$total_exams = $db->query("SELECT COUNT(*) FROM exam_schedule")->fetchColumn() ?? 0;

// Total marks entries
$total_marks_entries = $db->query("SELECT COUNT(*) FROM exam_marks")->fetchColumn() ?? 0;

// Exams scheduled today or upcoming
$upcoming_exams = $db->query("SELECT COUNT(*) FROM exam_schedule WHERE exam_date >= CURDATE()")->fetchColumn() ?? 0;

// Unique students who have appeared in exams
$total_examined_students = $db->query("SELECT COUNT(DISTINCT student_id) FROM exam_marks")->fetchColumn() ?? 0;

// --- FETCH RECENT ACTIVITY ---
$recent_schedule = $db->query("SELECT * FROM exam_schedule ORDER BY created_at DESC LIMIT 5")->fetchAll();

$page_title = "Examination Dashboard";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h2 class="page-title mb-0"><i class="fas fa-file-signature me-2" style="color: var(--teal);"></i>Examination Dashboard</h2>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Total Exams</p>
                        <h3 class="mb-0 fw-bold"><?= $total_exams ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="fas fa-edit fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Marks Entered</p>
                        <h3 class="mb-0 fw-bold text-success"><?= $total_marks_entries ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-info bg-opacity-10 text-info rounded-3 p-3 me-3">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Upcoming</p>
                        <h3 class="mb-0 fw-bold text-info"><?= $upcoming_exams ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Students</p>
                        <h3 class="mb-0 fw-bold text-warning"><?= $total_examined_students ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <a href="schedule.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 action-card h-100 bg-navy text-white overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <i class="fas fa-calendar-alt fa-3x opacity-25 position-absolute end-0 bottom-0 mb-3 me-3"></i>
                        <h4 class="fw-bold mb-2">Exam Schedule</h4>
                        <p class="small opacity-75">Create, view, and manage examination dates and rooms.</p>
                        <div class="mt-4">
                            <span class="btn btn-sm btn-light text-navy fw-bold px-3 rounded-pill">Manage Schedule <i class="fas fa-arrow-right ms-1"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="marks.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 action-card h-100 bg-teal text-navy overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <i class="fas fa-marker fa-3x opacity-25 position-absolute end-0 bottom-0 mb-3 me-3"></i>
                        <h4 class="fw-bold mb-2">Marks Entry</h4>
                        <p class="small opacity-75">Enter and update student marks for scheduled exams.</p>
                        <div class="mt-4">
                            <span class="btn btn-sm btn-navy fw-bold px-3 rounded-pill">Enter Marks <i class="fas fa-arrow-right ms-1"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="results.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 action-card h-100 bg-white border overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <i class="fas fa-award fa-3x text-warning opacity-25 position-absolute end-0 bottom-0 mb-3 me-3"></i>
                        <h4 class="fw-bold mb-2 text-navy">Results & Reports</h4>
                        <p class="small text-muted">Generate result sheets and print student report cards.</p>
                        <div class="mt-4">
                            <span class="btn btn-sm btn-outline-navy fw-bold px-3 rounded-pill">View Results <i class="fas fa-arrow-right ms-1"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="../../result-cards.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 action-card h-100 bg-white border overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <i class="fas fa-certificate fa-3x text-success opacity-25 position-absolute end-0 bottom-0 mb-3 me-3"></i>
                        <h4 class="fw-bold mb-2 text-navy">Result Cards</h4>
                        <p class="small text-muted">Generate persistent, printable academic report cards.</p>
                        <div class="mt-4">
                            <span class="btn btn-sm btn-outline-navy fw-bold px-3 rounded-pill">Generate Result Cards <i class="fas fa-arrow-right ms-1"></i></span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Schedule -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-navy">Recently Scheduled Exams</h5>
                    <a href="schedule.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light text-uppercase small fw-bold">
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_schedule)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No exams scheduled yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_schedule as $row): 
                                        $is_past = strtotime($row['exam_date']) < strtotime(date('Y-m-d'));
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['exam_title']) ?></td>
                                        <td><?= htmlspecialchars($row['subject']) ?></td>
                                        <td><span class="badge bg-light text-dark"><?= $row['class'] ?></span></td>
                                        <td><?= date('d M, Y', strtotime($row['exam_date'])) ?></td>
                                        <td><small class="text-muted"><?= date('h:i A', strtotime($row['start_time'])) ?></small></td>
                                        <td class="text-end">
                                            <?php if ($is_past): ?>
                                                <span class="badge bg-secondary">Completed</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Upcoming</span>
                                            <?php endif; ?>
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
    </div>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
    .bg-navy { background-color: var(--navy) !important; }
    .bg-teal { background-color: var(--teal) !important; }
    .text-navy { color: var(--navy) !important; }
    .btn-navy { background-color: var(--navy); color: white; }
    .btn-navy:hover { background-color: #1a3a5a; color: white; }
    .btn-outline-navy { color: var(--navy); border-color: var(--navy); }
    .btn-outline-navy:hover { background-color: var(--navy); color: white; }
    
    .stats-icon {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .page-title { font-family: 'Playfair Display', serif; font-weight: 700; color: var(--navy); }
    
    .action-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
    }
    .action-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
    }
</style>

<?php include '../../includes/footer.php'; ?>
