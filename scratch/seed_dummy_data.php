<?php
require_once __DIR__ . '/../config/db.php';

$db = (new Database())->getConnection();
$adminId = 1;
$year = function_exists('getCurrentAcademicYear') ? getCurrentAcademicYear() : '2026-2027';

function seedOne(PDO $db, string $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function seedScalar(PDO $db, string $sql, array $params = []) {
    return seedOne($db, $sql, $params)->fetchColumn();
}

function seedUser(PDO $db, string $name, string $username, string $email, string $role, string $phone = '') {
    $id = seedScalar($db, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1", [$username, $email]);
    if ($id) {
        seedOne($db, "UPDATE users SET full_name = ?, role = ?, phone = ?, is_active = 1 WHERE id = ?", [$name, $role, $phone, $id]);
        return (int)$id;
    }
    seedOne($db, "
        INSERT INTO users (full_name, username, email, password, role, phone, is_active, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, 1, 0)
    ", [$name, $username, $email, password_hash('Test@12345', PASSWORD_DEFAULT), $role, $phone]);
    return (int)$db->lastInsertId();
}

function seedStaff(PDO $db, array $staff) {
    $id = seedScalar($db, "SELECT id FROM staff WHERE employee_code = ? LIMIT 1", [$staff['employee_code']]);
    if ($id) {
        seedOne($db, "
            UPDATE staff SET full_name=?, role=?, email=?, phone=?, is_active=1, user_id=?, designation=?,
                campus=?, campus_id=?, joining_date=?, status='Active', salary=?
            WHERE id=?
        ", [
            $staff['full_name'], $staff['role'], $staff['email'], $staff['phone'], $staff['user_id'],
            $staff['designation'], $staff['campus'], $staff['campus_id'], $staff['joining_date'], $staff['salary'], $id
        ]);
        return (int)$id;
    }
    seedOne($db, "
        INSERT INTO staff (full_name, role, email, phone, is_active, user_id, employee_code, designation, campus, campus_id, joining_date, status, salary)
        VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 'Active', ?)
    ", [
        $staff['full_name'], $staff['role'], $staff['email'], $staff['phone'], $staff['user_id'],
        $staff['employee_code'], $staff['designation'], $staff['campus'], $staff['campus_id'],
        $staff['joining_date'], $staff['salary']
    ]);
    return (int)$db->lastInsertId();
}

