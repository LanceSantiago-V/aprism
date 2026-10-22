-- One immutable capture per Academic Period and Operational Class. The child
-- rows retain every eligible class attempt, including students without a tier.
CREATE TABLE IF NOT EXISTS risk_period_class_captures (
    risk_period_class_capture_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    academic_period_id INT UNSIGNED NOT NULL,
    operational_class_id INT UNSIGNED NOT NULL,
    cutoff_date DATE NOT NULL,
    origin ENUM('AutomaticPeriodClose', 'FixtureReconstruction') NOT NULL,
    cohort_count INT UNSIGNED NOT NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_risk_period_class (academic_period_id, operational_class_id),
    KEY idx_risk_capture_class (operational_class_id),
    CONSTRAINT fk_risk_capture_period FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods (academic_period_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_risk_capture_class FOREIGN KEY (operational_class_id)
        REFERENCES operational_classes (operational_class_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_period_student_snapshots (
    risk_period_student_snapshot_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    risk_period_class_capture_id INT UNSIGNED NOT NULL,
    student_class_enrollment_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    assessable TINYINT(1) NOT NULL,
    tier TINYINT UNSIGNED NULL,
    recorded_count INT UNSIGNED NOT NULL,
    absent_count INT UNSIGNED NOT NULL,
    late_count INT UNSIGNED NOT NULL,
    effective_absent_count INT UNSIGNED NOT NULL,
    consecutive_absence_count INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_risk_capture_attempt (risk_period_class_capture_id, student_class_enrollment_id),
    KEY idx_risk_snapshot_student (student_id),
    CONSTRAINT fk_risk_snapshot_capture FOREIGN KEY (risk_period_class_capture_id)
        REFERENCES risk_period_class_captures (risk_period_class_capture_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_risk_snapshot_sce FOREIGN KEY (student_class_enrollment_id)
        REFERENCES student_class_enrollments (student_class_enrollment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_risk_snapshot_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_risk_snapshot_tier CHECK (tier IS NULL OR tier BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
