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

// Read submitted values

$backupRetention = trim(
    $_POST['backup_retention_days'] ?? ''
);

$backupScheduleEnabled =
    ($_POST['backup_schedule_enabled'] ?? '0') === '1'
        ? '1'
        : '0';

$backupScheduleFrequency = trim(
    $_POST['backup_schedule_frequency'] ?? ''
);

$backupScheduleTime = trim(
    $_POST['backup_schedule_time'] ?? ''
);

$backupScheduleDay = trim(
    $_POST['backup_schedule_day'] ?? ''
);

// Validate backup retention

if ($backupRetention !== '') {

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
}

// Validate automatic backup scheduling

if ($backupScheduleEnabled === '1') {

    $allowedFrequencies = [
        'daily',
        'weekly'
    ];

    if (
        !in_array(
            $backupScheduleFrequency,
            $allowedFrequencies,
            true
        )
    ) {
        $_SESSION['error_message'] =
            'Please select a valid automatic backup frequency.';

        header(
            'Location: ' .
            APP_URL .
            '/dashboard/technical_admin_settings.php#backup'
        );
        exit;
    }

    $timeIsValid = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $backupScheduleTime
    );

    if ($timeIsValid !== 1) {
        $_SESSION['error_message'] =
            'Please select a valid automatic backup time.';

        header(
            'Location: ' .
            APP_URL .
            '/dashboard/technical_admin_settings.php#backup'
        );
        exit;
    }

    if ($backupScheduleFrequency === 'weekly') {

        $allowedDays = [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday'
        ];

        if (
            !in_array(
                $backupScheduleDay,
                $allowedDays,
                true
            )
        ) {
            $_SESSION['error_message'] =
                'Please select a valid day for the weekly backup schedule.';

            header(
                'Location: ' .
                APP_URL .
                '/dashboard/technical_admin_settings.php#backup'
            );
            exit;
        }
    }
}

// Save backup settings

try {

    $userId = (int) $_SESSION['user_id'];

    // Save or remove backup retention

    if ($backupRetention === '') {

        deleteSystemSetting(
            $pdo,
            'backup_retention_days'
        );

    } else {

        setSystemSetting(
            $pdo,
            'backup_retention_days',
            (string) $backupRetention,
            $userId
        );
    }

    // Save automatic backup enabled state

    setSystemSetting(
        $pdo,
        'backup_schedule_enabled',
        $backupScheduleEnabled,
        $userId
    );

    // Save automatic backup schedule details

    if ($backupScheduleEnabled === '1') {

        setSystemSetting(
            $pdo,
            'backup_schedule_frequency',
            $backupScheduleFrequency,
            $userId
        );

        setSystemSetting(
            $pdo,
            'backup_schedule_time',
            $backupScheduleTime,
            $userId
        );

        // Weekly schedules require a selected day

        if ($backupScheduleFrequency === 'weekly') {

            setSystemSetting(
                $pdo,
                'backup_schedule_day',
                $backupScheduleDay,
                $userId
            );

        } else {

            deleteSystemSetting(
                $pdo,
                'backup_schedule_day'
            );
        }

    } else {

        // Remove schedule details when automatic backups are disabled

        deleteSystemSetting(
            $pdo,
            'backup_schedule_frequency'
        );

        deleteSystemSetting(
            $pdo,
            'backup_schedule_time'
        );

        deleteSystemSetting(
            $pdo,
            'backup_schedule_day'
        );
    }

    // Prepare retention information for the audit log

    if ($backupRetention === '') {

        $retentionDescription =
            'No retention limit';

    } else {

        $retentionDescription =
            "{$backupRetention} days";
    }

    // Prepare schedule information for the audit log

    if ($backupScheduleEnabled === '1') {

        if ($backupScheduleFrequency === 'daily') {

            $scheduleDescription =
                "Daily at {$backupScheduleTime}";

        } else {

            $formattedDay = ucfirst(
                $backupScheduleDay
            );

            $scheduleDescription =
                "Weekly on {$formattedDay} at {$backupScheduleTime}";
        }

    } else {

        $scheduleDescription = 'Disabled';
    }

    // Record the settings update

    logAudit(
        $pdo,
        'System Settings',
        "Updated backup settings. Retention: {$retentionDescription}. Automatic backup schedule: {$scheduleDescription}."
    );

    $_SESSION['success_message'] =
        'Backup settings updated successfully.';

} catch (PDOException $e) {

    $_SESSION['error_message'] =
        'Backup settings could not be updated. Please try again.';
}

header(
    'Location: ' .
    APP_URL .
    '/dashboard/technical_admin_settings.php#backup'
);

exit;