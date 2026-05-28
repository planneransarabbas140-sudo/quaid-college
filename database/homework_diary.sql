-- Homework / Student Diary module migration
-- Keeps legacy diary_homework columns while adding the new module fields.

CREATE TABLE IF NOT EXISTS homework_diary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diary_date DATE DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    class VARCHAR(100) DEFAULT NULL,
    section VARCHAR(50) DEFAULT NULL,
    subject VARCHAR(120) DEFAULT NULL,
    title VARCHAR(180) DEFAULT NULL,
    homework TEXT DEFAULT NULL,
    instructions TEXT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    status ENUM('Assigned','Completed','Archived') DEFAULT 'Assigned',
    class_id VARCHAR(100) DEFAULT NULL,
    section_id VARCHAR(50) DEFAULT NULL,
    subject_id VARCHAR(120) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    homework_title VARCHAR(180) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    homework_status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_homework_class (class_id, section_id),
    INDEX idx_homework_due (due_date),
    INDEX idx_homework_status (homework_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS class_id VARCHAR(100) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS section_id VARCHAR(50) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS subject_id VARCHAR(120) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS teacher_id INT DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS homework_title VARCHAR(180) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS homework_status VARCHAR(20) NOT NULL DEFAULT 'active';
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS diary_date DATE DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS class VARCHAR(100) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS section VARCHAR(50) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS subject VARCHAR(120) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS title VARCHAR(180) DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS homework TEXT DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS instructions TEXT DEFAULT NULL;
ALTER TABLE homework_diary ADD COLUMN IF NOT EXISTS assigned_by INT DEFAULT NULL;
ALTER TABLE homework_diary MODIFY class_id VARCHAR(100) DEFAULT NULL;
ALTER TABLE homework_diary MODIFY subject_id VARCHAR(120) DEFAULT NULL;
ALTER TABLE homework_diary MODIFY homework_title VARCHAR(180) DEFAULT NULL;
ALTER TABLE homework_diary MODIFY description TEXT DEFAULT NULL;

UPDATE homework_diary SET class_id = class WHERE (class_id IS NULL OR class_id = '') AND class IS NOT NULL;
UPDATE homework_diary SET section_id = section WHERE (section_id IS NULL OR section_id = '') AND section IS NOT NULL;
UPDATE homework_diary SET subject_id = subject WHERE (subject_id IS NULL OR subject_id = '') AND subject IS NOT NULL;
UPDATE homework_diary SET homework_title = title WHERE (homework_title IS NULL OR homework_title = '') AND title IS NOT NULL;
UPDATE homework_diary SET description = homework WHERE (description IS NULL OR description = '') AND homework IS NOT NULL;
UPDATE homework_diary SET created_by = assigned_by WHERE created_by IS NULL AND assigned_by IS NOT NULL;
UPDATE homework_diary
SET status = CASE LOWER(COALESCE(status, 'assigned'))
    WHEN 'submitted' THEN 'Completed'
    WHEN 'completed' THEN 'Completed'
    WHEN 'archived' THEN 'Archived'
    WHEN 'expired' THEN 'Archived'
    ELSE 'Assigned'
END
WHERE status IS NULL OR status NOT IN ('Assigned','Completed','Archived');
UPDATE homework_diary
SET homework_status = CASE LOWER(COALESCE(homework_status, status, 'active'))
    WHEN 'submitted' THEN 'submitted'
    WHEN 'completed' THEN 'submitted'
    WHEN 'archived' THEN 'expired'
    WHEN 'expired' THEN 'expired'
    ELSE 'active'
END
WHERE homework_status IS NULL OR homework_status = '' OR homework_status NOT IN ('active','submitted','expired');
