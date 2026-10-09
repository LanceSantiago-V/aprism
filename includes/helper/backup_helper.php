<?php

require_once __DIR__ . '/system_settings_helper.php';

function createDatabaseBackup(
    PDO $pdo,
    string $host,
    string $username,
    string $password,
    string $databaseName,
    bool $forceNativeExport = false
): array {

    $timestamp = date('Ymd_His');
    $uniqueSuffix = bin2hex(random_bytes(4));

    $fileName = "aprism_backup_{$timestamp}_{$uniqueSuffix}.sql";

    $filePath =
        BACKUP_DIRECTORY .
        DIRECTORY_SEPARATOR .
        $fileName;

    $temporaryFilePath = $filePath . '.part';

    if (!is_dir(BACKUP_DIRECTORY) && !mkdir(BACKUP_DIRECTORY, 0700, true) && !is_dir(BACKUP_DIRECTORY)) {
        return createDatabaseBackupFailure('The backup directory is unavailable.');
    }

    if (!is_writable(BACKUP_DIRECTORY)) {
        return createDatabaseBackupFailure('The backup directory is not writable.');
    }

    try {
        if (
            !$forceNativeExport &&
            aprismExternalDumpIsUsable(MYSQLDUMP_PATH)
        ) {
            aprismCreateExternalDump(
                $temporaryFilePath,
                $host,
                $username,
                $password,
                $databaseName
            );
        } else {
            aprismCreateNativeDump(
                $pdo,
                $databaseName,
                $temporaryFilePath
            );
        }

        if (!aprismBackupFileIsValid($temporaryFilePath)) {
            throw new RuntimeException(
                'The database backup did not produce a valid SQL file.'
            );
        }

        if (!@rename($temporaryFilePath, $filePath)) {
            throw new RuntimeException(
                'The completed backup could not be stored.'
            );
        }
    } catch (Throwable $exception) {
        if (is_file($temporaryFilePath)) {
            @unlink($temporaryFilePath);
        }

        error_log('[APRISM Backup] ' . $exception->getMessage());

        return createDatabaseBackupFailure(
            aprismBackupUserMessage($exception->getMessage())
        );
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
        'retention_days' => $retentionDays,
        'error_message' => null
    ];
}

function aprismExternalDumpIsUsable(string $dumpPath): bool
{
    if ($dumpPath === '' || !function_exists('exec')) {
        return false;
    }

    $hasDirectoryComponent = strpbrk($dumpPath, '/\\') !== false;

    if (!$hasDirectoryComponent) {
        return true;
    }

    if (!is_file($dumpPath)) {
        return false;
    }

    return DIRECTORY_SEPARATOR === '\\' || is_executable($dumpPath);
}

function aprismCreateExternalDump(
    string $temporaryFilePath,
    string $host,
    string $username,
    string $password,
    string $databaseName
): void {
    $dumpCommand = escapeshellarg(MYSQLDUMP_PATH);
    $dbHost = escapeshellarg($host);
    $dbUsername = escapeshellarg($username);
    $dbPassword = escapeshellarg($password);
    $dbName = escapeshellarg($databaseName);
    $outputFile = escapeshellarg($temporaryFilePath);

    $command =
        "{$dumpCommand} " .
        "--host={$dbHost} " .
        "--user={$dbUsername} ";

    if ($password !== '') {
        $command .= "--password={$dbPassword} ";
    }

    $command .= "{$dbName} > {$outputFile} 2>&1";

    $commandOutput = [];
    $resultCode = 1;

    exec($command, $commandOutput, $resultCode);

    if ($resultCode !== 0) {
        throw new RuntimeException('The database dump process failed.');
    }
}

