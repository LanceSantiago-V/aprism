<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/teacher_analytics_helper.php';

$pageTitle = 'Cases';
$activePage = 'guidance_cases';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/guidance.css';
$userId = (int) ($_SESSION['user_id'] ?? 0);

$statusOptions = ['All', 'Open', 'Active', 'Closed'];
$status = trim((string) ($_GET['status'] ?? 'All'));
if (!in_array($status, $statusOptions, true)) {
    $status = 'All';
}

$search = trim((string) ($_GET['search'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
$requestedContext = (string) ($_GET['context'] ?? '');
$requestedCaseId = filter_var($_GET['case_id'] ?? null, FILTER_VALIDATE_INT);
$requestedCaseId = $requestedCaseId !== false && $requestedCaseId !== null && $requestedCaseId > 0
    ? (int) $requestedCaseId
    : 0;

function guidanceCasesEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guidanceCasesDateTime(mixed $value, string $format): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? '—' : date($format, $timestamp);
}

function guidanceCasesSelectionUrl(int $caseId, string $anchor = ''): string
{
    $query = [
        'status' => $GLOBALS['status'] !== 'All' ? $GLOBALS['status'] : null,
        'context' => $GLOBALS['contextKey'] !== '' ? $GLOBALS['contextKey'] : null,
        'search' => $GLOBALS['search'] !== '' ? $GLOBALS['search'] : null,
        'case_id' => $caseId > 0 ? $caseId : null,
    ];
    $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/guidance_cases.php'
        . ($query !== [] ? '?' . http_build_query($query) : '')
        . $anchor;
}

$scopeSql = " FROM guidance_cases gc
    INNER JOIN guidance_case_assignments gca
        ON gca.guidance_case_id = gc.guidance_case_id
       AND gca.assigned_user_id = ?
       AND gca.ended_at IS NULL ";

$summaryStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT gc.guidance_case_id) AS total_cases,
            COUNT(DISTINCT CASE WHEN gc.case_status IN ('Open', 'Active')
                                THEN gc.guidance_case_id END) AS ongoing_cases,
            COUNT(DISTINCT CASE WHEN gc.case_status = 'Closed'
                                THEN gc.guidance_case_id END) AS closed_cases,
            COUNT(DISTINCT CASE WHEN EXISTS (
                SELECT 1
                FROM guidance_followups f
                WHERE f.guidance_case_id = gc.guidance_case_id
                  AND f.assigned_user_id = ?
                  AND f.followup_status = 'Scheduled'
            ) THEN gc.guidance_case_id END) AS followup_cases"
    . $scopeSql
);
$summaryStmt->execute([$userId, $userId]);
$caseSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_cases' => 0,
    'ongoing_cases' => 0,
    'closed_cases' => 0,
    'followup_cases' => 0,
];

/* School Year and Semester are preserved on the source referral. Cases do not
   have an Academic Period relationship, so the filter uses only that recorded
   referral context and never presents it as a grading period. */
$contextStmt = $pdo->prepare(
    "SELECT DISTINCT TRIM(COALESCE(tr.school_year_snapshot, '')) AS school_year_snapshot,
                    TRIM(COALESCE(tr.semester_snapshot, '')) AS semester_snapshot"
    . $scopeSql . "
    INNER JOIN teacher_referrals tr ON tr.teacher_referral_id = gc.teacher_referral_id
    WHERE (TRIM(COALESCE(tr.school_year_snapshot, '')) <> ''
        OR TRIM(COALESCE(tr.semester_snapshot, '')) <> '')
    ORDER BY school_year_snapshot DESC, semester_snapshot"
);
$contextStmt->execute([$userId]);
$contextOptions = [];
foreach ($contextStmt->fetchAll(PDO::FETCH_ASSOC) as $contextRow) {
    $schoolYear = trim((string) $contextRow['school_year_snapshot']);
    $semester = trim((string) $contextRow['semester_snapshot']);
    $contextOptionKey = json_encode([$schoolYear, $semester], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($contextOptionKey)) {
        continue;
    }
    $contextLabel = implode(' · ', array_values(array_filter(
        [$schoolYear, $semester],
        static fn(string $part): bool => $part !== ''
    )));
    $contextOptions[$contextOptionKey] = $contextLabel;
}

