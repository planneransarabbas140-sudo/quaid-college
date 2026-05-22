CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','principal','teacher','student') NOT NULL DEFAULT 'student',
  `campus` enum('Rajanpur','Fazilpur','Kot Mithan') NOT NULL DEFAULT 'Rajanpur',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default Admin User (Password: Admin@123)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `campus`, `status`) 
VALUES ('admin', 'admin@qac.edu.pk', '$2y$10$8W3Y6L5vG5R2x7Z8N9J2O.tI3I1S1B1K1L1M1N1O1P1Q1R1S1T1U1', 'admin', 'Rajanpur', 'active')
ON DUPLICATE KEY UPDATE id=id;
-- Note: The hash above is a placeholder. In a real scenario, use: password_hash('Admin@123', PASSWORD_DEFAULT)
