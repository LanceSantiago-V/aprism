<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_reports_helper.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet =
    'assets/css/pages/academic-head-dashboard.css?v=' .
    (string) filemtime(__DIR__ . '/../assets/css/pages/academic-head-dashboard.css');
$additionalStylesheets = ['assets/css/pages/dashboard-shared.css'];

function academicHeadDashboardEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** @param array<int, array{value: int, color: string}> $segments */
function academicHeadDashboardDonutGradient(array $segments): string
{
    $total = array_sum(array_column($segments, 'value'));

    if ($total <= 0) {
        return 'conic-gradient(#e2e8f0 0 100%)';
    }

    $stops = [];
    $cursor = 0.0;

    foreach ($segments as $segment) {
        $value = max(0, (int) $segment['value']);

        if ($value === 0) {
            continue;
        }

        $next = $cursor + (($value / $total) * 100);
        $stops[] = $segment['color'] . ' ' . round($cursor, 2) . '% ' . round($next, 2) . '%';
        $cursor = $next;
    }

    return 'conic-gradient(' . implode(', ', $stops) . ')';
}

$activeSchoolYear = null;
$dashboard = [
    'metrics' => [],
    'charts' => ['levels' => [], 'programs' => [], 'sections' => []],
    'sections' => [],
];
$dashboardError = null;
$selectedSchoolYearLabel = 'All School Years';
$hasExplicitAllSchoolYears = isset($_GET['school_year_id'])
    && is_scalar($_GET['school_year_id'])
    && (string) $_GET['school_year_id'] === '0';
$dashboardFilters = [
    'school_year_id' => filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT) ?: 0,
    'semester' => trim((string) ($_GET['semester'] ?? 'All')),
];

try {
    $options = aprismAcademicHeadReportOptions($pdo);
    $activeSchoolYear = aprismActiveSchoolYear($pdo);
    $yearIds = array_map('intval', array_column($options['school_years'], 'school_year_id'));
    if ((int) $dashboardFilters['school_year_id'] === 0 && !$hasExplicitAllSchoolYears && $activeSchoolYear !== null) {
        $dashboardFilters['school_year_id'] = (int) $activeSchoolYear['school_year_id'];
    }
    if ((int) $dashboardFilters['school_year_id'] > 0 && !in_array((int) $dashboardFilters['school_year_id'], $yearIds, true)) {
        $dashboardFilters['school_year_id'] = $activeSchoolYear ? (int) $activeSchoolYear['school_year_id'] : 0;
    }
    foreach ($options['school_years'] as $schoolYearOption) {
        if ((int) $schoolYearOption['school_year_id'] === (int) $dashboardFilters['school_year_id']) {
            $selectedSchoolYearLabel = (string) $schoolYearOption['school_year'];
            break;
        }
    }
    if ($dashboardFilters['semester'] !== 'All' && !in_array($dashboardFilters['semester'], $options['contexts'], true)) {
        $dashboardFilters['semester'] = 'All';
    }
    $dashboard = aprismAcademicHeadDashboardData($pdo, $dashboardFilters);
} catch (Throwable $exception) {
    error_log('[APRISM Academic Head Dashboard] ' . $exception::class . ': ' . $exception->getMessage());
    $dashboardError = 'Unable to load dashboard data right now.';
}

$dashboardMetricCards = [
    ['key' => 'active_students', 'label' => 'Current Students', 'icon' => 'users', 'class' => 'is-current', 'scope' => 'Selected scope'],
    ['key' => 'active_sections', 'label' => 'Active Sections', 'icon' => 'layers-3', 'class' => 'is-sections', 'scope' => 'Institution-wide'],
    ['key' => 'active_classes', 'label' => 'Active Classes', 'icon' => 'book-open', 'class' => 'is-classes', 'scope' => 'Selected scope'],
    ['key' => 'active_schedules', 'label' => 'Active Schedules', 'icon' => 'calendar-days', 'class' => 'is-schedules', 'scope' => 'Selected scope'],
    ['key' => 'unresolved_students', 'label' => 'Placement Issues', 'icon' => 'circle-alert', 'class' => 'is-unresolved', 'scope' => 'Selected scope'],
];