function seedStudent(PDO $db, array $student) {
    $id = seedScalar($db, "SELECT id FROM students WHERE student_id = ? LIMIT 1", [$student['student_id']]);
    $values = [
        $student['user_id'], $student['student_id'], $student['first_name'], $student['last_name'], $student['date_of_birth'],
        $student['gender'], $student['blood_group'], 'Islam', 'Pakistani', $student['admission_date'], $student['class'],
        $student['section'], $student['roll_number'], $student['guardian_name'], 'Father', $student['guardian_phone'],
        $student['guardian_email'], $student['address'], $student['city'], 'Punjab', '33500', $student['guardian_phone'],
        'No known allergies', $student['student_id'], $student['father_name'], $student['guardian_phone'],
        strtolower($student['student_id']) . '@demo.qgc.test', $student['campus'], $student['campus_id'], 'Active'
    ];
    if ($id) {
        seedOne($db, "
            UPDATE students SET user_id=?, first_name=?, last_name=?, date_of_birth=?, gender=?, blood_group=?,
                religion=?, nationality=?, admission_date=?, class=?, section=?, roll_number=?, guardian_name=?,
                guardian_relation=?, guardian_phone=?, guardian_email=?, address=?, city=?, state=?, pin_code=?,
                emergency_contact=?, medical_info=?, registration_number=?, father_name=?, phone=?, email=?,
                campus=?, campus_id=?, status=?
            WHERE id=?
        ", array_merge(
            [$student['user_id']],
            array_slice($values, 2),
            [$id]
        ));
        return (int)$id;
    }
    seedOne($db, "
        INSERT INTO students (user_id, student_id, first_name, last_name, date_of_birth, gender, blood_group,
            religion, nationality, admission_date, class, section, roll_number, guardian_name, guardian_relation,
            guardian_phone, guardian_email, address, city, state, pin_code, emergency_contact, medical_info,
            registration_number, father_name, phone, email, campus, campus_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", $values);
    return (int)$db->lastInsertId();
}

function seedFeeStructure(PDO $db, string $class, string $feeType, float $amount, string $year) {
    $id = seedScalar($db, "SELECT id FROM fee_structure WHERE class = ? AND fee_type = ? AND academic_year = ? LIMIT 1", [$class, $feeType, $year]);
    if ($id) {
        seedOne($db, "UPDATE fee_structure SET amount=?, frequency='Monthly', is_active=1 WHERE id=?", [$amount, $id]);
        return (int)$id;
    }
    seedOne($db, "INSERT INTO fee_structure (class, fee_type, amount, frequency, academic_year, is_active) VALUES (?, ?, ?, 'Monthly', ?, 1)", [$class, $feeType, $amount, $year]);
    return (int)$db->lastInsertId();
}

function seedFeeCollection(PDO $db, array $row) {
    $existing = seedScalar($db, "SELECT id FROM fee_collections WHERE transaction_id = ? LIMIT 1", [$row['transaction_id']]);
    if ($existing) {
        seedOne($db, "
            UPDATE fee_collections SET student_id=?, fee_structure_id=?, amount_paid=?, payment_date=?, payment_method=?,
                status=?, due_date=?, academic_year=?, remarks=?, collected_by=?, fee_type=?, amount=?, paid_amount=?
            WHERE id=?
        ", [
            $row['student_id'], $row['fee_structure_id'], $row['paid_amount'], $row['payment_date'], $row['payment_method'],
            $row['status'], $row['due_date'], $row['academic_year'], $row['remarks'], $row['collected_by'],
            $row['fee_type'], $row['amount'], $row['paid_amount'], $existing
        ]);
        return (int)$existing;
    }
    seedOne($db, "
        INSERT INTO fee_collections (student_id, fee_structure_id, amount_paid, payment_date, payment_method, transaction_id,
            status, due_date, academic_year, remarks, collected_by, fee_type, amount, paid_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $row['student_id'], $row['fee_structure_id'], $row['paid_amount'], $row['payment_date'], $row['payment_method'],
        $row['transaction_id'], $row['status'], $row['due_date'], $row['academic_year'], $row['remarks'],
        $row['collected_by'], $row['fee_type'], $row['amount'], $row['paid_amount']
    ]);
    return (int)$db->lastInsertId();
}

function seedExam(PDO $db, array $exam) {
    $id = seedScalar($db, "SELECT id FROM exam_schedule WHERE exam_title=? AND class=? AND section=? AND subject=? LIMIT 1", [$exam['exam_title'], $exam['class'], $exam['section'], $exam['subject']]);
    if ($id) {
        seedOne($db, "UPDATE exam_schedule SET exam_type=?, exam_date=?, start_time=?, end_time=?, room=?, total_marks=?, campus=? WHERE id=?", [
            $exam['exam_type'], $exam['exam_date'], $exam['start_time'], $exam['end_time'], $exam['room'], $exam['total_marks'], $exam['campus'], $id
        ]);
        return (int)$id;
    }
    seedOne($db, "
        INSERT INTO exam_schedule (exam_title, exam_type, class, section, subject, exam_date, start_time, end_time, room, total_marks, campus)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [$exam['exam_title'], $exam['exam_type'], $exam['class'], $exam['section'], $exam['subject'], $exam['exam_date'], $exam['start_time'], $exam['end_time'], $exam['room'], $exam['total_marks'], $exam['campus']]);
    return (int)$db->lastInsertId();
}

function gradeFor(float $percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    if ($percentage >= 40) return 'D';
    return 'F';
}

