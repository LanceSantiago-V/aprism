/*
 * Correct Grade Import provenance from one Academic Period per batch to one
 * Operational Class teaching term per batch. Existing batch rows are preserved.
 */
ALTER TABLE grade_import_batches
    ADD COLUMN school_year_snapshot VARCHAR(9) NULL AFTER operational_class_id,
    ADD COLUMN semester_snapshot VARCHAR(30) NULL AFTER school_year_snapshot;

UPDATE grade_import_batches gib
INNER JOIN operational_classes oc
    ON oc.operational_class_id = gib.operational_class_id
SET gib.school_year_snapshot = oc.school_year,
    gib.semester_snapshot = oc.semester
WHERE gib.school_year_snapshot IS NULL
   OR gib.semester_snapshot IS NULL;

ALTER TABLE grade_import_batches
    MODIFY school_year_snapshot VARCHAR(9) NOT NULL,
    MODIFY semester_snapshot VARCHAR(30) NOT NULL,
    DROP FOREIGN KEY fk_grade_import_batches_period,
    DROP INDEX idx_grade_import_batches_context,
    DROP COLUMN academic_period_id,
    ADD INDEX idx_grade_import_batches_context (operational_class_id, school_year_snapshot, semester_snapshot),
    ADD INDEX idx_grade_import_batches_term_source (teacher_id, operational_class_id, school_year_snapshot, semester_snapshot, source_sha256, status);
