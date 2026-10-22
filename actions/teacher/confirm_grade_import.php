<?php

declare(strict_types=1);

ob_start();
$sent = false;
function gradeConfirmResponse(bool $success, string $message, int $status = 200, array $data = []): never
{
    global $sent;
    $sent = true;
    while (ob_get_level() > 0)
        @ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
register_shutdown_function(static function (): void {
    global $sent;
    if (!$sent && ($e = error_get_last()) !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true))
        gradeConfirmResponse(false, 'The Grade Import confirmation could not be completed.', 500);
});
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../includes/helper/current_teaching_context_helper.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/import/grade_import_engine.php';
$allowedRoles = [ROLE_TEACHER];
$apiResponseMode = true;
require_once __DIR__ . '/../../auth/session_guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null))
    gradeConfirmResponse(false, 'The request could not be verified. Refresh the page and try again.', 419);
$token = trim((string) ($_POST['review_token'] ?? ''));
$review = $_SESSION['grade_import_reviews'][$token] ?? null;
if (!is_array($review) || ($review['expires_at'] ?? 0) < time() || (int) ($review['teacher_id'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0))
    gradeConfirmResponse(false, 'The review has expired. Extract the workbook again.', 422);
$data = $review['data'];
$classId = (int) $review['operational_class_id'];
$classAcademicLevel = (string) ($data['class_context']['academic_level'] ?? '');
$templateAcademicLevel = ($data['template_type'] ?? '') === 'SHS' ? 'Senior High School' : 'College';
$workbookIssues = array_values($data['workbook_issues'] ?? []);
$allowConflicts = ($_POST['allow_conflicts'] ?? '') === '1';
$confirmIdentityReconciliations = ($_POST['confirm_identity_reconciliations'] ?? '') === '1';
$blocking = array_merge(
    array_values($data['period_resolution_issues'] ?? []),
    $workbookIssues
);
if (!in_array($classAcademicLevel, ['College', 'Senior High School'], true) || $classAcademicLevel !== $templateAcademicLevel) {
    $blocking[] = 'The reviewed workbook Academic Level does not match the confirmed class context. Extract a matching workbook again.';
}
if (!in_array((string) ($data['class_context']['semester'] ?? ''), ['First Semester', 'Second Semester'], true)) {
    $blocking[] = 'The reviewed class does not have a valid teaching Semester.';
}
$expectedPeriodNames = $classAcademicLevel === 'Senior High School'
    ? (($data['class_context']['semester'] ?? '') === 'Second Semester' ? ['Quarter 3', 'Quarter 4'] : ['Quarter 1', 'Quarter 2'])
    : ['Prelim', 'Midterm', 'Pre-Final', 'Final'];
$reviewedPeriodNames = array_map(static fn(array $period): string => (string) ($period['period_name'] ?? ''), $data['resolved_periods'] ?? []);
$expectedSorted = $expectedPeriodNames;
$reviewedSorted = $reviewedPeriodNames;
sort($expectedSorted);
sort($reviewedSorted);
if ($expectedSorted !== $reviewedSorted) {
    $blocking[] = 'The review does not contain the complete Academic Period set for this class teaching term.';
}
$identityReconciliations = [];
$expectedEnrollments = [];
foreach ($data['periods'] as $period)
    foreach ($period['blocking_issues'] ?? [] as $issue)
        $blocking[] = (string) $issue;
foreach ($data['periods'] as $period)
    foreach ($period['rows'] as $row) {
        $matchStatus = (string) ($row['match_status'] ?? '');
        if ($matchStatus === 'IDENTITY RECONCILIATION REQUIRED') {
            $reconciliation = $row['identity_reconciliation'] ?? null;
            if (!$confirmIdentityReconciliations)
                $blocking[] = 'Explicit confirmation is required for the reviewed same-class Student identity reconciliation.';
            elseif (!is_array($reconciliation) || ($reconciliation['mode'] ?? '') !== 'attach_authoritative_number')
                $blocking[] = 'This identity reconciliation is not eligible for the bounded automatic confirmation path.';
            else {
                $candidateStudentId = (int) ($reconciliation['candidate_student_id'] ?? 0);
                $incomingNumber = trim((string) ($reconciliation['incoming_student_number'] ?? ''));
                if ($candidateStudentId < 1 || $incomingNumber === '')
                    $blocking[] = 'The reviewed identity reconciliation is incomplete. Extract the workbook again.';
                elseif (isset($identityReconciliations[$candidateStudentId]) && $identityReconciliations[$candidateStudentId]['number'] !== $incomingNumber)
                    $blocking[] = 'One unresolved Student is mapped to more than one Student Number in the reviewed workbook.';
                else
                    $identityReconciliations[$candidateStudentId] = [
                        'number' => $incomingNumber,
                        'class_enrollment_id' => (int) ($reconciliation['candidate_student_class_enrollment_id'] ?? 0),
                        'incoming_name' => (string) ($reconciliation['incoming_student_name'] ?? ''),
                    ];
            }
        } elseif ($matchStatus !== 'MATCHED') {
            $blocking[] = 'Row ' . $row['row'] . ' in ' . ($period['period_name'] ?? $period['source_label']) . ' does not match an active participant.';
        } else {
            $enrollmentId = (int) ($row['student_class_enrollment_id'] ?? 0);
            $studentNumber = trim((string) ($row['student_number'] ?? ''));
            if ($enrollmentId < 1 || $studentNumber === '') {
                $blocking[] = 'A matched workbook row is missing its verified class-participant identity. Extract the workbook again.';
            } elseif (isset($expectedEnrollments[$enrollmentId]) && $expectedEnrollments[$enrollmentId] !== $studentNumber) {
                $blocking[] = 'One class participant is associated with conflicting Student Numbers in the reviewed workbook.';
            } else {
                $expectedEnrollments[$enrollmentId] = $studentNumber;
            }
        }
        foreach ($row['issues'] ?? [] as $issue)
            if (!str_starts_with($issue, 'Formula output was ignored'))
                $blocking[] = 'Row ' . $row['row'] . ': ' . $issue;
        foreach ($row['scores'] ?? [] as $score)
            if (($score['existing_score_state'] ?? '') === 'EXISTING SCORE CONFLICT' && !$allowConflicts)
                $blocking[] = 'Score changes require explicit confirmation.';
    }
foreach ($identityReconciliations as $candidateStudentId => $reconciliation) {
    $enrollmentId = (int) $reconciliation['class_enrollment_id'];
    if ($enrollmentId < 1) {
        $blocking[] = 'The reviewed class participation link is incomplete. Extract the workbook again.';
    } elseif (isset($expectedEnrollments[$enrollmentId]) && $expectedEnrollments[$enrollmentId] !== $reconciliation['number']) {
        $blocking[] = 'One class participant is associated with conflicting Student Numbers in the reviewed workbook.';
    } else {
        $expectedEnrollments[$enrollmentId] = $reconciliation['number'];
    }
}
if ($blocking !== [])
    gradeConfirmResponse(false, $blocking[0], 422, ['blocking_issues' => array_values(array_unique($blocking))]);
$commitAttempted = false;
try {
    $owner = $pdo->prepare("SELECT oc.school_year, oc.semester, oc.academic_level, sy.school_year_id FROM operational_classes oc INNER JOIN school_years sy ON sy.school_year=oc.school_year AND sy.status='Active' WHERE oc.operational_class_id=? AND oc.teacher_id=? AND oc.status='Active' AND " . aprismCurrentClassCondition('oc') . " LIMIT 1");
    $owner->execute([$classId, (int) $_SESSION['user_id']]);
    $class = $owner->fetch(PDO::FETCH_ASSOC);
    if ($class === false)
        gradeConfirmResponse(false, 'The selected class is no longer available.', 403);
    if ((string) $class['school_year'] !== (string) ($data['class_context']['school_year'] ?? '') || (string) $class['semester'] !== (string) ($data['class_context']['semester'] ?? '') || (string) ($class['academic_level'] ?? '') !== $classAcademicLevel)
        gradeConfirmResponse(false, 'The selected class term has changed. Extract the workbook again.', 422);
    $validPeriods = [];
    $periodStmt = $pdo->prepare("SELECT academic_period_id, period_name, academic_level, semester FROM academic_periods WHERE school_year_id=? AND semester=? AND academic_level=? AND is_archived=0");
    $periodStmt->execute([(int) $class['school_year_id'], $class['semester'], $classAcademicLevel]);
    foreach ($periodStmt->fetchAll(PDO::FETCH_ASSOC) as $period)
        $validPeriods[(int) $period['academic_period_id']] = $period;
    foreach ($data['periods'] as $period)
        if (
            !isset($validPeriods[(int) ($period['academic_period_id'] ?? 0)])
            || strcasecmp($validPeriods[(int) $period['academic_period_id']]['period_name'], (string) $period['period_name']) !== 0
            || (string) $validPeriods[(int) $period['academic_period_id']]['academic_level'] !== ($data['template_type'] === 'SHS' ? 'Senior High School' : 'College')
        )
            gradeConfirmResponse(false, 'A grading period is no longer valid for this class term.', 422);
    $pdo->beginTransaction();
    $ownerLock = $pdo->prepare("SELECT oc.school_year, oc.semester, oc.academic_level, sy.school_year_id FROM operational_classes oc INNER JOIN school_years sy ON sy.school_year=oc.school_year AND sy.status='Active' WHERE oc.operational_class_id=? AND oc.teacher_id=? AND oc.status='Active' AND " . aprismCurrentClassCondition('oc') . " LIMIT 1 FOR UPDATE");
    $ownerLock->execute([$classId, (int) $_SESSION['user_id']]);
    $lockedClass = $ownerLock->fetch(PDO::FETCH_ASSOC);
    if ($lockedClass === false || (string) $lockedClass['school_year'] !== (string) ($data['class_context']['school_year'] ?? '') || (string) $lockedClass['semester'] !== (string) ($data['class_context']['semester'] ?? '') || (string) ($lockedClass['academic_level'] ?? '') !== $classAcademicLevel)
        throw new RuntimeException('The selected class context changed before confirmation. Extract the workbook again.');

    $periodLock = $pdo->prepare("SELECT academic_period_id, period_name, academic_level FROM academic_periods WHERE school_year_id=? AND semester=? AND academic_level=? AND is_archived=0 FOR UPDATE");
    $periodLock->execute([(int) $lockedClass['school_year_id'], (string) $lockedClass['semester'], $classAcademicLevel]);
    $lockedPeriods = [];
    foreach ($periodLock->fetchAll(PDO::FETCH_ASSOC) as $periodRow)
        $lockedPeriods[(int) $periodRow['academic_period_id']] = $periodRow;
    foreach ($data['periods'] as $period) {
        $periodId = (int) ($period['academic_period_id'] ?? 0);
        if (!isset($lockedPeriods[$periodId]) || strcasecmp((string) $lockedPeriods[$periodId]['period_name'], (string) $period['period_name']) !== 0 || (string) $lockedPeriods[$periodId]['academic_level'] !== ($data['template_type'] === 'SHS' ? 'Senior High School' : 'College'))
            throw new RuntimeException('A grading period changed before confirmation. Extract the workbook again.');
    }

    if (!$allowConflicts && $identityReconciliations === []) {
        $duplicateBatchStmt = $pdo->prepare("SELECT grade_import_batch_id FROM grade_import_batches WHERE teacher_id=? AND operational_class_id=? AND school_year_snapshot=? AND semester_snapshot=? AND source_sha256=? AND status='Confirmed' LIMIT 1 FOR UPDATE");
        $duplicateBatchStmt->execute([(int) $_SESSION['user_id'], $classId, $lockedClass['school_year'], $lockedClass['semester'], (string) $data['source_sha256']]);
        $duplicateBatchId = $duplicateBatchStmt->fetchColumn();
        if ($duplicateBatchId !== false) {
            $pdo->rollBack();
            unset($_SESSION['grade_import_reviews'][$token]);
            gradeConfirmResponse(true, 'This exact term workbook was already confirmed. No changes were required.', 200, ['batch_id' => (int) $duplicateBatchId, 'no_changes_required' => true]);
        }
    }

    $identityEngine = new GradeImportEngine();
    foreach ($identityReconciliations as $candidateStudentId => $reconciliation) {
        $incomingNumber = $reconciliation['number'];
        $candidateClassEnrollmentId = (int) $reconciliation['class_enrollment_id'];
        if ($candidateClassEnrollmentId < 1)
            throw new RuntimeException('The reviewed class participation link is incomplete. Extract the workbook again.');
        $candidateStmt = $pdo->prepare("SELECT student_id, student_number, status FROM students WHERE student_id=? FOR UPDATE");
        $candidateStmt->execute([(int) $candidateStudentId]);
        $candidateStudent = $candidateStmt->fetch(PDO::FETCH_ASSOC);
        if ($candidateStudent === false || $candidateStudent['student_number'] !== null || (string) $candidateStudent['status'] !== 'Active')
            throw new RuntimeException('A reviewed unresolved Student identity changed before confirmation. Extract the workbook again.');

        $classEnrollmentStmt = $pdo->prepare("SELECT student_class_enrollment_id FROM student_class_enrollments WHERE student_class_enrollment_id=? AND student_id=? AND operational_class_id=? AND status='Active' FOR UPDATE");
        $classEnrollmentStmt->execute([$candidateClassEnrollmentId, (int) $candidateStudentId, $classId]);
        if ($classEnrollmentStmt->fetchColumn() === false)
            throw new RuntimeException('The reviewed class participation link changed before confirmation. Extract the workbook again.');

        if ($identityEngine->uniqueUnresolvedParticipantMatch($pdo, $classId, $reconciliation['incoming_name']) !== (int) $candidateStudentId)
            throw new RuntimeException('The same-class Student identity is no longer a unique match. Review the participant list and extract the workbook again.');

        $numberStmt = $pdo->prepare("SELECT student_id FROM students WHERE student_number=? FOR UPDATE");
        $numberStmt->execute([$incomingNumber]);
        if ($numberStmt->fetchColumn() !== false)
            throw new RuntimeException('The reviewed Student Number is now assigned to another Student. Refresh the Grade Import review.');

        $updateStudent = $pdo->prepare("UPDATE students SET student_number=? WHERE student_id=? AND student_number IS NULL");
        $updateStudent->execute([$incomingNumber, (int) $candidateStudentId]);
        if ($updateStudent->rowCount() !== 1)
            throw new RuntimeException('The unresolved Student Number could not be assigned safely. No changes were saved.');
    }

    if ($expectedEnrollments !== []) {
        $enrollmentIds = array_keys($expectedEnrollments);
        $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
        $enrollmentStmt = $pdo->prepare("SELECT sce.student_class_enrollment_id, s.student_number FROM student_class_enrollments sce INNER JOIN students s ON s.student_id=sce.student_id WHERE sce.operational_class_id=? AND sce.status='Active' AND s.status='Active' AND sce.student_class_enrollment_id IN ($placeholders) FOR UPDATE");
        $enrollmentStmt->execute(array_merge([$classId], $enrollmentIds));
        $confirmedEnrollments = [];
        foreach ($enrollmentStmt->fetchAll(PDO::FETCH_ASSOC) as $enrollmentRow)
            $confirmedEnrollments[(int) $enrollmentRow['student_class_enrollment_id']] = (string) $enrollmentRow['student_number'];
        foreach ($expectedEnrollments as $enrollmentId => $studentNumber)
            if (($confirmedEnrollments[$enrollmentId] ?? null) !== $studentNumber)
                throw new RuntimeException('A reviewed Student is no longer an active participant with the same Student Number. Extract the workbook again.');
    }
    $batch = $pdo->prepare("INSERT INTO grade_import_batches (teacher_id, operational_class_id, school_year_snapshot, semester_snapshot, original_filename, template_type, source_sha256, status, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Review', NOW())");
    $batch->execute([(int) $_SESSION['user_id'], $classId, $lockedClass['school_year'], $lockedClass['semester'], $data['source_filename'], $data['template_type'], $data['source_sha256']]);
    $batchId = (int) $pdo->lastInsertId();
    foreach ($data['periods'] as $period) {
        $periodId = (int) $period['academic_period_id'];
        $categoryIds = [];
        $componentIds = [];
        foreach ($period['categories'] as $category) {
            $importableComponents = array_values(array_filter(
                $category['components'],
                static fn(array $component): bool => $component['max_score'] !== null
            ));
            if ($importableComponents === []) {
                continue;
            }
            $find = $pdo->prepare("SELECT assessment_category_id FROM assessment_categories WHERE operational_class_id=? AND academic_period_id=? AND category_name=? LIMIT 1");
            $find->execute([$classId, $periodId, $category['category_name']]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if ($existing !== false) {
                $categoryId = (int) $existing['assessment_category_id'];
            } else {
                $insert = $pdo->prepare("INSERT INTO assessment_categories (operational_class_id, academic_period_id, category_name, weight_percent, display_order, source_label) VALUES (?, ?, ?, NULL, ?, ?)");
                $insert->execute([$classId, $periodId, $category['category_name'], count($categoryIds), $category['category_name']]);
                $categoryId = (int) $pdo->lastInsertId();
            }
            $categoryIds[$category['category_name']] = $categoryId;
            foreach ($importableComponents as $component) {
                $find = $pdo->prepare("SELECT assessment_component_id, max_score FROM assessment_components WHERE assessment_category_id=? AND component_code=? LIMIT 1");
                $find->execute([$categoryId, $component['code']]);
                $existingComponent = $find->fetch(PDO::FETCH_ASSOC);
                if ($existingComponent !== false) {
                    if ((float) $existingComponent['max_score'] !== (float) $component['max_score'])
                        throw new RuntimeException('An assessment component maximum conflicts with the existing class structure.');
                    $componentId = (int) $existingComponent['assessment_component_id'];
                } else {
                    $insert = $pdo->prepare("INSERT INTO assessment_components (assessment_category_id, component_code, component_name, max_score, display_order, source_label) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert->execute([$categoryId, $component['code'], $component['code'], $component['max_score'], count($componentIds), $component['code']]);
                    $componentId = (int) $pdo->lastInsertId();
                }
                $componentIds[$category['category_name'] . '|' . $component['code']] = $componentId;
            }
        }
        foreach ($period['rows'] as $row)
            foreach ($row['scores'] as $score) {
                $componentId = $componentIds[$score['category_name'] . '|' . $score['code']];
                $sceId = (int) $row['student_class_enrollment_id'];
                $find = $pdo->prepare("SELECT student_assessment_score_id, raw_score FROM student_assessment_scores WHERE student_class_enrollment_id=? AND assessment_component_id=? FOR UPDATE");
                $find->execute([$sceId, $componentId]);
                $existing = $find->fetch(PDO::FETCH_ASSOC);
                if ($existing === false) {
                    $insert = $pdo->prepare("INSERT INTO student_assessment_scores (student_class_enrollment_id, assessment_component_id, raw_score, originating_grade_import_batch_id) VALUES (?, ?, ?, ?)");
                    $insert->execute([$sceId, $componentId, $score['score'], $batchId]);
                    $scoreId = (int) $pdo->lastInsertId();
                    $revision = $pdo->prepare("INSERT INTO student_assessment_score_revisions (student_assessment_score_id, grade_import_batch_id, acted_by, previous_raw_score, new_raw_score, revision_type) VALUES (?, ?, ?, NULL, ?, 'Insert')");
                    $revision->execute([$scoreId, $batchId, (int) $_SESSION['user_id'], $score['score']]);
                } elseif ((float) $existing['raw_score'] !== (float) $score['score']) {
                    $update = $pdo->prepare("UPDATE student_assessment_scores SET raw_score=?, originating_grade_import_batch_id=? WHERE student_assessment_score_id=?");
                    $update->execute([$score['score'], $batchId, $existing['student_assessment_score_id']]);
                    $revision = $pdo->prepare("INSERT INTO student_assessment_score_revisions (student_assessment_score_id, grade_import_batch_id, acted_by, previous_raw_score, new_raw_score, revision_type) VALUES (?, ?, ?, ?, ?, 'Update')");
                    $revision->execute([$existing['student_assessment_score_id'], $batchId, (int) $_SESSION['user_id'], $existing['raw_score'], $score['score']]);
                }
            }
    }
    $pdo->prepare("UPDATE grade_import_batches SET status='Confirmed', confirmed_at=NOW() WHERE grade_import_batch_id=?")->execute([$batchId]);
    $reconciliationNote = $identityReconciliations === []
        ? ''
        : ' Reconciled ' . count($identityReconciliations) . ' unresolved Student identity number(s) from the reviewed Class List participation.';
    if (!logAudit($pdo, 'Confirm Grade Import', 'Confirmed term Grade Import batch #' . $batchId . ' for Operational Class #' . $classId . '.' . $reconciliationNote)) {
        throw new RuntimeException('The grades could not be saved because the import could not be recorded in the audit log. No changes were saved.');
    }
    $commitAttempted = true;
    if (!$pdo->commit()) {
        throw new RuntimeException('The Grade Import could not be committed. No changes were saved.');
    }
    unset($_SESSION['grade_import_reviews'][$token]);
    gradeConfirmResponse(true, 'Grade Import confirmed successfully.', 200, ['batch_id' => $batchId]);
} catch (Throwable $e) {
    $rollbackFailed = false;
    $rollbackConfirmed = false;
    try {
        if ($pdo->inTransaction()) {
            $rollbackConfirmed = $pdo->rollBack();
            if (!$rollbackConfirmed) {
                $rollbackFailed = true;
            }
        }
    } catch (Throwable $rollbackError) {
        $rollbackFailed = true;
        error_log('[APRISM Confirm Grade Import rollback] ' . $rollbackError::class . ': ' . $rollbackError->getMessage());
    }
    error_log('[APRISM Confirm Grade Import] ' . $e::class . ': ' . $e->getMessage());
    $outcomeUncertain = $rollbackFailed || ($commitAttempted && !$rollbackConfirmed);
    $message = $outcomeUncertain
        ? 'APRISM could not confirm whether these grades were saved. Check the import history before trying again.'
        : ($e instanceof PDOException
            ? 'The Grade Import could not be confirmed. No changes were saved.'
            : ($e instanceof RuntimeException
                ? $e->getMessage()
                : 'The Grade Import could not be confirmed. No changes were saved.'));
    gradeConfirmResponse(false, $message, 422, ['outcome_uncertain' => $outcomeUncertain]);
}
