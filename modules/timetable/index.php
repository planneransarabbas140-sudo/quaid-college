<?php
/**
 * File: modules/timetable/index.php
 * Description: Complete Timetable Management System
 */

require_once '../../config/db.php';

// Session Check
if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("INSERT INTO timetable (class, section, subject, teacher_name, day, start_time, end_time, room, campus) 
                                      VALUES (:class, :section, :subject, :teacher_name, :day, :start_time, :end_time, :room, :campus)");
                $stmt->execute([
                    ':class' => $_POST['class'],
                    ':section' => $_POST['section'],
                    ':subject' => $_POST['subject'],
                    ':teacher_name' => $_POST['teacher_name'],
                    ':day' => $_POST['day'],
                    ':start_time' => $_POST['start_time'],
                    ':end_time' => $_POST['end_time'],
                    ':room' => $_POST['room'],
                    ':campus' => $_POST['campus']
                ]);
                $message = "Schedule added successfully!";
                $messageType = "success";
            } elseif ($_POST['action'] === 'edit') {
                $stmt = $db->prepare("UPDATE timetable SET 
                                      class = :class, section = :section, subject = :subject, 
                                      teacher_name = :teacher_name, day = :day, 
                                      start_time = :start_time, end_time = :end_time, 
                                      room = :room, campus = :campus 
                                      WHERE id = :id");
                $stmt->execute([
                    ':class' => $_POST['class'],
                    ':section' => $_POST['section'],
                    ':subject' => $_POST['subject'],
                    ':teacher_name' => $_POST['teacher_name'],
                    ':day' => $_POST['day'],
                    ':start_time' => $_POST['start_time'],
                    ':end_time' => $_POST['end_time'],
                    ':room' => $_POST['room'],
                    ':campus' => $_POST['campus'],
                    ':id' => $_POST['id']
                ]);
                $message = "Schedule updated successfully!";
                $messageType = "success";
            } elseif ($_POST['action'] === 'delete') {
                $stmt = $db->prepare("DELETE FROM timetable WHERE id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                $message = "Schedule deleted successfully!";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Filter Values
$filterClass = $_GET['class'] ?? '';
$filterSection = $_GET['section'] ?? '';
$filterCampus = $_GET['campus'] ?? '';
$filterDay = $_GET['day'] ?? '';

// Fetch Data
$query = "SELECT * FROM timetable WHERE 1=1";
$params = [];

if ($filterClass) {
    $query .= " AND class = :class";
    $params[':class'] = $filterClass;
}
if ($filterSection) {
    $query .= " AND section = :section";
    $params[':section'] = $filterSection;
}
if ($filterCampus) {
    $query .= " AND campus = :campus";
    $params[':campus'] = $filterCampus;
}
if ($filterDay) {
    $query .= " AND day = :day";
    $params[':day'] = $filterDay;
}

$query .= " ORDER BY start_time ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

// Constants
$programs = [
    'Intermediate' => ['FSc Pre-Medical', 'FSc Pre-Engineering', 'ICS', 'I.Com', 'FA', 'Taleem-ul-Islam'],
    'Degree' => ['ADP Arts', 'ADP Science', 'BSCS', 'BS IT', 'BS Zoology', 'BS Mathematics', 'BS Urdu', 'BS Chemistry'],
    'NAVTTC' => ['CCA', 'Web Development', 'Graphic Designing', 'Digital Marketing', 'UX/UI Design', 'AI', 'Vibe Coding']
];
$sections = ['A', 'B', 'C', 'D', 'Morning', 'Evening', 'Weekend', 'Batch 1', 'Batch 2', 'Batch 3', 'Batch 4'];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$campuses = ['Rajanpur', 'Fazilpur', 'Kot Mithan'];

// Helper for coloring
function getSubjectColor($subject) {
    $colors = ['#4ec2b5', '#0f2d48', '#f39c12', '#e74c3c', '#9b59b6', '#3498db', '#2ecc71', '#1abc9c'];
    $hash = md5($subject);
    $index = hexdec(substr($hash, 0, 1)) % count($colors);
    return $colors[$index];
}

$page_title = 'Timetable Management';
include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="page-title text-navy mb-0">Class Timetable</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-navy" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Timetable
            </button>
            <button class="btn btn-teal text-white" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus me-2"></i>Add Schedule
            </button>
        </div>
    </div>

    <!-- Message Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show no-print" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select name="class" class="form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($programs as $category => $list): ?>
                            <optgroup label="<?php echo $category; ?>">
                                <?php foreach ($list as $prog): ?>
                                    <option value="<?php echo $prog; ?>" <?php echo $filterClass === $prog ? 'selected' : ''; ?>><?php echo $prog; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec; ?>" <?php echo $filterSection === $sec ? 'selected' : ''; ?>><?php echo $sec; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Campus</label>
                    <select name="campus" class="form-select">
                        <option value="">All Campuses</option>
                        <?php foreach ($campuses as $camp): ?>
                            <option value="<?php echo $camp; ?>" <?php echo $filterCampus === $camp ? 'selected' : ''; ?>><?php echo $camp; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Day</label>
                    <select name="day" class="form-select">
                        <option value="">All Days</option>
                        <?php foreach ($days as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo $filterDay === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-navy w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Timetable Grid -->
    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="card-header bg-navy text-white py-3">
            <h5 class="mb-0">
                <?php 
                if ($filterClass || $filterSection || $filterCampus) {
                    echo htmlspecialchars($filterClass . ' ' . $filterSection . ' (' . $filterCampus . ')');
                } else {
                    echo "Global Timetable View";
                }
                ?>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 text-center timetable-table">
                    <thead>
                        <tr class="bg-light">
                            <th style="width: 120px;">Time</th>
                            <?php foreach ($days as $day): ?>
                                <th class="<?php echo $filterDay && $filterDay !== $day ? 'd-none' : ''; ?>"><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get all unique time slots
                        $timeSlots = [];
                        foreach ($schedules as $s) {
                            $slot = date('h:i A', strtotime($s['start_time'])) . ' - ' . date('h:i A', strtotime($s['end_time']));
                            $timeSlots[$slot] = [
                                'start' => $s['start_time'],
                                'end' => $s['end_time']
                            ];
                        }
                        
                        // If no schedules, show empty message
                        if (empty($timeSlots)) {
                            echo "<tr><td colspan='7' class='py-5 text-muted'>No schedule found for the selected filters.</td></tr>";
                        } else {
                            // Sort time slots by start time
                            uasort($timeSlots, function($a, $b) {
                                return strcmp($a['start'], $b['start']);
                            });

                            foreach ($timeSlots as $slotText => $times): ?>
                                <tr>
                                    <td class="bg-light fw-bold align-middle"><?php echo $slotText; ?></td>
                                    <?php foreach ($days as $day): 
                                        if ($filterDay && $filterDay !== $day) continue;
                                        
                                        // Find item for this slot and day
                                        $item = null;
                                        foreach ($schedules as $s) {
                                            if ($s['day'] === $day && $s['start_time'] === $times['start'] && $s['end_time'] === $times['end']) {
                                                $item = $s;
                                                break;
                                            }
                                        }
                                    ?>
                                        <td class="p-2 align-middle position-relative">
                                            <?php if ($item): ?>
                                                <div class="timetable-slot shadow-sm" style="border-left: 4px solid <?php echo getSubjectColor($item['subject']); ?>">
                                                    <div class="fw-bold text-navy small mb-1"><?php echo htmlspecialchars($item['subject']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">
                                                        <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($item['teacher_name']); ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">
                                                        <i class="fas fa-door-open me-1"></i><?php echo htmlspecialchars($item['room']); ?>
                                                    </div>
                                                    
                                                    <!-- Overlay Actions (Hover) -->
                                                    <div class="slot-actions no-print">
                                                        <button class="btn btn-sm btn-warning p-1" onclick="editSchedule(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this schedule?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger p-1">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach;
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade no-print" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title">Add New Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select" required>
                                <?php foreach ($programs as $category => $list): ?>
                                    <optgroup label="<?php echo $category; ?>">
                                        <?php foreach ($list as $prog): ?>
                                            <option value="<?php echo $prog; ?>"><?php echo $prog; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Section</label>
                            <select name="section" class="form-select" required>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Mathematics" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teacher Name</label>
                            <input type="text" name="teacher_name" class="form-control" placeholder="e.g. Prof. Ahmed" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Day</label>
                            <select name="day" class="form-select" required>
                                <?php foreach ($days as $d): ?>
                                    <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room/Lab</label>
                            <input type="text" name="room" class="form-control" placeholder="e.g. Lab 1 or Room 202" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campus</label>
                            <select name="campus" class="form-select" required>
                                <?php foreach ($campuses as $camp): ?>
                                    <option value="<?php echo $camp; ?>"><?php echo $camp; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-teal text-white">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade no-print" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title">Edit Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <select name="class" id="edit_class" class="form-select" required>
                                <?php foreach ($programs as $category => $list): ?>
                                    <optgroup label="<?php echo $category; ?>">
                                        <?php foreach ($list as $prog): ?>
                                            <option value="<?php echo $prog; ?>"><?php echo $prog; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Section</label>
                            <select name="section" id="edit_section" class="form-select" required>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" id="edit_subject" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teacher Name</label>
                            <input type="text" name="teacher_name" id="edit_teacher" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Day</label>
                            <select name="day" id="edit_day" class="form-select" required>
                                <?php foreach ($days as $d): ?>
                                    <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" id="edit_start" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" id="edit_end" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room/Lab</label>
                            <input type="text" name="room" id="edit_room" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campus</label>
                            <select name="campus" id="edit_campus" class="form-select" required>
                                <?php foreach ($campuses as $camp): ?>
                                    <option value="<?php echo $camp; ?>"><?php echo $camp; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy text-white">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .text-navy { color: var(--navy); }
    .bg-navy { background-color: var(--navy) !important; }
    .bg-teal { background-color: var(--teal) !important; }
    .btn-teal { background-color: var(--teal); border-color: var(--teal); }
    .btn-teal:hover { background-color: var(--teal-dark); border-color: var(--teal-dark); color: white; }
    .btn-navy { background-color: var(--navy); border-color: var(--navy); color: white; }
    .btn-navy:hover { background-color: var(--navy-mid); border-color: var(--navy-mid); color: white; }
    
    .timetable-table th { background-color: #f8fafc; color: var(--navy); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; }
    .timetable-slot { background: #fff; padding: 10px; border-radius: 8px; min-height: 80px; position: relative; transition: all 0.3s; }
    .timetable-slot:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    
    .slot-actions { position: absolute; top: 5px; right: 5px; display: flex; gap: 2px; opacity: 0; transition: opacity 0.3s; }
    .timetable-slot:hover .slot-actions { opacity: 1; }
    
    @media print {
        .no-print { display: none !important; }
        #sidebar { display: none !important; }
        #content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
        .card { border: none !important; box-shadow: none !important; }
        .timetable-table { border: 2px solid #000 !important; width: 100% !important; }
        .timetable-table th, .timetable-table td { border: 1px solid #000 !important; padding: 5px !important; }
        .timetable-slot { border-left: 5px solid #000 !important; box-shadow: none !important; }
        .navbar { display: none !important; }
        body { background: #fff !important; }
    }
</style>

<script>
function editSchedule(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_class').value = data.class;
    document.getElementById('edit_section').value = data.section;
    document.getElementById('edit_subject').value = data.subject;
    document.getElementById('edit_teacher').value = data.teacher_name;
    document.getElementById('edit_day').value = data.day;
    document.getElementById('edit_start').value = data.start_time;
    document.getElementById('edit_end').value = data.end_time;
    document.getElementById('edit_room').value = data.room;
    document.getElementById('edit_campus').value = data.campus;
    
    var editModal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
    editModal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
