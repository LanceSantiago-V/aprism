<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/import/import_engine.php';

$allowedRoles = [ROLE_TEACHER];
$apiResponseMode = true;
require_once __DIR__ . '/../../auth/session_guard.php';

function scheduleImportParseResponse(
    bool $success,
    string $message,
    int $status = 200,
    string $code = 'OK',
    array $data = []
): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    scheduleImportParseResponse(false, 'Invalid schedule import request.', 405, 'METHOD_NOT_ALLOWED');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    scheduleImportParseResponse(
        false,
        'The request could not be verified. Refresh the page and try again.',
        419,
        'CSRF_VALIDATION_FAILED'
    );
}

$file = $_FILES['import_file'] ?? null;
if (!is_array($file)) {
    scheduleImportParseResponse(false, 'Choose an Excel or CSV teaching assignment file.', 422, 'FILE_REQUIRED');
}

if (
    !isset($file['error'], $file['tmp_name']) ||
    (int) $file['error'] !== UPLOAD_ERR_OK ||
    !is_uploaded_file((string) $file['tmp_name'])
) {
    scheduleImportParseResponse(false, 'The uploaded file could not be verified.', 422, 'INVALID_UPLOAD');
}

$extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
$source = trim((string) ($_POST['source'] ?? ''));
$allowedExtensions = match ($source) {
    'excel' => ['xlsx', 'xls'],
    'csv' => ['csv'],
    default => [],
};

if ($allowedExtensions === [] || !in_array($extension, $allowedExtensions, true)) {
    scheduleImportParseResponse(false, 'Use the selected Excel or CSV file format.', 422, 'UNSUPPORTED_FILE_TYPE');
}

$fileSize = (int) ($file['size'] ?? 0);
if ($fileSize < 1) {
    scheduleImportParseResponse(false, 'The uploaded file is empty.', 422, 'EMPTY_FILE');
}
if ($fileSize > 10 * 1024 * 1024) {
    scheduleImportParseResponse(false, 'The file must be 10 MB or smaller.', 422, 'FILE_TOO_LARGE');
}

try {
    $parsed = ImportEngine::parseFile((string) $file['tmp_name'], $extension);
    $rows = $parsed['rows'] ?? [];

    if (!is_array($rows) || $rows === []) {
        $errors = $parsed['errors'] ?? [];
        $message = is_array($errors) && isset($errors[0])
            ? (string) $errors[0]
            : 'No teaching assignment record was found in the file.';
        scheduleImportParseResponse(false, $message, 422, 'NO_SCHEDULE_RECORD');
    }

    if (count($rows) !== 1) {
        scheduleImportParseResponse(
            false,
            'Review one teaching assignment record at a time. This file contains multiple records.',
            422,
            'MULTIPLE_SCHEDULE_RECORDS'
        );
    }

    scheduleImportParseResponse(
        true,
        'Schedule details extracted. Review them before continuing.',
        200,
        'SCHEDULE_RECORD_EXTRACTED',
        ['row' => $rows[0]]
    );
} catch (Throwable $e) {
    error_log(
        '[APRISM Schedule Import Parse] type=' . $e::class .
        '; message=' . $e->getMessage() .
        '; extension=' . $extension .
        '; size=' . $fileSize
    );

    scheduleImportParseResponse(
        false,
        'APRISM could not read the file. Verify that it is a valid Excel or CSV file.',
        422,
        'SCHEDULE_FILE_PARSE_FAILED'
    );
}
