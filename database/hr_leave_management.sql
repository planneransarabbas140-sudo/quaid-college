-- HR Leave Management migration for Quaid College ERP
-- Safe for new databases. For older tables with ENUM status values, this
-- migration normalizes status to lowercase strings used by the module.

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
    INDEX idx_leave_dates (start_date, end_date),
    CONSTRAINT fk_leave_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE leave_applications
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending';

ALTER TABLE leave_applications
    ADD COLUMN IF NOT EXISTS total_days INT NOT NULL DEFAULT 1 AFTER end_date,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL AFTER approved_by;

UPDATE leave_applications
SET total_days = GREATEST(DATEDIFF(end_date, start_date) + 1, 1)
WHERE total_days IS NULL OR total_days <= 0;

UPDATE leave_applications
SET status = CASE LOWER(status)
    WHEN 'approved' THEN 'approved'
    WHEN 'rejected' THEN 'rejected'
    ELSE 'pending'
END;
