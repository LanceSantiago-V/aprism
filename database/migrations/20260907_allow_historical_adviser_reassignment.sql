SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE adviser_assignments
    DROP INDEX uq_adviser_assignment_section_term,
    ADD COLUMN active_assignment_key VARCHAR(80) NULL;

UPDATE adviser_assignments
SET active_assignment_key = CONCAT(section_id, ':', school_year_id)
WHERE status = 'Active';

ALTER TABLE adviser_assignments
    ADD UNIQUE KEY uq_active_adviser_assignment_section_term (active_assignment_key);

SET FOREIGN_KEY_CHECKS = 1;
