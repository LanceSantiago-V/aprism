<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

$apiResponseMode = true;
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../../auth/session_guard.php';

function saveClassScheduleReply(bool $success, string $message, int $status = 200, array $data = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    saveClassScheduleReply(false, 'Invalid schedule save request.', 405);
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    saveClassScheduleReply(false, 'The request could not be verified. Refresh the page and try again.', 403);
}

$classId = filter_var($_POST['operational_class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$submitted = json_decode((string) ($_POST['schedules'] ?? ''), true);
if ($classId === false || !is_array($submitted) || count($submitted) > 40) {
    saveClassScheduleReply(false, 'The class or meeting list is invalid. Review the schedule and try again.', 422);
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$normalizeTime = static function (mixed $raw, string $name): string {
    $time = DateTime::createFromFormat('!H:i', trim((string) $raw));
    $errors = DateTime::getLastErrors();
    if ($time === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new RuntimeException($name . ' must be a valid time.');
    }
    return $time->format('H:i:s');
};

try {
    $pdo->beginTransaction();
    $classStmt = $pdo->prepare("SELECT oc.operational_class_id, oc.section_id, oc.school_year, oc.semester, s.subject_name, sec.section_name FROM operational_classes oc INNER JOIN school_years sy ON sy.school_year = oc.school_year AND sy.status = 'Active' INNER JOIN subjects s ON s.subject_id = oc.subject_id INNER JOIN sections sec ON sec.section_id = oc.section_id WHERE oc.operational_class_id = ? AND oc.teacher_id = ? AND oc.status = 'Active' LIMIT 1 FOR UPDATE");
    $classStmt->execute([(int) $classId, (int) $_SESSION['user_id']]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
    if (!$class) {
        throw new DomainException('This Operational Class is unavailable or you do not have permission to edit it.');
    }

    $existingStmt = $pdo->prepare("SELECT class_schedule_id, day, start_time, end_time, room FROM class_schedules WHERE operational_class_id = ? AND status = 'Active' ORDER BY class_schedule_id FOR UPDATE");
    $existingStmt->execute([(int) $classId]);
    $existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC);
    $existingById = [];
    foreach ($existingRows as $row) {
        $existingById[(int) $row['class_schedule_id']] = $row;
    }

    $draft = [];
    $seenIds = [];
    foreach ($submitted as $index => $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Meeting ' . ($index + 1) . ' is invalid.');
        }
        $day = trim((string) ($item['day'] ?? ''));
        if (!in_array($day, $days, true)) {
            throw new RuntimeException('Meeting ' . ($index + 1) . ' must use Monday through Saturday.');
        }
        $start = $normalizeTime($item['startTime'] ?? '', 'Start time');
        $end = $normalizeTime($item['endTime'] ?? '', 'End time');
        if ($end <= $start) {
            throw new RuntimeException('Meeting ' . ($index + 1) . ' must end after it starts.');
        }
        $room = trim((string) ($item['room'] ?? ''));
        if (strlen($room) > 100) {
            throw new RuntimeException('Room must not exceed 100 characters.');
        }
        $id = isset($item['classScheduleId']) && $item['classScheduleId'] !== '' ? filter_var($item['classScheduleId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
        if ($id === false || ($id !== null && (!isset($existingById[(int) $id]) || isset($seenIds[(int) $id])))) {
            throw new DomainException('A meeting no longer belongs to this class. Refresh My Classes and retry.');
        }
        if ($id !== null) {
            $seenIds[(int) $id] = true;
        }
        $draft[] = ['classScheduleId' => $id === null ? null : (int) $id, 'day' => $day, 'startTime' => $start, 'endTime' => $end, 'room' => $room === '' ? null : $room];
    }

    for ($i = 0; $i < count($draft); $i++) {
        for ($j = $i + 1; $j < count($draft); $j++) {
            if ($draft[$i]['day'] === $draft[$j]['day'] && $draft[$i]['startTime'] < $draft[$j]['endTime'] && $draft[$i]['endTime'] > $draft[$j]['startTime']) {
                throw new RuntimeException('Two meetings in this class overlap. Adjust their day or times.');
            }
        }
    }

    $othersStmt = $pdo->prepare("SELECT cs.class_schedule_id, cs.operational_class_id, cs.day, cs.start_time, cs.end_time, cs.room, oc.teacher_id, oc.section_id FROM class_schedules cs INNER JOIN operational_classes oc ON oc.operational_class_id = cs.operational_class_id WHERE cs.status = 'Active' AND oc.status = 'Active' AND oc.school_year = ? AND oc.semester = ? AND oc.operational_class_id <> ? AND (oc.teacher_id = ? OR oc.section_id = ? OR (cs.room IS NOT NULL AND TRIM(cs.room) <> '')) FOR UPDATE");
    $othersStmt->execute([$class['school_year'], $class['semester'], (int) $classId, (int) $_SESSION['user_id'], (int) $class['section_id']]);
    $otherRows = $othersStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($draft as $meeting) {
        foreach ($otherRows as $other) {
            if ($meeting['day'] !== $other['day'] || $meeting['startTime'] >= $other['end_time'] || $meeting['endTime'] <= $other['start_time']) {
                continue;
            }
            if ((int) $other['teacher_id'] === (int) $_SESSION['user_id']) {
                throw new RuntimeException('This time conflicts with another active class assigned to you.');
            }
            if ((int) $other['section_id'] === (int) $class['section_id']) {
                throw new RuntimeException('This time conflicts with another class assigned to the same Section.');
            }
            if ($meeting['room'] !== null && trim((string) ($other['room'] ?? '')) !== '' && strcasecmp(trim($meeting['room']), trim((string) $other['room'])) === 0) {
                throw new RuntimeException('Room ' . $meeting['room'] . ' is already occupied during this time.');
            }
        }
    }

    $archiveOmitted = $pdo->prepare("UPDATE class_schedules SET status = 'Archived' WHERE class_schedule_id = ? AND operational_class_id = ? AND status = 'Active'");
    foreach ($existingById as $id => $_row) {
        if (!isset($seenIds[$id])) {
            $archiveOmitted->execute([$id, (int) $classId]);
        }
    }

    // Move retained rows through unique-safe temporary room values before applying the final draft.
    $temporaryRoom = $pdo->prepare("UPDATE class_schedules SET room = ? WHERE class_schedule_id = ? AND operational_class_id = ? AND status = 'Active'");
    foreach ($draft as $index => &$meeting) {
        if ($meeting['classScheduleId'] !== null) {
            $temporaryRoom->execute(['APRISM-TMP-' . bin2hex(random_bytes(10)) . '-' . $index, $meeting['classScheduleId'], (int) $classId]);
        }
    }
    unset($meeting);

    $update = $pdo->prepare("UPDATE class_schedules SET day = ?, start_time = ?, end_time = ?, room = ?, status = 'Active' WHERE class_schedule_id = ? AND operational_class_id = ?");
    $insert = $pdo->prepare("INSERT INTO class_schedules (operational_class_id, day, start_time, end_time, room, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    $findArchived = $pdo->prepare("SELECT class_schedule_id FROM class_schedules WHERE operational_class_id = ? AND day = ? AND start_time = ? AND end_time = ? AND (room <=> ?) AND status = 'Archived' ORDER BY class_schedule_id DESC LIMIT 1 FOR UPDATE");
    $reactivate = $pdo->prepare("UPDATE class_schedules SET status = 'Active' WHERE class_schedule_id = ? AND operational_class_id = ?");
    $archiveRetained = $pdo->prepare("UPDATE class_schedules SET status = 'Archived' WHERE class_schedule_id = ? AND operational_class_id = ? AND status = 'Active'");
    foreach ($draft as $meeting) {
        if ($meeting['classScheduleId'] !== null) {
            $findArchived->execute([(int) $classId, $meeting['day'], $meeting['startTime'], $meeting['endTime'], $meeting['room']]);
            $archivedId = $findArchived->fetchColumn();
            if ($archivedId !== false) {
                $archiveRetained->execute([$meeting['classScheduleId'], (int) $classId]);
                $reactivate->execute([(int) $archivedId, (int) $classId]);
            } else {
                $update->execute([$meeting['day'], $meeting['startTime'], $meeting['endTime'], $meeting['room'], $meeting['classScheduleId'], (int) $classId]);
            }
            continue;
        }
        $findArchived->execute([(int) $classId, $meeting['day'], $meeting['startTime'], $meeting['endTime'], $meeting['room']]);
        $archivedId = $findArchived->fetchColumn();
        if ($archivedId !== false) {
            $reactivate->execute([(int) $archivedId, (int) $classId]);
        } else {
            $insert->execute([(int) $classId, $meeting['day'], $meeting['startTime'], $meeting['endTime'], $meeting['room']]);
        }
    }

    logAudit($pdo, 'Update Class Schedule', 'Updated schedule for Subject "' . $class['subject_name'] . '" and Section "' . $class['section_name'] . '".');
    $pdo->commit();
    saveClassScheduleReply(true, 'Schedule saved successfully.', 200, ['operational_class_id' => (int) $classId]);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    saveClassScheduleReply(false, $e->getMessage(), 403);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[APRISM Save Class Schedule] Database operation failed: ' . $e->getMessage());
    saveClassScheduleReply(false, 'The schedule could not be saved. No changes were committed.', 500);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    saveClassScheduleReply(false, $e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[APRISM Save Class Schedule] ' . $e->getMessage());
    saveClassScheduleReply(false, 'The schedule could not be saved. No changes were committed.', 500);
}
