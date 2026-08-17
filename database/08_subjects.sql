CREATE TABLE subjects (

    subject_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    subject_code VARCHAR(20) NOT NULL,

    subject_name VARCHAR(150) NOT NULL,

    units DECIMAL(3,1) NOT NULL,

    status ENUM(
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_subject_code
        UNIQUE(subject_code),

    CONSTRAINT uq_subject_name
        UNIQUE(subject_name)

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;