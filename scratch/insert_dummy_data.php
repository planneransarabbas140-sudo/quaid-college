<?php
/**
 * File: scratch/insert_dummy_data.php
 * Purpose: Seed Quaid-e-Azam College ERP with dummy data
 */
require_once 'config/db.php';

$database = new Database();
$db = $database->getConnection();

echo "Starting Dummy Data Insertion...\n";

try {
    // 1. Create Missing Tables (Drop first to ensure schema match for dummy data)
    $db->exec("DROP TABLE IF EXISTS classes");
    $db->exec("CREATE TABLE classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100),
        section VARCHAR(10),
        room_no VARCHAR(20)
    )");

    $db->exec("DROP TABLE IF EXISTS teachers");
    $db->exec("CREATE TABLE teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150),
        subject VARCHAR(100),
        qualification VARCHAR(100),
        phone VARCHAR(20),
        city VARCHAR(100),
        salary DECIMAL(10,2)
    )");

    $db->exec("DROP TABLE IF EXISTS ptm_meetings");
    $db->exec("CREATE TABLE ptm_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200),
        meeting_date DATE,
        class_name VARCHAR(100),
        remarks TEXT
    )");

    $db->exec("DROP TABLE IF EXISTS complaints");
    $db->exec("CREATE TABLE complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_name VARCHAR(150),
        complaint_type VARCHAR(100),
        description TEXT,
        status VARCHAR(50)
    )");

    $db->exec("DROP TABLE IF EXISTS homework");
    $db->exec("CREATE TABLE homework (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject VARCHAR(100),
        class_name VARCHAR(100),
        homework_title VARCHAR(255),
        submission_date DATE
    )");

    $db->exec("DROP TABLE IF EXISTS exams");
    $db->exec("CREATE TABLE exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_name VARCHAR(150),
        class_name VARCHAR(100),
        start_date DATE,
        end_date DATE
    )");

    $db->exec("DROP TABLE IF EXISTS books");
    $db->exec("CREATE TABLE books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_title VARCHAR(255),
        author_name VARCHAR(150),
        category VARCHAR(100),
        quantity INT
    )");

    echo "Tables ready.\n";

    // 2. Clear Tables
    $tables = ['classes', 'teachers', 'ptm_meetings', 'complaints', 'homework', 'exams', 'books', 'students'];
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE $table");
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Tables truncated.\n";

    // 3. Insert Classes (10)
    $classes = [
        ['ICS Part 1','A','101'], ['ICS Part 1','B','102'], ['ICS Part 2','A','103'], ['ICS Part 2','B','104'],
        ['FSc Pre-Engineering','A','105'], ['FSc Pre-Medical','A','106'], ['ICom','A','107'], ['FA','A','108'],
        ['FA','B','109'], ['BSCS Semester 1','A','110']
    ];
    $stmt = $db->prepare("INSERT INTO classes (class_name, section, room_no) VALUES (?, ?, ?)");
    foreach ($classes as $c) $stmt->execute($c);
    echo "Inserted 10 Classes.\n";

    // 4. Insert Teachers (20)
    $teachers = [
        ['Hamza Tariq','Computer Science','MSCS','03001234567','Rajanpur',85000],
        ['Ayesha Noor','English','MA English','03111234567','DG Khan',70000],
        ['Bilal Ahmed','Physics','MPhil Physics','03211234567','Multan',92000],
        ['Sana Fatima','Chemistry','MSc Chemistry','03331234567','Lahore',78000],
        ['Usman Khalid','Mathematics','MPhil Math','03451234567','Bahawalpur',88000],
        ['Ali Raza','Biology','MSc Biology','03021234567','Rajanpur',76000],
        ['Maryam Zahra','Urdu','MA Urdu','03121234567','Multan',65000],
        ['Talha Javed','Statistics','MSc Statistics','03221234567','Layyah',73000],
        ['Hira Noor','Pakistan Studies','MA Pak Study','03321234567','DG Khan',60000],
        ['Daniyal Khan','Islamiat','MA Islamiat','03461234567','Muzaffargarh',62000],
        ['Komal Fatima','Economics','MA Economics','03031234567','Rahim Yar Khan',70000],
        ['Saad Ahmed','Accounting','MBA Finance','03131234567','Multan',85000],
        ['Fatima Noor','Computer Science','MSCS','03231234567','Lahore',95000],
        ['Ahmed Khan','Physics','MPhil Physics','03341234567','Rajanpur',90000],
        ['Iqra Bibi','English','MA English','03471234567','DG Khan',72000],
        ['Zain Ali','Mathematics','MSc Math','03041234567','Bahawalpur',81000],
        ['Muneeb Tariq','Chemistry','MSc Chemistry','03141234567','Multan',77000],
        ['Anaya Fatima','Biology','MPhil Biology','03241234567','Layyah',86000],
        ['Haider Ali','Computer Science','BSCS','03351234567','Rajanpur',68000],
        ['Sadia Khan','Urdu','MA Urdu','03481234567','Muzaffargarh',64000]
    ];
    $stmt = $db->prepare("INSERT INTO teachers (name, subject, qualification, phone, city, salary) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($teachers as $t) $stmt->execute($t);
    echo "Inserted 20 Teachers.\n";

    // 5. Insert PTM Meetings (15)
    $ptm = [
        ['Monthly PTM','2026-05-10','ICS Part 1','Student progress discussion'],
        ['Result PTM','2026-05-12','ICS Part 2','Midterm result discussion'],
        ['Discipline Meeting','2026-05-15','FA','Discipline awareness'],
        ['Attendance Review','2026-05-18','FSc Pre-Medical','Attendance issues'],
        ['Career Counseling','2026-05-20','BSCS Semester 1','Future guidance'],
        ['Parents Feedback','2026-05-22','ICom','Parent suggestions'],
        ['Exam Preparation','2026-05-24','FSc Pre-Engineering','Board preparation'],
        ['Scholarship Discussion','2026-05-25','ICS Part 1','Merit scholarships'],
        ['Performance Review','2026-05-26','ICS Part 2','Monthly performance'],
        ['Special Counseling','2026-05-27','FA','Weak students support'],
        ['Homework Review','2026-05-28','ICS Part 1','Homework completion'],
        ['Sports Discussion','2026-05-29','ICom','Sports participation'],
        ['Fee Discussion','2026-05-30','FA','Pending dues'],
        ['Board Preparation','2026-06-01','FSc Pre-Medical','Exam readiness'],
        ['Final PTM','2026-06-03','BSCS Semester 1','Semester feedback']
    ];
    $stmt = $db->prepare("INSERT INTO ptm_meetings (title, meeting_date, class_name, remarks) VALUES (?, ?, ?, ?)");
    foreach ($ptm as $p) $stmt->execute($p);
    echo "Inserted 15 PTM Meetings.\n";

    // 6. Insert Complaints (25)
    $complaints = [
        ['Ali Raza','Transport','Bus arriving late','Pending'],
        ['Ahmed Khan','Fee','Fee voucher issue','Resolved'],
        ['Usman Tariq','Attendance','Attendance missing','Pending'],
        ['Fatima Noor','Library','Book not issued','Resolved'],
        ['Bilal Ahmed','Teacher','Class timing issue','Pending'],
        ['Maryam Zahra','Exam','Wrong marks entry','Resolved'],
        ['Talha Javed','Internet','WiFi issue','Pending'],
        ['Komal Fatima','Classroom','Fan not working','Resolved'],
        ['Zain Ali','Transport','Bus seat issue','Pending'],
        ['Ayesha Noor','Homework','Homework upload problem','Resolved'],
        ['Hira Noor','Discipline','Student misbehavior','Pending'],
        ['Muneeb Tariq','Fee','Late fee fine','Resolved'],
        ['Sana Fatima','Library','Lost library card','Pending'],
        ['Haider Ali','Result','Result not showing','Resolved'],
        ['Iqra Bibi','Attendance','Biometric issue','Pending'],
        ['Saad Ahmed','Exam','Roll number slip issue','Resolved'],
        ['Anaya Fatima','Classroom','Projector issue','Pending'],
        ['Daniyal Khan','Transport','Driver complaint','Resolved'],
        ['Abdullah Raza','Portal','Password reset','Pending'],
        ['Eman Zahra','Homework','Assignment missing','Resolved'],
        ['Mohsin Ali','Fee','Duplicate challan','Pending'],
        ['Sidra Noor','Exam','Paper timing confusion','Resolved'],
        ['Hamza Tariq','Library','Book return issue','Pending'],
        ['Sadia Khan','Attendance','Late attendance','Resolved'],
        ['Rafay Ahmed','Classroom','AC not working','Pending']
    ];
    $stmt = $db->prepare("INSERT INTO complaints (student_name, complaint_type, description, status) VALUES (?, ?, ?, ?)");
    foreach ($complaints as $com) $stmt->execute($com);
    echo "Inserted 25 Complaints.\n";

    // 7. Insert Homework (50)
    $subjects = ['Computer Science', 'Physics', 'Chemistry', 'English', 'Mathematics', 'Urdu', 'Biology', 'Statistics', 'Accounting'];
    $classes_list = ['ICS Part 1', 'ICS Part 2', 'FSc Pre-Engineering', 'FSc Pre-Medical', 'FA', 'ICom', 'BSCS Semester 1'];
    $hw_titles = ['Assignment 1', 'Exercise 2.3', 'Lab Report', 'Essay Writing', 'Numerical Practice', 'Grammar Test', 'Diagram Drawing', 'Past Paper Solving', 'Revision Quiz'];
    
    $stmt = $db->prepare("INSERT INTO homework (subject, class_name, homework_title, submission_date) VALUES (?, ?, ?, ?)");
    for ($i = 1; $i <= 50; $i++) {
        $subj = $subjects[array_rand($subjects)];
        $cls = $classes_list[array_rand($classes_list)];
        $title = $hw_titles[array_rand($hw_titles)] . " - Part $i";
        $date = date('2026-05-d', strtotime("+$i days"));
        $stmt->execute([$subj, $cls, $title, $date]);
    }
    echo "Inserted 50 Homework records.\n";

    // 8. Insert Exams (8)
    $exams = [
        ['Mid Term','ICS Part 1','2026-06-01','2026-06-07'],
        ['Mid Term','ICS Part 2','2026-06-01','2026-06-07'],
        ['Mid Term','FSc Pre-Engineering','2026-06-01','2026-06-07'],
        ['Mid Term','FSc Pre-Medical','2026-06-01','2026-06-07'],
        ['Final Term','ICS Part 1','2026-09-01','2026-09-10'],
        ['Final Term','ICS Part 2','2026-09-01','2026-09-10'],
        ['Final Term','FA','2026-09-01','2026-09-10'],
        ['Final Term','BSCS Semester 1','2026-09-01','2026-09-10']
    ];
    $stmt = $db->prepare("INSERT INTO exams (exam_name, class_name, start_date, end_date) VALUES (?, ?, ?, ?)");
    foreach ($exams as $e) $stmt->execute($e);
    echo "Inserted 8 Exams.\n";

    // 9. Insert Books (100)
    $categories = ['Computer', 'Science', 'Language', 'Arts', 'Commerce', 'History', 'General'];
    $authors = ['Yasir Nawaz', 'Deitel', 'Korth', 'Punjab Board', 'Campbell', 'Wren & Martin', 'Frank Wood', 'Sultan Chand'];
    $book_prefixes = ['Fundamentals of', 'Advanced', 'Introduction to', 'Principles of', 'The Art of', 'Mastering'];
    
    $stmt = $db->prepare("INSERT INTO books (book_title, author_name, category, quantity) VALUES (?, ?, ?, ?)");
    for ($i = 1; $i <= 100; $i++) {
        $cat = $categories[array_rand($categories)];
        $auth = $authors[array_rand($authors)];
        $title = $book_prefixes[array_rand($book_prefixes)] . " " . $cat . " Vol $i";
        $qty = rand(5, 20);
        $stmt->execute([$title, $auth, $cat, $qty]);
    }
    echo "Inserted 100 Books.\n";

    // 10. Insert Students (200)
    $first_names = ['Ali', 'Ahmed', 'Usman', 'Fatima', 'Bilal', 'Maryam', 'Talha', 'Komal', 'Zain', 'Ayesha', 'Hira', 'Muneeb', 'Sana', 'Haider', 'Iqra', 'Saad', 'Anaya', 'Daniyal', 'Abdullah', 'Eman'];
    $last_names = ['Raza', 'Khan', 'Tariq', 'Noor', 'Ahmed', 'Zahra', 'Javed', 'Fatima', 'Ali', 'Bibi', 'Khalid', 'Abbas', 'Hassan', 'Mehmood', 'Shah'];
    
    $stmt = $db->prepare("INSERT INTO students (
        student_id, first_name, last_name, date_of_birth, gender, blood_group, religion, nationality,
        admission_date, class, section, roll_number, guardian_name, guardian_relation, 
        guardian_phone, guardian_email, address, city, state, pin_code, emergency_contact, medical_info
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");

    for ($i = 1; $i <= 200; $i++) {
        $fname = $first_names[array_rand($first_names)];
        $lname = $last_names[array_rand($last_names)];
        $sid = "STU-2026-" . str_pad($i, 4, '0', STR_PAD_LEFT);
        $dob = date('2005-m-d', strtotime("-" . rand(15, 20) . " years"));
        $gender = (rand(0, 1) == 0) ? 'Male' : 'Female';
        $bg = ['A+', 'B+', 'O+', 'AB+', 'A-', 'B-', 'O-'][rand(0, 6)];
        $cls = $classes_list[array_rand($classes_list)];
        $sec = ['A', 'B'][rand(0, 1)];
        $roll = "R-" . str_pad($i, 3, '0', STR_PAD_LEFT);
        $gname = $last_names[array_rand($last_names)] . " " . $first_names[array_rand($first_names)];
        
        $stmt->execute([
            $sid, $fname, $lname, $dob, $gender, $bg, 'Islam', 'Pakistani',
            '2026-05-01', $cls, $sec, $roll, $gname, 'Father',
            '0300' . rand(1000000, 9999999), 'guardian' . $i . '@example.com',
            'Street ' . $i . ', Phase ' . rand(1, 5), 'Rajanpur', 'Punjab', '33500',
            '0300' . rand(1000000, 9999999), 'None'
        ]);
    }
    echo "Inserted 200 Students.\n";

    echo "\nAll Dummy Data Inserted Successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
