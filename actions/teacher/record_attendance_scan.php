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

header('Content-Type: application/json; charset=utf-8');

function attendanceScanRespond(
    int $status,
    bool $ok,
    ?string $code = null,
    ?string $message = null,
    array $data = []
): never {
    http_response_code($status);
    $response = ['ok' => $ok];

    if ($ok) {
        $response['data'] = $data;
    } else {
        $response['error'] = [
            'code' => $code ?? 'attendance_scan_error',
            'message' => $message ?? 'The AprilTag scan could not be recorded.',
        ];
        if ($data !== []) {
            $response['data'] = $data;
        }
    }

    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    attendanceScanRespond(405, false, 'method_not_allowed', 'This endpoint accepts POST requests only.');
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if ($contentType === '' || !str_starts_with($contentType, 'application/json')) {
    attendanceScanRespond(415, false, 'json_required', 'The scan request must use a JSON body.');
}

$rawBody = file_get_contents('php://input');
try {
    $request = json_decode((string) $rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    attendanceScanRespond(400, false, 'invalid_json', 'The scan request must contain valid JSON.');
}

if (!is_array($request) || !validateCsrfToken($request['csrf_token'] ?? null)) {
    attendanceScanRespond(419, false, 'request_not_verified', 'The scan request could not be verified. Refresh the page and try again.');
}

$sessionId = filter_var(
    $request['attendance_session_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$tagCode = filter_var(
    $request['tag_code'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0, 'max_range' => 48713]]
);

if ($sessionId === false || $tagCode === false) {
    attendanceScanRespond(422, false, 'invalid_scan', 'A valid Attendance Session and tagStandard52h13 ID are required.');
}

$sessionId = (int) $sessionId;
$tagCode = (int) $tagCode;

try {
    $pdo->beginTransaction();

    $sessionStmt = $pdo->prepare("\n        SELECT\n            ats.attendance_session_id,\n            ats.operational_class_id,\n            ats.meeting_date,\n            ats.scheduled_start_time,\n            ats.status\n        FROM attendance_sessions AS ats\n        INNER JOIN operational_classes AS oc\n            ON oc.operational_class_id = ats.operational_class_id\n        INNER JOIN school_years AS active_sy\n            ON active_sy.school_year = oc.school_year\n           AND active_sy.status = 'Active'\n        WHERE ats.attendance_session_id = ?\n          AND oc.teacher_id = ?\n          AND oc.status = 'Active'\n        LIMIT 1\n        FOR UPDATE\n    ");
    $sessionStmt->execute([$sessionId, (int) $_SESSION['user_id']]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($session === null) {
        throw new RuntimeException('The Attendance Session is unavailable or you do not have permission to use it.');
    }

    if ((string) $session['status'] !== 'Open') {
        attendanceScanRespond(409, false, 'session_closed', 'This Attendance Session is closed and no longer accepts scans.');
    }

    $tagStmt = $pdo->prepare("\n        SELECT\n            at.current_student_id,\n            st.first_name,\n            st.middle_name,\n            st.last_name,\n            st.student_number\n        FROM april_tags AS at\n        INNER JOIN students AS st\n            ON st.student_id = at.current_student_id\n           AND st.status = 'Active'\n        WHERE at.tag_family = 'tagStandard52h13'\n          AND at.tag_code = ?\n          AND at.status = 'Assigned'\n          AND at.current_student_id IS NOT NULL\n        LIMIT 1\n        FOR UPDATE\n    ");
    $tagStmt->execute([$tagCode]);
    $tag = $tagStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($tag === null) {
        attendanceScanRespond(422, false, 'tag_not_assigned', 'This tag is not currently assigned to an active Student.');
    }

    $participantStmt = $pdo->prepare("\n        SELECT\n            sce.student_class_enrollment_id,\n            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name) AS display_name,\n            st.student_number\n        FROM student_class_enrollments AS sce\n        INNER JOIN students AS st\n            ON st.student_id = sce.student_id\n           AND st.status = 'Active'\n        WHERE sce.operational_class_id = ?\n          AND sce.student_id = ?\n          AND sce.status = 'Active'\n        LIMIT 1\n        FOR UPDATE\n    ");
    $participantStmt->execute([
        (int) $session['operational_class_id'],
        (int) $tag['current_student_id'],
    ]);
    $participant = $participantStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($participant === null) {
        attendanceScanRespond(422, false, 'student_not_in_class', 'This Student is not an active participant in this class.');
    }

    $existingStmt = $pdo->prepare("\n        SELECT status, checked_in_at, capture_source\n        FROM student_attendance_records\n        WHERE attendance_session_id = ?\n          AND student_class_enrollment_id = ?\n        LIMIT 1\n        FOR UPDATE\n    ");
    $existingStmt->execute([$sessionId, (int) $participant['student_class_enrollment_id']]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($existing !== null) {
        $pdo->rollBack();
        attendanceScanRespond(409, false, 'already_recorded', 'Attendance is already recorded for this Student.', [
            'student_class_enrollment_id' => (int) $participant['student_class_enrollment_id'],
            'student_name' => $participant['display_name'],
            'student_number' => $participant['student_number'],
            'status' => $existing['status'],
            'checked_in_at' => $existing['checked_in_at'],
            'capture_source' => $existing['capture_source'],
        ]);
    }

    $now = new DateTimeImmutable('now');
    $policy = aprismAttendancePolicy($pdo);
    $scheduledStart = aprismAttendanceScheduledStart(
        (string) $session['meeting_date'],
        (string) $session['scheduled_start_time']
    );

    if (!aprismAttendanceCaptureWindowOpen($scheduledStart, $now, $policy)) {
        attendanceScanRespond(422, false, 'attendance_not_open', 'Attendance capture is not yet available for this scheduled meeting.');
    }

    if (aprismAttendanceAbsenceThresholdCrossed($scheduledStart, $now, $policy)) {
        attendanceScanRespond(422, false, 'attendance_threshold_passed', 'The absence threshold has passed. This Student cannot receive an initial scan check-in.');
    }

    $status = aprismAttendanceStatus($scheduledStart, $now, $policy);
    if ($status === 'Absent') {
        attendanceScanRespond(422, false, 'attendance_threshold_passed', 'The absence threshold has passed. This Student cannot receive an initial scan check-in.');
    }

    $insertStmt = $pdo->prepare("\n        INSERT INTO student_attendance_records (\n            attendance_session_id,\n            student_class_enrollment_id,\n            status,\n            checked_in_at,\n            capture_source\n        ) VALUES (?, ?, ?, ?, 'Browser Camera AprilTag')\n    ");
    $checkedInAt = $now->format('Y-m-d H:i:s');
    $insertStmt->execute([
        $sessionId,
        (int) $participant['student_class_enrollment_id'],
        $status,
        $checkedInAt,
    ]);

    logAudit(
        $pdo,
        'Record AprilTag Attendance',
        'Recorded ' . $status . ' attendance for Student Class Enrollment #'
        . (int) $participant['student_class_enrollment_id']
        . ' in Attendance Session #'
        . $sessionId
    );

    $pdo->commit();
    attendanceScanRespond(200, true, null, null, [
        'student_class_enrollment_id' => (int) $participant['student_class_enrollment_id'],
        'student_name' => $participant['display_name'],
        'student_number' => $participant['student_number'],
        'status' => $status,
        'checked_in_at' => $checkedInAt,
        'capture_source' => 'Browser Camera AprilTag',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[APRISM AprilTag Attendance Scan] ' . $exception->getMessage());
    attendanceScanRespond(500, false, 'scan_recording_failed', 'The AprilTag scan could not be recorded.');
}
