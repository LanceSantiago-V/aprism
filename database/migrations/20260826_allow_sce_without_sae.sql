-- Allows valid Student Class Enrollment participation when academic placement is unknown.
-- Run once after database/13_students.sql. Do not use this as a database replacement.

ALTER TABLE student_class_enrollments
    DROP FOREIGN KEY fk_sce_enrollment_student;

ALTER TABLE student_class_enrollments
    MODIFY enrollment_id INT(10) UNSIGNED NULL;

ALTER TABLE student_class_enrollments
    ADD CONSTRAINT fk_sce_enrollment_student
        FOREIGN KEY (enrollment_id, student_id)
        REFERENCES student_academic_enrollments (student_academic_enrollment_id, student_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
