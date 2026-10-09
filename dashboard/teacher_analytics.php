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

$pageTitle = 'Analytics';
$activePage = 'analytics';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-analytics.css';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);

$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, ['overview', 'attendance', 'grades'], true)) {
    $view = 'overview';
}

$requestedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$classId = $requestedClassId !== false && $requestedClassId !== null && $requestedClassId > 0
    ? (int) $requestedClassId
    : 0;
$requestedPeriodId = filter_input(INPUT_GET, 'period_id', FILTER_VALIDATE_INT);
$periodId = $requestedPeriodId !== false && $requestedPeriodId !== null && $requestedPeriodId > 0
    ? (int) $requestedPeriodId
    : 0;
$startDate = aprismTeacherAnalyticsDate($_GET['start_date'] ?? null);
$endDate = aprismTeacherAnalyticsDate($_GET['end_date'] ?? null);
if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$search = trim((string) ($_GET['search'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
$completion = (string) ($_GET['completion'] ?? 'all');
if (!in_array($completion, ['all', 'complete', 'incomplete', 'no_data'], true)) {
    $completion = 'all';
}
$selectedStudentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$selectedStudentId = $selectedStudentId !== false && $selectedStudentId !== null && $selectedStudentId > 0
    ? (int) $selectedStudentId
    : 0;
$selectedCategoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$selectedCategoryId = $selectedCategoryId !== false && $selectedCategoryId !== null && $selectedCategoryId > 0
    ? (int) $selectedCategoryId
    : 0;

$classes = aprismGradeTeacherClasses($pdo, $teacherId);
$gradeContext = aprismGradeWorkspaceContext($pdo, $teacherId, $classId, $periodId);
if ($gradeContext['class'] !== null) {
    $classId = (int) $gradeContext['class_id'];
    $periodId = (int) $gradeContext['period_id'];
}

$attendance = $classId > 0
    ? aprismTeacherAnalyticsAttendance($pdo, $teacherId, $classId, $startDate, $endDate)
    : aprismTeacherAnalyticsEmptyAttendance();
$grade = $classId > 0 && $periodId > 0
    ? aprismGradeAnalyticsBuild($pdo, $teacherId, $classId, $periodId)
    : null;

function teacherAnalyticsEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function teacherAnalyticsNumber(mixed $value, int $decimals = 1): string
{
    return $value === null || $value === '' || !is_numeric($value)
        ? '—'
        : number_format((float) $value, $decimals);
}

function teacherAnalyticsRate(mixed $value): string
{
    return $value === null || $value === '' || !is_numeric($value)
        ? '—'
        : teacherAnalyticsNumber($value) . '%';
}

function teacherAnalyticsUrl(array $overrides = []): string
{
    $query = array_merge([
        'view' => $GLOBALS['view'],
        'class_id' => $GLOBALS['classId'] ?: null,
        'period_id' => $GLOBALS['periodId'] ?: null,
        'start_date' => $GLOBALS['startDate'] ?: null,
        'end_date' => $GLOBALS['endDate'] ?: null,
        'search' => $GLOBALS['search'] !== '' ? $GLOBALS['search'] : null,
        'completion' => $GLOBALS['completion'] !== 'all' ? $GLOBALS['completion'] : null,
        'student_id' => $GLOBALS['selectedStudentId'] ?: null,
        'category_id' => $GLOBALS['selectedCategoryId'] ?: null,
    ], $overrides);
    $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_analytics.php?' . http_build_query($query);
}

function teacherAnalyticsGradeStudentUrl(int $studentId): string
{
    $query = array_filter([
        'class_id' => $GLOBALS['classId'] ?: null,
        'period_id' => $GLOBALS['periodId'] ?: null,
        'student_id' => $studentId > 0 ? $studentId : null,
        'search' => $GLOBALS['search'] !== '' ? $GLOBALS['search'] : null,
        'completion' => $GLOBALS['completion'] !== 'all' ? $GLOBALS['completion'] : null,
        'category_id' => $GLOBALS['selectedCategoryId'] ?: null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_grade_student.php?' . http_build_query($query);
}

function teacherAnalyticsAttendanceStudentUrl(int $studentId): string
{
    $query = array_filter([
        'class_id' => $GLOBALS['classId'] ?: null,
        'student_id' => $studentId > 0 ? $studentId : null,
        'start_date' => $GLOBALS['startDate'] ?: null,
        'end_date' => $GLOBALS['endDate'] ?: null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_attendance_student.php?' . http_build_query($query);
}

if ($selectedStudentId > 0 && $view === 'attendance') {
    header('Location: ' . teacherAnalyticsAttendanceStudentUrl($selectedStudentId), true, 303);
    exit;
}
if ($selectedStudentId > 0 && $view === 'grades') {
    header('Location: ' . teacherAnalyticsGradeStudentUrl($selectedStudentId), true, 303);
    exit;
}

function teacherAnalyticsTrendChart(array $sessions): string
{
    $points = [];
    foreach ($sessions as $session) {
        if ($session['attendance_rate'] !== null) {
            $points[] = $session;
        }
    }
    if ($points === []) {
        return '<div class="teacher-analytics-inline-empty">No recorded attendance outcomes are available for this date range.</div>';
    }

    $width = 760;
    $height = 230;
    $left = 38;
    $right = 14;
    $top = 18;
    $bottom = 42;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $count = count($points);
    $svgPoints = [];
    foreach ($points as $index => $point) {
        $x = $count === 1 ? $left + ($plotWidth / 2) : $left + ($plotWidth * $index / ($count - 1));
        $rate = max(0, min(100, (float) $point['attendance_rate']));
        $y = $top + $plotHeight - ($plotHeight * $rate / 100);
        $svgPoints[] = [
            'x' => $x,
            'y' => $y,
            'date' => date('M j, Y', strtotime((string) $point['meeting_date'])),
            'label' => $point['scheduled_day'] . ' · ' . date('g:i A', strtotime((string) $point['scheduled_start_time'])),
            'rate' => $rate,
            'present' => (int) $point['present_count'],
            'late' => (int) $point['late_count'],
            'absent' => (int) $point['absent_count'],
        ];
    }
    $polyline = implode(' ', array_map(static fn(array $point): string => $point['x'] . ',' . $point['y'], $svgPoints));
    $markup = '<div class="teacher-analytics-trend-wrap"><svg class="teacher-analytics-trend" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Attendance rate by recorded class session">';
    foreach ([0, 50, 100] as $tick) {
        $y = $top + $plotHeight - ($plotHeight * $tick / 100);
        $markup .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . ($width - $right) . '" y2="' . $y . '" class="teacher-analytics-chart-gridline"></line><text x="' . ($left - 8) . '" y="' . ($y + 4) . '" class="teacher-analytics-chart-axis" text-anchor="end">' . $tick . '%</text>';
    }
    $markup .= '<polyline points="' . teacherAnalyticsEsc($polyline) . '" class="teacher-analytics-trend-line"></polyline>';
    foreach ($svgPoints as $point) {
        $href = teacherAnalyticsUrl([
            'view' => 'attendance',
            'start_date' => date('Y-m-d', strtotime((string) $point['date'])),
            'end_date' => date('Y-m-d', strtotime((string) $point['date'])),
            'student_id' => null,
        ]) . '#attendance-detail';
        $title = $point['date'] . ' · ' . $point['label'] . ' · Rate ' . teacherAnalyticsNumber($point['rate']) . '% · Present ' . $point['present'] . ', Late ' . $point['late'] . ', Absent ' . $point['absent'];
        $markup .= '<a href="' . teacherAnalyticsEsc($href) . '" aria-label="' . teacherAnalyticsEsc($title) . '"><circle cx="' . $point['x'] . '" cy="' . $point['y'] . '" r="5" class="teacher-analytics-trend-point"><title>' . teacherAnalyticsEsc($title) . '</title></circle></a>';
    }
    foreach ($svgPoints as $index => $point) {
        $markup .= '<text x="' . $point['x'] . '" y="' . ($height - 17) . '" class="teacher-analytics-chart-label" text-anchor="middle">' . teacherAnalyticsEsc($point['date']) . '</text>';
    }
    return $markup . '</svg></div>';
}

$gradeRows = $grade['rows'] ?? [];
$gradeRowsByStudent = [];
foreach ($gradeRows as $gradeRow) {
    $gradeRowsByStudent[(int) $gradeRow['student_id']] = $gradeRow;
}
$reviewRows = [];
foreach ($attendance['students'] as $student) {
    $gradeRow = $gradeRowsByStudent[(int) $student['student_id']] ?? null;
    $missing = $gradeRow !== null ? (int) $gradeRow['missing_entries'] : 0;
    $reasons = [];
    if ((int) $student['absent_count'] > 0) {
        $reasons[] = (int) $student['absent_count'] . ' absent';
    }
    if ((int) $student['late_count'] > 0) {
        $reasons[] = (int) $student['late_count'] . ' late';
    }
    if ($missing > 0) {
        $reasons[] = $missing . ' missing assessment' . ($missing === 1 ? '' : 's');
    }
    if ($reasons !== []) {
        $reviewRows[] = array_merge($student, [
            'grade' => $gradeRow,
            'missing_entries' => $missing,
            'review_reason' => implode(' · ', $reasons),
        ]);
    }
}
usort($reviewRows, static function (array $left, array $right): int {
    return [
        (int) $right['effective_absent_count'],
        (int) $right['absent_count'],
        (int) $right['late_count'],
        (int) $right['missing_entries'],
        (string) $left['student_name'],
    ] <=> [
        (int) $left['effective_absent_count'],
        (int) $left['absent_count'],
        (int) $left['late_count'],
        (int) $left['missing_entries'],
        (string) $right['student_name'],
    ];
});

$gradeFilteredRows = array_values(array_filter($gradeRows, static function (array $row) use ($search, $completion): bool {
    $stateMatches = $completion === 'all'
        || ($completion === 'complete' && $row['completion_state'] === 'Complete')
        || ($completion === 'incomplete' && $row['completion_state'] === 'Incomplete')
        || ($completion === 'no_data' && $row['completion_state'] === 'No data');
    if (!$stateMatches) {
        return false;
    }
    return $search === ''
        || stripos((string) $row['student_number'], $search) !== false
        || stripos((string) $row['student_name'], $search) !== false;
}));

$selectedClass = $gradeContext['class'];
$selectedPeriod = $grade['period'] ?? null;
$gradeSummary = $grade['summary'] ?? null;
$completionCounts = [
    'Complete' => $gradeSummary['complete_students'] ?? 0,
    'Incomplete' => $gradeSummary['incomplete_students'] ?? 0,
    'No data' => $gradeSummary['no_data_students'] ?? 0,
];
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="teacher-page-container teacher-analytics-page">
            <header class="teacher-analytics-header">
                <div>
                    <p class="teacher-analytics-eyebrow">Teacher workspace · Analytics</p>
                    <h1 class="teacher-page-title">Analytics</h1>
                </div>
            </header>
            <nav class="teacher-analytics-tabs" aria-label="Analytics workspace">
                <?php foreach (['overview' => 'Overview', 'attendance' => 'Attendance', 'grades' => 'Grades'] as $tabId => $tabLabel): ?>
                    <a class="<?= $view === $tabId ? 'active' : '' ?>" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['view' => $tabId, 'student_id' => null, 'search' => null, 'completion' => null])) ?>">
                        <i data-lucide="<?= $tabId === 'overview' ? 'layout-dashboard' : ($tabId === 'attendance' ? 'calendar-check-2' : 'chart-no-axes-column') ?>" aria-hidden="true"></i><?= $tabLabel ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <section class="teacher-panel teacher-analytics-context" aria-label="Analytics context">
                <form method="get" class="teacher-analytics-context-form">
                    <input type="hidden" name="view" value="<?= teacherAnalyticsEsc($view) ?>">
                    <label>Operational Class
                        <select name="class_id" onchange="this.form.elements.period_id.value=''; this.form.submit()">
                            <option value="">Select an Operational Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= (int) $class['operational_class_id'] ?>" <?= (int) $class['operational_class_id'] === $classId ? 'selected' : '' ?>><?= teacherAnalyticsEsc($class['subject_name'] . ' · ' . $class['section_name'] . ' · ' . $class['school_year']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if ($view !== 'grades'): ?>
                        <label>From<input type="date" name="start_date" value="<?= teacherAnalyticsEsc($startDate) ?>"></label>
                        <label>To<input type="date" name="end_date" value="<?= teacherAnalyticsEsc($endDate) ?>"></label>
                    <?php else: ?>
                        <input type="hidden" name="start_date" value="<?= teacherAnalyticsEsc($startDate) ?>">
                        <input type="hidden" name="end_date" value="<?= teacherAnalyticsEsc($endDate) ?>">
                    <?php endif; ?>
                    <?php if ($view === 'attendance'): ?>
                        <div class="teacher-analytics-context-static"><span>Grading Period</span><strong>Choose in Grades view</strong><input type="hidden" name="period_id" value="<?= (int) $periodId ?>"></div>
                    <?php else: ?>
                        <label>Grading Period
                            <select name="period_id" <?= $classId === 0 || $gradeContext['periods'] === [] ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <option value=""><?= $classId === 0 ? 'Select a class first' : 'Select a grading period' ?></option>
                                <?php foreach ($gradeContext['periods'] as $period): ?>
                                    <option value="<?= (int) $period['academic_period_id'] ?>" <?= (int) $period['academic_period_id'] === $periodId ? 'selected' : '' ?>><?= teacherAnalyticsEsc($period['academic_level'] . ' · ' . $period['period_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <button class="teacher-analytics-context-submit" type="submit">Apply</button>
                </form>
                <?php if ($selectedClass !== null): ?>
                    <div class="teacher-analytics-context-summary"><strong><?= teacherAnalyticsEsc($selectedClass['subject_name'] . ' · ' . $selectedClass['section_name']) ?></strong><span><?= teacherAnalyticsEsc($selectedClass['school_year'] . ' · ' . $selectedClass['semester']) ?></span></div>
                <?php else: ?>
                    <div class="teacher-analytics-context-summary"><strong>No class selected</strong><span>Choose a Teacher-owned Operational Class to begin.</span></div>
                <?php endif; ?>
            </section>

            <?php if ($classId === 0 || $selectedClass === null): ?>
                <section class="teacher-panel teacher-analytics-empty"><i data-lucide="bar-chart-3" aria-hidden="true"></i><h2>Choose a class to begin</h2><p>Attendance and assessment records for the selected class will appear here.</p></section>
            <?php elseif ($view === 'overview'): ?>
                <section class="teacher-analytics-summary" aria-label="Class overview summary">
                    <article><span class="teacher-analytics-summary-icon navy"><i data-lucide="users"></i></span><div><small>Enrolled Students</small><strong><?= count($attendance['students']) ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon green"><i data-lucide="calendar-check-2"></i></span><div><small>Attendance Rate</small><strong><?= teacherAnalyticsRate($attendance['summary']['attendance_rate']) ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon amber"><i data-lucide="clock-3"></i></span><div><small>Effective Absences</small><strong><?= (int) $attendance['summary']['effective_absent_count'] ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon blue"><i data-lucide="clipboard-check"></i></span><div><small>Grade Coverage</small><strong><?= $gradeSummary !== null ? teacherAnalyticsRate($gradeSummary['coverage_percent']) : '—' ?></strong></div></article>
                </section>
                <section class="teacher-analytics-primary-grid">
                    <article class="teacher-panel teacher-analytics-panel teacher-analytics-trend-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Attendance history</p><h2>Attendance trend</h2></div><span>Recorded sessions</span></div><?= teacherAnalyticsTrendChart($attendance['sessions']) ?><p class="teacher-analytics-note">Rate is Present divided by recorded Present, Late, and Absent outcomes.</p></article>
                    <article class="teacher-panel teacher-analytics-panel teacher-analytics-assessment-status"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Assessment status</p><h2>Grade data completeness</h2></div><a class="teacher-analytics-text-link" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['view' => 'grades'])) ?>">Open Grades <i data-lucide="arrow-up-right"></i></a></div><?php if ($gradeSummary === null): ?><div class="teacher-analytics-inline-empty">Select a grading period to include assessment evidence.</div><?php else: ?><div class="teacher-analytics-completion-list"><?php foreach ($completionCounts as $label => $count): ?><a href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['view' => 'grades', 'completion' => strtolower(str_replace(' ', '_', $label))])) ?>"><span class="teacher-analytics-status-dot <?= strtolower(str_replace(' ', '-', $label)) ?>"></span><?= $label ?><strong><?= (int) $count ?></strong></a><?php endforeach; ?></div><div class="teacher-analytics-coverage-track"><span style="width: <?= teacherAnalyticsEsc((float) $gradeSummary['coverage_percent']) ?>%"></span></div><p class="teacher-analytics-note"><?= (int) $gradeSummary['populated_entries'] ?> of <?= (int) $gradeSummary['expected_entries'] ?> expected raw-score entries recorded.</p><?php endif; ?></article>
                </section>
                <section class="teacher-panel teacher-analytics-review-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Objective evidence</p><h2>Students to review</h2></div><span><?= count($reviewRows) ?> flagged by recorded evidence</span></div><?php if ($reviewRows === []): ?><div class="teacher-analytics-inline-empty">No attendance or incomplete-grade evidence requires review in this context.</div><?php else: ?><div class="teacher-analytics-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>#</th><th>Student</th><th>Attendance</th><th>Grade data</th><th>Evidence</th><th>Action</th></tr></thead><tbody><?php foreach (array_slice($reviewRows, 0, 8) as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><strong><?= teacherAnalyticsEsc($row['student_name']) ?></strong><small><?= teacherAnalyticsEsc($row['student_number']) ?></small></td><td><?= teacherAnalyticsRate($row['attendance_rate']) ?><small><?= (int) $row['late_count'] ?> late · <?= (int) $row['absent_count'] ?> absent</small></td><td><?= $row['grade'] !== null ? (int) $row['grade']['score_count'] . '/' . (int) $row['grade']['component_count'] . ' recorded' : 'Not selected' ?></td><td><span class="teacher-analytics-evidence"><?= teacherAnalyticsEsc($row['review_reason']) ?></span></td><td><a class="teacher-analytics-row-action" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['view' => 'attendance', 'student_id' => (int) $row['student_id']])) ?>">View Student <i data-lucide="arrow-up-right"></i></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
            <?php elseif ($view === 'attendance'): ?>
                <section class="teacher-analytics-summary" aria-label="Attendance summary"><article><span class="teacher-analytics-summary-icon green"><i data-lucide="user-check"></i></span><div><small>Present</small><strong><?= (int) $attendance['summary']['present'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon amber"><i data-lucide="clock-3"></i></span><div><small>Late</small><strong><?= (int) $attendance['summary']['late'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon red"><i data-lucide="user-round-x"></i></span><div><small>Absent</small><strong><?= (int) $attendance['summary']['absent'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon navy"><i data-lucide="percent"></i></span><div><small>Attendance Rate</small><strong><?= teacherAnalyticsRate($attendance['summary']['attendance_rate']) ?></strong></div></article></section>
                <section class="teacher-analytics-primary-grid"><article class="teacher-panel teacher-analytics-panel teacher-analytics-trend-panel" id="attendance-detail"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Chronological sessions</p><h2>Attendance trend</h2></div><span><?= (int) $attendance['summary']['session_count'] ?> session<?= $attendance['summary']['session_count'] === 1 ? '' : 's' ?></span></div><?= teacherAnalyticsTrendChart($attendance['sessions']) ?></article><article class="teacher-panel teacher-analytics-panel teacher-analytics-status-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Selected date range</p><h2>Attendance status</h2></div><span>Raw outcomes</span></div><?php $statusTotal = max(1, (int) $attendance['summary']['recorded']); ?><div class="teacher-analytics-status-bar"><span class="present" style="width: <?= ($attendance['summary']['present'] / $statusTotal) * 100 ?>%"></span><span class="late" style="width: <?= ($attendance['summary']['late'] / $statusTotal) * 100 ?>%"></span><span class="absent" style="width: <?= ($attendance['summary']['absent'] / $statusTotal) * 100 ?>%"></span></div><div class="teacher-analytics-status-list"><div><i class="present"></i>Present<strong><?= (int) $attendance['summary']['present'] ?></strong></div><div><i class="late"></i>Late<strong><?= (int) $attendance['summary']['late'] ?></strong></div><div><i class="absent"></i>Absent<strong><?= (int) $attendance['summary']['absent'] ?></strong></div></div><p class="teacher-analytics-note">Effective absences: <?= (int) $attendance['summary']['effective_absent_count'] ?> using the approved 3 lates = 1 absence rule.</p></article></section>
                <section class="teacher-panel teacher-analytics-review-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Student evidence</p><h2>Attendance by Student</h2></div><span>Raw counts and derived effective absences</span></div><div class="teacher-analytics-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>#</th><th>Student</th><th>Present</th><th>Late</th><th>Absent</th><th>Attendance Rate</th><th>Effective Absences</th><th>Action</th></tr></thead><tbody><?php if ($attendance['students'] === []): ?><tr><td colspan="8" class="teacher-analytics-empty-cell">No enrolled Students are available for this class.</td></tr><?php else: foreach ($attendance['students'] as $index => $student): ?><tr><td><?= $index + 1 ?></td><td><strong><?= teacherAnalyticsEsc($student['student_name']) ?></strong><small><?= teacherAnalyticsEsc($student['student_number']) ?></small></td><td><?= (int) $student['present_count'] ?></td><td><?= (int) $student['late_count'] ?></td><td><?= (int) $student['absent_count'] ?></td><td><?= teacherAnalyticsRate($student['attendance_rate']) ?></td><td><?= (int) $student['effective_absent_count'] ?></td><td><a class="teacher-analytics-row-action" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['student_id' => (int) $student['student_id']])) ?>#attendance-student-detail">View <i data-lucide="arrow-up-right"></i></a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
            <?php else: ?>
                <?php if ($selectedPeriod === null || $gradeSummary === null): ?><section class="teacher-panel teacher-analytics-empty"><i data-lucide="chart-no-axes-column" aria-hidden="true"></i><h2>Select a grading period</h2><p>Choose a valid Academic Period to view assessment coverage and recorded raw-score evidence.</p></section><?php else: ?>
                    <section class="teacher-analytics-summary" aria-label="Grade summary"><article><span class="teacher-analytics-summary-icon navy"><i data-lucide="users"></i></span><div><small>Students</small><strong><?= (int) $gradeSummary['enrolled_students'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon green"><i data-lucide="circle-check"></i></span><div><small>Records Complete</small><strong><?= (int) $gradeSummary['complete_students'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon blue"><i data-lucide="chart-no-axes-column"></i></span><div><small>Data Coverage</small><strong><?= teacherAnalyticsRate($gradeSummary['coverage_percent']) ?></strong></div></article></section>
                    <section class="teacher-analytics-primary-grid"><article class="teacher-panel teacher-analytics-panel teacher-analytics-grade-coverage"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Ordered academic periods</p><h2>Assessment data coverage</h2></div><span>Not a grade trend</span></div><div class="teacher-analytics-grade-periods"><?php foreach ($grade['term_coverage'] as $period): ?><a class="<?= (int) $period['academic_period_id'] === $periodId ? 'selected' : '' ?>" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['period_id' => (int) $period['academic_period_id'], 'student_id' => null])) ?>#grade-coverage"><strong><?= teacherAnalyticsNumber($period['coverage_percent']) ?>%</strong><i><b style="height: <?= teacherAnalyticsEsc((float) $period['coverage_percent']) ?>%"></b></i><span><?= teacherAnalyticsEsc($period['period_name']) ?></span><small><?= (int) $period['populated_entries'] ?>/<?= (int) $period['expected_entries'] ?></small></a><?php endforeach; ?></div></article><article class="teacher-panel teacher-analytics-panel teacher-analytics-category-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Recorded raw scores</p><h2>Assessment category profile</h2></div><span>Raw %</span></div><div class="teacher-analytics-category-bars"><?php foreach ($grade['categories'] as $category): ?><a class="<?= $selectedCategoryId === (int) $category['assessment_category_id'] ? 'selected' : '' ?>" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['category_id' => (int) $category['assessment_category_id'], 'student_id' => null])) ?>#assessment-details"><span><?= teacherAnalyticsEsc($category['category_name']) ?></span><i><b style="width: <?= teacherAnalyticsEsc((float) ($category['average_component_percent'] ?? 0)) ?>%"></b></i><strong><?= teacherAnalyticsNumber($category['average_component_percent']) ?>%</strong></a><?php endforeach; ?></div><p class="teacher-analytics-note">Recorded score relative to available points; not the final period grade.</p></article></section>
                    <section class="teacher-panel teacher-analytics-completion-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Selected period</p><h2>Completion status</h2></div><span><?= teacherAnalyticsEsc($selectedPeriod['period_name']) ?></span></div><div class="teacher-analytics-completion-list teacher-analytics-completion-inline"><?php foreach ($completionCounts as $label => $count): ?><a class="<?= $completion === strtolower(str_replace(' ', '_', $label)) ? 'selected' : '' ?>" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['completion' => strtolower(str_replace(' ', '_', $label)), 'student_id' => null])) ?>#grade-records"><span class="teacher-analytics-status-dot <?= strtolower(str_replace(' ', '-', $label)) ?>"></span><?= $label ?><strong><?= (int) $count ?></strong></a><?php endforeach; ?></div></section>
                    <details class="teacher-panel teacher-analytics-details" id="assessment-details" <?= $selectedCategoryId > 0 ? 'open' : '' ?>><summary>Assessment details <span>Category summary and latest confirmed import</span></summary><div class="teacher-analytics-details-grid"><div class="teacher-analytics-detail-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>Category</th><th>Components</th><th>Students Scored</th><th>Missing</th><th>Avg. Raw %</th></tr></thead><tbody><?php foreach ($grade['categories'] as $category): ?><tr class="<?= $selectedCategoryId === (int) $category['assessment_category_id'] ? 'is-selected' : '' ?>"><td><strong><?= teacherAnalyticsEsc($category['category_name']) ?></strong></td><td><?= (int) $category['component_count'] ?></td><td><?= (int) $category['students_scored'] ?></td><td><?= (int) $category['missing_entries'] ?></td><td><?= teacherAnalyticsNumber($category['average_component_percent']) ?>%</td></tr><?php endforeach; ?></tbody></table></div><aside><p class="teacher-analytics-panel-eyebrow">Latest confirmed import</p><?php if ($grade['batch'] === null): ?><strong>No confirmed import batch</strong><?php else: ?><strong><?= teacherAnalyticsEsc($grade['batch']['original_filename']) ?></strong><span>Confirmed <?= teacherAnalyticsEsc((string) $grade['batch']['confirmed_at']) ?></span><a class="teacher-analytics-text-link" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php?<?= http_build_query(['view' => 'history', 'class_id' => $classId, 'period_id' => $periodId]) ?>">View import history <i data-lucide="arrow-up-right"></i></a><?php endif; ?></aside></div></details>
                    <section class="teacher-panel teacher-analytics-review-panel" id="grade-records"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Selected period roster</p><h2>Student records</h2></div><div class="teacher-analytics-actions"><a class="teacher-analytics-secondary-action" href="<?= APP_URL ?>/dashboard/teacher_reports.php?<?= http_build_query(['report_type' => 'grades', 'class_id' => $classId, 'period_id' => $periodId]) ?>">Grade Records Report</a><a class="teacher-analytics-primary-action" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php?<?= http_build_query(['class_id' => $classId, 'period_id' => $periodId]) ?>">Open Ledger</a></div></div><form class="teacher-analytics-filter" method="get"><input type="hidden" name="view" value="grades"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="period_id" value="<?= $periodId ?>"><label>Search Student<input type="search" name="search" value="<?= teacherAnalyticsEsc($search) ?>" placeholder="Student Number or name"></label><label>Record State<select name="completion"><option value="all" <?= $completion === 'all' ? 'selected' : '' ?>>All records</option><option value="complete" <?= $completion === 'complete' ? 'selected' : '' ?>>Complete</option><option value="incomplete" <?= $completion === 'incomplete' ? 'selected' : '' ?>>Partial</option><option value="no_data" <?= $completion === 'no_data' ? 'selected' : '' ?>>No Data</option></select></label><button type="submit">Apply</button></form><div class="teacher-analytics-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>#</th><th>Student Number</th><th>Student</th><th>Recorded</th><th>Missing</th><th>Raw %</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if ($gradeFilteredRows === []): ?><tr><td colspan="8" class="teacher-analytics-empty-cell">No Student records match this filter.</td></tr><?php else: foreach ($gradeFilteredRows as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><?= teacherAnalyticsEsc($row['student_number']) ?></td><td><strong><?= teacherAnalyticsEsc($row['student_name']) ?></strong></td><td><?= (int) $row['score_count'] ?>/<?= (int) $row['component_count'] ?></td><td><?= (int) $row['missing_entries'] ?></td><td><?= teacherAnalyticsRate($row['recorded_raw_percent']) ?></td><td><span class="teacher-analytics-state <?= strtolower(str_replace(' ', '-', $row['completion_state'])) ?>"><?= teacherAnalyticsEsc($row['completion_state']) ?></span></td><td><a class="teacher-analytics-row-action" href="<?= teacherAnalyticsEsc(teacherAnalyticsGradeStudentUrl((int) $row['student_id'])) ?>">View <i data-lucide="arrow-up-right"></i></a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
