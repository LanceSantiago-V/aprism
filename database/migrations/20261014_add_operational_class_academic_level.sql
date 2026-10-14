-- Store the authoritative Academic Level snapshot for each Operational Class.
-- Existing classes remain NULL until confirmed through a deliberate workflow;
-- no historical value is inferred or backfilled by this migration.
ALTER TABLE operational_classes
    ADD COLUMN academic_level ENUM('College', 'Senior High School') NULL DEFAULT NULL
        COMMENT 'Teacher-confirmed class Academic Level; not an Academic Period' AFTER section_id;
