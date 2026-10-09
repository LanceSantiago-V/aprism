<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_dashboard_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_analytics_helper.php';

$allowedRoles = [
    ROLE_TEACHER
];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

$pageTitle = 'Dashboard';

$activePage = 'dashboard';

$roleStylesheet = 'assets/css/teacher.css';

$pageStylesheet = 'assets/css/pages/teacher-dashboard.css';
$additionalStylesheets = ['assets/css/pages/dashboard-shared.css'];


$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$currentSchoolYear = (string) ($pdo->query("SELECT school_year FROM school_years WHERE status = 'Active' ORDER BY school_year_id DESC LIMIT 1")->fetchColumn() ?: '');
$teacherSummary = aprismTeacherDashboardSummary($pdo, $teacherId, $currentSchoolYear !== '' ? $currentSchoolYear : null);
$dashboardSchedules = aprismTeacherDashboardSchedule($pdo, $teacherId, $currentSchoolYear !== '' ? $currentSchoolYear : null);
$todaySchedules = array_values(array_filter(
    $dashboardSchedules,
    static fn(array $row): bool => ($row['schedule_state'] ?? '') === 'Today'
));
$upcomingSchedules = array_values(array_filter(
    $dashboardSchedules,
    static fn(array $row): bool => ($row['schedule_state'] ?? '') === 'Upcoming'
));
$dashboardAttention = aprismTeacherDashboardAttention($pdo, $teacherId, $currentSchoolYear !== '' ? $currentSchoolYear : null);
$teacherPendingImports = [];
$pendingStmt = $pdo->prepare(
    "SELECT grade_import_batch_id, operational_class_id, original_filename, template_type, status, created_at
     FROM grade_import_batches
     WHERE teacher_id = ? AND status IN ('Review', 'Validated')
     ORDER BY created_at DESC, grade_import_batch_id DESC
     LIMIT 5"
);
$pendingStmt->execute([$teacherId]);
$teacherPendingImports = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

$teacherAnalyticsClasses = [];
foreach (aprismGradeTeacherClasses($pdo, $teacherId) as $class) {
    if (($class['status'] ?? '') !== 'Active'
        || ($currentSchoolYear !== '' && (string) ($class['school_year'] ?? '') !== $currentSchoolYear)) {
        continue;
    }

    $classContext = aprismGradeClassContext(
        $pdo,
        $teacherId,
        (int) $class['operational_class_id']
    );
    if ($classContext !== null) {
        $teacherAnalyticsClasses[] = array_merge($class, $classContext);
    }
}

$teacherAttendanceTrend = [];
foreach ($teacherAnalyticsClasses as $class) {
    $attendanceAnalytics = aprismTeacherAnalyticsAttendance(
        $pdo,
        $teacherId,
        (int) $class['operational_class_id']
    );

    foreach ($attendanceAnalytics['sessions'] as $session) {
        $recordedCount = (int) ($session['recorded_count'] ?? 0);
        if ($recordedCount <= 0) {
            continue;
        }

        $teacherAttendanceTrend[] = [
            'meeting_date' => (string) ($session['meeting_date'] ?? ''),
            'subject_name' => (string) ($class['subject_name'] ?? ''),
            'section_name' => (string) ($class['section_name'] ?? ''),
            'label' => trim((string) ($class['subject_name'] ?? '') . ' · ' . (string) ($class['section_name'] ?? '')),
            'recorded_count' => $recordedCount,
            'present_count' => (int) ($session['present_count'] ?? 0),
            'late_count' => (int) ($session['late_count'] ?? 0),
            'absent_count' => (int) ($session['absent_count'] ?? 0),
            'attendance_rate' => $session['attendance_rate'] === null
                ? null
                : (float) $session['attendance_rate'],
        ];
    }
}

usort(
    $teacherAttendanceTrend,
    static fn(array $left, array $right): int => strcmp($left['meeting_date'], $right['meeting_date'])
);
$teacherAttendanceTrend = array_slice($teacherAttendanceTrend, -8);
$teacherAttendanceRecorded = array_sum(array_column($teacherAttendanceTrend, 'recorded_count'));
$teacherAttendancePresent = array_sum(array_column($teacherAttendanceTrend, 'present_count'));
$teacherAttendanceLate = array_sum(array_column($teacherAttendanceTrend, 'late_count'));
$teacherAttendanceAbsent = array_sum(array_column($teacherAttendanceTrend, 'absent_count'));
$teacherAttendanceRate = $teacherAttendanceRecorded > 0
    ? ($teacherAttendancePresent / $teacherAttendanceRecorded) * 100
    : null;

