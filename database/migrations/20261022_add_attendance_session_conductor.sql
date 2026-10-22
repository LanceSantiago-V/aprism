ALTER TABLE attendance_sessions
    ADD COLUMN conducted_by_user_id INT NULL AFTER operational_class_id,
    ADD KEY idx_attendance_session_conductor (conducted_by_user_id),
    ADD CONSTRAINT fk_attendance_session_conductor
        FOREIGN KEY (conducted_by_user_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
