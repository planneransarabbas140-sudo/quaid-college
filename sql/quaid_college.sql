-- SQL schema for Quaid-e-Azam Group of Colleges application
DROP DATABASE IF EXISTS quaid_college_db;
CREATE DATABASE quaid_college_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quaid_college_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    phone VARCHAR(50) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    remember_token VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender VARCHAR(20) DEFAULT NULL,
    blood_group VARCHAR(10) DEFAULT NULL,
    religion VARCHAR(50) DEFAULT NULL,
    nationality VARCHAR(50) DEFAULT NULL,
    admission_date DATE DEFAULT NULL,
    class VARCHAR(50) DEFAULT NULL,
    section VARCHAR(20) DEFAULT NULL,
    roll_number VARCHAR(50) DEFAULT NULL,
    guardian_name VARCHAR(150) DEFAULT NULL,
    guardian_relation VARCHAR(50) DEFAULT NULL,
    guardian_phone VARCHAR(50) DEFAULT NULL,
    guardian_email VARCHAR(150) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    pin_code VARCHAR(20) DEFAULT NULL,
    emergency_contact VARCHAR(100) DEFAULT NULL,
    medical_info TEXT DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE admission_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    father_name VARCHAR(150) NOT NULL,
    dob DATE NOT NULL,
    gender VARCHAR(20) NOT NULL,
    cnic VARCHAR(30) NOT NULL,
    religion VARCHAR(50) DEFAULT 'Islam',
    nationality VARCHAR(50) DEFAULT 'Pakistani',
    phone VARCHAR(30) NOT NULL,
    whatsapp VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address TEXT NOT NULL,
    prev_institution VARCHAR(255) NOT NULL,
    matric_roll VARCHAR(50) DEFAULT NULL,
    matric_year VARCHAR(20) DEFAULT NULL,
    matric_total INT DEFAULT 1100,
    matric_obtained INT DEFAULT NULL,
    matric_grade VARCHAR(20) DEFAULT NULL,
    board_name VARCHAR(120) DEFAULT NULL,
    program VARCHAR(120) NOT NULL,
    campus VARCHAR(120) NOT NULL,
    session VARCHAR(30) DEFAULT '2026-2028',
    photo VARCHAR(255) NOT NULL,
    matric_certificate VARCHAR(255) NOT NULL,
    cnic_copy VARCHAR(255) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    transaction_id VARCHAR(120) DEFAULT NULL,
    admission_fee DECIMAL(10,2) DEFAULT 5000.00,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    remarks TEXT DEFAULT NULL,
    student_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admission_applications_status (status),
    INDEX idx_admission_applications_cnic (cnic),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE whatsapp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(30) NOT NULL,
    message_type VARCHAR(80) NOT NULL,
    status VARCHAR(30) NOT NULL,
    response TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_whatsapp_logs_phone (phone),
    INDEX idx_whatsapp_logs_status (status)
) ENGINE=InnoDB;

CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    role VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;



CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(100) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    complainant_name VARCHAR(150) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_title VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    priority VARCHAR(50) NOT NULL DEFAULT 'Normal',
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requested_by INT DEFAULT NULL,
    role VARCHAR(50) NOT NULL,
    module_name VARCHAR(100) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    request_data LONGTEXT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_remarks TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approval_status (status),
    INDEX idx_approval_module (module_name),
    INDEX idx_approval_requested_by (requested_by),
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE front_desk_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_date DATE NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    purpose TEXT NOT NULL,
    assigned_to INT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    purpose TEXT NOT NULL,
    whom_to_meet VARCHAR(150) DEFAULT NULL,
    check_in DATETIME NOT NULL,
    check_out DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE fee_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class VARCHAR(50) NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    frequency VARCHAR(50) NOT NULL DEFAULT 'Monthly', -- Monthly, Quarterly, Semester, Annual, One-time
    academic_year VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_fee_class_type (class, fee_type, academic_year)
) ENGINE=InnoDB;

CREATE TABLE fee_collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash', -- Cash, Bank Transfer, Online, Cheque
    transaction_id VARCHAR(100) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Paid', -- Paid, Pending, Failed, Refunded
    due_date DATE DEFAULT NULL,
    academic_year VARCHAR(20) NOT NULL,
    remarks TEXT DEFAULT NULL,
    collected_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structure(id) ON DELETE CASCADE,
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE fee_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_collection_id INT NOT NULL,
    transaction_type VARCHAR(50) NOT NULL, -- Payment, Refund, Adjustment
    amount DECIMAL(10,2) NOT NULL,
    description TEXT DEFAULT NULL,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_by INT DEFAULT NULL,
    FOREIGN KEY (fee_collection_id) REFERENCES fee_collections(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE student_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Half Day') NOT NULL DEFAULT 'Present',
    remarks VARCHAR(255) DEFAULT NULL,
    marked_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_date (student_id, attendance_date)
) ENGINE=InnoDB;

CREATE TABLE staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Half Day') NOT NULL DEFAULT 'Present',
    check_in TIME DEFAULT NULL,
    check_out TIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    UNIQUE KEY unique_staff_date (staff_id, attendance_date)
) ENGINE=InnoDB;

CREATE TABLE leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    approved_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    salary_month VARCHAR(20) NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    allowances DECIMAL(10,2) DEFAULT 0.00,
    deductions DECIMAL(10,2) DEFAULT 0.00,
    net_salary DECIMAL(10,2) NOT NULL,
    payment_date DATE DEFAULT NULL,
    status ENUM('Pending', 'Paid') NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payroll_month (staff_id, salary_month)
) ENGINE=InnoDB;

CREATE TABLE timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class VARCHAR(50) NOT NULL,
    section VARCHAR(20) NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    period_number INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    teacher_id INT DEFAULT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE syllabus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class VARCHAR(50) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE accounts_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('Income', 'Expense') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    recorded_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(100),
    reference_id INT NULL,
    campus VARCHAR(255),
    category VARCHAR(255),
    description TEXT,
    amount DECIMAL(12,2),
    expense_type ENUM('manual','auto') DEFAULT 'manual',
    status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    created_by INT,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expenses_module (module_name, reference_id),
    INDEX idx_expenses_status (status),
    INDEX idx_expenses_campus (campus),
    INDEX idx_expenses_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(100),
    reference_id INT NULL,
    campus VARCHAR(255),
    description TEXT,
    amount DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_income_source (source, reference_id),
    INDEX idx_income_campus (campus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pos_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(150) NOT NULL,
    isbn VARCHAR(50) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    available INT NOT NULL DEFAULT 1,
    category VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE transport_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(150) NOT NULL,
    vehicle_number VARCHAR(50) NOT NULL,
    driver_name VARCHAR(150) NOT NULL,
    driver_phone VARCHAR(50) DEFAULT NULL,
    monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO users (full_name, username, email, password, role, phone, is_active)
VALUES ('Administrator', 'admin', 'admin@quaid.edu.pk', '$2y$10$KNPq50hffUEhmBYUd9bGbus1RKnzIrUs93WmGqouYPNBYfBXBz3Z2', 'admin', '03001234567', 1);
