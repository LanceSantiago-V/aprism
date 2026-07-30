<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function logAudit(
    PDO $pdo,
    string $action,
    string $description
): bool {

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $userId = (int) $_SESSION['user_id'];

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    try {

        $sql = "
            INSERT INTO audit_logs
            (
                user_id,
                action,
                description,
                ip_address
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $userId,
            $action,
            $description,
            $ipAddress
        ]);

    } catch (PDOException $e) {

        error_log(
            '[APRISM Audit] ' .
            $e->getMessage()
        );

        return false;
    }

}

function logSystemAudit(
    PDO $pdo,
    string $action,
    string $description
): bool {

    try {

        $sql = "
            INSERT INTO audit_logs
            (
                user_id,
                action,
                description,
                ip_address
            )
            VALUES
            (
                NULL,
                ?,
                ?,
                NULL
            )
        ";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $action,
            $description
        ]);

    } catch (PDOException $e) {

        error_log(
            '[APRISM System Audit] ' .
            $e->getMessage()
        );

        return false;
    }

}

function hasScheduledBackupRun(
    PDO $pdo,
    string $scheduledDate,
    string $scheduledTime
): bool {

    try {

        $scheduledStart =
            $scheduledDate . ' ' . $scheduledTime . ':00';

        $scheduledEnd = date(
            'Y-m-d H:i:s',
            strtotime($scheduledStart . ' +1 minute')
        );

        $sql = "
            SELECT 1
            FROM audit_logs
            WHERE user_id IS NULL
              AND action = 'Scheduled Database Backup'
              AND description LIKE 'Generated scheduled backup:%'
              AND created_at >= ?
              AND created_at < ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $scheduledStart,
            $scheduledEnd
        ]);

        return $stmt->fetchColumn() !== false;

    } catch (PDOException $e) {

        error_log(
            '[APRISM Scheduled Backup Check] ' .
            $e->getMessage()
        );

        return false;
    }
}