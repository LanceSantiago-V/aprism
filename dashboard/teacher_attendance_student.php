<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/teacher_analytics_helper.php';

$pageTitle = 'Student Attendance Detail';
$activePage = 'attendance';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-analytics.css';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);

$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$classId = $classId !== false && $classId !== null && $classId > 0 ? (int) $classId : 0;
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$studentId = $studentId !== false && $studentId !== null && $studentId > 0 ? (int) $studentId : 0;
$startDate = aprismTeacherAnalyticsDate($_GET['start_date'] ?? null);
$endDate = aprismTeacherAnalyticsDate($_GET['end_date'] ?? null);
if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

function attendanceStudentEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function attendanceStudentDate(mixed $value, string $format = 'M j, Y'): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? '—' : date($format, $timestamp);
}

function attendanceStudentBackUrl(): string
{
    $query = array_filter([
        'view' => 'attendance',
        'class_id' => $GLOBALS['classId'] ?: null,
        'start_date' => $GLOBALS['startDate'] ?: null,
        'end_date' => $GLOBALS['endDate'] ?: null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_analytics.php?' . http_build_query($query) . '#attendance-detail';
}

$class = $classId > 0 ? aprismGradeClassContext($pdo, $teacherId, $classId) : null;
$attendance = $class !== null
    ? aprismTeacherAnalyticsAttendance($pdo, $teacherId, $classId, $startDate, $endDate)
    : aprismTeacherAnalyticsEmptyAttendance();
$student = null;
foreach ($attendance['students'] as $candidate) {
    if ((int) $candidate['student_id'] === $studentId) {
        $student = $candidate;
        break;
    }
}
$history = $class !== null && $student !== null
    ? aprismTeacherAnalyticsAttendanceStudentHistory($pdo, $teacherId, $classId, $studentId, $startDate, $endDate)
    : [];
$pageError = $classId === 0 || $studentId === 0
    ? 'Open this detail from a Student row in Attendance Analytics.'
    : ($class === null ? 'The requested Operational Class is outside your Teacher scope.' : ($student === null ? 'The requested Student is not enrolled in this class or date range.' : null));
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="teacher-page-container teacher-analytics-page teacher-analytics-detail-page">
            <header class="teacher-analytics-header">
                <div>
                    <p class="teacher-analytics-eyebrow">Attendance evidence · Student detail</p>
                    <h1 class="teacher-page-title">Student Attendance Detail</h1>
                </div>
                <a class="teacher-analytics-secondary-action" href="<?= attendanceStudentEsc(attendanceStudentBackUrl()) ?>"><i data-lucide="arrow-left" aria-hidden="true"></i> Back to Attendance Analytics</a>
            </header>

            <?php if ($pageError !== null): ?>
                <section class="teacher-panel teacher-analytics-empty" role="alert">
                    <i data-lucide="calendar-x-2" aria-hidden="true"></i>
                    <h2>Attendance detail unavailable</h2>
                    <p><?= attendanceStudentEsc($pageError) ?></p>
                    <a class="teacher-analytics-secondary-action" href="<?= attendanceStudentEsc(attendanceStudentBackUrl()) ?>"><i data-lucide="arrow-left" aria-hidden="true"></i> Back to Attendance Analytics</a>
                </section>
            <?php else: ?>
                <section class="teacher-panel teacher-analytics-context-summary-panel" aria-label="Attendance detail context">
                    <div><strong><?= attendanceStudentEsc($student['student_name']) ?></strong><span><?= attendanceStudentEsc($student['student_number']) ?></span></div>
                    <div><strong><?= attendanceStudentEsc($class['subject_name']) ?> · <?= attendanceStudentEsc($class['section_name']) ?></strong><span><?= attendanceStudentEsc($class['school_year']) ?> · <?= attendanceStudentEsc($class['semester']) ?></span></div>
                    <div><strong><?= $startDate !== null || $endDate !== null ? 'Selected date range' : 'All recorded sessions' ?></strong><span><?= attendanceStudentEsc($startDate ?? 'Start') ?> – <?= attendanceStudentEsc($endDate ?? 'Present') ?></span></div>
                </section>
                <section class="teacher-analytics-summary" aria-label="Student attendance summary">
                    <article><span class="teacher-analytics-summary-icon green"><i data-lucide="user-check" aria-hidden="true"></i></span><div><small>Present</small><strong><?= (int) $student['present_count'] ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon amber"><i data-lucide="clock-3" aria-hidden="true"></i></span><div><small>Late</small><strong><?= (int) $student['late_count'] ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon red"><i data-lucide="user-round-x" aria-hidden="true"></i></span><div><small>Absent</small><strong><?= (int) $student['absent_count'] ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon navy"><i data-lucide="percent" aria-hidden="true"></i></span><div><small>Attendance Rate</small><strong><?= $student['attendance_rate'] === null ? '—' : attendanceStudentEsc(number_format((float) $student['attendance_rate'], 1) . '%') ?></strong></div></article>
                </section>
                <section class="teacher-panel teacher-analytics-review-panel">
                    <div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Recorded sessions</p><h2>Attendance history</h2></div><span><?= count($history) ?> session<?= count($history) === 1 ? '' : 's' ?></span></div>
                    <div class="teacher-analytics-table-wrap">
                        <table class="teacher-analytics-table teacher-analytics-attendance-history-table">
                            <thead><tr><th scope="col">Date</th><th scope="col">Session</th><th scope="col">Status</th><th scope="col">Time / capture</th></tr></thead>
                            <tbody>
                            <?php if ($history === []): ?><tr><td colspan="4" class="teacher-analytics-empty-cell">No attendance sessions are available for this Student in the selected range.</td></tr>
                            <?php else: foreach ($history as $record): ?>
                                <tr><td><?= attendanceStudentEsc(attendanceStudentDate($record['meeting_date'])) ?></td><td><?= attendanceStudentEsc($record['scheduled_day'] . ' · ' . attendanceStudentDate($record['scheduled_start_time'], 'g:i A')) ?><small><?= attendanceStudentEsc($record['scheduled_room'] ?: 'Room not recorded') ?></small></td><td><span class="teacher-analytics-state <?= strtolower((string) ($record['status'] ?? 'not-recorded')) ?>"><?= attendanceStudentEsc((string) ($record['status'] ?? 'Not recorded')) ?></span></td><td><?= attendanceStudentEsc($record['checked_in_at'] ?? 'Not recorded') ?><small><?= attendanceStudentEsc($record['capture_source'] ?? '—') ?></small></td></tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="teacher-analytics-note">Attendance statuses are the recorded session outcomes. Effective absence calculations remain governed by APRISM attendance rules.</p>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
