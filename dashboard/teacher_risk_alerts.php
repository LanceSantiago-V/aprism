<?php

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/monitoring_helper.php';
require_once __DIR__ . '/../includes/helper/adviser_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_referral_helper.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$scope = (string) ($_GET['scope'] ?? 'teacher');
$isAdviserScope = $scope === 'adviser';
$active = aprismActiveSchoolYear($pdo);
$schoolYear = $active['school_year'] ?? null;
$yearId = $active ? (int) $active['school_year_id'] : null;
$students = [];
$concerns = [];
$attendanceEvidenceByStudent = [];
$referralCounts = [];
$gradeCounts = [];
$adviserContextParts = [];
$adviserActiveConcernCount = 0;
$adviserSearchTerm = '';
$adviserPageSize = 4;
$adviserTotalFilteredConcerns = 0;
$adviserTotalPages = 0;
$adviserCurrentPage = 1;
$adviserFirstResult = 0;
$adviserLastResult = 0;
$adviserRiskRows = [];
$adviserVisibleRiskRows = [];
$adviserPageNumbers = [];
$teacherRiskRows = [];
$teacherVisibleRiskRows = [];
$teacherPageSize = 5;
$teacherCurrentPage = 1;
$teacherTotalPages = 0;
$teacherFirstResult = 0;
$teacherLastResult = 0;
$teacherPageNumbers = [];
$teacherReferralFormState = [];
$teacherReferralFormErrors = [];
$teacherReferralCreatedContext = null;

if ($isAdviserScope) {
    require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';
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
    $adviserContextParts = array_values(array_filter([
        implode(', ', $advisorySectionNames),
        'Senior High School',
        implode(', ', $advisoryGradeLevels),
    ]));

    $students = aprismAdviserStudents($pdo, $teacherId, $yearId);
    $classRiskEvidence = aprismMonitoringCurrentClassRiskEvidence($pdo, 'adviser', $teacherId);
    $riskSummaries = aprismMonitoringStudentRiskSummaries($classRiskEvidence);
    foreach ($riskSummaries as $studentId => $summary) {
        $attendanceEvidenceByStudent[(int) $studentId] = !empty($summary['assessable']) ? 1 : 0;
        if ((int) ($summary['tier'] ?? 0) > 0) {
            $concerns[(int) $studentId] = $summary;
        }
    }
    $gradeCounts = aprismAdviserMonitoringGradeCounts($pdo, $teacherId, $yearId);
    $followups = aprismAdviserReferralFollowups($pdo, $teacherId, $yearId);
    foreach ($followups as $followup) {
        $studentId = (int) $followup['student_id'];
        $referralCounts[$studentId] = ($referralCounts[$studentId] ?? 0) + 1;
    }
} else {
    require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';
    $students = aprismTeacherMonitoringStudents($pdo, $teacherId, $schoolYear);
    $classRiskEvidence = aprismMonitoringCurrentClassRiskEvidence($pdo, 'teacher', $teacherId);
    $riskSummaries = aprismMonitoringStudentRiskSummaries($classRiskEvidence);
    $referralCounts = aprismTeacherMonitoringReferralCounts($pdo, $teacherId, $schoolYear);
    $gradeCounts = aprismTeacherMonitoringGradeCounts($pdo, $teacherId, $schoolYear);

    foreach ($riskSummaries as $studentId => $summary) {
        $attendanceEvidenceByStudent[(int) $studentId] = !empty($summary['assessable']) ? 1 : 0;
        if ((int) ($summary['tier'] ?? 0) > 0) {
            $concerns[(int) $studentId] = $summary;
        }
    }
}

