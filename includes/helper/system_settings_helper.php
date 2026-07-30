<?php

function getSystemSetting(PDO $pdo, string $settingKey): ?string
{
    $sql = "
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$settingKey]);

    $value = $stmt->fetchColumn();

    if ($value === false) {
        return null;
    }

    return (string) $value;
}

function setSystemSetting(
    PDO $pdo,
    string $settingKey,
    string $settingValue,
    int $updatedBy
): void {
    $sql = "
        INSERT INTO system_settings (
            setting_key,
            setting_value,
            updated_by
        )
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by)
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $settingKey,
        $settingValue,
        $updatedBy
    ]);
}

function deleteSystemSetting(
    PDO $pdo,
    string $settingKey
): void {
    $sql = "
        DELETE FROM system_settings
        WHERE setting_key = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$settingKey]);
}

function cleanupExpiredBackups(
    string $backupDirectory,
    int $retentionDays
): int {
    if ($retentionDays < 1 || !is_dir($backupDirectory)) {
        return 0;
    }

    $cutoffTime = time() - ($retentionDays * 86400);
    $deletedCount = 0;

    $backupFiles = glob(
        $backupDirectory . DIRECTORY_SEPARATOR . 'aprism_backup_*.sql'
    );

    if ($backupFiles === false) {
        return 0;
    }

    foreach ($backupFiles as $backupFile) {

        if (!is_file($backupFile)) {
            continue;
        }

        $modifiedTime = filemtime($backupFile);

        if (
            $modifiedTime !== false &&
            $modifiedTime < $cutoffTime &&
            unlink($backupFile)
        ) {
            $deletedCount++;
        }
    }

    return $deletedCount;
}