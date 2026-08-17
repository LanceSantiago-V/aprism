CREATE TABLE import_history (

    import_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    imported_by INT UNSIGNED NOT NULL,

    import_type ENUM(
        'Programs',
        'Sections',
        'Subjects',
        'Students',
        'Schedules'
    ) NOT NULL,

    original_filename VARCHAR(255) NOT NULL,

    total_rows INT UNSIGNED NOT NULL DEFAULT 0,

    inserted_rows INT UNSIGNED NOT NULL DEFAULT 0,

    updated_rows INT UNSIGNED NOT NULL DEFAULT 0,

    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,

    failed_rows INT UNSIGNED NOT NULL DEFAULT 0,

    status ENUM(
        'Success',
        'Partial',
        'Failed'
    ) NOT NULL,

    imported_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_import_history_user
        FOREIGN KEY (imported_by)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;