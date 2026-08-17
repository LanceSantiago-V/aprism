CREATE TABLE operational_classes (

    operational_class_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    teacher_id INT NOT NULL,

    subject_id INT UNSIGNED NOT NULL,

    section_id INT UNSIGNED NOT NULL,

    school_year VARCHAR(9) NOT NULL,

    semester VARCHAR(30) NOT NULL,

    status ENUM(
        'Active',
        'Archived'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_operational_class
        UNIQUE (
            teacher_id,
            subject_id,
            section_id,
            school_year,
            semester
        ),

    CONSTRAINT fk_operational_classes_teacher
        FOREIGN KEY (teacher_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_operational_classes_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_operational_classes_section
        FOREIGN KEY (section_id)
        REFERENCES sections(section_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_operational_classes_teacher (
        teacher_id
    ),

    INDEX idx_operational_classes_subject (
        subject_id
    ),

    INDEX idx_operational_classes_section (
        section_id
    ),

    INDEX idx_operational_classes_period_status (
        school_year,
        semester,
        status
    )

)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;



CREATE TABLE class_schedules (

    class_schedule_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    operational_class_id INT UNSIGNED NOT NULL,

    day ENUM(
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday'
    ) NOT NULL,

    start_time TIME NOT NULL,

    end_time TIME NOT NULL,

    room VARCHAR(100) NULL,

    status ENUM(
        'Active',
        'Archived'
    ) NOT NULL DEFAULT 'Active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_class_schedules_operational_class
        FOREIGN KEY (operational_class_id)
        REFERENCES operational_classes(operational_class_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT uq_class_schedule
        UNIQUE (
            operational_class_id,
            day,
            start_time,
            end_time,
            room
        ),

    INDEX idx_class_schedules_operational_class (
        operational_class_id
    ),

    INDEX idx_class_schedules_day_time (
        day,
        start_time,
        end_time
    ),

    INDEX idx_class_schedules_status (
        status
    )

)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;