$contextParts = json_decode($requestedContext, true);
$contextKey = isset($contextOptions[$requestedContext])
    && is_array($contextParts)
    && count($contextParts) === 2
    && is_string($contextParts[0])
    && is_string($contextParts[1])
        ? $requestedContext
        : '';

$caseWhere = ['1 = 1'];
$caseParams = [$userId];
if ($status !== 'All') {
    $caseWhere[] = 'gc.case_status = ?';
    $caseParams[] = $status;
}
if ($contextKey !== '') {
    $caseWhere[] = "TRIM(COALESCE(tr.school_year_snapshot, '')) = ?";
    $caseWhere[] = "TRIM(COALESCE(tr.semester_snapshot, '')) = ?";
    $caseParams[] = $contextParts[0];
    $caseParams[] = $contextParts[1];
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $caseWhere[] = "(gc.case_number LIKE ?
        OR s.student_number LIKE ?
        OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) LIKE ?
        OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?)";
    array_push($caseParams, $like, $like, $like, $like);
}

$casesStmt = $pdo->prepare(
    "SELECT DISTINCT gc.guidance_case_id,
            gc.case_number,
            gc.case_status,
            gc.opened_at,
            COALESCE(case_updates.last_updated, gc.opened_at) AS last_updated,
            CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) AS student_name,
            s.student_id,
            s.student_number,
            tr.teacher_id AS origin_teacher_id,
            tr.operational_class_id,
            tr.student_class_enrollment_id,
            tr.school_year_snapshot,
            tr.semester_snapshot,
            CONCAT_WS(' ', u.first_name, u.last_name) AS assignee_name"
    . $scopeSql . "
    INNER JOIN teacher_referrals tr ON tr.teacher_referral_id = gc.teacher_referral_id
    INNER JOIN students s ON s.student_id = tr.student_id
    LEFT JOIN users u ON u.user_id = gca.assigned_user_id
    LEFT JOIN (
        SELECT guidance_case_id, MAX(created_at) AS last_updated
        FROM guidance_case_events
        GROUP BY guidance_case_id
    ) case_updates ON case_updates.guidance_case_id = gc.guidance_case_id
    WHERE " . implode(' AND ', $caseWhere) . "
    ORDER BY gc.opened_at DESC, gc.guidance_case_id DESC"
);
$casesStmt->execute($caseParams);
$cases = $casesStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedCase = null;
foreach ($cases as $case) {
    if ((int) $case['guidance_case_id'] === $requestedCaseId) {
        $selectedCase = $case;
        break;
    }
}
if ($selectedCase === null && $cases !== []) {
    $selectedCase = $cases[0];
}
$selectedCaseId = $selectedCase !== null ? (int) $selectedCase['guidance_case_id'] : 0;

