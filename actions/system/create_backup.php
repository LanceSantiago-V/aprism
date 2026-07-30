<?php

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/flash_message.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/backup_helper.php';

$backupResult = createDatabaseBackup(
    $pdo,
    $host,
    $username,
    $password,
    $dbname
);

if (!$backupResult['success']) {

    $_SESSION['error_message'] =
        'Database backup could not be created.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_backups.php'
    );

    exit;
}

$fileName = $backupResult['file_name'];

logAudit(
    $pdo,
    'Database Backup',
    "Generated backup: {$fileName}"
);

$deletedBackupCount =
    $backupResult['deleted_backup_count'];

$retentionDays =
    $backupResult['retention_days'];

if (
    $deletedBackupCount > 0 &&
    $retentionDays !== null
) {

    logAudit(
        $pdo,
        'Database Backup',
        "Removed {$deletedBackupCount} expired backup(s) based on the {$retentionDays}-day retention policy."
    );
}

$_SESSION['success_message'] =
    'Database backup created successfully.';

header(
    'Location: ' .
    APP_URL .
    '/dashboard/technical_admin_backups.php'
);

exit;