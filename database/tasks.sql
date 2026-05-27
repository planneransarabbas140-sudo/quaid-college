-- Tasks / Work Management module migration

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

ALTER TABLE tasks ADD COLUMN IF NOT EXISTS title VARCHAR(255) DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS task_title VARCHAR(255) DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS assigned_to INT DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS assigned_by INT DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'medium';
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS due_date DATE DEFAULT NULL;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'pending';
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE tasks SET title = task_title WHERE (title IS NULL OR title = '') AND task_title IS NOT NULL;
UPDATE tasks SET task_title = title WHERE (task_title IS NULL OR task_title = '') AND title IS NOT NULL;
UPDATE tasks SET status = CASE LOWER(REPLACE(status, ' ', '_'))
    WHEN 'completed' THEN 'completed'
    WHEN 'cancelled' THEN 'cancelled'
    WHEN 'canceled' THEN 'cancelled'
    WHEN 'in_progress' THEN 'in_progress'
    ELSE 'pending'
END;
UPDATE tasks SET priority = CASE LOWER(priority)
    WHEN 'low' THEN 'low'
    WHEN 'high' THEN 'high'
    WHEN 'urgent' THEN 'urgent'
    ELSE 'medium'
END;
