/*
 * Adviser assignments are academic scope, not Teacher class ownership.
 *
 * The existing Academic Setup stores the broader SHS semester vocabulary on
 * academic_periods.semester (First Semester / Second Semester), but does not
 * provide a separate persisted semester entity.  Keep the assignment context
 * explicit on the assignment until that canonical domain is introduced.
 * Existing rows remain preserved with a NULL context and therefore cannot be
 * treated as valid current Adviser scope until Academic Head reassigns them.
 */

ALTER TABLE adviser_assignments
    ADD COLUMN teaching_context VARCHAR(30) NULL AFTER school_year_id,
    ADD COLUMN active_teacher_context_key VARCHAR(100) NULL AFTER active_assignment_key;

/* Existing active rows have no authoritative context. Preserve them, but
 * remove their legacy active key so the new context-aware keys are exclusive
 * to assignments created by the approved workflow. */
UPDATE adviser_assignments
SET active_assignment_key = NULL,
    active_teacher_context_key = NULL
WHERE teaching_context IS NULL;

ALTER TABLE adviser_assignments
    DROP INDEX uq_active_adviser_assignment_section_term,
    ADD UNIQUE KEY uq_active_adviser_assignment_section_context (active_assignment_key),
    ADD UNIQUE KEY uq_active_adviser_assignment_teacher_context (active_teacher_context_key);

ALTER TABLE sections
    MODIFY year_level ENUM('1', '2', '3', '4', '11', '12') NULL;
