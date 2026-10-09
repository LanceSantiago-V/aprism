-- Establishes per-meeting attendance persistence without recording attendance automatically.

CREATE TABLE IF NOT EXISTS attendance_sessions (
    attendance_session_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    operational_class_id INT(10) UNSIGNED NOT NULL,
    class_schedule_id INT(10) UNSIGNED NOT NULL,
    meeting_date DATE NOT NULL,
    scheduled_day VARCHAR(20) NOT NULL,
    scheduled_start_time TIME NOT NULL,
    scheduled_end_time TIME NOT NULL,
    scheduled_room VARCHAR(100) DEFAULT NULL,
    status ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (attendance_session_id),
    UNIQUE KEY uq_attendance_session_schedule_date (class_schedule_id, meeting_date),
    KEY idx_attendance_session_class_date (operational_class_id, meeting_date),
    KEY idx_attendance_session_status (status),
    CONSTRAINT fk_attendance_session_class
        FOREIGN KEY (operational_class_id)
        REFERENCES operational_classes (operational_class_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_attendance_session_schedule
        FOREIGN KEY (class_schedule_id)
        REFERENCES class_schedules (class_schedule_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_attendance_records (
    student_attendance_record_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    attendance_session_id INT(10) UNSIGNED NOT NULL,
    student_class_enrollment_id INT(10) UNSIGNED NOT NULL,
    status ENUM('Present', 'Late', 'Absent') NOT NULL,
    checked_in_at DATETIME DEFAULT NULL,
    capture_source ENUM('Browser Camera AprilTag', 'Manual Correction') DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_attendance_record_id),
    UNIQUE KEY uq_student_attendance_session_enrollment (
        attendance_session_id,
        student_class_enrollment_id
    ),
    KEY idx_student_attendance_session_status (attendance_session_id, status),
    KEY idx_student_attendance_enrollment (student_class_enrollment_id),
    CONSTRAINT fk_student_attendance_session
        FOREIGN KEY (attendance_session_id)
        REFERENCES attendance_sessions (attendance_session_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_student_attendance_enrollment
        FOREIGN KEY (student_class_enrollment_id)
        REFERENCES student_class_enrollments (student_class_enrollment_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