$teacherReferralFlash = ['success' => null, 'error' => null, 'warning' => null];
$teacherReferralSheetData = [];
$teacherReferralClasses = [];
$teacherReferralSheetInitiallyOpen = false;
$teacherReferralSelectedClassId = 0;
$teacherReferralSelectedSceId = 0;
$teacherReferralSelectedReasonCode = '';
$teacherReferralSupportingNote = '';
$teacherReferralSelectedEvidence = null;
$teacherReferralSelectedEvidenceToken = '';
if (!$isAdviserScope) {
    require_once __DIR__ . '/../includes/helper/flash_message.php';
    $teacherReferralFlash = ['success' => $flash['success'], 'error' => $flash['error'], 'warning' => $flash['warning']];
    $teacherReferralFormState = is_array($_SESSION['teacher_referral_form'] ?? null) ? $_SESSION['teacher_referral_form'] : [];
    $teacherReferralFormErrors = is_array($_SESSION['teacher_referral_errors'] ?? null) ? $_SESSION['teacher_referral_errors'] : [];
    unset($_SESSION['teacher_referral_form'], $_SESSION['teacher_referral_errors']);
    $teacherReferralCreatedContext = is_array($_SESSION['teacher_referral_created_context'] ?? null) ? $_SESSION['teacher_referral_created_context'] : null;
    unset($_SESSION['teacher_referral_created_context']);
    $teacherReferralClasses = aprismTeacherReferralClasses($pdo, $teacherId, $schoolYear);
    $teacherReferralSheetData = aprismTeacherReferralSheetData($pdo, $teacherId, $schoolYear, $teacherReferralClasses, $classRiskEvidence ?? []);
    $teacherReferralRequestedClassId = (int) ($teacherReferralFormState['operational_class_id'] ?? (filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?: 0));
    foreach ($teacherReferralClasses as $class) {
        if ((int) $class['operational_class_id'] === $teacherReferralRequestedClassId) {
            $teacherReferralSelectedClassId = $teacherReferralRequestedClassId;
            break;
        }
    }
    $teacherReferralRequestedSceId = (int) ($teacherReferralFormState['student_class_enrollment_id'] ?? (filter_input(INPUT_GET, 'student_class_enrollment_id', FILTER_VALIDATE_INT) ?: 0));
    $teacherReferralRequestedStudentId = (int) ($teacherReferralFormState['student_id'] ?? (filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0));
    if ($teacherReferralSelectedClassId > 0) {
        foreach (aprismTeacherReferralRoster($pdo, $teacherId, $teacherReferralSelectedClassId) as $rosterStudent) {
            if ((int) $rosterStudent['student_class_enrollment_id'] === $teacherReferralRequestedSceId
                || ($teacherReferralRequestedSceId === 0 && (int) $rosterStudent['student_id'] === $teacherReferralRequestedStudentId)) {
                $teacherReferralSelectedSceId = (int) $rosterStudent['student_class_enrollment_id'];
                break;
            }
        }
    }
    if ($teacherReferralSelectedClassId > 0 && $teacherReferralSelectedSceId > 0) {
        $evidenceRow = aprismTeacherReferralAttendanceEvidence($pdo, $teacherId, $teacherReferralSelectedClassId, $teacherReferralSelectedSceId);
        if ($evidenceRow !== null) {
            $teacherReferralSelectedEvidence = aprismTeacherReferralEvidenceSnapshot($evidenceRow, 'risk_alerts');
            $teacherReferralSelectedEvidenceToken = aprismTeacherReferralIssueEvidenceToken($teacherReferralSelectedEvidence);
        }
    }
    $teacherReferralSelectedReasonCode = (string) ($teacherReferralFormState['reason_code'] ?? '');
    $teacherReferralSupportingNote = (string) ($teacherReferralFormState['note'] ?? '');
    $teacherReferralSheetInitiallyOpen = !empty($_GET['open_referral']) || $teacherReferralFormErrors !== [];
}

$studentsById = [];
foreach ($students as $student) {
    $studentsById[(int) $student['student_id']] = $student;
}

$scopedStudentCount = count($studentsById);
$studentsWithAttendanceEvidence = 0;
foreach (array_keys($studentsById) as $studentId) {
    if ((int) ($attendanceEvidenceByStudent[(int) $studentId] ?? 0) > 0) {
        $studentsWithAttendanceEvidence++;
    }
}
$studentsWithoutAttendanceEvidence = max(0, $scopedStudentCount - $studentsWithAttendanceEvidence);

