/* Minimal Teacher-originated referral provenance. Guidance handling is deferred. */
CREATE TABLE teacher_referrals (
    teacher_referral_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    operational_class_id INT UNSIGNED NULL,
    student_class_enrollment_id INT UNSIGNED NULL,
    school_year_snapshot VARCHAR(9) NULL,
    semester_snapshot VARCHAR(30) NULL,
    reason TEXT NOT NULL,
    status ENUM('Submitted','Closed') NOT NULL DEFAULT 'Submitted',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (teacher_referral_id),
    KEY idx_teacher_referrals_teacher_status (teacher_id, status, created_at),
    KEY idx_teacher_referrals_student (student_id, created_at),
    KEY idx_teacher_referrals_class (operational_class_id, created_at),
    CONSTRAINT fk_teacher_referrals_teacher FOREIGN KEY (teacher_id) REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_teacher_referrals_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_teacher_referrals_class FOREIGN KEY (operational_class_id) REFERENCES operational_classes (operational_class_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_teacher_referrals_sce FOREIGN KEY (student_class_enrollment_id) REFERENCES student_class_enrollments (student_class_enrollment_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
