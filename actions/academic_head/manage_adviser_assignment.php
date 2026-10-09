<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/adviser_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/notification_helper.php';

$redirect = APP_URL . '/dashboard/academic_head_adviser_assignments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_message'] = 'Your security token is invalid or has expired. Please try again.';
    header('Location: ' . $redirect);
    exit;
}

$operation = trim((string) ($_POST['operation'] ?? ''));
$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT) ?: 0;

if ($operation === 'deactivate') {
    if ($assignmentId <= 0) {
        $_SESSION['error_message'] = 'Invalid Adviser assignment selected.';
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT aa.adviser_assignment_id, aa.teacher_id, aa.section_id,
                    aa.school_year_id, aa.teaching_context, aa.status, u.username,
                    sec.section_name
             FROM adviser_assignments aa
             INNER JOIN users u ON u.user_id = aa.teacher_id
             INNER JOIN sections sec ON sec.section_id = aa.section_id
             WHERE aa.adviser_assignment_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment || $assignment['status'] !== 'Active') {
            throw new RuntimeException('Only an active Adviser assignment can be deactivated.');
        }

        $update = $pdo->prepare(
            "UPDATE adviser_assignments
             SET status = 'Ended',
                 ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP),
                 active_assignment_key = NULL,
                 active_teacher_context_key = NULL
             WHERE adviser_assignment_id = ?"
        );
        $update->execute([$assignmentId]);

        $pdo->commit();

        logAudit(
            $pdo,
            'Adviser Assignment Deactivated',
            'Deactivated Adviser assignment for ' . $assignment['username'] . ' in ' . $assignment['section_name'] . '.'
        );
        $_SESSION['success_message'] = 'Adviser assignment deactivated.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[APRISM Adviser Assignment Deactivate] ' . $exception->getMessage());
        $_SESSION['error_message'] = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Unable to deactivate the Adviser assignment.';
    }

    header('Location: ' . $redirect);
    exit;
}

if (!in_array($operation, ['create', 'change'], true)) {
    $_SESSION['error_message'] = 'Invalid Adviser assignment operation.';
    header('Location: ' . $redirect);
    exit;
}

$teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT) ?: 0;
$schoolYearId = filter_input(INPUT_POST, 'school_year_id', FILTER_VALIDATE_INT) ?: 0;
$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT) ?: 0;
$teachingContext = trim((string) ($_POST['teaching_context'] ?? ''));

