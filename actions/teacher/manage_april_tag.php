<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../../auth/session_guard.php';

function aprilTagRedirect(int $operationalClassId): never
{
    header('Location: ' . APP_URL . '/dashboard/teacher_april_tags.php?operational_class_id=' . $operationalClassId);
    exit;
}

function aprilTagFail(string $message, int $operationalClassId): never
{
    $_SESSION['error_message'] = $message;
    aprilTagRedirect($operationalClassId);
}

/** @return array<string, mixed>|null */
function aprilTagActiveAssignment(PDO $pdo, array $participant, int $operationalClassId): ?array
{
    $stmt = $pdo->prepare("
        SELECT at.april_tag_id, at.tag_code, ata.april_tag_assignment_id
        FROM april_tags AS at
        INNER JOIN april_tag_assignments AS ata
            ON ata.april_tag_id = at.april_tag_id
           AND ata.status = 'Assigned'
           AND ata.reclaimed_at IS NULL
        WHERE at.current_student_id = ?
          AND at.current_school_year_id = ?
          AND at.current_semester = ?
          AND at.status = 'Assigned'
          AND ata.issued_from_operational_class_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        (int) $participant['student_id'],
        (int) $participant['school_year_id'],
        (string) $participant['semester'],
        $operationalClassId,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aprilTagAcquireAvailableTag(PDO $pdo, int $tagCode): int
{
    $tagStmt = $pdo->prepare("
        SELECT april_tag_id, status
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
            INSERT INTO april_tags (tag_family, tag_code, status)
            VALUES ('tagStandard52h13', ?, 'Available')
        ");
        $createStmt->execute([$tagCode]);
        return (int) $pdo->lastInsertId();
    }

    if ((string) $tag['status'] !== 'Available') {
        throw new RuntimeException('AprilTag #' . $tagCode . ' is not available for assignment.');
    }

    return (int) $tag['april_tag_id'];
}

function aprilTagAssignToStudent(PDO $pdo, int $tagId, array $participant, int $operationalClassId, int $teacherId): void
{
    $assignmentStmt = $pdo->prepare("
        INSERT INTO april_tag_assignments
            (april_tag_id, student_id, school_year_id, semester,
             issued_from_operational_class_id, assigned_by, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Assigned')
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
        SET status = 'Assigned',
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
        throw new RuntimeException('The AprilTag changed before it could be assigned. Try again.');
    }
}

function aprilTagReclaimAssignment(PDO $pdo, array $assignment, int $teacherId): void
{
    $reclaimStmt = $pdo->prepare("
        UPDATE april_tag_assignments
        SET status = 'Reclaimed',
            reclaimed_by = ?,
            reclaimed_at = NOW()
        WHERE april_tag_assignment_id = ?
          AND status = 'Assigned'
          AND reclaimed_at IS NULL
    ");
    $reclaimStmt->execute([$teacherId, (int) $assignment['april_tag_assignment_id']]);

    if ($reclaimStmt->rowCount() !== 1) {
        throw new RuntimeException('The AprilTag assignment changed before it could be reclaimed. Try again.');
    }

    $releaseStmt = $pdo->prepare("
        UPDATE april_tags
        SET status = 'Available',
            current_student_id = NULL,
            current_school_year_id = NULL,
            current_semester = NULL,
            assigned_at = NULL,
            assigned_by = NULL
        WHERE april_tag_id = ?
          AND status = 'Assigned'
    ");
    $releaseStmt->execute([(int) $assignment['april_tag_id']]);

    if ($releaseStmt->rowCount() !== 1) {
        throw new RuntimeException('The AprilTag could not be released safely.');
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
    aprilTagFail('The request could not be verified. Refresh the page and try again.', $operationalClassId);
}

$operation = trim((string) ($_POST['operation'] ?? ''));
if (!in_array($operation, ['assign', 'replace', 'reclaim'], true)) {
    aprilTagFail('Invalid AprilTag operation.', $operationalClassId);
}

$studentClassEnrollmentId = filter_var(
    $_POST['student_class_enrollment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($studentClassEnrollmentId === false) {
    aprilTagFail('A valid Class List participant is required.', $operationalClassId);
}

$teacherId = (int) $_SESSION['user_id'];

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
    $participantStmt->execute([(int) $studentClassEnrollmentId, $operationalClassId, $teacherId]);
    $participant = $participantStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($participant === null) {
        throw new RuntimeException('This Class List participant is unavailable or you do not have permission to manage it.');
    }

    if ($operation === 'assign') {
        $tagCode = filter_var(
            $_POST['tag_code'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 48713]]
        );
        if ($tagCode === false) {
            throw new RuntimeException('Enter a valid tagStandard52h13 tag number from 0 to 48713.');
        }

        $studentTagStmt = $pdo->prepare("
            SELECT tag_code
            FROM april_tags
            WHERE current_student_id = ?
              AND current_school_year_id = ?
              AND current_semester = ?
              AND status = 'Assigned'
            LIMIT 1
            FOR UPDATE
        ");
        $studentTagStmt->execute([
            (int) $participant['student_id'],
            (int) $participant['school_year_id'],
            (string) $participant['semester'],
        ]);
        $existingTag = $studentTagStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($existingTag !== null) {
            if ((int) $existingTag['tag_code'] === (int) $tagCode) {
                $pdo->commit();
                $_SESSION['warning_message'] = 'This Student already has AprilTag #' . $tagCode . ' for the current academic term.';
                aprilTagRedirect($operationalClassId);
            }
            throw new RuntimeException('This Student already has an active AprilTag for the current academic term. Reclaim or replace it through its issuing class.');
        }

        $tagId = aprilTagAcquireAvailableTag($pdo, (int) $tagCode);
        aprilTagAssignToStudent($pdo, $tagId, $participant, $operationalClassId, $teacherId);

        if (
            !logAudit($pdo, 'AprilTag Assigned', sprintf(
                'Assigned AprilTag #%d to Student %s for Operational Class #%d (%s).',
                (int) $tagCode,
                (string) $participant['student_number'],
                $operationalClassId,
                (string) $participant['semester']
            ))
        ) {
            throw new RuntimeException('The AprilTag assignment could not be audited. No changes were saved.');
        }

        $pdo->commit();
        $_SESSION['success_message'] = 'AprilTag #' . $tagCode . ' was assigned successfully.';
        aprilTagRedirect($operationalClassId);
    }

    $activeAssignment = aprilTagActiveAssignment($pdo, $participant, $operationalClassId);
    if ($activeAssignment === null) {
        throw new RuntimeException('This AprilTag assignment cannot be managed from this Operational Class.');
    }

    if ($operation === 'replace') {
        $replacementTagCode = filter_var(
            $_POST['tag_code'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 48713]]
        );
        if ($replacementTagCode === false) {
            throw new RuntimeException('Enter a valid replacement tagStandard52h13 tag number from 0 to 48713.');
        }
        if ((int) $activeAssignment['tag_code'] === (int) $replacementTagCode) {
            $pdo->commit();
            $_SESSION['warning_message'] = 'AprilTag #' . $replacementTagCode . ' is already assigned to this Student.';
            aprilTagRedirect($operationalClassId);
        }

        $replacementTagId = aprilTagAcquireAvailableTag($pdo, (int) $replacementTagCode);

        /*
         * Releasing the existing current pointer inside this one transaction
         * preserves the one-current-tag-per-Student-and-term constraint.
         */
        aprilTagReclaimAssignment($pdo, $activeAssignment, $teacherId);
        aprilTagAssignToStudent($pdo, $replacementTagId, $participant, $operationalClassId, $teacherId);

        if (
            !logAudit($pdo, 'AprilTag Replaced', sprintf(
                'Replaced AprilTag #%d with AprilTag #%d for Student %s in Operational Class #%d.',
                (int) $activeAssignment['tag_code'],
                (int) $replacementTagCode,
                (string) $participant['student_number'],
                $operationalClassId
            ))
        ) {
            throw new RuntimeException('The AprilTag replacement could not be audited. No changes were saved.');
        }

        $pdo->commit();
        $_SESSION['success_message'] = 'AprilTag #' . $activeAssignment['tag_code'] . ' was reclaimed and replaced with AprilTag #' . $replacementTagCode . '.';
        aprilTagRedirect($operationalClassId);
    }

    $otherActiveClassStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_class_enrollments AS other_sce
        INNER JOIN operational_classes AS other_oc
            ON other_oc.operational_class_id = other_sce.operational_class_id
        WHERE other_sce.student_id = ?
          AND other_sce.status = 'Active'
          AND other_oc.status = 'Active'
          AND other_oc.school_year = ?
          AND other_oc.semester = ?
          AND other_sce.operational_class_id <> ?
    ");
    $otherActiveClassStmt->execute([
        (int) $participant['student_id'],
        (string) $participant['school_year'],
        (string) $participant['semester'],
        $operationalClassId,
    ]);
    if ((int) $otherActiveClassStmt->fetchColumn() > 0) {
        throw new RuntimeException('This Student is still active in another class this term. Keep or replace the AprilTag; do not reclaim it from this class.');
    }

    aprilTagReclaimAssignment($pdo, $activeAssignment, $teacherId);

    if (
        !logAudit($pdo, 'AprilTag Reclaimed', sprintf(
            'Reclaimed AprilTag #%d from Student %s for Operational Class #%d.',
            (int) $activeAssignment['tag_code'],
            (string) $participant['student_number'],
            $operationalClassId
        ))
    ) {
        throw new RuntimeException('The AprilTag reclaim could not be audited. No changes were saved.');
    }

    $pdo->commit();
    $_SESSION['success_message'] = 'AprilTag #' . (int) $activeAssignment['tag_code'] . ' is available for reassignment.';
    aprilTagRedirect($operationalClassId);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[APRISM AprilTag Lifecycle] ' . $exception->getMessage());
    aprilTagFail($exception->getMessage(), $operationalClassId);
}
