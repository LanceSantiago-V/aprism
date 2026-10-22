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
require_once __DIR__ . '/../../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/notification_helper.php';

$redirect = APP_URL . '/dashboard/academic_head_adviser_assignments.php';

function adviserAssignmentRedirectUrl(string $redirect, bool $preserveEdit = false): string
{
    $contexts = aprismAdviserTeachingContexts();
    $statuses = ['Active', 'Ended', 'Invalid / Ineligible', 'All'];
    $schoolYearId = filter_input(INPUT_POST, 'return_school_year_id', FILTER_VALIDATE_INT) ?: 0;
    $context = trim((string) ($_POST['return_teaching_context'] ?? 'All'));
    $status = trim((string) ($_POST['return_status'] ?? 'All'));
    $page = filter_input(INPUT_POST, 'return_page', FILTER_VALIDATE_INT) ?: 1;
    $teacher = trim((string) ($_POST['return_teacher'] ?? ''));

    $parameters = [
        'school_year_id' => max(0, $schoolYearId),
        'teaching_context' => $context === 'All' || !in_array($context, $contexts, true) ? 'All' : $context,
        'status' => in_array($status, $statuses, true) ? $status : 'All',
        'page' => max(1, $page),
        'teacher' => substr($teacher, 0, 120),
    ];
    if ($preserveEdit && trim((string) ($_POST['operation'] ?? '')) === 'change') {
        $editId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT) ?: 0;
        if ($editId > 0) {
            $parameters['edit'] = $editId;
        }
    }
    return $redirect . '?' . http_build_query($parameters);
}

function adviserAssignmentRememberForm(array $values, string $field, string $message): void
{
    $_SESSION['adviser_assignment_form_values'] = $values;
    $_SESSION['adviser_assignment_field_error'] = [
        'field' => $field,
        'message' => $message,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_message'] = 'Your security token expired. Refresh the page and try again.';
    header('Location: ' . adviserAssignmentRedirectUrl($redirect, trim((string) ($_POST['operation'] ?? '')) === 'change'));
    exit;
}

$operation = trim((string) ($_POST['operation'] ?? ''));
$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT) ?: 0;
$teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT) ?: 0;
$schoolYearId = filter_input(INPUT_POST, 'school_year_id', FILTER_VALIDATE_INT) ?: 0;
$sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT) ?: 0;
$teachingContext = trim((string) ($_POST['teaching_context'] ?? ''));
$formValues = [
    'operation' => in_array($operation, ['create', 'change'], true) ? $operation : 'create',
    'assignment_id' => $assignmentId,
    'teacher_id' => $teacherId,
    'school_year_id' => $schoolYearId,
    'section_id' => $sectionId,
    'teaching_context' => $teachingContext,
];
$activeField = 'teacher_id';
$committed = false;

