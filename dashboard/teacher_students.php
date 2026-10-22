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
$teacherClasses = aprismTeacherCurrentOperationalClasses($pdo, $teacherId, $currentSchoolYear);
$requestedClassId = max(0, (int) ($_GET['class_id'] ?? 0));
$validClassIds = array_map(static fn(array $class): int => (int) $class['operational_class_id'], $teacherClasses);
$classId = in_array($requestedClassId, $validClassIds, true) ? $requestedClassId : 0;
$sortBy = (string) ($_GET['sort'] ?? 'name');
if (!in_array($sortBy, ['name', 'number', 'classes'], true)) {
    $sortBy = 'name';
}

$pageSize = 6;
$totalStudents = aprismTeacherStudentsCount(
    $pdo,
    $teacherId,
    $currentSchoolYear,
    $searchTerm,
    $classId
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
    $searchTerm,
    $classId,
    $sortBy
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

$pageUrl = static function (int $page) use ($searchTerm, $classId, $sortBy): string {
    $query = ['page' => max(1, $page)];
    if ($searchTerm !== '') {
        $query['search'] = $searchTerm;
    }
    if ($classId > 0) {
        $query['class_id'] = $classId;
    }
    if ($sortBy !== 'name') {
        $query['sort'] = $sortBy;
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
                    <form class="teacher-students-controls" method="get" role="search" aria-label="Search and filter Students">
                        <label class="teacher-students-control teacher-students-search-control">
                            <span>Search</span>
                            <span class="teacher-search teacher-students-search">
                                <i data-lucide="search" aria-hidden="true"></i>
                                <input type="search" name="search" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" placeholder="Student Number or name" aria-label="Search by Student Number or name">
                            </span>
                        </label>
                        <label class="teacher-students-control">
                            <span>Class</span>
                            <select name="class_id" aria-label="Filter by class">
                                <option value="0">All classes</option>
                                <?php foreach ($teacherClasses as $class): ?>
                                    <?php $classLabel = trim((string) $class['subject_name'] . ' · ' . (string) $class['section_name']); ?>
                                    <option value="<?= (int) $class['operational_class_id'] ?>" <?= $classId === (int) $class['operational_class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="teacher-students-control">
                            <span>Sort</span>
                            <select name="sort" aria-label="Sort Students">
                                <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name (A–Z)</option>
                                <option value="number" <?= $sortBy === 'number' ? 'selected' : '' ?>>Student Number</option>
                                <option value="classes" <?= $sortBy === 'classes' ? 'selected' : '' ?>>Most classes</option>
                            </select>
                        </label>
                        <button type="submit" class="aprism-action-btn aprism-action-btn-secondary teacher-students-apply">Apply</button>
                    </form>
                </div>

                <div class="teacher-students-table-wrapper">
                    <table class="teacher-table teacher-students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Section</th>
                                <th>Classes with you</th>
                                <th>Attendance status</th>
                                <th>Referrals</th>
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
                                            <?php elseif ($classId > 0): ?>
                                                <p class="teacher-empty-state-text">No Students are enrolled in this class.</p>
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
                                        <td data-label="Section">
                                            <strong><?= htmlspecialchars((string) ($student['sections'] ?? 'Section not recorded'), ENT_QUOTES, 'UTF-8') ?></strong>
                                        </td>
                                        <td data-label="Classes with you">
                                            <strong><?= (int) $student['class_count'] ?> <?= (int) $student['class_count'] === 1 ? 'class' : 'classes' ?></strong>
                                            <small><?= htmlspecialchars($student['teaching_context'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td data-label="Attendance status">
                                            <?php if ($hasAttendanceConcern): ?>
                                                <span class="teacher-student-attendance-concern">
                                                    <i data-lucide="triangle-alert" aria-hidden="true"></i>
                                                    <?= (int) ($attendance['absent_count'] ?? 0) ?> absent · <?= (int) ($attendance['late_count'] ?? 0) ?> late
                                                </span>
                                            <?php else: ?>
                                                <span class="<?= $hasAttendanceEvidence ? 'teacher-student-attendance-clear' : 'teacher-student-attendance-no-records' ?>">
                                                    <i data-lucide="<?= $hasAttendanceEvidence ? 'circle-check' : 'minus-circle' ?>" aria-hidden="true"></i>
                                                    <?= $hasAttendanceEvidence ? 'No recorded concerns' : 'No attendance records' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Referrals" class="teacher-student-count <?= (int) $student['referral_count'] === 0 ? 'is-zero' : '' ?>"><span class="teacher-student-count-value"><?= (int) $student['referral_count'] ?></span></td>
                                        <td data-label="Action">
                                            <a class="aprism-action-btn aprism-action-btn-secondary teacher-student-view" aria-label="View Student record" href="<?= APP_URL ?>/dashboard/teacher_advisory_student.php?scope=teacher&amp;student_id=<?= (int) $student['student_id'] ?>">
                                                <i data-lucide="eye" aria-hidden="true"></i>
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
                                    <a href="<?= htmlspecialchars($pageUrl(1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $currentPage === 1 ? 'disabled' : '' ?>" aria-label="First page"><i data-lucide="chevrons-left" aria-hidden="true"></i></a>
                                    <a href="<?= htmlspecialchars($pageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $currentPage === 1 ? 'disabled' : '' ?>" aria-label="Previous page">
                                        <i data-lucide="chevron-left" aria-hidden="true"></i>
                                    </a>
                                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                                        <a href="<?= htmlspecialchars($pageUrl($page), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $page === $currentPage ? 'active' : '' ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                                    <?php endfor; ?>
                                    <a href="<?= htmlspecialchars($pageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $currentPage === $totalPages ? 'disabled' : '' ?>" aria-label="Next page">
                                        <i data-lucide="chevron-right" aria-hidden="true"></i>
                                    </a>
                                    <a href="<?= htmlspecialchars($pageUrl($totalPages), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link <?= $currentPage === $totalPages ? 'disabled' : '' ?>" aria-label="Last page"><i data-lucide="chevrons-right" aria-hidden="true"></i></a>
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
