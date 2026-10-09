<?php

declare(strict_types=1);

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
