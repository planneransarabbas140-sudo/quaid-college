-- Library circulation migration for Quaid College ERP
-- Run before using modules/library/issue.php, return.php, and fines.php.

CREATE TABLE IF NOT EXISTS library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id VARCHAR(50) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(150) NOT NULL,
    isbn VARCHAR(50) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    publisher VARCHAR(150) DEFAULT NULL,
    publication_year INT DEFAULT NULL,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    shelf_number VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_library_books_code (book_id),
    INDEX idx_library_books_title (title),
    INDEX idx_library_books_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE library_books
    ADD COLUMN IF NOT EXISTS book_id VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS publisher VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS publication_year INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS total_copies INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS available_copies INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS shelf_number VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'Available';

UPDATE library_books
SET total_copies = CASE WHEN COALESCE(total_copies, 0) > 0 THEN total_copies ELSE 1 END,
    available_copies = CASE WHEN COALESCE(available_copies, 0) > 0 THEN available_copies ELSE total_copies END;

CREATE TABLE IF NOT EXISTS library_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    issue_date DATE NOT NULL,
    return_date DATE NOT NULL,
    actual_return DATE DEFAULT NULL,
    fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'issued',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_library_issues_book (book_id),
    INDEX idx_library_issues_student (student_id),
    INDEX idx_library_issues_status (status),
    INDEX idx_library_issues_due (return_date),
    CONSTRAINT fk_library_issues_book
        FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE,
    CONSTRAINT fk_library_issues_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE library_issues
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'issued';

UPDATE library_issues
SET status = CASE LOWER(status)
    WHEN 'returned' THEN 'returned'
    ELSE 'issued'
END;
