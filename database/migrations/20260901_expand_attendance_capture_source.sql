-- Expands attendance provenance for manual capture and system finalization.

ALTER TABLE student_attendance_records
    MODIFY COLUMN capture_source ENUM(
        'Browser Camera AprilTag',
        'Manual Attendance',
        'Manual Correction',
        'System'
    ) DEFAULT NULL;
