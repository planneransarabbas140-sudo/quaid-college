-- Result Cards Module
-- Version: 1.0
-- Tables: result_cards, grading_system
-- Related files: result-cards.php, result-cards-print.php,
--                result-cards-ajax.php,
--                includes/result_card_functions.php
-- Related modules: Exam Management, Students, Attendance
-- Reset: TRUNCATE result_cards;
-- Grading: UPDATE grading_system SET ... to adjust grades

CREATE TABLE IF NOT EXISTS result_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    class_id VARCHAR(100) NOT NULL,
    campus_id INT DEFAULT NULL,
    session_year VARCHAR(20) NOT NULL,
    total_marks_obtained DECIMAL(8,2) DEFAULT 0,
    total_marks_possible DECIMAL(8,2) DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    grade VARCHAR(5) DEFAULT NULL,
    position_in_class INT DEFAULT NULL,
    attendance_present INT DEFAULT 0,
    attendance_total INT DEFAULT 0,
    teacher_remarks TEXT DEFAULT NULL,
    is_promoted TINYINT(1) DEFAULT NULL,
    status ENUM('draft','published') DEFAULT 'draft',
    generated_by INT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_exam (student_id, exam_id),
    INDEX idx_result_cards_class_exam (class_id, exam_id),
    INDEX idx_result_cards_status (status),
    INDEX idx_result_cards_campus (campus_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grading_system (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_percentage DECIMAL(5,2) NOT NULL,
    max_percentage DECIMAL(5,2) NOT NULL,
    grade VARCHAR(5) NOT NULL UNIQUE,
    remarks VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO grading_system
(min_percentage, max_percentage, grade, remarks) VALUES
(90, 100, 'A+', 'Outstanding'),
(80, 89.99, 'A',  'Excellent'),
(70, 79.99, 'B+', 'Very Good'),
(60, 69.99, 'B',  'Good'),
(50, 59.99, 'C',  'Satisfactory'),
(40, 49.99, 'D',  'Pass'),
(0,  39.99, 'F',  'Fail');
