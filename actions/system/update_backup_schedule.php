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

    if (
        !preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $backupScheduleTime
        )
    ) {
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
                'Please select a valid automatic backup day.';

            header(
                'Location: ' .
                APP_URL .
                '/dashboard/technical_admin_settings.php#backup'
            );
            exit;
        }

    } else {

        $backupScheduleDay = 'monday';
    }
}

try {

    $updatedBy = (int) $_SESSION['user_id'];

    $pdo->beginTransaction();

    setSystemSetting(
        $pdo,
        'backup_schedule_enabled',
        $backupScheduleEnabled,
        $updatedBy
    );

    if ($backupScheduleEnabled === '1') {

        setSystemSetting(
            $pdo,
            'backup_schedule_frequency',
            $backupScheduleFrequency,
            $updatedBy
        );

        setSystemSetting(
            $pdo,
            'backup_schedule_time',
            $backupScheduleTime,
            $updatedBy
        );

        setSystemSetting(
            $pdo,
            'backup_schedule_day',
            $backupScheduleDay,
            $updatedBy
        );

        if ($backupScheduleFrequency === 'weekly') {

            $scheduleDescription =
                ucfirst($backupScheduleFrequency) .
                ' on ' .
                ucfirst($backupScheduleDay) .
                ' at ' .
                $backupScheduleTime;

        } else {

            $scheduleDescription =
                ucfirst($backupScheduleFrequency) .
                ' at ' .
                $backupScheduleTime;
        }

        logAudit(
            $pdo,
            'System Settings',
            "Updated Automatic Backup Schedule\n" .
            "- Automatic Backups: Enabled\n" .
            "- Schedule: {$scheduleDescription}"
        );

    } else {

        logAudit(
            $pdo,
            'System Settings',
            "Updated Automatic Backup Schedule\n" .
            "- Automatic Backups: Disabled"
        );
    }

    $pdo->commit();

    $_SESSION['success_message'] =
        'Automatic backup schedule updated successfully.';

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[APRISM Backup Schedule] ' .
        $e->getMessage()
    );

    $_SESSION['error_message'] =
        'Automatic backup schedule could not be updated. Please try again.';
}

header(
    'Location: ' .
    APP_URL .
    '/dashboard/technical_admin_settings.php#backup'
);

exit;