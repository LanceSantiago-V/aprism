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

function manualAttendanceRedirect(int $classId, ?string $error = null, ?string $success = null): never
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
    manualAttendanceRedirect(0, 'Invalid manual attendance request.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    manualAttendanceRedirect(0, 'The request could not be verified. Refresh the page and try again.');
}

$attendanceSessionId = filter_var(
    $_POST['attendance_session_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$enrollmentId = filter_var(
    $_POST['student_class_enrollment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$classId = filter_var(
    $_POST['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($attendanceSessionId === false || $enrollmentId === false || $classId === false) {
    manualAttendanceRedirect(0, 'A valid Attendance Session and Student are required.');
}

$attendanceSessionId = (int) $attendanceSessionId;
$enrollmentId = (int) $enrollmentId;
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
        throw new RuntimeException('The Attendance Session is unavailable or you do not have permission to use it.');
    }

    if ((string) $session['status'] !== 'Open') {
        throw new RuntimeException('This Attendance Session is closed and no longer accepts initial attendance capture.');
    }

    $participantStmt = $pdo->prepare("
        SELECT sce.student_class_enrollment_id
        FROM student_class_enrollments AS sce
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        WHERE sce.student_class_enrollment_id = ?
          AND sce.operational_class_id = ?
          AND sce.status = 'Active'
        LIMIT 1
        FOR UPDATE
    ");
    $participantStmt->execute([$enrollmentId, $classId]);

    if ($participantStmt->fetchColumn() === false) {
        throw new RuntimeException('The selected Student is not an active participant in this class.');
    }

    $existingStmt = $pdo->prepare("
        SELECT student_attendance_record_id
        FROM student_attendance_records
        WHERE attendance_session_id = ?
          AND student_class_enrollment_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $existingStmt->execute([$attendanceSessionId, $enrollmentId]);

    if ($existingStmt->fetchColumn() !== false) {
        throw new RuntimeException('Attendance has already been recorded for this Student.');
    }

    $now = new DateTimeImmutable('now');
    $policy = aprismAttendancePolicy($pdo);
    $scheduledStart = aprismAttendanceScheduledStart(
        (string) $session['meeting_date'],
        (string) $session['scheduled_start_time']
    );

    if (!aprismAttendanceCaptureWindowOpen($scheduledStart, $now, $policy)) {
        throw new RuntimeException('Attendance capture is not yet available for this scheduled meeting.');
    }

    if (aprismAttendanceAbsenceThresholdCrossed($scheduledStart, $now, $policy)) {
        throw new RuntimeException('The absence threshold has passed. This Student cannot receive an initial manual check-in.');
    }

    $status = aprismAttendanceStatus($scheduledStart, $now, $policy);

    if ($status === 'Absent') {
        throw new RuntimeException('The absence threshold has passed. This Student cannot receive an initial manual check-in.');
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO student_attendance_records (
            attendance_session_id,
            student_class_enrollment_id,
            status,
            checked_in_at,
            capture_source
        ) VALUES (?, ?, ?, ?, 'Manual Attendance')
    ");
    $insertStmt->execute([
        $attendanceSessionId,
        $enrollmentId,
        $status,
        $now->format('Y-m-d H:i:s'),
    ]);

    logAudit(
        $pdo,
        'Record Manual Attendance',
        'Recorded ' . $status . ' attendance for Student Class Enrollment #'
        . $enrollmentId
        . ' in Attendance Session #'
        . $attendanceSessionId
    );

    $pdo->commit();

    manualAttendanceRedirect(
        $classId,
        null,
        'Manual attendance recorded as ' . $status . '.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[APRISM Manual Attendance] ' . $exception->getMessage());
    manualAttendanceRedirect(
        $classId,
        $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Manual attendance could not be recorded.'
    );
}
