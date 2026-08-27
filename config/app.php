<?php

declare(strict_types=1);

$environmentFile = __DIR__ . '/environment.php';

if (!is_file($environmentFile)) {
    http_response_code(500);
    error_log('[APRISM] Missing config/environment.php.');
    exit('APRISM is temporarily unavailable. Please try again later.');
}

$aprismConfig = require $environmentFile;

if (!is_array($aprismConfig)) {
    http_response_code(500);
    error_log('[APRISM] Invalid config/environment.php.');
    exit('APRISM is temporarily unavailable. Please try again later.');
}

$appUrl = rtrim((string) ($aprismConfig['app_url'] ?? ''), '/');
$storagePath = (string) ($aprismConfig['storage_path'] ?? '');
$errorLogPath = (string) ($aprismConfig['error_log_path'] ?? '');

if ($storagePath === '' || $errorLogPath === '') {
    http_response_code(500);
    error_log('[APRISM] Storage or error-log configuration is missing.');
    exit('APRISM is temporarily unavailable. Please try again later.');
}

if (!is_dir($storagePath) && !mkdir($storagePath, 0700, true) && !is_dir($storagePath)) {
    http_response_code(500);
    error_log('[APRISM] The configured storage directory is unavailable.');
    exit('APRISM is temporarily unavailable. Please try again later.');
}

$logDirectory = dirname($errorLogPath);
if (!is_dir($logDirectory)) {
    @mkdir($logDirectory, 0700, true);
}

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
if (is_dir($logDirectory) && is_writable($logDirectory)) {
    ini_set('error_log', $errorLogPath);
}
error_reporting(E_ALL);

define('APRISM_CONFIG', $aprismConfig);
define('APP_ENV', (string) ($aprismConfig['app_env'] ?? 'production'));
define('APP_URL', $appUrl);
define('APRISM_STORAGE_PATH', rtrim($storagePath, DIRECTORY_SEPARATOR));
define('BACKUP_DIRECTORY', APRISM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'backups');
define('MYSQLDUMP_PATH', (string) ($aprismConfig['mysqldump_path'] ?? ''));
define('APRISM_MAINTENANCE_FLAG', APRISM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'maintenance.flag');

function aprismWantsJson(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

function aprismRenderFailure(int $status, string $message = 'APRISM is temporarily unavailable. Please try again later.'): never
{
    http_response_code($status);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');

    if (aprismWantsJson()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $errorPage = in_array($status, [404, 503], true) ? (string) $status : '500';
    $errorTemplate = __DIR__ . '/../errors/' . $errorPage . '.php';
    if (is_file($errorTemplate)) {
        require $errorTemplate;
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

set_exception_handler(static function (Throwable $exception): void {
    error_log('[APRISM Unhandled Exception] ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    aprismRenderFailure(500);
});

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$maintenanceExemptions = ['/health.php', '/maintenance.php', '/errors/'];
$isMaintenanceExempt = PHP_SAPI === 'cli';
foreach ($maintenanceExemptions as $exemption) {
    if (str_contains($scriptName, $exemption)) {
        $isMaintenanceExempt = true;
        break;
    }
}

if (!$isMaintenanceExempt && is_file(APRISM_MAINTENANCE_FLAG)) {
    aprismRenderFailure(503, 'APRISM is temporarily unavailable while an update is being applied. Please try again shortly.');
}
