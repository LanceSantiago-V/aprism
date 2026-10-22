<?php

declare(strict_types=1);

require_once __DIR__ . '/audit_helper.php';

/**
 * Change only the current class responsibility. All academic and actor records
 * remain attached to their existing IDs. The class row serializes handovers.
 *
 * @return array{class_id:int,from_teacher_id:int,to_teacher_id:int,effective_at:string}
 */
function aprismHandoverOperationalClass(
    PDO $pdo,
    int $classId,
    int $expectedTeacherId,
    int $replacementTeacherId,
    int $authorizerId,
    string $reason
): array {
    $reason = trim($reason);
    if ($classId <= 0 || $expectedTeacherId <= 0 || $replacementTeacherId <= 0
        || $authorizerId <= 0 || (int) ($_SESSION['user_id'] ?? 0) !== $authorizerId) {
        throw new DomainException('The handover request could not be verified.');
    }
    if ($reason === '' || mb_strlen($reason) > 1000) {
        throw new DomainException('Provide a handover reason of at most 1,000 characters.');
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('A class handover must start its own transaction.');
    }

    try {
        $pdo->beginTransaction();

        $classStmt = $pdo->prepare("SELECT oc.operational_class_id, oc.teacher_id, oc.subject_id,
                oc.section_id, oc.school_year, oc.semester, oc.status
            FROM operational_classes oc
            INNER JOIN school_years sy ON sy.school_year = oc.school_year AND sy.status = 'Active'
            WHERE oc.operational_class_id = ? AND oc.status = 'Active'
            LIMIT 1 FOR UPDATE");
        $classStmt->execute([$classId]);
        $class = $classStmt->fetch(PDO::FETCH_ASSOC);
        if ($class === false) {
            throw new DomainException('Select an active class in the active School Year.');
        }
        if ((int) $class['teacher_id'] !== $expectedTeacherId) {
            throw new DomainException('This class has already changed Teacher. Refresh and review its current assignment.');
        }
        if ($expectedTeacherId === $replacementTeacherId) {
            throw new DomainException('Choose a different Teacher for this class.');
        }

        $teacherStmt = $pdo->prepare("SELECT u.user_id FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'
            WHERE u.user_id = ? AND u.account_status = 'Active' LIMIT 1 FOR UPDATE");
        $teacherStmt->execute([$replacementTeacherId]);
        if ($teacherStmt->fetchColumn() === false) {
            throw new DomainException('Choose an active Teacher account.');
        }

        // The existing unique key includes archived classes, so reject either status.
        $duplicateStmt = $pdo->prepare('SELECT operational_class_id FROM operational_classes
            WHERE teacher_id = ? AND subject_id = ? AND section_id = ?
              AND school_year = ? AND semester = ? AND operational_class_id <> ?
            LIMIT 1 FOR UPDATE');
        $duplicateStmt->execute([
            $replacementTeacherId, (int) $class['subject_id'], (int) $class['section_id'],
            $class['school_year'], $class['semester'], $classId,
        ]);
        if ($duplicateStmt->fetchColumn() !== false) {
            throw new DomainException('The replacement Teacher already has this Subject and Section in the same teaching context.');
        }

        $openStmt = $pdo->prepare("SELECT attendance_session_id FROM attendance_sessions
            WHERE operational_class_id = ? AND status = 'Open' LIMIT 1 FOR UPDATE");
        $openStmt->execute([$classId]);
        if ($openStmt->fetchColumn() !== false) {
            throw new DomainException('Close the class Attendance Session and sync any saved scans before handing it over.');
        }

        $pendingStmt = $pdo->prepare("SELECT grade_import_batch_id FROM grade_import_batches
            WHERE operational_class_id = ? AND status IN ('Review', 'Validated') LIMIT 1 FOR UPDATE");
        $pendingStmt->execute([$classId]);
        if ($pendingStmt->fetchColumn() !== false) {
            throw new DomainException('Resolve the class Grade Import review before handing it over.');
        }

        $sourceStmt = $pdo->prepare("SELECT day, start_time, end_time FROM class_schedules
            WHERE operational_class_id = ? AND status = 'Active' FOR UPDATE");
        $sourceStmt->execute([$classId]);
        $sourceMeetings = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);
        $otherStmt = $pdo->prepare("SELECT cs.day, cs.start_time, cs.end_time
            FROM operational_classes oc
            INNER JOIN class_schedules cs ON cs.operational_class_id = oc.operational_class_id
            WHERE oc.teacher_id = ? AND oc.school_year = ? AND oc.semester = ?
              AND oc.status = 'Active' AND cs.status = 'Active'
              AND oc.operational_class_id <> ? FOR UPDATE");
        $otherStmt->execute([$replacementTeacherId, $class['school_year'], $class['semester'], $classId]);
        $otherMeetings = $otherStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sourceMeetings as $source) {
            foreach ($otherMeetings as $other) {
                if ($source['day'] === $other['day']
                    && $source['start_time'] < $other['end_time']
                    && $source['end_time'] > $other['start_time']) {
                    throw new DomainException('The replacement Teacher has another active class at this time.');
                }
            }
        }

        $effectiveAt = (string) $pdo->query('SELECT CURRENT_TIMESTAMP(6)')->fetchColumn();
        $update = $pdo->prepare("UPDATE operational_classes SET teacher_id = ?
            WHERE operational_class_id = ? AND teacher_id = ? AND status = 'Active'");
        $update->execute([$replacementTeacherId, $classId, $expectedTeacherId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Class responsibility changed during handover.');
        }

        $history = $pdo->prepare('INSERT INTO operational_class_handovers
            (operational_class_id, from_teacher_id, to_teacher_id, authorized_by_user_id, effective_at, reason)
            VALUES (?, ?, ?, ?, ?, ?)');
        $history->execute([$classId, $expectedTeacherId, $replacementTeacherId, $authorizerId, $effectiveAt, $reason]);

        if (!logAudit($pdo, 'Operational Class Handover', sprintf(
            'Class #%d: Teacher #%d to Teacher #%d, authorized by User #%d, effective %s. Reason: %s',
            $classId, $expectedTeacherId, $replacementTeacherId, $authorizerId, $effectiveAt, $reason
        ))) {
            throw new RuntimeException('Required handover audit could not be saved.');
        }
        if (!$pdo->commit()) {
            throw new RuntimeException('Class handover could not be committed.');
        }
        return [
            'class_id' => $classId,
            'from_teacher_id' => $expectedTeacherId,
            'to_teacher_id' => $replacementTeacherId,
            'effective_at' => $effectiveAt,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
