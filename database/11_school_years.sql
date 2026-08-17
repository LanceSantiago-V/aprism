CREATE TABLE school_years (

    school_year_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    school_year VARCHAR(9) NOT NULL,

    start_date DATE NOT NULL,

    end_date DATE NOT NULL,

    status ENUM(
        'Active',
        'Inactive',
        'Archived'
    ) NOT NULL DEFAULT 'Inactive',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_school_year
        UNIQUE (school_year),

    INDEX idx_school_years_status (
        status
    ),

    INDEX idx_school_years_dates (
        start_date,
        end_date
    )

)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;