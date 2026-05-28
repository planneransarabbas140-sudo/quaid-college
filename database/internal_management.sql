-- Internal Management / 3-in-1 module migration

CREATE TABLE IF NOT EXISTS internal_management (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(220) NOT NULL,
    description TEXT DEFAULT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'note',
    category VARCHAR(120) DEFAULT 'General',
    file_path VARCHAR(255) DEFAULT NULL,
    visibility VARCHAR(30) NOT NULL DEFAULT 'private',
    created_by INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    INDEX idx_internal_type (type),
    INDEX idx_internal_category (category),
    INDEX idx_internal_status (status),
    INDEX idx_internal_assigned_to (assigned_to),
    INDEX idx_internal_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
