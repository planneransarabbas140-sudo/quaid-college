-- Staff Attendance migration for Quaid College ERP
-- Safe to run on existing databases.

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
    INDEX idx_staff_attendance_status (status),
    CONSTRAINT fk_staff_attendance_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_attendance_marked_by
        FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE staff_attendance
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'present';

UPDATE staff_attendance
SET status = CASE LOWER(status)
    WHEN 'present' THEN 'present'
    WHEN 'absent' THEN 'absent'
    WHEN 'leave' THEN 'leave'
    WHEN 'half day' THEN 'leave'
    WHEN 'late' THEN 'present'
    ELSE 'present'
END;
