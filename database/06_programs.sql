CREATE TABLE programs (

    program_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    program_code VARCHAR(20) NOT NULL,

    program_name VARCHAR(150) NOT NULL,

    status ENUM (
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_program_code
        UNIQUE (program_code),

    CONSTRAINT uq_program_name
        UNIQUE (program_name)

)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;