$studentMetrics = null;
$attendanceRate = null;
$attendanceContextAvailable = false;
$recentAttendance = [];
if ($selectedCase !== null) {
    $studentId = (int) $selectedCase['student_id'];

    $studentMetricsStmt = $pdo->prepare(
        "SELECT (SELECT COUNT(DISTINCT tr.teacher_referral_id)
                 FROM teacher_referrals tr
                 WHERE tr.student_id = ?) AS total_referrals,
                (SELECT COUNT(DISTINCT gc.guidance_case_id)
                 FROM guidance_cases gc
                 INNER JOIN guidance_case_assignments gca
                     ON gca.guidance_case_id = gc.guidance_case_id
                    AND gca.assigned_user_id = ?
                    AND gca.ended_at IS NULL
                 INNER JOIN teacher_referrals tr
                     ON tr.teacher_referral_id = gc.teacher_referral_id
                 WHERE tr.student_id = ?
                   AND gc.case_status IN ('Open', 'Active')) AS active_cases"
    );
    $studentMetricsStmt->execute([$studentId, $userId, $studentId]);
    $studentMetrics = $studentMetricsStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_referrals' => 0,
        'active_cases' => 0,
    ];

    $classId = (int) ($selectedCase['operational_class_id'] ?? 0);
    $studentClassEnrollmentId = (int) ($selectedCase['student_class_enrollment_id'] ?? 0);
    $originTeacherId = (int) ($selectedCase['origin_teacher_id'] ?? 0);
    if ($classId > 0 && $studentClassEnrollmentId > 0 && $originTeacherId > 0) {
        $attendanceData = aprismTeacherAnalyticsAttendance($pdo, $originTeacherId, $classId);
        foreach ($attendanceData['students'] as $attendanceStudent) {
            if ((int) $attendanceStudent['student_id'] === $studentId
                && (int) $attendanceStudent['student_class_enrollment_id'] === $studentClassEnrollmentId) {
                $attendanceContextAvailable = true;
                $attendanceRate = $attendanceStudent['attendance_rate'];
                break;
            }
        }

        if ($attendanceContextAvailable) {
            $attendanceHistoryStmt = $pdo->prepare(
                "SELECT ats.meeting_date, ats.scheduled_start_time, sar.status
                 FROM student_attendance_records sar
                 INNER JOIN attendance_sessions ats
                     ON ats.attendance_session_id = sar.attendance_session_id
                 INNER JOIN student_class_enrollments sce
                     ON sce.student_class_enrollment_id = sar.student_class_enrollment_id
                    AND sce.student_id = ?
                    AND sce.operational_class_id = ats.operational_class_id
                 WHERE sce.student_class_enrollment_id = ?
                   AND ats.operational_class_id = ?
                 ORDER BY ats.meeting_date DESC,
                          ats.scheduled_start_time DESC,
                          ats.attendance_session_id DESC
                 LIMIT 5"
            );
            $attendanceHistoryStmt->execute([$studentId, $studentClassEnrollmentId, $classId]);
            $recentAttendance = $attendanceHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

$pageHasFilters = $status !== 'All' || $contextKey !== '' || $search !== '';
$gradeUnavailableReason = 'No Academic Period is linked to this case, and the existing Grade Analytics route is Teacher-only.';
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content">
        <div class="main-wrapper guidance-cases-wrapper">
            <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
            <section class="guidance-page guidance-cases-page">
                <header class="guidance-cases-header">
                    <div>
                        <p class="page-eyebrow">Disciplinary Officer</p>
                        <h1>Cases</h1>
                    </div>
                </header>

                <section class="guidance-cases-kpis" aria-label="Assigned case summary">
                    <article class="guidance-cases-kpi is-blue">
                        <span class="guidance-cases-kpi-icon"><i data-lucide="folder-open" aria-hidden="true"></i></span>
                        <div><span>Total Cases</span><strong><?= (int) $caseSummary['total_cases'] ?></strong><small>Assigned to you</small></div>
                    </article>
                    <article class="guidance-cases-kpi is-blue">
                        <span class="guidance-cases-kpi-icon"><i data-lucide="clock-3" aria-hidden="true"></i></span>
                        <div><span>Ongoing</span><strong><?= (int) $caseSummary['ongoing_cases'] ?></strong><small>Open or active</small></div>
                    </article>
                    <article class="guidance-cases-kpi is-green">
                        <span class="guidance-cases-kpi-icon"><i data-lucide="circle-check" aria-hidden="true"></i></span>
                        <div><span>Closed</span><strong><?= (int) $caseSummary['closed_cases'] ?></strong><small>Resolved cases</small></div>
                    </article>
                    <article class="guidance-cases-kpi is-amber">
                        <span class="guidance-cases-kpi-icon"><i data-lucide="calendar-clock" aria-hidden="true"></i></span>
                        <div><span>For Follow-up</span><strong><?= (int) $caseSummary['followup_cases'] ?></strong><small>Scheduled follow-up exists</small></div>
                    </article>
                </section>

                <div class="guidance-cases-workspace">
                    <div class="guidance-cases-primary-column">
                        <form method="get" class="guidance-panel guidance-cases-filters<?= $contextOptions !== [] ? ' has-context' : '' ?>">
                            <label class="guidance-cases-field">
                                <span>Case Status</span>
                                    <select name="status" onchange="this.form.submit()">
                                    <?php foreach ($statusOptions as $option): ?>
                                        <option value="<?= guidanceCasesEsc($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= guidanceCasesEsc($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <?php if ($contextOptions !== []): ?>
                                <label class="guidance-cases-field">
                                    <span>Academic Context</span>
                                    <select name="context" onchange="this.form.submit()">
                                        <option value="">All recorded contexts</option>
                                        <?php foreach ($contextOptions as $optionKey => $label): ?>
                                            <option value="<?= guidanceCasesEsc($optionKey) ?>" <?= $contextKey === $optionKey ? 'selected' : '' ?>><?= guidanceCasesEsc($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>
                            <label class="guidance-cases-field guidance-cases-search">
                                <span>Search cases</span>
                                <span class="guidance-cases-search-control">
                                    <i data-lucide="search" aria-hidden="true"></i>
                                    <input type="search" name="search" value="<?= guidanceCasesEsc($search) ?>" maxlength="100" placeholder="Case number, student, or assignee">
                                </span>
                            </label>
                            <?php if ($selectedCaseId > 0): ?>
                                <input type="hidden" name="case_id" value="<?= $selectedCaseId ?>">
                            <?php endif; ?>
                        </form>

                        <section class="guidance-panel guidance-cases-worklist">
                            <div class="guidance-panel-heading guidance-cases-worklist-heading">
                                <div>
                                    <p class="guidance-section-kicker">Operational records</p>
                                    <h2>Case Worklist</h2>
                                </div>
                                <span class="guidance-toolbar-meta"><?= count($cases) ?> record<?= count($cases) === 1 ? '' : 's' ?></span>
                            </div>
                            <div class="guidance-table-wrap guidance-cases-table-wrap">
                                <table class="guidance-table guidance-case-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Case No.</th>
                                        <th scope="col">Student</th>
                                        <th scope="col">Assignee</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Date Opened</th>
                                        <th scope="col">Last Updated</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($cases === []): ?>
                                        <tr><td colspan="7" class="guidance-empty"><span><?= $pageHasFilters ? 'No cases match these filters.' : 'No cases are assigned to you.' ?></span></td></tr>
                                    <?php else: ?>
                                        <?php foreach ($cases as $case): ?>
                                            <?php
                                            $rowCaseId = (int) $case['guidance_case_id'];
                                            $rowCaseNumber = (string) $case['case_number'];
                                            $caseDetailUrl = APP_URL . '/dashboard/guidance_case.php?case_id=' . $rowCaseId;
                                            $attendanceUrl = guidanceCasesSelectionUrl($rowCaseId, '#recent-attendance');
                                            $selectedUrl = guidanceCasesSelectionUrl($rowCaseId);
                                            ?>
                                            <tr class="<?= $rowCaseId === $selectedCaseId ? 'is-selected' : '' ?>">
                                                <td data-label="Case No."><a class="guidance-case-number" href="<?= guidanceCasesEsc($selectedUrl) ?>" aria-label="Select case <?= guidanceCasesEsc($rowCaseNumber) ?>"><?= guidanceCasesEsc($rowCaseNumber) ?></a></td>
                                                <td data-label="Student"><strong><?= guidanceCasesEsc($case['student_name']) ?></strong><small><?= guidanceCasesEsc($case['student_number'] ?: 'Student Number pending') ?></small></td>
                                                <td data-label="Assignee"><?= guidanceCasesEsc($case['assignee_name'] ?: 'Unassigned') ?></td>
                                                <td data-label="Status"><span class="guidance-status <?= strtolower(guidanceCasesEsc($case['case_status'])) ?>"><?= guidanceCasesEsc($case['case_status']) ?></span></td>
                                                <td data-label="Date Opened"><time datetime="<?= guidanceCasesEsc($case['opened_at']) ?>"><?= guidanceCasesDateTime($case['opened_at'], 'M j, Y') ?><small><?= guidanceCasesDateTime($case['opened_at'], 'g:i A') ?></small></time></td>
                                                <td data-label="Last Updated"><time datetime="<?= guidanceCasesEsc($case['last_updated']) ?>"><?= guidanceCasesDateTime($case['last_updated'], 'M j, Y') ?><small><?= guidanceCasesDateTime($case['last_updated'], 'g:i A') ?></small></time></td>
                                                <td data-label="Action" class="guidance-case-row-actions">
                                                    <a class="btn btn-secondary guidance-case-view-button" href="<?= guidanceCasesEsc($caseDetailUrl) ?>">View case</a>
                                                    <details class="guidance-case-row-menu">
                                                        <summary aria-label="More actions for case <?= guidanceCasesEsc($rowCaseNumber) ?>"><i data-lucide="ellipsis" aria-hidden="true"></i></summary>
                                                        <div class="guidance-case-row-menu-items">
                                                            <a href="<?= guidanceCasesEsc($caseDetailUrl) ?>">View Details</a>
                                                            <a href="<?= guidanceCasesEsc($attendanceUrl) ?>">View Attendance</a>
                                                            <span class="is-unavailable" role="note" aria-label="<?= guidanceCasesEsc($gradeUnavailableReason) ?>">View Grade Analytics <small>Unavailable</small></span>
                                                            <a href="<?= guidanceCasesEsc($caseDetailUrl) ?>">Open Case Dossier</a>
                                                        </div>
                                                    </details>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <aside class="guidance-cases-side-column">
                        <section class="guidance-panel guidance-selected-student" id="student-overview" aria-labelledby="guidance-selected-student-title">
                            <div class="guidance-panel-heading">
                                <div class="guidance-cases-heading-with-icon">
                                    <span class="guidance-cases-section-icon is-blue"><i data-lucide="user-round" aria-hidden="true"></i></span>
                                    <div><p class="guidance-section-kicker">Case context</p><h2 id="guidance-selected-student-title">Selected Student Overview</h2></div>
                                </div>
                            </div>
                            <?php if ($selectedCase === null): ?>
                                <div class="guidance-cases-neutral-state">
                                    <i data-lucide="user-round-search" aria-hidden="true"></i>
                                    <p><?= $cases === [] && !$pageHasFilters ? 'Student context will appear when a case is assigned.' : 'Select a case to view its Student context.' ?></p>
                                </div>
                            <?php else: ?>
                                <?php
                                $selectedAcademicContext = implode(' · ', array_values(array_filter(
                                    [trim((string) $selectedCase['school_year_snapshot']), trim((string) $selectedCase['semester_snapshot'])],
                                    static fn(string $part): bool => $part !== ''
                                )));
                                $selectedCaseDetailsUrl = APP_URL . '/dashboard/guidance_case.php?case_id=' . $selectedCaseId;
                                $selectedAttendanceUrl = guidanceCasesSelectionUrl($selectedCaseId, '#recent-attendance');
                                ?>
                                <div class="guidance-cases-student-identity">
                                    <span class="guidance-cases-student-icon"><i data-lucide="user-round" aria-hidden="true"></i></span>
                                    <div><strong><?= guidanceCasesEsc($selectedCase['student_name']) ?></strong><span><?= guidanceCasesEsc($selectedCase['student_number'] ?: 'Student Number pending') ?></span><small><?= $selectedAcademicContext !== '' ? guidanceCasesEsc($selectedAcademicContext) . ' · at referral' : 'No academic context recorded on referral' ?></small></div>
                                </div>
                                <div class="guidance-cases-student-metrics">
                                    <article class="is-green"><span class="guidance-cases-metric-icon"><i data-lucide="calendar-check" aria-hidden="true"></i></span><div><span>Attendance Rate</span><strong><?= $attendanceRate === null ? '—' : guidanceCasesEsc(number_format((float) $attendanceRate, 1) . '%') ?></strong><small><?= $attendanceContextAvailable ? 'Referred class · recorded sessions' : 'No authorized linked attendance context' ?></small></div></article>
                                    <article class="is-blue"><span class="guidance-cases-metric-icon"><i data-lucide="chart-no-axes-column-increasing" aria-hidden="true"></i></span><div><span>Average Recorded Raw Score</span><strong>—</strong><small>No case-linked Academic Period</small></div></article>
                                    <article class="is-amber"><span class="guidance-cases-metric-icon"><i data-lucide="file-text" aria-hidden="true"></i></span><div><span>Total Referrals</span><strong><?= (int) ($studentMetrics['total_referrals'] ?? 0) ?></strong><small>APRISM referral records</small></div></article>
                                    <article class="is-red"><span class="guidance-cases-metric-icon"><i data-lucide="folder-open" aria-hidden="true"></i></span><div><span>Active Cases</span><strong><?= (int) ($studentMetrics['active_cases'] ?? 0) ?></strong><small>Open or active · assigned to you</small></div></article>
                                </div>
                                <nav class="guidance-cases-quick-actions" aria-label="Student quick actions">
                                    <a href="<?= guidanceCasesEsc($selectedAttendanceUrl) ?>"><i data-lucide="calendar-check" aria-hidden="true"></i><span>View Attendance</span></a>
                                    <span class="is-unavailable" role="note" aria-label="<?= guidanceCasesEsc($gradeUnavailableReason) ?>"><i data-lucide="chart-no-axes-column-increasing" aria-hidden="true"></i><span>View Grade Analytics</span></span>
                                    <a href="<?= guidanceCasesEsc($selectedCaseDetailsUrl) ?>"><i data-lucide="file-text" aria-hidden="true"></i><span>Open Case Dossier</span></a>
                                </nav>
                            <?php endif; ?>
                        </section>

                        <section class="guidance-panel guidance-recent-attendance" id="recent-attendance" aria-labelledby="guidance-recent-attendance-title">
                            <div class="guidance-panel-heading">
                                <div class="guidance-cases-heading-with-icon">
                                    <span class="guidance-cases-section-icon is-teal"><i data-lucide="calendar-days" aria-hidden="true"></i></span>
                                    <div><p class="guidance-section-kicker">Recorded evidence</p><h2 id="guidance-recent-attendance-title">Recent Attendance (Last 5 Sessions)</h2></div>
                                </div>
                            </div>
                            <?php if ($selectedCase === null): ?>
                                <div class="guidance-cases-neutral-state"><p>Select a case to view attendance in its referral class context.</p></div>
                            <?php elseif (!$attendanceContextAvailable): ?>
                                <div class="guidance-cases-neutral-state"><p>Attendance is unavailable for this case's linked class and Student enrollment.</p></div>
                            <?php elseif ($recentAttendance === []): ?>
                                <div class="guidance-cases-neutral-state"><p>No recorded attendance sessions are available for this referral context.</p></div>
                            <?php else: ?>
                                <div class="guidance-cases-attendance-table-wrap">
                                    <table class="guidance-cases-attendance-table">
                                        <thead><tr><th scope="col">Date</th><th scope="col">Time</th><th scope="col">Status</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($recentAttendance as $record): ?>
                                                <?php $recordStatus = (string) $record['status']; ?>
                                                <tr>
                                                    <td data-label="Date"><?= guidanceCasesDateTime($record['meeting_date'], 'M j, Y') ?></td>
                                                    <td data-label="Time"><?= guidanceCasesDateTime($record['scheduled_start_time'], 'g:i A') ?></td>
                                                    <td data-label="Status"><span class="guidance-cases-attendance-status is-<?= strtolower(guidanceCasesEsc($recordStatus)) ?>"><?= guidanceCasesEsc($recordStatus) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>
                    </aside>
                </div>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
