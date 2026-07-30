<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/flash_message.php';
require_once __DIR__ . '/../../includes/helper/system_settings_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#system'
    );
    exit;
}

// Read submitted values
$gracePeriod = trim(
    $_POST['attendance_grace_period_minutes'] ?? ''
);

$absentThreshold = trim(
    $_POST['attendance_absent_threshold_minutes'] ?? ''
);

// Validate grace period
if (
    $gracePeriod !== '' &&
    (
        filter_var($gracePeriod, FILTER_VALIDATE_INT) === false ||
        (int) $gracePeriod < 0
    )
) {
    $_SESSION['error_message'] =
        'Grace Period must be a valid non-negative whole number.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#system'
    );
    exit;
}

// Validate absent threshold
if (
    $absentThreshold !== '' &&
    (
        filter_var($absentThreshold, FILTER_VALIDATE_INT) === false ||
        (int) $absentThreshold < 0
    )
) {
    $_SESSION['error_message'] =
        'Absent Threshold must be a valid non-negative whole number.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#system'
    );
    exit;
}

// Get current values
$currentGracePeriod = getSystemSetting(
    $pdo,
    'attendance_grace_period_minutes'
);

$currentAbsentThreshold = getSystemSetting(
    $pdo,
    'attendance_absent_threshold_minutes'
);

// Normalize submitted values
$newGracePeriod = $gracePeriod === ''
    ? null
    : (string) (int) $gracePeriod;

$newAbsentThreshold = $absentThreshold === ''
    ? null
    : (string) (int) $absentThreshold;

// Check if anything changed
if (
    $currentGracePeriod === $newGracePeriod &&
    $currentAbsentThreshold === $newAbsentThreshold
) {
    $_SESSION['warning_message'] =
        'No system configuration changes were detected.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#system'
    );
    exit;
}

try {

    $pdo->beginTransaction();

    $updatedBy = (int) $_SESSION['user_id'];

    // Save or clear grace period
    if ($newGracePeriod === null) {
        deleteSystemSetting(
            $pdo,
            'attendance_grace_period_minutes'
        );
    } else {
        setSystemSetting(
            $pdo,
            'attendance_grace_period_minutes',
            $newGracePeriod,
            $updatedBy
        );
    }

    // Save or clear absent threshold
    if ($newAbsentThreshold === null) {
        deleteSystemSetting(
            $pdo,
            'attendance_absent_threshold_minutes'
        );
    } else {
        setSystemSetting(
            $pdo,
            'attendance_absent_threshold_minutes',
            $newAbsentThreshold,
            $updatedBy
        );
    }

    $pdo->commit();

    $gracePeriodDescription =
        $newGracePeriod !== null
        ? "{$newGracePeriod} minute(s)"
        : 'Unconfigured';

    $absentThresholdDescription =
        $newAbsentThreshold !== null
        ? "{$newAbsentThreshold} minute(s)"
        : 'Unconfigured';

    logAudit(
        $pdo,
        'System Settings',
        "Updated Attendance Policy Settings\n" .
        "- Grace Period: {$gracePeriodDescription}\n" .
        "- Absent Threshold: {$absentThresholdDescription}"
    );

    $_SESSION['success_message'] =
        'System configuration updated successfully.';

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[APRISM System Configuration] ' .
        $e->getMessage()
    );

    $_SESSION['error_message'] =
        'Unable to update system configuration.';
}

header(
    'Location: ' .
    APP_URL .
    '/dashboard/technical_admin_settings.php#system'
);
exit;

