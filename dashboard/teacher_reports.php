<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/grade_ledger_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_referral_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_reports_helper.php';
$pageTitle = 'Reports';
$activePage = 'reports';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-reports.css';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$csrfToken = generateCsrfToken();
$activeYear = $pdo->query("SELECT school_year_id, school_year FROM school_years WHERE status = 'Active' ORDER BY school_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$currentSchoolYear = $activeYear['school_year'] ?? null;
$reportClasses = aprismTeacherReportClasses($pdo, $teacherId);
$reportTypes = ['attendance' => 'Attendance', 'grades' => 'Grade Records', 'referrals' => 'Referral Activity'];
$reportType = (string) ($_GET['report_type'] ?? 'attendance');
if (!isset($reportTypes[$reportType])) {
    $reportType = 'attendance';
}
$requestedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$selectedClassId = $requestedClassId !== false && $requestedClassId !== null && $requestedClassId > 0 ? (int) $requestedClassId : null;
$selectedClass = null;
foreach ($reportClasses as $class) {
    if ($selectedClassId !== null && (int) $class['operational_class_id'] === $selectedClassId) {
        $selectedClass = $class;
        break;
    }
}
if ($selectedClass === null) {
    $selectedClassId = null;
}
$reportClassContext = $selectedClassId !== null ? aprismGradeClassContext($pdo, $teacherId, $selectedClassId) : null;
$reportPeriods = $reportClassContext !== null ? aprismGradePeriodsForClass($pdo, $reportClassContext) : [];
$requestedPeriodId = filter_input(INPUT_GET, 'period_id', FILTER_VALIDATE_INT);
$selectedPeriodId = null;
if ($reportClassContext !== null && $requestedPeriodId !== false && $requestedPeriodId !== null && $requestedPeriodId > 0) {
    foreach ($reportPeriods as $period) {
        if ((int) $period['academic_period_id'] === (int) $requestedPeriodId) {
            $selectedPeriodId = (int) $period['academic_period_id'];
            break;
        }
    }
}
$reportStudents = $selectedClassId !== null ? aprismTeacherReportStudents($pdo, $teacherId, $selectedClassId) : [];
$requestedStudentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$selectedStudentId = null;
if ($requestedStudentId !== false && $requestedStudentId !== null && $requestedStudentId > 0) {
    foreach ($reportStudents as $student) {
        if ((int) $student['student_id'] === (int) $requestedStudentId) {
            $selectedStudentId = (int) $student['student_id'];
            break;
        }
    }
}
$selectedStatus = (string) ($_GET['status'] ?? 'all');
if (!in_array($selectedStatus, ['all', 'Submitted', 'Returned', 'Accepted', 'Closed'], true)) {
    $selectedStatus = 'all';
}
function teacherReportDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
}
$dateFrom = teacherReportDate($_GET['date_from'] ?? null);
$dateTo = teacherReportDate($_GET['date_to'] ?? null);
if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}
$attendanceSummary = $selectedClassId !== null ? aprismTeacherAttendanceReport($pdo, $teacherId, $selectedClassId, $selectedStudentId, $dateFrom, $dateTo) : [];
$attendanceRows = $selectedClassId !== null ? aprismTeacherAttendanceReportRows($pdo, $teacherId, $selectedClassId, $selectedStudentId, $dateFrom, $dateTo) : [];
$gradeRows = $selectedClassId !== null ? aprismTeacherConfirmedGradeReport($pdo, $teacherId, $selectedClassId, $selectedPeriodId, $selectedStudentId) : [];
$referrals = $selectedClassId !== null ? aprismTeacherReferrals($pdo, $teacherId, $selectedClassId) : [];
if ($selectedStudentId !== null) {
    $referrals = array_values(array_filter($referrals, static fn(array $row): bool => (int) ($row['student_id'] ?? 0) === $selectedStudentId));
}
if ($selectedStatus !== 'all') {
    $referrals = array_values(array_filter($referrals, static fn(array $row): bool => (string) ($row['status'] ?? '') === $selectedStatus));
}
$gradePeriods = [];
foreach ($gradeRows as $row) {
    $periodKey = (string) $row['period_name'];
    if (!isset($gradePeriods[$periodKey])) {
        $gradePeriods[$periodKey] = ['academic_level' => $row['academic_level'], 'semester' => $row['semester'], 'components' => [], 'students' => []];
    }
    $componentKey = (string) $row['component_code'];
    $gradePeriods[$periodKey]['components'][$componentKey] = ['name' => $row['component_name'], 'max_score' => $row['max_score']];
    $studentKey = (string) $row['student_number'];
    if (!isset($gradePeriods[$periodKey]['students'][$studentKey])) {
        $gradePeriods[$periodKey]['students'][$studentKey] = ['student_number' => $row['student_number'], 'student_name' => $row['student_name'], 'scores' => []];
    }
    $gradePeriods[$periodKey]['students'][$studentKey]['scores'][$componentKey] = $row['raw_score'];
}
function teacherReportEsc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>

