<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/adviser_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';
require_once __DIR__ . '/../includes/helper/report_export_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$active = $pdo->query("SELECT school_year_id,school_year FROM school_years WHERE status='Active' ORDER BY school_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$yearId = $active ? (int) $active['school_year_id'] : null;
$assignments = aprismAdviserAssignments($pdo, $teacherId, $yearId);
if (
    !userHasResponsibility(RESPONSIBILITY_ADVISER)
    || $active === null
    || $assignments === []
) {
    http_response_code(403);
    exit('Adviser responsibility is not assigned for the current School Year.');
}

$advisorySectionNames = [];
$advisoryGradeLevels = [];
foreach ($assignments as $assignment) {
    $sectionName = trim((string) ($assignment['section_name'] ?? ''));
    if ($sectionName !== '') {
        $advisorySectionNames[$sectionName] = $sectionName;
    }

    $yearLevel = trim((string) ($assignment['year_level'] ?? ''));
    if ($yearLevel !== '') {
        $gradeLabel = in_array($yearLevel, ['11', '12'], true)
            ? 'Grade ' . $yearLevel
            : (ctype_digit($yearLevel) ? 'Year ' . $yearLevel : $yearLevel);
        $advisoryGradeLevels[$gradeLabel] = $gradeLabel;
    }
}
$advisoryContextParts = array_values(array_filter([
    implode(', ', $advisorySectionNames),
    'Senior High School', // Adviser assignments are scoped to active SHS programs.
    implode(', ', $advisoryGradeLevels),
]));

$allStudents = aprismAdviserStudents($pdo, $teacherId, $yearId);
$pageSize = aprismAcademicHeadPageSize('adviser');
$totalStudents = count($allStudents);
$totalPages = $totalStudents > 0 ? (int) ceil($totalStudents / $pageSize) : 0;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$currentPage = $totalPages > 0 ? min($currentPage, $totalPages) : 1;
$offset = ($currentPage - 1) * $pageSize;
$students = array_slice($allStudents, $offset, $pageSize);
$firstStudent = $totalStudents > 0 ? $offset + 1 : 0;
$lastStudent = $totalStudents > 0
    ? min($offset + count($students), $totalStudents)
    : 0;
$pageNumbers = $totalPages > 0
    ? ($totalPages <= 7
        ? range(1, $totalPages)
        : array_values(array_unique(array_filter(
            [1, $currentPage - 1, $currentPage, $currentPage + 1, $totalPages],
            static fn (int $page): bool => $page >= 1 && $page <= $totalPages
        ))))
    : [];
sort($pageNumbers, SORT_NUMERIC);
$pageUrl = static function (int $page) use ($pageSize): string {
    return '?' . http_build_query([
        'page' => max(1, $page),
        'per_page' => $pageSize,
    ]);
};

$concerns = aprismAdviserAttendanceConcerns($pdo, $teacherId, $yearId);
$attendanceEvidenceByStudent = aprismAdviserAttendanceEvidenceCounts($pdo, $teacherId, $yearId);
$referrals = aprismAdviserReferralFollowups($pdo, $teacherId, $yearId);
$gradeCounts = aprismAdviserMonitoringGradeCounts($pdo, $teacherId, $yearId);
$concernsByStudent = [];
foreach ($concerns as $concern) {
    $concernsByStudent[(int) $concern['student_id']] = $concern;
}
$referralsByStudent = [];
foreach ($referrals as $referral) {
    $studentId = (int) $referral['student_id'];
    $referralsByStudent[$studentId] = ($referralsByStudent[$studentId] ?? 0) + 1;
}
$gradeRecordTotal = array_sum(array_map('intval', $gradeCounts));
$adviserExport = (string) ($_GET['export'] ?? '');
if (in_array($adviserExport, ['csv', 'xlsx'], true)) {
    $exportRows = [];
    foreach ($allStudents as $student) {
        $id = (int) $student['student_id'];
        $concern = $concernsByStudent[$id] ?? [];
        $exportRows[] = [
            (string) $student['student_name'],
            (string) $student['student_number'],
            (int) ($attendanceEvidenceByStudent[$id] ?? 0),
            (int) ($attendanceEvidenceByStudent[$id] ?? 0) > 0 ? (int) ($concern['absent_count'] ?? 0) : null,
            (int) ($attendanceEvidenceByStudent[$id] ?? 0) > 0 ? (int) ($concern['late_count'] ?? 0) : null,
            (int) ($referralsByStudent[$id] ?? 0),
            (int) ($gradeCounts[$id] ?? 0),
        ];
    }
    $context = ['School Year' => (string) ($active['school_year'] ?? ''), 'Advisory Scope' => implode(', ', $advisoryContextParts), 'Advisory Students' => (string) $totalStudents];
    $filename = 'aprism-adviser-reports-' . (int) $yearId . '-' . date('Ymd_His');
    $headers = ['Student', 'Student Number', 'Attendance Evidence Records', 'Recorded Absences', 'Recorded Lates', 'Referral Records', 'Confirmed Grade Records'];
    if ($adviserExport === 'csv') aprismSendCsv($filename, [array_keys($context), array_values($context), $headers, ...$exportRows]);
    aprismSendXlsx($filename, 'Adviser Reports', $context, $headers, $exportRows, ['A' => 28, 'B' => 20, 'C' => 24, 'D' => 18, 'E' => 16, 'F' => 20, 'G' => 26], true);
}
$pageTitle = 'Advisory Reports';
$activePage = 'advisory_reports';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/adviser.css'; ?>
<!doctype html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>

