<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/import/class_list_import_engine.php';
require_once __DIR__ . '/../../includes/import/class_list_source_session.php';

$allowedRoles = [
    ROLE_TEACHER,
];

require_once __DIR__ . '/../../auth/session_guard.php';

function classListParseResponse(
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    classListParseResponse(false, 'Invalid Class List parse request.', 405, 'METHOD_NOT_ALLOWED');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    classListParseResponse(false, 'The request could not be verified. Refresh the page and try again.', 419, 'CSRF_VALIDATION_FAILED');
}

$operationalClassId = filter_var(
    $_POST['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($operationalClassId === false) {
    classListParseResponse(false, 'A valid Operational Class is required.', 422, 'INVALID_OPERATIONAL_CLASS');
}

try {
    $ownerStmt = $pdo->prepare("\n        SELECT oc.operational_class_id\n        FROM operational_classes AS oc\n        INNER JOIN school_years AS active_sy\n            ON active_sy.school_year = oc.school_year\n           AND active_sy.status = 'Active'\n        WHERE oc.operational_class_id = ?\n          AND oc.teacher_id = ?\n          AND oc.status = 'Active'\n        LIMIT 1\n    ");
    $ownerStmt->execute([(int) $operationalClassId, (int) $_SESSION['user_id']]);

    if ($ownerStmt->fetchColumn() === false) {
        classListParseResponse(false, 'The selected operational class is unavailable or you do not have permission to manage it.', 403, 'CLASS_ACCESS_DENIED');
    }
} catch (PDOException $e) {
    error_log('[APRISM Parse Class List Ownership] ' . $e->getMessage());
    classListParseResponse(false, 'The Class List request could not be checked. Please try again.', 500, 'DATABASE_ERROR');
}

$operation = $_POST['operation'] ?? 'upload';
if (!in_array($operation, ['upload', 'preview'], true)) {
    classListParseResponse(false, 'Invalid Class List source operation.', 422, 'INVALID_SOURCE_OPERATION');
}
if ($operation === 'upload' && !isset($_FILES['class_list_file'])) {
    classListParseResponse(false, 'No Class List file was received.', 422, 'FILE_REQUIRED');
}

$teacherId = (int) $_SESSION['user_id'];
$sourceSession = new ClassListSourceSession();

if ($operation === 'preview') {
    $token = trim((string) ($_POST['source_token'] ?? ''));
    try {
        $source = $sourceSession->get($token, $teacherId, (int) $operationalClassId);
        $engine = new ClassListImportEngine();
        $data = $engine->inspect(
            (string) $source['path'],
            (string) $source['original_name'],
            (string) $source['extension'],
            trim((string) ($_POST['worksheet_name'] ?? '')) ?: null,
            filter_var($_POST['header_row_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            filter_var($_POST['first_data_row_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null
        );
        $data['source_token'] = $token;
        classListParseResponse(true, 'Source structure updated.', 200, 'SOURCE_STRUCTURE_READY', $data);
    } catch (Throwable $e) {
        error_log('[APRISM Class List Source Preview] type=' . $e::class . '; message=' . $e->getMessage());
        classListParseResponse(false, 'The temporary source is unavailable. Upload the file again.', 422, 'SOURCE_SESSION_UNAVAILABLE');
    }
}

$file = $_FILES['class_list_file'];

if (
    !isset($file['error'], $file['tmp_name']) ||
    (int) $file['error'] !== UPLOAD_ERR_OK ||
    !is_uploaded_file((string) $file['tmp_name'])
) {
    classListParseResponse(false, 'The uploaded Class List file could not be verified.', 422, 'INVALID_UPLOAD');
}

$extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
    classListParseResponse(false, 'Use an Excel or CSV Class List file.', 422, 'UNSUPPORTED_FILE_TYPE');
}

if ((int) ($file['size'] ?? 0) < 1) {
    classListParseResponse(false, 'The Class List file is empty.', 422, 'EMPTY_FILE');
}

if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
    classListParseResponse(false, 'The Class List file must be 10 MB or smaller.', 422, 'FILE_TOO_LARGE');
}

$temporaryFile = (string) $file['tmp_name'];

if (!is_readable($temporaryFile)) {
    error_log('[APRISM Class List Extract] Uploaded temporary file is not readable.');
    classListParseResponse(false, 'The uploaded Class List file could not be read. Please upload it again.', 422, 'TEMP_FILE_UNREADABLE');
}

try {
    $stored = $sourceSession->storeUploadedFile($file, $teacherId, (int) $operationalClassId, $extension);
    $source = $sourceSession->get($stored['token'], $teacherId, (int) $operationalClassId);
    $engine = new ClassListImportEngine();
    $data = $engine->inspect((string) $source['path'], (string) $source['original_name'], (string) $source['extension']);
    $data['source_token'] = $stored['token'];
} catch (Throwable $e) {
    error_log(
        '[APRISM Class List Extract] ' .
        'type=' . $e::class .
        '; message=' . $e->getMessage() .
        '; extension=' . $extension .
        '; size=' . (int) ($file['size'] ?? 0)
    );

    classListParseResponse(
        false,
        'The Class List source could not be extracted. Please try another valid Excel or CSV file.',
        422,
        'SOURCE_EXTRACTION_FAILED'
    );
}

classListParseResponse(
    true,
    'Source extracted. Map the detected columns to continue.',
    200,
    'SOURCE_EXTRACTED',
    $data
);