<?php

declare(strict_types=1);

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [ROLE_TECHNICAL_ADMINISTRATOR];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

if (!isset($_GET['file'])) {
    aprismRenderFailure(404, 'The requested backup is unavailable.');
}

$file = basename(trim((string) $_GET['file']));

if ($file === '' || strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) !== 'sql') {
    aprismRenderFailure(404, 'The requested backup is unavailable.');
}

$filePath = BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . $file;

if (!is_file($filePath) || !is_readable($filePath)) {
    aprismRenderFailure(404, 'The requested backup is unavailable.');
}

logAudit($pdo, 'Database Backup Download', "Downloaded backup: {$file}");

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . (string) filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

readfile($filePath);
exit;
