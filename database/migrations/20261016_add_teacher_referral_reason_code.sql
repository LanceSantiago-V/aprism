-- Persist the Teacher-selected Quick Referral reason without guessing a
-- category for historical free-text referrals. Existing rows remain NULL and
-- continue to retain their original `reason` text.
ALTER TABLE teacher_referrals
    ADD COLUMN reason_code ENUM(
        'frequent_absences',
        'repeated_lateness',
        'low_academic_performance',
        'missing_late_requirements',
        'behavior_conduct',
        'other'
    ) NULL DEFAULT NULL AFTER semester_snapshot;
