-- Preserve the factual attendance evidence visible when a Teacher submits a referral.
-- This nullable JSON document is referral-specific history, not a live Tier record.
ALTER TABLE teacher_referrals
    ADD COLUMN evidence_snapshot_json LONGTEXT NULL AFTER reason;
