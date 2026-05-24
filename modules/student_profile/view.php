<?php
// File: modules/student_profile/view.php - View Student Details
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
$stmt->execute([':id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    setFlashMessage('error', 'Student not found!');
    redirect('list.php');
}

if (getUserRole() === 'student' && (int)($student['user_id'] ?? 0) !== (int)getUserId()) {
    setFlashMessage('error', 'You are not allowed to view that student profile.');
    redirect('index.php');
}

$studentDisplayId = getStudentDisplayId($student);

$page_title = "Student Details - " . $student['first_name'] . ' ' . $student['last_name'];
include '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Profile Photo</h6>
            </div>
            <div class="card-body text-center">
                <?php 
                // Profile photo display
                $photo_path = '../../uploads/students/photos/' . ($student['photo'] ?? 'default.png');
                if(!file_exists(dirname(__FILE__) . '/../../uploads/students/photos/' . $student['photo'])){
                    $photo_path = '../../assets/images/default-student.png';
                }
                ?>
                <img src="<?= $photo_path ?>" class="img-fluid rounded-circle mb-3" style="width: 200px; height: 200px; object-fit: cover; border: 3px solid #4ec2b5;">
                <h5><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></h5>
                <p class="text-muted">Student ID: <?php echo htmlspecialchars($studentDisplayId); ?></p>
                <div class="mt-3">
                    <?php if (getUserRole() !== 'student'): ?>
                        <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-warning btn-sm">Edit Profile</a>
                    <?php endif; ?>
                    <a href="id_card.php?id=<?php echo $student['id']; ?>" target="_blank" class="btn btn-success btn-sm">Generate ID Card</a>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="../attendance/mark.php?student_id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">Mark Attendance</a>
                    <a href="../fee_management/collections.php?student_id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm">View Fee Details</a>
                    <a href="../examination/marks.php?student_id=<?php echo $student['id']; ?>" class="btn btn-success btn-sm">View Results</a>
                    <a href="../../result-cards.php?student_id=<?php echo $student['id']; ?>&class_id=<?php echo urlencode((string)$student['class']); ?>" class="btn btn-outline-success btn-sm">View Result Cards</a>
                    <a href="../library/issue.php?student_id=<?php echo $student['id']; ?>" class="btn btn-warning btn-sm">Library History</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Personal Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Full Name:</strong> <?php echo $student['first_name'] . ' ' . $student['last_name']; ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo date('d M Y', strtotime($student['date_of_birth'])); ?></p>
                        <p><strong>Gender:</strong> <?php echo $student['gender']; ?></p>
                        <p><strong>Blood Group:</strong> <?php echo $student['blood_group']; ?></p>
                        <p><strong>Religion:</strong> <?php echo $student['religion']; ?></p>
                        <p><strong>Nationality:</strong> <?php echo $student['nationality']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Class:</strong> <?php echo $student['class'] . '-' . $student['section']; ?></p>
                        <p><strong>Roll Number:</strong> <?php echo $student['roll_number']; ?></p>
                        <p><strong>Admission Date:</strong> <?php echo date('d M Y', strtotime($student['admission_date'])); ?></p>
                        <p><strong>Emergency Contact:</strong> <?php echo $student['emergency_contact']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Guardian Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo $student['guardian_name']; ?></p>
                        <p><strong>Relation:</strong> <?php echo $student['guardian_relation']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Phone:</strong> <?php echo $student['guardian_phone']; ?></p>
                        <p><strong>Email:</strong> <?php echo $student['guardian_email']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Address</h6>
            </div>
            <div class="card-body">
                <p><?php echo nl2br($student['address']); ?></p>
                <p><?php echo $student['city'] . ', ' . $student['state'] . ' - ' . $student['pin_code']; ?></p>
            </div>
        </div>
        
        <?php if ($student['medical_info']): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Medical Information</h6>
            </div>
            <div class="card-body">
                <p><?php echo nl2br($student['medical_info']); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
