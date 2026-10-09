<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/adviser_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$active = $pdo->query("SELECT school_year_id,school_year FROM school_years WHERE status='Active' ORDER BY school_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
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

$searchTerm = trim((string) ($_GET['search'] ?? ''));
$pageSize = aprismAcademicHeadPageSize('adviser');
$totalStudents = aprismAdviserStudentsCount($pdo, $teacherId, $yearId, $searchTerm);
$totalPages = $totalStudents > 0 ? (int) ceil($totalStudents / $pageSize) : 0;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$currentPage = $totalPages > 0 ? min($currentPage, $totalPages) : 1;
$offset = ($currentPage - 1) * $pageSize;
$students = aprismAdviserStudentsPage($pdo, $teacherId, $yearId, $pageSize, $offset, $searchTerm);
$firstStudent = $totalStudents > 0 ? $offset + 1 : 0;
$lastStudent = $totalStudents > 0 ? min($offset + count($students), $totalStudents) : 0;
$pageNumbers = [];
if ($totalPages > 0) {
    $pageNumbers = $totalPages <= 7
        ? range(1, $totalPages)
        : array_values(array_unique(array_filter(
            [1, $currentPage - 1, $currentPage, $currentPage + 1, $totalPages],
            static fn (int $page): bool => $page >= 1 && $page <= $totalPages
        )));
    sort($pageNumbers, SORT_NUMERIC);
}
$pageUrl = static function (int $page) use ($searchTerm, $pageSize): string {
    $query = ['page' => max(1, $page), 'per_page' => $pageSize];
    if ($searchTerm !== '') {
        $query['search'] = $searchTerm;
    }
    return '?' . http_build_query($query);
};
$pageTitle = 'Advisory Students';
$activePage = 'advisory_students';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/adviser.css'; ?><!doctype html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>

<body><?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content adviser-students-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper adviser-page adviser-students-page">
            <div class="page-header adviser-page-header">
                <div>
                    <h1 class="teacher-page-title">Advisory Students</h1>
                    <div class="adviser-students-context" aria-label="Current advisory class and academic context">
                        <?php foreach ($advisoryContextParts as $index => $contextPart): ?>
                            <?php if ($index > 0): ?><span class="adviser-students-context-separator" aria-hidden="true">·</span><?php endif; ?>
                            <span><?= htmlspecialchars($contextPart, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <section class="teacher-panel adviser-workspace-panel adviser-students-workspace-panel">
                <div class="adviser-workspace-header">
                    <h2 class="teacher-panel-title">Students</h2>
                    <span class="registry-result-count"><?= number_format($totalStudents) ?> <?= $totalStudents === 1 ? 'Student' : 'Students' ?></span>
                </div>
                <form class="teacher-search adviser-students-search" method="get"
                    action="<?= APP_URL ?>/dashboard/teacher_advisory_students.php" role="search">
                    <label class="visually-hidden" for="adviserStudentSearch">Search by student number or name</label>
                    <i data-lucide="search" aria-hidden="true"></i>
                    <input id="adviserStudentSearch" type="search" name="search"
                        value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Search by student number or name" data-adviser-server-search>
                    <input type="hidden" name="per_page" value="<?= $pageSize ?>">
                    <button type="submit" class="visually-hidden">Search</button>
                </form>
                <?php if ($totalStudents === 0): ?>
                    <div class="adviser-table-shell adviser-empty-table-shell">
                        <div class="teacher-empty-state adviser-empty-state">
                            <i data-lucide="<?= $searchTerm !== '' ? 'search-x' : 'users' ?>" aria-hidden="true"></i>
                            <?php if ($searchTerm !== ''): ?>
                                <h3 class="teacher-empty-state-title">No students match your search</h3>
                                <p class="teacher-empty-state-text">Try a different student number or name.</p>
                            <?php else: ?>
                                <h3 class="teacher-empty-state-title">No students in your advisory scope</h3>
                                <p class="teacher-empty-state-text">There are no active student placements in this scope.</p>
                            <?php endif; ?>
                        </div>
                    </div><?php else: ?>
                    <div class="adviser-table-shell adviser-students-table-shell">
                        <table class="teacher-table adviser-students-table" data-adviser-student-table>
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Student</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($students as $student): ?>
                                    <tr data-adviser-student-row>
                                        <td data-label="Student Number"><?= htmlspecialchars($student['student_number']) ?></td>
                                        <td data-label="Student"><?= htmlspecialchars($student['student_name']) ?></td>
                                        <td data-label="Action"><a class="aprism-action-btn aprism-action-btn-secondary"
                                                href="<?= APP_URL ?>/dashboard/teacher_advisory_student.php?student_id=<?= (int) $student['student_id'] ?>">View student</a></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><?php endif; ?>
                <?php if ($totalPages > 1): ?>
                    <div class="teacher-pagination adviser-students-pagination">
                        <div class="teacher-pagination-info">
                            Showing <?= number_format($firstStudent) ?>–<?= number_format($lastStudent) ?> of <?= number_format($totalStudents) ?> students
                        </div>
                        <nav class="teacher-pagination-controls" aria-label="Advisory Students pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="<?= htmlspecialchars($pageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link" rel="prev">Previous</a>
                            <?php else: ?>
                                <span class="teacher-page-link disabled" aria-disabled="true">Previous</span>
                            <?php endif; ?>
                            <?php $lastDisplayedPage = 0; ?>
                            <?php foreach ($pageNumbers as $page): ?>
                                <?php if ($lastDisplayedPage > 0 && $page - $lastDisplayedPage > 1): ?>
                                    <span class="teacher-page-ellipsis" aria-hidden="true">…</span>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($pageUrl($page), ENT_QUOTES, 'UTF-8') ?>"
                                    class="teacher-page-link <?= $page === $currentPage ? 'active' : '' ?>"
                                    <?= $page === $currentPage ? 'aria-current="page"' : '' ?>
                                    aria-label="Page <?= $page ?>"><?= $page ?></a>
                                <?php $lastDisplayedPage = $page; ?>
                            <?php endforeach; ?>
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?= htmlspecialchars($pageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="teacher-page-link" rel="next">Next</a>
                            <?php else: ?>
                                <span class="teacher-page-link disabled" aria-disabled="true">Next</span>
                            <?php endif; ?>
                            </nav>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main><?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/pages/teacher-adviser.js"></script>
    <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="adviser"></script>
</body>

</html>
