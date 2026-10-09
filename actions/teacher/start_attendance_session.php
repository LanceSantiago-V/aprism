<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/attendance_helper.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../../auth/session_guard.php';

function startAttendanceRedirect(
    int $classId,
    ?int $scheduleId = null,
    ?string $error = null,
    ?string $success = null,
    bool $openScanner = false
): never {
    if ($error !== null) {
        $_SESSION['error_message'] = $error;
    }

    if ($success !== null) {
        $_SESSION['success_message'] = $success;
    }

    $query = 'operational_class_id=' . $classId;

    if ($scheduleId !== null) {
        $query .= '&class_schedule_id=' . $scheduleId;
    }

    $destination = $openScanner
        ? '/dashboard/teacher_attendance_scanner.php?'
        : '/dashboard/teacher_attendance.php?';

    header('Location: ' . APP_URL . $destination . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    startAttendanceRedirect(0, null, 'Invalid Attendance Session request.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    startAttendanceRedirect(0, null, 'The request could not be verified. Refresh the page and try again.');
}

$operationalClassId = filter_var(
    $_POST['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$classScheduleId = filter_var(
    $_POST['class_schedule_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($operationalClassId === false || $classScheduleId === false) {
    startAttendanceRedirect(0, null, 'A valid class and schedule are required.');
}

$operationalClassId = (int) $operationalClassId;
$classScheduleId = (int) $classScheduleId;
$meetingDate = date('Y-m-d');
$todayDay = date('l');

try {
    $pdo->beginTransaction();

    $scheduleStmt = $pdo->prepare("
        SELECT
            cs.class_schedule_id,
            cs.operational_class_id,
            cs.day,
            cs.start_time,
            cs.end_time,
            cs.room,
            oc.teacher_id,
            oc.status AS class_status,
            active_sy.status AS school_year_status
        FROM class_schedules AS cs
        INNER JOIN operational_classes AS oc
            ON oc.operational_class_id = cs.operational_class_id
        INNER JOIN school_years AS active_sy
            ON active_sy.school_year = oc.school_year
           AND active_sy.status = 'Active'
        WHERE cs.class_schedule_id = ?
          AND cs.operational_class_id = ?
          AND cs.status = 'Active'
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
        FOR UPDATE
    ");
    $scheduleStmt->execute([
        $classScheduleId,
        $operationalClassId,
        (int) $_SESSION['user_id'],
    ]);
    $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($schedule === null) {
        throw new RuntimeException('The selected class schedule is unavailable or you do not have permission to use it.');
    }

    if ((string) $schedule['day'] !== $todayDay) {
        throw new RuntimeException('Attendance can only be started for a scheduled meeting today.');
    }

    $attendancePolicy = aprismAttendancePolicy($pdo);
    $scheduledStart = aprismAttendanceScheduledStart(
        $meetingDate,
        (string) $schedule['start_time']
    );
    $earliestStart = $scheduledStart->modify(
        '-' . $attendancePolicy['pre_start_minutes'] . ' minutes'
    );

    if (new DateTimeImmutable('now') < $earliestStart) {
        throw new RuntimeException(
            'Attendance can be started within the configured pre-start allowance or once the class begins.'
        );
    }

    $duplicateStmt = $pdo->prepare("
        SELECT attendance_session_id
        FROM attendance_sessions
        WHERE class_schedule_id = ?
          AND meeting_date = ?
        LIMIT 1
        FOR UPDATE
    ");
    $duplicateStmt->execute([$classScheduleId, $meetingDate]);

    if ($duplicateStmt->fetchColumn() !== false) {
        throw new RuntimeException('An Attendance Session already exists for this meeting today.');
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO attendance_sessions (
            operational_class_id,
            class_schedule_id,
            meeting_date,
            scheduled_day,
            scheduled_start_time,
            scheduled_end_time,
            scheduled_room,
            status,
            opened_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Open', NOW())
    ");
    $insertStmt->execute([
        $operationalClassId,
        $classScheduleId,
        $meetingDate,
        $schedule['day'],
        $schedule['start_time'],
        $schedule['end_time'],
        $schedule['room'] !== '' ? $schedule['room'] : null,
    ]);

    logAudit(
        $pdo,
        'Start Attendance Session',
        'Started Attendance Session for Operational Class #'
        . $operationalClassId
        . ' using Class Schedule #'
        . $classScheduleId
        . ' on '
        . $meetingDate
    );

    $pdo->commit();

    startAttendanceRedirect(
        $operationalClassId,
        $classScheduleId,
        null,
        'Attendance Session started successfully.',
        true
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[APRISM Start Attendance Session] ' . $exception->getMessage());
    startAttendanceRedirect(
        $operationalClassId,
        $classScheduleId,
        $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'The Attendance Session could not be started.'
    );
}
