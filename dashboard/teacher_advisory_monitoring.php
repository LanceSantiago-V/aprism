<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/adviser_helper.php';

$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';

$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$active = aprismActiveSchoolYear($pdo);
$currentSchoolYear = $active['school_year'] ?? null;
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

$students = aprismAdviserStudents($pdo, $teacherId, $yearId);
$concerns = aprismAdviserAttendanceConcerns($pdo, $teacherId, $yearId);
$attendanceEvidenceByStudent = aprismAdviserAttendanceEvidenceCounts($pdo, $teacherId, $yearId);
$followups = aprismAdviserReferralFollowups($pdo, $teacherId, $yearId);
$gradeCounts = aprismAdviserMonitoringGradeCounts($pdo, $teacherId, $yearId);
$advisoryStudentIds = array_values(array_unique(array_map(
    static fn(array $student): int => (int) $student['student_id'],
    $students
)));
$advisoryStudentCount = count($advisoryStudentIds);
$studentsWithAttendanceEvidence = count(array_filter(
    $advisoryStudentIds,
    static fn(int $studentId): bool => (int) ($attendanceEvidenceByStudent[$studentId] ?? 0) > 0
));
$studentsWithoutAttendanceEvidence = max(0, $advisoryStudentCount - $studentsWithAttendanceEvidence);
$attendanceEvidenceComplete = $advisoryStudentCount > 0 && $studentsWithoutAttendanceEvidence === 0;
$assignedSections = implode(', ', array_column($assignments, 'section_name'));
$gradeRecordTotal = array_sum(array_map('intval', $gradeCounts));
$concernsByStudent = [];
foreach ($concerns as $concern) {
    $concernsByStudent[(int) $concern['student_id']] = $concern;
}

$pageTitle = 'Advisory Monitoring';
$activePage = 'advisory_monitoring';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/adviser.css';

?>
<!doctype html>
<html lang="en">

<head>
    <?php require __DIR__ . '/../includes/components/head.php'; ?>
