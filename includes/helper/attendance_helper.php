<?php

declare(strict_types=1);

/**
 * Resolve an attendance session without granting access from a class ID alone.
 * Call inside the scan transaction so the class/session ownership snapshot is locked.
 *
 * @return array{allowed:bool,code:?string,message:?string,session:?array<string,mixed>}
 */
function aprismResolveAttendanceScanSession(PDO $pdo, int $sessionId, int $teacherId): array
{
    $stmt = $pdo->prepare("SELECT ats.attendance_session_id, ats.operational_class_id,
            ats.conducted_by_user_id, ats.meeting_date, ats.scheduled_start_time, ats.status,
            oc.teacher_id AS current_teacher_id
        FROM attendance_sessions ats
        INNER JOIN operational_classes oc ON oc.operational_class_id = ats.operational_class_id
        INNER JOIN school_years active_sy
            ON active_sy.school_year = oc.school_year AND active_sy.status = 'Active'
        WHERE ats.attendance_session_id = ? AND oc.status = 'Active'
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($session === null) {
        return [
            'allowed' => false,
            'code' => 'session_unavailable',
            'message' => 'APRISM did not record this scan. The Attendance Session is unavailable or you do not have permission to use it; keep the saved scan on this device for review.',
            'session' => null,
        ];
    }

    $conductorId = $session['conducted_by_user_id'] === null
        ? null
        : (int) $session['conducted_by_user_id'];
    if ((int) $session['current_teacher_id'] !== $teacherId) {
        $responsibilityChanged = $conductorId === $teacherId;
        return [
            'allowed' => false,
            'code' => $responsibilityChanged ? 'responsibility_changed' : 'session_unavailable',
            'message' => $responsibilityChanged
                ? 'Class responsibility has changed. APRISM did not record this scan; it remains saved on this device for review. Contact the Technical Administrator before clearing it.'
                : 'APRISM did not record this scan. The Attendance Session is unavailable or you do not have permission to use it; keep the saved scan on this device for review.',
            'session' => null,
        ];
    }

    if ($conductorId !== null && $conductorId !== $teacherId) {
        return [
            'allowed' => false,
            'code' => 'session_conductor_mismatch',
            'message' => 'This Attendance Session was started by another Teacher. APRISM did not record this scan; keep it saved on this device for review and contact the Technical Administrator.',
            'session' => null,
        ];
    }

    unset($session['current_teacher_id']);
    return ['allowed' => true, 'code' => null, 'message' => null, 'session' => $session];
}

require_once __DIR__ . '/system_settings_helper.php';

const APRISM_ATTENDANCE_DEFAULT_GRACE_PERIOD_MINUTES = 10;
const APRISM_ATTENDANCE_DEFAULT_ABSENT_THRESHOLD_MINUTES = 30;
// A missing setting keeps the safe default of allowing attendance from the scheduled start.
const APRISM_ATTENDANCE_DEFAULT_PRE_START_MINUTES = 0;

function aprismAttendancePolicy(PDO $pdo): array
{
    $gracePeriod = getSystemSetting(
        $pdo,
        'attendance_grace_period_minutes'
    );
    $absentThreshold = getSystemSetting(
        $pdo,
        'attendance_absent_threshold_minutes'
    );
    $preStartMinutes = getSystemSetting(
        $pdo,
        'attendance_pre_start_minutes'
    );

    return [
        'grace_period_minutes' => aprismAttendancePolicyValue(
            $gracePeriod,
            APRISM_ATTENDANCE_DEFAULT_GRACE_PERIOD_MINUTES
        ),
        'absent_threshold_minutes' => aprismAttendancePolicyValue(
            $absentThreshold,
            APRISM_ATTENDANCE_DEFAULT_ABSENT_THRESHOLD_MINUTES
        ),
        'pre_start_minutes' => aprismAttendancePolicyValue(
            $preStartMinutes,
            APRISM_ATTENDANCE_DEFAULT_PRE_START_MINUTES
        ),
    ];
}

function aprismAttendancePolicyValue(?string $value, int $fallback): int
{
    if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return $fallback;
    }

    return max(0, (int) $value);
}

function aprismAttendanceStatus(
    DateTimeInterface $scheduledStart,
    DateTimeInterface $checkIn,
    array $policy
): string {
    $elapsedSeconds = $checkIn->getTimestamp() - $scheduledStart->getTimestamp();
    $graceSeconds = ((int) $policy['grace_period_minutes']) * 60;
    $absentThresholdSeconds = ((int) $policy['absent_threshold_minutes']) * 60;

    if ($elapsedSeconds <= $graceSeconds) {
        return 'Present';
    }

    if ($elapsedSeconds <= $absentThresholdSeconds) {
        return 'Late';
    }

    return 'Absent';
}

function aprismAttendanceScheduledStart(
    string $meetingDate,
    string $scheduledStartTime
): DateTimeImmutable {
    $scheduledStart = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $meetingDate . ' ' . $scheduledStartTime
    );

    if ($scheduledStart === false) {
        throw new InvalidArgumentException(
            'The scheduled meeting start time is invalid.'
        );
    }

    return $scheduledStart;
}

function aprismAttendanceCaptureWindowOpen(
    DateTimeInterface $scheduledStart,
    DateTimeInterface $at,
    array $policy
): bool {
    $preStartSeconds = ((int) $policy['pre_start_minutes']) * 60;
    $earliestCapture = $scheduledStart->getTimestamp() - $preStartSeconds;

    return $at->getTimestamp() >= $earliestCapture;
}

function aprismAttendanceAbsenceThresholdCrossed(
    DateTimeInterface $scheduledStart,
    DateTimeInterface $at,
    array $policy
): bool {
    $elapsedSeconds = $at->getTimestamp() - $scheduledStart->getTimestamp();
    $thresholdSeconds = ((int) $policy['absent_threshold_minutes']) * 60;

    return $elapsedSeconds > $thresholdSeconds;
}
