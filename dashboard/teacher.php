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
if ($upcomingSchedules === [] && $todaySchedules !== []) {
    $upcomingSchedules = array_map(static function (array $row): array {
        $row['schedule_state'] = 'Upcoming';
        $row['day_offset'] = 7;
        $row['scheduled_date'] = date('Y-m-d', strtotime('+7 days'));
        return $row;
    }, $todaySchedules);
}
$dashboardAttention = [];
$teacherAnalyticsClasses = [];
foreach (aprismGradeTeacherClasses($pdo, $teacherId) as $class) {
    if (($class['status'] ?? '') !== 'Active'
        || ($currentSchoolYear !== '' && (string) ($class['school_year'] ?? '') !== $currentSchoolYear)
        || !aprismClassIsInCurrentTeachingContext($pdo, (int) $class['operational_class_id'])) {
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
$teacherCurrentClassIds = array_map(
    static fn(array $class): int => (int) ($class['operational_class_id'] ?? 0),
    $teacherAnalyticsClasses
);
$dashboardAttention = aprismTeacherDashboardAttention(
    $pdo,
    $teacherId,
    $currentSchoolYear !== '' ? $currentSchoolYear : null,
    $teacherCurrentClassIds
);

$teacherClassAttendance = [];

$teacherAttendanceTrend = [];
foreach ($teacherAnalyticsClasses as $class) {
    $attendanceAnalytics = aprismTeacherAnalyticsAttendance(
        $pdo,
        $teacherId,
        (int) $class['operational_class_id']
    );

    $classSessions = array_slice($attendanceAnalytics['sessions'] ?? [], -6);
    $teacherClassAttendance[] = [
        'class' => $class,
        'attendance_rate' => $attendanceAnalytics['summary']['attendance_rate'] ?? null,
        'session_count' => count($attendanceAnalytics['sessions'] ?? []),
        'recent_sessions' => $classSessions,
    ];

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
$teacherAttendanceMetrics = aprismMonitoringAttendanceMetrics([
    'absent_count' => $teacherAttendanceAbsent,
    'late_count' => $teacherAttendanceLate,
]);

$teacherAttendancePlotPoints = [];
$attendancePlotCount = count($teacherAttendanceTrend);
$teacherAttendanceChartMax = 5;
foreach ($teacherAttendanceTrend as $trendPoint) {
    $teacherAttendanceChartMax = max($teacherAttendanceChartMax, (int) $trendPoint['recorded_count']);
}
$teacherAttendanceChartMax = (int) (ceil($teacherAttendanceChartMax / 5) * 5);
foreach ($teacherAttendanceTrend as $pointIndex => $trendPoint) {
    $teacherAttendancePlotPoints[] = [
        'x' => $attendancePlotCount <= 1 ? 300 : (($pointIndex + 0.5) * 600 / $attendancePlotCount),
        'present_count' => (int) $trendPoint['present_count'],
        'late_count' => (int) $trendPoint['late_count'],
        'absent_count' => (int) $trendPoint['absent_count'],
        'date' => (string) $trendPoint['meeting_date'],
        'label' => (string) $trendPoint['label'],
        'recorded_count' => (int) $trendPoint['recorded_count'],
    ];
}

$teacherGradeCoverage = [];
$teacherGradeExpected = 0;
$teacherGradePopulated = 0;
$teacherGradeCoveragePeriods = [];
$teacherGradeClassPeriods = [];
$teacherGradeCoverageClassIds = [];
foreach ($teacherAnalyticsClasses as $class) {
    $classId = (int) $class['operational_class_id'];
    if (!isset($teacherGradeClassPeriods[$classId])) {
        $teacherGradeClassPeriods[$classId] = [
            'subject_name' => (string) ($class['subject_name'] ?? ''),
            'section_name' => (string) ($class['section_name'] ?? ''),
            'periods' => [],
        ];
    }
    foreach (aprismGradePeriodsForClass($pdo, $class) as $period) {
        $analytics = aprismGradeAnalyticsBuild(
            $pdo,
            $teacherId,
            $classId,
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
                'start_date' => (string) ($period['start_date'] ?? ''),
                'end_date' => (string) ($period['end_date'] ?? ''),
                'populated_entries' => 0,
                'expected_entries' => 0,
                'class_ids' => [],
            ];
        }
        $teacherGradeCoveragePeriods[$periodId]['populated_entries'] += $populatedEntries;
        $teacherGradeCoveragePeriods[$periodId]['expected_entries'] += $expectedEntries;
        $teacherGradeCoveragePeriods[$periodId]['class_ids'][$classId] = true;
        $teacherGradeCoverageClassIds[$classId] = true;
        $teacherGradeClassPeriods[$classId]['periods'][$periodId] = [
            'period_name' => (string) ($period['period_name'] ?? 'Academic period'),
            'populated_entries' => $populatedEntries,
            'expected_entries' => $expectedEntries,
            'coverage_percent' => $expectedEntries > 0 ? ($populatedEntries / $expectedEntries) * 100 : null,
        ];
    }
}

foreach ($teacherGradeCoveragePeriods as $periodCoverage) {
    $expectedEntries = (int) $periodCoverage['expected_entries'];
    $populatedEntries = (int) $periodCoverage['populated_entries'];
    $teacherGradeCoverage[] = [
        'period_name' => (string) $periodCoverage['period_name'],
        'start_date' => (string) ($periodCoverage['start_date'] ?? ''),
        'end_date' => (string) ($periodCoverage['end_date'] ?? ''),
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
$teacherGradePeriodOptions = array_values(array_reduce(
    $teacherGradeCoverage,
    static function (array $options, array $period): array {
        $options[(string) $period['period_name']] = (string) $period['period_name'];
        return $options;
    },
    []
));
$teacherGradeActivePeriod = '';
$dashboardToday = date('Y-m-d');
foreach ($teacherGradeCoverage as $period) {
    if ($period['start_date'] !== '' && $period['end_date'] !== ''
        && $period['start_date'] <= $dashboardToday && $dashboardToday <= $period['end_date']) {
        $teacherGradeActivePeriod = (string) $period['period_name'];
        break;
    }
}
if ($teacherGradeActivePeriod === '' && $teacherGradePeriodOptions !== []) {
    $teacherGradeActivePeriod = (string) end($teacherGradePeriodOptions);
}

$teacherGradeClassCoverage = [];
foreach ($teacherGradeClassPeriods as $classId => $classCoverage) {
    foreach ($classCoverage['periods'] as $periodCoverage) {
        $teacherGradeClassCoverage[] = [
            'class_id' => (int) $classId,
            'subject_name' => (string) $classCoverage['subject_name'],
            'section_name' => (string) $classCoverage['section_name'],
            'period_name' => (string) $periodCoverage['period_name'],
            'populated_entries' => (int) $periodCoverage['populated_entries'],
            'expected_entries' => (int) $periodCoverage['expected_entries'],
            'coverage_percent' => $periodCoverage['coverage_percent'],
        ];
    }
}

$teacherReferralRecords = aprismTeacherDashboardReferralRecords(
    $pdo,
    $teacherId,
    $currentSchoolYear !== '' ? $currentSchoolYear : null,
    $teacherCurrentClassIds
);
$teacherReferralMetrics = aprismTeacherDashboardReferralMetrics($teacherReferralRecords);

$teacherRiskClassEvidence = aprismMonitoringCurrentClassRiskEvidence($pdo, 'teacher', $teacherId);
$teacherRiskSummaries = aprismMonitoringStudentRiskSummaries($teacherRiskClassEvidence);
$teacherRiskTierCounts = [1 => 0, 2 => 0, 3 => 0];
$teacherRiskNoTierCount = 0;
$teacherRiskNoTierAssessableCount = 0;
$teacherRiskInsufficientCount = 0;
$teacherRiskConsecutiveStudents = [];
foreach ($teacherRiskSummaries as $studentId => $riskSummary) {
    $tier = (int) ($riskSummary['tier'] ?? 0);
    if (isset($teacherRiskTierCounts[$tier])) {
        $teacherRiskTierCounts[$tier]++;
    } else {
        $teacherRiskNoTierCount++;
        if (!empty($riskSummary['assessable'])) {
            $teacherRiskNoTierAssessableCount++;
        }
    }
    if (empty($riskSummary['assessable'])) {
        $teacherRiskInsufficientCount++;
    }
    foreach (($riskSummary['class_evidence'] ?? []) as $classEvidence) {
        if ((int) ($classEvidence['consecutive_absence_count'] ?? 0) >= 3) {
            $teacherRiskConsecutiveStudents[(int) $studentId] = true;
            break;
        }
    }
}
$teacherRiskConsecutiveCount = count($teacherRiskConsecutiveStudents);

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
                <h1 class="teacher-page-title">Dashboard</h1>
                <a class="teacher-dashboard-header-action" href="<?= APP_URL ?>/dashboard/teacher_my_class.php"><i data-lucide="list-checks" aria-hidden="true"></i> Classes</a>
            </header>

            <section class="teacher-dashboard-summary" aria-label="Teacher overview counts">
                <a class="teacher-dashboard-summary-item" href="<?= APP_URL ?>/dashboard/teacher_my_class.php">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-blue"><i data-lucide="book-open-check" aria-hidden="true"></i></span>
                    <small>Active Classes</small>
                    <strong><?= (int) $teacherSummary['active_classes'] ?></strong>
                </a>
                <a class="teacher-dashboard-summary-item" href="<?= APP_URL ?>/dashboard/teacher_students.php">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-navy"><i data-lucide="users" aria-hidden="true"></i></span>
                    <small>Current Students</small>
                    <strong><?= (int) $teacherSummary['current_students'] ?></strong>
                </a>
                <a class="teacher-dashboard-summary-item" href="#teacher-schedule">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-yellow"><i data-lucide="calendar-days" aria-hidden="true"></i></span>
                    <small>Today’s Classes</small>
                    <strong><?= count($todaySchedules) ?></strong>
                </a>
                <a class="teacher-dashboard-summary-item" href="<?= APP_URL ?>/dashboard/teacher_referrals.php">
                    <span class="teacher-dashboard-summary-icon teacher-dashboard-summary-icon-red"><i data-lucide="clipboard-list" aria-hidden="true"></i></span>
                    <small>Pending Referrals</small>
                    <strong><?= (int) $teacherReferralMetrics['statuses']['Submitted'] ?></strong>
                </a>
            </section>

            <?php
            $teacherAttendanceChartMax = max(1, $teacherAttendanceChartMax);
            $teacherAttendanceRingCircumference = 2 * M_PI * 46;
            $teacherAttendanceRingProgress = $teacherAttendanceRate === null
                ? 0
                : $teacherAttendanceRingCircumference * (max(0, min(100, $teacherAttendanceRate)) / 100);
            $teacherGradeRemaining = max(0, $teacherGradeExpected - $teacherGradePopulated);
            $teacherGradeWarning = null;
            foreach (array_reverse($teacherGradeCoverage) as $periodCoverage) {
                if ($periodCoverage['end_date'] !== '' && $periodCoverage['end_date'] < $dashboardToday
                    && (int) $periodCoverage['expected_entries'] > (int) $periodCoverage['populated_entries']) {
                    $teacherGradeWarning = $periodCoverage;
                    break;
                }
            }
            $teacherAttentionPreview = array_slice($dashboardAttention, 0, 4);
            $teacherReferralStatuses = $teacherReferralMetrics['statuses'];
            $teacherReferralReasonColors = [
                'frequent_absences' => '#e9575b',
                'repeated_lateness' => '#f2b21b',
                'low_academic_performance' => '#3f8ec9',
                'missing_late_requirements' => '#9267d6',
                'behavior_conduct' => '#16a27a',
                'other' => '#f28c37',
                'legacy_unclassified' => '#718096',
            ];
            $teacherReferralMeaningfulReasons = array_values(array_filter(
                $teacherReferralMetrics['reasons'],
                static fn(array $reason): bool => ($reason['key'] ?? '') !== 'legacy_unclassified'
            ));
            $teacherReferralUnclassifiedCount = (int) array_sum(array_map(
                static fn(array $reason): int => ($reason['key'] ?? '') === 'legacy_unclassified' ? (int) $reason['count'] : 0,
                $teacherReferralMetrics['reasons']
            ));
            $teacherReferralClassifiedCount = (int) array_sum(array_column($teacherReferralMeaningfulReasons, 'count'));
            $teacherReferralHasUsefulDistribution = $teacherReferralMeaningfulReasons !== []
                && ($teacherReferralUnclassifiedCount === 0 || $teacherReferralClassifiedCount > $teacherReferralUnclassifiedCount);
            $teacherReferralReasonLabels = array_map(static fn(array $reason): string => (string) $reason['label'], $teacherReferralMeaningfulReasons);
            $teacherReferralReasonValues = array_map(static fn(array $reason): int => (int) $reason['count'], $teacherReferralMeaningfulReasons);
            $teacherReferralReasonColors = array_map(static fn(array $reason): string => $teacherReferralReasonColors[$reason['key']] ?? '#718096', $teacherReferralMeaningfulReasons);
            ?>

            <section class="teacher-dashboard-reference-row teacher-dashboard-attendance-row" aria-label="Attendance summary">
                <article class="teacher-panel teacher-dashboard-analytics-card teacher-dashboard-attendance-trend" aria-labelledby="teacher-attendance-trend-heading">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap">
                            <span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="chart-no-axes-combined"></i></span>
                            <h2 class="teacher-panel-title" id="teacher-attendance-trend-heading">Attendance trend</h2>
                        </div>
                        <?php if ($teacherAttendanceTrend !== []): ?><span class="teacher-dashboard-period-context">Latest <?= count($teacherAttendanceTrend) ?> sessions</span><?php endif; ?>
                    </div>
                    <?php if ($teacherAttendanceTrend === []): ?>
                        <div class="teacher-dashboard-analytics-empty"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><span>No recorded attendance data is available yet.</span></div>
                    <?php else: ?>
                        <div class="teacher-dashboard-attendance-layout">
                            <div class="teacher-dashboard-attendance-plot">
                                <?php
                                $attendanceSummaryLabels = implode('; ', array_map(
                                    static fn(array $point): string => teacherDashboardDateLabel($point['date']) . ': '
                                        . $point['present_count'] . ' present, ' . $point['late_count'] . ' late, ' . $point['absent_count'] . ' absent',
                                    $teacherAttendancePlotPoints
                                ));
                                ?>
                                <div class="teacher-dashboard-trend-chart" role="img" aria-label="Attendance records by session. <?= teacherDashboardEsc($attendanceSummaryLabels) ?>">
                                    <div class="teacher-dashboard-trend-y-axis" aria-hidden="true">
                                        <?php for ($tick = 4; $tick >= 0; $tick--): ?><span><?= (int) round($teacherAttendanceChartMax * $tick / 4) ?></span><?php endfor; ?>
                                    </div>
                                    <svg class="teacher-dashboard-trend-svg" viewBox="0 0 600 280" role="presentation" aria-hidden="true">
                                        <?php for ($tick = 0; $tick <= 4; $tick++): ?>
                                            <?php $gridY = 20 + ($tick * 60); ?>
                                            <line x1="0" y1="<?= $gridY ?>" x2="600" y2="<?= $gridY ?>" class="teacher-dashboard-trend-gridline" />
                                        <?php endfor; ?>
                                        <?php foreach ($teacherAttendancePlotPoints as $point): ?>
                                            <?php
                                            $barScale = 240 / $teacherAttendanceChartMax;
                                            $barBottom = 260;
                                            $presentHeight = $point['present_count'] * $barScale;
                                            $lateHeight = $point['late_count'] * $barScale;
                                            $absentHeight = $point['absent_count'] * $barScale;
                                            $presentY = $barBottom - $presentHeight;
                                            $lateY = $presentY - $lateHeight;
                                            $absentY = $lateY - $absentHeight;
                                            $barX = max(0, (float) $point['x'] - 14);
                                            $barTitle = teacherDashboardDateLabel($point['date']) . ': ' . $point['present_count'] . ' present, ' . $point['late_count'] . ' late, ' . $point['absent_count'] . ' absent · ' . $point['label'];
                                            ?>
                                            <g>
                                                <title><?= teacherDashboardEsc($barTitle) ?></title>
                                                <?php if ($presentHeight > 0): ?><rect x="<?= number_format($barX, 2, '.', '') ?>" y="<?= number_format($presentY, 2, '.', '') ?>" width="28" height="<?= number_format($presentHeight, 2, '.', '') ?>" class="teacher-dashboard-trend-bar is-present" /><?php endif; ?>
                                                <?php if ($lateHeight > 0): ?><rect x="<?= number_format($barX, 2, '.', '') ?>" y="<?= number_format($lateY, 2, '.', '') ?>" width="28" height="<?= number_format($lateHeight, 2, '.', '') ?>" class="teacher-dashboard-trend-bar is-late" /><?php endif; ?>
                                                <?php if ($absentHeight > 0): ?><rect x="<?= number_format($barX, 2, '.', '') ?>" y="<?= number_format($absentY, 2, '.', '') ?>" width="28" height="<?= number_format($absentHeight, 2, '.', '') ?>" class="teacher-dashboard-trend-bar is-absent" /><?php endif; ?>
                                            </g>
                                        <?php endforeach; ?>
                                    </svg>
                                    <div class="teacher-dashboard-trend-x-axis" style="grid-template-columns: repeat(<?= max(1, count($teacherAttendancePlotPoints)) ?>, minmax(0, 1fr))" aria-hidden="true">
                                        <?php foreach ($teacherAttendancePlotPoints as $point): ?><span><?= teacherDashboardEsc(teacherDashboardDateLabel($point['date'])) ?></span><?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="teacher-dashboard-plot-legend" aria-label="Attendance status legend">
                                    <span><i class="is-present" aria-hidden="true"></i>Present</span>
                                    <span><i class="is-late" aria-hidden="true"></i>Late</span>
                                    <span><i class="is-absent" aria-hidden="true"></i>Absent</span>
                                </div>
                            </div>
                            <aside class="teacher-dashboard-attendance-summary" aria-label="Attendance totals across displayed sessions">
                                <div class="teacher-dashboard-attendance-average">
                                    <div class="teacher-dashboard-coverage-donut teacher-dashboard-attendance-donut" role="img" aria-label="Average attendance rate <?= teacherDashboardPercent($teacherAttendanceRate) ?>">
                                        <svg viewBox="0 0 112 112" aria-hidden="true"><circle class="teacher-dashboard-coverage-donut-track" cx="56" cy="56" r="46"/><circle class="teacher-dashboard-coverage-donut-value" cx="56" cy="56" r="46" stroke-dasharray="<?= number_format($teacherAttendanceRingProgress, 2, '.', '') ?> <?= number_format($teacherAttendanceRingCircumference, 2, '.', '') ?>"/></svg>
                                        <span><strong><?= teacherDashboardPercent($teacherAttendanceRate) ?></strong><small>attendance</small></span>
                                    </div>
                                    <div class="teacher-dashboard-attendance-average-copy"><span>Class average</span><strong><?= teacherDashboardPercent($teacherAttendanceRate) ?></strong><small><?= number_format($teacherAttendancePresent) ?> of <?= number_format($teacherAttendanceRecorded) ?> records present</small></div>
                                </div>
                                <div class="teacher-dashboard-attendance-counts">
                                    <div><span><i class="is-present" aria-hidden="true"></i>Present</span><strong><?= number_format($teacherAttendancePresent) ?></strong></div>
                                    <div><span><i class="is-late" aria-hidden="true"></i>Late</span><strong><?= number_format($teacherAttendanceLate) ?></strong></div>
                                    <div><span><i class="is-absent" aria-hidden="true"></i>Absent</span><strong><?= number_format($teacherAttendanceAbsent) ?></strong></div>
                                    <div class="teacher-dashboard-effective-absences"><span>Effective absences</span><strong><?= number_format((int) $teacherAttendanceMetrics['effective_absent_count']) ?></strong></div>
                                </div>
                            </aside>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="teacher-panel teacher-dashboard-attendance-class-card" aria-labelledby="teacher-attendance-class-heading">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap"><span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="users-round"></i></span><h2 class="teacher-panel-title" id="teacher-attendance-class-heading">Attendance by class</h2></div>
                    </div>
                    <div class="teacher-dashboard-class-legend" aria-label="Session attendance rate legend">
                        <span><i class="is-excellent" aria-hidden="true"></i>95%+</span><span><i class="is-high" aria-hidden="true"></i>90–94%</span><span><i class="is-mid" aria-hidden="true"></i>80–89%</span><span><i class="is-low" aria-hidden="true"></i>Below 80%</span><span><i class="is-no-session" aria-hidden="true"></i>No session</span>
                    </div>
                    <?php if ($teacherClassAttendance === []): ?>
                        <div class="teacher-dashboard-analytics-empty"><i data-lucide="book-open" aria-hidden="true"></i><span>No current classes are available.</span></div>
                    <?php else: ?>
                        <div class="teacher-dashboard-class-list">
                            <?php foreach (array_slice($teacherClassAttendance, 0, 5) as $classRow): ?>
                                <?php
                                $class = $classRow['class'];
                                $classSessions = $classRow['recent_sessions'];
                                $classSessionCount = count($classSessions);
                                ?>
                                <div class="teacher-dashboard-class-row">
                                    <div class="teacher-dashboard-class-heading"><span><strong><?= teacherDashboardEsc($class['subject_name']) ?></strong><small><?= teacherDashboardEsc($class['section_name']) ?></small></span><strong><?= teacherDashboardPercent($classRow['attendance_rate'] === null ? null : (float) $classRow['attendance_rate']) ?></strong></div>
                                    <div class="teacher-dashboard-session-blocks" role="img" aria-label="Recent attendance sessions for <?= teacherDashboardEsc($class['subject_name'] . ' ' . $class['section_name']) ?>">
                                        <?php foreach ($classSessions as $session): ?>
                                            <?php
                                            $sessionRate = $session['attendance_rate'];
                                            $sessionClass = $sessionRate === null ? 'is-no-session' : ((float) $sessionRate >= 95 ? 'is-excellent' : ((float) $sessionRate >= 90 ? 'is-high' : ((float) $sessionRate >= 80 ? 'is-mid' : 'is-low')));
                                            $sessionLabel = $sessionRate === null ? 'No attendance records' : teacherDashboardPercent((float) $sessionRate) . ' attendance rate';
                                            ?>
                                            <span class="<?= $sessionClass ?>" title="<?= teacherDashboardEsc(teacherDashboardDateLabel((string) $session['meeting_date']) . ' · ' . $sessionLabel) ?>"></span>
                                        <?php endforeach; ?>
                                        <?php for ($emptyBlock = $classSessionCount; $emptyBlock < 6; $emptyBlock++): ?><span class="is-no-session" title="No recorded session"></span><?php endfor; ?>
                                    </div>
                                    <?php if ($classSessions !== []): ?><div class="teacher-dashboard-session-dates" aria-hidden="true"><?php foreach ($classSessions as $session): ?><span><?= teacherDashboardEsc(teacherDashboardDateLabel((string) $session['meeting_date'])) ?></span><?php endforeach; ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </section>

            <section class="teacher-dashboard-reference-row teacher-dashboard-secondary-row" aria-label="Grade and attendance monitoring">
                <article class="teacher-panel teacher-dashboard-grade-panel" aria-labelledby="teacher-grade-coverage-heading">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap"><span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="clipboard-list"></i></span><h2 class="teacher-panel-title" id="teacher-grade-coverage-heading">Grade Coverage</h2></div>
                        <div class="teacher-dashboard-section-tools">
                            <?php if ($teacherGradePeriodOptions !== []): ?>
                                <label class="teacher-dashboard-period-select-label" for="teacher-grade-period">Period</label>
                                <select class="teacher-dashboard-period-select" id="teacher-grade-period" aria-label="Grading period">
                                    <?php foreach ($teacherGradePeriodOptions as $periodName): ?><option value="<?= teacherDashboardEsc($periodName) ?>" <?= $periodName === $teacherGradeActivePeriod ? 'selected' : '' ?>><?= teacherDashboardEsc($periodName) ?></option><?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <a class="teacher-dashboard-text-link" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php">Grade Ledger</a>
                        </div>
                    </div>
                    <?php if ($teacherGradeCoverage === []): ?>
                        <div class="teacher-dashboard-analytics-empty"><i data-lucide="chart-column-increasing" aria-hidden="true"></i><span>No applicable raw-score entry data is available yet.</span></div>
                    <?php else: ?>
                        <?php if ($teacherGradeWarning !== null): ?><div class="teacher-dashboard-grade-alert" role="status"><i data-lucide="circle-alert" aria-hidden="true"></i><span><?= teacherDashboardEsc($teacherGradeWarning['period_name']) ?> raw scores remain incomplete for <?= (int) $teacherGradeWarning['class_count'] ?> <?= (int) $teacherGradeWarning['class_count'] === 1 ? 'class' : 'classes' ?>.</span></div><?php endif; ?>
                        <div class="teacher-dashboard-grade-classes" id="teacher-grade-class-progress">
                            <?php foreach ($teacherGradeClassCoverage as $coverage): ?>
                                <?php $coverageRate = $coverage['coverage_percent'] === null ? null : max(0, min(100, (float) $coverage['coverage_percent'])); ?>
                                <div class="teacher-dashboard-grade-class" data-period="<?= teacherDashboardEsc($coverage['period_name']) ?>" <?= $coverage['period_name'] !== $teacherGradeActivePeriod ? 'hidden' : '' ?>>
                                    <div class="teacher-dashboard-grade-class-heading"><span><strong><?= teacherDashboardEsc($coverage['subject_name']) ?></strong><small><?= teacherDashboardEsc($coverage['section_name']) ?></small></span><strong><?= teacherDashboardPercent($coverageRate) ?></strong></div>
                                    <div class="teacher-dashboard-grade-class-track" role="progressbar" aria-label="<?= teacherDashboardEsc($coverage['subject_name'] . ' ' . $coverage['section_name'] . ' ' . $coverage['period_name']) ?> raw-score coverage" aria-valuemin="0" aria-valuemax="100" <?= $coverageRate === null ? '' : 'aria-valuenow="' . number_format($coverageRate, 2, '.', '') . '"' ?>><span style="width: <?= $coverageRate === null ? '0' : number_format($coverageRate, 2, '.', '') ?>%"></span></div>
                                    <small class="teacher-dashboard-grade-class-counts"><?= number_format($coverage['populated_entries']) ?> of <?= number_format($coverage['expected_entries']) ?> raw scores recorded</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="teacher-dashboard-grade-overall">
                            <div class="teacher-dashboard-coverage-summary-heading"><span>Overall raw-score coverage</span><strong><?= teacherDashboardPercent($teacherGradeCoverageRate) ?></strong></div>
                            <div class="teacher-dashboard-coverage-total-track" role="progressbar" aria-label="Overall raw-score coverage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= number_format($teacherGradeCoverageRate ?? 0, 2, '.', '') ?>"><span style="width: <?= number_format($teacherGradeCoverageRate ?? 0, 2, '.', '') ?>%"></span></div>
                            <div class="teacher-dashboard-coverage-legend"><span><i class="is-recorded" aria-hidden="true"></i>Recorded <b><?= number_format($teacherGradePopulated) ?></b></span><span><i class="is-remaining" aria-hidden="true"></i>Remaining <b><?= number_format($teacherGradeRemaining) ?></b></span></div>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="teacher-panel teacher-dashboard-risk-panel" aria-labelledby="teacher-risk-tier-heading">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap"><span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="triangle-alert"></i></span><h2 class="teacher-panel-title" id="teacher-risk-tier-heading">Risk Tiers</h2></div>
                        <a class="teacher-dashboard-section-count teacher-dashboard-section-count-link" href="<?= APP_URL ?>/dashboard/teacher_risk_alerts.php"><?= number_format(count($teacherRiskSummaries)) ?> Students</a>
                    </div>
                    <?php $largestRiskTier = max(1, ...array_values($teacherRiskTierCounts)); ?>
                    <div class="teacher-dashboard-risk-bubbles" aria-label="Current risk tier counts">
                        <?php foreach ([3, 2, 1] as $tier): ?>
                            <?php $bubbleSize = (int) round(74 + (sqrt($teacherRiskTierCounts[$tier] / $largestRiskTier) * 60)); ?>
                            <a class="teacher-dashboard-risk-bubble is-tier-<?= $tier ?>" href="<?= APP_URL ?>/dashboard/teacher_risk_alerts.php?tier=<?= $tier ?>" style="--bubble-size: <?= $bubbleSize ?>px" aria-label="Tier <?= $tier ?>: <?= number_format($teacherRiskTierCounts[$tier]) ?> Students">
                                <strong><?= number_format($teacherRiskTierCounts[$tier]) ?></strong><span>Tier <?= $tier ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="teacher-dashboard-risk-evidence">
                        <div><strong><?= number_format($teacherRiskConsecutiveCount) ?></strong><span>Students with 3+ consecutive absences</span></div>
                        <?php if ($teacherRiskNoTierAssessableCount > 0): ?><div><strong><?= number_format($teacherRiskNoTierAssessableCount) ?></strong><span>Students not currently at risk</span></div><?php endif; ?>
                        <?php if ($teacherRiskInsufficientCount > 0): ?><div><strong><?= number_format($teacherRiskInsufficientCount) ?></strong><span>Students with insufficient evidence</span></div><?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="teacher-dashboard-reference-row teacher-dashboard-lower-row" aria-label="Teacher priorities and schedule">
                <section class="teacher-panel teacher-dashboard-attention <?= count($teacherAttentionPreview) < 2 ? 'is-compact' : '' ?>" id="teacher-attention" aria-labelledby="teacher-attention-heading">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap"><span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="users-round"></i></span><h2 class="teacher-panel-title" id="teacher-attention-heading">Needs attention</h2></div>
                        <span class="teacher-dashboard-section-count"><?= count($teacherAttentionPreview) ?> <?= count($teacherAttentionPreview) === 1 ? 'item' : 'items' ?></span>
                    </div>
                    <?php if ($teacherAttentionPreview === []): ?>
                        <div class="teacher-dashboard-empty"><span class="teacher-dashboard-empty-icon"><i data-lucide="circle-check" aria-hidden="true"></i></span><div><strong>No active attention items</strong><p>Attendance, raw-score, and submitted-referral work appears here.</p></div></div>
                    <?php else: ?>
                        <div class="teacher-dashboard-attention-list">
                            <?php foreach ($teacherAttentionPreview as $item): ?>
                                <?php
                                $studentId = (int) ($item['student_id'] ?? 0);
                                $riskTier = (int) ($teacherRiskSummaries[$studentId]['tier'] ?? 0);
                                $itemStatus = $riskTier > 0 ? 'Tier ' . $riskTier : (array_reduce($item['reasons'], static fn(bool $found, string $reason): bool => $found || str_starts_with($reason, 'Missing '), false) ? 'Grades' : 'Referral');
                                $firstLink = $item['links'][0] ?? null;
                                $initials = implode('', array_map(static fn(string $part): string => function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1), array_slice(preg_split('/\s+/', trim((string) $item['student_name'])) ?: [], 0, 2)));
                                ?>
                                <article class="teacher-dashboard-attention-item">
                                    <span class="teacher-dashboard-attention-icon" aria-hidden="true"><?= teacherDashboardEsc($initials !== '' ? $initials : 'S') ?></span>
                                    <div class="teacher-dashboard-attention-identity"><strong><?= teacherDashboardEsc($item['student_name']) ?></strong><small><?= teacherDashboardEsc($item['class_context']) ?></small><small><?= teacherDashboardEsc(implode(' · ', array_slice($item['reasons'], 0, 2))) ?></small></div>
                                    <span class="teacher-dashboard-attention-tier <?= $riskTier > 0 ? 'is-tier-' . $riskTier : 'is-other' ?>"><?= teacherDashboardEsc($itemStatus) ?></span>
                                    <?php if (is_array($firstLink)): ?><a class="teacher-dashboard-attention-review" href="<?= teacherDashboardEsc($firstLink['url']) ?>">Review</a><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="teacher-panel teacher-dashboard-agenda-panel <?= $upcomingSchedules !== [] ? 'has-next' : '' ?>" id="teacher-schedule" data-aprism-destination-focus aria-labelledby="teacher-agenda-heading">
                    <div class="teacher-dashboard-section-header">
                        <div class="teacher-dashboard-panel-title-wrap"><span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="calendar-days"></i></span><h2 class="teacher-panel-title" id="teacher-agenda-heading">Today and next</h2></div>
                    </div>
                    <div class="teacher-dashboard-agenda-group">
                        <h3 class="teacher-dashboard-agenda-label">Today <span><?= count($todaySchedules) ?></span></h3>
                        <?php if ($todaySchedules === []): ?>
                            <p class="teacher-dashboard-agenda-empty">No classes are scheduled today.</p>
                        <?php else: ?>
                            <div class="teacher-dashboard-schedule-list">
                                <?php foreach ($todaySchedules as $schedule): ?>
                                    <?php $sessionStatus = trim((string) ($schedule['attendance_status'] ?? '')); ?>
                                    <a class="teacher-dashboard-schedule-item is-today" href="<?= APP_URL ?>/dashboard/teacher_attendance_scanner.php?operational_class_id=<?= (int) $schedule['operational_class_id'] ?>" aria-label="Open attendance for <?= teacherDashboardEsc($schedule['subject_name'] . ' — ' . $schedule['section_name']) ?>">
                                        <span class="teacher-dashboard-schedule-time"><?= teacherDashboardEsc(date('g:i A', strtotime($schedule['start_time']))) ?><small><?= teacherDashboardEsc(date('g:i A', strtotime($schedule['end_time']))) ?></small></span>
                                        <span class="teacher-dashboard-schedule-details"><strong><?= teacherDashboardEsc($schedule['subject_name']) ?></strong><small><?= teacherDashboardEsc($schedule['section_name']) ?> · <?= teacherDashboardEsc($schedule['room'] ?: 'Room not recorded') ?></small><?php if ($sessionStatus !== '' && $sessionStatus !== 'Scheduled'): ?><small class="teacher-dashboard-schedule-state"><?= teacherDashboardEsc($sessionStatus) ?></small><?php endif; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="teacher-dashboard-agenda-group is-next">
                        <h3 class="teacher-dashboard-agenda-label">Next class</h3>
                        <?php if ($upcomingSchedules !== []): ?>
                            <div class="teacher-dashboard-schedule-list">
                                <?php foreach (array_slice($upcomingSchedules, 0, 1) as $schedule): ?>
                                    <a class="teacher-dashboard-schedule-item" href="<?= APP_URL ?>/dashboard/teacher_class_list.php?operational_class_id=<?= (int) $schedule['operational_class_id'] ?>">
                                        <span class="teacher-dashboard-schedule-day"><?= teacherDashboardEsc(date('D, M j', strtotime((string) $schedule['scheduled_date']))) ?><small><?= teacherDashboardEsc(date('g:i A', strtotime($schedule['start_time']))) ?>–<?= teacherDashboardEsc(date('g:i A', strtotime($schedule['end_time']))) ?></small></span>
                                        <span class="teacher-dashboard-schedule-details"><strong><?= teacherDashboardEsc($schedule['subject_name']) ?></strong><small><?= teacherDashboardEsc($schedule['section_name']) ?> · <?= teacherDashboardEsc($schedule['room'] ?: 'Room not recorded') ?></small></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="teacher-dashboard-agenda-empty">No upcoming class scheduled.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </section>

            <section class="teacher-panel teacher-dashboard-referral-panel" aria-labelledby="teacher-referral-status-heading">
                <div class="teacher-dashboard-section-header">
                    <div class="teacher-dashboard-panel-title-wrap"><span class="teacher-dashboard-panel-icon" aria-hidden="true"><i data-lucide="clipboard-list"></i></span><h2 class="teacher-panel-title" id="teacher-referral-status-heading">Referral status</h2></div>
                    <a class="teacher-dashboard-text-link" href="<?= APP_URL ?>/dashboard/teacher_referrals.php">Referrals</a>
                </div>
                <div class="teacher-dashboard-referral-statuses" aria-label="Referral counts by status">
                    <?php foreach ([['Submitted', 'is-submitted'], ['Returned', 'is-returned'], ['Accepted', 'is-accepted'], ['Closed', 'is-closed']] as [$status, $statusClass]): ?>
                        <a class="teacher-dashboard-referral-status <?= $statusClass ?>" href="<?= APP_URL ?>/dashboard/teacher_referrals.php?status=<?= rawurlencode($status) ?>"><strong><?= number_format((int) $teacherReferralStatuses[$status]) ?></strong><span><?= teacherDashboardEsc($status) ?></span></a>
                    <?php endforeach; ?>
                </div>
                <?php if ($teacherReferralMetrics['reasons'] === []): ?>
                    <div class="teacher-dashboard-analytics-empty"><i data-lucide="chart-pie" aria-hidden="true"></i><span>No current-class referral reason data is available.</span></div>
                <?php elseif (!$teacherReferralHasUsefulDistribution): ?>
                    <div class="teacher-dashboard-analytics-empty"><span>Referral reasons are not classified for these records.</span></div>
                <?php else: ?>
                    <div class="teacher-dashboard-referral-chart-wrap">
                        <div class="teacher-dashboard-referral-chart" role="img" aria-label="Referral reason counts: <?= teacherDashboardEsc(implode('; ', array_map(static fn(array $reason): string => $reason['label'] . ' ' . $reason['count'], $teacherReferralMeaningfulReasons))) ?>">
                            <canvas id="teacher-referral-reasons-chart" data-labels="<?= teacherDashboardEsc(json_encode($teacherReferralReasonLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>" data-values="<?= teacherDashboardEsc(json_encode($teacherReferralReasonValues)) ?>" data-colors="<?= teacherDashboardEsc(json_encode($teacherReferralReasonColors)) ?>" aria-hidden="true"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

        </div>

    </main>


    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

    <script src="<?= APP_URL ?>/assets/js/pages/teacher-dashboard.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/teacher-dashboard.js') ?>"></script>

</body>

</html>
