<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/attendance_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../../auth/session_guard.php';

function closeAttendanceRedirect(int $classId, ?string $error = null, ?string $success = null): never
{
    if ($error !== null) {
        $_SESSION['error_message'] = $error;
    }

    if ($success !== null) {
        $_SESSION['success_message'] = $success;
    }

    header(
        'Location: ' . APP_URL
        . '/dashboard/teacher_attendance.php?operational_class_id='
        . $classId
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    closeAttendanceRedirect(0, 'Invalid Attendance Session closure request.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    closeAttendanceRedirect(0, 'The request could not be verified. Refresh the page and try again.');
}

$attendanceSessionId = filter_var(
    $_POST['attendance_session_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$classId = filter_var(
    $_POST['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($attendanceSessionId === false || $classId === false) {
    closeAttendanceRedirect(0, 'A valid Attendance Session is required.');
}

$attendanceSessionId = (int) $attendanceSessionId;
$classId = (int) $classId;

try {
    $pdo->beginTransaction();

    $sessionStmt = $pdo->prepare("
        SELECT
            ats.attendance_session_id,
            ats.operational_class_id,
            ats.meeting_date,
            ats.scheduled_start_time,
            ats.status,
            oc.teacher_id
        FROM attendance_sessions AS ats
        INNER JOIN operational_classes AS oc
            ON oc.operational_class_id = ats.operational_class_id
        INNER JOIN school_years AS active_sy
            ON active_sy.school_year = oc.school_year
           AND active_sy.status = 'Active'
        WHERE ats.attendance_session_id = ?
          AND ats.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
        FOR UPDATE
    ");
    $sessionStmt->execute([
        $attendanceSessionId,
        $classId,
        (int) $_SESSION['user_id'],
    ]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($session === null) {
        throw new RuntimeException('The Attendance Session is unavailable or you do not have permission to close it.');
    }

    if ((string) $session['status'] !== 'Open') {
        throw new RuntimeException('This Attendance Session is already closed.');
    }

    $policy = aprismAttendancePolicy($pdo);
    $scheduledStart = aprismAttendanceScheduledStart(
        (string) $session['meeting_date'],
        (string) $session['scheduled_start_time']
    );
    $now = new DateTimeImmutable('now');

    $unresolvedStmt = $pdo->prepare("
        SELECT sce.student_class_enrollment_id
        FROM student_class_enrollments AS sce
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        LEFT JOIN student_attendance_records AS sar
            ON sar.student_class_enrollment_id = sce.student_class_enrollment_id
           AND sar.attendance_session_id = ?
        WHERE sce.operational_class_id = ?
          AND sce.status = 'Active'
          AND sar.student_attendance_record_id IS NULL
        ORDER BY sce.student_class_enrollment_id
        FOR UPDATE
    ");
    $unresolvedStmt->execute([$attendanceSessionId, $classId]);
    $unresolved = $unresolvedStmt->fetchAll(PDO::FETCH_COLUMN);

    if ($unresolved !== [] && !aprismAttendanceAbsenceThresholdCrossed($scheduledStart, $now, $policy)) {
        throw new RuntimeException(
            'Attendance cannot close yet. Wait until the configured absence threshold has passed for unresolved Students.'
        );
    }

    if ($unresolved !== []) {
        $insertAbsentStmt = $pdo->prepare("
            INSERT INTO student_attendance_records (
                attendance_session_id,
                student_class_enrollment_id,
                status,
                checked_in_at,
                capture_source
            ) VALUES (?, ?, 'Absent', NULL, 'System')
        ");

        foreach ($unresolved as $unresolvedEnrollmentId) {
            $insertAbsentStmt->execute([
                $attendanceSessionId,
                (int) $unresolvedEnrollmentId,
            ]);
        }
    }

    $updateStmt = $pdo->prepare("
        UPDATE attendance_sessions
        SET status = 'Closed',
            closed_at = NOW()
        WHERE attendance_session_id = ?
          AND status = 'Open'
    ");
    $updateStmt->execute([$attendanceSessionId]);

    if ($updateStmt->rowCount() !== 1) {
        throw new RuntimeException('The Attendance Session could not be closed.');
    }

    logAudit(
        $pdo,
        'Close Attendance Session',
        'Closed Attendance Session #'
        . $attendanceSessionId
        . ' for Operational Class #'
        . $classId
    );

    $pdo->commit();

    closeAttendanceRedirect(
        $classId,
        null,
        'Attendance Session closed successfully.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[APRISM Close Attendance Session] ' . $exception->getMessage());
    closeAttendanceRedirect(
        $classId,
        $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'The Attendance Session could not be closed.'
    );
}
