<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| JSON endpoint boundary
|--------------------------------------------------------------------------
|
| This endpoint is consumed by fetch(). Buffer any accidental output from a
| dependency so it cannot corrupt the JSON response. A shutdown handler also
| converts otherwise unhandled fatal errors into a controlled API response.
|
*/

ob_start();

$GLOBALS['aprism_class_list_response_sent'] = false;

function classListParseResponse(
    bool $success,
    string $message,
    int $status = 200,
    string $code = 'OK',
    array $data = []
): never {
    $GLOBALS['aprism_class_list_response_sent'] = true;

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

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

register_shutdown_function(static function (): void {
    if (($GLOBALS['aprism_class_list_response_sent'] ?? false) === true) {
        return;
    }

    $error = error_get_last();

    if (
        $error === null ||
        !in_array(
            $error['type'],
            [
                E_ERROR,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
                E_PARSE,
                E_USER_ERROR,
                E_RECOVERABLE_ERROR,
            ],
            true
        )
    ) {
        return;
    }

    error_log(
        '[APRISM Parse Class List Fatal] ' .
        'type=' . $error['type'] .
        '; message=' . $error['message'] .
        '; file=' . $error['file'] .
        '; line=' . $error['line']
    );

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    echo json_encode(
        [
            'success' => false,
            'message' => 'The Class List request could not be completed. Please try again.',
            'code' => 'UNEXPECTED_SERVER_ERROR',
            'data' => [],
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Dependencies
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/import/class_list_import_engine.php';
require_once __DIR__ . '/../../includes/import/class_list_source_session.php';

$allowedRoles = [
    ROLE_TEACHER,
];

/*
|--------------------------------------------------------------------------
| API-safe session guard mode
|--------------------------------------------------------------------------
|
| The shared guard keeps its existing redirect behavior for normal pages.
| This endpoint explicitly requests JSON errors instead.
|
*/

$apiResponseMode = true;

require_once __DIR__ . '/../../auth/session_guard.php';

/*
|--------------------------------------------------------------------------
| Request validation
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    classListParseResponse(
        false,
        'Invalid Class List parse request.',
        405,
        'METHOD_NOT_ALLOWED'
    );
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    classListParseResponse(
        false,
        'The request could not be verified. Refresh the page and try again.',
        419,
        'CSRF_VALIDATION_FAILED'
    );
}

$operationalClassId = filter_var(
    $_POST['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($operationalClassId === false) {
    classListParseResponse(
        false,
        'A valid Operational Class is required.',
        422,
        'INVALID_OPERATIONAL_CLASS'
    );
}

$teacherId = (int) ($_SESSION['user_id'] ?? 0);

if ($teacherId < 1) {
    classListParseResponse(
        false,
        'Your session has expired. Please log in again.',
        401,
        'SESSION_EXPIRED'
    );
}

try {
    $ownerStmt = $pdo->prepare("
        SELECT oc.operational_class_id
        FROM operational_classes AS oc
        INNER JOIN school_years AS active_sy
            ON active_sy.school_year = oc.school_year
           AND active_sy.status = 'Active'
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
    ");

    $ownerStmt->execute([
        (int) $operationalClassId,
        $teacherId,
    ]);

    if ($ownerStmt->fetchColumn() === false) {
        classListParseResponse(
            false,
            'The selected operational class is unavailable or you do not have permission to manage it.',
            403,
            'CLASS_ACCESS_DENIED'
        );
    }
} catch (Throwable $e) {
    error_log(
        '[APRISM Parse Class List Ownership] ' .
        'type=' . $e::class .
        '; message=' . $e->getMessage()
    );

    classListParseResponse(
        false,
        'The Class List request could not be checked. Please try again.',
        500,
        'DATABASE_ERROR'
    );
}

$operation = trim((string) ($_POST['operation'] ?? 'upload'));

if (!in_array($operation, ['upload', 'preview'], true)) {
    classListParseResponse(
        false,
        'Invalid Class List source operation.',
        422,
        'INVALID_SOURCE_OPERATION'
    );
}

try {
    $sourceSession = new ClassListSourceSession();
} catch (Throwable $e) {
    error_log(
        '[APRISM Parse Class List Source Session] ' .
        'type=' . $e::class .
        '; message=' . $e->getMessage()
    );

    classListParseResponse(
        false,
        'Temporary Class List source storage is unavailable. Please try again.',
        500,
        'SOURCE_SESSION_UNAVAILABLE'
    );
}

/*
|--------------------------------------------------------------------------
| Re-read a staged source after structure changes
|--------------------------------------------------------------------------
*/

if ($operation === 'preview') {
    $token = trim((string) ($_POST['source_token'] ?? ''));

    if ($token === '') {
        classListParseResponse(
            false,
            'The temporary source has expired. Upload the file again.',
            422,
            'SOURCE_TOKEN_REQUIRED'
        );
    }

    $worksheetName = trim((string) ($_POST['worksheet_name'] ?? ''));

    $headerRowNumber = filter_var(
        $_POST['header_row_number'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    $firstDataRowNumber = filter_var(
        $_POST['first_data_row_number'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );

    try {
        $source = $sourceSession->get(
            $token,
            $teacherId,
            (int) $operationalClassId
        );

        $engine = new ClassListImportEngine();

        $data = $engine->inspect(
            (string) $source['path'],
            (string) $source['original_name'],
            (string) $source['extension'],
            $worksheetName !== '' ? $worksheetName : null,
            $headerRowNumber !== false ? $headerRowNumber : null,
            $firstDataRowNumber !== false ? $firstDataRowNumber : null
        );

        $data['source_token'] = $token;

        classListParseResponse(
            true,
            'Source structure updated.',
            200,
            'SOURCE_STRUCTURE_READY',
            $data
        );
    } catch (Throwable $e) {
        error_log(
            '[APRISM Class List Source Preview] ' .
            'type=' . $e::class .
            '; message=' . $e->getMessage()
        );

        classListParseResponse(
            false,
            'The temporary source is unavailable or could not be read. Upload the file again.',
            422,
            'SOURCE_SESSION_UNAVAILABLE'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Upload and extract a new temporary source
|--------------------------------------------------------------------------
*/

if (!isset($_FILES['class_list_file'])) {
    classListParseResponse(
        false,
        'No Class List file was received.',
        422,
        'FILE_REQUIRED'
    );
}

$file = $_FILES['class_list_file'];

if (
    !isset($file['error'], $file['tmp_name']) ||
    (int) $file['error'] !== UPLOAD_ERR_OK ||
    !is_uploaded_file((string) $file['tmp_name'])
) {
    classListParseResponse(
        false,
        'The uploaded Class List file could not be verified.',
        422,
        'INVALID_UPLOAD'
    );
}

$extension = strtolower(
    pathinfo(
        (string) ($file['name'] ?? ''),
        PATHINFO_EXTENSION
    )
);

if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
    classListParseResponse(
        false,
        'Use an Excel or CSV Class List file.',
        422,
        'UNSUPPORTED_FILE_TYPE'
    );
}

$fileSize = (int) ($file['size'] ?? 0);

if ($fileSize < 1) {
    classListParseResponse(
        false,
        'The Class List file is empty.',
        422,
        'EMPTY_FILE'
    );
}

if ($fileSize > 10 * 1024 * 1024) {
    classListParseResponse(
        false,
        'The Class List file must be 10 MB or smaller.',
        422,
        'FILE_TOO_LARGE'
    );
}

$temporaryFile = (string) $file['tmp_name'];

if (!is_readable($temporaryFile)) {
    error_log(
        '[APRISM Class List Extract] Uploaded temporary file is not readable.'
    );

    classListParseResponse(
        false,
        'The uploaded Class List file could not be read. Please upload it again.',
        422,
        'TEMP_FILE_UNREADABLE'
    );
}

try {
    $stored = $sourceSession->storeUploadedFile(
        $file,
        $teacherId,
        (int) $operationalClassId,
        $extension
    );

    $source = $sourceSession->get(
        (string) $stored['token'],
        $teacherId,
        (int) $operationalClassId
    );

    $engine = new ClassListImportEngine();

    $data = $engine->inspect(
        (string) $source['path'],
        (string) $source['original_name'],
        (string) $source['extension']
    );

    $data['source_token'] = (string) $stored['token'];
    $data['source_expires_at'] = (int) $stored['expires_at'];

    classListParseResponse(
        true,
        'Source extracted. Confirm the source structure before mapping columns.',
        200,
        'SOURCE_EXTRACTED',
        $data
    );
} catch (Throwable $e) {
    error_log(
        '[APRISM Class List Extract] ' .
        'type=' . $e::class .
        '; message=' . $e->getMessage() .
        '; extension=' . $extension .
        '; size=' . $fileSize
    );

    classListParseResponse(
        false,
        'The Class List source could not be extracted. Please try another valid Excel or CSV file.',
        422,
        'SOURCE_EXTRACTION_FAILED'
    );
}