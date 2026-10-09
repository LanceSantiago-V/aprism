CREATE TABLE adviser_assignments (
    adviser_assignment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    section_id INT UNSIGNED NOT NULL,
    school_year_id INT UNSIGNED NOT NULL,
    status ENUM('Active', 'Ended', 'Archived') NOT NULL DEFAULT 'Active',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_adviser_assignment_section_term (section_id, school_year_id),
    KEY idx_adviser_assignment_teacher_status (teacher_id, status),
    KEY idx_adviser_assignment_term (school_year_id, status),
    CONSTRAINT fk_adviser_assignments_teacher FOREIGN KEY (teacher_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_adviser_assignments_section FOREIGN KEY (section_id) REFERENCES sections (section_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_adviser_assignments_school_year FOREIGN KEY (school_year_id) REFERENCES school_years (school_year_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
