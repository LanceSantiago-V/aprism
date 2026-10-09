/*
 * Grade Import foundation.
 *
 * Assessment structure is scoped to an Operational Class and Academic Period.
 * Only raw component scores are persisted; calculated workbook outputs remain
 * source material for review and are not imported as raw scores.
 */
CREATE TABLE assessment_categories (
    assessment_category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operational_class_id INT UNSIGNED NOT NULL,
    academic_period_id INT UNSIGNED NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    category_code VARCHAR(50) NULL,
    weight_percent DECIMAL(5,2) NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    source_label VARCHAR(150) NULL,
    status ENUM('Active', 'Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_assessment_category_class_period_name
        UNIQUE (operational_class_id, academic_period_id, category_name),
    CONSTRAINT fk_assessment_categories_class
        FOREIGN KEY (operational_class_id)
        REFERENCES operational_classes(operational_class_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assessment_categories_period
        FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(academic_period_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_assessment_categories_class_period (operational_class_id, academic_period_id),
    INDEX idx_assessment_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assessment_components (
    assessment_component_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assessment_category_id INT UNSIGNED NOT NULL,
    component_code VARCHAR(50) NOT NULL,
    component_name VARCHAR(150) NOT NULL,
    max_score DECIMAL(10,2) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    source_label VARCHAR(150) NULL,
    status ENUM('Active', 'Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_assessment_component_category_code
        UNIQUE (assessment_category_id, component_code),
    CONSTRAINT fk_assessment_components_category
        FOREIGN KEY (assessment_category_id)
        REFERENCES assessment_categories(assessment_category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_assessment_components_category_status (assessment_category_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grade_import_batches (
    grade_import_batch_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    operational_class_id INT UNSIGNED NOT NULL,
    academic_period_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    template_type ENUM('SHS', 'College/Tertiary') NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    status ENUM('Review', 'Validated', 'Confirmed', 'Failed') NOT NULL DEFAULT 'Review',
    reviewed_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_grade_import_batches_teacher
        FOREIGN KEY (teacher_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_grade_import_batches_class
        FOREIGN KEY (operational_class_id) REFERENCES operational_classes(operational_class_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_grade_import_batches_period
        FOREIGN KEY (academic_period_id) REFERENCES academic_periods(academic_period_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_grade_import_batches_context (operational_class_id, academic_period_id),
    INDEX idx_grade_import_batches_teacher_status (teacher_id, status),
    INDEX idx_grade_import_batches_source (source_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_assessment_scores (
    student_assessment_score_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_class_enrollment_id INT UNSIGNED NOT NULL,
    assessment_component_id INT UNSIGNED NOT NULL,
    raw_score DECIMAL(10,2) NOT NULL,
    originating_grade_import_batch_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_student_assessment_component
        UNIQUE (student_class_enrollment_id, assessment_component_id),
    CONSTRAINT fk_student_assessment_scores_sce
        FOREIGN KEY (student_class_enrollment_id)
        REFERENCES student_class_enrollments(student_class_enrollment_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_student_assessment_scores_component
        FOREIGN KEY (assessment_component_id)
        REFERENCES assessment_components(assessment_component_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_student_assessment_scores_batch
        FOREIGN KEY (originating_grade_import_batch_id)
        REFERENCES grade_import_batches(grade_import_batch_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_student_assessment_scores_sce (student_class_enrollment_id),
    INDEX idx_student_assessment_scores_component (assessment_component_id),
    INDEX idx_student_assessment_scores_batch (originating_grade_import_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_assessment_score_revisions (
    student_assessment_score_revision_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_assessment_score_id INT UNSIGNED NOT NULL,
    grade_import_batch_id INT UNSIGNED NOT NULL,
    acted_by INT NOT NULL,
    previous_raw_score DECIMAL(10,2) NULL,
    new_raw_score DECIMAL(10,2) NOT NULL,
    revision_type ENUM('Insert', 'Update') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_score_revisions_score
        FOREIGN KEY (student_assessment_score_id)
        REFERENCES student_assessment_scores(student_assessment_score_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_score_revisions_batch
        FOREIGN KEY (grade_import_batch_id)
        REFERENCES grade_import_batches(grade_import_batch_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_score_revisions_actor
        FOREIGN KEY (acted_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_score_revisions_score (student_assessment_score_id),
    INDEX idx_score_revisions_batch (grade_import_batch_id),
    INDEX idx_score_revisions_actor (acted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