if (!$isAdviserScope) {
    $teacherPageSize = aprismAcademicHeadPageSize('teacher');

    foreach ($concerns as $studentId => $concern) {
        $student = $studentsById[(int) $studentId] ?? null;
        if ($student === null) {
            continue;
        }

        $teacherRiskRows[] = [
            'student_id' => (int) $studentId,
            'student' => $student,
            'concern' => $concern,
        ];
    }

    $teacherTotalPages = $teacherRiskRows !== []
        ? (int) ceil(count($teacherRiskRows) / $teacherPageSize)
        : 0;
    $teacherCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
    $teacherCurrentPage = $teacherTotalPages > 0
        ? min($teacherCurrentPage, $teacherTotalPages)
        : 1;
    $teacherOffset = ($teacherCurrentPage - 1) * $teacherPageSize;
    $teacherVisibleRiskRows = array_slice($teacherRiskRows, $teacherOffset, $teacherPageSize);
    $teacherFirstResult = $teacherRiskRows !== [] ? $teacherOffset + 1 : 0;
    $teacherLastResult = $teacherRiskRows !== []
        ? min($teacherOffset + count($teacherVisibleRiskRows), count($teacherRiskRows))
        : 0;
    $teacherPageNumbers = $teacherTotalPages > 0
        ? ($teacherTotalPages <= 7
            ? range(1, $teacherTotalPages)
            : array_values(array_unique(array_filter(
                [1, $teacherCurrentPage - 1, $teacherCurrentPage, $teacherCurrentPage + 1, $teacherTotalPages],
                static fn (int $page): bool => $page >= 1 && $page <= $teacherTotalPages
            ))))
        : [];
    sort($teacherPageNumbers, SORT_NUMERIC);
}

if ($isAdviserScope) {
    $adviserActiveConcernCount = count($concerns);
    $adviserSearchTerm = trim((string) ($_GET['search'] ?? ''));
    $adviserPageSize = aprismAcademicHeadPageSize('adviser');

    foreach ($concerns as $concernKey => $concern) {
        $studentId = (int) ($concern['student_id'] ?? $concernKey);
        $student = $studentsById[$studentId] ?? null;
        if ($student === null) {
            continue;
        }

        if (
            $adviserSearchTerm !== ''
            && stripos(
                (string) ($student['student_number'] ?? '') . ' ' . (string) ($student['student_name'] ?? ''),
                $adviserSearchTerm
            ) === false
        ) {
            continue;
        }

        $adviserRiskRows[] = [
            'student_id' => $studentId,
            'student' => $student,
            'concern' => $concern,
        ];
    }

    $adviserTotalFilteredConcerns = count($adviserRiskRows);
    $adviserTotalPages = $adviserTotalFilteredConcerns > 0
        ? (int) ceil($adviserTotalFilteredConcerns / $adviserPageSize)
        : 0;
    $adviserCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
    $adviserCurrentPage = $adviserTotalPages > 0
        ? min($adviserCurrentPage, $adviserTotalPages)
        : 1;
    $adviserOffset = ($adviserCurrentPage - 1) * $adviserPageSize;
    $adviserVisibleRiskRows = array_slice($adviserRiskRows, $adviserOffset, $adviserPageSize);
    $adviserFirstResult = $adviserTotalFilteredConcerns > 0 ? $adviserOffset + 1 : 0;
    $adviserLastResult = $adviserTotalFilteredConcerns > 0
        ? min($adviserOffset + count($adviserVisibleRiskRows), $adviserTotalFilteredConcerns)
        : 0;
    $adviserPageNumbers = $adviserTotalPages > 0
        ? ($adviserTotalPages <= 7
            ? range(1, $adviserTotalPages)
            : array_values(array_unique(array_filter(
                [1, $adviserCurrentPage - 1, $adviserCurrentPage, $adviserCurrentPage + 1, $adviserTotalPages],
                static fn (int $page): bool => $page >= 1 && $page <= $adviserTotalPages
            ))))
        : [];
    sort($adviserPageNumbers, SORT_NUMERIC);
}

