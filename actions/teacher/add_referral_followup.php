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

$referralId = filter_input(INPUT_POST, 'teacher_referral_id', FILTER_VALIDATE_INT);
$status = trim((string) ($_POST['status_after'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
$redirect = APP_URL . '/dashboard/teacher_referral_detail.php' . ($referralId ? '?referral_id=' . (int) $referralId : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '') || !$referralId) {
    $_SESSION['error_message'] = 'The follow-up request could not be verified.';
    header('Location: ' . $redirect);
    exit;
}

if ($status !== 'Submitted' || $note === '' || mb_strlen($note) > 5000) {
    $_SESSION['error_message'] = 'Select a supported status and provide a concise follow-up note.';
    header('Location: ' . $redirect);
    exit;
}

$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$referral = aprismTeacherReferralDetail($pdo, $teacherId, (int) $referralId);
if ($referral === null || (int) $referral['teacher_id'] !== $teacherId) {
    http_response_code(403);
    exit('You are not allowed to update this referral.');
}

try {
    $pdo->beginTransaction();

    $stateStmt = $pdo->prepare(
        "SELECT tr.status, gc.guidance_case_id
         FROM teacher_referrals tr
         LEFT JOIN guidance_cases gc
           ON gc.teacher_referral_id = tr.teacher_referral_id
         WHERE tr.teacher_referral_id = ?
           AND tr.teacher_id = ?
         FOR UPDATE"
    );
    $stateStmt->execute([(int) $referralId, $teacherId]);
    $currentState = $stateStmt->fetch(PDO::FETCH_ASSOC);
    if ($currentState === false
        || $currentState['status'] !== 'Returned'
        || $currentState['guidance_case_id'] !== null) {
        throw new RuntimeException('Only a returned referral without an opened case can be clarified and resubmitted.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO teacher_referral_followups
            (teacher_referral_id, author_user_id, note, status_after)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([(int) $referralId, $teacherId, $note, $status]);
    $followupId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "UPDATE teacher_referrals
         SET status = 'Submitted', updated_at = CURRENT_TIMESTAMP
         WHERE teacher_referral_id = ? AND teacher_id = ? AND status = 'Returned'"
    );
    $stmt->execute([(int) $referralId, $teacherId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('This referral is no longer available for clarification.');
    }

    foreach (aprismGuidanceUserIds($pdo) as $guidanceUserId) {
        aprismNotify(
            $pdo,
            $guidanceUserId,
            'teacher_referral_clarified',
            'Referral clarification received',
            'A Teacher clarified and resubmitted a returned referral for review.',
            APP_URL . '/dashboard/guidance_referrals.php?status=Submitted',
            'teacher-referral-clarified-' . (int) $referralId . '-' . $followupId,
            'teacher_referral',
            (int) $referralId
        );
    }

    logAudit($pdo, 'Add Teacher Referral Follow-up', 'Added follow-up to a Teacher referral.');
    $pdo->commit();
    $_SESSION['success_message'] = 'Referral clarification submitted for review.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[APRISM Referral Follow-up] ' . $e->getMessage());
    $_SESSION['error_message'] = 'The referral follow-up could not be saved. Please try again.';
}

header('Location: ' . $redirect);
exit;
