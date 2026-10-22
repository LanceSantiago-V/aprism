CREATE TABLE operational_class_handovers (
    operational_class_handover_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    operational_class_id INT UNSIGNED NOT NULL,
    from_teacher_id INT NOT NULL,
    to_teacher_id INT NOT NULL,
    authorized_by_user_id INT NOT NULL,
    effective_at DATETIME(6) NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (operational_class_handover_id),
    KEY idx_class_handover_history (operational_class_id, effective_at),
    KEY idx_class_handover_from (from_teacher_id),
    KEY idx_class_handover_to (to_teacher_id),
    CONSTRAINT fk_class_handover_class FOREIGN KEY (operational_class_id)
        REFERENCES operational_classes (operational_class_id) ON DELETE RESTRICT,
    CONSTRAINT fk_class_handover_from FOREIGN KEY (from_teacher_id)
        REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_class_handover_to FOREIGN KEY (to_teacher_id)
        REFERENCES users (user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_class_handover_authorizer FOREIGN KEY (authorized_by_user_id)
        REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
