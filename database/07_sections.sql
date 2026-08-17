CREATE TABLE sections (

    section_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    program_id INT UNSIGNED NOT NULL,

    section_name VARCHAR(50) NOT NULL,

    year_level ENUM(
        '1',
        '2',
        '3',
        '4'
    ) NOT NULL,

    status ENUM(
        'Active',
        'Inactive'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_program_section
        UNIQUE (
            program_id,
            section_name
        ),

    CONSTRAINT fk_sections_program
        FOREIGN KEY (program_id)
        REFERENCES programs(program_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;