<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TEACHER
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';


/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function redirectWithMessage(
    string $type,
    string $message
): never {

    $_SESSION[$type . '_message'] = $message;

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/teacher_my_class.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    redirectWithMessage(
        'error',
        'Invalid request.'
    );

}


/*
|--------------------------------------------------------------------------
| Authenticated Teacher
|--------------------------------------------------------------------------
*/

$teacherId = (int) ($_SESSION['user_id'] ?? 0);

if ($teacherId <= 0) {

    redirectWithMessage(
        'error',
        'Your session has expired. Please log in again.'
    );

}


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
|
| The frontend intentionally submits institutional identifying values
| rather than database primary keys.
|
| The server resolves those values against the existing reference
| records and never creates Subject or Section records.
|--------------------------------------------------------------------------
*/

$subjectCode = trim(
    (string) ($_POST['subject_code'] ?? '')
);

$sectionName = trim(
    (string) ($_POST['section'] ?? '')
);

$room = trim(
    (string) ($_POST['room'] ?? '')
);

$day = trim(
    (string) ($_POST['day'] ?? '')
);

$startTime = trim(
    (string) ($_POST['start_time'] ?? '')
);

$endTime = trim(
    (string) ($_POST['end_time'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Required Fields
|--------------------------------------------------------------------------
*/

if (
    $subjectCode === '' ||
    $sectionName === '' ||
    $day === '' ||
    $startTime === '' ||
    $endTime === ''
) {

    redirectWithMessage(
        'error',
        'Subject code, section, day, start time, and end time are required.'
    );

}


/*
|--------------------------------------------------------------------------
| Normalize Day
|--------------------------------------------------------------------------
*/

$dayMap = [

    'monday' =>
        'Monday',

    'tuesday' =>
        'Tuesday',

    'wednesday' =>
        'Wednesday',

    'thursday' =>
        'Thursday',

    'friday' =>
        'Friday',

    'saturday' =>
        'Saturday'

];

$normalizedDay =
    $dayMap[strtolower($day)]
    ?? null;

if ($normalizedDay === null) {

    redirectWithMessage(
        'error',
        'The selected class day is invalid.'
    );

}


/*
|--------------------------------------------------------------------------
| Validate Time Format
|--------------------------------------------------------------------------
*/

$startDateTime = DateTime::createFromFormat(
    'H:i',
    $startTime
);

$endDateTime = DateTime::createFromFormat(
    'H:i',
    $endTime
);

$startErrors =
    DateTime::getLastErrors();

$endErrors =
    DateTime::getLastErrors();

if (
    $startDateTime === false ||
    $endDateTime === false ||
    (
        is_array($startErrors) &&
        (
            $startErrors['warning_count'] > 0 ||
            $startErrors['error_count'] > 0
        )
    ) ||
    (
        is_array($endErrors) &&
        (
            $endErrors['warning_count'] > 0 ||
            $endErrors['error_count'] > 0
        )
    )
) {

    redirectWithMessage(
        'error',
        'The class time format is invalid.'
    );

}

if (
    $endDateTime->format('H:i') <=
    $startDateTime->format('H:i')
) {

    redirectWithMessage(
        'error',
        'End time must be later than start time.'
    );

}


/*
|--------------------------------------------------------------------------
| Database Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Active School Year
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            school_year_id,
            school_year,
            start_date,
            end_date
        FROM school_years
        WHERE status = 'Active'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute();

    $schoolYear =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schoolYear) {

        throw new RuntimeException(
            'No active School Year is available.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Current Academic Context
    |--------------------------------------------------------------------------
    |
    | Find the non-archived academic period that contains today.
    |
    | This allows APRISM to derive the current semester from the
    | Academic Setup foundation instead of asking the Teacher to
    | manually invent or select a semester.
    |--------------------------------------------------------------------------
    */

    $today =
        date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT
            academic_period_id,
            academic_level,
            semester,
            period_name,
            start_date,
            end_date
        FROM academic_periods
        WHERE school_year_id = ?
          AND is_archived = 0
          AND start_date <= ?
          AND end_date >= ?
        ORDER BY
            start_date ASC
        LIMIT 1
    ");

    $stmt->execute([
        (int) $schoolYear['school_year_id'],
        $today,
        $today
    ]);

    $academicPeriod =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$academicPeriod) {

        throw new RuntimeException(
            'No active Academic Period covers the current date.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Semester Requirement
    |--------------------------------------------------------------------------
    */

    $semester =
        trim(
            (string) (
                $academicPeriod['semester']
                ?? ''
            )
        );

    if ($semester === '') {

        throw new RuntimeException(
            'The current Academic Period does not define a semester.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Subject
    |--------------------------------------------------------------------------
    |
    | Subject records must already exist.
    | APRISM does not create them here.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            subject_id,
            subject_code,
            subject_name,
            status
        FROM subjects
        WHERE subject_code = ?
          AND status = 'Active'
        LIMIT 1
    ");

    $stmt->execute([
        $subjectCode
    ]);

    $subject =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {

        throw new RuntimeException(
            'The specified Subject Code does not match an active institutional Subject record.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Section
    |--------------------------------------------------------------------------
    |
    | Section records must already exist.
    | APRISM does not create them here.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            section_id,
            section_name,
            program_id,
            year_level,
            status
        FROM sections
        WHERE section_name = ?
          AND status = 'Active'
        LIMIT 1
    ");

    $stmt->execute([
        $sectionName
    ]);

    $section =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {

        throw new RuntimeException(
            'The specified Section does not match an active institutional Section record.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Verify Teacher Account
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            user_id
        FROM users
        WHERE user_id = ?
          AND account_status = 'Active'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $teacherId
    ]);

    $teacher =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {

        throw new RuntimeException(
            'The authenticated Teacher account is no longer active.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Operational Class
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            operational_class_id
        FROM operational_classes
        WHERE teacher_id = ?
          AND subject_id = ?
          AND section_id = ?
          AND school_year = ?
          AND semester = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([

        $teacherId,

        (int) $subject['subject_id'],

        (int) $section['section_id'],

        $schoolYear['school_year'],

        $semester

    ]);

    $existingClass =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingClass) {

        throw new RuntimeException(
            'This operational class already exists for the current School Year and Semester.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Teacher Schedule Conflict
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            oc.operational_class_id
        FROM operational_classes AS oc

        INNER JOIN class_schedules AS cs
            ON cs.operational_class_id =
                oc.operational_class_id

        WHERE oc.teacher_id = ?
          AND oc.school_year = ?
          AND oc.semester = ?
          AND oc.status = 'Active'

          AND cs.day = ?
          AND cs.status = 'Active'

          AND cs.start_time < ?
          AND cs.end_time > ?

        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([

        $teacherId,

        $schoolYear['school_year'],

        $semester,

        $normalizedDay,

        $endTime,

        $startTime

    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {

        throw new RuntimeException(
            'The Teacher already has another active class scheduled during this time.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Section Schedule Conflict
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            oc.operational_class_id
        FROM operational_classes AS oc

        INNER JOIN class_schedules AS cs
            ON cs.operational_class_id =
                oc.operational_class_id

        WHERE oc.section_id = ?
          AND oc.school_year = ?
          AND oc.semester = ?
          AND oc.status = 'Active'

          AND cs.day = ?
          AND cs.status = 'Active'

          AND cs.start_time < ?
          AND cs.end_time > ?

        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([

        (int) $section['section_id'],

        $schoolYear['school_year'],

        $semester,

        $normalizedDay,

        $endTime,

        $startTime

    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {

        throw new RuntimeException(
            'The selected Section already has another active class scheduled during this time.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Room Conflict
    |--------------------------------------------------------------------------
    */

    if ($room !== '') {

        $stmt = $pdo->prepare("
            SELECT
                cs.class_schedule_id
            FROM class_schedules AS cs

            INNER JOIN operational_classes AS oc
                ON oc.operational_class_id =
                    cs.operational_class_id

            WHERE oc.school_year = ?
              AND oc.semester = ?
              AND oc.status = 'Active'

              AND cs.day = ?
              AND cs.status = 'Active'

              AND cs.room = ?

              AND cs.start_time < ?
              AND cs.end_time > ?

            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([

            $schoolYear['school_year'],

            $semester,

            $normalizedDay,

            $room,

            $endTime,

            $startTime

        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {

            throw new RuntimeException(
                'The selected room is already occupied during this time.'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Create Operational Class
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO operational_classes (
            teacher_id,
            subject_id,
            section_id,
            school_year,
            semester,
            status
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'Active'
        )
    ");

    $stmt->execute([

        $teacherId,

        (int) $subject['subject_id'],

        (int) $section['section_id'],

        $schoolYear['school_year'],

        $semester

    ]);

    $operationalClassId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Create Class Schedule
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO class_schedules (
            operational_class_id,
            day,
            start_time,
            end_time,
            room,
            status
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'Active'
        )
    ");

    $stmt->execute([

        $operationalClassId,

        $normalizedDay,

        $startTime,

        $endTime,

        $room !== ''
        ? $room
        : null

    ]);


    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'Create Operational Class',
        'Created operational class for subject "' .
        $subject['subject_code'] .
        '" and section "' .
        $section['section_name'] .
        '".'
    );


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    redirectWithMessage(
        'success',
        'Operational class created successfully.'
    );


} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    redirectWithMessage(
        'error',
        $e->getMessage()
    );


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    error_log(
        '[APRISM Create Operational Class] ' .
        $e->getMessage()
    );

    redirectWithMessage(
        'error',
        'Unable to create the operational class at this time.'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    error_log(
        '[APRISM Create Operational Class] ' .
        $e->getMessage()
    );

    redirectWithMessage(
        'error',
        'Unable to create the operational class at this time.'
    );

}