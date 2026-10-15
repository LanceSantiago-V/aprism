<?php

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/flash_message.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/backup_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_message'] = 'The backup request could not be verified.';
    header('Location: ' . APP_URL . '/dashboard/technical_admin_backups.php');
    exit;
}

$backupResult = createDatabaseBackup(
    $pdo,
    $host,
    $username,
    $password,
    $dbname
);

if (!$backupResult['success']) {

    $errorMessage = (string) ($backupResult['error_message'] ?? 'Database backup could not be created.');

    logAudit(
        $pdo,
        'Database Backup',
        'Backup creation failed: ' . $errorMessage
    );

    $_SESSION['error_message'] = $errorMessage;

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_backups.php'
    );

    exit;
}

$fileName = $backupResult['file_name'];

$_SESSION['created_backup_focus_file'] = $fileName;

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
    'Backup created successfully.';

$requestedPerPage = filter_input(INPUT_POST, 'per_page', FILTER_VALIDATE_INT);
$returnPerPage = $requestedPerPage && $requestedPerPage > 0
    ? min($requestedPerPage, 100)
    : 6;

header(
    'Location: ' . APP_URL . '/dashboard/technical_admin_backups.php?' .
    http_build_query(['per_page' => $returnPerPage])
);

exit;
