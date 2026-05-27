-- Final remaining modules migration for Quaid College ERP
-- Safe baseline tables only. Existing module PHP files also perform column checks
-- for older installations that need compatibility columns.

CREATE TABLE IF NOT EXISTS staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'present',
    marked_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_staff_date (staff_id, attendance_date),
    INDEX idx_staff_attendance_date (attendance_date),
    INDEX idx_staff_attendance_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL DEFAULT 1,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_staff (staff_id),
    INDEX idx_leave_status (status),
    INDEX idx_leave_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    salary_month VARCHAR(20) NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_date DATE DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    generated_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payroll_month (staff_id, salary_month),
    INDEX idx_payroll_month (salary_month),
    INDEX idx_payroll_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(150) NOT NULL,
    start_point VARCHAR(150) DEFAULT NULL,
    end_point VARCHAR(150) DEFAULT NULL,
    monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    vehicle_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transport_routes_name (route_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_no VARCHAR(50) NOT NULL UNIQUE,
    driver_name VARCHAR(150) NOT NULL,
    capacity INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transport_vehicles_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    route_id INT NOT NULL,
    monthly_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    fee_month VARCHAR(7) NOT NULL,
    fee_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_transport_student_month (student_id, fee_month),
    INDEX idx_transport_assignments_route (route_id),
    INDEX idx_transport_assignments_fee (fee_month, fee_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homework_diary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id VARCHAR(100) DEFAULT NULL,
    section_id VARCHAR(50) DEFAULT NULL,
    subject_id VARCHAR(120) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    homework_title VARCHAR(180) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    diary_date DATE DEFAULT NULL,
    class VARCHAR(100) DEFAULT NULL,
    section VARCHAR(50) DEFAULT NULL,
    subject VARCHAR(120) DEFAULT NULL,
    title VARCHAR(180) DEFAULT NULL,
    homework TEXT DEFAULT NULL,
    instructions TEXT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    status VARCHAR(30) DEFAULT 'Assigned',
    homework_status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_homework_class (class_id, section_id),
    INDEX idx_homework_due (due_date),
    INDEX idx_homework_status (homework_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lms_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id VARCHAR(100) DEFAULT NULL,
    section_id VARCHAR(50) DEFAULT NULL,
    subject_id VARCHAR(120) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    title VARCHAR(220) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_file_name VARCHAR(255) DEFAULT NULL,
    file_type VARCHAR(20) DEFAULT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    uploaded_by INT DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    download_count INT NOT NULL DEFAULT 0,
    INDEX idx_lms_class (class_id, section_id),
    INDEX idx_lms_subject (subject_id),
    INDEX idx_lms_status (status),
    INDEX idx_lms_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id VARCHAR(100) DEFAULT NULL,
    section_id VARCHAR(50) DEFAULT NULL,
    subject_id VARCHAR(120) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    day_of_week VARCHAR(20) DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    room_no VARCHAR(50) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    legacy_timetable_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tt_class (class_id, section_id),
    INDEX idx_tt_teacher (teacher_id),
    INDEX idx_tt_day_time (day_of_week, start_time, end_time),
    INDEX idx_tt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class VARCHAR(100) DEFAULT NULL,
    section VARCHAR(50) DEFAULT NULL,
    day_of_week VARCHAR(20) DEFAULT NULL,
    day VARCHAR(20) DEFAULT NULL,
    period_number INT DEFAULT 1,
    subject VARCHAR(120) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    teacher_name VARCHAR(180) DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    room_number VARCHAR(50) DEFAULT NULL,
    room VARCHAR(50) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(220) NOT NULL,
    description TEXT DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_type VARCHAR(20) DEFAULT NULL,
    file_size VARCHAR(40) DEFAULT '0',
    uploaded_by INT DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    visibility VARCHAR(20) NOT NULL DEFAULT 'public',
    download_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_downloads_category (category),
    INDEX idx_downloads_status (status),
    INDEX idx_downloads_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ptm_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(220) NOT NULL,
    class_id VARCHAR(100) DEFAULT NULL,
    section_id VARCHAR(50) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    meeting_date DATE DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    venue VARCHAR(180) DEFAULT NULL,
    agenda TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ptm_class (class_id, section_id),
    INDEX idx_ptm_teacher (teacher_id),
    INDEX idx_ptm_date (meeting_date),
    INDEX idx_ptm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) DEFAULT NULL,
    task_title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    due_date DATE DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tasks_assigned_to (assigned_to),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_priority (priority),
    INDEX idx_tasks_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(100) DEFAULT NULL,
    complainant_name VARCHAR(150) DEFAULT NULL,
    complainant_type VARCHAR(50) NOT NULL DEFAULT 'student',
    contact_no VARCHAR(50) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    assigned_to INT DEFAULT NULL,
    response TEXT DEFAULT NULL,
    submitted_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_complaint_number (complaint_number),
    INDEX idx_complaints_status (status),
    INDEX idx_complaints_priority (priority),
    INDEX idx_complaints_category (category),
    INDEX idx_complaints_assigned_to (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pos_products_category (category),
    INDEX idx_pos_products_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(100) NOT NULL UNIQUE,
    customer_type VARCHAR(30) NOT NULL DEFAULT 'walk_in',
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(180) DEFAULT NULL,
    sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_sales_invoice (invoice_no),
    INDEX idx_pos_sales_date (sale_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_sale_items_sale (sale_id),
    INDEX idx_pos_sale_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_title VARCHAR(220) DEFAULT NULL,
    expense_category VARCHAR(120) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expense_date DATE DEFAULT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
    paid_to VARCHAR(180) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    account_transaction_id INT DEFAULT NULL,
    INDEX idx_account_expenses_date (expense_date),
    INDEX idx_account_expenses_category (expense_category),
    INDEX idx_account_expenses_status (status),
    INDEX idx_account_expenses_tx (account_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounts_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(120) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    transaction_date DATE DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    transaction_id VARCHAR(100) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_accounts_transactions_date (transaction_date),
    INDEX idx_accounts_transactions_type (transaction_type),
    INDEX idx_accounts_transactions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id VARCHAR(50) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(150) DEFAULT NULL,
    isbn VARCHAR(50) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    publisher VARCHAR(150) DEFAULT NULL,
    publication_year INT DEFAULT NULL,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    shelf_number VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_library_books_code (book_id),
    INDEX idx_library_books_title (title),
    INDEX idx_library_books_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS library_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    issue_date DATE NOT NULL,
    return_date DATE NOT NULL,
    actual_return DATE DEFAULT NULL,
    fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'issued',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_library_issues_book (book_id),
    INDEX idx_library_issues_student (student_id),
    INDEX idx_library_issues_status (status),
    INDEX idx_library_issues_due (return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS internal_management (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(220) NOT NULL,
    description TEXT DEFAULT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'note',
    category VARCHAR(120) DEFAULT 'General',
    file_path VARCHAR(255) DEFAULT NULL,
    visibility VARCHAR(30) NOT NULL DEFAULT 'private',
    created_by INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    INDEX idx_internal_type (type),
    INDEX idx_internal_category (category),
    INDEX idx_internal_status (status),
    INDEX idx_internal_assigned_to (assigned_to),
    INDEX idx_internal_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