<body class="aprism-report-print-page"><?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content adviser-reports-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper adviser-page adviser-reports-page">
            <div class="page-header adviser-page-header">
                <div>
                    <h1 class="teacher-page-title">Advisory Reports</h1>
                    <div class="adviser-students-context adviser-reports-context" aria-label="Current advisory class and academic context">
                        <?php foreach ($advisoryContextParts as $index => $contextPart): ?>
                            <?php if ($index > 0): ?><span class="adviser-students-context-separator" aria-hidden="true">·</span><?php endif; ?>
                            <span><?= htmlspecialchars($contextPart, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="aprism-export-actions no-print" aria-label="Advisory report actions">
                    <a class="aprism-export-action aprism-export-csv" href="?<?= htmlspecialchars(http_build_query(['export' => 'csv']), ENT_QUOTES, 'UTF-8') ?>"><i data-lucide="file-down" aria-hidden="true"></i><span>CSV</span></a>
                    <a class="aprism-export-action aprism-export-xlsx" href="?<?= htmlspecialchars(http_build_query(['export' => 'xlsx']), ENT_QUOTES, 'UTF-8') ?>"><i data-lucide="file-spreadsheet" aria-hidden="true"></i><span>Excel</span></a>
                    <button class="aprism-export-action aprism-export-print" type="button" data-report-print><i data-lucide="printer" aria-hidden="true"></i><span>Print / PDF</span></button>
                </div>
            </div>
            <div class="aprism-report-print-only"><strong>APRISM</strong><h1>Adviser Reports</h1><p><?= htmlspecialchars((string) ($active['school_year'] ?? 'School Year unavailable'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(implode(', ', $advisoryContextParts), ENT_QUOTES, 'UTF-8') ?></p></div>
            <div class="aprism-adviser-print-table-wrap">
                <table class="aprism-adviser-print-table">
                    <thead><tr><th>Student</th><th>Attendance Concerns</th><th>Referrals</th><th>Grade Records</th></tr></thead>
                    <tbody>
                    <?php foreach ($allStudents as $printStudent): ?>
                        <?php
                        $printStudentId = (int) $printStudent['student_id'];
                        $printConcern = $concernsByStudent[$printStudentId] ?? [];
                        $printConcernCount = (int) ($printConcern['absent_count'] ?? 0) + (int) ($printConcern['late_count'] ?? 0);
                        $printAttendanceText = $printConcernCount > 0
                            ? number_format($printConcernCount) . ' ' . ($printConcernCount === 1 ? 'concern' : 'concerns')
                            : ((int) ($attendanceEvidenceByStudent[$printStudentId] ?? 0) > 0 ? 'No recorded absence or late concerns' : 'No attendance records yet');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $printStudent['student_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $printStudent['student_number'], ENT_QUOTES, 'UTF-8') ?>)</td>
                            <td><?= htmlspecialchars($printAttendanceText, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format((int) ($referralsByStudent[$printStudentId] ?? 0)) ?></td>
                            <td><?= number_format((int) ($gradeCounts[$printStudentId] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($allStudents === []): ?><tr><td colspan="4">No advisory records are available in this scope.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <section class="adviser-stat-grid adviser-reports-stat-grid" aria-label="Advisory report summary">
                <article class="adviser-stat-card"><div class="adviser-stat-icon"><i data-lucide="users" aria-hidden="true"></i></div><div class="adviser-stat-copy"><span class="adviser-stat-label">Advisory Students</span><strong class="adviser-stat-value"><?= number_format($totalStudents) ?></strong></div></article>
                <article class="adviser-stat-card"><div class="adviser-stat-icon adviser-stat-icon-warning"><i data-lucide="triangle-alert" aria-hidden="true"></i></div><div class="adviser-stat-copy"><span class="adviser-stat-label">Attendance Concerns</span><strong class="adviser-stat-value"><?= number_format(count($concerns)) ?></strong></div></article>
                <article class="adviser-stat-card"><div class="adviser-stat-icon adviser-stat-icon-neutral"><i data-lucide="clipboard-check" aria-hidden="true"></i></div><div class="adviser-stat-copy"><span class="adviser-stat-label">Referral Records</span><strong class="adviser-stat-value"><?= number_format(count($referrals)) ?></strong></div></article>
                <article class="adviser-stat-card"><div class="adviser-stat-icon adviser-stat-icon-success"><i data-lucide="file-check-2" aria-hidden="true"></i></div><div class="adviser-stat-copy"><span class="adviser-stat-label">Confirmed Grade Records</span><strong class="adviser-stat-value"><?= number_format($gradeRecordTotal) ?></strong></div></article>
            </section>
            <section class="teacher-panel adviser-workspace-panel adviser-reports-panel" aria-labelledby="advisory-summary-title">
                <div class="adviser-workspace-header adviser-reports-header">
                    <h2 class="teacher-panel-title" id="advisory-summary-title">Advisory summary</h2>
                </div>
                <div class="adviser-table-shell adviser-reports-table-shell">
                    <table class="teacher-table adviser-report-table adviser-reports-table">
                        <caption class="visually-hidden">Student summary for the current advisory scope</caption>
                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Attendance Concerns</th>
                                <th scope="col">Referrals</th>
                                <th scope="col">Grade Records</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <?php if ($students): ?>
                            <tbody><?php foreach ($students as $student): ?>
                                <?php $studentId = (int) $student['student_id']; $concern = $concernsByStudent[$studentId] ?? []; ?>
                                <tr>
                                    <td data-label="Student"><strong><?= htmlspecialchars((string) $student['student_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) $student['student_number'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                    <?php $concernCount = (int) ($concern['absent_count'] ?? 0) + (int) ($concern['late_count'] ?? 0); ?>
                                    <td data-label="Attendance Concerns">
                                        <?php if ($concernCount > 0): ?>
                                            <span class="adviser-status adviser-status-warning adviser-reports-concern"><?= number_format($concernCount) ?> <?= $concernCount === 1 ? 'concern' : 'concerns' ?></span>
                                        <?php elseif ((int) ($attendanceEvidenceByStudent[$studentId] ?? 0) > 0): ?>
                                            <span class="adviser-status adviser-reports-concern">No recorded absence or late concerns</span>
                                        <?php else: ?>
                                            <span class="adviser-status adviser-reports-concern">No attendance records yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Referrals"><?= number_format((int) ($referralsByStudent[$studentId] ?? 0)) ?></td>
                                    <td data-label="Grade Records"><?= number_format((int) ($gradeCounts[$studentId] ?? 0)) ?></td>
                                    <td data-label="Action"><a class="aprism-action-btn aprism-action-btn-secondary" href="<?= APP_URL ?>/dashboard/teacher_advisory_student.php?student_id=<?= $studentId ?>">View student</a></td>
                                </tr>
                            <?php endforeach; ?></tbody>
                        <?php endif; ?>
                    </table>
                    <?php if ($totalStudents === 0): ?>
                        <div class="adviser-reports-empty-state" role="status">
                            <span class="adviser-reports-empty-icon" aria-hidden="true"><i data-lucide="clipboard-list"></i></span>
                            <div class="adviser-reports-empty-copy">
                                <h3>No advisory records yet</h3>
                                <p>Student advisory summary records will appear here.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($totalStudents > 0): ?>
                    <div class="teacher-pagination adviser-reports-pagination">
                        <div class="teacher-pagination-info">
                            Showing <?= number_format($firstStudent) ?>–<?= number_format($lastStudent) ?> of <?= number_format($totalStudents) ?> students
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <nav class="teacher-pagination-controls" aria-label="Advisory Reports pagination">
                                <?php if ($currentPage > 1): ?>
                                    <a class="teacher-page-link" href="<?= htmlspecialchars($pageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>" rel="prev">Previous</a>
                                <?php else: ?>
                                    <span class="teacher-page-link disabled" aria-disabled="true">Previous</span>
                                <?php endif; ?>
                                <?php $lastDisplayedPage = 0; ?>
                                <?php foreach ($pageNumbers as $page): ?>
                                    <?php if ($lastDisplayedPage > 0 && $page - $lastDisplayedPage > 1): ?><span class="teacher-page-ellipsis" aria-hidden="true">…</span><?php endif; ?>
                                    <a class="teacher-page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= htmlspecialchars($pageUrl($page), ENT_QUOTES, 'UTF-8') ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?> aria-label="Page <?= $page ?>"><?= $page ?></a>
                                    <?php $lastDisplayedPage = $page; ?>
                                <?php endforeach; ?>
                                <?php if ($currentPage < $totalPages): ?>
                                    <a class="teacher-page-link" href="<?= htmlspecialchars($pageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>" rel="next">Next</a>
                                <?php else: ?>
                                    <span class="teacher-page-link disabled" aria-disabled="true">Next</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main><?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <?php if ($totalStudents > 0): ?>
        <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="adviser"></script>
    <?php endif; ?>
</body>

</html>
