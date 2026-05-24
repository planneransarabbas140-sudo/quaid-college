<?php
// File: modules/student_profile/edit.php - Edit Student Details
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute([':id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    setFlashMessage('error', 'Student not found!');
    redirect('list.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
    $error = 'Security check failed. Please refresh the page and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $student['id'];

    // 1. Photo upload
    $upload_dir = dirname(__FILE__) . '/../../uploads/students/photos/';

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] === 0){
        try {
            $filename = saveUploadedFile($_FILES['photo'], $upload_dir, 'student_' . $student_id, ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024, true);
            $db->prepare("UPDATE students SET photo = ? WHERE id = ?")->execute([$filename, $student_id]);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // 2. CNIC and Certificate uploads (using absolute paths for consistency)
    $docs_dir = dirname(__FILE__) . '/../../uploads/students/documents/';

    if(isset($_FILES['cnic_copy']) && $_FILES['cnic_copy']['error'] === 0){
        try {
            $filename = saveUploadedFile($_FILES['cnic_copy'], $docs_dir, 'cnic_' . $student_id, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            $db->prepare("UPDATE students SET cnic_copy = ? WHERE id = ?")->execute([$filename, $student_id]);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if(isset($_FILES['certificate']) && $_FILES['certificate']['error'] === 0){
        try {
            $filename = saveUploadedFile($_FILES['certificate'], $docs_dir, 'cert_' . $student_id, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            $db->prepare("UPDATE students SET certificate = ? WHERE id = ?")->execute([$filename, $student_id]);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($error === '') {
    $data = [
        ':first_name' => sanitizeInput($_POST['first_name']),
        ':last_name' => sanitizeInput($_POST['last_name']),
        ':date_of_birth' => $_POST['date_of_birth'],
        ':gender' => $_POST['gender'],
        ':blood_group' => $_POST['blood_group'],
        ':religion' => sanitizeInput($_POST['religion']),
        ':nationality' => sanitizeInput($_POST['nationality']),
        ':admission_date' => $_POST['admission_date'],
        ':class' => sanitizeInput($_POST['class']),
        ':section' => sanitizeInput($_POST['section']),
        ':roll_number' => sanitizeInput($_POST['roll_number']),
        ':guardian_name' => sanitizeInput($_POST['guardian_name']),
        ':guardian_relation' => sanitizeInput($_POST['guardian_relation']),
        ':guardian_phone' => sanitizeInput($_POST['guardian_phone']),
        ':guardian_email' => sanitizeInput($_POST['guardian_email']),
        ':address' => sanitizeInput($_POST['address']),
        ':city' => sanitizeInput($_POST['city']),
        ':state' => sanitizeInput($_POST['state']),
        ':pin_code' => sanitizeInput($_POST['pin_code']),
        ':emergency_contact' => sanitizeInput($_POST['emergency_contact']),
        ':medical_info' => sanitizeInput($_POST['medical_info']),
        ':id' => $id,
    ];

    $stmt = $db->prepare("UPDATE students SET
        first_name = :first_name,
        last_name = :last_name,
        date_of_birth = :date_of_birth,
        gender = :gender,
        blood_group = :blood_group,
        religion = :religion,
        nationality = :nationality,
        admission_date = :admission_date,
        class = :class,
        section = :section,
        roll_number = :roll_number,
        guardian_name = :guardian_name,
        guardian_relation = :guardian_relation,
        guardian_phone = :guardian_phone,
        guardian_email = :guardian_email,
        address = :address,
        city = :city,
        state = :state,
        pin_code = :pin_code,
        emergency_contact = :emergency_contact,
        medical_info = :medical_info
    WHERE id = :id");

    if ($stmt->execute($data)) {
        setFlashMessage('success', 'Student information updated successfully!');
        redirect('view.php?id=' . $id);
    } else {
        $error = 'Failed to update student information. Please try again.';
    }
    }
}

$page_title = 'Edit Student';
include '../../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Edit Student</h6>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="row g-3" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <div class="col-md-12 mb-4">
                <div class="p-4 border rounded-3 bg-light">
                    <h5 class="text-navy fw-bold mb-3">Student Media & Documents</h5>
                    <div class="row align-items-end">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Student Photo</label>
                            <div class="d-flex align-items-center gap-3">
                                <img src="../../uploads/students/photos/<?= !empty($student['photo']) ? $student['photo'] : 'default.png' ?>" 
                                     alt="Student Photo" 
                                     id="photoPreview"
                                     style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid #4ec2b5;">
                                <div>
                                    <input type="file" name="photo" id="photo" 
                                           class="form-control" 
                                           accept="image/jpeg,image/png,image/jpg">
                                    <small class="text-muted">JPG, PNG — Max 2MB</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">B-Form / CNIC Copy</label>
                            <input type="file" name="cnic_copy" class="form-control" accept=".pdf,image/*">
                            <small class="text-muted">PDF or Image — Max 5MB</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Previous Certificate</label>
                            <input type="file" name="certificate" class="form-control" accept=".pdf,image/*">
                            <small class="text-muted">PDF or Image — Max 5MB</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">First Name *</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo $student['first_name']; ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name *</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo $student['last_name']; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?php echo $student['date_of_birth']; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <option value="Male" <?php echo $student['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $student['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $student['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-select">
                    <option value="">Select</option>
                    <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $group): ?>
                        <option value="<?php echo $group; ?>" <?php echo $student['blood_group'] === $group ? 'selected' : ''; ?>><?php echo $group; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Religion</label>
                <input type="text" name="religion" class="form-control" value="<?php echo $student['religion']; ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nationality</label>
                <input type="text" name="nationality" class="form-control" value="<?php echo $student['nationality']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Admission Date</label>
                <input type="date" name="admission_date" class="form-control" value="<?php echo $student['admission_date']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Class *</label>
                <input type="text" name="class" class="form-control" value="<?php echo $student['class']; ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Section</label>
                <input type="text" name="section" class="form-control" value="<?php echo $student['section']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Roll Number</label>
                <input type="text" name="roll_number" class="form-control" value="<?php echo $student['roll_number']; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Name *</label>
                <input type="text" name="guardian_name" class="form-control" value="<?php echo $student['guardian_name']; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Relation</label>
                <input type="text" name="guardian_relation" class="form-control" value="<?php echo $student['guardian_relation']; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Phone *</label>
                <input type="text" name="guardian_phone" class="form-control" value="<?php echo $student['guardian_phone']; ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Guardian Email</label>
                <input type="email" name="guardian_email" class="form-control" value="<?php echo $student['guardian_email']; ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?php echo $student['emergency_contact']; ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"><?php echo $student['address']; ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?php echo $student['city']; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="<?php echo $student['state']; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">PIN Code</label>
                <input type="text" name="pin_code" class="form-control" value="<?php echo $student['pin_code']; ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Medical Information</label>
                <textarea name="medical_info" class="form-control" rows="3"><?php echo $student['medical_info']; ?></textarea>
            </div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('photo').addEventListener('change', function(){
    const file = this.files[0];
    if(file){
        const reader = new FileReader();
        reader.onload = function(e){
            document.getElementById('photoPreview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