try {
    if ($teacherId <= 0 || $schoolYearId <= 0 || $sectionId <= 0) {
        throw new RuntimeException('Teacher, School Year, teaching context, and SHS Section are required.');
    }

    if (!aprismAdviserTeachingContextIsValid($teachingContext)) {
        throw new RuntimeException('Select a valid SHS teaching context.');
    }

    $pdo->beginTransaction();

    $teacherStmt = $pdo->prepare(
        "SELECT u.user_id, u.username
         FROM users u
         WHERE u.user_id = ?
           AND u.role_id = ?
           AND u.account_status = 'Active'
         LIMIT 1
         FOR UPDATE"
    );
    $teacherStmt->execute([$teacherId, ROLE_TEACHER]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) {
        throw new RuntimeException('The selected account is not an active Teacher.');
    }

    $capabilityStmt = $pdo->prepare(
        "SELECT 1
         FROM teacher_responsibility_assignments
         WHERE teacher_id = ?
           AND responsibility_name = 'Adviser'
           AND school_year_id = ?
           AND status = 'Active'
         LIMIT 1"
    );
    $capabilityStmt->execute([$teacherId, $schoolYearId]);
    if (!$capabilityStmt->fetchColumn()) {
        throw new RuntimeException('The selected Teacher does not have active Adviser capability for this School Year.');
    }

    $schoolYearStmt = $pdo->prepare(
        "SELECT school_year_id, school_year
         FROM school_years
         WHERE school_year_id = ?
           AND status IN ('Active', 'Inactive')
         LIMIT 1"
    );
    $schoolYearStmt->execute([$schoolYearId]);
    $schoolYear = $schoolYearStmt->fetch(PDO::FETCH_ASSOC);
    if (!$schoolYear) {
        throw new RuntimeException('The selected School Year is not available.');
    }

    $sectionStmt = $pdo->prepare(
        "SELECT sec.section_id, sec.section_name, p.program_code
         FROM sections sec
         INNER JOIN programs p
             ON p.program_id = sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
         WHERE sec.section_id = ?
           AND sec.status = 'Active'
         LIMIT 1
         FOR UPDATE"
    );
    $sectionStmt->execute([$sectionId]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        throw new RuntimeException('The selected Section is not an eligible active SHS Section.');
    }

    $oldAssignment = null;
    if ($operation === 'change') {
        if ($assignmentId <= 0) {
            throw new RuntimeException('The Adviser assignment to change was not found.');
        }

        $oldStmt = $pdo->prepare(
            "SELECT adviser_assignment_id, teacher_id, section_id, school_year_id,
                    teaching_context, status
             FROM adviser_assignments
             WHERE adviser_assignment_id = ?
             FOR UPDATE"
        );
        $oldStmt->execute([$assignmentId]);
        $oldAssignment = $oldStmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldAssignment || $oldAssignment['status'] !== 'Active') {
            throw new RuntimeException('Only an active Adviser assignment can be changed.');
        }
    }

    $duplicateTeacherSql =
        "SELECT adviser_assignment_id
         FROM adviser_assignments
         WHERE teacher_id = ?
           AND school_year_id = ?
           AND teaching_context = ?
           AND status = 'Active'";
    $duplicateTeacherParams = [$teacherId, $schoolYearId, $teachingContext];
    if ($assignmentId > 0) {
        $duplicateTeacherSql .= ' AND adviser_assignment_id <> ?';
        $duplicateTeacherParams[] = $assignmentId;
    }
    $duplicateTeacherStmt = $pdo->prepare($duplicateTeacherSql . ' LIMIT 1 FOR UPDATE');
    $duplicateTeacherStmt->execute($duplicateTeacherParams);
    if ($duplicateTeacherStmt->fetchColumn()) {
        throw new RuntimeException('This Teacher already has an active assignment for the selected School Year and teaching context.');
    }

    $duplicateSectionSql =
        "SELECT adviser_assignment_id
         FROM adviser_assignments
         WHERE section_id = ?
           AND school_year_id = ?
           AND teaching_context = ?
           AND status = 'Active'";
    $duplicateSectionParams = [$sectionId, $schoolYearId, $teachingContext];
    if ($assignmentId > 0) {
        $duplicateSectionSql .= ' AND adviser_assignment_id <> ?';
        $duplicateSectionParams[] = $assignmentId;
    }
    $duplicateSectionStmt = $pdo->prepare($duplicateSectionSql . ' LIMIT 1 FOR UPDATE');
    $duplicateSectionStmt->execute($duplicateSectionParams);
    if ($duplicateSectionStmt->fetchColumn()) {
        throw new RuntimeException('This SHS Section already has an active Adviser for the selected School Year and teaching context.');
    }

    if ($oldAssignment) {
        $endStmt = $pdo->prepare(
            "UPDATE adviser_assignments
             SET status = 'Ended',
                 ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP),
                 active_assignment_key = NULL,
                 active_teacher_context_key = NULL
             WHERE adviser_assignment_id = ?"
        );
        $endStmt->execute([$assignmentId]);
    }

    $activeAssignmentKey = $sectionId . ':' . $schoolYearId . ':' . $teachingContext;
    $activeTeacherContextKey = $teacherId . ':' . $schoolYearId . ':' . $teachingContext;
    $insertStmt = $pdo->prepare(
        "INSERT INTO adviser_assignments
            (teacher_id, section_id, school_year_id, teaching_context, status,
             active_assignment_key, active_teacher_context_key)
         VALUES (?, ?, ?, ?, 'Active', ?, ?)"
    );
    $insertStmt->execute([
        $teacherId,
        $sectionId,
        $schoolYearId,
        $teachingContext,
        $activeAssignmentKey,
        $activeTeacherContextKey,
    ]);

    $pdo->commit();

    $auditAction = $oldAssignment ? 'Adviser Assignment Changed' : 'Adviser Assignment Created';
    $auditDescription = ($oldAssignment ? 'Changed' : 'Created')
        . ' Adviser assignment for ' . $teacher['username']
        . ' to ' . $section['section_name']
        . ' (' . $teachingContext . ', ' . $schoolYear['school_year'] . ').';
    logAudit($pdo, $auditAction, $auditDescription);
    aprismNotify($pdo, $teacherId, 'adviser_assignment', 'Adviser assignment updated', 'You have an active Adviser assignment for ' . $section['section_name'] . ' (' . $teachingContext . ').', APP_URL . '/dashboard/teacher_advisory_students.php', 'adviser-assignment-' . $teacherId . '-' . $schoolYearId . '-' . hash('sha256', $teachingContext), 'adviser_assignment', null);
    $_SESSION['success_message'] = $oldAssignment
        ? 'Adviser assignment changed.'
        : 'Adviser assignment created.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[APRISM Adviser Assignment] ' . $exception->getMessage());
    $_SESSION['error_message'] = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Unable to save the Adviser assignment.';
}

header('Location: ' . $redirect);
exit;
