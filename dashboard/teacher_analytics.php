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
$skipAprismChartRuntime = true;
$teacherId = (int) ($_SESSION['user_id'] ?? 0);

$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, ['overview', 'attendance', 'grades'], true)) {
    $view = 'overview';
}

$pageTitle = $view === 'overview' ? 'Class Analytics' : 'Analytics';
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
    return APP_URL . '/dashboard/teacher_grade_student.php?' . http_build_query($query) . '#student-grade-detail';
}

function teacherAnalyticsAttendanceStudentUrl(int $studentId): string
{
    $query = array_filter([
        'class_id' => $GLOBALS['classId'] ?: null,
        'student_id' => $studentId > 0 ? $studentId : null,
        'start_date' => $GLOBALS['startDate'] ?: null,
        'end_date' => $GLOBALS['endDate'] ?: null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_attendance_student.php?' . http_build_query($query) . '#attendance-student-detail';
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


function teacherAnalyticsOutcomeTrendChart(array $sessions): string
{
    $points = array_values(array_filter($sessions, static fn(array $session): bool => (int) $session['recorded_count'] > 0));
    if ($points === []) {
        $savedCount = count($sessions);
        $message = $savedCount === 0
            ? 'No saved meetings are available in this date range.'
            : 'Saved meetings exist, but none has recorded attendance outcomes yet.';
        return '<div class="teacher-analytics-inline-empty" role="status">' . teacherAnalyticsEsc($message) . '</div>';
    }

    $width = 820;
    $height = 300;
    $left = 48;
    $right = 20;
    $top = 22;
    $bottom = 46;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $maxCount = max(array_map(
        static fn(array $point): int => (int) $point['present_count'] + (int) $point['late_count'] + (int) $point['absent_count'],
        $points
    ));
    $axisMax = max(1, (int) (ceil($maxCount / 2) * 2));
    $tickValues = array_values(array_unique([0, (int) ceil($axisMax / 2), $axisMax]));
    $categories = [
        'present' => ['label' => 'Present', 'class' => 'present'],
        'late' => ['label' => 'Late', 'class' => 'late'],
        'absent' => ['label' => 'Absent', 'class' => 'absent'],
    ];
    $coordinates = [];
    foreach ($points as $index => $point) {
        $x = count($points) === 1 ? $left + ($plotWidth / 2) : $left + ($plotWidth * $index / (count($points) - 1));
        foreach ($categories as $key => $category) {
            $value = (int) $point[$key . '_count'];
            $coordinates[$key][] = [
                'x' => $x,
                'y' => $top + $plotHeight - ($plotHeight * $value / $axisMax),
            ];
        }
    }

    $mobileMinWidth = count($points) > 7 ? max(560, count($points) * 72) : null;
    $hitWidth = min(64, ($plotWidth / count($points)) * 0.75);
    $mobileWidthStyle = $mobileMinWidth === null ? '' : ' style="--analytics-mobile-min-width: ' . $mobileMinWidth . 'px"';
    $chartClass = 'analytics-line-chart' . ($mobileMinWidth !== null ? ' is-scrollable' : '');
    $frameClass = 'analytics-chart-frame teacher-analytics-outcome-chart-frame' . ($mobileMinWidth !== null ? ' is-scrollable' : '');
    $markup = '<div class="' . $frameClass . '">'
        . '<svg class="' . $chartClass . '"' . $mobileWidthStyle . ' viewBox="0 0 ' . $width . ' ' . $height . '" role="group" aria-label="Present, Late, and Absent outcomes across saved class meetings">';
    foreach ($tickValues as $tick) {
        $y = $top + $plotHeight - ($plotHeight * $tick / $axisMax);
        $markup .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . ($width - $right) . '" y2="' . $y . '" class="analytics-chart-gridline"></line>'
            . '<text x="' . ($left - 12) . '" y="' . ($y + 4) . '" class="analytics-chart-axis" text-anchor="end">' . $tick . '</text>';
    }
    $markup .= '<text x="14" y="' . ($top + ($plotHeight / 2)) . '" class="analytics-chart-axis-title" text-anchor="middle" transform="rotate(-90 14 ' . ($top + ($plotHeight / 2)) . ')">Recorded outcomes</text>';
    $markup .= '<line data-chart-crosshair class="analytics-chart-crosshair" x1="' . $left . '" y1="' . $top . '" x2="' . $left . '" y2="' . ($top + $plotHeight) . '"></line>';

    foreach ($categories as $key => $category) {
        $polyline = implode(' ', array_map(
            static fn(array $coordinate): string => $coordinate['x'] . ',' . $coordinate['y'],
            $coordinates[$key]
        ));
        $markup .= '<polyline points="' . teacherAnalyticsEsc($polyline) . '" class="analytics-chart-series ' . $category['class'] . '" aria-hidden="true"></polyline>';
        foreach ($coordinates[$key] as $coordinate) {
            if ($key === 'late') {
                $markup .= '<rect x="' . ($coordinate['x'] - 4) . '" y="' . ($coordinate['y'] - 4) . '" width="8" height="8" class="analytics-chart-marker ' . $category['class'] . '" aria-hidden="true"></rect>';
            } elseif ($key === 'absent') {
                $markup .= '<path d="M ' . $coordinate['x'] . ' ' . ($coordinate['y'] - 5) . ' L ' . ($coordinate['x'] + 5) . ' ' . $coordinate['y'] . ' L ' . $coordinate['x'] . ' ' . ($coordinate['y'] + 5) . ' L ' . ($coordinate['x'] - 5) . ' ' . $coordinate['y'] . ' Z" class="analytics-chart-marker ' . $category['class'] . '" aria-hidden="true"></path>';
            } else {
                $markup .= '<circle cx="' . $coordinate['x'] . '" cy="' . $coordinate['y'] . '" r="4" class="analytics-chart-marker ' . $category['class'] . '" aria-hidden="true"></circle>';
            }
        }
    }

    foreach ($points as $index => $point) {
        $x = $coordinates['present'][$index]['x'];
        $date = date('M j, Y', strtotime((string) $point['meeting_date']));
        $time = date('g:i A', strtotime((string) $point['scheduled_start_time']));
        $label = $date . ' · ' . $point['scheduled_day'] . ' at ' . $time;
        $present = (int) $point['present_count'];
        $late = (int) $point['late_count'];
        $absent = (int) $point['absent_count'];
        $hitX = $x - ($hitWidth / 2);
        $markup .= '<g class="analytics-chart-meeting" data-meeting-point data-meeting-id="' . (int) $point['attendance_session_id'] . '" data-meeting-label="' . teacherAnalyticsEsc($label) . '" data-present="' . $present . '" data-late="' . $late . '" data-absent="' . $absent . '" data-recorded="' . ($present + $late + $absent) . '" tabindex="0" role="button" aria-pressed="false" aria-label="' . teacherAnalyticsEsc($label . ': Present ' . $present . ', Late ' . $late . ', Absent ' . $absent) . '">'
            . '<rect x="' . $hitX . '" y="' . $top . '" width="' . $hitWidth . '" height="' . $plotHeight . '" data-base-x="' . $hitX . '" data-base-width="' . $hitWidth . '" class="analytics-chart-hit-area"></rect>'
            . '<line x1="' . $x . '" y1="' . $top . '" x2="' . $x . '" y2="' . ($top + $plotHeight) . '" class="analytics-chart-meeting-guide"></line>';
        foreach ($categories as $key => $category) {
            $coordinate = $coordinates[$key][$index];
            if ($key === 'late') {
                $markup .= '<rect x="' . ($coordinate['x'] - 5) . '" y="' . ($coordinate['y'] - 5) . '" width="10" height="10" class="analytics-chart-meeting-marker ' . $category['class'] . '"></rect>';
            } elseif ($key === 'absent') {
                $markup .= '<path d="M ' . $coordinate['x'] . ' ' . ($coordinate['y'] - 6) . ' L ' . ($coordinate['x'] + 6) . ' ' . $coordinate['y'] . ' L ' . $coordinate['x'] . ' ' . ($coordinate['y'] + 6) . ' L ' . ($coordinate['x'] - 6) . ' ' . $coordinate['y'] . ' Z" class="analytics-chart-meeting-marker ' . $category['class'] . '"></path>';
            } else {
                $markup .= '<circle cx="' . $coordinate['x'] . '" cy="' . $coordinate['y'] . '" r="5" class="analytics-chart-meeting-marker ' . $category['class'] . '"></circle>';
            }
        }
        $markup .= '</g>';
    }

    $labelStep = max(1, (int) ceil(count($points) / 5));
    foreach ($points as $index => $point) {
        if ($index % $labelStep !== 0 && $index !== count($points) - 1) {
            continue;
        }
        $date = date('M j', strtotime((string) $point['meeting_date']));
        $markup .= '<text x="' . $coordinates['present'][$index]['x'] . '" y="' . ($height - 17) . '" class="analytics-chart-label" text-anchor="middle">' . teacherAnalyticsEsc($date) . '</text>';
    }
    $markup .= '</svg><div class="analytics-chart-tooltip" data-chart-tooltip role="tooltip" hidden></div>';
    if ($mobileMinWidth !== null) {
        $markup .= '<p class="analytics-chart-scroll-hint">Swipe horizontally to see every plotted meeting.</p>';
    }
    $markup .= '</div>';
    return $markup;
}

function teacherAnalyticsAttendanceComposition(array $summary): string
{
    $counts = [
        'present' => (int) $summary['present'],
        'late' => (int) $summary['late'],
        'absent' => (int) $summary['absent'],
    ];
    $total = array_sum($counts);
    if ($total === 0) {
        return '<div class="teacher-analytics-inline-empty" role="status">No recorded attendance outcomes are available for this date range.</div>';
    }

    $radius = 60;
    $circumference = 2 * M_PI * $radius;
    $activeSegmentCount = count(array_filter($counts, static fn(int $count): bool => $count > 0));
    $segmentGap = $activeSegmentCount > 1 ? 2.5 : 0.0;
    $offset = 0.0;
    $markup = '<div class="analytics-composition" role="img" aria-label="Attendance composition: Present ' . $counts['present'] . ', Late ' . $counts['late'] . ', Absent ' . $counts['absent'] . '; ' . $total . ' total recorded outcomes.">'
        . '<div class="analytics-donut-wrap"><svg class="analytics-donut" viewBox="0 0 160 160" aria-hidden="true"><circle class="analytics-donut-track" cx="80" cy="80" r="' . $radius . '"></circle>';
    foreach ($counts as $key => $count) {
        if ($count === 0) {
            continue;
        }
        $segment = $circumference * $count / $total;
        $visibleSegment = max(0, $segment - $segmentGap);
        $segmentOffset = -($offset + ($segmentGap / 2));
        $markup .= '<circle class="analytics-donut-segment ' . $key . '" cx="80" cy="80" r="' . $radius . '" stroke-dasharray="' . $visibleSegment . ' ' . ($circumference - $visibleSegment) . '" stroke-dashoffset="' . $segmentOffset . '"></circle>';
        $offset += $segment;
    }
    $markup .= '</svg><div class="analytics-donut-center"><strong>' . $total . '</strong><span>recorded</span></div></div><ul class="analytics-composition-legend">';
    foreach (['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent'] as $key => $label) {
        $markup .= '<li><span class="analytics-legend-key ' . $key . '" aria-hidden="true"></span><span>' . $label . '</span><strong>' . $counts[$key] . '</strong></li>';
    }
    return $markup . '</ul></div>';
}

function teacherAnalyticsMeetingDateUrl(array $session): string
{
    $meetingDate = (string) $session['meeting_date'];
    return teacherAnalyticsUrl([
        'view' => 'attendance',
        'start_date' => $meetingDate,
        'end_date' => $meetingDate,
        'period_id' => null,
        'student_id' => null,
    ]) . '#attendance-detail';
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
$additionalStylesheets = $view === 'overview' && $classId > 0 && $selectedClass !== null
    ? ['assets/css/components/analytics-charts.css']
    : [];
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
                    <p class="teacher-analytics-eyebrow">Teacher workspace · <?= $view === 'overview' ? 'Class Analytics' : 'Analytics' ?></p>
                    <h1 class="teacher-page-title"><?= $view === 'overview' ? 'Class Analytics' : 'Analytics' ?></h1>
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
                    <label class="teacher-analytics-context-class">Operational Class
                        <select name="class_id" onchange="this.form.elements.period_id.value=''; this.form.submit()">
                            <option value="">Select an Operational Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= (int) $class['operational_class_id'] ?>" <?= (int) $class['operational_class_id'] === $classId ? 'selected' : '' ?>><?= teacherAnalyticsEsc($class['subject_name'] . ' · ' . $class['section_name'] . ' · ' . $class['school_year']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if ($view !== 'grades'): ?>
                        <label class="teacher-analytics-context-date">From<input type="date" name="start_date" value="<?= teacherAnalyticsEsc($startDate) ?>"></label>
                        <label class="teacher-analytics-context-date">To<input type="date" name="end_date" value="<?= teacherAnalyticsEsc($endDate) ?>"></label>
                    <?php else: ?>
                        <input type="hidden" name="start_date" value="<?= teacherAnalyticsEsc($startDate) ?>">
                        <input type="hidden" name="end_date" value="<?= teacherAnalyticsEsc($endDate) ?>">
                    <?php endif; ?>
                    <?php if ($view === 'attendance'): ?>
                        <div class="teacher-analytics-context-static teacher-analytics-context-period"><span>Grading Period</span><strong>Choose in Grades view</strong><input type="hidden" name="period_id" value="<?= (int) $periodId ?>"></div>
                    <?php else: ?>
                        <label class="teacher-analytics-context-period">Grading Period
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
                    <div class="teacher-analytics-context-summary"><strong><?= teacherAnalyticsEsc($selectedClass['subject_name'] . ' · ' . $selectedClass['section_name']) ?></strong><span><?= teacherAnalyticsEsc($selectedClass['school_year'] . ' · ' . $selectedClass['semester']) ?> · Operational Class #<?= (int) $selectedClass['operational_class_id'] ?></span></div>
                <?php else: ?>
                    <div class="teacher-analytics-context-summary"><strong>No class selected</strong><span>Choose a Teacher-owned Operational Class to begin.</span></div>
                <?php endif; ?>
            </section>

            <?php if ($classId === 0 || $selectedClass === null): ?>
                <section class="teacher-panel teacher-analytics-empty"><i data-lucide="bar-chart-3" aria-hidden="true"></i><h2>Choose a class to begin</h2><p>Attendance and assessment records for the selected class will appear here.</p></section>
            <?php elseif ($view === 'overview'): ?>
                <section class="teacher-analytics-summary teacher-analytics-overview-summary" aria-label="Class overview summary">
                    <article><span class="teacher-analytics-summary-icon navy"><i data-lucide="users" aria-hidden="true"></i></span><div><small>Class participants</small><strong><?= count($attendance['students']) ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon green"><i data-lucide="calendar-check-2" aria-hidden="true"></i></span><div><small>Attendance Rate</small><strong><?= teacherAnalyticsRate($attendance['summary']['attendance_rate']) ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon amber"><i data-lucide="clock-3" aria-hidden="true"></i></span><div><small>Effective Absences</small><strong><?= (int) $attendance['summary']['recorded'] > 0 ? (int) $attendance['summary']['effective_absent_count'] : '—' ?></strong></div></article>
                    <article><span class="teacher-analytics-summary-icon blue"><i data-lucide="clipboard-check" aria-hidden="true"></i></span><div><small>Grade Coverage</small><strong><?= $gradeSummary !== null ? teacherAnalyticsRate($gradeSummary['coverage_percent']) : '—' ?></strong></div></article>
                </section>
                <section class="teacher-analytics-primary-grid teacher-analytics-overview-charts" aria-label="Class attendance analytics">
                    <article class="teacher-panel teacher-analytics-panel teacher-analytics-trend-panel teacher-analytics-outcome-panel">
                        <div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Saved attendance records</p><h2>Attendance across meetings</h2></div><span><?= (int) $attendance['summary']['session_count'] ?> saved meeting<?= (int) $attendance['summary']['session_count'] === 1 ? '' : 's' ?></span></div>
                        <ul class="analytics-chart-legend" aria-label="Attendance categories">
                            <li><span class="analytics-legend-key present" aria-hidden="true"></span>Present</li>
                            <li><span class="analytics-legend-key late" aria-hidden="true"></span>Late</li>
                            <li><span class="analytics-legend-key absent" aria-hidden="true"></span>Absent</li>
                        </ul>
                        <?= teacherAnalyticsOutcomeTrendChart($attendance['sessions']) ?>
                        <div class="analytics-chart-selection" id="meeting-selection-summary" data-chart-selection-summary role="status" aria-live="polite">Select a meeting point or a meeting record to see its exact attendance counts.</div>
                        <button class="analytics-chart-reset" type="button" data-chart-reset disabled>Reset selection</button>
                        <p class="teacher-analytics-note">Each line shows the stored count for that category. Meetings without records remain listed below and are not plotted as zero.</p>
                    </article>
                    <article class="teacher-panel teacher-analytics-panel teacher-analytics-composition-panel">
                        <div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">All saved meetings in range</p><h2>Attendance composition</h2></div><span><?= (int) $attendance['summary']['recorded'] ?> recorded outcomes</span></div>
                        <?= teacherAnalyticsAttendanceComposition($attendance['summary']) ?>
                        <p class="teacher-analytics-note">Total is the number of stored Present, Late, and Absent records in this class and date range.</p>
                    </article>
                </section>
                <section class="teacher-panel teacher-analytics-review-panel teacher-analytics-meetings-panel" id="meeting-records">
                    <div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Supporting attendance records</p><h2>Saved meetings</h2></div><span>Chart selection highlights its record</span></div>
                    <div class="teacher-analytics-table-wrap teacher-analytics-meetings-wrap">
                        <table class="teacher-analytics-table teacher-analytics-meetings-table" data-meeting-table aria-describedby="meeting-selection-summary">
                            <thead><tr><th scope="col">Meeting</th><th scope="col">Conducted by</th><th scope="col">Present</th><th scope="col">Late</th><th scope="col">Absent</th><th scope="col">Recorded</th><th scope="col">Session</th><th scope="col">Actions</th></tr></thead>
                            <tbody>
                            <?php if ($attendance['sessions'] === []): ?>
                                <tr><td colspan="8" class="teacher-analytics-empty-cell">No saved meetings are available in this date range.</td></tr>
                            <?php else: foreach ($attendance['sessions'] as $session): $recordedCount = (int) $session['recorded_count']; ?>
                                <tr class="teacher-analytics-meeting-row" data-meeting-row data-meeting-id="<?= (int) $session['attendance_session_id'] ?>">
                                    <td data-label="Meeting"><strong><?= teacherAnalyticsEsc(date('D, M j, Y', strtotime((string) $session['meeting_date']))) ?></strong><small><?= teacherAnalyticsEsc($session['scheduled_day']) ?> · <?= teacherAnalyticsEsc(date('g:i A', strtotime((string) $session['scheduled_start_time']))) ?>–<?= teacherAnalyticsEsc(date('g:i A', strtotime((string) $session['scheduled_end_time']))) ?></small></td>
                                    <td data-label="Conducted by"><?= teacherAnalyticsEsc(trim((string) ($session['conducted_by_teacher_name'] ?? '')) ?: 'Not recorded') ?></td>
                                    <td data-label="Present"><?= $recordedCount > 0 ? (int) $session['present_count'] : '—' ?></td>
                                    <td data-label="Late"><?= $recordedCount > 0 ? (int) $session['late_count'] : '—' ?></td>
                                    <td data-label="Absent"><?= $recordedCount > 0 ? (int) $session['absent_count'] : '—' ?></td>
                                    <td data-label="Recorded"><?= $recordedCount ?></td>
                                    <td data-label="Session"><span class="teacher-analytics-state"><?= teacherAnalyticsEsc($session['status']) ?></span></td>
                                    <td data-label="Actions"><div class="teacher-analytics-meeting-actions"><button type="button" class="analytics-meeting-select" data-meeting-select data-meeting-id="<?= (int) $session['attendance_session_id'] ?>" data-meeting-label="<?= teacherAnalyticsEsc(date('M j, Y', strtotime((string) $session['meeting_date'])) . ' · ' . $session['scheduled_day'] . ' at ' . date('g:i A', strtotime((string) $session['scheduled_start_time']))) ?>" data-present="<?= (int) $session['present_count'] ?>" data-late="<?= (int) $session['late_count'] ?>" data-absent="<?= (int) $session['absent_count'] ?>" data-recorded="<?= $recordedCount ?>" aria-pressed="false">Select</button><a class="teacher-analytics-row-action" href="<?= teacherAnalyticsEsc(teacherAnalyticsMeetingDateUrl($session)) ?>">View date in Attendance <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></div></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="teacher-panel teacher-analytics-assessment-status teacher-analytics-assessment-status-compact"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Assessment status</p><h2>Grade data completeness</h2></div><a class="teacher-analytics-text-link" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['view' => 'grades'])) ?>">Open Grades <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></div><?php if ($gradeSummary === null): ?><div class="teacher-analytics-inline-empty">Select a grading period to include assessment evidence.</div><?php else: ?><div class="teacher-analytics-completion-list"><?php foreach ($completionCounts as $label => $count): ?><a href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['view' => 'grades', 'completion' => strtolower(str_replace(' ', '_', $label))])) ?>"><span class="teacher-analytics-status-dot <?= strtolower(str_replace(' ', '-', $label)) ?>"></span><?= $label ?><strong><?= (int) $count ?></strong></a><?php endforeach; ?></div><div class="teacher-analytics-coverage-track"><span style="width: <?= teacherAnalyticsEsc((float) $gradeSummary['coverage_percent']) ?>%"></span></div><p class="teacher-analytics-note"><?= (int) $gradeSummary['populated_entries'] ?> of <?= (int) $gradeSummary['expected_entries'] ?> expected raw-score entries recorded.</p><?php endif; ?></section>
                <section class="teacher-panel teacher-analytics-review-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Objective evidence</p><h2>Students to review</h2></div><span><?= count($reviewRows) ?> flagged by recorded evidence</span></div><?php if ($reviewRows === []): ?><div class="teacher-analytics-inline-empty">No attendance or incomplete-grade evidence requires review in this context.</div><?php else: ?><div class="teacher-analytics-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>#</th><th>Student</th><th>Attendance</th><th>Grade data</th><th>Evidence</th><th>Action</th></tr></thead><tbody><?php foreach (array_slice($reviewRows, 0, 8) as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><strong><?= teacherAnalyticsEsc($row['student_name']) ?></strong><small><?= teacherAnalyticsEsc($row['student_number']) ?></small></td><td><?= teacherAnalyticsRate($row['attendance_rate']) ?><small><?= (int) $row['late_count'] ?> late · <?= (int) $row['absent_count'] ?> absent</small></td><td><?= $row['grade'] !== null ? (int) $row['grade']['score_count'] . '/' . (int) $row['grade']['component_count'] . ' recorded' : 'Not selected' ?></td><td><span class="teacher-analytics-evidence"><?= teacherAnalyticsEsc($row['review_reason']) ?></span></td><td><a class="teacher-analytics-row-action" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['view' => 'attendance', 'student_id' => (int) $row['student_id']])) ?>">View Student <i data-lucide="arrow-up-right"></i></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
            <?php elseif ($view === 'attendance'): ?>
                <section class="teacher-analytics-summary" aria-label="Attendance summary"><article><span class="teacher-analytics-summary-icon green"><i data-lucide="user-check"></i></span><div><small>Present</small><strong><?= (int) $attendance['summary']['present'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon amber"><i data-lucide="clock-3"></i></span><div><small>Late</small><strong><?= (int) $attendance['summary']['late'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon red"><i data-lucide="user-round-x"></i></span><div><small>Absent</small><strong><?= (int) $attendance['summary']['absent'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon navy"><i data-lucide="percent"></i></span><div><small>Attendance Rate</small><strong><?= teacherAnalyticsRate($attendance['summary']['attendance_rate']) ?></strong></div></article></section>
                <section class="teacher-analytics-primary-grid"><article class="teacher-panel teacher-analytics-panel teacher-analytics-trend-panel" id="attendance-detail" data-aprism-destination-focus><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Chronological sessions</p><h2>Attendance trend</h2></div><span><?= (int) $attendance['summary']['session_count'] ?> session<?= $attendance['summary']['session_count'] === 1 ? '' : 's' ?></span></div><?= teacherAnalyticsTrendChart($attendance['sessions']) ?></article><article class="teacher-panel teacher-analytics-panel teacher-analytics-status-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Selected date range</p><h2>Attendance status</h2></div><span>Raw outcomes</span></div><?php $statusTotal = max(1, (int) $attendance['summary']['recorded']); ?><div class="teacher-analytics-status-bar"><span class="present" style="width: <?= ($attendance['summary']['present'] / $statusTotal) * 100 ?>%"></span><span class="late" style="width: <?= ($attendance['summary']['late'] / $statusTotal) * 100 ?>%"></span><span class="absent" style="width: <?= ($attendance['summary']['absent'] / $statusTotal) * 100 ?>%"></span></div><div class="teacher-analytics-status-list"><div><i class="present"></i>Present<strong><?= (int) $attendance['summary']['present'] ?></strong></div><div><i class="late"></i>Late<strong><?= (int) $attendance['summary']['late'] ?></strong></div><div><i class="absent"></i>Absent<strong><?= (int) $attendance['summary']['absent'] ?></strong></div></div><p class="teacher-analytics-note">Effective absences: <?= (int) $attendance['summary']['effective_absent_count'] ?> using the approved 3 lates = 1 absence rule.</p></article></section>
                <section class="teacher-panel teacher-analytics-review-panel" id="attendance-student-detail" data-aprism-destination-focus><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Student evidence</p><h2>Attendance by Student</h2></div><span>Raw counts and derived effective absences</span></div><div class="teacher-analytics-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>#</th><th>Student</th><th>Present</th><th>Late</th><th>Absent</th><th>Attendance Rate</th><th>Effective Absences</th><th>Action</th></tr></thead><tbody><?php if ($attendance['students'] === []): ?><tr><td colspan="8" class="teacher-analytics-empty-cell">No enrolled Students are available for this class.</td></tr><?php else: foreach ($attendance['students'] as $index => $student): ?><tr><td><?= $index + 1 ?></td><td><strong><?= teacherAnalyticsEsc($student['student_name']) ?></strong><small><?= teacherAnalyticsEsc($student['student_number']) ?></small></td><td><?= (int) $student['present_count'] ?></td><td><?= (int) $student['late_count'] ?></td><td><?= (int) $student['absent_count'] ?></td><td><?= teacherAnalyticsRate($student['attendance_rate']) ?></td><td><?= (int) $student['effective_absent_count'] ?></td><td><a class="teacher-analytics-row-action" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['student_id' => (int) $student['student_id']])) ?>#attendance-student-detail">View <i data-lucide="arrow-up-right"></i></a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
            <?php else: ?>
                <?php if ($selectedPeriod === null || $gradeSummary === null): ?><section class="teacher-panel teacher-analytics-empty"><i data-lucide="chart-no-axes-column" aria-hidden="true"></i><h2>Select a grading period</h2><p>Choose a valid Academic Period to view assessment coverage and recorded raw-score evidence.</p></section><?php else: ?>
                    <section class="teacher-analytics-summary" aria-label="Grade summary"><article><span class="teacher-analytics-summary-icon navy"><i data-lucide="users"></i></span><div><small>Students</small><strong><?= (int) $gradeSummary['enrolled_students'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon green"><i data-lucide="circle-check"></i></span><div><small>Records Complete</small><strong><?= (int) $gradeSummary['complete_students'] ?></strong></div></article><article><span class="teacher-analytics-summary-icon blue"><i data-lucide="chart-no-axes-column"></i></span><div><small>Data Coverage</small><strong><?= teacherAnalyticsRate($gradeSummary['coverage_percent']) ?></strong></div></article></section>
                    <section class="teacher-analytics-primary-grid"><article class="teacher-panel teacher-analytics-panel teacher-analytics-grade-coverage" id="grade-coverage" data-aprism-destination-focus><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Ordered academic periods</p><h2>Assessment data coverage</h2></div><span>Not a grade trend</span></div><div class="teacher-analytics-grade-periods"><?php foreach ($grade['term_coverage'] as $period): ?><a class="<?= (int) $period['academic_period_id'] === $periodId ? 'selected' : '' ?>" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['period_id' => (int) $period['academic_period_id'], 'student_id' => null])) ?>#grade-coverage"><strong><?= teacherAnalyticsNumber($period['coverage_percent']) ?>%</strong><i><b style="height: <?= teacherAnalyticsEsc((float) $period['coverage_percent']) ?>%"></b></i><span><?= teacherAnalyticsEsc($period['period_name']) ?></span><small><?= (int) $period['populated_entries'] ?>/<?= (int) $period['expected_entries'] ?></small></a><?php endforeach; ?></div></article><article class="teacher-panel teacher-analytics-panel teacher-analytics-category-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Recorded raw scores</p><h2>Assessment category profile</h2></div><span>Raw %</span></div><div class="teacher-analytics-category-bars"><?php foreach ($grade['categories'] as $category): ?><a class="<?= $selectedCategoryId === (int) $category['assessment_category_id'] ? 'selected' : '' ?>" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['category_id' => (int) $category['assessment_category_id'], 'student_id' => null])) ?>#assessment-details"><span><?= teacherAnalyticsEsc($category['category_name']) ?></span><i><b style="width: <?= teacherAnalyticsEsc((float) ($category['average_component_percent'] ?? 0)) ?>%"></b></i><strong><?= teacherAnalyticsNumber($category['average_component_percent']) ?>%</strong></a><?php endforeach; ?></div><p class="teacher-analytics-note">Recorded score relative to available points; not the final period grade.</p></article></section>
                    <section class="teacher-panel teacher-analytics-completion-panel"><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Selected period</p><h2>Completion status</h2></div><span><?= teacherAnalyticsEsc($selectedPeriod['period_name']) ?></span></div><div class="teacher-analytics-completion-list teacher-analytics-completion-inline"><?php foreach ($completionCounts as $label => $count): ?><a class="<?= $completion === strtolower(str_replace(' ', '_', $label)) ? 'selected' : '' ?>" href="<?= teacherAnalyticsEsc(teacherAnalyticsUrl(['completion' => strtolower(str_replace(' ', '_', $label)), 'student_id' => null])) ?>#grade-records"><span class="teacher-analytics-status-dot <?= strtolower(str_replace(' ', '-', $label)) ?>"></span><?= $label ?><strong><?= (int) $count ?></strong></a><?php endforeach; ?></div></section>
                    <details class="teacher-panel teacher-analytics-details" id="assessment-details" data-aprism-destination-focus <?= $selectedCategoryId > 0 ? 'open' : '' ?>><summary>Assessment details <span>Category summary and latest confirmed import</span></summary><div class="teacher-analytics-details-grid"><div class="teacher-analytics-detail-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>Category</th><th>Components</th><th>Students Scored</th><th>Missing</th><th>Avg. Raw %</th></tr></thead><tbody><?php foreach ($grade['categories'] as $category): ?><tr class="<?= $selectedCategoryId === (int) $category['assessment_category_id'] ? 'is-selected' : '' ?>"><td><strong><?= teacherAnalyticsEsc($category['category_name']) ?></strong></td><td><?= (int) $category['component_count'] ?></td><td><?= (int) $category['students_scored'] ?></td><td><?= (int) $category['missing_entries'] ?></td><td><?= teacherAnalyticsNumber($category['average_component_percent']) ?>%</td></tr><?php endforeach; ?></tbody></table></div><aside><p class="teacher-analytics-panel-eyebrow">Latest confirmed import</p><?php if ($grade['batch'] === null): ?><strong>No confirmed import batch</strong><?php else: ?><strong><?= teacherAnalyticsEsc($grade['batch']['original_filename']) ?></strong><span>Confirmed <?= teacherAnalyticsEsc((string) $grade['batch']['confirmed_at']) ?></span><a class="teacher-analytics-text-link" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php?<?= http_build_query(['view' => 'history', 'class_id' => $classId, 'period_id' => $periodId]) ?>">View import history <i data-lucide="arrow-up-right"></i></a><?php endif; ?></aside></div></details>
                    <section class="teacher-panel teacher-analytics-review-panel" id="grade-records" data-aprism-destination-focus><div class="teacher-analytics-panel-heading"><div><p class="teacher-analytics-panel-eyebrow">Selected period roster</p><h2>Student records</h2></div><div class="teacher-analytics-actions"><a class="teacher-analytics-secondary-action" href="<?= APP_URL ?>/dashboard/teacher_reports.php?<?= http_build_query(['report_type' => 'grades', 'class_id' => $classId, 'period_id' => $periodId]) ?>">Grade Records Report</a><a class="teacher-analytics-primary-action" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php?<?= http_build_query(['class_id' => $classId, 'period_id' => $periodId]) ?>">Open Ledger</a></div></div><form class="teacher-analytics-filter" method="get"><input type="hidden" name="view" value="grades"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="period_id" value="<?= $periodId ?>"><label>Search Student<input type="search" name="search" value="<?= teacherAnalyticsEsc($search) ?>" placeholder="Student Number or name"></label><label>Record State<select name="completion"><option value="all" <?= $completion === 'all' ? 'selected' : '' ?>>All records</option><option value="complete" <?= $completion === 'complete' ? 'selected' : '' ?>>Complete</option><option value="incomplete" <?= $completion === 'incomplete' ? 'selected' : '' ?>>Partial</option><option value="no_data" <?= $completion === 'no_data' ? 'selected' : '' ?>>No Data</option></select></label><button type="submit">Apply</button></form><div class="teacher-analytics-table-wrap"><table class="teacher-analytics-table"><thead><tr><th>#</th><th>Student Number</th><th>Student</th><th>Recorded</th><th>Missing</th><th>Raw %</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if ($gradeFilteredRows === []): ?><tr><td colspan="8" class="teacher-analytics-empty-cell">No Student records match this filter.</td></tr><?php else: foreach ($gradeFilteredRows as $index => $row): ?><tr><td><?= $index + 1 ?></td><td><?= teacherAnalyticsEsc($row['student_number']) ?></td><td><strong><?= teacherAnalyticsEsc($row['student_name']) ?></strong></td><td><?= (int) $row['score_count'] ?>/<?= (int) $row['component_count'] ?></td><td><?= (int) $row['missing_entries'] ?></td><td><?= teacherAnalyticsRate($row['recorded_raw_percent']) ?></td><td><span class="teacher-analytics-state <?= strtolower(str_replace(' ', '-', $row['completion_state'])) ?>"><?= teacherAnalyticsEsc($row['completion_state']) ?></span></td><td><a class="teacher-analytics-row-action" href="<?= teacherAnalyticsEsc(teacherAnalyticsGradeStudentUrl((int) $row['student_id'])) ?>">View <i data-lucide="arrow-up-right"></i></a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <?php if ($view === 'overview' && $classId > 0 && $selectedClass !== null): ?>
        <script src="<?= APP_URL ?>/assets/js/components/analytics-chart-interactions.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/components/analytics-chart-interactions.js') ?>"></script>
    <?php endif; ?>
</body>
</html>
