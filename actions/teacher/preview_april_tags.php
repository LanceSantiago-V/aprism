<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/apriltag_renderer.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../../auth/session_guard.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Return the endpoint's single JSON response shape.
 *
 * @param array<int, mixed> $details
 */
function aprilTagPreviewRespond(
    int $status,
    bool $ok,
    ?string $code = null,
    ?string $message = null,
    array $details = [],
    array $data = []
): never {
    http_response_code($status);

    $response = ['ok' => $ok];

    if ($ok) {
        $response['data'] = $data;
    } else {
        $response['error'] = [
            'code' => $code ?? 'preview_error',
            'message' => $message ?? 'The AprilTag preview could not be generated.',
            'details' => $details,
        ];
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    aprilTagPreviewRespond(
        405,
        false,
        'method_not_allowed',
        'This endpoint accepts POST requests only.'
    );
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

if (
    $contentType === ''
    || !str_starts_with($contentType, 'application/json')
) {
    aprilTagPreviewRespond(
        415,
        false,
        'json_required',
        'This endpoint accepts a JSON request body only.'
    );
}

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    aprilTagPreviewRespond(
        400,
        false,
        'invalid_json',
        'The request body must contain valid JSON.'
    );
}

try {
    $request = json_decode(
        $rawBody,
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException) {
    aprilTagPreviewRespond(
        400,
        false,
        'invalid_json',
        'The request body must contain valid JSON.'
    );
}

if (!is_array($request)) {
    aprilTagPreviewRespond(
        400,
        false,
        'invalid_request',
        'The JSON body must be an object.'
    );
}

if (!validateCsrfToken($request['csrf_token'] ?? null)) {
    aprilTagPreviewRespond(
        419,
        false,
        'csrf_failed',
        'The request could not be verified. Refresh the page and try again.'
    );
}

$parsePositiveInteger = static function (mixed $value): ?int {
    if (is_array($value) || is_object($value) || is_bool($value)) {
        return null;
    }

    $value = trim((string) $value);

    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        return null;
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT);

    return $integer === false ? null : (int) $integer;
};

$parseTagCode = static function (mixed $value): ?int {
    if (is_array($value) || is_object($value) || is_bool($value)) {
        return null;
    }

    $value = trim((string) $value);

    if (!preg_match('/^(0|[1-9][0-9]*)$/', $value)) {
        return null;
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT);

    if ($integer === false || $integer < 0 || $integer > 48713) {
        return null;
    }

    return (int) $integer;
};

$operationalClassId = $parsePositiveInteger(
    $request['operational_class_id'] ?? null
);

if ($operationalClassId === null) {
    aprilTagPreviewRespond(
        422,
        false,
        'invalid_operational_class',
        'A valid Operational Class is required.'
    );
}

$proposals = $request['proposals'] ?? null;

if (!is_array($proposals) || $proposals === []) {
    aprilTagPreviewRespond(
        422,
        false,
        'invalid_proposals',
        'At least one Student and AprilTag proposal is required.'
    );
}

if (count($proposals) > 100) {
    aprilTagPreviewRespond(
        422,
        false,
        'proposal_limit_exceeded',
        'A preview request may contain at most 100 proposals.'
    );
}

$normalizedProposals = [];
$enrollmentIds = [];
$tagCodes = [];
$validationDetails = [];

foreach ($proposals as $index => $proposal) {
    if (!is_array($proposal)) {
        $validationDetails[] = [
            'index' => $index,
            'reason' => 'Each proposal must be an object.',
        ];
        continue;
    }

    $enrollmentId = $parsePositiveInteger(
        $proposal['student_class_enrollment_id'] ?? null
    );
    $tagCode = $parseTagCode($proposal['tag_code'] ?? null);

    if ($enrollmentId === null) {
        $validationDetails[] = [
            'index' => $index,
            'reason' => 'A valid student_class_enrollment_id is required.',
        ];
        continue;
    }

    if ($tagCode === null) {
        $validationDetails[] = [
            'index' => $index,
            'student_class_enrollment_id' => $enrollmentId,
            'reason' => 'tag_code must be an integer from 0 to 48713.',
        ];
        continue;
    }

    if (isset($enrollmentIds[$enrollmentId])) {
        $validationDetails[] = [
            'index' => $index,
            'student_class_enrollment_id' => $enrollmentId,
            'reason' => 'The same student_class_enrollment_id was submitted more than once.',
        ];
        continue;
    }

    if (isset($tagCodes[$tagCode])) {
        $validationDetails[] = [
            'index' => $index,
            'tag_code' => $tagCode,
            'reason' => 'The same tag_code was submitted more than once.',
        ];
        continue;
    }

    $enrollmentIds[$enrollmentId] = true;
    $tagCodes[$tagCode] = true;
    $normalizedProposals[] = [
        'student_class_enrollment_id' => $enrollmentId,
        'tag_code' => $tagCode,
    ];
}

if ($validationDetails !== []) {
    aprilTagPreviewRespond(
        422,
        false,
        'invalid_proposals',
        'One or more proposals are invalid or duplicated.',
        $validationDetails
    );
}

$teacherId = (int) ($_SESSION['user_id'] ?? 0);

if ($teacherId < 1) {
    aprilTagPreviewRespond(
        401,
        false,
        'teacher_session_required',
        'A valid Teacher session is required.'
    );
}

try {
    $classStmt = $pdo->prepare("
        SELECT
            oc.operational_class_id
        FROM operational_classes AS oc
        INNER JOIN school_years AS sy
            ON sy.school_year = oc.school_year
           AND sy.status = 'Active'
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
    ");
    $classStmt->execute([$operationalClassId, $teacherId]);

    if ($classStmt->fetchColumn() === false) {
        aprilTagPreviewRespond(
            403,
            false,
            'class_not_owned',
            'The selected Operational Class is unavailable or you do not have permission to preview it.'
        );
    }

    $enrollmentPlaceholders = implode(
        ',',
        array_fill(0, count($normalizedProposals), '?')
    );

    $enrollmentStmt = $pdo->prepare("
        SELECT
            sce.student_class_enrollment_id,
            sce.student_id,
            st.student_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.suffix
        FROM student_class_enrollments AS sce
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        WHERE sce.student_class_enrollment_id IN ($enrollmentPlaceholders)
          AND sce.operational_class_id = ?
          AND sce.status = 'Active'
    ");
    $enrollmentStmt->execute(array_merge(
        array_keys($enrollmentIds),
        [$operationalClassId]
    ));

    $enrollmentsById = [];

    foreach ($enrollmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $enrollmentsById[(int) $row['student_class_enrollment_id']] = $row;
    }

    if (count($enrollmentsById) !== count($normalizedProposals)) {
        $missing = [];

        foreach ($normalizedProposals as $proposal) {
            $id = $proposal['student_class_enrollment_id'];

            if (!isset($enrollmentsById[$id])) {
                $missing[] = [
                    'student_class_enrollment_id' => $id,
                    'reason' => 'The enrollment is inactive, unavailable, or does not belong to this Operational Class.',
                ];
            }
        }

        aprilTagPreviewRespond(
            422,
            false,
            'invalid_enrollments',
            'One or more proposed Students are not active members of this Operational Class.',
            $missing
        );
    }

    $studentIds = [];

    foreach ($enrollmentsById as $row) {
        $studentIds[(int) $row['student_id']] = true;
    }

    $studentPlaceholders = implode(
        ',',
        array_fill(0, count($studentIds), '?')
    );

    $assignedStudentStmt = $pdo->prepare("
        SELECT DISTINCT
            at.current_student_id,
            at.tag_code
        FROM april_tags AS at
        WHERE at.tag_family = 'tagStandard52h13'
          AND at.status = 'Assigned'
          AND at.current_student_id IN ($studentPlaceholders)
    ");
    $assignedStudentStmt->execute(array_keys($studentIds));

    $assignedStudents = [];

    foreach ($assignedStudentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $assignedStudents[(int) $row['current_student_id']] = (int) $row['tag_code'];
    }

    if ($assignedStudents !== []) {
        $details = [];

        foreach ($enrollmentsById as $row) {
            $studentId = (int) $row['student_id'];

            if (isset($assignedStudents[$studentId])) {
                $details[] = [
                    'student_class_enrollment_id' => (int) $row['student_class_enrollment_id'],
                    'student_number' => (string) $row['student_number'],
                    'assigned_tag_code' => $assignedStudents[$studentId],
                    'reason' => 'The Student already has a globally assigned AprilTag.',
                ];
            }
        }

        aprilTagPreviewRespond(
            409,
            false,
            'student_already_assigned',
            'One or more proposed Students already have a globally assigned AprilTag.',
            $details
        );
    }

    $tagPlaceholders = implode(',', array_fill(0, count($tagCodes), '?'));
    $tagStmt = $pdo->prepare("
        SELECT
            tag_code,
            status
        FROM april_tags
        WHERE tag_family = 'tagStandard52h13'
          AND tag_code IN ($tagPlaceholders)
    ");
    $tagStmt->execute(array_keys($tagCodes));

    $tagStates = [];

    foreach ($tagStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tagStates[(int) $row['tag_code']] = (string) $row['status'];
    }

    $unavailableTags = [];

    foreach ($tagStates as $tagCode => $status) {
        if ($status === 'Assigned' || $status === 'Retired') {
            $unavailableTags[] = [
                'tag_code' => $tagCode,
                'status' => $status,
                'reason' => $status === 'Retired'
                    ? 'Retired tags cannot be previewed.'
                    : 'Assigned tags cannot be previewed.',
            ];
        }
    }

    if ($unavailableTags !== []) {
        aprilTagPreviewRespond(
            409,
            false,
            'tag_unavailable',
            'One or more proposed AprilTags are Assigned or Retired.',
            $unavailableTags
        );
    }

    $matricesByTag = [];

    foreach (array_keys($tagCodes) as $tagCode) {
        try {
            $matrix = aprism_apriltag_matrix((int) $tagCode);
        } catch (Throwable) {
            aprilTagPreviewRespond(
                422,
                false,
                'tag_not_renderable',
                'One or more proposed AprilTags could not be rendered by the local tagStandard52h13 renderer.',
                [['tag_code' => (int) $tagCode]]
            );
        }

        if (
            !is_array($matrix)
            || count($matrix) !== 10
        ) {
            aprilTagPreviewRespond(
                422,
                false,
                'tag_not_renderable',
                'One or more proposed AprilTags did not produce a valid 10×10 marker matrix.',
                [['tag_code' => (int) $tagCode]]
            );
        }

        $normalizedMatrix = [];

        foreach ($matrix as $rowIndex => $row) {
            if (!is_array($row) || count($row) !== 10) {
                aprilTagPreviewRespond(
                    422,
                    false,
                    'tag_not_renderable',
                    'One or more proposed AprilTags did not produce a valid 10×10 marker matrix.',
                    [['tag_code' => (int) $tagCode]]
                );
            }

            $normalizedRow = [];

            foreach ($row as $columnIndex => $cell) {
                if ($cell !== 0 && $cell !== 1) {
                    aprilTagPreviewRespond(
                        422,
                        false,
                        'tag_not_renderable',
                        'The local renderer returned a matrix containing a non-binary cell.',
                        [
                            [
                                'tag_code' => (int) $tagCode,
                                'row' => $rowIndex,
                                'column' => $columnIndex,
                            ]
                        ]
                    );
                }

                $normalizedRow[] = (int) $cell;
            }

            $normalizedMatrix[] = $normalizedRow;
        }

        $matricesByTag[(int) $tagCode] = $normalizedMatrix;
    }

    $responseProposals = [];

    foreach ($normalizedProposals as $proposal) {
        $enrollment = $enrollmentsById[
            $proposal['student_class_enrollment_id']
        ];

        $studentName = trim(implode(' ', array_filter([
            $enrollment['first_name'],
            $enrollment['middle_name'],
            $enrollment['last_name'],
            $enrollment['suffix'],
        ], static fn($part): bool => trim((string) $part) !== '')));

        $responseProposals[] = [
            'student_class_enrollment_id' => $proposal['student_class_enrollment_id'],
            'student_id' => (int) $enrollment['student_id'],
            'student_number' => (string) $enrollment['student_number'],
            'student_name' => $studentName,
            'tag_code' => $proposal['tag_code'],
            'matrix' => $matricesByTag[$proposal['tag_code']],
        ];
    }

    aprilTagPreviewRespond(
        200,
        true,
        data: [
            'family' => 'tagStandard52h13',
            'matrix_width' => 10,
            'matrix_height' => 10,
            'operational_class_id' => $operationalClassId,
            'proposals' => $responseProposals,
        ]
    );
} catch (Throwable $exception) {
    error_log('[APRISM AprilTag Preview] ' . $exception->getMessage());

    aprilTagPreviewRespond(
        500,
        false,
        'preview_failed',
        'The AprilTag preview could not be generated. No changes were made.'
    );
}
