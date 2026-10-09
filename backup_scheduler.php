<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be executed from the command line.');
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/pdo.php';
require_once __DIR__ . '/includes/helper/system_settings_helper.php';
require_once __DIR__ . '/includes/helper/backup_helper.php';
require_once __DIR__ . '/includes/helper/audit_helper.php';

$enabled = getSystemSetting(
    $pdo,
    'backup_schedule_enabled'
);

$frequency = getSystemSetting(
    $pdo,
    'backup_schedule_frequency'
);

$backupTime = getSystemSetting(
    $pdo,
    'backup_schedule_time'
);

$backupDay = getSystemSetting(
    $pdo,
    'backup_schedule_day'
);

if ($enabled !== '1') {
    echo "Automatic backups are disabled." . PHP_EOL;
    exit;
}

$currentDay = strtolower(date('l'));
$currentTime = date('H:i');

if (
    $frequency === 'weekly' &&
    $backupDay !== $currentDay
) {
    echo "No backup is scheduled for today." . PHP_EOL;
    exit;
}

if ($currentTime < $backupTime) {
    echo "Backup is not due yet for the current scheduled day." . PHP_EOL;
    exit;
}

$schedulerLockHandle = null;
$schedulerLockPath = BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . '.scheduler.lock';

if (!is_dir(BACKUP_DIRECTORY) && !mkdir(BACKUP_DIRECTORY, 0700, true) && !is_dir(BACKUP_DIRECTORY)) {
    echo "The backup directory is unavailable." . PHP_EOL;
    exit(1);
}

$schedulerLockHandle = @fopen($schedulerLockPath, 'c');

if ($schedulerLockHandle === false || !flock($schedulerLockHandle, LOCK_EX | LOCK_NB)) {
    if (is_resource($schedulerLockHandle)) {
        fclose($schedulerLockHandle);
    }

    echo "Another scheduled backup run is already in progress." . PHP_EOL;
    exit;
}

register_shutdown_function(
    static function () use (&$schedulerLockHandle): void {
        if (is_resource($schedulerLockHandle)) {
            flock($schedulerLockHandle, LOCK_UN);
            fclose($schedulerLockHandle);
        }
    }
);

$scheduledDate = date('Y-m-d');

if (
    hasScheduledBackupRun(
        $pdo,
        $scheduledDate,
        $backupTime
    )
) {
    echo "Scheduled backup already completed for this occurrence." . PHP_EOL;
    exit;
}

$result = createDatabaseBackup(
    $pdo,
    $host,
    $username,
    $password,
    $dbname
);

if (!$result['success']) {

    logSystemAudit(
        $pdo,
        'Scheduled Database Backup',
        'Scheduled database backup failed.'
    );

    echo "Scheduled database backup failed." . PHP_EOL;
    exit(1);
}

logSystemAudit(
    $pdo,
    'Scheduled Database Backup',
    "Generated scheduled backup: {$result['file_name']}"
);

if ($result['deleted_backup_count'] > 0) {

    logSystemAudit(
        $pdo,
        'Scheduled Database Backup',
        "Removed {$result['deleted_backup_count']} expired backup(s) based on the {$result['retention_days']}-day retention policy."
    );
}

echo "Scheduled database backup created: " .
    $result['file_name'] .
    PHP_EOL;
