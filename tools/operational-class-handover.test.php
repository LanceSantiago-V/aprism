<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
$databaseName = (string) (APRISM_CONFIG['database']['name'] ?? '');
if (!str_starts_with($databaseName, 'aprism_handover_qa_')) {
    fwrite(STDERR, "Refusing to run outside an isolated handover QA database.\n");
    exit(2);
}
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/operational_class_handover_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_reports_helper.php';

$_SESSION['user_id'] = 1;
foreach ([1 => 'qa_admin@dasmarinas.sti.edu.ph', 2 => 'qa_original@dasmarinas.sti.edu.ph', 3 => 'qa_replacement@dasmarinas.sti.edu.ph'] as $id => $email) {
    $check = $pdo->prepare('SELECT email FROM users WHERE user_id = ?');
    $check->execute([$id]);
    if ($check->fetchColumn() !== $email) {
        throw new RuntimeException('Synthetic account identity check failed.');
    }
}

function qaAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function qaOwner(PDO $pdo, int $classId): int
{
    $stmt = $pdo->prepare('SELECT teacher_id FROM operational_classes WHERE operational_class_id = ?');
    $stmt->execute([$classId]);
    return (int) $stmt->fetchColumn();
}

function qaRejected(PDO $pdo, int $classId, int $original, int $replacement, string $expected): void
{
    $currentOwner = qaOwner($pdo, $classId);
    try {
        aprismHandoverOperationalClass($pdo, $classId, $original, $replacement, 1, 'Synthetic QA handover');
        throw new RuntimeException('Unsafe handover was accepted.');
    } catch (DomainException $exception) {
        qaAssert(str_contains($exception->getMessage(), $expected), 'Unexpected rejection: ' . $exception->getMessage());
    }
    qaAssert(qaOwner($pdo, $classId) === $currentOwner, 'Rejected handover changed class ownership.');
}

function qaAcademicSnapshot(PDO $pdo): string
{
    $tables = [
        'class_schedules' => 'class_schedule_id',
        'student_class_enrollments' => 'student_class_enrollment_id',
        'attendance_sessions' => 'attendance_session_id',
        'student_attendance_records' => 'student_attendance_record_id',
        'assessment_categories' => 'assessment_category_id',
        'assessment_components' => 'assessment_component_id',
        'grade_import_batches' => 'grade_import_batch_id',
        'student_assessment_scores' => 'student_assessment_score_id',
        'student_assessment_score_revisions' => 'student_assessment_score_revision_id',
        'teacher_referrals' => 'teacher_referral_id',
        'april_tags' => 'april_tag_id',
        'april_tag_assignments' => 'april_tag_assignment_id',
    ];
    $snapshot = [];
    foreach ($tables as $table => $key) {
        $snapshot[$table] = $pdo->query("SELECT * FROM {$table} ORDER BY {$key}")->fetchAll(PDO::FETCH_ASSOC);
    }
    return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
}

$before = qaAcademicSnapshot($pdo);
qaRejected($pdo, 3, 2, 3, 'another active class');
qaRejected($pdo, 4, 2, 3, 'already has this Subject');
qaRejected($pdo, 1, 2, 4, 'active Teacher');
qaRejected($pdo, 1, 3, 3, 'already changed Teacher');

$pdo->exec("INSERT INTO attendance_sessions (operational_class_id, class_schedule_id, meeting_date,
    scheduled_day, scheduled_start_time, scheduled_end_time, status)
    VALUES (1, 1, '2026-10-26', 'Monday', '09:00:00', '10:00:00', 'Open')");
try {
    qaRejected($pdo, 1, 2, 3, 'Close the class Attendance Session');
} finally {
    $pdo->exec("DELETE FROM attendance_sessions WHERE operational_class_id = 1 AND meeting_date = '2026-10-26' AND status = 'Open'");
}
qaAssert(qaAcademicSnapshot($pdo) === $before, 'Rejected handover changed academic records.');

$pdo->exec("CREATE TRIGGER qa_handover_audit_failure BEFORE INSERT ON audit_logs
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic audit failure'");
try {
    try {
        aprismHandoverOperationalClass($pdo, 1, 2, 3, 1, 'Synthetic QA audit rollback');
        throw new RuntimeException('Audit failure did not prevent handover.');
    } catch (RuntimeException $exception) {
        qaAssert(str_contains($exception->getMessage(), 'audit'), 'Unexpected audit error.');
    }
} finally {
    $pdo->exec('DROP TRIGGER qa_handover_audit_failure');
}
qaAssert(qaOwner($pdo, 1) === 2, 'Audit failure changed class ownership.');
qaAssert((int) $pdo->query('SELECT COUNT(*) FROM operational_class_handovers')->fetchColumn() === 0, 'Audit failure left handover history.');
qaAssert(qaAcademicSnapshot($pdo) === $before, 'Audit failure changed academic records.');

$result = aprismHandoverOperationalClass($pdo, 1, 2, 3, 1, 'Synthetic QA replacement');
qaAssert($result['from_teacher_id'] === 2 && $result['to_teacher_id'] === 3, 'Incorrect result.');
qaAssert(qaOwner($pdo, 1) === 3, 'Replacement did not acquire class.');
qaAssert(count(aprismTeacherConfirmedGradeReport($pdo, 3, 1)) === 1, 'Replacement cannot report pre-handover score.');
qaAssert(count(aprismTeacherConfirmedGradeReport($pdo, 2, 1)) === 0, 'Former Teacher can report transferred class.');
qaAssert(qaAcademicSnapshot($pdo) === $before, 'Successful handover changed academic records.');
qaAssert((int) $pdo->query('SELECT COUNT(*) FROM operational_class_handovers')->fetchColumn() === 1, 'Handover history missing.');
qaAssert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'Operational Class Handover'")->fetchColumn() === 1, 'Required audit missing.');
qaRejected($pdo, 1, 2, 3, 'already changed Teacher');

aprismHandoverOperationalClass($pdo, 1, 3, 2, 1, 'Restore synthetic class for browser QA');
qaAssert(qaOwner($pdo, 1) === 2, 'QA reset handover failed.');
qaAssert((int) $pdo->query('SELECT COUNT(*) FROM operational_class_handovers')->fetchColumn() === 2, 'Second handover history missing.');
qaAssert(qaAcademicSnapshot($pdo) === $before, 'Repeated handover changed academic records.');

echo "PASS: conflicts, disabled Teacher, stale request, open session, audit rollback,\n";
echo "      successful handover, historical scores, replay, return handover, academic integrity.\n";