$adviserRiskPageUrl = static function (int $page) use ($adviserSearchTerm, $adviserPageSize): string {
    $query = [
        'scope' => 'adviser',
        'page' => max(1, $page),
        'per_page' => $adviserPageSize,
    ];
    if ($adviserSearchTerm !== '') {
        $query['search'] = $adviserSearchTerm;
    }
    return APP_URL . '/dashboard/teacher_risk_alerts.php?' . http_build_query($query);
};
$teacherRiskPageUrl = static function (int $page) use ($teacherPageSize): string {
    $query = [
        'scope' => 'teacher',
        'page' => max(1, $page),
        'per_page' => $teacherPageSize,
    ];
    return APP_URL . '/dashboard/teacher_risk_alerts.php?' . http_build_query($query);
};

$pageTitle = $isAdviserScope ? 'Advisory Risk Alerts' : 'Risk Alerts';
$activePage = $isAdviserScope ? 'advisory_risk_alerts' : 'risk_alerts';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-risk-alerts.css';
$additionalStylesheets = $isAdviserScope ? ['assets/css/pages/adviser.css'] : [];
?>
<!doctype html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?><?php if (!$isAdviserScope): ?><link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components/teacher-referral-action-sheet.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/components/teacher-referral-action-sheet.css') ?>"><?php endif; ?></head>

