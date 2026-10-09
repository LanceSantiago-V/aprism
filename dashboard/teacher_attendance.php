<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/attendance_helper.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../auth/session_guard.php';

$currentSchoolYear = null;
$operationalClasses = [];
$selectedClass = null;
$selectedSchedule = null;
$todaySchedules = [];
$participants = [];
$attendanceRecords = [];
$recentAttendanceSessions = [];
$attendanceSession = null;
$attendancePersistenceAvailable = false;
$attendanceMetricCounts = [
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'recorded' => 0,
];
$todayDate = date('Y-m-d');
$todayDay = date('l');
$todayLabel = date('l, F j, Y');

$selectedClassId = filter_var(
    $_GET['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$selectedClassId = $selectedClassId === false
    ? null
    : (int) $selectedClassId;

$selectedScheduleId = filter_var(
    $_GET['class_schedule_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$selectedScheduleId = $selectedScheduleId === false
    ? null
    : (int) $selectedScheduleId;

function teacherAttendanceFormatTime(?string $time): string
{
    if ($time === null || $time === '') {
        return 'Time unavailable';
    }

    $timestamp = strtotime($time);

    return $timestamp === false
        ? $time
        : date('g:i A', $timestamp);
}

try {
    $activeSchoolYearStmt = $pdo->query("
        SELECT school_year_id, school_year
        FROM school_years
        WHERE status = 'Active'
        ORDER BY school_year_id DESC
        LIMIT 1
    ");

    $activeSchoolYear =
        $activeSchoolYearStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($activeSchoolYear !== null) {
        $currentSchoolYear = (string) $activeSchoolYear['school_year'];
    }

    if ($currentSchoolYear !== null) {
        $classStmt = $pdo->prepare("
            SELECT
                oc.operational_class_id,
                oc.semester,
                sub.subject_name,
                sec.section_name,
                cs.class_schedule_id,
                cs.day,
                cs.start_time,
                cs.end_time,
                cs.room
            FROM operational_classes AS oc
            INNER JOIN subjects AS sub
                ON sub.subject_id = oc.subject_id
            INNER JOIN sections AS sec
                ON sec.section_id = oc.section_id
            LEFT JOIN class_schedules AS cs
                ON cs.operational_class_id = oc.operational_class_id
               AND cs.status = 'Active'
            WHERE oc.teacher_id = ?
              AND oc.school_year = ?
              AND oc.status = 'Active'
            ORDER BY
                sub.subject_name,
                sec.section_name,
                cs.day,
                cs.start_time
        ");

        $classStmt->execute([
            (int) $_SESSION['user_id'],
            $currentSchoolYear,
        ]);

        $classRows = $classStmt->fetchAll(PDO::FETCH_ASSOC);
        $classesById = [];

        foreach ($classRows as $row) {
            $classId = (int) $row['operational_class_id'];

            if (!isset($classesById[$classId])) {
                $classesById[$classId] = [
                    'id' => $classId,
                    'subject_name' => (string) $row['subject_name'],
                    'section_name' => (string) $row['section_name'],
                    'semester' => (string) $row['semester'],
                    'schedules' => [],
                ];
            }

            if ($row['day'] !== null) {
                $classesById[$classId]['schedules'][] = [
                    'id' => (int) $row['class_schedule_id'],
                    'day' => (string) $row['day'],
                    'start_time' => (string) $row['start_time'],
                    'end_time' => (string) $row['end_time'],
                    'room' => (string) ($row['room'] ?? ''),
                ];
            }
        }

        $operationalClasses = array_values($classesById);

        if ($selectedClassId !== null) {
            foreach ($operationalClasses as $class) {
                if ($class['id'] === $selectedClassId) {
                    $selectedClass = $class;
                    break;
                }
            }
        }

        if ($selectedClass !== null) {
            foreach ($selectedClass['schedules'] as $schedule) {
                if (
                    $selectedScheduleId !== null &&
                    $schedule['day'] === $todayDay &&
                    $schedule['id'] === $selectedScheduleId
                ) {
                    $selectedSchedule = $schedule;
                }

                if ($schedule['day'] === $todayDay) {
                    $todaySchedules[] = $schedule;
                }
            }

            if ($selectedSchedule === null && count($todaySchedules) === 1) {
                $selectedSchedule = $todaySchedules[0];
                $selectedScheduleId = $selectedSchedule['id'];
            }

            $tableCheckStmt = $pdo->query(
                "SHOW TABLES LIKE 'attendance_sessions'"
            );
            $hasSessionsTable = $tableCheckStmt->fetchColumn() !== false;
            $tableCheckStmt = $pdo->query(
                "SHOW TABLES LIKE 'student_attendance_records'"
            );
            $hasRecordsTable = $tableCheckStmt->fetchColumn() !== false;
            $attendancePersistenceAvailable = $hasSessionsTable && $hasRecordsTable;

            if (
                $attendancePersistenceAvailable &&
                $selectedSchedule !== null
            ) {
                $sessionStmt = $pdo->prepare("\n                    SELECT\n                        attendance_session_id,\n                        meeting_date,\n                        scheduled_day,\n                        scheduled_start_time,\n                        scheduled_end_time,\n                        scheduled_room,\n                        status,\n                        opened_at,\n                        closed_at\n                    FROM attendance_sessions\n                    WHERE operational_class_id = ?\n                      AND class_schedule_id = ?\n                      AND meeting_date = ?\n                    LIMIT 1\n                ");
                $sessionStmt->execute([
                    $selectedClass['id'],
                    $selectedSchedule['id'],
                    $todayDate,
                ]);
                $attendanceSession = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($attendanceSession !== null) {
                    $recordStmt = $pdo->prepare("
                        SELECT
                            student_class_enrollment_id,
                            status,
                            checked_in_at,
                            capture_source
                        FROM student_attendance_records
                        WHERE attendance_session_id = ?
                    ");
                    $recordStmt->execute([
                        (int) $attendanceSession['attendance_session_id'],
                    ]);

                    foreach ($recordStmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
                        $attendanceRecords[(int) $record['student_class_enrollment_id']] = $record;
                    }
                }
            }

            $participantStmt = $pdo->prepare("
                SELECT
                    sce.student_class_enrollment_id,
                    st.student_id,
                    st.student_number,
                    st.first_name,
                    st.middle_name,
                    st.last_name,
                    st.suffix,
                    at.tag_code
                FROM student_class_enrollments AS sce
                INNER JOIN students AS st
                    ON st.student_id = sce.student_id
                   AND st.status = 'Active'
                LEFT JOIN april_tags AS at
                    ON at.current_student_id = st.student_id
                   AND at.tag_family = 'tagStandard52h13'
                   AND at.status = 'Assigned'
                WHERE sce.operational_class_id = ?
                  AND sce.status = 'Active'
                ORDER BY
                    st.last_name,
                    st.first_name,
                    st.middle_name,
                    st.student_number
            ");

            $participantStmt->execute([$selectedClass['id']]);
            $participants = $participantStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($participants as &$participant) {
                $participant['attendance'] = $attendanceRecords[
                    (int) $participant['student_class_enrollment_id']
                ] ?? null;
                $participant['display_name'] = trim(
                    (string) $participant['last_name'] . ', '
                    . (string) $participant['first_name'] . ' '
                    . (string) ($participant['middle_name'] ?? '') . ' '
                    . (string) ($participant['suffix'] ?? '')
                );
            }

            unset($participant);

            foreach ($attendanceRecords as $record) {
                $attendanceMetricCounts['recorded']++;
                if ($record['status'] === 'Present') {
                    $attendanceMetricCounts['present']++;
                } elseif ($record['status'] === 'Late') {
                    $attendanceMetricCounts['late']++;
                } elseif ($record['status'] === 'Absent') {
                    $attendanceMetricCounts['absent']++;
                }
            }

            if ($attendancePersistenceAvailable) {
                $recentSessionsStmt = $pdo->prepare("
                    SELECT
                        ats.attendance_session_id,
                        ats.meeting_date,
                        ats.scheduled_start_time,
                        ats.status,
                        COUNT(sar.student_attendance_record_id) AS recorded_count,
                        SUM(sar.status = 'Present') AS present_count,
                        SUM(sar.status = 'Late') AS late_count,
                        SUM(sar.status = 'Absent') AS absent_count
                    FROM attendance_sessions AS ats
                    INNER JOIN operational_classes AS oc
                        ON oc.operational_class_id = ats.operational_class_id
                    LEFT JOIN student_attendance_records AS sar
                        ON sar.attendance_session_id = ats.attendance_session_id
                    WHERE ats.operational_class_id = ?
                      AND oc.teacher_id = ?
                      AND oc.status = 'Active'
                    GROUP BY
                        ats.attendance_session_id,
                        ats.meeting_date,
                        ats.scheduled_start_time,
                        ats.status
                    ORDER BY ats.meeting_date DESC, ats.scheduled_start_time DESC
                    LIMIT 7
                ");
                $recentSessionsStmt->execute([
                    $selectedClass['id'],
                    (int) $_SESSION['user_id'],
                ]);
                $recentAttendanceSessions = $recentSessionsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (PDOException $exception) {
    error_log(
        '[APRISM Teacher Attendance Workspace] '
        . $exception->getMessage()
    );

    $_SESSION['error_message'] =
        'The Attendance workspace could not be loaded.';
}

$pageTitle = 'Attendance Log';
$activePage = 'attendance';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-attendance.css';
$isScannerTerminal = defined('APRISM_ATTENDANCE_SCANNER_TERMINAL')
    && APRISM_ATTENDANCE_SCANNER_TERMINAL === true;
$noClassToday = $isScannerTerminal
    && $selectedSchedule === null
    && $todaySchedules === []
    && ($selectedClass['schedules'] ?? []) !== [];
$bodyPageClass = $isScannerTerminal
    ? 'teacher-attendance-scanner-route'
        . ($attendanceSession !== null && $attendanceSession['status'] === 'Open'
            ? ' is-session-open'
            : '')
    : 'teacher-attendance-log-route';

?>

<!DOCTYPE html>
<html lang="en">

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body class="<?= $bodyPageClass ?>">

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed'])
        ? 'expanded'
        : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <div class="content-wrapper">

            <?php if (!$isScannerTerminal): ?>
            <div class="teacher-page-header">
                <div>
                    <nav class="teacher-attendance-breadcrumb" aria-label="Breadcrumb">
                        <ol>
                            <li>Attendance</li>
                            <li aria-current="page">Attendance Log</li>
                        </ol>
                    </nav>
                    <h1 class="teacher-page-title">Attendance Log</h1>
                </div>
            </div>

            <section class="teacher-panel teacher-attendance-classes-panel">
                <div class="teacher-panel-header">
                    <div>
                        <h2 class="teacher-panel-title">My Active Classes</h2>
                    </div>
                    <?php if ($currentSchoolYear !== null): ?>
                        <span class="teacher-attendance-school-year">
                            School Year <?= htmlspecialchars($currentSchoolYear, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($operationalClasses === []): ?>
                    <div class="teacher-empty-state teacher-attendance-empty-state">
                        <i data-lucide="book-open-check"></i>
                        <h3 class="teacher-empty-state-title">
                            No Active Operational Classes
                        </h3>
                        <p class="teacher-empty-state-text">
                            Your active classes for the institutional active
                            School Year will appear here.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="teacher-attendance-class-list">
                        <?php foreach ($operationalClasses as $class): ?>
                            <a
                                class="teacher-attendance-class-card <?= $selectedClass !== null
                                    && $selectedClass['id'] === $class['id']
                                    ? 'is-selected'
                                    : '' ?>"
                                href="<?= APP_URL ?>/dashboard/teacher_attendance.php?operational_class_id=<?= $class['id'] ?>"
                            >
                                <div class="teacher-attendance-class-icon">
                                    <i data-lucide="clipboard-check"></i>
                                </div>
                                <div class="teacher-attendance-class-details">
                                    <strong><?= htmlspecialchars(
                                        $class['subject_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?></strong>
                                    <span><?= htmlspecialchars(
                                        $class['section_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?></span>
                                    <small>
                                        <?= count($class['schedules']) ?> schedule<?= count($class['schedules']) === 1 ? '' : 's' ?>
                                    </small>
                                </div>
                                <i data-lucide="chevron-right" class="teacher-attendance-class-arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($selectedClass !== null): ?>
                <section class="teacher-panel teacher-attendance-workspace <?= $isScannerTerminal ? 'teacher-attendance-scanner-workspace' : '' ?>">
                    <?php if ($isScannerTerminal): ?>
                        <div class="teacher-attendance-terminal-header">
                            <a class="teacher-class-workspace-back" href="<?= APP_URL ?>/dashboard/teacher_my_class.php" aria-label="Back to My Classes" title="Back to My Classes">
                                <i data-lucide="arrow-left" aria-hidden="true"></i>
                            </a>
                            <div class="teacher-attendance-terminal-title">
                                <span class="teacher-attendance-eyebrow">Attendance Scanner</span>
                                <h1 class="teacher-page-title">
                                    <?= htmlspecialchars($selectedClass['subject_name'], ENT_QUOTES, 'UTF-8') ?>
                                </h1>
                                <p class="teacher-page-subtitle">
                                    <?= htmlspecialchars($selectedClass['section_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <span aria-hidden="true"> · </span>
                                    <?= htmlspecialchars($currentSchoolYear ?? 'School Year unavailable', ENT_QUOTES, 'UTF-8') ?>
                                    <span aria-hidden="true"> · </span>
                                    <?= htmlspecialchars($selectedClass['semester'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                            <span class="teacher-badge <?= $attendanceSession !== null && $attendanceSession['status'] === 'Open' ? 'teacher-badge-success' : 'teacher-badge-info' ?>">
                                <?= $attendanceSession !== null ? htmlspecialchars('Session ' . $attendanceSession['status'], ENT_QUOTES, 'UTF-8') : 'Not started' ?>
                            </span>
                        </div>
                        <div class="teacher-attendance-terminal-context" aria-label="Class and session context">
                            <div class="teacher-attendance-terminal-context-item">
                                <span class="teacher-attendance-scanner-heading-icon" aria-hidden="true"><i data-lucide="clock-3"></i></span>
                                <div class="teacher-attendance-terminal-context-copy">
                                    <span>Schedule</span>
                                    <strong><?= $selectedSchedule !== null
                                    ? htmlspecialchars($selectedSchedule['day'] . ' · ' . teacherAttendanceFormatTime($selectedSchedule['start_time']) . '–' . teacherAttendanceFormatTime($selectedSchedule['end_time']), ENT_QUOTES, 'UTF-8')
                                    : (count($todaySchedules) > 1
                                        ? 'Choose a meeting scheduled today'
                                        : ($selectedClass['schedules'] !== []
                                            ? 'No meeting scheduled today'
                                            : 'No schedule configured')) ?></strong>
                                </div>
                            </div>
                            <div class="teacher-attendance-terminal-context-item">
                                <span class="teacher-attendance-scanner-heading-icon" aria-hidden="true"><i data-lucide="door-open"></i></span>
                                <div class="teacher-attendance-terminal-context-copy">
                                    <span>Room</span>
                                    <strong><?= $selectedSchedule !== null && $selectedSchedule['room'] !== ''
                                    ? htmlspecialchars($selectedSchedule['room'], ENT_QUOTES, 'UTF-8')
                                    : ($selectedSchedule !== null
                                        ? 'Room not recorded'
                                        : (count($todaySchedules) > 1
                                            ? 'Choose a meeting first'
                                            : 'No meeting today')) ?></strong>
                                </div>
                            </div>
                            <div class="teacher-attendance-terminal-context-item">
                                <span class="teacher-attendance-scanner-heading-icon" aria-hidden="true"><i data-lucide="book-open-check"></i></span>
                                <div class="teacher-attendance-terminal-context-copy">
                                    <span>Academic context</span>
                                    <strong><?= htmlspecialchars(($currentSchoolYear ?? 'School Year unavailable') . ' · ' . $selectedClass['semester'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                    <div class="teacher-panel-header">
                        <div class="teacher-attendance-selected-heading">
                            <div class="teacher-attendance-selected-icon" aria-hidden="true">
                                <i data-lucide="book-open-check"></i>
                            </div>
                            <div class="teacher-attendance-selected-copy">
                                <h2 class="teacher-panel-title">
                                    <?= htmlspecialchars(
                                        $selectedClass['subject_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                    <span aria-hidden="true">·</span>
                                    <?= htmlspecialchars(
                                        $selectedClass['section_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h2>
                                <p class="teacher-panel-subtitle">
                                    <?= htmlspecialchars(
                                        $currentSchoolYear ?? 'School Year unavailable',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                    <span aria-hidden="true">·</span>
                                    <?= htmlspecialchars(
                                        $selectedClass['semester'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>
                            </div>
                        </div>
                        <div class="teacher-attendance-selected-actions">
                            <span class="teacher-badge <?= $attendanceSession === null
                                ? 'teacher-badge-info'
                                : ($attendanceSession['status'] === 'Open'
                                    ? 'teacher-badge-success'
                                    : 'teacher-badge-warning') ?>">
                                <?= $attendanceSession === null
                                    ? 'Today’s class'
                                    : htmlspecialchars(
                                        'Session ' . $attendanceSession['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                            </span>
                            <?php if ($todaySchedules !== []): ?>
                                <?php
                                $scannerParams = ['operational_class_id' => $selectedClass['id']];
                                if ($selectedSchedule !== null) {
                                    $scannerParams['class_schedule_id'] = $selectedSchedule['id'];
                                }
                                ?>
                                <a
                                    class="teacher-primary-btn teacher-attendance-open-scanner-link"
                                    href="<?= APP_URL ?>/dashboard/teacher_attendance_scanner.php?<?= htmlspecialchars(http_build_query($scannerParams), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <i data-lucide="camera" aria-hidden="true"></i>
                                    <span>Open Attendance Session</span>
                                    <i data-lucide="chevron-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$isScannerTerminal): ?>
                        <div class="teacher-attendance-log-metrics" aria-label="Attendance summary">
                            <div class="is-roster">
                                <span class="teacher-attendance-metric-icon" aria-hidden="true"><i data-lucide="users-round"></i></span>
                                <span class="teacher-attendance-metric-copy"><span>Roster Students</span><strong><?= count($participants) ?></strong></span>
                            </div>
                            <div class="is-present">
                                <span class="teacher-attendance-metric-icon" aria-hidden="true"><i data-lucide="circle-check"></i></span>
                                <span class="teacher-attendance-metric-copy"><span>Present</span><strong><?= $attendanceMetricCounts['present'] ?></strong></span>
                            </div>
                            <div class="is-late">
                                <span class="teacher-attendance-metric-icon" aria-hidden="true"><i data-lucide="clock-3"></i></span>
                                <span class="teacher-attendance-metric-copy"><span>Late</span><strong><?= $attendanceMetricCounts['late'] ?></strong></span>
                            </div>
                            <div class="is-absent">
                                <span class="teacher-attendance-metric-icon" aria-hidden="true"><i data-lucide="circle-x"></i></span>
                                <span class="teacher-attendance-metric-copy"><span>Absent</span><strong><?= $attendanceMetricCounts['absent'] ?></strong></span>
                            </div>
                            <div class="is-recorded">
                                <span class="teacher-attendance-metric-icon" aria-hidden="true"><i data-lucide="file-check-2"></i></span>
                                <span class="teacher-attendance-metric-copy"><span>Recorded</span><strong><?= $attendanceMetricCounts['recorded'] ?></strong></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$isScannerTerminal): ?>
                </section>

                <section class="teacher-panel teacher-attendance-history" aria-labelledby="attendanceHistoryTitle">
                    <div class="teacher-attendance-section-header">
                        <div>
                            <h2 id="attendanceHistoryTitle">Recent Meetings</h2>
                        </div>
                        <a class="teacher-attendance-secondary-action" href="<?= APP_URL ?>/dashboard/teacher_analytics.php?<?= htmlspecialchars(http_build_query(['view' => 'attendance', 'class_id' => $selectedClass['id']]), ENT_QUOTES, 'UTF-8') ?>#attendance-detail">
                            <i data-lucide="list" aria-hidden="true"></i>
                            <span>View All Sessions</span>
                        </a>
                    </div>
                    <?php if ($recentAttendanceSessions === []): ?>
                        <p class="teacher-attendance-history-empty">No Attendance Sessions have been recorded for this class yet.</p>
                    <?php else: ?>
                        <div class="teacher-table-wrapper teacher-attendance-history-table-wrap">
                            <table class="teacher-table teacher-attendance-history-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Date &amp; Time</th>
                                        <th scope="col">Present</th>
                                        <th scope="col">Late</th>
                                        <th scope="col">Absent</th>
                                        <th scope="col">Recorded</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendanceSessions as $recentSession): ?>
                                        <?php
                                        $sessionDate = (string) $recentSession['meeting_date'];
                                        $sessionAnalyticsUrl = APP_URL . '/dashboard/teacher_analytics.php?'
                                            . http_build_query([
                                                'view' => 'attendance',
                                                'class_id' => $selectedClass['id'],
                                                'start_date' => $sessionDate,
                                                'end_date' => $sessionDate,
                                            ]) . '#attendance-detail';
                                        ?>
                                        <tr>
                                            <td data-label="Date &amp; Time">
                                                <strong><?= htmlspecialchars(date('M j, Y', strtotime($sessionDate)), ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span><?= htmlspecialchars(teacherAttendanceFormatTime((string) $recentSession['scheduled_start_time']), ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td data-label="Present"><?= (int) ($recentSession['present_count'] ?? 0) ?></td>
                                            <td data-label="Late"><?= (int) ($recentSession['late_count'] ?? 0) ?></td>
                                            <td data-label="Absent"><?= (int) ($recentSession['absent_count'] ?? 0) ?></td>
                                            <td data-label="Recorded"><?= (int) $recentSession['recorded_count'] ?></td>
                                            <td data-label="Status">
                                                <span class="teacher-badge <?= $recentSession['status'] === 'Open' ? 'teacher-badge-success' : 'teacher-badge-info' ?>">
                                                    <?= htmlspecialchars((string) $recentSession['status'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td data-label="Action">
                                                <a class="teacher-attendance-view-action" href="<?= htmlspecialchars($sessionAnalyticsUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="View attendance for <?= htmlspecialchars(date('M j, Y', strtotime($sessionDate)), ENT_QUOTES, 'UTF-8') ?>">
                                                    <span>View</span>
                                                    <i data-lucide="chevron-right" aria-hidden="true"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                    <?php else: ?>

                    <?php if ($isScannerTerminal): ?>
                        <?php if ($attendanceSession === null || $attendanceSession['status'] !== 'Open'): ?>
                            <section class="teacher-attendance-scanner teacher-attendance-scanner-locked<?= $noClassToday ? ' teacher-attendance-no-class' : '' ?>" aria-label="Attendance scanner session setup">
                                <?php if ($noClassToday): ?>
                                <div class="teacher-attendance-no-class-state" role="status">
                                    <div class="teacher-attendance-no-class-icon" aria-hidden="true">
                                        <i data-lucide="calendar-clock"></i>
                                    </div>
                                    <div class="teacher-attendance-no-class-copy">
                                        <span class="teacher-attendance-eyebrow">Attendance Scanner</span>
                                        <h2>No class today</h2>
                                        <p>There is no scheduled meeting for this class today.</p>
                                        <p>Attendance scanning will be available once the scheduled meeting’s attendance session is started.</p>
                                    </div>
                                    <div class="teacher-attendance-no-class-note">
                                        <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                                        <span>The scanner is unavailable without a scheduled meeting.</span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="teacher-attendance-locked-state" role="status">
                                    <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                                    <div class="teacher-attendance-locked-copy">
                                        <?php if ($attendanceSession !== null && $attendanceSession['status'] !== 'Open'): ?>
                                            <strong>Attendance session is closed</strong>
                                            <span>This meeting is read-only. Attendance history remains available in the Attendance workspace.</span>
                                        <?php else: ?>
                                            <strong>Attendance session not started</strong>
                                            <?php if (!$attendancePersistenceAvailable): ?>
                                                <span>Attendance cannot be recorded for this meeting yet.</span>
                                            <?php elseif ($selectedSchedule !== null): ?>
                                                <span>Start attendance for this meeting to enable scanning.</span>
                                            <?php elseif (count($todaySchedules) > 1): ?>
                                                <span>Choose one of today’s scheduled meetings to continue.</span>
                                            <?php elseif ($todaySchedules === [] && ($selectedClass['schedules'] ?? []) === []): ?>
                                                <span>No schedule is configured for this class.</span>
                                            <?php else: ?>
                                                <span>Scanning becomes available after a scheduled meeting’s attendance session is started.</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($attendancePersistenceAvailable && $selectedSchedule !== null && $attendanceSession === null): ?>
                                        <form method="post" action="<?= APP_URL ?>/actions/teacher/start_attendance_session.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="operational_class_id" value="<?= $selectedClass['id'] ?>">
                                            <input type="hidden" name="class_schedule_id" value="<?= $selectedSchedule['id'] ?>">
                                            <button type="submit" class="teacher-primary-btn teacher-attendance-start-button">
                                                <i data-lucide="play" aria-hidden="true"></i>
                                                <span>Start Attendance</span>
                                            </button>
                                        </form>
                                    <?php elseif (count($todaySchedules) > 1 && $selectedSchedule === null): ?>
                                        <form method="get" class="teacher-attendance-scanner-schedule-form">
                                            <input type="hidden" name="operational_class_id" value="<?= $selectedClass['id'] ?>">
                                            <label for="scannerSchedule">Choose today’s meeting</label>
                                            <select id="scannerSchedule" name="class_schedule_id" required>
                                                <option value="">Select a meeting</option>
                                                <?php foreach ($todaySchedules as $schedule): ?>
                                                    <option value="<?= $schedule['id'] ?>">
                                                        <?= htmlspecialchars(teacherAttendanceFormatTime($schedule['start_time']) . '–' . teacherAttendanceFormatTime($schedule['end_time']) . ($schedule['room'] !== '' ? ' · ' . $schedule['room'] : ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="teacher-primary-btn teacher-attendance-start-button">
                                                <i data-lucide="calendar-check" aria-hidden="true"></i>
                                                <span>Use This Meeting</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </section>
                        <?php else: ?>
                        <section
                            class="teacher-attendance-scanner"
                            data-attendance-scanner
                            data-session-id="<?= (int) $attendanceSession['attendance_session_id'] ?>"
                            data-operational-class-id="<?= (int) $selectedClass['id'] ?>"
                            data-csrf-token="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                            data-worker-url="<?= APP_URL ?>/assets/js/pages/teacher-attendance-worker.js"
                            data-decoder-url="<?= APP_URL ?>/assets/vendor/apriltag/aprism_apriltag_detector.js"
                            data-wasm-url="<?= APP_URL ?>/assets/vendor/apriltag/aprism_apriltag_detector.wasm"
                            data-scan-endpoint="<?= APP_URL ?>/actions/teacher/record_attendance_scan.php"
                        >
                            <div class="teacher-attendance-scanner-layout">
                                <div class="teacher-attendance-camera-card">
                                    <div class="teacher-attendance-scanner-header">
                                        <div class="teacher-attendance-scanner-heading">
                                            <span class="teacher-attendance-scanner-heading-icon" aria-hidden="true"><i data-lucide="scan-line"></i></span>
                                            <div class="teacher-attendance-scanner-heading-copy">
                                                <span class="teacher-attendance-eyebrow">AprilTag Scanner</span>
                                                <h3>Continuous AprilTag scanning</h3>
                                                <p>Keep the camera running to continuously detect AprilTags in view. Manual Attendance remains available below.</p>
                                            </div>
                                        </div>
                                        <span class="teacher-badge teacher-badge-success">Session Open</span>
                                    </div>
                                    <div class="teacher-attendance-video-frame">
                                        <video data-attendance-video playsinline muted aria-label="AprilTag camera preview"></video>
                                        <canvas data-attendance-canvas hidden aria-hidden="true"></canvas>
                                        <div class="teacher-attendance-camera-placeholder" data-attendance-camera-placeholder>
                                            <i data-lucide="scan-line" aria-hidden="true"></i>
                                            <strong>Camera ready when you are</strong>
                                            <span>Start the camera to scan tagStandard52h13 markers.</span>
                                        </div>
                                        <div class="teacher-attendance-camera-active-badge" aria-hidden="true">
                                            <span class="teacher-attendance-camera-active-dot"></span>
                                            <span>Camera active</span>
                                        </div>
                                        <div class="teacher-attendance-camera-mode" aria-hidden="true">
                                            <span><i data-lucide="scan-line"></i> Continuous scanning</span>
                                            <small>Present AprilTags one after another.</small>
                                        </div>
                                    </div>
                                    <div class="teacher-attendance-camera-controls">
                                        <button type="button" class="teacher-primary-btn" data-attendance-start-camera>
                                            <i data-lucide="camera" aria-hidden="true"></i>
                                            <span>Start Camera</span>
                                        </button>
                                        <button type="button" class="teacher-attendance-camera-stop" data-attendance-stop-camera disabled>
                                            <i data-lucide="camera-off" aria-hidden="true"></i>
                                            <span>Stop Camera</span>
                                        </button>
                                    </div>
                                    <p class="teacher-attendance-camera-status" data-attendance-camera-status role="status">Camera is off.</p>
                                </div>

                                <div class="teacher-attendance-scanner-side">
                                    <section class="teacher-attendance-live-metrics" aria-label="Attendance summary">
                                        <div class="teacher-attendance-scanner-section-heading">
                                            <span class="teacher-attendance-scanner-heading-icon" aria-hidden="true"><i data-lucide="users-round"></i></span>
                                            <span class="teacher-attendance-eyebrow">Attendance Summary</span>
                                        </div>
                                        <div class="teacher-attendance-metric-total">
                                            <span>Students</span>
                                            <strong><?= count($participants) ?></strong>
                                        </div>
                                        <div class="teacher-attendance-metric-grid">
                                            <div class="teacher-attendance-metric is-present"><strong data-attendance-metric="present"><?= $attendanceMetricCounts['present'] ?></strong><span>Present</span></div>
                                            <div class="teacher-attendance-metric is-late"><strong data-attendance-metric="late"><?= $attendanceMetricCounts['late'] ?></strong><span>Late</span></div>
                                            <div class="teacher-attendance-metric is-absent"><strong data-attendance-metric="absent"><?= $attendanceMetricCounts['absent'] ?></strong><span>Absent</span></div>
                                        </div>
                                        <div class="teacher-attendance-progress">
                                            <span>Recorded <strong data-attendance-metric="recorded-label"><?= $attendanceMetricCounts['recorded'] ?></strong></span>
                                            <span>Unrecorded <strong data-attendance-metric="unrecorded-label"><?= max(0, count($participants) - $attendanceMetricCounts['recorded']) ?></strong></span>
                                            <div><span data-attendance-progress-bar style="width: <?= count($participants) > 0 ? round(($attendanceMetricCounts['recorded'] / count($participants)) * 100, 2) : 0 ?>%"></span></div>
                                        </div>
                                    </section>

                                    <section class="teacher-attendance-session-controls">
                                        <div class="teacher-attendance-scanner-section-heading">
                                            <span class="teacher-attendance-scanner-heading-icon" aria-hidden="true"><i data-lucide="settings"></i></span>
                                            <span class="teacher-attendance-eyebrow">Session Controls</span>
                                        </div>
                                        <button type="button" class="teacher-attendance-close-button" data-bs-toggle="modal" data-bs-target="#closeAttendanceModal">
                                            <i data-lucide="square"></i>
                                            <span>Close Attendance</span>
                                        </button>
                                    </section>

                                    <aside class="teacher-attendance-scan-feedback" data-attendance-scan-feedback aria-live="polite">
                                        <div class="teacher-attendance-scanner-section-heading">
                                            <span class="teacher-attendance-scanner-heading-icon" aria-hidden="true"><i data-lucide="file-check-2"></i></span>
                                            <span class="teacher-attendance-eyebrow">Latest result</span>
                                        </div>
                                        <div class="teacher-attendance-scan-result" data-attendance-scan-result>
                                            <i data-lucide="scan" aria-hidden="true"></i>
                                            <strong>No scan yet</strong>
                                            <span>Recognized students will appear here.</span>
                                        </div>
                                    </aside>
                                    <aside class="teacher-attendance-offline-state" data-attendance-offline-state aria-live="polite" hidden>
                                        <span class="teacher-attendance-eyebrow">Offline scan queue</span>
                                        <strong data-attendance-offline-summary>Checking local queue…</strong>
                                        <span data-attendance-offline-detail>Queued scans are revalidated by APRISM before they are recorded.</span>
                                        <button type="button" class="teacher-attendance-offline-sync" data-attendance-offline-sync disabled>Sync queued scans</button>
                                    </aside>
                                </div>
                            </div>
                        </section>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$isScannerTerminal || ($isScannerTerminal && $attendanceSession !== null && $attendanceSession['status'] === 'Open')): ?>
                    <?php if (!$isScannerTerminal): ?>
                    <section class="teacher-panel teacher-attendance-roster-panel">
                    <?php endif; ?>
                    <div class="teacher-attendance-roster-header">
                        <div>
                            <h3>Class Participants</h3>
                        </div>
                    </div>

                    <?php if ($participants === []): ?>
                        <div class="teacher-empty-state teacher-attendance-empty-state">
                            <i data-lucide="users-round"></i>
                            <h3 class="teacher-empty-state-title">
                                No Active Class Participants
                            </h3>
                            <p class="teacher-empty-state-text">
                                Active Class List participants will appear here.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="teacher-table-wrapper">
                            <table class="teacher-table teacher-attendance-roster-table">
                                <thead>
                                    <tr>
                                        <?php if (!$isScannerTerminal): ?>
                                            <th scope="col">Student</th>
                                            <th scope="col">AprilTag</th>
                                            <th scope="col">Attendance</th>
                                            <th scope="col">Recorded</th>
                                            <th scope="col">Source</th>
                                            <th scope="col">Action</th>
                                        <?php else: ?>
                                            <th>Student Number</th>
                                            <th>Student</th>
                                            <th>AprilTag</th>
                                            <th>Attendance</th>
                                            <th>Recorded</th>
                                            <th>Source</th>
                                            <?php if ($attendanceSession !== null && $attendanceSession['status'] === 'Open'): ?>
                                                <th>Action</th>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $participant): ?>
                                        <?php $attendance = $participant['attendance']; ?>
                                        <?php if (!$isScannerTerminal): ?>
                                        <?php
                                        $firstInitial = function_exists('mb_substr')
                                            ? mb_substr((string) $participant['first_name'], 0, 1, 'UTF-8')
                                            : substr((string) $participant['first_name'], 0, 1);
                                        $lastInitial = function_exists('mb_substr')
                                            ? mb_substr((string) $participant['last_name'], 0, 1, 'UTF-8')
                                            : substr((string) $participant['last_name'], 0, 1);
                                        $participantInitials = strtoupper($firstInitial . $lastInitial);
                                        ?>
                                        <tr data-attendance-row="<?= (int) $participant['student_class_enrollment_id'] ?>">
                                            <td data-attendance-student data-label="Student">
                                                <div class="teacher-attendance-student-identity">
                                                    <span class="teacher-attendance-student-avatar" aria-hidden="true"><?= htmlspecialchars($participantInitials, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="teacher-attendance-student-copy">
                                                        <strong><?= htmlspecialchars(
                                                            (string) $participant['display_name'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?></strong>
                                                        <small class="teacher-attendance-number"><?= htmlspecialchars(
                                                            (string) $participant['student_number'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?></small>
                                                    </span>
                                                </div>
                                            </td>
                                            <td data-label="AprilTag">
                                                <?php if ($participant['tag_code'] !== null): ?>
                                                    <span class="teacher-badge teacher-badge-success">
                                                        #<?= (int) $participant['tag_code'] ?>
                                                    </span>
                                                    <span class="teacher-attendance-tag-family">
                                                        tagStandard52h13
                                                    </span>
                                                <?php else: ?>
                                                    <span class="teacher-badge teacher-badge-info">
                                                        Awaiting AprilTag
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-attendance-status data-label="Attendance">
                                                <?php if ($attendance === null): ?>
                                                    <span class="teacher-badge teacher-badge-info">
                                                        Not recorded
                                                    </span>
                                                <?php else: ?>
                                                    <span class="teacher-badge <?= $attendance['status'] === 'Present'
                                                        ? 'teacher-badge-success'
                                                        : ($attendance['status'] === 'Late'
                                                            ? 'teacher-badge-warning'
                                                            : 'teacher-badge-danger') ?>">
                                                        <?= htmlspecialchars($attendance['status'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="teacher-attendance-recorded-time" data-attendance-recorded data-label="Recorded">
                                                <?= $attendance !== null && $attendance['checked_in_at'] !== null
                                                    ? htmlspecialchars(
                                                        teacherAttendanceFormatTime($attendance['checked_in_at']),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    )
                                                    : '—' ?>
                                            </td>
                                            <td data-attendance-source data-label="Source">
                                                <?= $attendance !== null && $attendance['capture_source'] !== null
                                                    ? htmlspecialchars($attendance['capture_source'], ENT_QUOTES, 'UTF-8')
                                                    : '—' ?>
                                            </td>
                                            <td class="teacher-attendance-row-action" data-attendance-action data-label="Action">
                                                <div class="teacher-attendance-participant-actions">
                                                    <?php if ($attendanceSession !== null && $attendanceSession['status'] === 'Open'): ?>
                                                    <?php if ($attendance === null): ?>
                                                        <form method="post" action="<?= APP_URL ?>/actions/teacher/mark_attendance_manual.php">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="operational_class_id" value="<?= $selectedClass['id'] ?>">
                                                            <input type="hidden" name="attendance_session_id" value="<?= (int) $attendanceSession['attendance_session_id'] ?>">
                                                            <input type="hidden" name="student_class_enrollment_id" value="<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                            <button type="submit" class="teacher-attendance-manual-button">
                                                                <i data-lucide="user-check" aria-hidden="true"></i>
                                                                <span>Mark Manually</span>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="teacher-attendance-recorded-label">Recorded</span>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                    <a class="teacher-attendance-view-action" href="<?= APP_URL ?>/dashboard/teacher_attendance_student.php?<?= htmlspecialchars(http_build_query(['class_id' => $selectedClass['id'], 'student_id' => (int) $participant['student_id']]), ENT_QUOTES, 'UTF-8') ?>">
                                                        <span>View</span>
                                                        <i data-lucide="chevron-right" aria-hidden="true"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <tr data-attendance-row="<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                <td class="teacher-attendance-number" data-label="Student Number">
                                                    <?= htmlspecialchars((string) $participant['student_number'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td data-attendance-student data-label="Student">
                                                    <strong><?= htmlspecialchars((string) $participant['display_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                </td>
                                                <td data-label="AprilTag">
                                                    <?php if ($participant['tag_code'] !== null): ?>
                                                        <span class="teacher-badge teacher-badge-success">#<?= (int) $participant['tag_code'] ?></span>
                                                        <span class="teacher-attendance-tag-family">tagStandard52h13</span>
                                                    <?php else: ?>
                                                        <span class="teacher-badge teacher-badge-info">Awaiting AprilTag</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-attendance-status data-label="Attendance">
                                                    <?php if ($attendance === null): ?>
                                                        <span class="teacher-badge teacher-badge-info">Not recorded</span>
                                                    <?php else: ?>
                                                        <span class="teacher-badge <?= $attendance['status'] === 'Present'
                                                            ? 'teacher-badge-success'
                                                            : ($attendance['status'] === 'Late'
                                                                ? 'teacher-badge-warning'
                                                                : 'teacher-badge-danger') ?>">
                                                            <?= htmlspecialchars($attendance['status'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="teacher-attendance-recorded-time" data-attendance-recorded data-label="Recorded">
                                                    <?= $attendance !== null && $attendance['checked_in_at'] !== null
                                                        ? htmlspecialchars(teacherAttendanceFormatTime($attendance['checked_in_at']), ENT_QUOTES, 'UTF-8')
                                                        : '—' ?>
                                                </td>
                                                <td data-attendance-source data-label="Source">
                                                    <?= $attendance !== null && $attendance['capture_source'] !== null
                                                        ? htmlspecialchars($attendance['capture_source'], ENT_QUOTES, 'UTF-8')
                                                        : '—' ?>
                                                </td>
                                                <?php if ($attendanceSession !== null && $attendanceSession['status'] === 'Open'): ?>
                                                    <td class="teacher-attendance-row-action" data-attendance-action data-label="Action">
                                                        <?php if ($attendance === null): ?>
                                                            <form method="post" action="<?= APP_URL ?>/actions/teacher/mark_attendance_manual.php">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="operational_class_id" value="<?= $selectedClass['id'] ?>">
                                                                <input type="hidden" name="attendance_session_id" value="<?= (int) $attendanceSession['attendance_session_id'] ?>">
                                                                <input type="hidden" name="student_class_enrollment_id" value="<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                                <button type="submit" class="teacher-attendance-manual-button">
                                                                    <i data-lucide="user-check" aria-hidden="true"></i>
                                                                    <span>Mark Manually</span>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="teacher-attendance-recorded-label">Recorded</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$isScannerTerminal): ?>
                    </section>
                    <?php else: ?>
                </section>
                    <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php if ($attendanceSession !== null && $attendanceSession['status'] === 'Open'): ?>
        <div class="modal fade" id="closeAttendanceModal" tabindex="-1" aria-labelledby="closeAttendanceModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-custom">
                    <div class="modal-body modal-body-custom">
                        <h3 class="modal-title mb-2" id="closeAttendanceModalLabel">Close Attendance Session?</h3>
                        <p class="mb-4">
                            Students without a recorded result can only be finalized as Absent after the configured absence threshold has passed.
                        </p>
                        <div class="d-flex align-items-center justify-content-end gap-3">
                            <button type="button" class="teacher-attendance-modal-secondary" data-bs-dismiss="modal">Keep Open</button>
                            <form method="post" action="<?= APP_URL ?>/actions/teacher/close_attendance_session.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="operational_class_id" value="<?= $selectedClass['id'] ?>">
                                <input type="hidden" name="attendance_session_id" value="<?= (int) $attendanceSession['attendance_session_id'] ?>">
                                <button type="submit" class="teacher-primary-btn">Close Attendance</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="toast-container-custom" id="toastContainer"></div>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

    <?php if ($isScannerTerminal && $attendanceSession !== null && $attendanceSession['status'] === 'Open'): ?>
        <script src="<?= APP_URL ?>/assets/js/pages/teacher-attendance.js"></script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toastContainer = document.getElementById('toastContainer');

            const showToast = (title, text, type = 'success') => {
                if (!toastContainer || !text) {
                    return;
                }

                const toast = document.createElement('div');
                toast.className = 'toast-custom';
                const icon = type === 'warning' ? 'alert-circle' : 'check';

                toast.innerHTML = `
                    <div class="toast-icon ${type}">
                        <i data-lucide="${icon}"></i>
                    </div>
                    <div class="toast-content">
                        <h5 class="toast-title"></h5>
                        <p class="toast-text"></p>
                    </div>
                `;
                toast.querySelector('.toast-title').textContent = title;
                toast.querySelector('.toast-text').textContent = text;
                toastContainer.appendChild(toast);
                window.lucide?.createIcons();
                setTimeout(() => toast.classList.add('show'), 10);
                setTimeout(() => toast.remove(), 4800);
            };

            window.aprismShowToast = showToast;

            <?php if (!empty($flash['success'])): ?>
                showToast('Success', <?= json_encode((string) $flash['success'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            <?php endif; ?>
            <?php if (!empty($flash['error'])): ?>
                showToast('Error', <?= json_encode((string) $flash['error'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, 'warning');
            <?php endif; ?>
        });
    </script>

</body>
</html>