try {
    if ($operation === 'deactivate') {
        if ($assignmentId <= 0) {
            throw new RuntimeException('Select a valid active Adviser assignment.');
        }

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
            throw new RuntimeException('Only an active Adviser assignment can be ended.');
        }

        $update = $pdo->prepare(
            "UPDATE adviser_assignments
             SET status = 'Ended', ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP),
                 active_assignment_key = NULL, active_teacher_context_key = NULL
             WHERE adviser_assignment_id = ?"
        );
        $update->execute([$assignmentId]);

        if (!logAudit(
            $pdo,
            'Adviser Assignment Deactivated',
            'Ended Adviser assignment for ' . $assignment['username'] . ' in ' . $assignment['section_name'] . '.'
        )) {
            throw new RuntimeException('The assignment could not be recorded in the audit log. No changes were saved.');
        }

        $pdo->commit();
        $committed = true;
        $_SESSION['success_message'] = 'Adviser assignment ended.';
        header('Location: ' . adviserAssignmentRedirectUrl($redirect));
        exit;
    }

    if (!in_array($operation, ['create', 'change'], true)) {
        throw new RuntimeException('Choose a valid Adviser assignment action.');
    }
    if ($operation === 'change' && $assignmentId <= 0) {
        $activeField = 'teacher_id';
        throw new RuntimeException('The active assignment to change could not be found.');
    }
    if ($teacherId <= 0) {
        $activeField = 'teacher_id';
        throw new RuntimeException('Select an active Teacher.');
    }
    if ($schoolYearId <= 0) {
        $activeField = 'school_year_id';
        throw new RuntimeException('Select a School Year.');
    }
    if (!aprismAdviserTeachingContextIsValid($teachingContext)) {
        $activeField = 'teaching_context';
        throw new RuntimeException('Select a valid SHS teaching context.');
    }
    if ($sectionId <= 0) {
        $activeField = 'section_id';
        throw new RuntimeException('Select an eligible SHS Section.');
    }

    $pdo->beginTransaction();

    $teacherStmt = $pdo->prepare(
        "SELECT u.user_id, u.username
         FROM users u
         WHERE u.user_id = ? AND u.role_id = ? AND u.account_status = 'Active'
         LIMIT 1 FOR UPDATE"
    );
    $teacherStmt->execute([$teacherId, ROLE_TEACHER]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) {
        $activeField = 'teacher_id';
        throw new RuntimeException('The selected account is not an active Teacher.');
    }

    $activeField = 'school_year_id';
    $schoolYearStmt = $pdo->prepare(
        "SELECT school_year_id, school_year, status
         FROM school_years
         WHERE school_year_id = ? AND status IN ('Active', 'Inactive')
         LIMIT 1 FOR UPDATE"
    );
    $schoolYearStmt->execute([$schoolYearId]);
    $schoolYear = $schoolYearStmt->fetch(PDO::FETCH_ASSOC);
    if (!$schoolYear) {
        throw new RuntimeException('The selected School Year is not available.');
    }

    $activeField = 'section_id';
    $sectionStmt = $pdo->prepare(
        "SELECT sec.section_id, sec.section_name, sec.year_level, p.program_code
         FROM sections sec
         INNER JOIN programs p
             ON p.program_id = sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
         WHERE sec.section_id = ? AND sec.status = 'Active'
         LIMIT 1 FOR UPDATE"
    );
    $sectionStmt->execute([$sectionId]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        throw new RuntimeException('The selected Section is not an active Section with confirmed SHS classification.');
    }

    $oldAssignment = null;
    if ($operation === 'change') {
        $activeField = 'teacher_id';
        $oldStmt = $pdo->prepare(
            "SELECT adviser_assignment_id, teacher_id, section_id, school_year_id,
                    teaching_context, status
             FROM adviser_assignments
             WHERE adviser_assignment_id = ?
             LIMIT 1 FOR UPDATE"
        );
        $oldStmt->execute([$assignmentId]);
        $oldAssignment = $oldStmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldAssignment || $oldAssignment['status'] !== 'Active') {
            throw new RuntimeException('Only an active Adviser assignment can be changed.');
        }
    }

    $activeField = 'teacher_id';
    $duplicateTeacherSql =
        "SELECT adviser_assignment_id FROM adviser_assignments
         WHERE teacher_id = ? AND school_year_id = ? AND teaching_context = ? AND status = 'Active'";
    $duplicateTeacherParams = [$teacherId, $schoolYearId, $teachingContext];
    if ($operation === 'change') {
        $duplicateTeacherSql .= ' AND adviser_assignment_id <> ?';
        $duplicateTeacherParams[] = $assignmentId;
    }
    $duplicateTeacherStmt = $pdo->prepare($duplicateTeacherSql . ' LIMIT 1 FOR UPDATE');
    $duplicateTeacherStmt->execute($duplicateTeacherParams);
    if ($duplicateTeacherStmt->fetchColumn()) {
        throw new RuntimeException('This Teacher already has an assignment for the selected School Year and teaching context. Use Change or End on the existing assignment.');
    }

    $activeField = 'section_id';
    $duplicateSectionSql =
        "SELECT adviser_assignment_id FROM adviser_assignments
         WHERE section_id = ? AND school_year_id = ? AND teaching_context = ? AND status = 'Active'";
    $duplicateSectionParams = [$sectionId, $schoolYearId, $teachingContext];
    if ($operation === 'change') {
        $duplicateSectionSql .= ' AND adviser_assignment_id <> ?';
        $duplicateSectionParams[] = $assignmentId;
    }
    $duplicateSectionStmt = $pdo->prepare($duplicateSectionSql . ' LIMIT 1 FOR UPDATE');
    $duplicateSectionStmt->execute($duplicateSectionParams);
    if ($duplicateSectionStmt->fetchColumn()) {
        throw new RuntimeException('This Section already has an Adviser for the selected School Year and teaching context. Use Change or End on the existing assignment.');
    }

    // The Academic Head can activate only this fixed capability as part of
    // this assignment. No role or arbitrary permission values are accepted.
    aprismActivateAdviserResponsibility($pdo, $teacherId, $schoolYearId);

    if ($oldAssignment) {
        $endStmt = $pdo->prepare(
            "UPDATE adviser_assignments
             SET status = 'Ended', ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP),
                 active_assignment_key = NULL, active_teacher_context_key = NULL
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

    $auditAction = $oldAssignment ? 'Adviser Assignment Changed' : 'Adviser Assignment Created';
    $auditDescription = ($oldAssignment ? 'Changed' : 'Created')
        . ' Adviser assignment for ' . $teacher['username']
        . ' to ' . $section['section_name']
        . ' (' . $teachingContext . ', ' . $schoolYear['school_year'] . ').';
    if (!logAudit($pdo, $auditAction, $auditDescription)) {
        throw new RuntimeException('The assignment could not be recorded in the audit log. No changes were saved.');
    }

    $pdo->commit();
    $committed = true;

    $notificationWarning = null;
    try {
        aprismNotify(
            $pdo,
            $teacherId,
            'adviser_assignment',
            'Adviser assignment updated',
            'You have an active Adviser assignment for ' . $section['section_name'] . ' (' . $teachingContext . ').',
            APP_URL . '/dashboard/teacher_advisory_students.php',
            'adviser-assignment-' . $teacherId . '-' . $schoolYearId . '-' . hash('sha256', $teachingContext),
            'adviser_assignment',
            null
        );
    } catch (Throwable $notificationException) {
        error_log('[APRISM Adviser Assignment Notification] ' . $notificationException->getMessage());
        $notificationWarning = 'Assignment saved, but its notification could not be sent. Do not submit it again.';
    }

    $_SESSION['success_message'] = $oldAssignment ? 'Adviser assignment changed.' : 'Adviser assigned.';
    if ($notificationWarning !== null) {
        $_SESSION['warning_message'] = $notificationWarning;
    }
    unset($_SESSION['adviser_assignment_form_values'], $_SESSION['adviser_assignment_field_error']);
} catch (Throwable $exception) {
    if (!$committed && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[APRISM Adviser Assignment] ' . $exception->getMessage());

    if (in_array($operation, ['create', 'change'], true)) {
        if ($exception instanceof PDOException) {
            $message = 'The assignment could not be saved because the selected Teacher or Section is no longer available. Review the current assignments and try again.';
        } elseif ($exception instanceof RuntimeException) {
            $message = $exception->getMessage();
        } else {
            $message = 'Unable to save the Adviser assignment. No changes were saved.';
        }
        adviserAssignmentRememberForm($formValues, $activeField, $message);
    } else {
        $_SESSION['error_message'] = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Unable to end the Adviser assignment. No changes were saved.';
    }
}

header('Location: ' . adviserAssignmentRedirectUrl($redirect, !$committed && $operation === 'change'));
exit;
