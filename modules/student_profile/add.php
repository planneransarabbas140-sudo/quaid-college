<?php
// File: modules/student_profile/add.php - Add Student
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
    $error = 'Security check failed. Please refresh the page and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate unique student ID
    $student_id = generateUniqueId('STU');

    $campus = sanitizeInput($_POST['campus'] ?? '');
    $program = sanitizeInput($_POST['class'] ?? '');
    if ($campus === '' || $program === '') {
        $error = 'Please select campus and program.';
    } elseif (!in_array($program, array_column(getCampusPrograms($campus), 'name'), true)) {
        $error = 'Selected program is not available at the selected campus.';
    } else {
        $values = [
            'first_name' => sanitizeInput($_POST['first_name']),
            'last_name' => sanitizeInput($_POST['last_name']),
            'date_of_birth' => $_POST['date_of_birth'],
            'gender' => $_POST['gender'],
            'blood_group' => $_POST['blood_group'],
            'religion' => sanitizeInput($_POST['religion']),
            'nationality' => sanitizeInput($_POST['nationality']),
            'admission_date' => $_POST['admission_date'],
            'class' => $program,
            'section' => sanitizeInput($_POST['section']),
            'roll_number' => sanitizeInput($_POST['roll_number']),
            'guardian_name' => sanitizeInput($_POST['guardian_name']),
            'guardian_relation' => sanitizeInput($_POST['guardian_relation']),
            'guardian_phone' => sanitizeInput($_POST['guardian_phone']),
            'guardian_email' => sanitizeInput($_POST['guardian_email']),
            'address' => sanitizeInput($_POST['address']),
            'city' => sanitizeInput($_POST['city']),
            'state' => sanitizeInput($_POST['state']),
            'pin_code' => sanitizeInput($_POST['pin_code']),
            'emergency_contact' => sanitizeInput($_POST['emergency_contact']),
            'medical_info' => sanitizeInput($_POST['medical_info'])
        ];

        if (columnExists($db, 'students', 'student_id')) {
            $values['student_id'] = $student_id;
        }
        if (columnExists($db, 'students', 'registration_number')) {
            $values['registration_number'] = $student_id;
        }
        if (columnExists($db, 'students', 'campus_id')) {
            $values['campus_id'] = getOrCreateCampusId($db, $campus);
        } elseif (columnExists($db, 'students', 'campus')) {
            $values['campus'] = $campus;
        }
        if (columnExists($db, 'students', 'program_id')) {
            $values['program_id'] = getOrCreateProgramId($db, $program);
        }

        $columns = array_keys($values);
        $placeholders = array_map(fn($column) => ':' . $column, $columns);
        $stmt = $db->prepare("INSERT INTO students (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")");
    }

    if (!$error && $stmt->execute(array_combine($placeholders, array_values($values)))) {
        setFlashMessage('success', 'Student added successfully! Student ID: ' . $student_id);
        redirect('list.php');
    } elseif (!$error) {
        $error = 'Failed to add student. Please try again.';
    }
}

$page_title = "Add New Student";
include '../../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Add New Student</h6>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <?= csrfTokenInput() ?>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="mb-3">Personal Information</h6>
                    <div class="mb-3">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Blood Group</label>
                        <select name="blood_group" class="form-select">
                            <option value="">Select</option>
                            <option>A+</option><option>A-</option>
                            <option>B+</option><option>B-</option>
                            <option>O+</option><option>O-</option>
                            <option>AB+</option><option>AB-</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6 class="mb-3">Academic Information</h6>
                    <div class="mb-3">
                        <label>Campus *</label>
                        <select name="campus" id="campus" class="form-select" required>
                            <?php renderCampusOptions($_POST['campus'] ?? ''); ?>
                        </select>
                        <small class="text-muted">Choose campus first to see available programs.</small>
                    </div>
                    <div class="mb-3">
                        <label>Program *</label>
                        <select name="class" id="program" class="form-select" required>
                            <?php renderProgramOptions($_POST['class'] ?? ''); ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Section</label>
                        <select name="section" class="form-select">
                            <option>A</option><option>B</option><option>C</option><option>D</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Roll Number</label>
                        <input type="text" name="roll_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Admission Date</label>
                        <input type="date" name="admission_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="col-md-12">
                    <h6 class="mb-3">Guardian Information</h6>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label>Guardian Name *</label>
                        <input type="text" name="guardian_name" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label>Relation</label>
                        <select name="guardian_relation" class="form-select">
                            <option>Father</option><option>Mother</option><option>Guardian</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label>Phone *</label>
                        <input type="text" name="guardian_phone" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="guardian_email" class="form-control">
                    </div>
                </div>
                
                <div class="col-md-12">
                    <h6 class="mb-3">Address Information</h6>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label>City</label>
                        <input type="text" name="city" class="form-control">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label>State</label>
                        <input type="text" name="state" class="form-control">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label>PIN Code</label>
                        <input type="text" name="pin_code" class="form-control">
                    </div>
                </div>
                
                <div class="col-md-12">
                    <h6 class="mb-3">Other Information</h6>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label>Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label>Medical Information (Allergies, Conditions, etc.)</label>
                        <textarea name="medical_info" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Student</button>
                <a href="list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php echo getCampusProgramScript('campus', 'program'); ?>
<?php include '../../includes/footer.php'; ?>
