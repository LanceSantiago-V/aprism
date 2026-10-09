/* Append-only Teacher referral follow-up history. Existing referral status values remain authoritative. */
CREATE TABLE teacher_referral_followups (
    referral_followup_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_referral_id INT UNSIGNED NOT NULL,
    author_user_id INT NOT NULL,
    note TEXT NOT NULL,
    status_after ENUM('Submitted','Closed') NOT NULL DEFAULT 'Submitted',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (referral_followup_id),
    KEY idx_teacher_referral_followups_referral (teacher_referral_id, created_at),
    KEY idx_teacher_referral_followups_author (author_user_id, created_at),
    CONSTRAINT fk_teacher_referral_followups_referral
        FOREIGN KEY (teacher_referral_id) REFERENCES teacher_referrals (teacher_referral_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_teacher_referral_followups_author
        FOREIGN KEY (author_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
