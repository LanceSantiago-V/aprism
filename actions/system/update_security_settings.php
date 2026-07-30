<?php

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/system_settings_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#security'
    );
    exit;
}

$sessionTimeout = trim($_POST['session_timeout'] ?? '');

$temporaryLockDuration = trim(
    $_POST['temporary_lock_duration'] ?? ''
);

$maximumFailedLoginAttempts = trim(
    $_POST['maximum_failed_login_attempts'] ?? ''
);

if (
    $sessionTimeout === '' ||
    filter_var($sessionTimeout, FILTER_VALIDATE_INT) === false
) {
    $_SESSION['error_message'] =
        'Standard Session Timeout must be a valid whole number.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#security'
    );
    exit;
}

$sessionTimeout = (int) $sessionTimeout;

if ($sessionTimeout < 5 || $sessionTimeout > 1440) {
    $_SESSION['error_message'] =
        'Standard Session Timeout must be between 5 and 1440 minutes.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#security'
    );
    exit;
}


if (
    $temporaryLockDuration === '' ||
    filter_var($temporaryLockDuration, FILTER_VALIDATE_INT) === false
) {

    $_SESSION['error_message'] =
        'Temporary Lock Duration must be a valid whole number.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#security'
    );

    exit;

}

$temporaryLockDuration = (int) $temporaryLockDuration;

if (
    $temporaryLockDuration < 1 ||
    $temporaryLockDuration > 1440
) {

    $_SESSION['error_message'] =
        'Temporary Lock Duration must be between 1 and 1440 minutes.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#security'
    );

    exit;

}

// Validate Maximum Failed Login Attempts
if (
    $maximumFailedLoginAttempts !== '' &&
    !in_array(
        $maximumFailedLoginAttempts,
        ['3', '5', '10'],
        true
    )
) {

    $_SESSION['error_message'] =
        'Maximum Failed Login Attempts is invalid.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#security'
    );

    exit;

}

try {

    $updatedBy = (int) $_SESSION['user_id'];

    $pdo->beginTransaction();

    setSystemSetting(
        $pdo,
        'security_session_timeout_minutes',
        (string) $sessionTimeout,
        $updatedBy
    );

    setSystemSetting(
        $pdo,
        'security_temporary_lock_duration_minutes',
        (string) $temporaryLockDuration,
        $updatedBy
    );

    if ($maximumFailedLoginAttempts === '') {

        deleteSystemSetting(
            $pdo,
            'security_max_failed_login_attempts'
        );

    } else {

        setSystemSetting(
            $pdo,
            'security_max_failed_login_attempts',
            $maximumFailedLoginAttempts,
            $updatedBy
        );

    }

    $failedAttemptsText =
        $maximumFailedLoginAttempts === ''
        ? 'Not enforced'
        : $maximumFailedLoginAttempts . ' attempts';

    logAudit(
        $pdo,
        'System Settings',
        "Updated Security Settings\n" .
        "- Standard Session Timeout: {$sessionTimeout} minutes\n" .
        "- Temporary Lock Duration: {$temporaryLockDuration} minutes\n" .
        "- Maximum Failed Login Attempts: {$failedAttemptsText}"
    );

    $pdo->commit();

    $_SESSION['success_message'] =
        'Security settings updated successfully.';

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[APRISM Security Settings] ' .
        $e->getMessage()
    );

    $_SESSION['error_message'] =
        'Security settings could not be updated. Please try again.';

}

header(
    'Location: ' .
    APP_URL .
    '/dashboard/technical_admin_settings.php#security'
);

exit;