function aprismCreateNativeDump(
    PDO $pdo,
    string $databaseName,
    string $temporaryFilePath
): void {
    $handle = @fopen($temporaryFilePath, 'wb');

    if ($handle === false) {
        throw new RuntimeException(
            'The temporary backup file could not be opened.'
        );
    }

    try {
        aprismWriteBackupLine($handle, '-- APRISM PHP-native SQL export');
        aprismWriteBackupLine($handle, '-- Generated: ' . date('c'));
        aprismWriteBackupLine($handle, 'SET NAMES utf8mb4;');
        aprismWriteBackupLine($handle, 'SET FOREIGN_KEY_CHECKS=0;');
        aprismWriteBackupLine($handle, '');

        $tableStatement = $pdo->query('SHOW FULL TABLES');

        while ($tableRow = $tableStatement->fetch(PDO::FETCH_NUM)) {
            $tableName = (string) ($tableRow[0] ?? '');
            $tableType = strtoupper((string) ($tableRow[1] ?? ''));

            if ($tableName === '') {
                continue;
            }

            if ($tableType !== 'BASE TABLE') {
                throw new RuntimeException(
                    'The PHP-native exporter cannot safely include database views.'
                );
            }

            $quotedTable = aprismQuoteIdentifier($tableName);
            $createStatement = $pdo->query(
                'SHOW CREATE TABLE ' . $quotedTable
            )->fetch(PDO::FETCH_ASSOC);

            $createSql = (string) (
                $createStatement['Create Table'] ??
                $createStatement['Create View'] ??
                ''
            );

            if ($createSql === '') {
                throw new RuntimeException(
                    'The PHP-native exporter could not read a table definition.'
                );
            }

            aprismWriteBackupLine(
                $handle,
                'DROP TABLE IF EXISTS ' . $quotedTable . ';'
            );
            aprismWriteBackupLine($handle, $createSql . ';');

            $columnTypes = aprismColumnTypes(
                $pdo,
                $databaseName,
                $tableName
            );

            $dataStatement = $pdo->query(
                'SELECT * FROM ' . $quotedTable
            );

            while ($row = $dataStatement->fetch(PDO::FETCH_ASSOC)) {
                $columns = [];
                $values = [];

                foreach ($row as $columnName => $value) {
                    $columns[] = aprismQuoteIdentifier((string) $columnName);
                    $values[] = aprismQuoteValue(
                        $pdo,
                        $value,
                        $columnTypes[$columnName] ?? ''
                    );
                }

                aprismWriteBackupLine(
                    $handle,
                    'INSERT INTO ' . $quotedTable .
                    ' (' . implode(', ', $columns) . ') VALUES (' .
                    implode(', ', $values) . ');'
                );
            }

            aprismWriteBackupLine($handle, '');
        }

        aprismWriteBackupLine($handle, 'SET FOREIGN_KEY_CHECKS=1;');
        aprismWriteBackupLine($handle, '-- APRISM BACKUP COMPLETE');
    } finally {
        if (!fclose($handle)) {
            throw new RuntimeException(
                'The completed backup file could not be closed.'
            );
        }
    }
}

function aprismColumnTypes(
    PDO $pdo,
    string $databaseName,
    string $tableName
): array {
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table
         ORDER BY ORDINAL_POSITION'
    );
    $statement->execute([
        ':schema' => $databaseName,
        ':table' => $tableName
    ]);

    $types = [];

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $types[(string) $row['COLUMN_NAME']] =
            strtolower((string) $row['DATA_TYPE']);
    }

    return $types;
}

function aprismQuoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function aprismQuoteValue(
    PDO $pdo,
    mixed $value,
    string $dataType
): string {
    if ($value === null) {
        return 'NULL';
    }

    $binaryTypes = [
        'binary',
        'varbinary',
        'tinyblob',
        'blob',
        'mediumblob',
        'longblob',
        'bit'
    ];

    if (in_array(strtolower($dataType), $binaryTypes, true)) {
        return "X'" . bin2hex((string) $value) . "'";
    }

    $numericTypes = [
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'integer',
        'bigint',
        'decimal',
        'numeric',
        'float',
        'double',
        'real'
    ];

    if (in_array(strtolower($dataType), $numericTypes, true) && is_numeric($value)) {
        return (string) $value;
    }

    return $pdo->quote((string) $value);
}

function aprismWriteBackupLine($handle, string $line): void
{
    $contents = $line . PHP_EOL;
    $length = strlen($contents);
    $offset = 0;

    while ($offset < $length) {
        $written = fwrite($handle, substr($contents, $offset));

        if ($written === false || $written === 0) {
            throw new RuntimeException(
                'The SQL backup could not be written completely.'
            );
        }

        $offset += $written;
    }
}

function aprismBackupFileIsValid(string $filePath): bool
{
    if (!is_file($filePath) || filesize($filePath) <= 0) {
        return false;
    }

    $prefix = (string) file_get_contents($filePath, false, null, 0, 8192);

    return str_contains($prefix, 'CREATE TABLE');
}

function aprismBackupUserMessage(string $message): string
{
    if (str_contains($message, 'cannot safely include database views')) {
        return 'The backup contains database views that this server cannot export safely.';
    }

    if (
        str_contains($message, 'could not be written') ||
        str_contains($message, 'temporary backup file')
    ) {
        return 'The backup could not be written to server storage.';
    }

    if (str_contains($message, 'dump process failed')) {
        return 'The database dump process failed and the backup was not created.';
    }

    return $message !== ''
        ? $message
        : 'The database backup could not be created.';
}

function createDatabaseBackupFailure(string $message): array
{
    error_log('[APRISM Backup] ' . $message);

    return [
        'success' => false,
        'file_name' => null,
        'deleted_backup_count' => 0,
        'retention_days' => null,
        'error_message' => $message
    ];
}
