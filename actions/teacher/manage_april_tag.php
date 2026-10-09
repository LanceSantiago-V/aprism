<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/apriltag_renderer.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../../auth/session_guard.php';

function aprilTagRedirect(int $operationalClassId): never
{
    header(
        'Location: ' . APP_URL
        . '/dashboard/teacher_april_tags.php?operational_class_id='
        . $operationalClassId
    );
    exit;
}

function aprilTagFail(
    string $message,
    int $operationalClassId
): never {
    $_SESSION['error_message'] = $message;
    aprilTagRedirect($operationalClassId);
}

/**
 * @return array<string, mixed>|null
 */
function aprilTagActiveAssignment(
    PDO $pdo,
    array $participant,
    int $operationalClassId
): ?array {
    $stmt = $pdo->prepare("
        SELECT
            at.april_tag_id,
            at.tag_code,
            ata.april_tag_assignment_id
        FROM april_tags AS at
        INNER JOIN april_tag_assignments AS ata
            ON ata.april_tag_id = at.april_tag_id
           AND ata.status = 'Assigned'
           AND ata.reclaimed_at IS NULL
        WHERE at.current_student_id = ?
          AND at.status = 'Assigned'
          AND ata.issued_from_operational_class_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        (int) $participant['student_id'],
        $operationalClassId,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Existing Available rows are reused. If an ID has never existed before,
 * APRISM creates its internal state only during confirmed issuance.
 */
function aprilTagAcquireAvailableTag(PDO $pdo, int $tagCode): int
{
    $tagStmt = $pdo->prepare("
        SELECT
            april_tag_id,
            status
        FROM april_tags
        WHERE tag_family = 'tagStandard52h13'
          AND tag_code = ?
        LIMIT 1
        FOR UPDATE
    ");

    $tagStmt->execute([$tagCode]);
    $tag = $tagStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($tag === null) {
        $createStmt = $pdo->prepare("
            INSERT INTO april_tags
                (tag_family, tag_code, status)
            VALUES
                ('tagStandard52h13', ?, 'Available')
        ");

        $createStmt->execute([$tagCode]);

        return (int) $pdo->lastInsertId();
    }

    if ((string) $tag['status'] !== 'Available') {
        if ((string) $tag['status'] === 'Retired') {
            throw new RuntimeException(
                'AprilTag #' . $tagCode
                . ' is retired and cannot be issued.'
            );
        }

        throw new RuntimeException(
            'AprilTag #' . $tagCode
            . ' is already assigned and cannot be issued.'
        );
    }

    return (int) $tag['april_tag_id'];
}

function aprilTagAssignToStudent(
    PDO $pdo,
    int $tagId,
    array $participant,
    int $operationalClassId,
    int $teacherId
): void {
    $assignmentStmt = $pdo->prepare("
        INSERT INTO april_tag_assignments
            (
                april_tag_id,
                student_id,
                school_year_id,
                semester,
                issued_from_operational_class_id,
                assigned_by,
                status
            )
        VALUES
            (?, ?, ?, ?, ?, ?, 'Assigned')
    ");

    $assignmentStmt->execute([
        $tagId,
        (int) $participant['student_id'],
        (int) $participant['school_year_id'],
        (string) $participant['semester'],
        $operationalClassId,
        $teacherId,
    ]);

    $activateStmt = $pdo->prepare("
        UPDATE april_tags
        SET
            status = 'Assigned',
            current_student_id = ?,
            current_school_year_id = ?,
            current_semester = ?,
            assigned_at = NOW(),
            assigned_by = ?
        WHERE april_tag_id = ?
          AND status = 'Available'
    ");

    $activateStmt->execute([
        (int) $participant['student_id'],
        (int) $participant['school_year_id'],
        (string) $participant['semester'],
        $teacherId,
        $tagId,
    ]);

    if ($activateStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The AprilTag changed before it could be assigned. Try again.'
        );
    }
}

function aprilTagReclaimAssignment(
    PDO $pdo,
    array $assignment,
    int $teacherId
): void {
    $reclaimStmt = $pdo->prepare("
        UPDATE april_tag_assignments
        SET
            status = 'Reclaimed',
            reclaimed_by = ?,
            reclaimed_at = NOW()
        WHERE april_tag_assignment_id = ?
          AND status = 'Assigned'
          AND reclaimed_at IS NULL
    ");

    $reclaimStmt->execute([
        $teacherId,
        (int) $assignment['april_tag_assignment_id'],
    ]);

    if ($reclaimStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The AprilTag assignment changed before it could be reclaimed.'
        );
    }

    $releaseStmt = $pdo->prepare("
        UPDATE april_tags
        SET
            status = 'Available',
            current_student_id = NULL,
            current_school_year_id = NULL,
            current_semester = NULL,
            assigned_at = NULL,
            assigned_by = NULL
        WHERE april_tag_id = ?
          AND status = 'Assigned'
    ");

    $releaseStmt->execute([
        (int) $assignment['april_tag_id'],
    ]);

    if ($releaseStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The AprilTag could not be released safely.'
        );
    }
}

function aprilTagValidateCode(mixed $value, string $label): int
{
    $code = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 0,
                'max_range' => 48713,
            ],
        ]
    );

    if ($code === false) {
        throw new RuntimeException(
            $label . ' must be a valid tagStandard52h13 ID from 0 to 48713.'
        );
    }

    return (int) $code;
}

/**
 * Require the local authoritative renderer to produce this tag's matrix.
 * No PNG is generated and no database state is changed here.
 */
function aprilTagRequireRenderable(int $tagCode): void
{
    try {
        aprism_apriltag_matrix($tagCode);
    } catch (Throwable $exception) {
        throw new RuntimeException(
            'AprilTag #' . $tagCode
            . ' could not be rendered by the local tagStandard52h13 renderer.'
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$operationalClassId = filter_var(
    $_POST['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($operationalClassId === false) {
    $_SESSION['error_message'] = 'A valid Operational Class is required.';
    header('Location: ' . APP_URL . '/dashboard/teacher_my_class.php');
    exit;
}

$operationalClassId = (int) $operationalClassId;

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    aprilTagFail(
        'The request could not be verified. Refresh the page and try again.',
        $operationalClassId
    );
}

$operation = trim((string) ($_POST['operation'] ?? ''));

if (
    !in_array(
        $operation,
        ['assign', 'bulk_assign', 'replace', 'reclaim'],
        true
    )
) {
    aprilTagFail('Invalid AprilTag operation.', $operationalClassId);
}

$teacherId = (int) $_SESSION['user_id'];

if ($operation === 'reclaim') {
    aprilTagFail(
        'Standalone AprilTag reclaim is disabled. Use explicit replacement or an authorized lifecycle workflow.',
        $operationalClassId
    );
}

if ($operation === 'bulk_assign') {
    $selectedIds =
        $_POST['selected_student_class_enrollment_ids'] ?? [];

    $rawTagCodes = trim((string) ($_POST['tag_codes_raw'] ?? ''));

    if (!is_array($selectedIds) || $selectedIds === []) {
        aprilTagFail(
            'Select at least one awaiting Student for bulk issuance.',
            $operationalClassId
        );
    }

    if ($rawTagCodes === '') {
        aprilTagFail(
            'Use Auto-Fill or provide one valid tag ID for each selected Student.',
            $operationalClassId
        );
    }

    if (count($selectedIds) > 100) {
        aprilTagFail(
            'Bulk issuance is limited to 100 Students per request.',
            $operationalClassId
        );
    }

    $idList = [];

    foreach ($selectedIds as $rawId) {
        $participantId = filter_var(
            $rawId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (
            $participantId === false
            || in_array((int) $participantId, $idList, true)
        ) {
            aprilTagFail(
                'The bulk Student selection is invalid or duplicated.',
                $operationalClassId
            );
        }

        $idList[] = (int) $participantId;
    }

    $submittedTagCodes = preg_split(
        '/[\s,;]+/',
        $rawTagCodes,
        -1,
        PREG_SPLIT_NO_EMPTY
    ) ?: [];

    if (count($submittedTagCodes) !== count($idList)) {
        aprilTagFail(
            'Provide exactly one AprilTag ID for each selected Student.',
            $operationalClassId
        );
    }

    $tagCodesByParticipant = [];

    foreach ($idList as $index => $participantId) {
        try {
            $tagCode = aprilTagValidateCode(
                $submittedTagCodes[$index] ?? null,
                'Every AprilTag ID'
            );
        } catch (RuntimeException $exception) {
            aprilTagFail(
                $exception->getMessage(),
                $operationalClassId
            );
        }

        $tagCodesByParticipant[$participantId] = $tagCode;
    }

    if (
        count($tagCodesByParticipant)
        !== count(array_unique($tagCodesByParticipant))
    ) {
        aprilTagFail(
            'Bulk issuance cannot use the same AprilTag ID more than once.',
            $operationalClassId
        );
    }

    foreach (array_unique($tagCodesByParticipant) as $tagCode) {
        try {
            aprilTagRequireRenderable((int) $tagCode);
        } catch (RuntimeException $exception) {
            aprilTagFail(
                $exception->getMessage()
                . ' No changes were saved.',
                $operationalClassId
            );
        }
    }

    try {
        $pdo->beginTransaction();

        $placeholders = implode(
            ',',
            array_fill(0, count($idList), '?')
        );

        $participantStmt = $pdo->prepare("
            SELECT
                sce.student_class_enrollment_id,
                sce.student_id,
                st.student_number,
                oc.school_year,
                oc.semester,
                sy.school_year_id
            FROM student_class_enrollments AS sce
            INNER JOIN students AS st
                ON st.student_id = sce.student_id
               AND st.status = 'Active'
            INNER JOIN operational_classes AS oc
                ON oc.operational_class_id = sce.operational_class_id
            INNER JOIN school_years AS sy
                ON sy.school_year = oc.school_year
               AND sy.status = 'Active'
            WHERE sce.student_class_enrollment_id IN ($placeholders)
              AND sce.operational_class_id = ?
              AND sce.status = 'Active'
              AND oc.teacher_id = ?
              AND oc.status = 'Active'
            FOR UPDATE
        ");

        $participantStmt->execute(array_merge(
            $idList,
            [$operationalClassId, $teacherId]
        ));

        $selectedParticipants = $participantStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (count($selectedParticipants) !== count($idList)) {
            throw new RuntimeException(
                'One or more selected Students are unavailable or not owned by you. No changes were saved.'
            );
        }

        $participantsById = [];

        foreach ($selectedParticipants as $participant) {
            $participantsById[
                (int) $participant['student_class_enrollment_id']
            ] = $participant;
        }

        /*
         * Lock and validate every submitted tag before any assignment
         * is created. Missing IDs are intentionally created as internal
         * Available state and immediately activated in this transaction.
         */
        foreach ($tagCodesByParticipant as $tagCode) {
            $tagStateStmt = $pdo->prepare("
                SELECT
                    april_tag_id,
                    status
                FROM april_tags
                WHERE tag_family = 'tagStandard52h13'
                  AND tag_code = ?
                LIMIT 1
                FOR UPDATE
            ");

            $tagStateStmt->execute([$tagCode]);
            $tagState = $tagStateStmt->fetch(PDO::FETCH_ASSOC);

            if (
                $tagState !== false
                && (string) $tagState['status'] !== 'Available'
            ) {
                if ((string) $tagState['status'] === 'Retired') {
                    throw new RuntimeException(
                        'AprilTag #' . $tagCode
                        . ' is retired and cannot be issued. No changes were saved.'
                    );
                }

                throw new RuntimeException(
                    'AprilTag #' . $tagCode
                    . ' is already assigned. No changes were saved.'
                );
            }
        }

        $issuedCount = 0;

        foreach ($idList as $participantId) {
            $participant = $participantsById[$participantId];
            $tagCode = $tagCodesByParticipant[$participantId];

            $studentTagStmt = $pdo->prepare("
                SELECT
                    at.april_tag_id,
                    at.tag_code
                FROM april_tags AS at
                INNER JOIN april_tag_assignments AS ata
                    ON ata.april_tag_id = at.april_tag_id
                   AND ata.status = 'Assigned'
                   AND ata.reclaimed_at IS NULL
                WHERE at.current_student_id = ?
                  AND at.status = 'Assigned'
                LIMIT 1
                FOR UPDATE
            ");

            $studentTagStmt->execute([
                (int) $participant['student_id'],
            ]);

            if ($studentTagStmt->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException(
                    'Student '
                    . (string) $participant['student_number']
                    . ' already has a current AprilTag. No changes were saved.'
                );
            }

            $tagId = aprilTagAcquireAvailableTag(
                $pdo,
                $tagCode
            );

            aprilTagAssignToStudent(
                $pdo,
                $tagId,
                $participant,
                $operationalClassId,
                $teacherId
            );

            if (
                !logAudit(
                    $pdo,
                    'AprilTags Bulk Assigned',
                    sprintf(
                        'Bulk-assigned AprilTag #%d to Student %s for Operational Class #%d (%s).',
                        $tagCode,
                        (string) $participant['student_number'],
                        $operationalClassId,
                        (string) $participant['semester']
                    )
                )
            ) {
                throw new RuntimeException(
                    'The bulk AprilTag issuance could not be audited. No changes were saved.'
                );
            }

            $issuedCount++;
        }

        $pdo->commit();

        $_SESSION['success_message'] =
            $issuedCount
            . ' AprilTag'
            . ($issuedCount === 1 ? '' : 's')
            . ' issued successfully.';

        aprilTagRedirect($operationalClassId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            '[APRISM AprilTag Bulk Issuance] '
            . $exception->getMessage()
        );

        aprilTagFail(
            $exception->getMessage(),
            $operationalClassId
        );
    }
}

$submittedTagCode = null;

if ($operation === 'assign' || $operation === 'replace') {
    try {
        $submittedTagCode = aprilTagValidateCode(
            $_POST['tag_code'] ?? null,
            $operation === 'replace'
            ? 'The replacement AprilTag ID'
            : 'The AprilTag ID'
        );

        aprilTagRequireRenderable($submittedTagCode);
    } catch (RuntimeException $exception) {
        aprilTagFail(
            $exception->getMessage(),
            $operationalClassId
        );
    }
}

$studentClassEnrollmentId = filter_var(
    $_POST['student_class_enrollment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($studentClassEnrollmentId === false) {
    aprilTagFail(
        'A valid Class List participant is required.',
        $operationalClassId
    );
}

try {
    $pdo->beginTransaction();

    $participantStmt = $pdo->prepare("
        SELECT
            sce.student_class_enrollment_id,
            sce.student_id,
            st.student_number,
            oc.school_year,
            oc.semester,
            sy.school_year_id
        FROM student_class_enrollments AS sce
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        INNER JOIN operational_classes AS oc
            ON oc.operational_class_id = sce.operational_class_id
        INNER JOIN school_years AS sy
            ON sy.school_year = oc.school_year
           AND sy.status = 'Active'
        WHERE sce.student_class_enrollment_id = ?
          AND sce.operational_class_id = ?
          AND sce.status = 'Active'
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
        FOR UPDATE
    ");

    $participantStmt->execute([
        (int) $studentClassEnrollmentId,
        $operationalClassId,
        $teacherId,
    ]);

    $participant = $participantStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($participant === null) {
        throw new RuntimeException(
            'This Class List participant is unavailable or you do not have permission to manage it.'
        );
    }

    if ($operation === 'assign') {
        $tagCode = (int) $submittedTagCode;

        $studentTagStmt = $pdo->prepare("
            SELECT
                at.tag_code
            FROM april_tags AS at
            INNER JOIN april_tag_assignments AS ata
                ON ata.april_tag_id = at.april_tag_id
               AND ata.status = 'Assigned'
               AND ata.reclaimed_at IS NULL
            WHERE at.current_student_id = ?
              AND at.status = 'Assigned'
            LIMIT 1
            FOR UPDATE
        ");

        $studentTagStmt->execute([
            (int) $participant['student_id'],
        ]);

        $existingTag = $studentTagStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($existingTag !== null) {
            if ((int) $existingTag['tag_code'] === $tagCode) {
                $pdo->commit();

                $_SESSION['warning_message'] =
                    'This Student already has AprilTag #'
                    . $tagCode
                    . ' globally.';

                aprilTagRedirect($operationalClassId);
            }

            throw new RuntimeException(
                'This Student already has a current AprilTag. '
                . 'Use replacement through its issuing class.'
            );
        }

        $tagId = aprilTagAcquireAvailableTag(
            $pdo,
            $tagCode
        );

        aprilTagAssignToStudent(
            $pdo,
            $tagId,
            $participant,
            $operationalClassId,
            $teacherId
        );

        if (
            !logAudit(
                $pdo,
                'AprilTag Assigned',
                sprintf(
                    'Assigned AprilTag #%d to Student %s for Operational Class #%d (%s).',
                    $tagCode,
                    (string) $participant['student_number'],
                    $operationalClassId,
                    (string) $participant['semester']
                )
            )
        ) {
            throw new RuntimeException(
                'The AprilTag assignment could not be audited. No changes were saved.'
            );
        }

        $pdo->commit();

        $_SESSION['success_message'] =
            'AprilTag #' . $tagCode . ' was assigned successfully.';

        aprilTagRedirect($operationalClassId);
    }

    $activeAssignment = aprilTagActiveAssignment(
        $pdo,
        $participant,
        $operationalClassId
    );

    if ($activeAssignment === null) {
        throw new RuntimeException(
            'This AprilTag assignment cannot be managed from this Operational Class.'
        );
    }

    if ($operation === 'replace') {
        $replacementTagCode = (int) $submittedTagCode;

        if (
            (int) $activeAssignment['tag_code']
            === $replacementTagCode
        ) {
            $pdo->commit();

            $_SESSION['warning_message'] =
                'AprilTag #' . $replacementTagCode
                . ' is already assigned to this Student.';

            aprilTagRedirect($operationalClassId);
        }

        $replacementTagId = aprilTagAcquireAvailableTag(
            $pdo,
            $replacementTagCode
        );

        aprilTagReclaimAssignment(
            $pdo,
            $activeAssignment,
            $teacherId
        );

        aprilTagAssignToStudent(
            $pdo,
            $replacementTagId,
            $participant,
            $operationalClassId,
            $teacherId
        );

        if (
            !logAudit(
                $pdo,
                'AprilTag Replaced',
                sprintf(
                    'Replaced AprilTag #%d with AprilTag #%d for Student %s in Operational Class #%d.',
                    (int) $activeAssignment['tag_code'],
                    $replacementTagCode,
                    (string) $participant['student_number'],
                    $operationalClassId
                )
            )
        ) {
            throw new RuntimeException(
                'The AprilTag replacement could not be audited. No changes were saved.'
            );
        }

        $pdo->commit();

        $_SESSION['success_message'] =
            'AprilTag #'
            . $activeAssignment['tag_code']
            . ' was reclaimed and replaced with AprilTag #'
            . $replacementTagCode
            . '.';

        aprilTagRedirect($operationalClassId);
    }

    throw new RuntimeException(
        'Standalone AprilTag reclaim is disabled. Use explicit replacement or an authorized lifecycle workflow.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[APRISM AprilTag Lifecycle] '
        . $exception->getMessage()
    );

    aprilTagFail(
        $exception->getMessage(),
        $operationalClassId
    );
}