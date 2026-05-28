-- Complaints / Suggestions module migration

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

ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complaint_number VARCHAR(100) DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complainant_name VARCHAR(150) DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complainant_type VARCHAR(50) NOT NULL DEFAULT 'student';
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS contact_no VARCHAR(50) DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS subject VARCHAR(255) DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'pending';
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'medium';
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS assigned_to INT DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS response TEXT DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS submitted_by INT DEFAULT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS resolved_at DATETIME DEFAULT NULL;
