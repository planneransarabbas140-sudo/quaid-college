<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../config/db.php';
$database = new Database();
$db = $database->getConnection();
ensureAdmissionApplicationsTable($db);

if (!$db) {
    die("<div style='background:red;color:white;padding:15px;'>? DB Connection FAILED</div>");
}

$success = false;
$error   = false;
$app_id  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        requireCsrfToken();

        // -- 1. Validate required fields ----------------------
        $required = ['full_name','father_name','dob','gender',
                     'cnic','phone','address','prev_institution',
                     'program','campus'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // -- 2. Sanitize ---------------------------------------
        $data = [];
        foreach ($_POST as $k => $v) {
            $data[$k] = sanitizeInput($v);
        }

        if (!in_array($data['program'], array_column(getCampusPrograms($data['campus']), 'name'), true)) {
            throw new Exception("Selected program is not available at the selected campus.");
        }

        // -- 3. File uploads -----------------------------------
        $upload_dir = __DIR__ . '/../../uploads/admissions/';
        $fileRules = [
            'photo' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'max' => 2 * 1024 * 1024,
                'image' => true,
            ],
            'matric_certificate' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
                'max' => 5 * 1024 * 1024,
                'image' => false,
            ],
            'cnic_copy' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
                'max' => 5 * 1024 * 1024,
                'image' => false,
            ],
        ];
        $uploaded_paths = [];

        foreach ($fileRules as $fk => $rule) {
            if (!isset($_FILES[$fk])) {
                throw new Exception(str_replace('_', ' ', $fk) . " is required.");
            }

            $uploaded_paths[$fk] = saveUploadedFile(
                $_FILES[$fk],
                $upload_dir,
                $fk,
                $rule['extensions'],
                $rule['max'],
                $rule['image']
            );
        }

        // -- 4. Duplicate CNIC check ---------------------------
        $dup = $db->prepare("SELECT id FROM admission_applications WHERE cnic = :cnic AND status = 'pending' LIMIT 1");
        $dup->execute([':cnic' => $data['cnic']]);
        if ($dup->rowCount() > 0) {
            throw new Exception("A pending application with this CNIC already exists.");
        }

        // -- 5. Generate Application ID ------------------------
        $app_id = generateUniqueId('QAC');

        // -- 6. INSERT into admission_applications table --------
        $sql = "INSERT INTO admission_applications (
                    application_id, full_name, father_name, dob, gender,
                    cnic, religion, nationality,
                    phone, whatsapp, email, address,
                    prev_institution, matric_roll, matric_year,
                    matric_total, matric_obtained, matric_grade, board_name,
                    program, campus, session,
                    photo, matric_certificate, cnic_copy,
                    payment_method, transaction_id, admission_fee,
                    status, created_at
                ) VALUES (
                    :application_id, :full_name, :father_name, :dob, :gender,
                    :cnic, :religion, :nationality,
                    :phone, :whatsapp, :email, :address,
                    :prev_institution, :matric_roll, :matric_year,
                    :matric_total, :matric_obtained, :matric_grade, :board_name,
                    :program, :campus, :session,
                    :photo, :matric_certificate, :cnic_copy,
                    :payment_method, :transaction_id, :admission_fee,
                    'pending', NOW()
                )";

        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':application_id'     => $app_id,
            ':full_name'          => $data['full_name'],
            ':father_name'        => $data['father_name'],
            ':dob'                => $data['dob'],
            ':gender'             => $data['gender'],
            ':cnic'               => $data['cnic'],
            ':religion'           => $data['religion']        ?? 'Islam',
            ':nationality'        => $data['nationality']     ?? 'Pakistani',
            ':phone'              => $data['phone'],
            ':whatsapp'           => $data['whatsapp']        ?? '',
            ':email'              => $data['email']           ?? '',
            ':address'            => $data['address'],
            ':prev_institution'   => $data['prev_institution'],
            ':matric_roll'        => $data['matric_roll']     ?? '',
            ':matric_year'        => $data['matric_year']     ?? '',
            ':matric_total'       => $data['matric_total']    ?? 1100,
            ':matric_obtained'    => $data['matric_obtained'] ?? 0,
            ':matric_grade'       => $data['matric_grade']    ?? '',
            ':board_name'         => $data['board_name']      ?? '',
            ':program'            => $data['program'],
            ':campus'             => $data['campus'],
            ':session'            => $data['session']         ?? '2026-2028',
            ':photo'              => $uploaded_paths['photo']              ?? '',
            ':matric_certificate' => $uploaded_paths['matric_certificate'] ?? '',
            ':cnic_copy'          => $uploaded_paths['cnic_copy']          ?? '',
            ':payment_method'     => $data['payment_method']  ?? 'Cash',
            ':transaction_id'     => $data['transaction_id']  ?? 'N/A',
            ':admission_fee'      => $data['admission_fee']   ?? 5000,
        ]);

        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception("Admission application save failed: " . implode(' | ', $stmt->errorInfo()));
        }

        $success = true;

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Admission Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Admission | Quaid-e-Azam Group of Colleges</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="../../assets/js/payment_helper.js?v=2"></script>

    <style>
        :root {
            --teal: #4ec2b5;
            --teal-dark: #35a99c;
            --navy: #0f2d48;
            --navy-mid: #1a4060;
            --white: #ffffff;
            --font-display: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
            --font-mono: 'Space Mono', monospace;
        }

        body {
            font-family: var(--font-body);
            background-color: #f8fafc;
            color: var(--navy);
        }

        .admission-header {
            background: var(--navy);
            padding: 60px 0;
            color: white;
            text-align: center;
        }

        .section-badge {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            background: rgba(78, 194, 181, 0.15);
            color: var(--teal);
            padding: 6px 16px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 15px;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(15, 45, 72, 0.08);
            border: none;
            margin-top: -40px;
            overflow: hidden;
        }

        /* Multi-step Progress Bar */
        .progress-container {
            padding: 40px 0 20px;
            background: #fdfdfd;
            border-bottom: 1px solid #eee;
        }

        .step-progress {
            display: flex;
            justify-content: space-between;
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .step-progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            transform: translateY(-50%);
            z-index: 1;
        }

        .step-item {
            position: relative;
            z-index: 2;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            font-weight: 700;
            color: #94a3b8;
            transition: all 0.3s ease;
        }

        .step-item.active {
            border-color: var(--teal);
            color: var(--teal);
            box-shadow: 0 0 0 5px rgba(78, 194, 181, 0.1);
        }

        .step-item.completed {
            background: var(--teal);
            border-color: var(--teal);
            color: white;
        }

        .step-label {
            position: absolute;
            top: 50px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            color: #94a3b8;
        }

        .step-item.active .step-label { color: var(--navy); font-weight: 700; }

        .form-step { display: none; padding: 40px; }
        .form-step.active { display: block; }

        .form-label {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--navy-mid);
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            padding: 12px 18px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 4px rgba(78, 194, 181, 0.1);
        }

        .btn-next, .btn-submit {
            background: var(--teal);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-next:hover, .btn-submit:hover {
            background: var(--teal-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(78, 194, 181, 0.3);
            color: white;
        }

        .btn-back {
            background: #f1f5f9;
            color: var(--navy);
            border: none;
            padding: 14px 40px;
            border-radius: 12px;
            font-weight: 700;
        }

        .success-card {
            text-align: center;
            padding: 60px 40px;
        }

        .success-icon {
            font-size: 4rem;
            color: var(--teal);
            margin-bottom: 25px;
        }

        .app-id-box {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 15px;
            font-family: var(--font-mono);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--navy);
            margin: 20px 0;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="admission-header">
        <div class="container">
            <span class="section-badge" style="background:rgba(255,255,255,0.1); color:var(--teal);">Admissions 2026-2028</span>
            <h1 style="font-family: var(--font-display); font-weight: 900; font-size: 3rem;">Apply for Admission</h1>
            <p style="opacity: 0.8; max-width: 600px; margin: 15px auto;">Join the legacy of excellence. Fill out the form below to start your academic journey with Quaid-e-Azam Group of Colleges.</p>
            <a href="../../index.php" class="text-white text-decoration-none" style="font-size: 0.85rem; opacity: 0.6;">
                <i class="fas fa-arrow-left me-1"></i> Back to Home
            </a>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card form-card">
                    
                    <?php if ($success): ?>
                        <div class="success-card">
                            <i class="fas fa-check-circle success-icon"></i>
                            <h2 style="font-family: var(--font-display); font-weight: 700;">Application Submitted!</h2>
                            <p class="text-muted">Your admission application has been submitted. Please wait for admin approval.</p>
                            <div class="app-id-box"><?= $app_id ?></div>
                            <p class="small text-muted">Please save this Application ID for future reference.</p>
                            <div class="mt-4">
                                <a href="apply.php" class="btn btn-outline-primary px-4 py-2 rounded-pill me-2">Submit New</a>
                                <a href="../../index.php" class="btn btn-primary px-4 py-2 rounded-pill" style="background:var(--navy);">Return Home</a>
                            </div>
                        </div>
                    <?php else: ?>

                        <div class="progress-container">
                            <div class="step-progress">
                                <div class="step-item active" id="step-1-dot">1 <span class="step-label">Personal</span></div>
                                <div class="step-item" id="step-2-dot">2 <span class="step-label">Academic</span></div>
                                <div class="step-item" id="step-3-dot">3 <span class="step-label">Documents</span></div>
                                <div class="step-item" id="step-4-dot">4 <span class="step-label">Payment</span></div>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger mx-4 mt-4"><?= $error ?></div>
                        <?php endif; ?>

                        <form id="admissionForm" method="POST" enctype="multipart/form-data">
                            <?= csrfTokenInput() ?>
                            
                            <!-- STEP 1: PERSONAL -->
                            <div class="form-step active" id="step-1">
                                <h4 class="mb-4" style="font-family: var(--font-display); font-weight: 700; border-left: 4px solid var(--teal); padding-left: 15px;">Personal Information</h4>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="full_name" class="form-control" required placeholder="As per Matric Result">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Father's Name *</label>
                                        <input type="text" name="father_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth *</label>
                                        <input type="date" name="dob" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gender *</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">CNIC / B-Form Number *</label>
                                        <input type="text" name="cnic" class="form-control" required placeholder="32403-XXXXXXX-X">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Religion</label>
                                        <input type="text" name="religion" class="form-control" value="Islam">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Nationality</label>
                                        <input type="text" name="nationality" class="form-control" value="Pakistani">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="text" name="phone" class="form-control" required placeholder="03000000000">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">WhatsApp Number</label>
                                        <input type="text" name="whatsapp" class="form-control">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Home Address *</label>
                                        <textarea name="address" class="form-control" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="mt-5 text-end">
                                    <button type="button" class="btn-next" onclick="nextStep(2)">Next Step <i class="fas fa-arrow-right ms-2"></i></button>
                                </div>
                            </div>

                            <!-- STEP 2: ACADEMIC -->
                            <div class="form-step" id="step-2">
                                <h4 class="mb-4" style="font-family: var(--font-display); font-weight: 700; border-left: 4px solid var(--teal); padding-left: 15px;">Academic Information</h4>
                                <div class="row g-4">
                                    <div class="col-md-12">
                                        <label class="form-label">Previous Institution Name *</label>
                                        <input type="text" name="prev_institution" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Matric Roll Number</label>
                                        <input type="text" name="matric_roll" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Matric Passing Year</label>
                                        <select name="matric_year" class="form-select">
                                            <?php for($y=2026; $y>=2020; $y--) echo "<option value='$y'>$y</option>"; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Board Name</label>
                                        <select name="board_name" class="form-select">
                                            <option value="BISE DG Khan">BISE DG Khan</option>
                                            <option value="BISE Bahawalpur">BISE Bahawalpur</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Total Marks</label>
                                        <input type="number" name="matric_total" class="form-control" value="1100">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Obtained Marks</label>
                                        <input type="number" name="matric_obtained" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Grade / Division</label>
                                        <select name="matric_grade" class="form-select">
                                            <option value="A+">A+</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                        </select>
                                    </div>

                                    <hr class="my-4">

                                    <div class="col-md-4">
                                        <label class="form-label">Campus Selection *</label>
                                        <select name="campus" id="campus" class="form-select" required>
                                            <?php renderCampusOptions($_POST['campus'] ?? ''); ?>
                                        </select>
                                        <small class="text-muted">Choose campus first to load programs.</small>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label" style="color:var(--teal);">Program Applying For *</label>
                                        <select name="program" id="program" class="form-select" required style="border-color:var(--teal);">
                                            <?php renderProgramOptions($_POST['program'] ?? ''); ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Session</label>
                                        <input type="text" name="session" class="form-control" value="2026-2028" readonly>
                                    </div>
                                </div>
                                <div class="mt-5 d-flex justify-content-between">
                                    <button type="button" class="btn-back" onclick="nextStep(1)"><i class="fas fa-arrow-left me-2"></i> Back</button>
                                    <button type="button" class="btn-next" onclick="nextStep(3)">Next Step <i class="fas fa-arrow-right ms-2"></i></button>
                                </div>
                            </div>

                            <!-- STEP 3: DOCUMENTS -->
                            <div class="form-step" id="step-3">
                                <h4 class="mb-4" style="font-family: var(--font-display); font-weight: 700; border-left: 4px solid var(--teal); padding-left: 15px;">Documents Upload</h4>
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <label class="form-label">Student Photo *</label>
                                        <div class="p-4 border rounded-3 text-center bg-light">
                                            <i class="fas fa-user-circle mb-2 text-muted" style="font-size: 2rem;"></i>
                                            <input type="file" name="photo" class="form-control" required accept="image/*">
                                            <small class="text-muted d-block mt-2">JPG/PNG, Max 2MB</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Matric Certificate *</label>
                                        <div class="p-4 border rounded-3 text-center bg-light">
                                            <i class="fas fa-file-pdf mb-2 text-muted" style="font-size: 2rem;"></i>
                                            <input type="file" name="matric_certificate" class="form-control" required accept="image/*,application/pdf">
                                            <small class="text-muted d-block mt-2">PDF/Image, Max 5MB</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">CNIC / B-Form Copy *</label>
                                        <div class="p-4 border rounded-3 text-center bg-light">
                                            <i class="fas fa-id-card mb-2 text-muted" style="font-size: 2rem;"></i>
                                            <input type="file" name="cnic_copy" class="form-control" required accept="image/*,application/pdf">
                                            <small class="text-muted d-block mt-2">PDF/Image, Max 5MB</small>
                                        </div>
                                    </div>
                                </div> <!-- row g-4 closed -->

                                <div class="mt-5 d-flex justify-content-between">
                                    <button type="button" class="btn-back" onclick="nextStep(2)"><i class="fas fa-arrow-left me-2"></i> Back</button>
                                    <button type="button" class="btn-next" onclick="nextStep(4)">Next Step <i class="fas fa-arrow-right ms-2"></i></button>
                                </div>
                            </div> <!-- step-3 closed -->

                            <!-- STEP 4: PAYMENT -->
                            <div class="form-step" id="step-4">
                                <h4 class="mb-4" style="font-family: var(--font-display); font-weight: 700; border-left: 4px solid var(--teal); padding-left: 15px;">Admission Fee Payment</h4>
                                <div class="alert alert-info border-0 shadow-sm mb-4">
                                    <i class="fas fa-info-circle me-2"></i> Admission Fee: <strong>PKR 5,000</strong>
                                    <input type="hidden" name="admission_fee" value="5000">
                                </div>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Payment Method *</label>
                                        <select name="payment_method" id="pay_method" class="form-select" required>
                                            <option value="Cash">Cash</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Online Payment">Online Payment</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="EasyPaisa">EasyPaisa</option>
                                            <option value="JazzCash">JazzCash</option>
                                            <option value="Card/ATM">Card/ATM</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="tid_field" style="display: none;">
                                        <label class="form-label">Transaction ID / Reference *</label>
                                        <input type="text" name="transaction_id" class="form-control" placeholder="Enter Transaction ID">
                                    </div>
                                    
                                    <div class="col-12 mt-5">
                                        <div class="form-check p-4 border rounded-3" style="background: rgba(78, 194, 181, 0.05); border-color: var(--teal) !important;">
                                            <input class="form-check-input ms-0" type="checkbox" id="declaration" required>
                                            <label class="form-check-label ms-2" for="declaration">
                                                <strong>Declaration:</strong> I hereby declare that all the information provided above is correct to the best of my knowledge. I understand that any false information may lead to the cancellation of my admission.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-5 d-flex justify-content-between">
                                    <button type="button" class="btn-back" onclick="nextStep(3)"><i class="fas fa-arrow-left me-2"></i> Back</button>
                                    <button type="submit" class="btn-submit">Submit Application <i class="fas fa-paper-plane ms-2"></i></button>
                                </div>
                            </div>

                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function nextStep(step) {
            // Basic validation for Step 1
            if (step === 2) {
                const step1 = document.getElementById('step-1');
                const inputs = step1.querySelectorAll('[required]');
                let valid = true;
                inputs.forEach(input => {
                    if (!input.value) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                if (!valid) return alert("Please fill all required fields in Step 1.");
            }

            // Basic validation for Step 2
            if (step === 3) {
                const step2 = document.getElementById('step-2');
                const inputs = step2.querySelectorAll('[required]');
                let valid = true;
                inputs.forEach(input => {
                    if (!input.value) {
                        input.classList.add('is-invalid');
                        valid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                if (!valid) return alert("Please fill all required fields in Step 2.");
            }

            // Hide all steps
            document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach(s => s.classList.remove('active'));

            // Show current step
            document.getElementById('step-' + step).classList.add('active');
            
            // Update dots
            for(let i=1; i<=4; i++) {
                const dot = document.getElementById('step-' + i + '-dot');
                if (i < step) dot.classList.add('completed');
                else dot.classList.remove('completed');
                
                if (i === step) dot.classList.add('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupPaymentMethod('pay_method', 'tid_field');
        });
    </script>
    <?php echo getCampusProgramScript('campus', 'program'); ?>
</body>
</html>
