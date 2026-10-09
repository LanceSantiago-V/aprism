CREATE TABLE teacher_responsibility_assignments (
    responsibility_assignment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    responsibility_name VARCHAR(50) NOT NULL,
    school_year_id INT UNSIGNED NOT NULL,
    status ENUM('Active', 'Ended', 'Archived') NOT NULL DEFAULT 'Active',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_responsibility_school_year (teacher_id, responsibility_name, school_year_id),
    KEY idx_teacher_responsibility_status (teacher_id, responsibility_name, status),
    KEY idx_teacher_responsibility_school_year (school_year_id, responsibility_name, status),
    CONSTRAINT fk_teacher_responsibility_teacher FOREIGN KEY (teacher_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_teacher_responsibility_school_year FOREIGN KEY (school_year_id) REFERENCES school_years (school_year_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
