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
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$scope = (string) ($_GET['scope'] ?? 'adviser');
$isCoreScope = $scope === 'teacher';
$active = aprismActiveSchoolYear($pdo);
$currentSchoolYear = $active['school_year'] ?? null;
$yearId = $active ? (int) $active['school_year_id'] : null;
$studentId = (int) ($_GET['student_id'] ?? 0);
$student = null;
$attendance = [];
$referrals = [];
$gradeRecordCount = 0;
$teacherClasses = [];
$studentRiskSummary = null;

if ($isCoreScope) {
    $students = aprismTeacherMonitoringStudents($pdo, $teacherId, $currentSchoolYear);
    foreach ($students as $candidate) {
        if ((int) $candidate['student_id'] === $studentId) {
            $student = $candidate;
            break;
        }
    }
    if ($student === null) {
        http_response_code(404);
        exit('Student is outside your teaching scope.');
    }

    $attendanceMap = aprismTeacherMonitoringAttendance($pdo, $teacherId, $currentSchoolYear);
    $attendance = $attendanceMap[$studentId] ?? [];
    $attendance['record_count'] = (int) ($attendance['recorded_count'] ?? 0);
    $attendance = array_merge($attendance, aprismMonitoringAttendanceMetrics($attendance));
    $gradeCounts = aprismTeacherMonitoringGradeCounts($pdo, $teacherId, $currentSchoolYear);
    $gradeRecordCount = (int) ($gradeCounts[$studentId] ?? 0);
    $teacherClasses = aprismTeacherStudentClasses($pdo, $teacherId, $currentSchoolYear, $studentId);
    $riskRows = aprismMonitoringCurrentClassRiskEvidence($pdo, 'teacher', $teacherId, $studentId);
    $riskSummaries = aprismMonitoringStudentRiskSummaries($riskRows);
    $studentRiskSummary = $riskSummaries[$studentId] ?? null;
    $referrals = array_values(array_filter(
        aprismTeacherReferrals($pdo, $teacherId),
        static fn(array $row): bool => (int) ($row['student_id'] ?? 0) === $studentId
            && (string) ($row['school_year_snapshot'] ?? '') === (string) $currentSchoolYear
    ));
    $student['section_name'] = $student['teaching_context'] ?? 'Authorized class context';
    $student['school_year'] = $currentSchoolYear ?? 'No active School Year';
    $student['academic_level'] = 'Teaching scope';
    $student['year_level'] = 'Not applicable';
} else {
    $assignments = aprismAdviserAssignments($pdo, $teacherId, $yearId);
    if (
        !userHasResponsibility(RESPONSIBILITY_ADVISER)
        || $active === null
        || $assignments === []
    ) {
        http_response_code(403);
        exit('Student is outside your Adviser scope.');
    }
    $student = aprismAdviserStudent($pdo, $teacherId, $studentId, $yearId);
    $stmt = $pdo->prepare("SELECT COUNT(CASE WHEN sar.status='Absent' THEN 1 END) absent_count,COUNT(CASE WHEN sar.status='Late' THEN 1 END) late_count,COUNT(*) record_count FROM student_attendance_records sar INNER JOIN student_class_enrollments sce ON sce.student_class_enrollment_id=sar.student_class_enrollment_id INNER JOIN operational_classes oc ON oc.operational_class_id=sce.operational_class_id WHERE sce.student_id=? AND oc.school_year=?");
    $stmt->execute([$studentId, $student['school_year']]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $attendance = array_merge($attendance, aprismMonitoringAttendanceMetrics($attendance));
    $referrals = aprismAdviserReferralFollowups($pdo, $teacherId, $yearId);
    $referrals = array_values(array_filter($referrals, static fn($row) => (int) $row['student_id'] === $studentId));
    $gradeCounts = aprismAdviserMonitoringGradeCounts($pdo, $teacherId, $yearId);
    $gradeRecordCount = (int) ($gradeCounts[$studentId] ?? 0);
}

$pageTitle = $isCoreScope ? 'Student Monitoring' : 'Advisory Student';
$activePage = $isCoreScope ? 'my_students' : 'advisory_students';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/adviser.css';
$referralRecordCount = count($referrals);
$studentNumber = trim((string) ($student['student_number'] ?? ''));
$studentContext = trim((string) ($student['section_name'] ?? ''));
if ($studentContext === '') {
    $studentContext = $isCoreScope ? 'Teaching context not recorded' : 'Section not recorded';
}
$newReferralQuery = ['new' => 1, 'student_id' => $studentId];
if ($isCoreScope && count($teacherClasses) === 1) {
    $newReferralQuery['class_id'] = (int) $teacherClasses[0]['operational_class_id'];
}
$newReferralUrl = APP_URL . '/dashboard/teacher_referrals.php?' . http_build_query($newReferralQuery) . '#new-referral';
?>
<!doctype html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>

<body><?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper adviser-page adviser-student-detail-page <?= $isCoreScope ? 'teacher-student-detail-page' : '' ?>">
            <?php if ($isCoreScope): ?>
            <?php
            $riskLabel = (string) ($studentRiskSummary['tier_label'] ?? 'Insufficient evidence');
            $riskReason = (string) ($studentRiskSummary['main_reason'] ?? 'No eligible attendance evidence is available in this teaching scope.');
            $riskTier = isset($studentRiskSummary['tier']) ? (int) $studentRiskSummary['tier'] : null;
            $riskToneClass = match ($riskTier) {
                3 => 'is-risk-high',
                2 => 'is-risk-medium',
                1 => 'is-risk-low',
                default => 'is-risk-neutral',
            };
            $hasAttendanceConcern = (int) ($attendance['absent_count'] ?? 0) > 0
                || (int) ($attendance['late_count'] ?? 0) > 0;
            ?>
            <div class="teacher-student-back-link">
                <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="<?= APP_URL ?>/dashboard/teacher_students.php"><i data-lucide="arrow-left" aria-hidden="true"></i>Back to My Students</a>
            </div>
            <header class="teacher-student-identity-banner">
                <div class="teacher-student-identity-copy">
                    <h1 class="teacher-page-title"><?= htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="teacher-student-identity-meta">
                        <span><small>Student Number</small><strong><?= htmlspecialchars($studentNumber !== '' ? $studentNumber : 'Not recorded', ENT_QUOTES, 'UTF-8') ?></strong></span>
                        <span><small>Classes with you</small><strong><?= count($teacherClasses) ?> <?= count($teacherClasses) === 1 ? 'class' : 'classes' ?></strong></span>
                    </div>
                </div>
                <a class="aprism-action-btn aprism-action-btn-primary teacher-student-new-referral" href="<?= htmlspecialchars($newReferralUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <i data-lucide="plus" aria-hidden="true"></i>New Referral
                </a>
            </header>

            <section class="teacher-student-summary-grid" aria-label="Student summary">
                <article class="teacher-student-summary-item teacher-student-summary-referrals">
                    <div class="teacher-student-summary-copy"><span>Teacher referrals</span><strong><?= $referralRecordCount ?></strong></div>
                    <span class="teacher-student-summary-icon" aria-hidden="true"><i data-lucide="file-warning"></i></span>
                </article>
                <article class="teacher-student-summary-item teacher-student-summary-grades">
                    <div class="teacher-student-summary-copy"><span>Grade records</span><strong><?= $gradeRecordCount ?></strong></div>
                    <span class="teacher-student-summary-icon" aria-hidden="true"><i data-lucide="table-2"></i></span>
                </article>
                <article class="teacher-student-summary-item teacher-student-summary-attendance <?= $hasAttendanceConcern ? 'is-concern' : 'is-positive' ?>">
                    <div class="teacher-student-summary-copy"><span>Total absences</span><strong><?= (int) ($attendance['absent_count'] ?? 0) ?></strong></div>
                    <span class="teacher-student-summary-icon" aria-hidden="true"><i data-lucide="user-check"></i></span>
                </article>
                <article class="teacher-student-summary-item teacher-student-summary-risk teacher-student-risk-summary <?= $riskToneClass ?>">
                    <div class="teacher-student-summary-copy"><span>Current risk</span><strong><?= htmlspecialchars($riskLabel, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($riskReason, ENT_QUOTES, 'UTF-8') ?></small></div>
                    <span class="teacher-student-summary-icon" aria-hidden="true"><i data-lucide="shield-alert"></i></span>
                </article>
            </section>

            <div class="teacher-student-detail-columns">
                <section class="teacher-panel teacher-student-classes-panel" aria-labelledby="teacher-student-classes-title">
                    <div class="teacher-panel-header"><h2 id="teacher-student-classes-title" class="teacher-panel-title">Classes with You</h2><span class="teacher-student-panel-count"><?= count($teacherClasses) ?></span></div>
                    <?php if ($teacherClasses === []): ?>
                        <div class="teacher-empty-state teacher-student-empty"><h3 class="teacher-empty-state-title">No current classes</h3><p class="teacher-empty-state-text">No active class participation is recorded in your current teaching scope.</p></div>
                    <?php else: ?>
                        <div class="teacher-student-classes-scroll <?= count($teacherClasses) >= 5 ? 'is-scrollable' : '' ?>" role="region" tabindex="0" aria-label="Classes with you">
                            <table class="teacher-student-classes-table">
                                <thead><tr><th scope="col">Class</th><th scope="col">Section</th><th scope="col">School Year · Context</th></tr></thead>
                                <tbody>
                                    <?php foreach ($teacherClasses as $class): ?>
                                        <tr>
                                            <td data-label="Class"><strong><?= htmlspecialchars($class['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                            <td data-label="Section"><?= htmlspecialchars($class['section_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td data-label="School Year · Context"><span><?= htmlspecialchars((string) $class['school_year'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $class['teaching_context'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                <section class="teacher-panel teacher-student-attendance-panel" aria-labelledby="teacher-student-attendance-title">
                    <div class="teacher-panel-header"><h2 id="teacher-student-attendance-title" class="teacher-panel-title">Attendance Summary</h2></div>
                    <dl class="teacher-student-attendance-grid">
                        <div><dt>Records</dt><dd><?= (int) ($attendance['record_count'] ?? 0) ?></dd></div>
                        <div><dt>Absences</dt><dd><?= (int) ($attendance['absent_count'] ?? 0) ?></dd></div>
                        <div><dt>Lates</dt><dd><?= (int) ($attendance['late_count'] ?? 0) ?></dd></div>
                        <div><dt>Effective absences</dt><dd><?= (int) ($attendance['effective_absent_count'] ?? 0) ?></dd></div>
                    </dl>
                </section>
            </div>

            <section class="teacher-panel teacher-student-referral-history" id="referrals" aria-labelledby="teacher-student-referral-title">
                <div class="teacher-panel-header"><div><h2 id="teacher-student-referral-title" class="teacher-panel-title">Referral History</h2><p class="teacher-panel-subtitle">Teacher-submitted referrals visible in your current School Year.</p></div></div>
                <?php if ($referrals === []): ?>
                    <div class="teacher-empty-state teacher-student-empty"><h3 class="teacher-empty-state-title">No referrals in this School Year</h3><p class="teacher-empty-state-text">Teacher-submitted referrals for this Student will appear here.</p></div>
                <?php else: ?>
                    <div class="adviser-table-shell" role="region" tabindex="0" aria-label="Student referral history">
                        <table class="teacher-table adviser-detail-referral-table">
                            <thead><tr><th scope="col">Concern</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                            <tbody><?php foreach ($referrals as $referral): ?>
                                <tr><td data-label="Concern"><?= htmlspecialchars(aprismReferralReasonDisplay($referral['reason_code'] ?? null, $referral['reason'] ?? null), ENT_QUOTES, 'UTF-8') ?></td><td data-label="Submitted"><?= htmlspecialchars($referral['created_at'], ENT_QUOTES, 'UTF-8') ?></td><td data-label="Status"><span class="adviser-status"><?= htmlspecialchars($referral['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td data-label="Action"><a class="referral-row-action" href="<?= APP_URL ?>/dashboard/teacher_referral_detail.php?referral_id=<?= (int) $referral['teacher_referral_id'] ?>#referral-detail-title">View details</a></td></tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php else: ?>
            <div class="page-header adviser-page-header adviser-student-detail-header">
                <div class="adviser-student-detail-heading">
                    <div class="adviser-student-detail-main">
                        <div class="adviser-student-detail-title-row">
                            <h1 class="teacher-page-title"><?= htmlspecialchars($student['student_name']) ?></h1>
                        </div>
                        <div class="adviser-context-strip" role="group" aria-label="Student identity and scope">
                            <span class="adviser-context-item adviser-student-number"><i data-lucide="badge-check" aria-hidden="true"></i><span><small>Student Number</small><strong><?= htmlspecialchars($studentNumber !== '' ? $studentNumber : 'Not recorded', ENT_QUOTES, 'UTF-8') ?></strong></span></span>
                            <span class="adviser-context-item adviser-student-scope"><i data-lucide="shield-check" aria-hidden="true"></i><strong>Read-only <?= $isCoreScope ? 'teaching' : 'advisory' ?> scope</strong></span>
                        </div>
                        <p class="adviser-student-detail-description">Attendance, grades, and non-confidential referral history within your <?= $isCoreScope ? 'teaching' : 'advisory' ?> scope.</p>
                    </div>
                    <section class="adviser-detail-summary" aria-label="Student monitoring summary">
                        <div class="adviser-detail-summary-item"><small>Teacher referrals</small><strong><?= $referralRecordCount ?></strong></div>
                        <div class="adviser-detail-summary-item"><small>Grade records</small><strong><?= $gradeRecordCount ?></strong></div>
                    </section>
                </div>
            </div>
            <nav class="adviser-detail-jumps" aria-label="Student detail sections">
                <a href="#overview">Context</a>
                <a href="#attendance">Attendance</a>
                <a href="#referrals">Referral history</a>
            </nav>
            <div class="adviser-detail-grid">
                <section class="teacher-panel" id="overview" data-aprism-destination-focus>
                    <div class="teacher-panel-header">
                        <div>
                            <h2 class="teacher-panel-title">Student context</h2>
                        </div>
                    </div>
                    <div class="adviser-detail-meta">
                        <div><small><?= $isCoreScope ? 'Teaching context' : 'Section' ?></small><strong><?= htmlspecialchars($studentContext, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div><small>School Year</small><strong><?= htmlspecialchars($student['school_year']) ?></strong>
                        </div>
                        <?php if (!$isCoreScope): ?>
                        <div><small>Academic
                                Level</small><strong><?= htmlspecialchars($student['academic_level']) ?></strong></div>
                        <div><small>Year
                                Level</small><strong><?= htmlspecialchars($student['year_level'] ?? 'Not recorded') ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="teacher-panel" id="attendance" data-aprism-destination-focus>
                    <div class="teacher-panel-header">
                        <div>
                            <h2 class="teacher-panel-title">Attendance</h2>
                        </div>
                    </div>
                    <div class="adviser-detail-meta">
                        <div><small>Records</small><strong><?= (int) ($attendance['record_count'] ?? 0) ?></strong></div>
                        <div><small>Absences</small><strong><?= (int) ($attendance['absent_count'] ?? 0) ?></strong></div>
                        <div><small>Lates</small><strong><?= (int) ($attendance['late_count'] ?? 0) ?></strong></div>
                        <div><small>Effective absences</small><strong><?= (int) ($attendance['effective_absent_count'] ?? 0) ?></strong></div>
                    </div>
                </section>
            </div>
            <section class="teacher-panel adviser-workspace-panel" id="referrals" data-aprism-destination-focus>
                <div class="teacher-panel-header">
                    <div>
                        <h2 class="teacher-panel-title">Referral history</h2>
                        <p class="teacher-panel-subtitle">Non-confidential Teacher-submitted provenance only.</p>
                    </div>
                </div><?php if (!$referrals): ?>
                    <div class="teacher-empty-state adviser-detail-empty"><i data-lucide="clipboard-plus" aria-hidden="true"></i>
                        <div class="adviser-detail-empty-copy">
                            <h3 class="teacher-empty-state-title">No referrals in this context</h3>
                            <p class="teacher-empty-state-text">No Teacher-submitted referrals are currently visible for this Student.</p>
                        </div>
                    </div><?php else: ?>
                    <div class="adviser-table-shell" role="region" tabindex="0" aria-label="Referral records">
                        <table class="teacher-table adviser-detail-referral-table">
                            <thead>
                                <tr>
                                    <th scope="col">Concern</th>
                                    <th scope="col">Submitted</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($referrals as $referral): ?>
                                    <tr>
                                        <td data-label="Concern"><?= htmlspecialchars(aprismReferralReasonDisplay($referral['reason_code'] ?? null, $referral['reason'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Submitted"><?= htmlspecialchars($referral['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Status"><span class="adviser-status"><?= htmlspecialchars($referral['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </main><?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>

</html>
