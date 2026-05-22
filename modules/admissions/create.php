<?php
// File: modules/admissions/create.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = sanitizeInput($_POST['gender']);
    $admission_date = $_POST['admission_date'];
    $class = sanitizeInput($_POST['class']);
    $section = sanitizeInput($_POST['section']);
    $guardian_name = sanitizeInput($_POST['guardian_name']);
    $guardian_phone = sanitizeInput($_POST['guardian_phone']);
    $address = sanitizeInput($_POST['address']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $transaction_id = sanitizeInput($_POST['transaction_id']);
    $admission_fee = floatval($_POST['admission_fee']);
    $campus = sanitizeInput($_POST['campus']);
    
    // Auto-generate student ID: QGC-YYYY-XXXX
    $year = date('Y', strtotime($admission_date));
    $stmt = $db->query("SELECT COUNT(*) as count FROM students WHERE YEAR(admission_date) = $year");
    $count = $stmt->fetch()['count'] + 1;
    $student_id = "QGC-" . $year . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    
    try {
        if ($campus === '' || $class === '') {
            throw new Exception('Please select campus and program.');
        }
        if (!in_array($class, array_column(getCampusPrograms($campus), 'name'), true)) {
            throw new Exception('Selected program is not available at the selected campus.');
        }

        $values = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'date_of_birth' => $date_of_birth,
            'gender' => $gender,
            'admission_date' => $admission_date,
            'class' => $class,
            'section' => $section,
            'guardian_name' => $guardian_name,
            'guardian_phone' => $guardian_phone,
            'address' => $address,
            'guardian_relation' => sanitizeInput($_POST['guardian_relation'] ?? 'Father'),
            'guardian_email' => sanitizeInput($_POST['guardian_email'] ?? ''),
            'religion' => sanitizeInput($_POST['religion'] ?? 'Islam'),
            'nationality' => sanitizeInput($_POST['nationality'] ?? 'Pakistani'),
            'blood_group' => sanitizeInput($_POST['blood_group'] ?? ''),
            'roll_number' => sanitizeInput($_POST['roll_number'] ?? ''),
            'city' => sanitizeInput($_POST['city'] ?? ''),
            'state' => sanitizeInput($_POST['state'] ?? 'Punjab'),
            'pin_code' => sanitizeInput($_POST['pin_code'] ?? ''),
            'emergency_contact' => sanitizeInput($_POST['emergency_contact'] ?? ''),
            'medical_info' => sanitizeInput($_POST['medical_info'] ?? ''),
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
            $values['program_id'] = getOrCreateProgramId($db, $class);
        }
        foreach (['payment_method' => $payment_method, 'transaction_id' => $transaction_id, 'admission_fee' => $admission_fee] as $column => $value) {
            if (columnExists($db, 'students', $column)) {
                $values[$column] = $value;
            }
        }

        $columns = array_keys($values);
        $placeholders = array_map(fn($column) => ':' . $column, $columns);
        $stmt = $db->prepare("INSERT INTO students (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")");
        $stmt->execute(array_combine($placeholders, array_values($values)));
        
        $success = "Student Admission successful! ID: " . $student_id;
    } catch (Exception $e) {
        $error = "Error during admission: " . $e->getMessage();
    }
}

$page_title = "New Admission";
include '../../includes/header.php';
?>
<script src="../../assets/js/payment_helper.js"></script>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">Student Admission Form</h2>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Admissions</a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <h5 class="text-primary mb-3">Personal Details</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" name="date_of_birth" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 mb-3">Academic Details</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Admission Date *</label>
                            <input type="date" class="form-control" name="admission_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Section *</label>
                            <select class="form-select" name="section" required>
                                <option value="">-- Select --</option>
                                <option value="A">Section A</option>
                                <option value="B">Section B</option>
                                <option value="C">Section C</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Campus *</label>
                            <select class="form-select" name="campus" id="campus" required>
                                <?php renderCampusOptions($_POST['campus'] ?? ''); ?>
                            </select>
                            <small class="text-muted">Choose campus first.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Program *</label>
                            <select class="form-select" name="class" id="program" required>
                                <?php renderProgramOptions($_POST['class'] ?? ''); ?>
                            </select>
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 mb-3">Guardian Details</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian Name *</label>
                            <input type="text" class="form-control" name="guardian_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian Phone *</label>
                            <input type="text" class="form-control" name="guardian_phone" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Address *</label>
                        <textarea class="form-control" name="address" rows="3" required></textarea>
                    </div>

                    <h5 class="text-primary mt-4 mb-3">Admission Fee & Payment</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Admission Fee (PKR) *</label>
                            <input type="number" class="form-control" name="admission_fee" value="5000" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select name="payment_method" id="payment_method_select" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Online Payment">Online Payment</option>
                                <option value="Cheque">Cheque</option>
                                <option value="EasyPaisa">EasyPaisa</option>
                                <option value="JazzCash">JazzCash</option>
                                <option value="Card/ATM">Card/ATM</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="tid_field" style="display: none;">
                            <label class="form-label">Transaction ID *</label>
                            <input type="text" name="transaction_id" class="form-control" placeholder="Enter Reference ID">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Submit Admission</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setupPaymentMethod('payment_method_select', 'tid_field');
});
</script>
<?php echo getCampusProgramScript('campus', 'program'); ?>

<?php include '../../includes/footer.php'; ?>
