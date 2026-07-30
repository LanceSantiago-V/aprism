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
        '/dashboard/technical_admin_settings.php#backup'
    );
    exit;
}

$backupRetention = trim(
    $_POST['backup_retention_days'] ?? ''
);

if ($backupRetention === '') {

    try {

        deleteSystemSetting(
            $pdo,
            'backup_retention_days'
        );

        logAudit(
            $pdo,
            'System Settings',
            "Updated Backup Retention Settings\n" .
            "- Backup Retention Limit: Unconfigured"
        );

        $_SESSION['success_message'] =
            'Backup retention settings updated successfully.';

    } catch (Throwable $e) {

        error_log(
            '[APRISM Backup Retention] ' .
            $e->getMessage()
        );

        $_SESSION['error_message'] =
            'Backup retention settings could not be updated. Please try again.';
    }

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#backup'
    );
    exit;
}

if (
    filter_var(
        $backupRetention,
        FILTER_VALIDATE_INT
    ) === false
) {
    $_SESSION['error_message'] =
        'Backup Retention Limit must be a valid whole number.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#backup'
    );
    exit;
}

$backupRetention = (int) $backupRetention;

if (
    $backupRetention < 1 ||
    $backupRetention > 365
) {
    $_SESSION['error_message'] =
        'Backup Retention Limit must be between 1 and 365 days.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_settings.php#backup'
    );
    exit;
}

try {

    $updatedBy = (int) $_SESSION['user_id'];

    setSystemSetting(
        $pdo,
        'backup_retention_days',
        (string) $backupRetention,
        $updatedBy
    );

    logAudit(
        $pdo,
        'System Settings',
        "Updated Backup Retention Settings\n" .
        "- Backup Retention Limit: {$backupRetention} days"
    );

    $_SESSION['success_message'] =
        'Backup retention settings updated successfully.';

} catch (Throwable $e) {

    error_log(
        '[APRISM Backup Retention] ' .
        $e->getMessage()
    );

    $_SESSION['error_message'] =
        'Backup retention settings could not be updated. Please try again.';
}

header(
    'Location: ' .
    APP_URL .
    '/dashboard/technical_admin_settings.php#backup'
);

exit;