$placedStudents = (int) ($dashboard['metrics']['placed_students'] ?? 0);
$unresolvedStudents = (int) ($dashboard['metrics']['unresolved_students'] ?? 0);
$currentStudents = (int) ($dashboard['metrics']['active_students'] ?? 0);
$placementTotal = $placedStudents + $unresolvedStudents;
$placementBreakdownMatchesCurrent = $placementTotal === $currentStudents;
$placedPercentage = $placementBreakdownMatchesCurrent && $placementTotal > 0
    ? (int) round(($placedStudents / $placementTotal) * 100)
    : null;
$unresolvedPercentage = $placedPercentage !== null ? 100 - $placedPercentage : null;

$placementStatusGradient = academicHeadDashboardDonutGradient([
    ['value' => $placedStudents, 'color' => '#217a5a'],
    ['value' => $unresolvedStudents, 'color' => '#d18a18'],
]);

$placementReviewScopeValid = (int) $dashboardFilters['school_year_id'] > 0
    && in_array($dashboardFilters['semester'], ['First Semester', 'Second Semester'], true);
$placementReviewQuery = [
    'school_year_id' => (int) $dashboardFilters['school_year_id'],
    'semester' => $dashboardFilters['semester'],
];
$placementReviewUrl = APP_URL . '/dashboard/academic_head_student_placements.php?'
    . http_build_query($placementReviewQuery);
$placementIssuePreview = [];
$placementIssueTotal = 0;
$placementIssuePreviewAvailable = $placementReviewScopeValid && $dashboardError === null;
if ($placementIssuePreviewAvailable) {
    try {
        $placementIssueFilters = [
            'search' => '',
            'school_year_id' => (int) $dashboardFilters['school_year_id'],
            'semester' => $dashboardFilters['semester'],
            'status' => 'All',
        ];
        $placementIssueTotal = aprismRegistryUnresolvedStudentsCount($pdo, $placementIssueFilters);
        $placementIssuePreview = aprismRegistryUnresolvedStudentsPage($pdo, $placementIssueFilters, 3, 0);
    } catch (Throwable $exception) {
        error_log('[APRISM Academic Head Dashboard Placement Preview] ' . $exception::class . ': ' . $exception->getMessage());
        $placementIssuePreviewAvailable = false;
    }
}

$placementIssueStudentUrl = static function (array $student) use ($dashboardFilters): string {
    return APP_URL . '/dashboard/academic_head_student_placements.php?'
        . http_build_query([
            'search' => (string) ($student['student_number'] ?? ''),
            'school_year_id' => (int) $dashboardFilters['school_year_id'],
            'semester' => $dashboardFilters['semester'],
        ]);
};

$attendanceClassOptions = [];
$attendanceSessionRows = [];
$attendanceSummaryAvailable = $dashboardError === null;
$selectedAttendanceClassId = filter_input(INPUT_GET, 'attendance_class_id', FILTER_VALIDATE_INT) ?: 0;
$attendanceVisibleTotals = ['present' => 0, 'late' => 0, 'absent' => 0];
if ($attendanceSummaryAvailable) {
    try {
        $attendanceFilters = [
            'search' => '',
            'school_year_id' => (int) $dashboardFilters['school_year_id'],
            'semester' => $dashboardFilters['semester'],
            'teacher_id' => 0,
            'section_id' => 0,
            'operational_class_id' => 0,
            'status' => 'All',
        ];
        $attendanceClassOptions = aprismAcademicHeadAttendanceClassOptions($pdo, $attendanceFilters);
        $availableAttendanceClassIds = array_map(
            'intval',
            array_column($attendanceClassOptions, 'operational_class_id')
        );
        if ($selectedAttendanceClassId > 0 && !in_array($selectedAttendanceClassId, $availableAttendanceClassIds, true)) {
            $selectedAttendanceClassId = 0;
        }
        $attendanceFilters['operational_class_id'] = $selectedAttendanceClassId;
        $attendanceSessionRows = aprismAcademicHeadAttendanceSessionRows($pdo, $attendanceFilters, 8);
        foreach ($attendanceSessionRows as $attendanceSession) {
            $present = (int) ($attendanceSession['present_count'] ?? 0);
            $late = (int) ($attendanceSession['late_count'] ?? 0);
            $absent = (int) ($attendanceSession['absent_count'] ?? 0);
            $attendanceVisibleTotals['present'] += $present;
            $attendanceVisibleTotals['late'] += $late;
            $attendanceVisibleTotals['absent'] += $absent;
        }
    } catch (Throwable $exception) {
        error_log('[APRISM Academic Head Dashboard Attendance] ' . $exception::class . ': ' . $exception->getMessage());
        $attendanceSummaryAvailable = false;
    }
}

