<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
$databaseName = (string) (APRISM_CONFIG['database']['name'] ?? '');
if (!str_starts_with($databaseName, 'aprism_handover_qa_')) {
    fwrite(STDERR, "Refusing to run outside an isolated handover QA database.\n");
    exit(2);
}
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/attendance_helper.php';
require_once __DIR__ . '/../includes/helper/operational_class_handover_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_reports_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_analytics_helper.php';

$_SESSION['user_id'] = 1;

function handoverAttendanceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$identity = $pdo->query("SELECT user_id, email FROM users WHERE user_id IN (1, 2, 3) ORDER BY user_id")->fetchAll(PDO::FETCH_KEY_PAIR);
handoverAttendanceAssert(($identity[1] ?? '') === 'qa_admin@dasmarinas.sti.edu.ph'
    && ($identity[2] ?? '') === 'qa_original@dasmarinas.sti.edu.ph'
    && ($identity[3] ?? '') === 'qa_replacement@dasmarinas.sti.edu.ph', 'Synthetic QA identities do not match expected accounts.');

$classId = 1;
$originalTeacherId = (int) $pdo->query("SELECT teacher_id FROM operational_classes WHERE operational_class_id = {$classId}")->fetchColumn();
handoverAttendanceAssert(in_array($originalTeacherId, [2, 3], true), 'QA class must belong to one of the isolated synthetic Teachers.');
$replacementTeacherId = $originalTeacherId === 2 ? 3 : 2;
$owner = $pdo->prepare('SELECT teacher_id FROM operational_classes WHERE operational_class_id = ? FOR UPDATE');
$owner->execute([$classId]);
handoverAttendanceAssert((int) $owner->fetchColumn() === $originalTeacherId, 'QA class ownership changed during test setup.');

$handoverCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM operational_class_handovers')->fetchColumn();
$handoverAuditCountBefore = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'Operational Class Handover'")->fetchColumn();
$existingAttendanceRowsBefore = $pdo->query('SELECT * FROM student_attendance_records ORDER BY student_attendance_record_id')->fetchAll(PDO::FETCH_ASSOC);
$pdo->exec("CREATE TRIGGER qa_attendance_handover_audit_failure BEFORE INSERT ON audit_logs
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic audit failure'");
try {
    try {
        aprismHandoverOperationalClass($pdo, $classId, $originalTeacherId, $replacementTeacherId, 1, 'Synthetic attendance audit rollback QA');
        throw new RuntimeException('Unexpected handover success.');
    } catch (RuntimeException $exception) {
        handoverAttendanceAssert(str_contains(strtolower($exception->getMessage()), 'audit'), 'Unexpected handover audit failure response.');
    }
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS qa_attendance_handover_audit_failure');
}
$owner->execute([$classId]);
handoverAttendanceAssert((int) $owner->fetchColumn() === $originalTeacherId, 'Failed audit did not roll back class ownership.');
handoverAttendanceAssert((int) $pdo->query('SELECT COUNT(*) FROM operational_class_handovers')->fetchColumn() === $handoverCountBefore, 'Failed audit left handover history behind.');
handoverAttendanceAssert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'Operational Class Handover'")->fetchColumn() === $handoverAuditCountBefore, 'Failed audit left a handover audit record behind.');

$scheduleId = (int) $pdo->query("SELECT class_schedule_id FROM class_schedules WHERE operational_class_id = {$classId} ORDER BY class_schedule_id LIMIT 1")->fetchColumn();
$enrollmentId = (int) $pdo->query("SELECT student_class_enrollment_id FROM student_class_enrollments WHERE operational_class_id = {$classId} AND status = 'Active' ORDER BY student_class_enrollment_id LIMIT 1")->fetchColumn();
handoverAttendanceAssert($scheduleId > 0 && $enrollmentId > 0, 'Isolated QA class needs a schedule and active SCE.');

$attendanceBefore = (int) $pdo->query('SELECT COUNT(*) FROM student_attendance_records')->fetchColumn();
$unattributedSessionsBefore = (int) $pdo->query('SELECT COUNT(*) FROM attendance_sessions WHERE conducted_by_user_id IS NULL')->fetchColumn();
$stamp = date('Y-m-d H:i:s');
$meetingDate = '2030-01-01';
while (true) {
    $check = $pdo->prepare('SELECT 1 FROM attendance_sessions WHERE class_schedule_id = ? AND meeting_date = ?');
    $check->execute([$scheduleId, $meetingDate]);
    if ($check->fetchColumn() === false) {
        break;
    }
    $meetingDate = (new DateTimeImmutable($meetingDate))->modify('+1 day')->format('Y-m-d');
}

$sessionInsert = $pdo->prepare("INSERT INTO attendance_sessions
    (operational_class_id, conducted_by_user_id, class_schedule_id, meeting_date,
     scheduled_day, scheduled_start_time, scheduled_end_time, status, opened_at, closed_at)
    SELECT ?, ?, ?, ?, cs.day, cs.start_time, cs.end_time, 'Closed', ?, ?
    FROM class_schedules cs WHERE cs.class_schedule_id = ?");
$sessionInsert->execute([$classId, $originalTeacherId, $scheduleId, $meetingDate, $stamp, $stamp, $scheduleId]);
$sessionId = (int) $pdo->lastInsertId();
handoverAttendanceAssert((int) $pdo->query('SELECT COUNT(*) FROM attendance_sessions WHERE conducted_by_user_id IS NULL')->fetchColumn() === $unattributedSessionsBefore, 'The migration/test unexpectedly attributed a pre-existing session.');
$recordInsert = $pdo->prepare("INSERT INTO student_attendance_records
    (attendance_session_id, student_class_enrollment_id, status, checked_in_at, capture_source)
    VALUES (?, ?, 'Present', ?, 'Browser Camera AprilTag')");
$recordInsert->execute([$sessionId, $enrollmentId, $stamp]);

$pdo->beginTransaction();
$oldAccess = aprismResolveAttendanceScanSession($pdo, $sessionId, $originalTeacherId);
$pdo->rollBack();
handoverAttendanceAssert($oldAccess['allowed'] === true, 'Original conductor could not resolve their session before transfer.');

aprismHandoverOperationalClass($pdo, $classId, $originalTeacherId, $replacementTeacherId, 1, 'Synthetic attendance handover QA');
$pdo->beginTransaction();
$formerAccess = aprismResolveAttendanceScanSession($pdo, $sessionId, $originalTeacherId);
$pdo->rollBack();
$pdo->beginTransaction();
$replacementAccess = aprismResolveAttendanceScanSession($pdo, $sessionId, $replacementTeacherId);
$pdo->rollBack();
handoverAttendanceAssert($formerAccess['allowed'] === false && $formerAccess['code'] === 'responsibility_changed', 'Former Teacher was not safely rejected with recoverable device feedback.');
handoverAttendanceAssert($replacementAccess['allowed'] === false && $replacementAccess['code'] === 'session_conductor_mismatch', 'Replacement Teacher was allowed to impersonate the original session conductor.');
handoverAttendanceAssert((int) $pdo->query('SELECT COUNT(*) FROM student_attendance_records')->fetchColumn() === $attendanceBefore + 1, 'Rejected stale synchronization changed attendance records.');

$reportFilters = [
    'search' => '', 'school_year_id' => 0, 'semester' => 'All', 'teacher_id' => $replacementTeacherId,
    'section_id' => 0, 'operational_class_id' => $classId, 'status' => 'All',
];
$attendanceRows = aprismAcademicHeadAttendanceRows($pdo, $reportFilters, 100, 0);
$classReport = array_values(array_filter($attendanceRows, static fn (array $row): bool => (int) $row['operational_class_id'] === $classId))[0] ?? null;
handoverAttendanceAssert($classReport !== null, 'Attendance report omitted the transferred class.');
$replacementNameStmt = $pdo->prepare("SELECT CONCAT_WS(' ', first_name, last_name) FROM users WHERE user_id = ?");
$replacementNameStmt->execute([$replacementTeacherId]);
handoverAttendanceAssert((string) $classReport['teacher_name'] === (string) $replacementNameStmt->fetchColumn(), 'Current Teacher report value is incorrect.');
handoverAttendanceAssert(str_contains((string) $classReport['conducting_teacher_names'], 'Not recorded'), 'Historical sessions without a known conductor are not clearly distinguished.');
$originalNameStmt = $pdo->prepare("SELECT CONCAT_WS(' ', first_name, last_name) FROM users WHERE user_id = ?");
$originalNameStmt->execute([$originalTeacherId]);
$originalTeacherName = (string) $originalNameStmt->fetchColumn();
handoverAttendanceAssert(str_contains((string) $classReport['conducting_teacher_names'], $originalTeacherName), 'Attendance report did not retain the original recorded conductor.');

$sessionRows = aprismAcademicHeadAttendanceSessionRows($pdo, $reportFilters, 12);
$sessionReport = array_values(array_filter($sessionRows, static fn (array $row): bool => (int) $row['attendance_session_id'] === $sessionId))[0] ?? null;
handoverAttendanceAssert($sessionReport !== null, 'Session report omitted the synthetic meeting.');
handoverAttendanceAssert((string) $sessionReport['conducted_by_teacher_name'] === $originalTeacherName, 'Session report did not identify the original conducting Teacher.');
$teacherAnalytics = aprismTeacherAnalyticsAttendance($pdo, $replacementTeacherId, $classId);
$analyticsSession = array_values(array_filter(
    $teacherAnalytics['sessions'],
    static fn (array $row): bool => (int) $row['attendance_session_id'] === $sessionId
))[0] ?? null;
handoverAttendanceAssert($analyticsSession !== null, 'Replacement Teacher analytics omitted the historical session.');
handoverAttendanceAssert((string) $analyticsSession['conducted_by_teacher_name'] === $originalTeacherName, 'Teacher analytics did not identify the original conducting Teacher.');
$existingAttendanceRowsAfter = $pdo->query("SELECT * FROM student_attendance_records WHERE attendance_session_id <> {$sessionId} ORDER BY student_attendance_record_id")->fetchAll(PDO::FETCH_ASSOC);
handoverAttendanceAssert($existingAttendanceRowsAfter === $existingAttendanceRowsBefore, 'Existing attendance records changed during the handover test.');

echo "PASS: handover audit rollback, unchanged legacy attribution, future session attribution, former-teacher rejection, replacement impersonation rejection, no attendance duplication, and current-vs-recorded report identity.\n";