$db->beginTransaction();
try {
    $teacherUsers = [
        'DUMMY-TCH-001' => seedUser($db, 'Ayesha Khan', 'demo.teacher.ayesha', 'ayesha.teacher@demo.qgc.test', 'teacher', '03001234501'),
        'DUMMY-TCH-002' => seedUser($db, 'Muhammad Bilal', 'demo.teacher.bilal', 'bilal.teacher@demo.qgc.test', 'teacher', '03001234502'),
        'DUMMY-TCH-003' => seedUser($db, 'Sana Iqbal', 'demo.teacher.sana', 'sana.teacher@demo.qgc.test', 'teacher', '03001234503'),
    ];

    $staffRows = [
        ['employee_code'=>'DUMMY-TCH-001','full_name'=>'Ayesha Khan','role'=>'teacher','email'=>'ayesha.teacher@demo.qgc.test','phone'=>'03001234501','user_id'=>$teacherUsers['DUMMY-TCH-001'],'designation'=>'English Lecturer','campus'=>'Misbah Campus - Rajanpur','campus_id'=>1,'joining_date'=>'2023-08-15','salary'=>85000],
        ['employee_code'=>'DUMMY-TCH-002','full_name'=>'Muhammad Bilal','role'=>'teacher','email'=>'bilal.teacher@demo.qgc.test','phone'=>'03001234502','user_id'=>$teacherUsers['DUMMY-TCH-002'],'designation'=>'Mathematics Lecturer','campus'=>'Misbah Campus - Rajanpur','campus_id'=>1,'joining_date'=>'2022-09-01','salary'=>90000],
        ['employee_code'=>'DUMMY-TCH-003','full_name'=>'Sana Iqbal','role'=>'teacher','email'=>'sana.teacher@demo.qgc.test','phone'=>'03001234503','user_id'=>$teacherUsers['DUMMY-TCH-003'],'designation'=>'Computer Science Lecturer','campus'=>'Hamid Campus - Fazilpur','campus_id'=>2,'joining_date'=>'2024-01-10','salary'=>82000],
        ['employee_code'=>'DUMMY-STF-001','full_name'=>'Nadeem Akhtar','role'=>'staff','email'=>'nadeem.accounts@demo.qgc.test','phone'=>'03001234504','user_id'=>null,'designation'=>'Accounts Officer','campus'=>'Misbah Campus - Rajanpur','campus_id'=>1,'joining_date'=>'2021-04-12','salary'=>65000],
        ['employee_code'=>'DUMMY-STF-002','full_name'=>'Farah Noor','role'=>'staff','email'=>'farah.frontdesk@demo.qgc.test','phone'=>'03001234505','user_id'=>null,'designation'=>'Front Desk Officer','campus'=>'Abul Rehman Campus - Kot Mithan','campus_id'=>3,'joining_date'=>'2024-03-01','salary'=>52000],
    ];
    foreach ($staffRows as $staff) {
        seedStaff($db, $staff);
    }

    $classes = ['FSc Pre-Medical', 'ICS Computer Science', 'BSCS'];
    $feeIds = [];
    foreach ($classes as $class) {
        $feeIds[$class]['Tuition Fee'] = seedFeeStructure($db, $class, 'Tuition Fee', $class === 'BSCS' ? 8500 : 5500, $year);
        $feeIds[$class]['Computer Lab Fee'] = seedFeeStructure($db, $class, 'Computer Lab Fee', $class === 'FSc Pre-Medical' ? 1200 : 1800, $year);
        $feeIds[$class]['Sports Fund'] = seedFeeStructure($db, $class, 'Sports Fund', 500, $year);
    }

    $studentSeeds = [
        ['DUMMY-STU-001','Ahmed','Ali','M','2008-04-12','A+','FSc Pre-Medical','A','01','Muhammad Ali','03011230001','Misbah Campus - Rajanpur',1],
        ['DUMMY-STU-002','Fatima','Zahra','F','2008-07-18','B+','FSc Pre-Medical','A','02','Imran Hussain','03011230002','Misbah Campus - Rajanpur',1],
        ['DUMMY-STU-003','Hassan','Raza','M','2007-11-05','O+','FSc Pre-Medical','B','03','Raza Abbas','03011230003','Misbah Campus - Rajanpur',1],
        ['DUMMY-STU-004','Areeba','Malik','F','2008-01-21','A-','FSc Pre-Medical','B','04','Khalid Malik','03011230004','Misbah Campus - Rajanpur',1],
        ['DUMMY-STU-005','Usman','Tariq','M','2007-05-14','B-','ICS Computer Science','A','05','Tariq Mehmood','03011230005','Hamid Campus - Fazilpur',2],
        ['DUMMY-STU-006','Maham','Nawaz','F','2008-09-25','O-','ICS Computer Science','A','06','Nawaz Ahmed','03011230006','Hamid Campus - Fazilpur',2],
        ['DUMMY-STU-007','Danish','Iqbal','M','2007-02-02','AB+','ICS Computer Science','B','07','Iqbal Shah','03011230007','Hamid Campus - Fazilpur',2],
        ['DUMMY-STU-008','Zainab','Aslam','F','2008-12-08','A+','ICS Computer Science','B','08','Aslam Farooq','03011230008','Hamid Campus - Fazilpur',2],
        ['DUMMY-STU-009','Hamza','Saeed','M','2005-06-30','B+','BSCS','A','09','Saeed Anwar','03011230009','Abul Rehman Campus - Kot Mithan',3],
        ['DUMMY-STU-010','Noor','Fatima','F','2005-03-17','A-','BSCS','A','10','Yasir Mahmood','03011230010','Abul Rehman Campus - Kot Mithan',3],
        ['DUMMY-STU-011','Ali','Haider','M','2004-10-29','O+','BSCS','B','11','Haider Ali','03011230011','Abul Rehman Campus - Kot Mithan',3],
        ['DUMMY-STU-012','Hira','Batool','F','2005-08-09','AB-','BSCS','B','12','Nisar Batool','03011230012','Abul Rehman Campus - Kot Mithan',3],
    ];

    $students = [];
    foreach ($studentSeeds as $i => $s) {
        $studentUser = seedUser($db, $s[1] . ' ' . $s[2], strtolower(str_replace('-', '.', $s[0])), strtolower($s[0]) . '@demo.qgc.test', 'student', $s[10]);
        $students[$s[0]] = seedStudent($db, [
            'user_id'=>$studentUser, 'student_id'=>$s[0], 'first_name'=>$s[1], 'last_name'=>$s[2],
            'date_of_birth'=>$s[3] === 'M' || $s[3] === 'F' ? $s[4] : $s[3],
            'gender'=>$s[3] === 'F' ? 'Female' : 'Male', 'blood_group'=>$s[5], 'admission_date'=>'2026-04-' . str_pad((string)(($i % 20) + 1), 2, '0', STR_PAD_LEFT),
            'class'=>$s[6], 'section'=>$s[7], 'roll_number'=>$s[8], 'guardian_name'=>$s[9],
            'guardian_phone'=>$s[10], 'guardian_email'=>strtolower(str_replace(' ', '.', $s[9])) . '@parent.demo.qgc.test',
            'address'=>'Demo Street ' . ($i + 1) . ', South Punjab', 'city'=>str_contains($s[11], 'Fazilpur') ? 'Fazilpur' : (str_contains($s[11], 'Kot') ? 'Kot Mithan' : 'Rajanpur'),
            'father_name'=>$s[9], 'campus'=>$s[11], 'campus_id'=>$s[12],
        ]);
    }

    foreach ($studentSeeds as $i => $s) {
        $studentPk = $students[$s[0]];
        foreach ($feeIds[$s[6]] as $feeType => $feeId) {
            $amount = (float)seedScalar($db, "SELECT amount FROM fee_structure WHERE id = ?", [$feeId]);
            $paid = ($i % 4 === 0 && $feeType === 'Tuition Fee') ? max(0, $amount - 1500) : $amount;
            $status = $paid >= $amount ? 'Paid' : 'Partial';
            seedFeeCollection($db, [
                'student_id'=>$studentPk, 'fee_structure_id'=>$feeId, 'paid_amount'=>$paid, 'payment_date'=>'2026-05-' . str_pad((string)(($i % 18) + 3), 2, '0', STR_PAD_LEFT),
                'payment_method'=>($i % 3 === 0 ? 'Bank Transfer' : 'Cash'), 'transaction_id'=>'DUMMY-FEE-' . $s[0] . '-' . preg_replace('/\W+/', '', $feeType),
                'status'=>$status, 'due_date'=>'2026-05-30', 'academic_year'=>$year, 'remarks'=>'Dummy fee record for testing',
                'collected_by'=>1, 'fee_type'=>$feeType, 'amount'=>$amount, 'paid_amount'=>$paid
            ]);
        }
    }

    $subjects = ['English', 'Mathematics', 'Computer Science'];
    $examIds = [];
    foreach ($classes as $class) {
        foreach (['A', 'B'] as $section) {
            foreach ($subjects as $idx => $subject) {
                $examIds[$class][$section][$subject] = seedExam($db, [
                    'exam_title'=>'Mid Term 2026', 'exam_type'=>'Mid Term', 'class'=>$class, 'section'=>$section, 'subject'=>$subject,
                    'exam_date'=>'2026-06-' . str_pad((string)(10 + $idx), 2, '0', STR_PAD_LEFT), 'start_time'=>'09:00:00',
                    'end_time'=>'11:00:00', 'room'=>'Room ' . ($idx + 1), 'total_marks'=>100, 'campus'=>'Demo'
                ]);
            }
        }
    }

    foreach ($studentSeeds as $i => $s) {
        $totalObtained = 0;
        $totalPossible = 0;
        foreach ($subjects as $idx => $subject) {
            $examId = $examIds[$s[6]][$s[7]][$subject];
            $obtained = min(98, max(35, 58 + (($i * 7 + $idx * 11) % 38)));
            $grade = gradeFor($obtained);
            $existing = seedScalar($db, "SELECT id FROM exam_marks WHERE student_id=? AND exam_schedule_id=? LIMIT 1", [$students[$s[0]], $examId]);
            if ($existing) {
                seedOne($db, "UPDATE exam_marks SET subject=?, class=?, section=?, total_marks=100, obtained_marks=?, grade=?, remarks=? WHERE id=?", [$subject, $s[6], $s[7], $obtained, $grade, 'Dummy marks for testing', $existing]);
            } else {
                seedOne($db, "INSERT INTO exam_marks (student_id, exam_schedule_id, subject, class, section, total_marks, obtained_marks, grade, remarks) VALUES (?, ?, ?, ?, ?, 100, ?, ?, ?)", [$students[$s[0]], $examId, $subject, $s[6], $s[7], $obtained, $grade, 'Dummy marks for testing']);
            }
            $totalObtained += $obtained;
            $totalPossible += 100;
        }

        for ($d = 1; $d <= 10; $d++) {
            $date = '2026-05-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
            $status = (($i + $d) % 9 === 0) ? 'Absent' : ((($i + $d) % 7 === 0) ? 'Late' : 'Present');
            $existing = seedScalar($db, "SELECT id FROM student_attendance WHERE student_id=? AND attendance_date=? LIMIT 1", [$students[$s[0]], $date]);
            if ($existing) {
                seedOne($db, "UPDATE student_attendance SET status=?, marked_by=? WHERE id=?", [$status, 1, $existing]);
            } else {
                seedOne($db, "INSERT INTO student_attendance (student_id, attendance_date, status, remarks, marked_by) VALUES (?, ?, ?, 'Dummy attendance', ?)", [$students[$s[0]], $date, $status, 1]);
            }
        }

        $percentage = round(($totalObtained / $totalPossible) * 100, 2);
        $grade = gradeFor($percentage);
        $present = (int)seedScalar($db, "SELECT COUNT(*) FROM student_attendance WHERE student_id=? AND status IN ('Present','Late','Half Day')", [$students[$s[0]]]);
        $totalDays = (int)seedScalar($db, "SELECT COUNT(*) FROM student_attendance WHERE student_id=?", [$students[$s[0]]]);
        $examIdForCard = $examIds[$s[6]][$s[7]]['English'];
        $existingCard = seedScalar($db, "SELECT id FROM result_cards WHERE student_id=? AND exam_id=? LIMIT 1", [$students[$s[0]], $examIdForCard]);
        $cardParams = [$students[$s[0]], $examIdForCard, $s[6], $s[12], $year, $totalObtained, $totalPossible, $percentage, $grade, ($i % 4) + 1, $present, $totalDays, 'Dummy result card: consistent effort and good classroom participation.', $percentage >= 40 ? 1 : 0, $i % 3 === 0 ? 'published' : 'draft', 1];
        if ($existingCard) {
            seedOne($db, "UPDATE result_cards SET class_id=?, campus_id=?, session_year=?, total_marks_obtained=?, total_marks_possible=?, percentage=?, grade=?, position_in_class=?, attendance_present=?, attendance_total=?, teacher_remarks=?, is_promoted=?, status=?, generated_by=? WHERE id=?", array_merge(array_slice($cardParams, 2), [$existingCard]));
        } else {
            seedOne($db, "INSERT INTO result_cards (student_id, exam_id, class_id, campus_id, session_year, total_marks_obtained, total_marks_possible, percentage, grade, position_in_class, attendance_present, attendance_total, teacher_remarks, is_promoted, status, generated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $cardParams);
        }

        $voucherNumber = 'DUMMY-VCH-202605-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
        $voucherId = seedScalar($db, "SELECT id FROM vouchers WHERE voucher_number=? LIMIT 1", [$voucherNumber]);
        $voucherTotal = array_sum(array_map(fn($feeId) => (float)seedScalar($db, "SELECT amount FROM fee_structure WHERE id=?", [$feeId]), $feeIds[$s[6]]));
        if ($voucherId) {
            seedOne($db, "UPDATE vouchers SET student_id=?, family_id=?, class=?, voucher_type='individual', issue_date='2026-05-01', due_date='2026-05-30', total_amount=?, status=?, note='Dummy voucher for testing', generated_by=1 WHERE id=?", [$students[$s[0]], $s[10], $s[6], $voucherTotal, $i % 5 === 0 ? 'unpaid' : 'paid', $voucherId]);
            seedOne($db, "DELETE FROM voucher_items WHERE voucher_id=?", [$voucherId]);
        } else {
            seedOne($db, "INSERT INTO vouchers (voucher_number, student_id, family_id, class, voucher_type, issue_date, due_date, total_amount, status, note, generated_by) VALUES (?, ?, ?, ?, 'individual', '2026-05-01', '2026-05-30', ?, ?, 'Dummy voucher for testing', 1)", [$voucherNumber, $students[$s[0]], $s[10], $s[6], $voucherTotal, $i % 5 === 0 ? 'unpaid' : 'paid']);
            $voucherId = (int)$db->lastInsertId();
        }
        foreach ($feeIds[$s[6]] as $feeType => $feeId) {
            $amount = (float)seedScalar($db, "SELECT amount FROM fee_structure WHERE id=?", [$feeId]);
            seedOne($db, "INSERT INTO voucher_items (voucher_id, fee_head, amount) VALUES (?, ?, ?)", [$voucherId, $feeType, $amount]);
        }
    }

    $expenses = [
        ['DUMMY-EXP-001','Misbah Campus - Rajanpur','Utilities','Electricity bill for May 2026',42000,'paid'],
        ['DUMMY-EXP-002','Hamid Campus - Fazilpur','Maintenance','Computer lab maintenance',27500,'approved'],
        ['DUMMY-EXP-003','Abul Rehman Campus - Kot Mithan','Stationery','Exam stationery and printing',18500,'pending'],
        ['DUMMY-EXP-004','Misbah Campus - Rajanpur','Transport','Vehicle fuel and service',36000,'paid'],
    ];
    foreach ($expenses as $exp) {
        $existing = seedScalar($db, "SELECT id FROM expenses WHERE module_name='dummy_seed' AND reference_id=? LIMIT 1", [substr($exp[0], -3)]);
        $params = ['dummy_seed', (int)substr($exp[0], -3), $exp[1], $exp[2], $exp[3], $exp[4], 'manual', $exp[5], 1, $exp[5] === 'pending' ? null : 1];
        if ($existing) {
            seedOne($db, "UPDATE expenses SET campus=?, category=?, description=?, amount=?, expense_type=?, status=?, created_by=?, approved_by=? WHERE id=?", array_merge(array_slice($params, 2), [$existing]));
        } else {
            seedOne($db, "INSERT INTO expenses (module_name, reference_id, campus, category, description, amount, expense_type, status, created_by, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $params);
        }
    }

    if (tableExists($db, 'timetable')) {
        foreach ($classes as $class) {
            foreach (['A', 'B'] as $section) {
                seedOne($db, "DELETE FROM timetable WHERE class=? AND section=? AND subject='English' AND teacher_name='Ayesha Khan'", [$class, $section]);
                seedOne($db, "INSERT INTO timetable (class, section, day_of_week, period_number, subject, teacher_id, start_time, end_time, room_number, teacher_name, day, room, campus) VALUES (?, ?, 'Monday', 1, 'English', 1, '08:30:00', '09:15:00', 'Room 1', 'Ayesha Khan', 'Monday', 'Room 1', 'Demo')", [$class, $section]);
            }
        }
    }

    if (tableExists($db, 'library_books')) {
        foreach ([['DUMMY-LIB-001','Introduction to Computer Science','Ali Raza'], ['DUMMY-LIB-002','English Grammar Practice','S. Khan'], ['DUMMY-LIB-003','College Mathematics','M. Bilal']] as $book) {
            $existing = seedScalar($db, "SELECT id FROM library_books WHERE book_id=? LIMIT 1", [$book[0]]);
            if ($existing) {
                seedOne($db, "UPDATE library_books SET title=?, author=?, quantity=5, available=4, category='Academic', total_copies=5, available_copies=4, status='Available' WHERE id=?", [$book[1], $book[2], $existing]);
            } else {
                seedOne($db, "INSERT INTO library_books (title, author, quantity, available, category, book_id, total_copies, available_copies, status) VALUES (?, ?, 5, 4, 'Academic', ?, 5, 4, 'Available')", [$book[1], $book[2], $book[0]]);
            }
        }
    }

    if (tableExists($db, 'pos_products')) {
        foreach ([['Notebook', 'Stationery', 180, 50], ['College Badge', 'Uniform', 120, 80], ['Exam File', 'Stationery', 90, 100]] as $product) {
            $existing = seedScalar($db, "SELECT id FROM pos_products WHERE name=? LIMIT 1", [$product[0]]);
            if ($existing) {
                seedOne($db, "UPDATE pos_products SET category=?, price=?, stock=?, is_active=1 WHERE id=?", [$product[1], $product[2], $product[3], $existing]);
            } else {
                seedOne($db, "INSERT INTO pos_products (name, category, price, stock, is_active) VALUES (?, ?, ?, ?, 1)", $product);
            }
        }
    }

    $db->commit();
    echo "Dummy seed completed.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Dummy seed failed: " . $e->getMessage() . "\n");
    exit(1);
}
?>