$teacherAttendancePlotPoints = [];
$attendancePlotCount = count($teacherAttendanceTrend);
foreach ($teacherAttendanceTrend as $pointIndex => $trendPoint) {
    if ($trendPoint['attendance_rate'] === null) {
        continue;
    }
    $plotRate = max(0, min(100, (float) $trendPoint['attendance_rate']));
    $teacherAttendancePlotPoints[] = [
        'x' => $attendancePlotCount <= 1 ? 300 : (($pointIndex + 0.5) * 600 / $attendancePlotCount),
        'y' => 20 + ((100 - $plotRate) * 2.4),
        'rate' => $plotRate,
        'date' => (string) $trendPoint['meeting_date'],
        'label' => (string) $trendPoint['label'],
        'recorded_count' => (int) $trendPoint['recorded_count'],
    ];
}
$teacherAttendanceLinePath = implode(' ', array_map(
    static fn(array $point): string => number_format((float) $point['x'], 2, '.', '') . ',' . number_format((float) $point['y'], 2, '.', ''),
    $teacherAttendancePlotPoints
));
$teacherAttendanceAreaPath = '';
if (count($teacherAttendancePlotPoints) >= 3) {
    $areaCoordinates = array_map(
        static fn(array $point): string => number_format((float) $point['x'], 2, '.', '') . ',' . number_format((float) $point['y'], 2, '.', ''),
        $teacherAttendancePlotPoints
    );
    $firstPlotPoint = $teacherAttendancePlotPoints[0];
    $lastPlotPoint = $teacherAttendancePlotPoints[array_key_last($teacherAttendancePlotPoints)];
    $teacherAttendanceAreaPath = 'M ' . number_format((float) $firstPlotPoint['x'], 2, '.', '') . ' 260 L '
        . implode(' L ', $areaCoordinates) . ' L '
        . number_format((float) $lastPlotPoint['x'], 2, '.', '') . ' 260 Z';
}

$teacherGradeCoverage = [];
$teacherGradeExpected = 0;
$teacherGradePopulated = 0;
$teacherGradeCoveragePeriods = [];
$teacherGradeCoverageClassIds = [];
foreach ($teacherAnalyticsClasses as $class) {
    foreach (aprismGradePeriodsForClass($pdo, $class) as $period) {
        $analytics = aprismGradeAnalyticsBuild(
            $pdo,
            $teacherId,
            (int) $class['operational_class_id'],
            (int) $period['academic_period_id']
        );
        $summary = $analytics['summary'] ?? null;
        $expectedEntries = (int) ($summary['expected_entries'] ?? 0);
        if ($summary === null || $expectedEntries <= 0) {
            continue;
        }

        $populatedEntries = (int) ($summary['populated_entries'] ?? 0);
        $teacherGradeExpected += $expectedEntries;
        $teacherGradePopulated += $populatedEntries;
        $periodId = (int) ($period['academic_period_id'] ?? 0);
        if ($periodId <= 0) {
            continue;
        }
        if (!isset($teacherGradeCoveragePeriods[$periodId])) {
            $teacherGradeCoveragePeriods[$periodId] = [
                'period_name' => (string) ($period['period_name'] ?? 'Academic period'),
                'populated_entries' => 0,
                'expected_entries' => 0,
                'class_ids' => [],
            ];
        }
        $teacherGradeCoveragePeriods[$periodId]['populated_entries'] += $populatedEntries;
        $teacherGradeCoveragePeriods[$periodId]['expected_entries'] += $expectedEntries;
        $teacherGradeCoveragePeriods[$periodId]['class_ids'][(int) $class['operational_class_id']] = true;
        $teacherGradeCoverageClassIds[(int) $class['operational_class_id']] = true;
    }
}

