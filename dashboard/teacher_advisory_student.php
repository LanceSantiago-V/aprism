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
$referralRecordCount = count($referrals); ?>
<!doctype html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>

<body><?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper adviser-page">
            <div class="page-header adviser-page-header">
                <div>
                    <h1 class="teacher-page-title"><?= htmlspecialchars($student['student_name']) ?></h1>
                    <div class="adviser-context-strip" aria-label="Student context">
                        <span class="adviser-context-item"><i data-lucide="badge-check"></i><strong><?= htmlspecialchars($student['student_number']) ?></strong></span>
                        <span class="adviser-context-separator" aria-hidden="true"></span>
                        <span class="adviser-context-item"><i data-lucide="shield-check"></i><strong>Read-only <?= $isCoreScope ? 'teaching' : 'advisory' ?> scope</strong></span>
                    </div>
                    <a class="adviser-detail-back" href="<?= APP_URL ?>/dashboard/<?= $isCoreScope ? 'teacher_students.php' : 'teacher_advisory_students.php' ?>"><i data-lucide="arrow-left" aria-hidden="true"></i> Back to <?= $isCoreScope ? 'My Students' : 'Advisory Students' ?></a>
                </div>
            </div>
            <section class="adviser-detail-summary" aria-label="Student monitoring summary">
                <div class="adviser-detail-summary-item"><small>Teacher referrals</small><strong><?= $referralRecordCount ?></strong></div>
                <div class="adviser-detail-summary-item"><small>Grade records</small><strong><?= $gradeRecordCount ?></strong></div>
            </section>
            <nav class="adviser-detail-jumps" aria-label="Student detail sections">
                <a href="#overview">Context</a>
                <a href="#attendance">Attendance</a>
                <a href="#referrals">Referral history</a>
            </nav>
            <div class="adviser-detail-grid">
                <section class="teacher-panel" id="overview">
                    <div class="teacher-panel-header">
                        <div>
                            <h2 class="teacher-panel-title">Student context</h2>
                        </div>
                    </div>
                    <div class="adviser-detail-meta">
                        <div><small><?= $isCoreScope ? 'Teaching context' : 'Section' ?></small><strong><?= htmlspecialchars($student['section_name']) ?></strong>
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
                <section class="teacher-panel" id="attendance">
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
            <section class="teacher-panel adviser-workspace-panel" id="referrals">
                <div class="teacher-panel-header">
                    <div>
                        <h2 class="teacher-panel-title">Referral history</h2>
                        <p class="teacher-panel-subtitle">Non-confidential Teacher-submitted provenance only.</p>
                    </div>
                </div><?php if (!$referrals): ?>
                    <div class="teacher-empty-state"><i data-lucide="clipboard-plus"></i>
                        <h3 class="teacher-empty-state-title">No referrals in this context</h3>
                        <p class="teacher-empty-state-text">No Teacher-submitted referrals are currently visible for this
                            Student.</p>
                    </div><?php else: ?>
                    <div class="adviser-table-shell">
                        <table class="teacher-table">
                            <thead>
                                <tr>
                                    <th>Concern</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($referrals as $referral): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($referral['reason']) ?></td>
                                        <td><?= htmlspecialchars($referral['created_at']) ?></td>
                                        <td><span class="adviser-status"><?= htmlspecialchars($referral['status']) ?></span></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><?php endif; ?>
            </section>
        </div>
    </main><?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>

</html>
