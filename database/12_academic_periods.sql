CREATE TABLE academic_periods (

    academic_period_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    school_year_id INT UNSIGNED NOT NULL,

    academic_level ENUM(
        'College',
        'Senior High School'
    ) NOT NULL,

    period_name VARCHAR(50) NOT NULL,

    start_date DATE NOT NULL,

    end_date DATE NOT NULL,

    is_archived TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_academic_period
        UNIQUE (
            school_year_id,
            academic_level,
            period_name
        ),

    CONSTRAINT fk_academic_periods_school_year
        FOREIGN KEY (school_year_id)
        REFERENCES school_years(school_year_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_academic_periods_school_year (
        school_year_id
    ),

    INDEX idx_academ ic_periods_level (
        academic_level
    ),

    INDEX idx_academic_periods_dates (
        start_date,
        end_date
    ),

    INDEX idx_academic_periods_archived (
        is_archived
    )

)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;