foreach ($teacherGradeCoveragePeriods as $periodCoverage) {
    $expectedEntries = (int) $periodCoverage['expected_entries'];
    $populatedEntries = (int) $periodCoverage['populated_entries'];
    $teacherGradeCoverage[] = [
        'period_name' => (string) $periodCoverage['period_name'],
        'populated_entries' => $populatedEntries,
        'expected_entries' => $expectedEntries,
        'class_count' => count($periodCoverage['class_ids']),
        'coverage_percent' => $expectedEntries > 0 ? ($populatedEntries / $expectedEntries) * 100 : null,
    ];
}

$teacherGradeCoverageRate = $teacherGradeExpected > 0
    ? ($teacherGradePopulated / $teacherGradeExpected) * 100
    : null;
$teacherGradeCoverageDisplay = array_slice($teacherGradeCoverage, 0, 6);
$teacherGradeCoverageClassCount = count($teacherGradeCoverageClassIds);

function teacherDashboardEsc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function teacherDashboardPercent(?float $value): string
{
    return $value === null ? '—' : number_format($value, 0) . '%';
}

function teacherDashboardDateLabel(string $value): string
{
    $timestamp = strtotime($value);
    return $timestamp === false ? 'Recorded session' : date('M j', $timestamp);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <?php require __DIR__ . '/../includes/components/head.php'; ?>

</head>

<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content dashboard-shell aprism-dashboard teacher-dashboard-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <div id="teacher-dashboard-page" class="content-wrapper dashboard-content teacher-dashboard-page">
            <header class="teacher-dashboard-header">
                <div>
                    <p class="teacher-dashboard-eyebrow">Teacher workspace</p>
                    <h1 class="teacher-page-title">Dashboard</h1>
                    <p class="teacher-dashboard-context"><?= $currentSchoolYear !== '' ? teacherDashboardEsc($currentSchoolYear) : 'No active School Year configured' ?></p>
                </div>
                <a class="teacher-dashboard-header-action" href="<?= APP_URL ?>/dashboard/teacher_my_class.php"><i data-lucide="list-checks" aria-hidden="true"></i> View my classes</a>
            </header>

            <section class="teacher-dashboard-summary" aria-label="Teacher overview counts">
                <a class="teacher-dashboard-summary-item" href="<?= APP_URL ?>/dashboard/teacher_my_class.php">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-blue"><i data-lucide="book-open-check" aria-hidden="true"></i></span>
                    <small>Active Classes</small>
                    <strong><?= (int) $teacherSummary['active_classes'] ?></strong>
                    <span class="teacher-dashboard-summary-description">Classes currently assigned to you</span>
                </a>
                <a class="teacher-dashboard-summary-item" href="<?= APP_URL ?>/dashboard/teacher_students.php">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-navy"><i data-lucide="users" aria-hidden="true"></i></span>
                    <small>Current Students</small>
                    <strong><?= (int) $teacherSummary['current_students'] ?></strong>
                    <span class="teacher-dashboard-summary-description">Across your active classes</span>
                </a>
                <a class="teacher-dashboard-summary-item" href="#teacher-schedule">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-yellow"><i data-lucide="calendar-days" aria-hidden="true"></i></span>
                    <small>Today’s Classes</small>
                    <strong><?= count($todaySchedules) ?></strong>
                    <span class="teacher-dashboard-summary-description">Classes scheduled for today</span>
                </a>
                <a class="teacher-dashboard-summary-item" href="<?= APP_URL ?>/dashboard/teacher_referrals.php">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-red"><i data-lucide="clipboard-list" aria-hidden="true"></i></span>
                    <small>Open Referrals</small>
                    <strong><?= (int) $teacherSummary['open_referrals'] ?></strong>
                    <span class="teacher-dashboard-summary-description">Referral cases currently open</span>
                </a>
            </section>

            <div class="teacher-dashboard-primary-flow">
                <section class="teacher-dashboard-analytics" aria-label="Attendance and grade-entry progress">
                    <article class="teacher-panel teacher-dashboard-analytics-card" aria-labelledby="teacher-attendance-trend-heading">
                        <div class="teacher-dashboard-section-header">
                            <div class="teacher-dashboard-panel-title-wrap">
                                <span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="chart-no-axes-combined"></i></span>
                                <div><p class="teacher-dashboard-section-eyebrow">Attendance overview</p><h2 class="teacher-panel-title" id="teacher-attendance-trend-heading">Attendance trend</h2></div>
                            </div>
                        </div>
                        <?php if ($teacherAttendanceTrend === []): ?>
                            <div class="teacher-dashboard-analytics-empty"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><span>No recorded attendance data is available yet.</span></div>
                        <?php else: ?>
                            <?php
                            $attendanceRingCircumference = 2 * M_PI * 46;
                            $attendanceRingProgress = $teacherAttendanceRate === null
                                ? 0
                                : $attendanceRingCircumference * (max(0, min(100, $teacherAttendanceRate)) / 100);
                            ?>
                            <div class="teacher-dashboard-attendance-layout">
                                <div class="teacher-dashboard-attendance-plot">
                                    <p class="teacher-dashboard-attendance-period">Latest <?= count($teacherAttendanceTrend) ?> recorded sessions</p>
                                    <div class="teacher-dashboard-trend-chart" role="img" aria-label="Recorded session attendance rates: <?= teacherDashboardEsc(implode('; ', array_map(static fn(array $point): string => teacherDashboardDateLabel($point['meeting_date']) . ' ' . teacherDashboardPercent($point['attendance_rate']), $teacherAttendanceTrend))) ?>">
                                        <div class="teacher-dashboard-trend-y-axis" aria-hidden="true"><span>100%</span><span>75%</span><span>50%</span><span>25%</span><span>0%</span></div>
                                        <svg class="teacher-dashboard-trend-svg" viewBox="0 0 600 280" role="presentation" aria-hidden="true">
                                            <defs><linearGradient id="teacherAttendanceArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#2f83bd" stop-opacity=".2"/><stop offset="100%" stop-color="#2f83bd" stop-opacity=".015"/></linearGradient></defs>
                                            <line x1="0" y1="20" x2="600" y2="20" class="teacher-dashboard-trend-gridline" />
                                            <line x1="0" y1="80" x2="600" y2="80" class="teacher-dashboard-trend-gridline" />
                                            <line x1="0" y1="140" x2="600" y2="140" class="teacher-dashboard-trend-gridline" />
                                            <line x1="0" y1="200" x2="600" y2="200" class="teacher-dashboard-trend-gridline" />
                                            <line x1="0" y1="260" x2="600" y2="260" class="teacher-dashboard-trend-gridline" />
                                            <?php if ($teacherAttendanceAreaPath !== ''): ?><path d="<?= teacherDashboardEsc($teacherAttendanceAreaPath) ?>" class="teacher-dashboard-trend-area" /><?php endif; ?>
                                            <?php foreach ($teacherAttendancePlotPoints as $point): ?>
                                                <rect x="<?= number_format(max(0, (float) $point['x'] - 14), 2, '.', '') ?>" y="<?= number_format((float) $point['y'], 2, '.', '') ?>" width="28" height="<?= number_format(max(0, 260 - (float) $point['y']), 2, '.', '') ?>" rx="4" class="teacher-dashboard-trend-bar" />
                                            <?php endforeach; ?>
                                            <?php if (count($teacherAttendancePlotPoints) >= 2): ?><polyline points="<?= teacherDashboardEsc($teacherAttendanceLinePath) ?>" class="teacher-dashboard-trend-line" /><?php endif; ?>
                                            <?php foreach ($teacherAttendancePlotPoints as $point): ?>
                                                <circle cx="<?= number_format((float) $point['x'], 2, '.', '') ?>" cy="<?= number_format((float) $point['y'], 2, '.', '') ?>" r="6" class="teacher-dashboard-trend-point"><title><?= teacherDashboardEsc(teacherDashboardDateLabel($point['date']) . ': ' . teacherDashboardPercent($point['rate']) . ', ' . $point['recorded_count'] . ' recorded attendance records, ' . $point['label']) ?></title></circle>
                                            <?php endforeach; ?>
                                        </svg>
                                        <div class="teacher-dashboard-trend-x-axis" style="grid-template-columns: repeat(<?= max(1, count($teacherAttendancePlotPoints)) ?>, minmax(0, 1fr))" aria-hidden="true">
                                            <?php foreach ($teacherAttendancePlotPoints as $point): ?><span><?= teacherDashboardEsc(teacherDashboardDateLabel($point['date'])) ?></span><?php endforeach; ?>
                                        </div>
                                    </div>
                                    <p class="teacher-dashboard-chart-note">Attendance rate is Present records divided by all recorded attendance for the sessions shown.</p>
                                </div>
                                <aside class="teacher-dashboard-attendance-summary" aria-label="Attendance totals across displayed sessions">
                                    <div class="teacher-dashboard-attendance-average">
                                        <div class="teacher-dashboard-coverage-donut teacher-dashboard-attendance-donut" role="img" aria-label="Average attendance rate <?= teacherDashboardPercent($teacherAttendanceRate) ?>">
                                            <svg viewBox="0 0 112 112" aria-hidden="true"><circle class="teacher-dashboard-coverage-donut-track" cx="56" cy="56" r="46"/><circle class="teacher-dashboard-coverage-donut-value" cx="56" cy="56" r="46" stroke-dasharray="<?= number_format($attendanceRingProgress, 2, '.', '') ?> <?= number_format($attendanceRingCircumference, 2, '.', '') ?>"/></svg>
                                            <span><strong><?= teacherDashboardPercent($teacherAttendanceRate) ?></strong><small>attendance</small></span>
                                        </div>
                                        <div class="teacher-dashboard-attendance-average-copy"><span>Class average</span><strong><?= teacherDashboardPercent($teacherAttendanceRate) ?></strong><small><?= number_format($teacherAttendancePresent) ?> of <?= number_format($teacherAttendanceRecorded) ?> records present</small></div>
                                    </div>
                                    <div class="teacher-dashboard-attendance-counts">
                                        <div><span><i class="is-present" aria-hidden="true"></i>Present</span><strong><?= number_format($teacherAttendancePresent) ?></strong></div>
                                        <div><span><i class="is-late" aria-hidden="true"></i>Late</span><strong><?= number_format($teacherAttendanceLate) ?></strong></div>
                                        <div><span><i class="is-absent" aria-hidden="true"></i>Absent</span><strong><?= number_format($teacherAttendanceAbsent) ?></strong></div>
                                    </div>
                                </aside>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="teacher-panel teacher-dashboard-analytics-card" aria-labelledby="teacher-grade-coverage-heading">
                        <div class="teacher-dashboard-section-header">
                            <div class="teacher-dashboard-panel-title-wrap">
                                <span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="clipboard-list"></i></span>
                                <div><p class="teacher-dashboard-section-eyebrow">Grade entries</p><h2 class="teacher-panel-title" id="teacher-grade-coverage-heading">Grade entry progress</h2></div>
                            </div>
                            <a class="teacher-dashboard-grade-action" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php"><i data-lucide="external-link" aria-hidden="true"></i>Open Grade Ledger</a>
                        </div>
                        <?php if ($teacherGradeCoverage === []): ?>
                            <div class="teacher-dashboard-analytics-empty"><i data-lucide="chart-column-increasing" aria-hidden="true"></i><span>No applicable raw-score entry data is available yet.</span></div>
                        <?php else: ?>
                            <?php
                            $coverageRate = $teacherGradeCoverageRate === null ? 0 : max(0, min(100, $teacherGradeCoverageRate));
                            $teacherGradeRemaining = max(0, $teacherGradeExpected - $teacherGradePopulated);
                            ?>
                            <?php if ($teacherGradeRemaining > 0): ?>
                                <div class="teacher-dashboard-grade-alert" role="status">
                                    <i data-lucide="circle-alert" aria-hidden="true"></i>
                                    <span><strong><?= number_format($teacherGradeRemaining) ?> missing <?= $teacherGradeRemaining === 1 ? 'entry' : 'entries' ?></strong><small>Complete the remaining raw scores in the Grade Ledger.</small></span>
                                </div>
                            <?php endif; ?>
                            <div class="teacher-dashboard-coverage-overview">
                                <div class="teacher-dashboard-coverage-summary">
                                    <div class="teacher-dashboard-coverage-summary-heading"><span>Overall entry completion</span><strong><?= teacherDashboardPercent($teacherGradeCoverageRate) ?></strong></div>
                                    <small class="teacher-dashboard-coverage-scope">Across <?= $teacherGradeCoverageClassCount ?> active <?= $teacherGradeCoverageClassCount === 1 ? 'class' : 'classes' ?></small>
                                    <div class="teacher-dashboard-coverage-total-track" role="progressbar" aria-label="Overall grade-entry completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= number_format($coverageRate, 2, '.', '') ?>"><span style="width: <?= number_format($coverageRate, 2, '.', '') ?>%"></span></div>
                                    <div class="teacher-dashboard-coverage-legend"><span><i class="is-recorded" aria-hidden="true"></i>Recorded <b><?= number_format($teacherGradePopulated) ?></b></span><span><i class="is-remaining" aria-hidden="true"></i>Remaining <b><?= number_format($teacherGradeRemaining) ?></b></span></div>
                                </div>
                            </div>
                            <div class="teacher-dashboard-coverage-list">
                                <div class="teacher-dashboard-coverage-list-heading"><span>Grading period</span><span>Status</span></div>
                                <?php foreach ($teacherGradeCoverageDisplay as $coverage): ?>
                                    <?php $remainingEntries = max(0, (int) $coverage['expected_entries'] - (int) $coverage['populated_entries']); ?>
                                    <div class="teacher-dashboard-coverage-row">
                                        <div class="teacher-dashboard-coverage-label"><strong><?= teacherDashboardEsc($coverage['period_name']) ?></strong><small><?= number_format((int) $coverage['populated_entries']) ?> of <?= number_format((int) $coverage['expected_entries']) ?> recorded</small></div>
                                        <span class="teacher-dashboard-coverage-value <?= $remainingEntries === 0 ? 'is-complete' : 'is-incomplete' ?>" aria-label="<?= $remainingEntries === 0 ? 'Complete' : number_format($remainingEntries) . ' missing' ?>"><?= $remainingEntries === 0 ? 'Complete' : number_format($remainingEntries) . ' missing' ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="teacher-panel teacher-dashboard-attention <?= count($dashboardAttention) < 2 ? 'is-compact' : '' ?>" id="teacher-attention">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap">
                            <span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="users-round"></i></span>
                            <div>
                                <p class="teacher-dashboard-section-eyebrow">Action items</p>
                                <h2 class="teacher-panel-title">Needs attention</h2>
                            </div>
                        </div>
                        <span class="teacher-dashboard-section-count"><?= count($dashboardAttention) ?> <?= count($dashboardAttention) === 1 ? 'item' : 'items' ?></span>
                    </div>
                    <?php if ($dashboardAttention === []): ?>
                        <div class="teacher-dashboard-empty">
                            <span class="teacher-dashboard-empty-icon"><i data-lucide="circle-check" aria-hidden="true"></i></span>
                            <div><strong>No active attention items</strong><p>Attendance concerns, incomplete raw grade entries, and submitted referrals will appear here.</p></div>
                        </div>
                    <?php else: ?>
                        <div class="teacher-dashboard-attention-list">
                            <?php foreach ($dashboardAttention as $item): ?>
                                <article class="teacher-dashboard-attention-item">
                                    <span class="teacher-dashboard-attention-icon" aria-hidden="true"><i data-lucide="user-round"></i></span>
                                    <div class="teacher-dashboard-attention-identity">
                                        <strong><?= teacherDashboardEsc($item['student_name']) ?></strong>
                                        <small><?= teacherDashboardEsc($item['student_number']) ?> · <?= teacherDashboardEsc($item['class_context']) ?></small>
                                    </div>
                                    <div class="teacher-dashboard-evidence"><?php foreach ($item['reasons'] as $reason): ?><span><?= teacherDashboardEsc($reason) ?></span><?php endforeach; ?></div>
                                    <div class="teacher-dashboard-attention-actions"><?php foreach ($item['links'] as $link): ?><a class="teacher-dashboard-action-link" href="<?= teacherDashboardEsc($link['url']) ?>"><i data-lucide="<?= $link['label'] === 'Referral' ? 'clipboard-plus' : ($link['label'] === 'Grades' ? 'chart-column' : 'calendar-check') ?>" aria-hidden="true"></i><?= $link['label'] === 'Referral' ? 'View Referral' : ($link['label'] === 'Grades' ? 'Open Grades' : 'Review Attendance') ?></a><?php endforeach; ?></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="teacher-panel teacher-dashboard-agenda-panel <?= $upcomingSchedules !== [] ? 'has-next' : '' ?>" id="teacher-schedule" aria-labelledby="teacher-agenda-heading">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap">
                            <span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="calendar-days"></i></span>
                            <div><p class="teacher-dashboard-section-eyebrow">Your schedule</p><h2 class="teacher-panel-title" id="teacher-agenda-heading">Today and next</h2></div>
                        </div>
                        <a class="teacher-dashboard-text-link" href="<?= APP_URL ?>/dashboard/teacher_my_class.php">View all classes</a>
                    </div>
                    <div class="teacher-dashboard-agenda-group">
                        <h3 class="teacher-dashboard-agenda-label">Today <span><?= count($todaySchedules) ?></span></h3>
                        <?php if ($todaySchedules === []): ?>
                            <p class="teacher-dashboard-agenda-empty">No classes are scheduled today.</p>
                        <?php else: ?>
                            <div class="teacher-dashboard-schedule-list">
                                <?php foreach ($todaySchedules as $schedule): ?>
                                    <?php $sessionStatus = (string) ($schedule['attendance_status'] ?? 'Scheduled'); ?>
                                    <a class="teacher-dashboard-schedule-item is-today" href="<?= APP_URL ?>/dashboard/teacher_attendance_scanner.php?operational_class_id=<?= (int) $schedule['operational_class_id'] ?>" aria-label="Open attendance for <?= teacherDashboardEsc($schedule['subject_name'] . ' — ' . $schedule['section_name']) ?>">
                                        <span class="teacher-dashboard-schedule-time"><?= teacherDashboardEsc(date('g:i A', strtotime($schedule['start_time']))) ?><small><?= teacherDashboardEsc(date('g:i A', strtotime($schedule['end_time']))) ?></small></span>
                                        <span class="teacher-dashboard-schedule-details"><strong><?= teacherDashboardEsc($schedule['subject_name']) ?></strong><small><?= teacherDashboardEsc($schedule['section_name']) ?> · <?= teacherDashboardEsc($schedule['room'] ?: 'Room not recorded') ?></small></span>
                                        <span class="teacher-dashboard-schedule-status"><?= teacherDashboardEsc($sessionStatus) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($upcomingSchedules !== []): ?>
                        <div class="teacher-dashboard-agenda-group is-next">
                            <h3 class="teacher-dashboard-agenda-label">Next class</h3>
                            <div class="teacher-dashboard-schedule-list">
                                <?php foreach (array_slice($upcomingSchedules, 0, 1) as $schedule): ?>
                                    <a class="teacher-dashboard-schedule-item" href="<?= APP_URL ?>/dashboard/teacher_class_list.php?operational_class_id=<?= (int) $schedule['operational_class_id'] ?>">
                                        <span class="teacher-dashboard-schedule-day"><?= teacherDashboardEsc($schedule['day']) ?><small><?= teacherDashboardEsc(date('g:i A', strtotime($schedule['start_time']))) ?></small></span>
                                        <span class="teacher-dashboard-schedule-details"><strong><?= teacherDashboardEsc($schedule['subject_name']) ?></strong><small><?= teacherDashboardEsc($schedule['section_name']) ?> · <?= teacherDashboardEsc(date('g:i A', strtotime($schedule['end_time']))) ?></small></span>
                                        <span class="teacher-dashboard-schedule-status"><?= teacherDashboardEsc($schedule['room'] ?: 'Room not recorded') ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($teacherPendingImports !== []): ?>
                <section class="teacher-panel teacher-dashboard-pending">
                    <div class="teacher-dashboard-section-header">
                        <div><p class="teacher-dashboard-section-eyebrow">Continue work</p><h2 class="teacher-panel-title">Pending grade imports</h2></div>
                        <a class="teacher-dashboard-text-link" href="<?= APP_URL ?>/dashboard/teacher_grade_import.php">Open Grade Import</a>
                    </div>
                    <div class="teacher-dashboard-pending-list">
                        <?php foreach ($teacherPendingImports as $import): ?><a href="<?= APP_URL ?>/dashboard/teacher_grade_import.php?operational_class_id=<?= (int) $import['operational_class_id'] ?>" class="teacher-dashboard-pending-item"><span><strong><?= teacherDashboardEsc($import['original_filename']) ?></strong><small><?= teacherDashboardEsc($import['template_type']) ?></small></span><span class="teacher-dashboard-pending-status"><?= teacherDashboardEsc($import['status']) ?></span></a><?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </div>

    </main>


    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>
