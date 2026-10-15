<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
$apiResponseMode = true;
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/teacher_referral_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$respond = static function (int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $respond(403, ['ok' => false, 'message' => 'The referral context could not be verified. Refresh the page and try again.']);
}

$classId = filter_input(INPUT_POST, 'operational_class_id', FILTER_VALIDATE_INT) ?: 0;
$sceId = filter_input(INPUT_POST, 'student_class_enrollment_id', FILTER_VALIDATE_INT) ?: 0;
$sourceContext = (string) ($_POST['source_context'] ?? 'referrals');
if (!in_array($sourceContext, ['risk_alerts', 'referrals'], true)) {
    $sourceContext = 'referrals';
}
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$evidence = $classId > 0 && $sceId > 0
    ? aprismTeacherReferralAttendanceEvidence($pdo, $teacherId, $classId, $sceId)
    : null;
if ($evidence === null) {
    $respond(404, ['ok' => false, 'message' => 'Choose an active Student from one of your classes.']);
}

$snapshot = aprismTeacherReferralEvidenceSnapshot($evidence, $sourceContext);
$token = aprismTeacherReferralIssueEvidenceToken($snapshot);
$respond(200, ['ok' => true, 'snapshot' => $snapshot, 'token' => $token]);
