<?php

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

if (!isset($_GET['file'])) {
    http_response_code(404);
    exit('Backup not found.');
}

$file = basename(trim($_GET['file']));

if (
    $file === '' ||
    strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'sql'
) {
    http_response_code(400);
    exit('Invalid backup file.');
}

$filePath =
    BACKUP_DIRECTORY .
    DIRECTORY_SEPARATOR .
    $file;

if (
    !file_exists($filePath) ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit('Backup not found.');
}

logAudit(
    $pdo,
    'Database Backup Download',
    "Downloaded backup: {$file}"
);

header('Content-Type: application/sql');
header(
    'Content-Disposition: attachment; filename="' .
    $file .
    '"'
);
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

readfile($filePath);

exit;