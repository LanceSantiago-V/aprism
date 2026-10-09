<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/monitoring_helper.php';

$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';

$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$activeSchoolYear = aprismActiveSchoolYear($pdo);
$currentSchoolYear = $activeSchoolYear['school_year'] ?? null;
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$searchTerm = function_exists('mb_substr')
    ? mb_substr($searchTerm, 0, 100)
    : substr($searchTerm, 0, 100);

$pageSize = 6;
$totalStudents = aprismTeacherStudentsCount(
    $pdo,
    $teacherId,
    $currentSchoolYear,
    $searchTerm
);
$totalPages = $totalStudents > 0
    ? (int) ceil($totalStudents / $pageSize)
    : 0;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$currentPage = $totalPages > 0 ? min($currentPage, $totalPages) : 1;
$offset = ($currentPage - 1) * $pageSize;

$students = aprismTeacherStudentsPage(
    $pdo,
    $teacherId,
    $currentSchoolYear,
    $pageSize,
    $offset,
    $searchTerm
);
$attendanceByStudent = aprismTeacherMonitoringAttendance(
    $pdo,
    $teacherId,
    $currentSchoolYear
);
$referralCounts = aprismTeacherMonitoringReferralCounts(
    $pdo,
    $teacherId,
    $currentSchoolYear
);
$gradeCounts = aprismTeacherMonitoringGradeCounts(
    $pdo,
    $teacherId,
    $currentSchoolYear
);

foreach ($students as &$student) {
    $studentId = (int) $student['student_id'];
    $student['attendance'] = $attendanceByStudent[$studentId] ?? [];
    $student['referral_count'] = (int) ($referralCounts[$studentId] ?? 0);
    $student['grade_count'] = (int) ($gradeCounts[$studentId] ?? 0);
}
unset($student);

$firstStudent = $totalStudents > 0 ? $offset + 1 : 0;
$lastStudent = $totalStudents > 0
    ? min($offset + count($students), $totalStudents)
    : 0;

$pageUrl = static function (int $page) use ($searchTerm): string {
    $query = ['page' => max(1, $page)];
    if ($searchTerm !== '') {
        $query['search'] = $searchTerm;
    }

    return '?' . http_build_query($query);
};

$pageTitle = 'My Students';
$activePage = 'my_students';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-students.css';

?>
<!doctype html>
<html lang="en">

<head>
    <?php require __DIR__ . '/../includes/components/head.php'; ?>
</head>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content teacher-students-main <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <div class="content-wrapper teacher-students-page">
            <div class="page-header">
                <div class="page-header-left">
                    <h1 class="teacher-page-title">My Students</h1>
                </div>
            </div>

            <section class="teacher-panel teacher-students-panel">
                <div class="teacher-panel-header teacher-students-header">
                    <div>
                        <h2 class="teacher-panel-title">Students in your classes</h2>
                        <span class="teacher-students-count"><?= number_format($totalStudents) ?> <?= $totalStudents === 1 ? 'Student' : 'Students' ?></span>
                    </div>
                    <form class="teacher-search teacher-students-search" method="get" role="search">
                        <i data-lucide="search" aria-hidden="true"></i>
                        <input type="search" name="search" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" placeholder="Student Number or name" aria-label="Search My Students">
                        <button type="submit" class="visually-hidden">Search Students</button>
                    </form>
                </div>

                <div class="teacher-students-table-wrapper">
                    <table class="teacher-table teacher-students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Classes with you</th>
                                <th>Attendance</th>
                                <th>Referrals</th>
                                <th>Grade records</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students === []): ?>
                                <tr>
                                    <td colspan="6" class="teacher-students-empty-cell">
                                        <div class="teacher-empty-state">
                                            <i data-lucide="users" aria-hidden="true"></i>
                                            <h3 class="teacher-empty-state-title">No Students found</h3>
                                            <?php if ($searchTerm !== ''): ?>
                                                <p class="teacher-empty-state-text">No Students match this search.</p>
                                            <?php else: ?>
                                                <p class="teacher-empty-state-text">Students appear here when they are enrolled in your active classes.</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                    $attendance = $student['attendance'];
                                    $hasAttendanceEvidence = (int) ($attendance['recorded_count'] ?? 0) > 0;
                                    $hasAttendanceConcern = (int) ($attendance['absent_count'] ?? 0) > 0
                                        || (int) ($attendance['late_count'] ?? 0) > 0;
                                    ?>
                                    <tr data-student-row>
                                        <td data-label="Student">
                                            <strong><?= htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small><?= htmlspecialchars($student['student_number'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td data-label="Classes with you">
                                            <strong><?= (int) $student['class_count'] ?> <?= (int) $student['class_count'] === 1 ? 'class' : 'classes' ?></strong>
                                            <small><?= htmlspecialchars($student['teaching_context'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td data-label="Attendance">
                                            <?php if ($hasAttendanceConcern): ?>
                                                <span class="teacher-student-attendance-concern">
                                                    <?= (int) ($attendance['absent_count'] ?? 0) ?> absent · <?= (int) ($attendance['late_count'] ?? 0) ?> late
                                                </span>
                                            <?php else: ?>
                                                <span class="<?= $hasAttendanceEvidence ? 'teacher-student-attendance-clear' : 'teacher-student-attendance-no-records' ?>">
                                                    <?= $hasAttendanceEvidence ? 'No recorded absence/late concerns' : 'No attendance records' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Referrals" class="teacher-student-count <?= (int) $student['referral_count'] === 0 ? 'is-zero' : '' ?>"><span class="teacher-student-count-value"><?= (int) $student['referral_count'] ?></span></td>
                                        <td data-label="Grade records" class="teacher-student-count <?= (int) $student['grade_count'] === 0 ? 'is-zero' : '' ?>"><span class="teacher-student-count-value"><?= (int) $student['grade_count'] ?></span></td>
                                        <td data-label="Action">
                                            <a class="aprism-action-btn aprism-action-btn-secondary" aria-label="View Student record" href="<?= APP_URL ?>/dashboard/teacher_advisory_student.php?scope=teacher&amp;student_id=<?= $student['student_id'] ?>">
                                                <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($totalStudents > 0): ?>
                        <div class="teacher-pagination teacher-students-pagination">
                            <div class="teacher-pagination-info">
                                Showing <?= number_format($firstStudent) ?> to <?= number_format($lastStudent) ?> of <?= number_format($totalStudents) ?> Students
                            </div>
                            <?php if ($totalPages > 1): ?>
                                <?php
                                $startPage = max(1, min($currentPage - 1, $totalPages - 2));
                                $endPage = min($totalPages, $startPage + 2);
                                ?>
                                <nav class="teacher-pagination-controls" aria-label="My Students pagination">
                                    <a href="<?= htmlspecialchars($pageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $currentPage === 1 ? 'disabled' : '' ?>" aria-label="Previous page">
                                        <i data-lucide="chevron-left" aria-hidden="true"></i>
                                    </a>
                                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                                        <a href="<?= htmlspecialchars($pageUrl($page), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $page === $currentPage ? 'active' : '' ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                                    <?php endfor; ?>
                                    <a href="<?= htmlspecialchars($pageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $currentPage === $totalPages ? 'disabled' : '' ?>" aria-label="Next page">
                                        <i data-lucide="chevron-right" aria-hidden="true"></i>
                                    </a>
                                </nav>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>

</html>
