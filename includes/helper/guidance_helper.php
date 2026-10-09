<?php

declare(strict_types=1);

require_once __DIR__ . '/notification_helper.php';

function aprismGuidanceCaseNumber(int $caseId): string
{
    return 'GDN-' . date('Y') . '-' . str_pad((string) $caseId, 5, '0', STR_PAD_LEFT);
}

function aprismGuidanceCase(PDO $pdo, int $caseId): ?array
{
    $stmt = $pdo->prepare("SELECT gc.*, tr.student_id, tr.teacher_id AS origin_teacher_id, tr.operational_class_id, tr.reason, tr.status AS referral_status,
        CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) AS student_name, s.student_number,
        CONCAT_WS(' ', origin.first_name, origin.last_name) AS origin_teacher_name,
        CONCAT_WS(' ', assignee.first_name, assignee.last_name) AS assignee_name
        FROM guidance_cases gc
        INNER JOIN teacher_referrals tr ON tr.teacher_referral_id=gc.teacher_referral_id
        INNER JOIN students s ON s.student_id=tr.student_id
        INNER JOIN users origin ON origin.user_id=tr.teacher_id
        LEFT JOIN users assignee ON assignee.user_id=gc.current_assignee_user_id
        WHERE gc.guidance_case_id=? LIMIT 1");
    $stmt->execute([$caseId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Confidential case material is available only to the currently assigned
 * Guidance Officer or a recorded case assignee.  Queue access to unaccepted
 * referrals is deliberately handled separately.
 */
function aprismGuidanceCaseForUser(PDO $pdo, int $caseId, int $userId): ?array
{
    $case = aprismGuidanceCase($pdo, $caseId);
    if ($case === null) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM guidance_case_assignments WHERE guidance_case_id = ? AND assigned_user_id = ? AND ended_at IS NULL LIMIT 1');
    $stmt->execute([$caseId, $userId]);
    if ((int) $case['current_assignee_user_id'] !== $userId && !$stmt->fetchColumn()) {
        return null;
    }

    return $case;
}

function aprismGuidanceCaseEvents(PDO $pdo, int $caseId): array
{
    $stmt = $pdo->prepare("SELECT e.*, CONCAT_WS(' ', u.first_name, u.last_name) AS actor_name
        FROM guidance_case_events e INNER JOIN users u ON u.user_id=e.actor_user_id
        WHERE e.guidance_case_id=? ORDER BY e.created_at ASC, e.guidance_case_event_id ASC");
    $stmt->execute([$caseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismGuidanceConfidentialNotes(PDO $pdo, int $caseId): array
{
    $stmt = $pdo->prepare("SELECT n.*, CONCAT_WS(' ', u.first_name, u.last_name) AS author_name
        FROM guidance_confidential_notes n INNER JOIN users u ON u.user_id=n.author_user_id
        WHERE n.guidance_case_id=? ORDER BY n.created_at ASC, n.guidance_confidential_note_id ASC");
    $stmt->execute([$caseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismGuidanceFollowups(PDO $pdo, int $caseId): array
{
    $stmt = $pdo->prepare("SELECT f.*, CONCAT_WS(' ', u.first_name, u.last_name) AS assignee_name
        FROM guidance_followups f INNER JOIN users u ON u.user_id=f.assigned_user_id
        WHERE f.guidance_case_id=? ORDER BY f.scheduled_at ASC, f.guidance_followup_id ASC");
    $stmt->execute([$caseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismActiveGuidanceOfficers(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT user_id, CONCAT_WS(\' \', first_name, last_name) AS display_name FROM users WHERE role_id = ? AND account_status = \'Active\' ORDER BY last_name, first_name');
    $stmt->execute([ROLE_DISCIPLINARY_OFFICER]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismGuidanceLogEvent(PDO $pdo, int $caseId, int $actorId, string $type, string $summary): void
{
    $stmt = $pdo->prepare('INSERT INTO guidance_case_events (guidance_case_id, event_type, event_summary, actor_user_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$caseId, $type, $summary, $actorId]);
}
