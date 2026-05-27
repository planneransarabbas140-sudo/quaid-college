-- Parent Teacher Meeting module migration

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

ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS title VARCHAR(220) DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS class_id VARCHAR(100) DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS section_id VARCHAR(50) DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS teacher_id INT DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS meeting_date DATE DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS start_time TIME DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS venue VARCHAR(180) DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS agenda TEXT DEFAULT NULL;
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'scheduled';
ALTER TABLE ptm_meetings ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL;

-- If an older seed table has class_name/remarks columns, copy them into class_id/agenda.
-- The PHP module performs this compatibility sync only after checking those columns exist.