<body><?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= $isAdviserScope ? 'adviser-risk-alerts-shell ' : 'teacher-risk-alerts-teacher-shell ' ?><?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper teacher-risk-alerts-page <?= $isAdviserScope ? 'adviser-page adviser-risk-alerts-page' : 'teacher-risk-alerts-teacher' ?>">
            <div class="page-header adviser-page-header">
                <div>
                    <h1 class="teacher-page-title" tabindex="-1" data-referral-sheet-return-focus><?= htmlspecialchars($pageTitle) ?></h1>
                    <?php if ($isAdviserScope && $adviserContextParts !== []): ?>
                        <div class="adviser-students-context adviser-risk-context" aria-label="Current advisory class and academic context">
                            <?php foreach ($adviserContextParts as $index => $contextPart): ?>
                                <?php if ($index > 0): ?><span class="adviser-students-context-separator" aria-hidden="true">·</span><?php endif; ?>
                                <span><?= htmlspecialchars($contextPart, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!$isAdviserScope && $schoolYear !== null): ?>
                        <div class="teacher-risk-alerts-teacher-context" aria-label="Monitoring scope">
                            <i data-lucide="layers" aria-hidden="true"></i>
                            <span>Your active classes</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <section class="teacher-panel adviser-workspace-panel <?= $isAdviserScope
                ? ($concerns === [] ? 'is-empty' : 'has-concerns') . ' adviser-risk-alerts-panel'
                : ($teacherRiskRows === [] ? 'is-empty' : 'has-concerns') . ' teacher-risk-alerts-teacher-panel' ?>">
                <?php if ($isAdviserScope): ?>
                    <div class="adviser-workspace-header adviser-risk-alerts-header">
                        <h2 class="teacher-panel-title">Attendance concerns</h2>
                        <span class="adviser-risk-count-badge <?= $adviserActiveConcernCount === 0 ? 'is-clear' : 'is-active' ?>">
                            <?= number_format($adviserActiveConcernCount) ?> Active
                        </span>
                    </div>

                    <?php if ($adviserActiveConcernCount === 0): ?>
                        <div class="teacher-empty-state adviser-risk-empty-state" role="status">
                            <i data-lucide="<?= $scopedStudentCount === 0 ? 'users' : ($studentsWithAttendanceEvidence === 0 ? 'info' : 'shield-check') ?>" aria-hidden="true"></i>
                            <?php if ($scopedStudentCount === 0): ?>
                                <h3 class="teacher-empty-state-title">No Students are in the current Adviser scope</h3>
                                <p class="teacher-empty-state-text">Students appear here when active placement records match your assigned Sections and School Year.</p>
                            <?php elseif ($studentsWithAttendanceEvidence === 0): ?>
                                <h3 class="teacher-empty-state-title">Attendance evidence is not available yet</h3>
                                <p class="teacher-empty-state-text">No attendance records are available for your assigned Students, so attendance concerns cannot yet be assessed.</p>
                            <?php elseif ($studentsWithoutAttendanceEvidence > 0): ?>
                                <h3 class="teacher-empty-state-title">No recorded absence/late concerns in the available records</h3>
                                <p class="teacher-empty-state-text">Attendance records are available for <?= number_format($studentsWithAttendanceEvidence) ?> of <?= number_format($scopedStudentCount) ?> assigned Students; concerns cannot yet be assessed for the remaining <?= number_format($studentsWithoutAttendanceEvidence) ?>.</p>
                            <?php else: ?>
                                <h3 class="teacher-empty-state-title">No recorded absence/late concerns</h3>
                                <p class="teacher-empty-state-text">Attendance records are available for all <?= number_format($scopedStudentCount) ?> assigned Students.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form class="teacher-search adviser-risk-search" method="get"
                            action="<?= APP_URL ?>/dashboard/teacher_risk_alerts.php" role="search">
                            <label class="visually-hidden" for="adviserRiskSearch">Search by student number or name</label>
                            <i data-lucide="search" aria-hidden="true"></i>
                            <input id="adviserRiskSearch" type="search" name="search"
                                value="<?= htmlspecialchars($adviserSearchTerm, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Search by student number or name" data-adviser-risk-server-search>
                            <input type="hidden" name="scope" value="adviser">
                            <input type="hidden" name="per_page" value="<?= $adviserPageSize ?>">
                            <button type="submit" class="visually-hidden">Search</button>
                        </form>

                        <?php if ($studentsWithoutAttendanceEvidence > 0 && $scopedStudentCount > 0): ?>
                            <div class="teacher-risk-evidence-note" role="status">
                                Attendance records are available for <?= number_format($studentsWithAttendanceEvidence) ?> of <?= number_format($scopedStudentCount) ?> assigned Students; concerns cannot yet be assessed for the remaining <?= number_format($studentsWithoutAttendanceEvidence) ?>.
                            </div>
                        <?php endif; ?>

                        <?php if ($adviserVisibleRiskRows === []): ?>
                            <div class="adviser-table-shell adviser-risk-table-shell adviser-risk-filter-empty" role="status">
                                <div class="teacher-empty-state">
                                    <i data-lucide="search-x" aria-hidden="true"></i>
                                    <h3 class="teacher-empty-state-title">No matching students</h3>
                                    <p class="teacher-empty-state-text">Try a different student number or name.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="adviser-table-shell adviser-risk-table-shell">
                                <table class="teacher-table adviser-risk-table adviser-risk-alerts-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Student Number</th>
                                            <th scope="col">Student</th>
                                            <th scope="col">Attendance Concern</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($adviserVisibleRiskRows as $riskRow): ?>
                                            <?php
                                            $studentId = (int) $riskRow['student_id'];
                                            $student = $riskRow['student'];
                                            $concern = $riskRow['concern'];
                                            $leadingClass = $concern['contributing_classes'][0] ?? [];
                                            $tier = (int) ($concern['tier'] ?? 0);
                                            $actionUrl = APP_URL . '/dashboard/teacher_advisory_student.php?student_id=' . $studentId;
                                            ?>
                                            <tr data-adviser-risk-row>
                                                <td data-label="Student Number"><?= htmlspecialchars((string) $student['student_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td data-label="Student"><?= htmlspecialchars((string) $student['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td data-label="Attendance Concern">
                                                    <span class="adviser-risk-concern-list">
                                                        <span class="adviser-risk-concern-badge is-absence">Tier <?= $tier ?></span>
                                                        <span><?= htmlspecialchars((string) ($concern['main_reason'] ?? 'Attendance concern'), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php if ($leadingClass !== []): ?>
                                                            <small><?= htmlspecialchars((string) ($leadingClass['subject_name'] ?? 'Class') . ' · ' . (string) ($leadingClass['section_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td data-label="Action"><a class="aprism-action-btn aprism-action-btn-secondary" href="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>">View student</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($adviserTotalPages > 1): ?>
                            <div class="teacher-pagination adviser-risk-pagination">
                                <div class="teacher-pagination-info">
                                    Showing <?= number_format($adviserFirstResult) ?>–<?= number_format($adviserLastResult) ?> of <?= number_format($adviserTotalFilteredConcerns) ?> students
                                </div>
                                <nav class="teacher-pagination-controls" aria-label="Advisory attendance concerns pagination">
                                    <?php if ($adviserCurrentPage > 1): ?>
                                        <a href="<?= htmlspecialchars($adviserRiskPageUrl($adviserCurrentPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link" rel="prev">Previous</a>
                                    <?php else: ?>
                                        <span class="teacher-page-link disabled" aria-disabled="true">Previous</span>
                                    <?php endif; ?>
                                    <?php $lastDisplayedPage = 0; ?>
                                    <?php foreach ($adviserPageNumbers as $page): ?>
                                        <?php if ($lastDisplayedPage > 0 && $page - $lastDisplayedPage > 1): ?>
                                            <span class="teacher-page-ellipsis" aria-hidden="true">…</span>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($adviserRiskPageUrl($page), ENT_QUOTES, 'UTF-8') ?>"
                                            class="teacher-page-link <?= $page === $adviserCurrentPage ? 'active' : '' ?>"
                                            <?= $page === $adviserCurrentPage ? 'aria-current="page"' : '' ?>
                                            aria-label="Page <?= $page ?>"><?= $page ?></a>
                                        <?php $lastDisplayedPage = $page; ?>
                                    <?php endforeach; ?>
                                    <?php if ($adviserCurrentPage < $adviserTotalPages): ?>
                                        <a href="<?= htmlspecialchars($adviserRiskPageUrl($adviserCurrentPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link" rel="next">Next</a>
                                    <?php else: ?>
                                        <span class="teacher-page-link disabled" aria-disabled="true">Next</span>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="adviser-workspace-header teacher-risk-alerts-teacher-header">
                        <h2 class="teacher-panel-title">Attendance concerns</h2>
                        <span class="teacher-risk-alerts-active-count <?= $teacherRiskRows === [] ? 'is-clear' : 'is-active' ?>">
                            <?= number_format(count($teacherRiskRows)) ?> Active
                        </span>
                    </div>
                    <?php if ($teacherRiskRows === []): ?>
                        <div class="teacher-empty-state teacher-risk-alerts-empty teacher-risk-alerts-teacher-empty" role="status">
                            <i data-lucide="<?= $scopedStudentCount === 0 ? 'users' : ($studentsWithAttendanceEvidence === 0 ? 'info' : 'shield-check') ?>" aria-hidden="true"></i>
                            <?php if ($scopedStudentCount === 0): ?>
                                <h3 class="teacher-empty-state-title">No Students are in your active classes</h3>
                                <p class="teacher-empty-state-text">Students appear here when they are enrolled in your active classes.</p>
                            <?php elseif ($studentsWithAttendanceEvidence === 0): ?>
                                <h3 class="teacher-empty-state-title">Attendance evidence is not available yet</h3>
                                <p class="teacher-empty-state-text">No attendance records are available for Students in your active classes, so attendance concerns cannot yet be assessed.</p>
                            <?php elseif ($studentsWithoutAttendanceEvidence > 0): ?>
                                <h3 class="teacher-empty-state-title">No recorded absence/late concerns in the available records</h3>
                                <p class="teacher-empty-state-text">Attendance records are available for <?= number_format($studentsWithAttendanceEvidence) ?> of <?= number_format($scopedStudentCount) ?> Students in your active classes; concerns cannot yet be assessed for the remaining <?= number_format($studentsWithoutAttendanceEvidence) ?>.</p>
                            <?php else: ?>
                                <h3 class="teacher-empty-state-title">No recorded absence/late concerns</h3>
                                <p class="teacher-empty-state-text">Attendance records are available for all <?= number_format($scopedStudentCount) ?> Students in your active classes.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php if ($studentsWithoutAttendanceEvidence > 0 && $scopedStudentCount > 0): ?>
                            <div class="teacher-risk-evidence-note" role="status">
                                Attendance records are available for <?= number_format($studentsWithAttendanceEvidence) ?> of <?= number_format($scopedStudentCount) ?> Students in your active classes; concerns cannot yet be assessed for the remaining <?= number_format($studentsWithoutAttendanceEvidence) ?>.
                            </div>
                        <?php endif; ?>
                        <div class="adviser-table-shell teacher-risk-alerts-teacher-table-shell">
                            <table class="teacher-table adviser-risk-table teacher-risk-alerts-teacher-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Student</th>
                                        <th scope="col">Attendance concern</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacherVisibleRiskRows as $riskRow): ?>
                                        <?php
                                        $studentId = (int) $riskRow['student_id'];
                                        $student = $riskRow['student'];
                                        $concern = $riskRow['concern'];
                                        $justReferred = is_array($teacherReferralCreatedContext)
                                            && (int) ($teacherReferralCreatedContext['student_id'] ?? 0) === $studentId
                                            && (int) ($teacherReferralCreatedContext['operational_class_id'] ?? 0) === (int) (($concern['contributing_classes'][0]['operational_class_id'] ?? 0));
                                        $leadingClass = $concern['contributing_classes'][0] ?? [];
                                        $tier = (int) ($concern['tier'] ?? 0);
                                        $actionUrl = APP_URL . '/dashboard/teacher_advisory_student.php?scope=teacher&student_id=' . $studentId;
                                        $referralClassId = (int) ($leadingClass['operational_class_id'] ?? 0);
                                        $referralSceId = (int) ($leadingClass['student_class_enrollment_id'] ?? 0);
                                        $quickReferralUrl = $referralClassId > 0
                                            ? APP_URL . '/dashboard/teacher_referrals.php?' . http_build_query([
                                                'new' => 1,
                                                'class_id' => $referralClassId,
                                                'student_class_enrollment_id' => $referralSceId,
                                                'student_id' => $studentId,
                                            ])
                                            : null;
                                        ?>
                                        <tr>
                                            <td data-label="Student">
                                                <strong><?= htmlspecialchars((string) $student['student_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <small><?= htmlspecialchars((string) $student['student_number'], ENT_QUOTES, 'UTF-8') ?></small>
                                            </td>
                                            <td data-label="Attendance concern">
                                                <span class="teacher-risk-alerts-concern-list">
                                                    <span class="teacher-risk-alerts-concern-badge is-absence">Tier <?= $tier ?></span>
                                                    <span><?= htmlspecialchars((string) ($concern['main_reason'] ?? 'Attendance concern'), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php if ($leadingClass !== []): ?>
                                                        <small><?= htmlspecialchars((string) ($leadingClass['subject_name'] ?? 'Class') . ' · ' . (string) ($leadingClass['section_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td data-label="Action"><div class="teacher-action-group"><a class="aprism-action-btn aprism-action-btn-secondary" href="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>">View student</a><?php if ($justReferred): ?><span class="teacher-risk-alerts-referred" role="status">Referral submitted</span><?php elseif ($quickReferralUrl !== null && $referralSceId > 0): ?><a class="aprism-action-btn aprism-action-btn-secondary" href="<?= htmlspecialchars($quickReferralUrl, ENT_QUOTES, 'UTF-8') ?>" data-referral-sheet-open data-source-context="risk_alerts" data-operational-class-id="<?= $referralClassId ?>" data-sce-id="<?= $referralSceId ?>" data-student-id="<?= $studentId ?>" data-source-page="<?= $teacherCurrentPage ?>">Refer</a><?php endif; ?></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($teacherTotalPages > 1): ?>
                            <div class="teacher-pagination teacher-risk-alerts-teacher-pagination">
                                <div class="teacher-pagination-info">
                                    Showing <?= number_format($teacherFirstResult) ?>–<?= number_format($teacherLastResult) ?> of <?= number_format(count($teacherRiskRows)) ?> students
                                </div>
                                <nav class="teacher-pagination-controls" aria-label="Teacher attendance concerns pagination">
                                    <?php if ($teacherCurrentPage > 1): ?>
                                        <a href="<?= htmlspecialchars($teacherRiskPageUrl($teacherCurrentPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link" rel="prev">Previous</a>
                                    <?php else: ?>
                                        <span class="teacher-page-link disabled" aria-disabled="true">Previous</span>
                                    <?php endif; ?>
                                    <?php $lastDisplayedPage = 0; ?>
                                    <?php foreach ($teacherPageNumbers as $page): ?>
                                        <?php if ($lastDisplayedPage > 0 && $page - $lastDisplayedPage > 1): ?>
                                            <span class="teacher-page-ellipsis" aria-hidden="true">…</span>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($teacherRiskPageUrl($page), ENT_QUOTES, 'UTF-8') ?>"
                                            class="teacher-page-link <?= $page === $teacherCurrentPage ? 'active' : '' ?>"
                                            <?= $page === $teacherCurrentPage ? 'aria-current="page"' : '' ?>
                                            aria-label="Page <?= $page ?>"><?= $page ?></a>
                                        <?php $lastDisplayedPage = $page; ?>
                                    <?php endforeach; ?>
                                    <?php if ($teacherCurrentPage < $teacherTotalPages): ?>
                                        <a href="<?= htmlspecialchars($teacherRiskPageUrl($teacherCurrentPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link" rel="next">Next</a>
                                    <?php else: ?>
                                        <span class="teacher-page-link disabled" aria-disabled="true">Next</span>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php if (!$isAdviserScope): ?>
        <?php
        $referralSheetId = 'teacher-referral-sheet';
        $referralClasses = $teacherReferralClasses;
        $referralSheetData = $teacherReferralSheetData;
        $referralSheetInitiallyOpen = $teacherReferralSheetInitiallyOpen;
        $selectedClassId = $teacherReferralSelectedClassId;
        $selectedSceId = $teacherReferralSelectedSceId;
        $selectedReasonCode = $teacherReferralSelectedReasonCode;
        $supportingNote = $teacherReferralSupportingNote;
        $selectedEvidence = $teacherReferralSelectedEvidence;
        $selectedEvidenceToken = $teacherReferralSelectedEvidenceToken;
        $referralSourceContext = 'risk_alerts';
        $referralSourcePage = $teacherCurrentPage;
        $formErrors = $teacherReferralFormErrors;
        $csrfToken = generateCsrfToken();
        require __DIR__ . '/../includes/components/teacher_referral_action_sheet.php';
        ?>
        <div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
        <script>window.APRISM_REFERRAL_FLASH = <?= json_encode($teacherReferralFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
        <script src="<?= APP_URL ?>/assets/js/components/teacher-referral-action-sheet.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/components/teacher-referral-action-sheet.js') ?>"></script>
    <?php endif; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
<?php if ($isAdviserScope): ?>
    <script src="<?= APP_URL ?>/assets/js/pages/teacher-adviser.js"></script>
    <?php if ($adviserActiveConcernCount > 0): ?>
        <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="adviser"></script>
    <?php endif; ?>
<?php elseif (count($teacherRiskRows) > 5): ?>
    <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="standard"></script>
<?php endif; ?>
</body>

</html>
