/* ============================================================
   APRISM — STUDENT / ACADEMIC CONTEXT / CLASS PARTICIPATION
   FINAL APPROVED SCHEMA
   ============================================================

   Creates ONLY:
     1. students
     2. student_academic_enrollments
     3. student_class_enrollments

   Does NOT alter:
     school_years
     academic_periods
     programs
     subjects
     sections
     operational_classes
     class_schedules
*/


/* ============================================================
   A. CREATE TABLE — students
   ============================================================ */

CREATE TABLE IF NOT EXISTS `students` (
    `student_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

    `student_number` VARCHAR(50) NOT NULL,

    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `suffix` VARCHAR(30) DEFAULT NULL,

    `status` ENUM(
        'Active',
        'Inactive',
        'Archived'
    ) NOT NULL DEFAULT 'Active',

    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`student_id`),

    UNIQUE KEY `uq_students_student_number`
        (`student_number`),

    KEY `idx_students_last_name`
        (`last_name`),

    KEY `idx_students_first_name`
        (`first_name`),

    KEY `idx_students_status`
        (`status`)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


/* ============================================================
   B. CREATE TABLE — student_academic_enrollments
   ============================================================ */

CREATE TABLE IF NOT EXISTS `student_academic_enrollments` (
    `student_academic_enrollment_id`
        INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

    `student_id`
        INT(10) UNSIGNED NOT NULL,

    `school_year_id`
        INT(10) UNSIGNED NOT NULL,

    /*
     * Broader academic context.
     *
     * College:
     *   First Semester / Second Semester
     *
     * SHS:
     *   NULL under the current Academic Setup structure.
     *
     * This is NOT an Academic Period identifier.
     */
    `semester`
        VARCHAR(30) DEFAULT NULL,

    /*
     * Historical academic level.
     *
     * This is stored on the enrollment because the enrollment
     * itself must remain historically queryable even after the
     * student's later academic context changes.
     */
    `academic_level`
        ENUM(
            'College',
            'Senior High School'
        ) NOT NULL,

    /*
     * Nullable because an identified Student may have an
     * incomplete or ambiguous academic placement.
     */
    `program_id`
        INT(10) UNSIGNED DEFAULT NULL,

    `section_id`
        INT(10) UNSIGNED DEFAULT NULL,

    /*
     * Contextual year level.
     *
     * VARCHAR is intentional:
     * College may use 1–4;
     * SHS may use 11–12;
     * future institutional structures must not require
     * parsing Section names.
     */
    `year_level`
        VARCHAR(20) DEFAULT NULL,

    /*
     * Lifecycle / resolution state.
     *
     * Review / Incomplete preserve the Student identity
     * without inventing uncertain placement data.
     */
    `status`
        ENUM(
            'Active',
            'Inactive',
            'Review',
            'Incomplete',
            'Archived'
        ) NOT NULL DEFAULT 'Active',

    /*
     * Historical validity window for this placement.
     */
    `effective_start`
        DATE NOT NULL,

    `effective_end`
        DATE DEFAULT NULL,

    `created_at`
        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `updated_at`
        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`student_academic_enrollment_id`),

    /*
     * Supporting indexes for historical queries.
     */
    KEY `idx_sae_student`
        (`student_id`),

    KEY `idx_sae_school_year`
        (`school_year_id`),

    KEY `idx_sae_student_school_year`
        (`student_id`, `school_year_id`),

    KEY `idx_sae_student_semester`
        (`student_id`, `school_year_id`, `semester`),

    KEY `idx_sae_program`
        (`program_id`),

    KEY `idx_sae_section`
        (`section_id`),

    KEY `idx_sae_status`
        (`status`),

    KEY `idx_sae_effective_dates`
        (`effective_start`, `effective_end`),

    /*
     * Allows student_class_enrollments to enforce that
     * enrollment_id and student_id belong together.
     *
     * This does NOT prevent multiple historical enrollments
     * for the same Student.
     */
    UNIQUE KEY `uq_sae_enrollment_student`
        (`student_academic_enrollment_id`, `student_id`),

    /*
     * Historical lifecycle protection.
     */
    CONSTRAINT `chk_sae_effective_dates`
        CHECK (
            `effective_end` IS NULL
            OR `effective_end` >= `effective_start`
        ),

    /*
     * Student identity.
     */
    CONSTRAINT `fk_sae_student`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`student_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    /*
     * Authoritative School Year.
     */
    CONSTRAINT `fk_sae_school_year`
        FOREIGN KEY (`school_year_id`)
        REFERENCES `school_years` (`school_year_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    /*
     * Existing persistent Program.
     */
    CONSTRAINT `fk_sae_program`
        FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`program_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    /*
     * Existing persistent Section.
     */
    CONSTRAINT `fk_sae_section`
        FOREIGN KEY (`section_id`)
        REFERENCES `sections` (`section_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


/* ============================================================
   C. CREATE TABLE — student_class_enrollments
   ============================================================ */

CREATE TABLE IF NOT EXISTS `student_class_enrollments` (
    `student_class_enrollment_id`
        INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,

    /*
     * Permanent Student identity.
     */
    `student_id`
        INT(10) UNSIGNED NOT NULL,

    /*
     * Historical academic placement under which
     * this class participation occurred.
     */
    `enrollment_id`
        INT(10) UNSIGNED NOT NULL,

    /*
     * Existing Schedule/Operational Class anchor.
     *
     * No Schedule table is modified.
     */
    `operational_class_id`
        INT(10) UNSIGNED NOT NULL,

    `status`
        ENUM(
            'Active',
            'Completed',
            'Dropped',
            'Withdrawn',
            'Archived'
        ) NOT NULL DEFAULT 'Active',

    `enrolled_at`
        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `ended_at`
        DATETIME DEFAULT NULL,

    `created_at`
        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `updated_at`
        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`student_class_enrollment_id`),

    /*
     * CORE DUPLICATE-PARTICIPATION PROTECTION
     *
     * Same Student + same Operational Class
     * can exist only once.
     *
     * This deliberately does NOT include subject_id.
     */
    UNIQUE KEY `uq_sce_student_operational_class`
        (`student_id`, `operational_class_id`),

    KEY `idx_sce_enrollment`
        (`enrollment_id`),

    KEY `idx_sce_operational_class`
        (`operational_class_id`),

    KEY `idx_sce_status`
        (`status`),

    KEY `idx_sce_student`
        (`student_id`),

    KEY `idx_sce_student_enrollment`
        (`student_id`, `enrollment_id`),

    /*
     * Student identity.
     */
    CONSTRAINT `fk_sce_student`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`student_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    /*
     * Historical academic placement.
     *
     * The composite relationship prevents an enrollment
     * belonging to one Student from being attached to
     * another Student's class-participation record.
     */
    CONSTRAINT `fk_sce_enrollment_student`
        FOREIGN KEY (
            `enrollment_id`,
            `student_id`
        )
        REFERENCES `student_academic_enrollments` (
            `student_academic_enrollment_id`,
            `student_id`
        )
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    /*
     * Existing operational teaching instance.
     *
     * Schedule architecture remains untouched.
     */
    CONSTRAINT `fk_sce_operational_class`
        FOREIGN KEY (`operational_class_id`)
        REFERENCES `operational_classes` (`operational_class_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    /*
     * Participation lifecycle protection.
     */
    CONSTRAINT `chk_sce_ended_at`
        CHECK (
            `ended_at` IS NULL
            OR `ended_at` >= `enrolled_at`
        )
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


/* ============================================================
   D. FOREIGN KEYS
   ============================================================

   All foreign keys are defined inline above.

   Relationship:

   students
       |
       | 1 : many
       v
   student_academic_enrollments
       |
       | 1 : many
       v
   student_class_enrollments
       |
       | many : 1
       v
   operational_classes
       |
       v
   class_schedules

   Academic Period is intentionally NOT referenced by
   student_academic_enrollments.
*/


/* ============================================================
   E. UNIQUENESS CONSTRAINTS
   ============================================================

   students:
       UNIQUE(student_number)

   student_academic_enrollments:
       NO broad Student/School-Year/Semester UNIQUE constraint.

       The composite enrollment_id + student_id key exists
       only to enforce referential consistency from
       student_class_enrollments.

   student_class_enrollments:
       UNIQUE(student_id, operational_class_id)

   Therefore:

       Student 123 + Operational Class 50
           = one participation record

       Student 123 + Operational Class 90
           = valid separate participation

   Repeated Subjects remain supported because uniqueness
   does not use subject_id.
*/


/* ============================================================
   F. LIFECYCLE / CHECK CONSTRAINTS
   ============================================================

   Academic Enrollment:

       effective_start <= effective_end

       OR

       effective_end IS NULL

   Class Participation:

       enrolled_at <= ended_at

       OR

       ended_at IS NULL

   Placement completeness is intentionally NOT enforced
   by a CHECK constraint because Review/Incomplete records
   may legitimately contain NULL Program/Section/Year Level.
*/


/* ============================================================
   G. ROLLBACK / DROP STATEMENTS
   ============================================================

   WARNING:
   These statements permanently remove the three new tables
   and their data.

   Execute ONLY if rollback is intentionally required.

   Drop child table first because it references the
   academic enrollment table.
*/


-- DROP TABLE IF EXISTS `student_class_enrollments`;
-- DROP TABLE IF EXISTS `student_academic_enrollments`;
-- DROP TABLE IF EXISTS `students`;