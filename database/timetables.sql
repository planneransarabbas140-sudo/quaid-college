-- Timetable Management module migration

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

ALTER TABLE timetables ADD COLUMN IF NOT EXISTS class_id VARCHAR(100) DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS section_id VARCHAR(50) DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS subject_id VARCHAR(120) DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS teacher_id INT DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS day_of_week VARCHAR(20) DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS start_time TIME DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS room_no VARCHAR(50) DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active';
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS legacy_timetable_id INT DEFAULT NULL;
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL;

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

ALTER TABLE timetable ADD COLUMN IF NOT EXISTS day VARCHAR(20) DEFAULT NULL;
ALTER TABLE timetable ADD COLUMN IF NOT EXISTS day_of_week VARCHAR(20) DEFAULT NULL;
ALTER TABLE timetable ADD COLUMN IF NOT EXISTS period_number INT DEFAULT 1;
ALTER TABLE timetable ADD COLUMN IF NOT EXISTS teacher_name VARCHAR(180) DEFAULT NULL;
ALTER TABLE timetable ADD COLUMN IF NOT EXISTS room VARCHAR(50) DEFAULT NULL;
ALTER TABLE timetable ADD COLUMN IF NOT EXISTS room_number VARCHAR(50) DEFAULT NULL;
ALTER TABLE timetable ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active';

INSERT INTO timetables (class_id, section_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no, status, legacy_timetable_id, created_by)
SELECT t.class, t.section, t.subject, t.teacher_id, COALESCE(NULLIF(t.day_of_week, ''), t.day), t.start_time, t.end_time, COALESCE(t.room_number, t.room), COALESCE(t.status, 'active'), t.id, NULL
FROM timetable t
WHERE NOT EXISTS (SELECT 1 FROM timetables tt WHERE tt.legacy_timetable_id = t.id);