$attendanceChartPayload = [
    'labels' => [],
    'present' => [],
    'late' => [],
    'absent' => [],
];
$attendanceChartAccessibleSummary = [];
foreach ($attendanceSessionRows as $attendanceSession) {
    $meetingDate = (string) ($attendanceSession['meeting_date'] ?? '');
    $meetingDateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $meetingDate);
    $meetingDateLabel = $meetingDateValue ? $meetingDateValue->format('M j, Y') : $meetingDate;
    $present = (int) ($attendanceSession['present_count'] ?? 0);
    $late = (int) ($attendanceSession['late_count'] ?? 0);
    $absent = (int) ($attendanceSession['absent_count'] ?? 0);
    $attendanceChartPayload['labels'][] = $meetingDateLabel;
    $attendanceChartPayload['present'][] = $present;
    $attendanceChartPayload['late'][] = $late;
    $attendanceChartPayload['absent'][] = $absent;
    $attendanceChartAccessibleSummary[] = $meetingDateLabel . ': ' . $present . ' Present, ' . $late . ' Late, ' . $absent . ' Absent';
}
$attendanceChartAccessibleLabel = 'Recorded Student attendance counts by session date. '
    . implode('. ', $attendanceChartAccessibleSummary);

$attendanceReportUrl = APP_URL . '/dashboard/academic_head_reports.php?'
    . http_build_query([
        'report' => 'attendance',
        'school_year_id' => (int) $dashboardFilters['school_year_id'],
        'semester' => $dashboardFilters['semester'],
    ]);

$academicQuickActions = [
    ['label' => 'Sections', 'description' => 'Manage institutional sections', 'icon' => 'layers-3', 'url' => APP_URL . '/dashboard/academic_head_sections.php'],
    ['label' => 'Schedules', 'description' => 'Review class schedules', 'icon' => 'calendar-days', 'url' => APP_URL . '/dashboard/academic_head_schedules.php'],
    ['label' => 'Students', 'description' => 'Browse student records', 'icon' => 'users', 'url' => APP_URL . '/dashboard/academic_head_students.php'],
    ['label' => 'Adviser Assignments', 'description' => 'Manage Adviser assignments', 'icon' => 'user-round-cog', 'url' => APP_URL . '/dashboard/academic_head_adviser_assignments.php'],
    ['label' => 'Reports', 'description' => 'Build academic reports', 'icon' => 'chart-no-axes-column', 'url' => APP_URL . '/dashboard/academic_head_reports.php'],
];