<body><?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper teacher-reports-workspace">
            <header class="teacher-reports-page-header">
                <div>
                    <h1 class="teacher-page-title">Reports</h1>
                </div>
            </header>
            <div class="reports-builder-layout">
                <aside class="teacher-panel reports-builder-panel">
                    <div class="reports-builder-heading">
                        <div>
                            <p class="report-eyebrow">Report builder</p>
                            <h2 class="teacher-panel-title">Choose a report</h2>
                        </div><i data-lucide="sliders-horizontal" aria-hidden="true"></i>
                    </div>
                    <form class="reports-builder-form" method="get"><div class="reports-filter-grid"><label class="reports-field"
                            for="report-type"><span>Report Type</span><select id="report-type" name="report_type">
                                <option value="attendance" <?= $reportType === 'attendance' ? 'selected' : '' ?>>Attendance
                                </option>
                                <option value="grades" <?= $reportType === 'grades' ? 'selected' : '' ?>>Grade Records
                                </option>
                                <option value="referrals" <?= $reportType === 'referrals' ? 'selected' : '' ?>>Referral
                                    Activity</option>
                            </select></label><label class="reports-field reports-field-wide" for="report-class"><span>Operational
                                Class</span><select id="report-class" name="class_id">
                                <option value="">Select a class</option><?php foreach ($reportClasses as $class): ?>
                                    <option value="<?= (int) $class['operational_class_id'] ?>" <?= $selectedClassId !== null && (int) $class['operational_class_id'] === $selectedClassId ? 'selected' : '' ?>>
                                        <?= teacherReportEsc($class['subject_name'] . ' — ' . $class['section_name'] . ' · ' . $class['school_year']) ?>
                                    </option><?php endforeach; ?>
                            </select></label>
                        <?php if ($selectedClassId !== null): ?>
                            <label class="reports-field reports-field-wide" for="report-student"><span>Student</span><select id="report-student" name="student_id">
                                <option value="">All Students</option><?php foreach ($reportStudents as $student): ?><option value="<?= (int) $student['student_id'] ?>" <?= $selectedStudentId !== null && (int) $student['student_id'] === $selectedStudentId ? 'selected' : '' ?>><?= teacherReportEsc($student['student_number'] . ' — ' . $student['student_name']) ?></option><?php endforeach; ?>
                            </select></label>
                        <?php endif; ?>
                        <?php if ($reportType === 'grades'): ?>
                            <label class="reports-field" for="report-period"><span>Academic Period</span><select id="report-period" name="period_id">
                                <option value="">All Periods</option><?php foreach ($reportPeriods as $period): ?><option value="<?= (int) $period['academic_period_id'] ?>" <?= $selectedPeriodId !== null && (int) $period['academic_period_id'] === $selectedPeriodId ? 'selected' : '' ?>><?= teacherReportEsc($period['period_name']) ?></option><?php endforeach; ?>
                            </select></label>
                        <?php else: ?>
                            <input type="hidden" name="period_id" value="<?= (int) ($selectedPeriodId ?? 0) ?>">
                        <?php endif; ?>
                        <?php if ($reportType === 'attendance'): ?>
                            <label class="reports-field" for="report-date-from"><span>From</span><input id="report-date-from" type="date" name="date_from" value="<?= teacherReportEsc($dateFrom ?? '') ?>"></label>
                            <label class="reports-field" for="report-date-to"><span>To</span><input id="report-date-to" type="date" name="date_to" value="<?= teacherReportEsc($dateTo ?? '') ?>"></label>
                        <?php elseif ($reportType === 'referrals'): ?>
                            <label class="reports-field" for="report-status"><span>Status</span><select id="report-status" name="status"><option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>All statuses</option><option value="Submitted" <?= $selectedStatus === 'Submitted' ? 'selected' : '' ?>>Submitted</option><option value="Returned" <?= $selectedStatus === 'Returned' ? 'selected' : '' ?>>Returned</option><option value="Accepted" <?= $selectedStatus === 'Accepted' ? 'selected' : '' ?>>Accepted</option><option value="Closed" <?= $selectedStatus === 'Closed' ? 'selected' : '' ?>>Closed</option></select></label>
                        <?php endif; ?></div>
                        <div class="reports-builder-context"><span>Current academic
                                context</span><strong><?= teacherReportEsc($currentSchoolYear ?: 'Not available') ?></strong><small>Classes
                                remain scoped to your Teacher ownership.</small></div><button type="submit"
                            class="btn btn-primary reports-view-button"><i data-lucide="play" aria-hidden="true"></i>
                            View Report</button>
                    </form>
                </aside>
                <section class="teacher-panel reports-preview-panel reports-preview-column <?= $selectedClass === null ? 'is-awaiting-selection' : ($reportType !== 'grades' ? 'is-short-report' : '') ?>">
                    <div class="reports-preview-toolbar">
                        <div>
                            <p class="report-eyebrow">Report preview</p>
                            <h2 class="teacher-panel-title">
                                <?= $selectedClass ? teacherReportEsc($reportTypes[$reportType]) : 'No report selected' ?>
                            </h2>
                            <?php if ($selectedClass): ?>
                                <p class="teacher-panel-subtitle">
                                    <?= teacherReportEsc($selectedClass['subject_name'] . ' · ' . $selectedClass['section_name'] . ' · ' . $selectedClass['school_year'] . ' · ' . $selectedClass['semester']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="reports-preview-actions"><?php if ($selectedClass): ?>
                                <?php if ($reportType === 'grades'): ?><a class="btn reports-export-button reports-export-print" href="<?= APP_URL ?>/dashboard/teacher_grade_analytics.php?<?= http_build_query(['class_id' => $selectedClassId, 'period_id' => $selectedPeriodId]) ?>"><i data-lucide="chart-no-axes-column" aria-hidden="true"></i> Grade Analytics</a><?php endif; ?>
                                <form method="post" action="<?= APP_URL ?>/actions/teacher/export_report.php"
                                    class="reports-export-form"><input type="hidden" name="csrf_token"
                                        value="<?= teacherReportEsc($csrfToken) ?>"><input type="hidden" name="report_type"
                                        value="<?= teacherReportEsc($reportType) ?>"><input type="hidden"
                                        name="operational_class_id" value="<?= (int) $selectedClassId ?>"><input type="hidden" name="student_id" value="<?= (int) ($selectedStudentId ?? 0) ?>"><input type="hidden" name="period_id" value="<?= (int) ($selectedPeriodId ?? 0) ?>"><input type="hidden" name="date_from" value="<?= teacherReportEsc($dateFrom ?? '') ?>"><input type="hidden" name="date_to" value="<?= teacherReportEsc($dateTo ?? '') ?>"><input type="hidden" name="status" value="<?= teacherReportEsc($selectedStatus) ?>"><button
                                        type="button" class="btn reports-export-button reports-export-print"
                                        data-report-print><i data-lucide="printer" aria-hidden="true"></i> Print /
                                        PDF</button></form>
                                <form method="post" action="<?= APP_URL ?>/actions/teacher/export_report.php"
                                    class="reports-export-form"><input type="hidden" name="csrf_token"
                                        value="<?= teacherReportEsc($csrfToken) ?>"><input type="hidden" name="report_type"
                                        value="<?= teacherReportEsc($reportType) ?>"><input type="hidden"
                                        name="operational_class_id" value="<?= (int) $selectedClassId ?>"><input type="hidden" name="student_id" value="<?= (int) ($selectedStudentId ?? 0) ?>"><input type="hidden" name="period_id" value="<?= (int) ($selectedPeriodId ?? 0) ?>"><input type="hidden" name="date_from" value="<?= teacherReportEsc($dateFrom ?? '') ?>"><input type="hidden" name="date_to" value="<?= teacherReportEsc($dateTo ?? '') ?>"><input type="hidden" name="status" value="<?= teacherReportEsc($selectedStatus) ?>"><input
                                        type="hidden" name="format" value="xlsx"><button type="submit"
                                        class="btn btn-primary reports-export-button"><i data-lucide="file-spreadsheet"
                                            aria-hidden="true"></i> Export Excel</button></form>
                                <form method="post" action="<?= APP_URL ?>/actions/teacher/export_report.php" class="reports-export-form"><input type="hidden" name="csrf_token" value="<?= teacherReportEsc($csrfToken) ?>"><input type="hidden" name="report_type" value="<?= teacherReportEsc($reportType) ?>"><input type="hidden" name="operational_class_id" value="<?= (int) $selectedClassId ?>"><input type="hidden" name="student_id" value="<?= (int) ($selectedStudentId ?? 0) ?>"><input type="hidden" name="period_id" value="<?= (int) ($selectedPeriodId ?? 0) ?>"><input type="hidden" name="date_from" value="<?= teacherReportEsc($dateFrom ?? '') ?>"><input type="hidden" name="date_to" value="<?= teacherReportEsc($dateTo ?? '') ?>"><input type="hidden" name="status" value="<?= teacherReportEsc($selectedStatus) ?>"><input type="hidden" name="format" value="csv"><button type="submit" class="btn reports-export-button"><i data-lucide="file-down" aria-hidden="true"></i> Export CSV</button></form><?php endif; ?><span
                                class="reports-preview-state <?= $selectedClass ? 'is-ready' : '' ?>"><?= $selectedClass ? 'Ready to review' : 'Awaiting selection' ?></span>
                        </div>
                    </div>
                    <div class="reports-preview-body">
                        <?php if ($selectedClass === null): ?>
                            <div class="reports-empty-state"><span class="reports-empty-icon"><i data-lucide="file-search"
                                        aria-hidden="true"></i></span>
                                <h2>Select a class to preview a report</h2>
                                <p>Choose a report type and class, then set the available filters to generate a report.</p>
                            </div>
                        <?php elseif ($reportType === 'attendance'): ?>
                            <div class="report-result-heading">
                                <div>
                                    <p class="report-eyebrow">Attendance report</p>
                                    <h2 class="teacher-panel-title">
                                        <?= teacherReportEsc($selectedClass['subject_name'] . ' — ' . $selectedClass['section_name']) ?>
                                    </h2>
                                    <p class="teacher-panel-subtitle">
                                        <?= teacherReportEsc($selectedClass['school_year'] . ' · ' . $selectedClass['semester']) ?>
                                    </p>
                                </div>
                            </div><?php $a = $attendanceSummary[0] ?? []; ?>
                            <div class="report-result-metrics">
                                <div><span>Sessions</span><strong><?= (int) ($a['session_count'] ?? 0) ?></strong></div>
                                <div><span>Present</span><strong><?= (int) ($a['present_count'] ?? 0) ?></strong></div>
                                <div><span>Late</span><strong><?= (int) ($a['late_count'] ?? 0) ?></strong></div>
                                <div><span>Absent</span><strong><?= (int) ($a['absent_count'] ?? 0) ?></strong></div>
                            </div><?php if (!$attendanceRows): ?>
                                <div class="reports-inline-empty"><i data-lucide="calendar-off" aria-hidden="true"></i>
                                    <p>No Attendance records are available for this class yet.</p>
                                </div><?php else: ?>
                                <div class="teacher-table-wrapper reports-result-table">
                                    <table class="teacher-table reports-mobile-table">
                                        <thead>
                                            <tr>
                                                <th>Student Number</th>
                                                <th>Student</th>
                                                <th>Present</th>
                                                <th>Late</th>
                                                <th>Absent</th>
                                                <th>Recorded</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php foreach ($attendanceRows as $row): ?>
                                                <tr>
                                                    <td data-label="Student Number"><?= teacherReportEsc($row['student_number']) ?></td>
                                                    <td data-label="Student"><strong><?= teacherReportEsc($row['student_name']) ?></strong></td>
                                                    <td data-label="Present"><?= (int) $row['present_count'] ?></td>
                                                    <td data-label="Late"><?= (int) $row['late_count'] ?></td>
                                                    <td data-label="Absent"><?= (int) $row['absent_count'] ?></td>
                                                    <td data-label="Recorded"><?= (int) $row['recorded_count'] ?></td>
                                                </tr><?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div><?php endif; ?>
                        <?php elseif ($reportType === 'grades'): ?>
                            <div class="report-result-heading">
                                <div>
                                    <p class="report-eyebrow">Grade records</p>
                                    <h2 class="teacher-panel-title">Confirmed raw scores</h2>
                                    <p class="teacher-panel-subtitle">Read-only scores from confirmed Grade Import records
                                        for this class.</p>
                                </div>
                            </div><?php if (!$gradePeriods): ?>
                                <div class="reports-inline-empty"><i data-lucide="file-search" aria-hidden="true"></i>
                                    <p>No confirmed raw Grade Import records are available for this class.</p>
                                </div><?php else:
                                foreach ($gradePeriods as $periodName => $period): ?>
                                    <section class="report-grade-period">
                                        <div class="report-grade-period-heading">
                                            <strong><?= teacherReportEsc($periodName) ?></strong><span><?= teacherReportEsc($period['academic_level'] . ' · ' . $period['semester']) ?></span>
                                        </div>
                                        <div class="teacher-table-wrapper reports-result-table">
                                            <table class="teacher-table reports-score-table">
                                                <thead>
                                                    <tr>
                                                        <th>Student Number</th>
                                                        <th>Student</th>
                                                        <?php foreach ($period['components'] as $code => $component): ?>
                                                            <th><?= teacherReportEsc($code) ?><small><?= teacherReportEsc($component['max_score']) ?></small>
                                                            </th><?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody><?php foreach ($period['students'] as $student): ?>
                                                        <tr>
                                                            <td><?= teacherReportEsc($student['student_number']) ?></td>
                                                            <td><strong><?= teacherReportEsc($student['student_name']) ?></strong></td>
                                                            <?php foreach ($period['components'] as $code => $component): ?>
                                                                <td><?= array_key_exists($code, $student['scores']) ? teacherReportEsc($student['scores'][$code]) : '—' ?>
                                                                </td><?php endforeach; ?>
                                                        </tr><?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section><?php endforeach; endif; ?>
                        <?php else: ?>
                            <div class="report-result-heading">
                                <div>
                                    <p class="report-eyebrow">Referral activity</p>
                                    <h2 class="teacher-panel-title">Teacher-submitted referrals</h2>
                                    <p class="teacher-panel-subtitle">Records retained with their class context and current
                                        status.</p>
                                </div>
                            </div><?php if (!$referrals): ?>
                                <div class="reports-inline-empty"><i data-lucide="clipboard-plus" aria-hidden="true"></i>
                                    <p>No referrals have been submitted for this class.</p>
                                </div><?php else: ?>
                                <div class="teacher-table-wrapper reports-result-table">
                                    <table class="teacher-table reports-mobile-table reports-referrals-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Student</th>
                                                <th>Concern</th>
                                                <th>Submitted</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php foreach ($referrals as $index => $referral): ?>
                                                <tr>
                                                    <td data-label="#"><?= $index + 1 ?></td>
                                                    <td data-label="Student"><strong><?= teacherReportEsc($referral['student_name']) ?></strong><small><?= teacherReportEsc($referral['student_number']) ?></small>
                                                    </td>
                                                    <td data-label="Concern"><?= teacherReportEsc(mb_strimwidth($referral['reason'], 0, 120, '…')) ?>
                                                    </td>
                                                    <td data-label="Submitted"><?= teacherReportEsc(date('M j, Y g:i A', strtotime($referral['created_at']))) ?>
                                                    </td>
                                                    <td data-label="Status"><span
                                                            class="status-pill status-<?= teacherReportEsc(strtolower($referral['status'] ?? 'submitted')) ?>"><?= teacherReportEsc($referral['status'] ?? 'Submitted') ?></span>
                                                    </td>
                                                    <td data-label="Actions"><a class="referral-row-action" href="<?= APP_URL ?>/dashboard/teacher_referral_detail.php?referral_id=<?= (int) $referral['teacher_referral_id'] ?>"><i data-lucide="arrow-up-right" aria-hidden="true"></i> View details</a></td>
                                                </tr><?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div><?php endif; ?><?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main><?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/pages/teacher-reports.js"></script>
</body>

</html>
