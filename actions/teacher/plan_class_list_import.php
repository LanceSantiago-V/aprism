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
require_once __DIR__ . '/../../includes/import/class_list_import_plan_engine.php';
require_once __DIR__ . '/../../includes/import/class_list_persistence_engine.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../../auth/session_guard.php';

function classListPlanResponse(
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

/** @return array<int, array{first_name: string, middle_name: string, last_name: string, suffix: string}> */
function classListPlanIdentityOverrides(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (!is_string($value)) {
        classListPlanResponse(false, 'The New Student identity details are invalid.', 422, 'INVALID_IDENTITY_COMPLETION');
    }

    try {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        classListPlanResponse(false, 'The New Student identity details are invalid.', 422, 'INVALID_IDENTITY_COMPLETION');
    }

    if (!is_array($decoded) || count($decoded) > 500) {
        classListPlanResponse(false, 'The New Student identity details are invalid.', 422, 'INVALID_IDENTITY_COMPLETION');
    }

    $limits = ['first_name' => 100, 'middle_name' => 100, 'last_name' => 100, 'suffix' => 30];
    $overrides = [];

    foreach ($decoded as $sourceRow => $identity) {
        $row = filter_var($sourceRow, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($row === false || !is_array($identity) || array_diff(array_keys($identity), array_keys($limits)) !== []) {
            classListPlanResponse(false, 'The New Student identity details are invalid.', 422, 'INVALID_IDENTITY_COMPLETION');
        }

        $normalized = [];

        foreach ($limits as $field => $limit) {
            $raw = $identity[$field] ?? '';

            if (!is_string($raw)) {
                classListPlanResponse(false, 'The New Student identity details are invalid.', 422, 'INVALID_IDENTITY_COMPLETION');
            }

            $text = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

            if (strlen($text) > $limit) {
                classListPlanResponse(false, sprintf('%s is too long.', ucwords(str_replace('_', ' ', $field))), 422, 'INVALID_IDENTITY_COMPLETION');
            }

            $normalized[$field] = $text;
        }

        $overrides[(int) $row] = $normalized;
    }

    return $overrides;
}

/**
 * Accept only row-keyed, Teacher-reviewed academic-context input. Reference
 * validation happens in the plan engine against current institutional data.
 *
 * @return array<int, array<string, mixed>>
 */
function classListPlanContextDecisions(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (!is_string($value)) {
        classListPlanResponse(false, 'The Academic Enrollment review details are invalid.', 422, 'INVALID_ACADEMIC_CONTEXT_DECISION');
    }

    try {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        classListPlanResponse(false, 'The Academic Enrollment review details are invalid.', 422, 'INVALID_ACADEMIC_CONTEXT_DECISION');
    }

    if (!is_array($decoded) || count($decoded) > 500) {
        classListPlanResponse(false, 'The Academic Enrollment review details are invalid.', 422, 'INVALID_ACADEMIC_CONTEXT_DECISION');
    }

    $allowedFields = ['semester', 'academic_level', 'program_id', 'section_id', 'year_level', 'effective_start'];
    $decisions = [];

    foreach ($decoded as $sourceRow => $decision) {
        $row = filter_var($sourceRow, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($row === false || !is_array($decision) || array_diff(array_keys($decision), $allowedFields) !== []) {
            classListPlanResponse(false, 'The Academic Enrollment review details are invalid.', 422, 'INVALID_ACADEMIC_CONTEXT_DECISION');
        }

        $normalized = [];

        foreach ($allowedFields as $field) {
            $raw = $decision[$field] ?? null;

            if (!is_string($raw) && !is_int($raw) && $raw !== null) {
                classListPlanResponse(false, 'The Academic Enrollment review details are invalid.', 422, 'INVALID_ACADEMIC_CONTEXT_DECISION');
            }

            $normalized[$field] = is_string($raw)
                ? trim(preg_replace('/\s+/', ' ', $raw) ?? $raw)
                : $raw;
        }

        $decisions[(int) $row] = $normalized;
    }

    return $decisions;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    classListPlanResponse(false, 'Invalid Class List import-plan request.', 405, 'METHOD_NOT_ALLOWED');
}

$operation = $_POST['operation'] ?? 'plan';

if (!in_array($operation, ['plan', 'confirm'], true)) {
    classListPlanResponse(false, 'Invalid Class List import operation.', 422, 'INVALID_IMPORT_OPERATION');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    classListPlanResponse(false, 'The request could not be verified. Refresh the page and try again.', 419, 'CSRF_VALIDATION_FAILED');
}

$operationalClassId = filter_var($_POST['operational_class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$sourceToken = trim((string) ($_POST['source_token'] ?? ''));
$worksheetName = trim((string) ($_POST['worksheet_name'] ?? ''));
$headerRow = filter_var($_POST['header_row_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$firstDataRow = filter_var($_POST['first_data_row_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);

if ($operationalClassId === false) {
    classListPlanResponse(false, 'A valid Operational Class is required.', 422, 'INVALID_OPERATIONAL_CLASS');
}

if ($sourceToken === '') {
    classListPlanResponse(false, 'The uploaded Class List source is unavailable. Upload the file again.', 422, 'SOURCE_TOKEN_REQUIRED');
}

if ($worksheetName === '' || $headerRow === false || $firstDataRow === false || $firstDataRow <= $headerRow) {
    classListPlanResponse(false, 'Confirm a valid worksheet, Header Row, and First Student Row.', 422, 'INVALID_SOURCE_STRUCTURE');
}

$fields = ['student_number', 'student_name_raw', 'first_name', 'middle_name', 'last_name', 'suffix', 'program', 'section', 'year_level'];
$receivedMapping = $_POST['mapping'] ?? [];

if (!is_array($receivedMapping)) {
    classListPlanResponse(false, 'The Class List column mapping is invalid.', 422, 'INVALID_MAPPING');
}

$mapping = [];
$usedColumns = [];

foreach ($fields as $field) {
    $column = filter_var($receivedMapping[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $mapping[$field] = $column === false ? null : (int) $column;

    if ($mapping[$field] !== null) {
        if (isset($usedColumns[$mapping[$field]])) {
            classListPlanResponse(false, 'A source column can only be mapped to one APRISM field.', 422, 'DUPLICATE_SOURCE_COLUMN');
        }

        $usedColumns[$mapping[$field]] = true;
    }
}

if ($mapping['student_number'] === null) {
    classListPlanResponse(false, 'Student Number must be mapped before preparing an import plan.', 422, 'STUDENT_NUMBER_MAPPING_REQUIRED');
}

if ($mapping['student_name_raw'] === null && ($mapping['first_name'] === null || $mapping['last_name'] === null)) {
    classListPlanResponse(false, 'Map Student Name (combined), or both First Name and Last Name.', 422, 'STUDENT_NAME_MAPPING_REQUIRED');
}

$identityOverrides = classListPlanIdentityOverrides($_POST['identity_overrides_json'] ?? null);
$contextDecisions = classListPlanContextDecisions($_POST['academic_context_decisions_json'] ?? null);
$teacherId = (int) $_SESSION['user_id'];

try {
    $classStmt = $pdo->prepare("
        SELECT
            oc.operational_class_id, oc.school_year, oc.semester, oc.section_id,
            sy.school_year_id, sy.start_date AS school_year_start_date, sy.end_date AS school_year_end_date,
            sec.section_name, sec.year_level AS section_year_level,
            p.program_id, p.program_code, p.program_name, p.academic_level
        FROM operational_classes AS oc
        INNER JOIN school_years AS sy
            ON sy.school_year = oc.school_year
           AND sy.status = 'Active'
        INNER JOIN sections AS sec ON sec.section_id = oc.section_id
        LEFT JOIN programs AS p ON p.program_id = sec.program_id
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
    ");
    $classStmt->execute([(int) $operationalClassId, $teacherId]);
    $classContext = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($classContext === null) {
        classListPlanResponse(false, 'The selected operational class is unavailable or you do not have permission to manage it.', 403, 'CLASS_ACCESS_DENIED');
    }

    $source = (new ClassListSourceSession())->get($sourceToken, $teacherId, (int) $operationalClassId);
    if ($operation === 'confirm') {
        if (!hash_equals('confirm_class_list_import', (string) ($_POST['confirmation_intent'] ?? ''))) {
            classListPlanResponse(false, 'Explicit Class List confirmation is required.', 422, 'CONFIRMATION_REQUIRED');
        }

        $result = (new ClassListPersistenceEngine())->confirm(
            $pdo,
            $source,
            $classContext,
            $worksheetName,
            (int) $headerRow,
            (int) $firstDataRow,
            $mapping,
            $identityOverrides,
            $contextDecisions,
            $teacherId
        );

        classListPlanResponse(
            true,
            'Class List import confirmed. Student and enrollment records were saved transactionally.',
            200,
            'IMPORT_CONFIRMED',
            $result
        );
    }

    $plan = (new ClassListImportPlanEngine())->build(
        $pdo,
        $source,
        $classContext,
        $worksheetName,
        (int) $headerRow,
        (int) $firstDataRow,
        $mapping,
        $identityOverrides,
        $contextDecisions
    );

    classListPlanResponse(true, 'Server-validated import plan is ready. No Student or enrollment records were changed.', 200, 'IMPORT_PLAN_READY', $plan);
} catch (Throwable $e) {
    error_log('[APRISM Class List Import Plan] type=' . $e::class . '; message=' . $e->getMessage());
    classListPlanResponse(false, 'The import plan could not be prepared. Upload the source again if it has expired.', 422, 'IMPORT_PLAN_FAILED');
}
