<?php

declare(strict_types=1);

$apiResponseMode = true;
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Notification request could not be verified.']); exit;
}

$userId = (int) $_SESSION['user_id'];
$operation = (string) ($_POST['operation'] ?? '');
if ($operation === 'mark_all_read') {
    $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at, NOW()) WHERE target_user_id=? AND archived_at IS NULL');
    $stmt->execute([$userId]);
} elseif ($operation === 'mark_read') {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    if (!$notificationId) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid notification.']); exit; }
    $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at, NOW()) WHERE notification_id=? AND target_user_id=?');
    $stmt->execute([$notificationId, $userId]);
} else { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Unsupported notification operation.']); exit; }
echo json_encode(['success' => true, 'unread_count' => aprismUnreadNotificationCount($pdo, $userId)]);
