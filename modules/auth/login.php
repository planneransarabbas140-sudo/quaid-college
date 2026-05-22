<?php
// File: modules/auth/login.php
require_once '../../config/db.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('../../dashboard.php');
}

$error = '';
$success = '';
$username_val = '';
$selected_role = sanitizeInput($_POST['selected_role'] ?? 'student');

function login_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function ensurePendingSignupsTable(PDO $db) {
    $db->prepare("CREATE TABLE IF NOT EXISTS pending_signups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(50),
        username VARCHAR(100),
        role VARCHAR(50),
        campus VARCHAR(100),
        extra_info TEXT,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        requested_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    $action = $_POST['action'] ?? 'login';

    if ($action === 'signup') {
        try {
            ensurePendingSignupsTable($db);

            $full_name = sanitizeInput($_POST['full_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $username = sanitizeInput($_POST['signup_username'] ?? '');
            $role = sanitizeInput($_POST['signup_role'] ?? 'student');
            $campus = sanitizeInput($_POST['campus'] ?? '');
            $password = $_POST['signup_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $selected_role = $role;

            if (!in_array($role, ['student', 'teacher'], true)) {
                throw new Exception('Only Student and Teacher accounts can request signup.');
            }
            if ($full_name === '' || $email === '' || $username === '' || $campus === '') {
                throw new Exception('Please fill all required signup fields.');
            }
            if ($password === '' || $password !== $confirm_password) {
                throw new Exception('Password and confirm password must match.');
            }

            $extra = [];
            if ($role === 'student') {
                $extra['class'] = sanitizeInput($_POST['class'] ?? '');
                $extra['roll_number'] = sanitizeInput($_POST['roll_number'] ?? '');
            }
            if ($role === 'teacher') {
                $extra['subject_specialization'] = sanitizeInput($_POST['subject_specialization'] ?? '');
            }

            $stmt = $db->prepare("INSERT INTO pending_signups (full_name, email, phone, username, role, campus, extra_info) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$full_name, $email, $phone, $username, $role, $campus, json_encode($extra, JSON_UNESCAPED_UNICODE)]);
            $success = 'Your account request has been submitted. Admin will approve it shortly.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $username_val = sanitizeInput($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username_val) || empty($password)) {
            $error = "Please enter both username/email and password.";
        } else {
            try {
                $stmt = $db->prepare("SELECT * FROM users WHERE (username = :u OR email = :u) LIMIT 1");
                $stmt->execute([':u' => $username_val]);
                $user = $stmt->fetch();

                $isActive = true;
                if ($user && columnExists($db, 'users', 'is_active')) {
                    $isActive = (int)($user['is_active'] ?? 0) === 1;
                }
                if ($user && columnExists($db, 'users', 'status')) {
                    $isActive = $isActive && strtolower((string)($user['status'] ?? 'active')) === 'active';
                }
                $isApprovedStudent = true;
                if ($user && strtolower((string)$user['role']) === 'student' && tableExists($db, 'students') && columnExists($db, 'students', 'user_id')) {
                    $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                    $stmt->execute([(int)$user['id']]);
                    $isApprovedStudent = (bool)$stmt->fetch();
                }

                if ($user && password_verify($password, $user['password']) && $isActive && $isApprovedStudent) {
                    session_regenerate_id(true);

                    // Set Session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = strtolower($user['role']);
                    $_SESSION['user_role'] = strtolower($user['role']);
                    $_SESSION['user_campus'] = $user['campus'] ?? 'Rajanpur';

                    redirect('../../dashboard.php');
                } else {
                    $error = "Invalid username/password or your student admission is not approved yet.";
                }
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

$roleMeta = [
    'student' => ['label' => 'Student', 'icon' => 'fa-graduation-cap', 'desc' => 'Access results, attendance & fee', 'class' => 'role-student'],
    'teacher' => ['label' => 'Teacher', 'icon' => 'fa-chalkboard-user', 'desc' => 'Manage classes, marks & attendance', 'class' => 'role-teacher'],
    'admin' => ['label' => 'Admin', 'icon' => 'fa-gear', 'desc' => 'Full system control', 'class' => 'role-admin'],
    'staff' => ['label' => 'Staff', 'icon' => 'fa-briefcase', 'desc' => 'HR, payroll & department access', 'class' => 'role-staff'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Portal | Quaid-e-Azam Group of Colleges</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --teal: #4ec2b5;
            --teal-dark: #35a99c;
            --navy: #0f2d48;
            --navy-mid: #1a4060;
            --purple: #6f42c1;
            --green: #198754;
            --font-display: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
            --font-mono: 'Space Mono', monospace;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font-body);
            background: #f8fafc;
            color: #13263a;
        }
        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(360px, 44%) 1fr;
        }
        .brand-panel {
            position: relative;
            min-height: 100vh;
            padding: clamp(32px, 5vw, 64px);
            color: #fff;
            background:
                linear-gradient(135deg, rgba(15,45,72,.94), rgba(15,45,72,.82)),
                url('<?= login_h(BASE_URL) ?>assets/images/banner.jpg') center/cover;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .brand-panel::after {
            content: '';
            position: absolute;
            inset: auto -80px -80px auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(78,194,181,.14);
        }
        .brand-logo {
            width: 82px;
            height: 82px;
            object-fit: contain;
            background: #fff;
            border-radius: 18px;
            padding: 8px;
            margin-bottom: 24px;
        }
        .brand-title {
            font-family: var(--font-display);
            font-size: clamp(2.6rem, 5vw, 4.8rem);
            line-height: .96;
            font-weight: 900;
            margin-bottom: 18px;
        }
        .brand-kicker {
            color: var(--teal);
            font-family: var(--font-mono);
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 700;
            font-size: .82rem;
        }
        .brand-copy {
            color: rgba(255,255,255,.78);
            max-width: 520px;
            line-height: 1.8;
            margin-top: 22px;
        }
        .brand-stat {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        .brand-stat div {
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 16px;
            padding: 16px;
            background: rgba(255,255,255,.06);
        }
        .brand-stat strong { display: block; font-size: 1.35rem; color: #fff; }
        .brand-stat span { color: rgba(255,255,255,.68); font-size: .82rem; }
        .form-panel {
            min-height: 100vh;
            padding: clamp(24px, 4vw, 60px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            width: min(100%, 620px);
            background: #fff;
            border: 1px solid #e8eef5;
            border-radius: 24px;
            box-shadow: 0 28px 80px rgba(15,45,72,.10);
            padding: clamp(24px, 4vw, 44px);
        }
        .step-panel {
            opacity: 1;
            transform: translateY(0);
            transition: opacity .25s ease, transform .25s ease;
        }
        .step-panel.is-hidden {
            display: none !important;
            opacity: 0;
            transform: translateY(12px);
        }
        .auth-heading {
            font-family: var(--font-display);
            font-weight: 900;
            color: var(--navy);
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 8px;
        }
        .auth-subtitle { color: #64748b; margin-bottom: 28px; }
        .role-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .role-card {
            border: 1px solid #e4ebf3;
            border-radius: 18px;
            background: #fff;
            padding: 22px;
            text-align: left;
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
            cursor: pointer;
        }
        .role-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 44px rgba(15,45,72,.12);
            border-color: rgba(78,194,181,.55);
        }
        .role-icon {
            width: 52px;
            height: 52px;
            display: inline-grid;
            place-items: center;
            border-radius: 16px;
            color: #fff;
            margin-bottom: 18px;
            font-size: 1.35rem;
        }
        .role-student .role-icon, .badge-student { background: var(--teal); }
        .role-teacher .role-icon, .badge-teacher { background: var(--navy); }
        .role-admin .role-icon, .badge-admin { background: var(--purple); }
        .role-staff .role-icon, .badge-staff { background: var(--green); }
        .role-card h3 { color: var(--navy); font-weight: 900; margin-bottom: 6px; font-size: 1.18rem; }
        .role-card p { color: #64748b; margin: 0; font-size: .92rem; line-height: 1.5; }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            color: #fff;
            padding: 8px 14px;
            font-weight: 800;
            margin-bottom: 18px;
        }
        .form-label {
            font-family: var(--font-mono);
            color: var(--navy);
            font-size: .76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .form-control, .form-select {
            min-height: 52px;
            border-radius: 14px;
            border-color: #dce5ef;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 .22rem rgba(78,194,181,.18);
        }
        .btn-teal {
            background: var(--teal);
            border-color: var(--teal);
            color: #fff;
            border-radius: 14px;
            min-height: 54px;
            font-weight: 800;
        }
        .btn-teal:hover { background: var(--teal-dark); border-color: var(--teal-dark); color: #fff; }
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #94a3b8;
            margin: 24px 0;
        }
        .divider::before, .divider::after { content: ''; height: 1px; background: #e5edf5; flex: 1; }
        .password-toggle {
            border-radius: 0 14px 14px 0 !important;
            border-color: #dce5ef;
            min-width: 54px;
        }
        .text-teal { color: var(--teal) !important; }
        .signup-panel {
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid #e8eef5;
            display: none;
        }
        @media (max-width: 992px) {
            .auth-shell { grid-template-columns: 1fr; }
            .brand-panel { min-height: auto; }
            .brand-stat { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .form-panel { min-height: auto; }
        }
        @media (max-width: 560px) {
            .role-grid, .brand-stat { grid-template-columns: 1fr; }
            .auth-card { border-radius: 18px; }
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="brand-panel">
            <div>
                <img src="<?= login_h(BASE_URL) ?>assets/images/qgc-logo.png" alt="Quaid-e-Azam Group of Colleges" class="brand-logo">
                <div class="brand-kicker">Quaid College System</div>
                <h1 class="brand-title">Learning, managed with clarity.</h1>
                <p class="brand-copy">Access your Quaid-e-Azam Group of Colleges portal for academics, staff workflows, attendance, fees, payroll, and campus operations.</p>
            </div>
            <div class="brand-stat">
                <div><strong>3</strong><span>Campuses</span></div>
                <div><strong>24/7</strong><span>Portal Access</span></div>
                <div><strong>ERP</strong><span>College System</span></div>
            </div>
        </section>

        <section class="form-panel">
            <div class="auth-card">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= login_h($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= login_h($success) ?></div>
                <?php endif; ?>

                <div id="role-selection" class="step-panel">
                    <h2 class="auth-heading">Choose Portal</h2>
                    <p class="auth-subtitle">Select your role to continue.</p>
                    <div class="role-grid">
                        <?php foreach ($roleMeta as $role => $meta): ?>
                            <button type="button" class="role-card <?= login_h($meta['class']) ?>" onclick="selectRole('<?= login_h($role) ?>')">
                                <span class="role-icon"><i class="fas <?= login_h($meta['icon']) ?>"></i></span>
                                <h3><?= login_h($meta['label']) ?></h3>
                                <p><?= login_h($meta['desc']) ?></p>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="login-form-section" class="step-panel is-hidden">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill mb-4" onclick="goBack()">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </button>
                    <div id="role-badge" class="role-badge badge-student">
                        <i class="fas fa-user"></i><span id="role-display">Student</span>
                    </div>
                    <h2 class="auth-heading">Welcome back, <span id="role-title">Student</span></h2>
                    <p class="auth-subtitle">Sign in to your <span id="role-subtitle">Student</span> account</p>

                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="selected_role" id="selected_role" value="<?= login_h($selected_role) ?>">
                        <div class="mb-3">
                            <label class="form-label">Username or Email</label>
                            <input type="text" name="username_email" class="form-control" value="<?= login_h($username_val) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="login_password" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary password-toggle" onclick="togglePassword('login_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="forgot_password.php" class="text-decoration-none fw-bold text-teal">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-teal w-100">Sign In <i class="fas fa-arrow-right-to-bracket ms-2"></i></button>
                    </form>

                    <div class="divider">or</div>
                    <div class="text-center">
                        <button type="button" class="btn btn-link text-decoration-none fw-bold" onclick="toggleSignup()">Don't have an account?</button>
                    </div>

                    <div id="signup-section" class="signup-panel">
                        <div id="restricted-signup" class="alert alert-warning d-none">
                            Admin/Staff accounts are created by the administrator. Contact: admin@quaid.edu.pk
                        </div>
                        <form method="POST" id="signup-form">
                            <input type="hidden" name="action" value="signup">
                            <input type="hidden" name="signup_role" id="signup_role" value="student">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" id="full_name" class="form-control" oninput="suggestUsername()" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="signup_username" id="signup_username" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="signup_password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Campus</label>
                                    <select name="campus" class="form-select" required>
                                        <option value="">Select Campus</option>
                                        <option value="Fazilpur">Fazilpur</option>
                                        <option value="Kot Mithan">Kot Mithan</option>
                                        <option value="Rajanpur">Rajanpur</option>
                                    </select>
                                </div>
                                <div class="col-md-6 student-field">
                                    <label class="form-label">Class</label>
                                    <select name="class" class="form-select">
                                        <option value="">Select Class</option>
                                        <option value="9th">9th</option>
                                        <option value="10th">10th</option>
                                        <option value="11th">11th</option>
                                        <option value="12th">12th</option>
                                    </select>
                                </div>
                                <div class="col-md-6 student-field">
                                    <label class="form-label">Roll Number</label>
                                    <input type="text" name="roll_number" class="form-control">
                                </div>
                                <div class="col-md-6 teacher-field d-none">
                                    <label class="form-label">Subject Specialization</label>
                                    <input type="text" name="subject_specialization" class="form-control">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-teal w-100">Request Account</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        let selectedRole = '<?= login_h($selected_role) ?>';
        const roleLabels = { student: 'Student', teacher: 'Teacher', admin: 'Admin', staff: 'Staff' };
        const roleIcons = { student: 'fa-graduation-cap', teacher: 'fa-chalkboard-user', admin: 'fa-gear', staff: 'fa-briefcase' };

        function selectRole(role) {
            selectedRole = role;
            const label = roleLabels[role] || 'Student';
            document.getElementById('selected_role').value = role;
            document.getElementById('signup_role').value = role;
            document.getElementById('role-display').textContent = label;
            document.getElementById('role-title').textContent = label;
            document.getElementById('role-subtitle').textContent = label;
            const badge = document.getElementById('role-badge');
            badge.className = 'role-badge badge-' + role;
            badge.querySelector('i').className = 'fas ' + (roleIcons[role] || 'fa-user');
            document.getElementById('role-selection').classList.add('is-hidden');
            document.getElementById('login-form-section').classList.remove('is-hidden');
            updateSignupVisibility();
        }

        function goBack() {
            document.getElementById('login-form-section').classList.add('is-hidden');
            document.getElementById('role-selection').classList.remove('is-hidden');
            document.getElementById('signup-section').style.display = 'none';
        }

        function toggleSignup() {
            const signupSection = document.getElementById('signup-section');
            signupSection.style.display = signupSection.style.display === 'block' ? 'none' : 'block';
            updateSignupVisibility();
        }

        function updateSignupVisibility() {
            const restricted = document.getElementById('restricted-signup');
            const signupForm = document.getElementById('signup-form');
            const canSignup = selectedRole === 'student' || selectedRole === 'teacher';
            restricted.classList.toggle('d-none', canSignup);
            signupForm.classList.toggle('d-none', !canSignup);
            document.querySelectorAll('.student-field').forEach((field) => field.classList.toggle('d-none', selectedRole !== 'student'));
            document.querySelectorAll('.teacher-field').forEach((field) => field.classList.toggle('d-none', selectedRole !== 'teacher'));
            document.getElementById('signup_role').value = selectedRole;
        }

        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        function suggestUsername() {
            const name = document.getElementById('full_name').value.trim().toLowerCase();
            const username = name
                .replace(/[^a-z0-9\s]/g, '')
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .join('.');
            if (username && !document.getElementById('signup_username').dataset.touched) {
                document.getElementById('signup_username').value = username;
            }
        }

        document.getElementById('signup_username').addEventListener('input', function () {
            this.dataset.touched = '1';
        });

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        selectRole('<?= login_h($selected_role ?: 'student') ?>');
        <?php endif; ?>
    </script>
</body>
</html>
