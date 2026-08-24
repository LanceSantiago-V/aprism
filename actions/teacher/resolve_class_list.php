<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/import/class_list_source_session.php';
require_once __DIR__ . '/../../includes/import/class_list_resolution_engine.php';

$allowedRoles = [
    ROLE_TEACHER,
];

$apiResponseMode = true;

require_once __DIR__ . '/../../auth/session_guard.php';

function classListResolutionResponse(
    bool $success,
    string $message,
    int $status = 200,
    string $code = 'OK',
    array $data = []
): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'code' => $code,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

/**
 * @return array<int, array{first_name: string, middle_name: string, last_name: string, suffix: string}>
 */
function classListIdentityOverrides(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (!is_string($value)) {
        classListResolutionResponse(
            false,
            'The New Student identity details are invalid.',
            422,
            'INVALID_IDENTITY_COMPLETION'
        );
    }

    try {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        classListResolutionResponse(
            false,
            'The New Student identity details are invalid.',
            422,
            'INVALID_IDENTITY_COMPLETION'
        );
    }

    if (!is_array($decoded) || count($decoded) > 500) {
        classListResolutionResponse(
            false,
            'The New Student identity details are invalid.',
            422,
            'INVALID_IDENTITY_COMPLETION'
        );
    }

    $allowedFields = [
        'first_name' => 100,
        'middle_name' => 100,
        'last_name' => 100,
        'suffix' => 30,
    ];
    $overrides = [];

    foreach ($decoded as $sourceRow => $identity) {
        $rowNumber = filter_var(
            $sourceRow,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($rowNumber === false || !is_array($identity)) {
            classListResolutionResponse(
                false,
                'The New Student identity details are invalid.',
                422,
                'INVALID_IDENTITY_COMPLETION'
            );
        }

        if (array_diff(array_keys($identity), array_keys($allowedFields)) !== []) {
            classListResolutionResponse(
                false,
                'The New Student identity details are invalid.',
                422,
                'INVALID_IDENTITY_COMPLETION'
            );
        }

        $normalized = [];

        foreach ($allowedFields as $field => $maximumLength) {
            $raw = $identity[$field] ?? '';

            if (!is_string($raw)) {
                classListResolutionResponse(
                    false,
                    'The New Student identity details are invalid.',
                    422,
                    'INVALID_IDENTITY_COMPLETION'
                );
            }

            $text = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

            if (strlen($text) > $maximumLength) {
                classListResolutionResponse(
                    false,
                    sprintf('%s is too long.', ucwords(str_replace('_', ' ', $field))),
                    422,
                    'INVALID_IDENTITY_COMPLETION'
                );
            }

            $normalized[$field] = $text;
        }

        $overrides[(int) $rowNumber] = $normalized;
    }

    return $overrides;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    classListResolutionResponse(
        false,
        'Invalid Class List resolution request.',
        405,
        'METHOD_NOT_ALLOWED'
    );
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    classListResolutionResponse(
        false,
        'The request could not be verified. Refresh the page and try again.',
        419,
        'CSRF_VALIDATION_FAILED'
    );
}

$operationalClassId = filter_var(
    $_POST['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($operationalClassId === false) {
    classListResolutionResponse(
        false,
        'A valid Operational Class is required.',
        422,
        'INVALID_OPERATIONAL_CLASS'
    );
}

$sourceToken = trim((string) ($_POST['source_token'] ?? ''));

if ($sourceToken === '') {
    classListResolutionResponse(
        false,
        'The uploaded Class List source is unavailable. Upload the file again.',
        422,
        'SOURCE_TOKEN_REQUIRED'
    );
}

$worksheetName = trim((string) ($_POST['worksheet_name'] ?? ''));

if ($worksheetName === '') {
    classListResolutionResponse(
        false,
        'Select a worksheet before checking Resolution Preview.',
        422,
        'WORKSHEET_REQUIRED'
    );
}

$headerRow = filter_var(
    $_POST['header_row_number'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$firstDataRow = filter_var(
    $_POST['first_data_row_number'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 2]]
);

if (
    $headerRow === false
    || $firstDataRow === false
    || $firstDataRow <= $headerRow
) {
    classListResolutionResponse(
        false,
        'Confirm a valid Header Row and First Student Row.',
        422,
        'INVALID_SOURCE_STRUCTURE'
    );
}

$allowedMappingFields = [
    'student_number',
    'student_name_raw',
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'program',
    'section',
    'year_level',
];

$receivedMapping = $_POST['mapping'] ?? [];

if (!is_array($receivedMapping)) {
    classListResolutionResponse(
        false,
        'The Class List column mapping is invalid.',
        422,
        'INVALID_MAPPING'
    );
}

$mapping = [];
$usedColumns = [];

foreach ($allowedMappingFields as $field) {
    $value = filter_var(
        $receivedMapping[$field] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $mapping[$field] = $value === false ? null : (int) $value;

    if ($mapping[$field] !== null) {
        if (isset($usedColumns[$mapping[$field]])) {
            classListResolutionResponse(
                false,
                'A source column can only be mapped to one APRISM field.',
                422,
                'DUPLICATE_SOURCE_COLUMN'
            );
        }

        $usedColumns[$mapping[$field]] = true;
    }
}

if ($mapping['student_number'] === null) {
    classListResolutionResponse(
        false,
        'Student Number must be mapped before Resolution Preview.',
        422,
        'STUDENT_NUMBER_MAPPING_REQUIRED'
    );
}

if (
    $mapping['student_name_raw'] === null
    && ($mapping['first_name'] === null || $mapping['last_name'] === null)
) {
    classListResolutionResponse(
        false,
        'Map Student Name (combined), or both First Name and Last Name.',
        422,
        'STUDENT_NAME_MAPPING_REQUIRED'
    );
}

$identityOverrides = classListIdentityOverrides(
    $_POST['identity_overrides_json'] ?? null
);

$teacherId = (int) $_SESSION['user_id'];
$tokenFingerprint = substr(hash('sha256', $sourceToken), 0, 16);

try {
    $classStmt = $pdo->prepare("
        SELECT
            oc.operational_class_id,
            oc.school_year,
            oc.semester,
            oc.section_id,
            sy.school_year_id,
            sec.section_name,
            sec.year_level AS section_year_level,
            p.program_id,
            p.program_code,
            p.program_name,
            p.academic_level
        FROM operational_classes AS oc
        INNER JOIN school_years AS sy
            ON sy.school_year = oc.school_year
           AND sy.status = 'Active'
        INNER JOIN sections AS sec
            ON sec.section_id = oc.section_id
        LEFT JOIN programs AS p
            ON p.program_id = sec.program_id
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
    ");

    $classStmt->execute([
        (int) $operationalClassId,
        $teacherId,
    ]);

    $classContext = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    error_log(
        '[APRISM Class List Resolution Preview] phase=class_context; '
        . 'type=' . $e::class
        . '; message=' . $e->getMessage()
        . '; teacher_id=' . $teacherId
        . '; operational_class_id=' . (int) $operationalClassId
    );

    classListResolutionResponse(
        false,
        'The Class List context could not be checked. Please try again.',
        500,
        'DATABASE_ERROR'
    );
}

if ($classContext === null) {
    classListResolutionResponse(
        false,
        'The selected operational class is unavailable or you do not have permission to manage it.',
        403,
        'CLASS_ACCESS_DENIED'
    );
}

try {
    $sourceSession = new ClassListSourceSession();

    $source = $sourceSession->get(
        $sourceToken,
        $teacherId,
        (int) $operationalClassId
    );
} catch (Throwable $e) {
    error_log(
        '[APRISM Class List Resolution Preview] phase=source_session; '
        . 'type=' . $e::class
        . '; message=' . $e->getMessage()
        . '; token_fingerprint=' . $tokenFingerprint
        . '; teacher_id=' . $teacherId
        . '; operational_class_id=' . (int) $operationalClassId
    );

    classListResolutionResponse(
        false,
        'The temporary Class List source is unavailable. Upload the file again.',
        422,
        'SOURCE_SESSION_UNAVAILABLE'
    );
}

try {
    $engine = new ClassListResolutionEngine();

    $data = $engine->preview(
        $pdo,
        $source,
        $classContext,
        $worksheetName,
        (int) $headerRow,
        (int) $firstDataRow,
        $mapping,
        $identityOverrides
    );
} catch (Throwable $e) {
    error_log(
        '[APRISM Class List Resolution Preview] phase=engine; '
        . 'type=' . $e::class
        . '; message=' . $e->getMessage()
        . '; token_fingerprint=' . $tokenFingerprint
        . '; teacher_id=' . $teacherId
        . '; operational_class_id=' . (int) $operationalClassId
    );

    classListResolutionResponse(
        false,
        'The Resolution Preview could not be prepared. Check the source structure and mapping, then try again.',
        500,
        'RESOLUTION_PREVIEW_FAILED'
    );
}

classListResolutionResponse(
    true,
    'Resolution Preview is ready. No Student or enrollment records were changed.',
    200,
    'RESOLUTION_PREVIEW_READY',
    $data
);