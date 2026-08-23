<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';

$allowedRoles = [
    ROLE_TEACHER,
];

require_once __DIR__ . '/../../auth/session_guard.php';

function classListImportResponse(
    bool $success,
    string $message,
    int $status = 200,
    string $code = 'OK'
): never {
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'code' => $code,
            'data' => [],
        ],
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    classListImportResponse(
        false,
        'Invalid Class List import request.',
        405,
        'METHOD_NOT_ALLOWED'
    );
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    classListImportResponse(
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
    classListImportResponse(
        false,
        'A valid Operational Class is required.',
        422,
        'INVALID_OPERATIONAL_CLASS'
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
        (int) $_SESSION['user_id'],
    ]);

    if ($ownerStmt->fetchColumn() === false) {
        classListImportResponse(
            false,
            'The selected operational class is unavailable or you do not have permission to manage it.',
            403,
            'CLASS_ACCESS_DENIED'
        );
    }
} catch (PDOException $e) {
    error_log(
        '[APRISM Import Class List Ownership] ' .
        $e->getMessage()
    );

    classListImportResponse(
        false,
        'The Class List request could not be checked. Please try again.',
        500,
        'DATABASE_ERROR'
    );
}

classListImportResponse(
    false,
    'Class List confirmation remains unavailable until an official roster source is reviewed and its field mapping is approved. No Student or enrollment records were changed.',
    409,
    'SOURCE_MAPPING_REQUIRED'
);