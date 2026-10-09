/*
 * Guidance Officer and shared in-app notification foundation.
 * Existing role ID 4 is preserved so legacy user records keep their identity.
 */
UPDATE roles
SET role_name = 'Guidance Officer',
    description = 'Manages confidential intervention cases, follow-ups, and permitted referral workflows.'
WHERE role_id = 4
  AND role_name = 'Disciplinary Officer';

ALTER TABLE teacher_referrals
    MODIFY status ENUM('Submitted', 'Returned', 'Accepted', 'Closed') NOT NULL DEFAULT 'Submitted';

CREATE TABLE notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_user_id INT NOT NULL,
    notification_type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message VARCHAR(500) NOT NULL,
    context_url VARCHAR(500) NULL,
    source_entity_type VARCHAR(80) NULL,
    source_entity_id BIGINT UNSIGNED NULL,
    deduplication_key VARCHAR(191) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (notification_id),
    UNIQUE KEY uq_notifications_target_dedupe (target_user_id, deduplication_key),
    KEY idx_notifications_target_unread (target_user_id, read_at, created_at),
    KEY idx_notifications_expiry (expires_at, archived_at),
    CONSTRAINT fk_notifications_target
        FOREIGN KEY (target_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guidance_cases (
    guidance_case_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_referral_id INT UNSIGNED NOT NULL,
    case_number VARCHAR(40) NOT NULL,
    case_status ENUM('Open', 'Active', 'Closed') NOT NULL DEFAULT 'Open',
    current_assignee_user_id INT NULL,
    non_confidential_disposition TEXT NULL,
    opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL DEFAULT NULL,
    closed_by_user_id INT NULL,
    PRIMARY KEY (guidance_case_id),
    UNIQUE KEY uq_guidance_cases_referral (teacher_referral_id),
    UNIQUE KEY uq_guidance_cases_number (case_number),
    KEY idx_guidance_cases_status_assignee (case_status, current_assignee_user_id),
    CONSTRAINT fk_guidance_cases_referral
        FOREIGN KEY (teacher_referral_id) REFERENCES teacher_referrals (teacher_referral_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_cases_assignee
        FOREIGN KEY (current_assignee_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_cases_closed_by
        FOREIGN KEY (closed_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guidance_case_assignments (
    guidance_case_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    guidance_case_id INT UNSIGNED NOT NULL,
    assigned_user_id INT NOT NULL,
    assigned_by_user_id INT NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (guidance_case_assignment_id),
    KEY idx_guidance_case_assignments_case (guidance_case_id, ended_at),
    KEY idx_guidance_case_assignments_user (assigned_user_id, ended_at),
    CONSTRAINT fk_guidance_case_assignments_case
        FOREIGN KEY (guidance_case_id) REFERENCES guidance_cases (guidance_case_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_case_assignments_user
        FOREIGN KEY (assigned_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_case_assignments_actor
        FOREIGN KEY (assigned_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guidance_case_events (
    guidance_case_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    guidance_case_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    event_summary VARCHAR(500) NOT NULL,
    actor_user_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (guidance_case_event_id),
    KEY idx_guidance_case_events_case (guidance_case_id, created_at),
    CONSTRAINT fk_guidance_case_events_case
        FOREIGN KEY (guidance_case_id) REFERENCES guidance_cases (guidance_case_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_case_events_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guidance_confidential_notes (
    guidance_confidential_note_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    guidance_case_id INT UNSIGNED NOT NULL,
    author_user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (guidance_confidential_note_id),
    KEY idx_guidance_confidential_notes_case (guidance_case_id, created_at),
    CONSTRAINT fk_guidance_confidential_notes_case
        FOREIGN KEY (guidance_case_id) REFERENCES guidance_cases (guidance_case_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_confidential_notes_author
        FOREIGN KEY (author_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guidance_followups (
    guidance_followup_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    guidance_case_id INT UNSIGNED NOT NULL,
    assigned_user_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    followup_status ENUM('Scheduled', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
    completion_note TEXT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_by_user_id INT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (guidance_followup_id),
    KEY idx_guidance_followups_assignee_due (assigned_user_id, followup_status, scheduled_at),
    KEY idx_guidance_followups_case (guidance_case_id, scheduled_at),
    CONSTRAINT fk_guidance_followups_case
        FOREIGN KEY (guidance_case_id) REFERENCES guidance_cases (guidance_case_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_followups_assignee
        FOREIGN KEY (assigned_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_followups_creator
        FOREIGN KEY (created_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_guidance_followups_cancelled_by
        FOREIGN KEY (cancelled_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
