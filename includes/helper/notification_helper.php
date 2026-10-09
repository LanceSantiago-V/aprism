<?php

declare(strict_types=1);

/** Shared, target-scoped in-app notification helpers. */
function aprismNotify(PDO $pdo, int $targetUserId, string $type, string $title, string $message, ?string $contextUrl, string $deduplicationKey, ?string $entityType = null, ?int $entityId = null, ?string $expiresAt = null): void
{
    if ($targetUserId < 1 || $deduplicationKey === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO notifications (target_user_id, notification_type, title, message, context_url, source_entity_type, source_entity_id, deduplication_key, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title), message = VALUES(message), context_url = VALUES(context_url),
            expires_at = VALUES(expires_at), archived_at = NULL'
    );
    $stmt->execute([$targetUserId, $type, $title, $message, $contextUrl, $entityType, $entityId, $deduplicationKey, $expiresAt]);
}

function aprismUnreadNotificationCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE target_user_id=? AND read_at IS NULL AND archived_at IS NULL AND (expires_at IS NULL OR expires_at >= NOW())');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function aprismRecentNotifications(PDO $pdo, int $userId, int $limit = 6): array
{
    $limit = max(1, min(20, $limit));
    $stmt = $pdo->prepare("SELECT notification_id, notification_type, title, message, context_url, created_at, read_at
        FROM notifications
        WHERE target_user_id=? AND archived_at IS NULL AND (expires_at IS NULL OR expires_at >= NOW())
        ORDER BY created_at DESC, notification_id DESC LIMIT {$limit}");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismGuidanceUserIds(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE role_id=? AND account_status='Active'");
    $stmt->execute([ROLE_DISCIPLINARY_OFFICER]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Request-time reminder only: APRISM never claims closed-browser push. A
 * Teacher who opens an authenticated page during the five-minute window gets
 * one deduplicated reminder for the actual scheduled occurrence.
 */
function aprismQueueTeacherClassReminders(PDO $pdo, int $teacherId, ?string $schoolYear): void
{
    if ($teacherId < 1 || $schoolYear === null || $schoolYear === '') {
        return;
    }

    $stmt = $pdo->prepare("SELECT cs.class_schedule_id, oc.operational_class_id, s.subject_name, sec.section_name, cs.start_time
        FROM class_schedules cs
        INNER JOIN operational_classes oc ON oc.operational_class_id = cs.operational_class_id
        INNER JOIN subjects s ON s.subject_id = oc.subject_id
        INNER JOIN sections sec ON sec.section_id = oc.section_id
        WHERE oc.teacher_id = ? AND oc.school_year = ? AND oc.status = 'Active' AND cs.status = 'Active'
          AND cs.day = DAYNAME(CURDATE())
          AND NOW() >= DATE_SUB(TIMESTAMP(CURDATE(), cs.start_time), INTERVAL 5 MINUTE)
          AND NOW() < DATE_ADD(TIMESTAMP(CURDATE(), cs.start_time), INTERVAL 5 MINUTE)");
    $stmt->execute([$teacherId, $schoolYear]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $class) {
        $key = 'class-reminder-' . (int) $class['class_schedule_id'] . '-' . date('Y-m-d');
        aprismNotify(
            $pdo,
            $teacherId,
            'class_starting',
            'Class starting soon',
            $class['subject_name'] . ' · ' . $class['section_name'] . ' starts at ' . date('g:i A', strtotime((string) $class['start_time'])) . '.',
            APP_URL . '/dashboard/teacher_attendance.php?operational_class_id=' . (int) $class['operational_class_id'],
            $key,
            'class_schedule',
            (int) $class['class_schedule_id'],
            date('Y-m-d 23:59:59')
        );
    }
}
