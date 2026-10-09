-- Current AprilTag ownership is global and persistent. Available inventory
-- remains representable because MySQL permits multiple NULLs in a UNIQUE key.
ALTER TABLE april_tags
    ADD UNIQUE KEY uq_april_tags_current_student_global (current_student_id);