?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content aprism-dashboard <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper academic-head-page academic-dashboard-page">
            <section class="page-header dashboard-page-header">
                <div class="page-header-left">
                    <h1 class="page-title">Academic Head Dashboard</h1>
                </div>
                <form class="dashboard-context-form" method="get" aria-label="Dashboard context">
                    <label>
                        <span><i data-lucide="calendar-days" aria-hidden="true"></i>School Year</span>
                        <select name="school_year_id" onchange="this.form.submit()"><option value="0">All School Years</option><?php foreach (($options['school_years'] ?? []) as $year): ?><option value="<?= (int) $year['school_year_id'] ?>" <?= (int) $dashboardFilters['school_year_id'] === (int) $year['school_year_id'] ? 'selected' : '' ?>><?= academicHeadDashboardEsc($year['school_year']) ?></option><?php endforeach; ?></select>
                    </label>
                    <label>
                        <span><i data-lucide="layers-3" aria-hidden="true"></i>Teaching Context</span>
                        <select name="semester" onchange="this.form.submit()"><option value="All">All Contexts</option><?php foreach (($options['contexts'] ?? []) as $context): ?><option value="<?= academicHeadDashboardEsc($context) ?>" <?= $dashboardFilters['semester'] === $context ? 'selected' : '' ?>><?= academicHeadDashboardEsc($context) ?></option><?php endforeach; ?></select>
                    </label>
                </form>
            </section>

            <?php if ($dashboardError !== null): ?><div class="registry-feedback is-error" role="alert"><?= academicHeadDashboardEsc($dashboardError) ?></div><?php endif; ?>

            <section class="dashboard-kpi-section" aria-label="Academic Head dashboard summary">
                <div class="dashboard-kpi-grid">
                    <?php foreach ($dashboardMetricCards as $card): ?>
                        <?php $canOpenPlacementReview = $card['key'] === 'unresolved_students' && $placementReviewScopeValid; ?>
                        <?php if ($canOpenPlacementReview): ?><a class="dashboard-kpi-card <?= academicHeadDashboardEsc($card['class']) ?>" href="<?= academicHeadDashboardEsc($placementReviewUrl) ?>" aria-label="Review placement issues in the selected academic context"><?php else: ?><article class="dashboard-kpi-card <?= academicHeadDashboardEsc($card['class']) ?>"><?php endif; ?>
                            <span class="dashboard-kpi-icon"><i data-lucide="<?= academicHeadDashboardEsc($card['icon']) ?>" aria-hidden="true"></i></span>
                            <span class="dashboard-kpi-label-row"><span class="dashboard-kpi-label"><?= academicHeadDashboardEsc($card['label']) ?></span></span>
                            <strong><?= $dashboardError === null ? number_format((int) ($dashboard['metrics'][$card['key']] ?? 0)) : '—' ?></strong>
                            <span class="dashboard-kpi-scope"><?= academicHeadDashboardEsc($card['scope']) ?></span>
                            <?php if ($canOpenPlacementReview): ?></a><?php else: ?></article><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="dashboard-monitoring-grid" aria-label="Academic monitoring overview">
                <article class="dashboard-chart-panel dashboard-placement-overview-panel">
                    <header class="dashboard-panel-header">
                        <div class="dashboard-panel-title-wrap">
                            <span class="dashboard-panel-icon is-placement"><i data-lucide="users" aria-hidden="true"></i></span>
                            <div><span class="dashboard-eyebrow">Selected academic scope</span><h2>Student Placement Overview</h2></div>
                        </div>
                    </header>
                    <?php if ($dashboardError !== null): ?>
                        <div class="dashboard-monitor-empty" role="status">Student placement overview is unavailable right now.</div>
                    <?php else: ?>
                    <div class="placement-overview-content">
                        <div class="dashboard-donut <?= $placementTotal > 0 ? '' : 'is-empty' ?>"<?= $placementTotal > 0 ? ' style="background: ' . academicHeadDashboardEsc($placementStatusGradient) . '"' : '' ?> role="img" aria-label="Placement overview: <?= number_format($placedStudents) ?> placed, <?= number_format($unresolvedStudents) ?> unresolved, <?= number_format($currentStudents) ?> current Students" title="<?= number_format($placedStudents) ?> placed · <?= number_format($unresolvedStudents) ?> unresolved">
                            <div class="dashboard-donut-center"><strong><?= number_format($currentStudents) ?></strong><span>Current Students</span></div>
                        </div>
                        <div class="placement-overview-legend" aria-label="Placement breakdown">
                            <div class="placement-legend-item is-placed"><span class="placement-legend-dot" aria-hidden="true"></span><span>Placed Students</span><strong><?= number_format($placedStudents) ?></strong><?php if ($placedPercentage !== null): ?><span class="placement-share"><?= $placedPercentage ?>%</span><?php endif; ?></div>
                            <div class="placement-legend-item is-unresolved"><span class="placement-legend-dot" aria-hidden="true"></span><span>Unresolved Placement</span><strong><?= number_format($unresolvedStudents) ?></strong><?php if ($unresolvedPercentage !== null): ?><span class="placement-share"><?= $unresolvedPercentage ?>%</span><?php endif; ?></div>
                            <p class="placement-overview-note">Placement counts use the selected School Year and teaching context.</p>
                        </div>
                    </div>
                    <div class="placement-overview-action">
                        <?php if ($placementReviewScopeValid): ?>
                            <a class="aprism-action-btn aprism-action-btn-primary is-standard" href="<?= academicHeadDashboardEsc($placementReviewUrl) ?>"><span>Review Placement Issues</span><i data-lucide="arrow-right" aria-hidden="true"></i></a>
                        <?php else: ?>
                            <button class="aprism-action-btn aprism-action-btn-primary is-standard" type="button" disabled aria-describedby="placement-scope-help"><span>Review Placement Issues</span><i data-lucide="arrow-right" aria-hidden="true"></i></button>
                            <p id="placement-scope-help"><i data-lucide="info" aria-hidden="true"></i>Select a School Year and a specific teaching context to open the scoped review.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </article>

                <article class="dashboard-chart-panel dashboard-attendance-panel">
                    <header class="dashboard-panel-header">
                        <div class="dashboard-panel-title-wrap">
                            <span class="dashboard-panel-icon is-attendance"><i data-lucide="calendar-check-2" aria-hidden="true"></i></span>
                            <div><span class="dashboard-eyebrow">Persisted Student outcomes</span><h2>Recorded Attendance</h2></div>
                        </div>
                        <div class="attendance-panel-tools">
                            <form class="attendance-class-filter" method="get" aria-label="Filter recorded attendance by class">
                                <input type="hidden" name="school_year_id" value="<?= (int) $dashboardFilters['school_year_id'] ?>">
                                <input type="hidden" name="semester" value="<?= academicHeadDashboardEsc($dashboardFilters['semester']) ?>">
                                <label class="visually-hidden" for="attendance-class-filter">Operational Class</label>
                                <select id="attendance-class-filter" name="attendance_class_id" onchange="this.form.submit()">
                                    <option value="0">All recorded classes</option>
                                    <?php foreach ($attendanceClassOptions as $attendanceClass): ?>
                                        <?php
                                            $attendanceSubjectCode = trim((string) ($attendanceClass['subject_code'] ?? ''));
                                            $attendanceSubjectName = trim((string) ($attendanceClass['subject_name'] ?? ''));
                                            $attendanceClassLabel = trim($attendanceSubjectCode . ($attendanceSubjectCode !== '' && $attendanceSubjectName !== '' ? ' · ' : '') . $attendanceSubjectName);
                                            $attendanceClassLabel .= ' — ' . (string) ($attendanceClass['section_name'] ?? '');
                                        ?>
                                        <option value="<?= (int) $attendanceClass['operational_class_id'] ?>" <?= $selectedAttendanceClassId === (int) $attendanceClass['operational_class_id'] ? 'selected' : '' ?>><?= academicHeadDashboardEsc($attendanceClassLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <a class="dashboard-panel-action" href="<?= academicHeadDashboardEsc($attendanceReportUrl) ?>">Attendance Report<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                        </div>
                    </header>
                    <?php if (!$attendanceSummaryAvailable): ?>
                        <div class="dashboard-monitor-empty" role="status">Recorded attendance is unavailable right now.</div>
                    <?php elseif ($attendanceSessionRows === []): ?>
                        <div class="dashboard-monitor-empty"><i data-lucide="calendar-x-2" aria-hidden="true"></i><span>No saved Student attendance records in this academic scope.</span></div>
                    <?php else: ?>
                        <div class="attendance-count-grid" aria-label="Saved Student attendance outcomes in the sessions shown">
                            <div class="attendance-count is-present"><strong><?= number_format($attendanceVisibleTotals['present']) ?></strong><span>Present</span></div>
                            <div class="attendance-count is-late"><strong><?= number_format($attendanceVisibleTotals['late']) ?></strong><span>Late</span></div>
                            <div class="attendance-count is-absent"><strong><?= number_format($attendanceVisibleTotals['absent']) ?></strong><span>Absent</span></div>
                        </div>
                        <div class="attendance-session-legend" aria-label="Attendance chart series">
                            <span class="is-present"><i aria-hidden="true"></i>Present</span>
                            <span class="is-late"><i aria-hidden="true"></i>Late</span>
                            <span class="is-absent"><i aria-hidden="true"></i>Absent</span>
                        </div>
                        <?php
                            $attendanceChartDataAttribute = json_encode(
                                $attendanceChartPayload,
                                JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG
                            );
                        ?>
                        <div class="attendance-chart-frame">
                            <canvas
                                id="academic-head-attendance-chart"
                                class="attendance-chart-canvas"
                                data-attendance-chart="<?= academicHeadDashboardEsc($attendanceChartDataAttribute ?: '{}') ?>"
                                role="img"
                                tabindex="0"
                                aria-label="<?= academicHeadDashboardEsc($attendanceChartAccessibleLabel) ?>. Use the left and right arrow keys to inspect each recorded session."></canvas>
                        </div>
                        <p class="attendance-sessions-shown">Showing <?= count($attendanceSessionRows) ?> recorded session<?= count($attendanceSessionRows) === 1 ? '' : 's' ?>.</p>
                    <?php endif; ?>
                    <p class="attendance-data-note">Only sessions with saved Student attendance records are shown. The latest 8 are included when available; totals summarize this preview, and sessions without records are omitted.</p>
                </article>

                <article class="dashboard-chart-panel dashboard-placement-issues-panel">
                    <header class="dashboard-panel-header">
                        <div class="dashboard-panel-title-wrap">
                            <span class="dashboard-panel-icon is-issues"><i data-lucide="triangle-alert" aria-hidden="true"></i></span>
                            <div><span class="dashboard-eyebrow">Scoped student records</span><h2>Placement Issues to Review</h2></div>
                        </div>
                        <?php if ($placementReviewScopeValid && $placementIssuePreviewAvailable): ?><span class="dashboard-record-count"><?= number_format($placementIssueTotal) ?> record<?= $placementIssueTotal === 1 ? '' : 's' ?></span><?php endif; ?>
                    </header>
                    <?php if ($dashboardError !== null || ($placementReviewScopeValid && !$placementIssuePreviewAvailable)): ?>
                        <div class="dashboard-monitor-empty" role="status">Placement issue records are unavailable right now.</div>
                    <?php elseif (!$placementReviewScopeValid): ?>
                        <div class="dashboard-monitor-empty"><i data-lucide="list-filter" aria-hidden="true"></i><span>Choose a School Year and a specific teaching context to preview placement issue records.</span></div>
                    <?php elseif ($placementIssuePreview === []): ?>
                        <div class="dashboard-monitor-empty"><i data-lucide="circle-check" aria-hidden="true"></i><span>No unresolved placement records in this context.</span></div>
                    <?php else: ?>
                        <div class="placement-issue-list">
                            <?php foreach ($placementIssuePreview as $issueStudent): ?>
                                <?php $issueLabel = (int) ($issueStudent['current_placement_count'] ?? 0) > 1 ? 'Ambiguous' : 'Missing Evidence'; ?>
                                <article class="placement-issue-item">
                                    <div class="placement-issue-identity">
                                        <strong><?= academicHeadDashboardEsc($issueStudent['student_name']) ?></strong>
                                        <?php if (trim((string) ($issueStudent['student_number'] ?? '')) !== ''): ?><span><?= academicHeadDashboardEsc($issueStudent['student_number']) ?></span><?php endif; ?>
                                        <?php if (trim((string) ($issueStudent['participation_evidence'] ?? '')) !== ''): ?><small><?= academicHeadDashboardEsc($issueStudent['participation_evidence']) ?></small><?php endif; ?>
                                    </div>
                                    <span class="placement-issue-badge <?= $issueLabel === 'Ambiguous' ? 'is-ambiguous' : 'is-missing' ?>"><?= academicHeadDashboardEsc($issueLabel) ?></span>
                                    <a class="placement-issue-action" href="<?= academicHeadDashboardEsc($placementIssueStudentUrl($issueStudent)) ?>" aria-label="Review placement for <?= academicHeadDashboardEsc($issueStudent['student_name']) ?>">Review<i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <footer class="dashboard-workspace-footer">
                        <?php if ($placementReviewScopeValid): ?><a href="<?= academicHeadDashboardEsc($placementReviewUrl) ?>">Open placement review<i data-lucide="arrow-right" aria-hidden="true"></i></a><?php endif; ?>
                    </footer>
                </article>

                <article class="dashboard-chart-panel dashboard-actions-panel">
                    <header class="dashboard-panel-header">
                        <div class="dashboard-panel-title-wrap">
                            <span class="dashboard-panel-icon is-actions"><i data-lucide="zap" aria-hidden="true"></i></span>
                            <div><span class="dashboard-eyebrow">Academic workspaces</span><h2>Academic Actions</h2></div>
                        </div>
                    </header>
                    <nav class="dashboard-action-grid" aria-label="Academic Head pages">
                        <?php foreach ($academicQuickActions as $action): ?>
                            <a class="dashboard-action-card dashboard-preview-card" href="<?= academicHeadDashboardEsc($action['url']) ?>">
                                <span class="dashboard-action-icon"><i data-lucide="<?= academicHeadDashboardEsc($action['icon']) ?>" aria-hidden="true"></i></span>
                                <span class="dashboard-action-copy"><strong><?= academicHeadDashboardEsc($action['label']) ?></strong><small><?= academicHeadDashboardEsc($action['description']) ?></small></span>
                                <i class="dashboard-action-arrow" data-lucide="chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </article>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/pages/academic-head-dashboard.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/academic-head-dashboard.js') ?>"></script>
</body>
</html>
