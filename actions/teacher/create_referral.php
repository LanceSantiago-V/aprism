<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/teacher_referral_helper.php';
require_once __DIR__ . '/../../includes/helper/notification_helper.php';

$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$classId = filter_input(INPUT_POST, 'operational_class_id', FILTER_VALIDATE_INT) ?: 0;
$sceId = filter_input(INPUT_POST, 'student_class_enrollment_id', FILTER_VALIDATE_INT) ?: 0;
$reasonCode = trim((string) ($_POST['reason_code'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
$sourceContext = (string) ($_POST['source_context'] ?? 'referrals');
$sourceContext = in_array($sourceContext, ['risk_alerts', 'referrals'], true) ? $sourceContext : 'referrals';
$sourcePage = max(1, (int) ($_POST['source_page'] ?? 1));
$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT) ?: 0;
$evidenceToken = trim((string) ($_POST['evidence_token'] ?? ''));
$submissionToken = trim((string) ($_POST['submission_token'] ?? ''));
$failureQuery = [
    'new' => 1,
    'class_id' => $classId,
    'student_class_enrollment_id' => $sceId,
];
if ($studentId > 0) {
    $failureQuery['student_id'] = $studentId;
}
if ($sourceContext === 'risk_alerts') {
    $failureQuery['scope'] = 'teacher';
    $failureQuery['page'] = $sourcePage;
    $failureQuery['open_referral'] = 1;
    $redirect = APP_URL . '/dashboard/teacher_risk_alerts.php?' . http_build_query($failureQuery);
    $successRedirect = APP_URL . '/dashboard/teacher_risk_alerts.php?' . http_build_query([
        'scope' => 'teacher',
        'page' => $sourcePage,
    ]);
} else {
    $redirect = APP_URL . '/dashboard/teacher_referrals.php?' . http_build_query($failureQuery) . '#new-referral';
    $successRedirect = APP_URL . '/dashboard/teacher_referrals.php#referral-history-title';
}
$formState = [
    'operational_class_id' => $classId,
    'student_class_enrollment_id' => $sceId,
    'reason_code' => $reasonCode,
    'note' => $note,
    'source_context' => $sourceContext,
    'source_page' => $sourcePage,
];

$fail = static function (array $errors, string $message) use ($formState, $redirect): never {
    $_SESSION['teacher_referral_form'] = $formState;
    $_SESSION['teacher_referral_errors'] = $errors;
    unset($_SESSION['success_message'], $_SESSION['warning_message']);
    $_SESSION['error_message'] = $message;
    header('Location: ' . $redirect);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $fail(['form' => 'Your referral request could not be verified.'], 'Your referral request could not be verified.');
}

$submissionTokenStatus = aprismTeacherReferralSubmissionTokenStatus($submissionToken, $teacherId);
if ($submissionTokenStatus === 'consumed') {
    unset($_SESSION['teacher_referral_form'], $_SESSION['teacher_referral_errors']);
    unset($_SESSION['success_message'], $_SESSION['error_message']);
    $_SESSION['warning_message'] = 'This referral form was already submitted. No duplicate was created. Check Referral History before trying again.';
    header('Location: ' . $successRedirect);
    exit;
}
if ($submissionTokenStatus !== 'issued') {
    $fail(['form' => 'Refresh the referral form before submitting.'], 'The referral form could not be verified. Refresh it and try again.');
}

$errors = [];
if ($classId <= 0) {
    $errors['operational_class_id'] = 'Select one of your active classes.';
}
if ($sceId <= 0) {
    $errors['student_class_enrollment_id'] = 'Select a Student from the chosen class.';
}
if (!aprismReferralReasonIsValid($reasonCode)) {
    $errors['reason_code'] = 'Select a referral reason.';
}
if (mb_strlen($note) > 5000) {
    $errors['note'] = 'Keep the supporting note to 5,000 characters or fewer.';
} elseif ($reasonCode === 'other' && $note === '') {
    $errors['note'] = 'Explain the concern when Other Concern is selected.';
}
if ($errors !== []) {
    $fail($errors, 'Review the highlighted referral fields.');
}

$context = aprismTeacherReferralContext($pdo, $teacherId, (int) $classId, (int) $sceId);
if ($context === null) {
    $fail(
        ['student_class_enrollment_id' => 'Select an active Student from one of your classes.'],
        'The selected Student is not an active participant in one of your classes.'
    );
}

$evidenceSnapshot = $evidenceToken !== ''
    ? aprismTeacherReferralConsumeEvidenceToken($evidenceToken, $teacherId, (int) $classId, (int) $sceId)
    : null;
if (($evidenceToken !== '' && ($evidenceSnapshot === null
        || (int) ($evidenceSnapshot['student_id'] ?? 0) !== (int) $context['student_id']
        || (int) ($evidenceSnapshot['operational_class_id'] ?? 0) !== (int) $classId
        || (int) ($evidenceSnapshot['student_class_enrollment_id'] ?? 0) !== (int) $sceId))) {
    $fail(
        ['student_class_enrollment_id' => 'Review the selected Student and refresh the attendance evidence before submitting.'],
        'The referral evidence could not be verified. Review the selected Student and try again.'
    );
}
if ($evidenceSnapshot === null) {
    // No-JavaScript form submissions have no displayed evidence token. Capture
    // the same authoritative evidence at submission rather than accepting a
    // client-controlled source flag to omit the referral snapshot.
    $evidence = aprismTeacherReferralAttendanceEvidence($pdo, $teacherId, (int) $classId, (int) $sceId);
    if ($evidence === null) {
        $fail(
            ['student_class_enrollment_id' => 'Review the selected Student and try again.'],
            'The selected Student could not be verified for this referral.'
        );
    }
    $evidenceSnapshot = aprismTeacherReferralEvidenceSnapshot($evidence, $sourceContext);
}
$evidenceSnapshotJson = $evidenceSnapshot !== null
    ? json_encode($evidenceSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : null;
if ($evidenceSnapshot !== null && $evidenceSnapshotJson === false) {
    $fail(['form' => 'The referral evidence could not be prepared.'], 'The referral could not be prepared. Please try again.');
}

$saved = false;
try {
    $pdo->beginTransaction();
    $ownerLock = $pdo->prepare("SELECT operational_class_id FROM operational_classes
        WHERE operational_class_id = ? AND teacher_id = ? AND status = 'Active'
        LIMIT 1 FOR UPDATE");
    $ownerLock->execute([(int) $classId, $teacherId]);
    if ($ownerLock->fetchColumn() === false) {
        throw new DomainException('This class was reassigned before the referral was saved. Choose a class you currently teach.');
    }
    $stmt = $pdo->prepare("INSERT INTO teacher_referrals (teacher_id, student_id, operational_class_id, student_class_enrollment_id, school_year_snapshot, semester_snapshot, reason_code, reason, evidence_snapshot_json, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted')");
    $stmt->execute([$teacherId, (int) $context['student_id'], (int) $classId, (int) $sceId, $context['school_year'], $context['semester'], $reasonCode, $note, $evidenceSnapshotJson]);
    $referralId = (int) $pdo->lastInsertId();
    foreach (aprismGuidanceUserIds($pdo) as $guidanceUserId) {
        aprismNotify($pdo, $guidanceUserId, 'teacher_referral_submitted', 'New Teacher referral', 'A Teacher referral is ready for Guidance review.', APP_URL . '/dashboard/guidance_referrals.php?status=Submitted&referral_id=' . $referralId . '#referral-' . $referralId, 'teacher-referral-submitted-' . $referralId, 'teacher_referral', $referralId);
    }
    if (!logAudit($pdo, 'Create Teacher Referral', 'Created a Teacher referral record.')) {
        throw new RuntimeException('The referral could not be recorded in the audit log.');
    }
    if (!$pdo->commit()) {
        throw new RuntimeException('The referral could not be committed.');
    }
    $saved = true;
    aprismTeacherReferralMarkSubmissionTokenConsumed($submissionToken, $teacherId);
    unset($_SESSION['teacher_referral_form'], $_SESSION['teacher_referral_errors']);
    unset($_SESSION['error_message'], $_SESSION['warning_message']);
    $_SESSION['success_message'] = 'Referral submitted successfully.';
    $_SESSION['teacher_referral_created_context'] = [
        'student_id' => (int) $context['student_id'],
        'operational_class_id' => (int) $classId,
    ];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[APRISM Referral] ' . $e->getMessage());
    $_SESSION['teacher_referral_form'] = $formState;
    unset($_SESSION['success_message'], $_SESSION['warning_message']);
    $_SESSION['error_message'] = $e instanceof DomainException
        ? $e->getMessage()
        : 'The referral could not be saved. Please try again.';
    if ($e instanceof DomainException) {
        $_SESSION['teacher_referral_errors'] = ['operational_class_id' => $e->getMessage()];
    }
}
header('Location: ' . ($saved ? $successRedirect : $redirect));
exit;
