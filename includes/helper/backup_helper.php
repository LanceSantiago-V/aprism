<?php

require_once __DIR__ . '/system_settings_helper.php';

function createDatabaseBackup(
    PDO $pdo,
    string $host,
    string $username,
    string $password,
    string $databaseName
): array {

    $timestamp = date('Ymd_His');

    $fileName = "aprism_backup_{$timestamp}.sql";

    $filePath =
        BACKUP_DIRECTORY .
        DIRECTORY_SEPARATOR .
        $fileName;

    $dumpCommand = escapeshellarg(MYSQLDUMP_PATH);
    $dbHost = escapeshellarg($host);
    $dbUsername = escapeshellarg($username);
    $dbPassword = escapeshellarg($password);
    $dbName = escapeshellarg($databaseName);
    $outputFile = escapeshellarg($filePath);

    $command =
        "{$dumpCommand} " .
        "--host={$dbHost} " .
        "--user={$dbUsername} ";

    if ($password !== '') {
        $command .= "--password={$dbPassword} ";
    }

    $command .=
        "{$dbName} > {$outputFile} 2>&1";

    $commandOutput = [];
    $resultCode = 1;

    exec(
        $command,
        $commandOutput,
        $resultCode
    );

    if (
        $resultCode !== 0 ||
        !file_exists($filePath) ||
        filesize($filePath) === 0
    ) {

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return [
            'success' => false,
            'file_name' => null,
            'deleted_backup_count' => 0,
            'retention_days' => null
        ];
    }

    $deletedBackupCount = 0;
    $retentionDays = null;

    $retentionSetting = getSystemSetting(
        $pdo,
        'backup_retention_days'
    );

    if ($retentionSetting !== null) {

        $retentionDays = (int) $retentionSetting;

        $deletedBackupCount = cleanupExpiredBackups(
            BACKUP_DIRECTORY,
            $retentionDays
        );
    }

    return [
        'success' => true,
        'file_name' => $fileName,
        'deleted_backup_count' => $deletedBackupCount,
        'retention_days' => $retentionDays
    ];
}