</head>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper adviser-page adviser-monitoring-page">
            <div class="page-header adviser-page-header">
                <div>
                    <h1 class="teacher-page-title">Advisory Monitoring</h1>
                    <div class="adviser-context-strip" aria-label="Advisory scope">
                        <span class="adviser-context-item"><i data-lucide="calendar"></i><strong><?= htmlspecialchars((string) ($active['school_year'] ?? 'No active School Year')) ?></strong></span>
                        <span class="adviser-context-separator" aria-hidden="true"></span>
                        <span class="adviser-context-item"><i data-lucide="layers-2"></i><strong><?= htmlspecialchars($assignedSections !== '' ? $assignedSections : 'Assigned Section scope') ?></strong></span>
                    </div>
                </div>
            </div>

            <section class="adviser-stat-grid" aria-label="Advisory monitoring summary">
                <article class="adviser-stat-card">
                    <div class="adviser-stat-icon">
                        <i data-lucide="users"></i>
                    </div>
                    <div class="adviser-stat-copy">
                        <span class="adviser-stat-label">Advisory Students</span>
                        <strong class="adviser-stat-value"><?= count($students) ?></strong>
                    </div>
                </article>
                <article class="adviser-stat-card">
                    <div class="adviser-stat-icon <?= $concerns !== []
                        ? 'adviser-stat-icon-warning'
                        : ($attendanceEvidenceComplete ? 'adviser-stat-icon-success' : 'adviser-stat-icon-academic') ?>">
                        <i data-lucide="triangle-alert"></i>
                    </div>
                    <div class="adviser-stat-copy">
                        <span class="adviser-stat-label">Attendance Concerns</span>
                        <strong class="adviser-stat-value"><?= count($concerns) ?></strong>
                    </div>
                </article>
                <article class="adviser-stat-card">
                    <div class="adviser-stat-icon <?= $followups === [] ? 'adviser-stat-icon-success' : 'adviser-stat-icon-warning' ?>">
                        <i data-lucide="clipboard-check"></i>
                    </div>
                    <div class="adviser-stat-copy">
                        <span class="adviser-stat-label">Referral Follow-ups</span>
                        <strong class="adviser-stat-value"><?= count($followups) ?></strong>
                    </div>
                </article>
                <article class="adviser-stat-card">
                    <div class="adviser-stat-icon adviser-stat-icon-academic">
                        <i data-lucide="file-check-2"></i>
                    </div>
                    <div class="adviser-stat-copy">
                        <span class="adviser-stat-label">Confirmed Grade Records</span>
                        <strong class="adviser-stat-value"><?= $gradeRecordTotal ?></strong>
                    </div>
                </article>
            </section>

            <section class="teacher-panel adviser-workspace-panel">
                <div class="adviser-workspace-header">
                    <div>
                        <h2 class="teacher-panel-title">Assigned advisory scope</h2>
                    </div>
                    <a class="aprism-action-btn aprism-action-btn-primary is-standard"
                        href="<?= APP_URL ?>/dashboard/teacher_advisory_students.php">
                        <i data-lucide="users"></i>
                        View Student
                    </a>
                </div>

                <?php if (!$students): ?>
                    <div class="adviser-table-shell adviser-empty-table-shell">
                        <div class="teacher-empty-state adviser-empty-state">
                            <i data-lucide="users"></i>
                            <h3 class="teacher-empty-state-title">No advisory Students in this context</h3>
                            <p class="teacher-empty-state-text">
                                Students appear here when active academic placement records match the assigned Section and
                                School Year.
                            </p>
                        </div>
                    </div>
                <?php elseif ($concerns === [] && $followups === []): ?>
                    <div class="adviser-monitoring-calm-state" role="status">
                        <span class="adviser-monitoring-calm-icon" aria-hidden="true"><i data-lucide="info"></i></span>
                        <div class="adviser-monitoring-calm-copy">
                            <?php if ($studentsWithAttendanceEvidence === 0): ?>
                                <h3>No attendance records are available for your assigned Students yet.</h3>
                                <p>Attendance concerns cannot be assessed until records are available. No referral follow-ups are open at this time.</p>
                            <?php elseif ($studentsWithoutAttendanceEvidence > 0): ?>
                                <h3>No recorded absence/late concerns in the available records.</h3>
                                <p>Attendance records are available for <?= $studentsWithAttendanceEvidence ?> of <?= $advisoryStudentCount ?> assigned Students; evidence is not yet available for <?= $studentsWithoutAttendanceEvidence ?>. No referral follow-ups are open at this time.</p>
                            <?php else: ?>
                                <h3>No recorded absence/late concerns.</h3>
                                <p>Attendance records are available for all <?= $advisoryStudentCount ?> assigned Students. No referral follow-ups are open at this time.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="adviser-filter-bar">
                        <span class="adviser-filter-summary"><strong><?= count($students) ?></strong> Students in your advisory scope</span>
                        <form class="teacher-search" onsubmit="return false">
                            <i data-lucide="search"></i>
                            <input type="search" aria-label="Search advisory students" placeholder="Student number or name" data-adviser-search>
                        </form>
                    </div>
                    <?php if ($studentsWithoutAttendanceEvidence > 0): ?>
                        <div class="adviser-monitoring-evidence-note" role="status">
                            Attendance records are available for <?= $studentsWithAttendanceEvidence ?> of <?= $advisoryStudentCount ?> assigned Students; the remaining <?= $studentsWithoutAttendanceEvidence ?> do not yet have recorded attendance evidence.
                        </div>
                    <?php endif; ?>
                    <div class="adviser-table-shell adviser-monitoring-table-shell">
                        <table class="teacher-table adviser-monitoring-table">
                            <caption class="visually-hidden">Attendance, referral, and confirmed grade record counts for students in your assigned advisory scope</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Student</th>
                                    <th scope="col">Section</th>
                                    <th scope="col">Absences</th>
                                    <th scope="col">Lates</th>
                                    <th scope="col">Effective absences</th>
                                    <th scope="col">Referrals</th>
                                    <th scope="col">Confirmed grade records</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                    $studentConcern = $concernsByStudent[(int) $student['student_id']] ?? null;
                                    $hasAttendanceEvidence = (int) ($attendanceEvidenceByStudent[(int) $student['student_id']] ?? 0) > 0;
                                    $studentReferrals = array_filter(
                                        $followups,
                                        static fn(array $row): bool => (int) $row['student_id'] === (int) $student['student_id']
                                    );
                                    ?>
                                    <tr data-adviser-monitoring-row>
                                        <td data-label="Student">
                                            <strong><?= htmlspecialchars($student['student_name']) ?></strong>
                                            <small><?= htmlspecialchars($student['student_number']) ?></small>
                                        </td>
                                        <td data-label="Section"><?= htmlspecialchars($student['section_name']) ?></td>
                                        <td data-label="Absences"><?= $hasAttendanceEvidence ? (int) ($studentConcern['absent_count'] ?? 0) : '<span class="adviser-no-attendance-evidence">No records</span>' ?></td>
                                        <td data-label="Lates"><?= $hasAttendanceEvidence ? (int) ($studentConcern['late_count'] ?? 0) : '<span class="adviser-no-attendance-evidence">—</span>' ?></td>
                                        <td data-label="Effective absences"><?= $hasAttendanceEvidence ? (int) ($studentConcern['effective_absent_count'] ?? 0) : '<span class="adviser-no-attendance-evidence">—</span>' ?></td>
                                        <td data-label="Referrals"><?= count($studentReferrals) ?></td>
                                        <td data-label="Confirmed grade records"><?= (int) ($gradeCounts[(int) $student['student_id']] ?? 0) ?></td>
                                        <td data-label="Action">
                                            <a class="aprism-action-btn aprism-action-btn-secondary"
                                                aria-label="View <?= htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') ?> advisory details"
                                                href="<?= APP_URL ?>/dashboard/teacher_advisory_student.php?student_id=<?= (int) $student['student_id'] ?>">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/pages/teacher-adviser.js"></script>
</body>

</html>
