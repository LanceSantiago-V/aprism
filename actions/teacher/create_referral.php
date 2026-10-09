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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Your referral request could not be verified.';
    header('Location: ' . APP_URL . '/dashboard/teacher_referrals.php');
    exit;
}

$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$classId = filter_input(INPUT_POST, 'operational_class_id', FILTER_VALIDATE_INT);
$sceId = filter_input(INPUT_POST, 'student_class_enrollment_id', FILTER_VALIDATE_INT);
$reason = trim((string) ($_POST['reason'] ?? ''));
$redirect = APP_URL . '/dashboard/teacher_referrals.php' . ($classId ? '?class_id=' . (int) $classId : '');

if (!$classId || !$sceId || $reason === '' || mb_strlen($reason) > 5000) {
    $_SESSION['error_message'] = 'Select a class and Student, then provide a concise concern description.';
    header('Location: ' . $redirect);
    exit;
}

$context = aprismTeacherReferralContext($pdo, $teacherId, (int) $classId, (int) $sceId);
if ($context === null) {
    $_SESSION['error_message'] = 'The selected Student is not an active participant in one of your classes.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO teacher_referrals (teacher_id, student_id, operational_class_id, student_class_enrollment_id, school_year_snapshot, semester_snapshot, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted')");
    $stmt->execute([$teacherId, (int) $context['student_id'], (int) $classId, (int) $sceId, $context['school_year'], $context['semester'], $reason]);
    $referralId = (int) $pdo->lastInsertId();
    foreach (aprismGuidanceUserIds($pdo) as $guidanceUserId) {
        aprismNotify($pdo, $guidanceUserId, 'teacher_referral_submitted', 'New Teacher referral', 'A Teacher referral is ready for Guidance review.', APP_URL . '/dashboard/guidance_referrals.php', 'teacher-referral-submitted-' . $referralId, 'teacher_referral', $referralId);
    }
    logAudit($pdo, 'Create Teacher Referral', 'Created a Teacher referral record.');
    $_SESSION['success_message'] = 'Referral submitted for downstream handling.';
} catch (Throwable $e) {
    error_log('[APRISM Referral] ' . $e->getMessage());
    $_SESSION['error_message'] = 'The referral could not be saved. Please try again.';
}
header('Location: ' . $redirect);
exit;
