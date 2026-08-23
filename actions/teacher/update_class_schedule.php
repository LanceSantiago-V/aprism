<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

$allowedRoles = [
    ROLE_TEACHER,
];

require_once __DIR__ . '/../../auth/session_guard.php';


function scheduleEditJsonResponse(
    bool $success,
    string $message,
    int $status = 200,
    string $code = 'OK',
    array $data = []
): never {
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ],
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


function scheduleEditNormalizeTime(
    string $value,
    string $fieldName
): string {
    $time = DateTime::createFromFormat('!H:i', $value);

    $errors = DateTime::getLastErrors();

    if (
        $time === false ||
        (
            is_array($errors) &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )
        )
    ) {
        throw new RuntimeException(
            'The ' . $fieldName . ' format is invalid.'
        );
    }

    return $time->format('H:i:s');
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    scheduleEditJsonResponse(
        false,
        'Invalid Class Schedule update request.',
        405,
        'METHOD_NOT_ALLOWED'
    );
}


if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    scheduleEditJsonResponse(
        false,
        'The request could not be verified. Refresh the page and try again.',
        419,
        'CSRF_VALIDATION_FAILED'
    );
}


$classScheduleId = filter_var(
    $_POST['class_schedule_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($classScheduleId === false) {
    scheduleEditJsonResponse(
        false,
        'A valid Class Schedule is required.',
        422,
        'INVALID_CLASS_SCHEDULE'
    );
}


$dayMap = [
    'monday' => 'Monday',
    'tuesday' => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday',
    'saturday' => 'Saturday',
];

$dayInput = trim((string) ($_POST['day'] ?? ''));
$normalizedDay = $dayMap[strtolower($dayInput)] ?? null;

if ($normalizedDay === null) {
    scheduleEditJsonResponse(
        false,
        'The selected Day is invalid.',
        422,
        'INVALID_DAY'
    );
}


$room = trim((string) ($_POST['room'] ?? ''));

if (strlen($room) > 100) {
    scheduleEditJsonResponse(
        false,
        'Room must not exceed 100 characters.',
        422,
        'INVALID_ROOM'
    );
}


try {
    $normalizedStartTime = scheduleEditNormalizeTime(
        trim((string) ($_POST['start_time'] ?? '')),
        'Start Time'
    );

    $normalizedEndTime = scheduleEditNormalizeTime(
        trim((string) ($_POST['end_time'] ?? '')),
        'End Time'
    );
} catch (RuntimeException $e) {
    scheduleEditJsonResponse(
        false,
        $e->getMessage(),
        422,
        'INVALID_TIME'
    );
}


if ($normalizedEndTime <= $normalizedStartTime) {
    scheduleEditJsonResponse(
        false,
        'End Time must be later than Start Time.',
        422,
        'INVALID_TIME_RANGE'
    );
}


try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Resolve the Existing Schedule Under Teacher Ownership
    |--------------------------------------------------------------------------
    |
    | The browser provides only class_schedule_id. The authoritative record,
    | including its Operational Class, School Year, Semester, and Section, is
    | always reloaded from the database under the authenticated Teacher.
    |
    */

    $scheduleStmt = $pdo->prepare("
        SELECT
            cs.class_schedule_id,
            cs.operational_class_id,
            oc.section_id,
            oc.school_year,
            oc.semester,
            sub.subject_name,
            sec.section_name
        FROM class_schedules AS cs
        INNER JOIN operational_classes AS oc
            ON oc.operational_class_id = cs.operational_class_id
        INNER JOIN school_years AS active_sy
            ON active_sy.school_year = oc.school_year
           AND active_sy.status = 'Active'
        INNER JOIN subjects AS sub
            ON sub.subject_id = oc.subject_id
        INNER JOIN sections AS sec
            ON sec.section_id = oc.section_id
        WHERE cs.class_schedule_id = ?
          AND cs.status = 'Active'
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
        FOR UPDATE
    ");

    $scheduleStmt->execute([
        (int) $classScheduleId,
        (int) $_SESSION['user_id'],
    ]);

    $existingSchedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($existingSchedule === null) {
        $pdo->rollBack();

        scheduleEditJsonResponse(
            false,
            'The selected Class Schedule is unavailable or you do not have permission to edit it.',
            403,
            'SCHEDULE_ACCESS_DENIED'
        );
    }

    $operationalClassId = (int) $existingSchedule['operational_class_id'];
    $sectionId = (int) $existingSchedule['section_id'];

    /*
    |--------------------------------------------------------------------------
    | Exact Duplicate Schedule
    |--------------------------------------------------------------------------
    |
    | Preserve the current NULL-safe Room rule. The schedule being edited is
    | explicitly excluded so unchanged values remain valid.
    |
    */

    $duplicateStmt = $pdo->prepare("
        SELECT cs.class_schedule_id
        FROM class_schedules AS cs
        WHERE cs.operational_class_id = ?
          AND cs.day = ?
          AND cs.start_time = ?
          AND cs.end_time = ?
          AND (cs.room <=> ?)
          AND cs.status = 'Active'
          AND cs.class_schedule_id <> ?
        LIMIT 1
        FOR UPDATE
    ");

    $duplicateStmt->execute([
        $operationalClassId,
        $normalizedDay,
        $normalizedStartTime,
        $normalizedEndTime,
        $room !== '' ? $room : null,
        (int) $classScheduleId,
    ]);

    if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            'This exact class schedule already exists in your My Classes for the current School Year and Semester.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Teacher Schedule Conflict
    |--------------------------------------------------------------------------
    */

    $teacherConflictStmt = $pdo->prepare("
        SELECT cs.class_schedule_id
        FROM operational_classes AS oc
        INNER JOIN class_schedules AS cs
            ON cs.operational_class_id = oc.operational_class_id
        WHERE oc.teacher_id = ?
          AND oc.school_year = ?
          AND oc.semester = ?
          AND oc.status = 'Active'
          AND cs.day = ?
          AND cs.status = 'Active'
          AND cs.start_time < ?
          AND cs.end_time > ?
          AND cs.class_schedule_id <> ?
        LIMIT 1
        FOR UPDATE
    ");

    $teacherConflictStmt->execute([
        (int) $_SESSION['user_id'],
        $existingSchedule['school_year'],
        $existingSchedule['semester'],
        $normalizedDay,
        $normalizedEndTime,
        $normalizedStartTime,
        (int) $classScheduleId,
    ]);

    if ($teacherConflictStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            'This schedule conflicts with another active class assigned to you.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Section Schedule Conflict
    |--------------------------------------------------------------------------
    */

    $sectionConflictStmt = $pdo->prepare("
        SELECT cs.class_schedule_id
        FROM operational_classes AS oc
        INNER JOIN class_schedules AS cs
            ON cs.operational_class_id = oc.operational_class_id
        WHERE oc.section_id = ?
          AND oc.school_year = ?
          AND oc.semester = ?
          AND oc.status = 'Active'
          AND cs.day = ?
          AND cs.status = 'Active'
          AND cs.start_time < ?
          AND cs.end_time > ?
          AND cs.class_schedule_id <> ?
        LIMIT 1
        FOR UPDATE
    ");

    $sectionConflictStmt->execute([
        $sectionId,
        $existingSchedule['school_year'],
        $existingSchedule['semester'],
        $normalizedDay,
        $normalizedEndTime,
        $normalizedStartTime,
        (int) $classScheduleId,
    ]);

    if ($sectionConflictStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            'This schedule conflicts with another active class assigned to the selected Section.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Room Schedule Conflict
    |--------------------------------------------------------------------------
    */

    if ($room !== '') {
        $roomConflictStmt = $pdo->prepare("
            SELECT cs.class_schedule_id
            FROM class_schedules AS cs
            INNER JOIN operational_classes AS oc
                ON oc.operational_class_id = cs.operational_class_id
            WHERE oc.school_year = ?
              AND oc.semester = ?
              AND oc.status = 'Active'
              AND cs.day = ?
              AND cs.status = 'Active'
              AND TRIM(cs.room) = TRIM(?)
              AND cs.start_time < ?
              AND cs.end_time > ?
              AND cs.class_schedule_id <> ?
            LIMIT 1
            FOR UPDATE
        ");

        $roomConflictStmt->execute([
            $existingSchedule['school_year'],
            $existingSchedule['semester'],
            $normalizedDay,
            $room,
            $normalizedEndTime,
            $normalizedStartTime,
            (int) $classScheduleId,
        ]);

        if ($roomConflictStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'The selected room is already occupied during this time.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Only the Existing Class Schedule Record
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE class_schedules
        SET
            day = ?,
            start_time = ?,
            end_time = ?,
            room = ?
        WHERE class_schedule_id = ?
          AND status = 'Active'
    ");

    $updateStmt->execute([
        $normalizedDay,
        $normalizedStartTime,
        $normalizedEndTime,
        $room !== '' ? $room : null,
        (int) $classScheduleId,
    ]);

    logAudit(
        $pdo,
        'Update Class Schedule',
        'Updated schedule for Subject "' .
        $existingSchedule['subject_name'] .
        '" and Section "' .
        $existingSchedule['section_name'] .
        '".'
    );

    $pdo->commit();

    scheduleEditJsonResponse(
        true,
        'Class Schedule updated successfully.',
        200,
        'SCHEDULE_UPDATED',
        [
            'class_schedule_id' => (int) $classScheduleId,
            'operational_class_id' => $operationalClassId,
            'day' => $normalizedDay,
            'start_time' => $normalizedStartTime,
            'end_time' => $normalizedEndTime,
            'room' => $room !== '' ? $room : null,
        ]
    );
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    scheduleEditJsonResponse(
        false,
        $e->getMessage(),
        422,
        'SCHEDULE_VALIDATION_FAILED'
    );
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[APRISM Update Class Schedule PDO] ' .
        $e->getMessage()
    );

    scheduleEditJsonResponse(
        false,
        'The Class Schedule could not be updated because a database operation failed. No changes were saved.',
        500,
        'DATABASE_ERROR'
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[APRISM Update Class Schedule] ' .
        $e->getMessage()
    );

    scheduleEditJsonResponse(
        false,
        'The Class Schedule could not be updated. No changes were saved.',
        500,
        'UPDATE_FAILED'
    );
}