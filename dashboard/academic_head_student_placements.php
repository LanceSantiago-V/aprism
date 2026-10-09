<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/institutional_registry_helper.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Placement Data Issues';
$activePage = 'students';
$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-registry.css';

function placementPageEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'school_year_id' => filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT) ?: 0,
    'semester' => trim((string) ($_GET['semester'] ?? 'First Semester')),
];
$filters['search'] = function_exists('mb_substr') ? mb_substr($filters['search'], 0, 100) : substr($filters['search'], 0, 100);
if (!in_array($filters['semester'], ['First Semester', 'Second Semester'], true)) {
    $filters['semester'] = 'First Semester';
}

$schoolYears = [];
$programs = [];
$sections = [];
$students = [];
$totalStudents = 0;
$loadError = null;
$pageSize = 10;
$currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

try {
    $schoolYears = $pdo->query("SELECT school_year_id, school_year, status, start_date FROM school_years ORDER BY start_date DESC, school_year_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if ((int) $filters['school_year_id'] === 0) {
        $active = aprismActiveSchoolYear($pdo);
        $filters['school_year_id'] = $active ? (int) $active['school_year_id'] : 0;
    }
    $programs = $pdo->query("SELECT program_id, program_code, program_name, academic_level FROM programs WHERE status = 'Active' ORDER BY academic_level, program_code, program_name")->fetchAll(PDO::FETCH_ASSOC);
    $sections = $pdo->query("SELECT sec.section_id, sec.section_name, sec.program_id, sec.year_level, p.academic_level
        FROM sections sec INNER JOIN programs p ON p.program_id = sec.program_id AND p.status = 'Active'
        WHERE sec.status = 'Active' ORDER BY p.program_code, sec.year_level, sec.section_name")->fetchAll(PDO::FETCH_ASSOC);
    $totalStudents = aprismRegistryUnresolvedStudentsCount($pdo, $filters);
    $totalPages = max(1, (int) ceil($totalStudents / $pageSize));
    $currentPage = min($currentPage, $totalPages);
    $students = aprismRegistryUnresolvedStudentsPage($pdo, $filters, $pageSize, ($currentPage - 1) * $pageSize);
} catch (Throwable $exception) {
    error_log('[APRISM Academic Head Student Placements] ' . $exception::class . ': ' . $exception->getMessage());
    $loadError = 'Unable to load unresolved Student placements.';
    $totalPages = 1;
}

$firstStudent = $totalStudents > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
$lastStudent = $totalStudents > 0 ? min(($currentPage - 1) * $pageSize + count($students), $totalStudents) : 0;
$pageUrl = static function (int $page) use ($filters): string {
    $query = array_filter($filters, static fn (mixed $value): bool => $value !== '' && $value !== 0 && $value !== null);
    $query['page'] = max(1, $page);
    return '?' . http_build_query($query);
};
$review = $_SESSION['aprism_student_placement_reconciliation'] ?? null;
$showReview = is_array($review) && (int) ($_GET['review'] ?? 0) === 1;
$reviewStudents = [];
$reviewLoadError = null;
if ($showReview && is_array($review['student_ids'] ?? null) && $review['student_ids'] !== []) {
    $ids = array_values(array_filter(array_map('intval', $review['student_ids']), static fn (int $id): bool => $id > 0));
    if ($ids !== []) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $pdo->prepare("SELECT student_number, CONCAT_WS(' ', first_name, middle_name, last_name, suffix) AS student_name FROM students WHERE student_id IN ({$marks}) ORDER BY last_name, first_name, student_number");
            $stmt->execute($ids);
            $reviewStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('[APRISM Academic Head Placement Review] ' . $exception::class . ': ' . $exception->getMessage());
            $reviewLoadError = 'Selected Student identities could not be loaded.';
        }
    }
}
$returnQuery = ['search' => $filters['search'], 'school_year_id' => $filters['school_year_id'], 'semester' => $filters['semester'], 'page' => $currentPage];

?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper registry-page placement-page">
            <section class="page-header">
                <div class="page-header-left"><h1 class="page-title">Placement Data Issues</h1></div>
                <div class="page-header-right"><a class="aprism-action-btn aprism-action-btn-primary is-standard" href="<?= APP_URL ?>/dashboard/academic_head_students.php"><i data-lucide="upload" aria-hidden="true"></i><span>Import Placement Data</span></a><a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="<?= APP_URL ?>/dashboard/academic_head_students.php"><i data-lucide="arrow-left" aria-hidden="true"></i><span>Back to Students</span></a></div>
            </section>
            <?php if ($flash['success'] || $flash['error'] || $loadError): ?><div class="registry-feedback <?= ($flash['error'] || $loadError) ? 'is-error' : 'is-success' ?>" role="<?= ($flash['error'] || $loadError) ? 'alert' : 'status' ?>"><?= placementPageEsc($loadError ?: ($flash['error'] ?: $flash['success'])) ?></div><?php endif; ?>

            <?php if ($showReview && is_array($review['placement'] ?? null)): ?>
                <section class="module-card placement-review-card">
                    <div class="registry-section-heading"><div><h2>Review Emergency Placement</h2><span><?= $reviewLoadError !== null ? 'Selected Student identities unavailable' : count($reviewStudents) . ' selected Student' . (count($reviewStudents) === 1 ? '' : 's') ?></span></div></div>
                    <div class="placement-review-grid"><div><span>School Year</span><strong><?= placementPageEsc($review['placement']['school_year'] ?? '—') ?></strong></div><div><span>Context</span><strong><?= placementPageEsc($review['placement']['semester'] ?? '—') ?></strong></div><div><span>Academic Level</span><strong><?= placementPageEsc($review['placement']['academic_level'] ?? '—') ?></strong></div><div><span>Program / Section</span><strong><?= placementPageEsc(trim(($review['placement']['program_code'] ?? '') . ' · ' . ($review['placement']['section_name'] ?? ''), ' ·')) ?></strong></div><div><span>Year / Grade</span><strong><?= placementPageEsc($review['placement']['year_level'] ?? '—') ?></strong></div></div>
                    <?php if ($reviewLoadError !== null): ?><div class="registry-feedback is-error" role="alert"><?= placementPageEsc($reviewLoadError) ?> Refresh the page or cancel this review.</div><?php else: ?><div class="placement-review-students"><?php foreach ($reviewStudents as $student): ?><span><?= placementPageEsc($student['student_number']) ?> · <?= placementPageEsc($student['student_name']) ?></span><?php endforeach; ?></div><?php endif; ?>
                    <div class="registry-form-actions"><?php if ($reviewLoadError === null): ?><form method="post" action="<?= APP_URL ?>/actions/academic_head/reconcile_student_placements.php"><input type="hidden" name="csrf_token" value="<?= placementPageEsc(generateCsrfToken()) ?>"><input type="hidden" name="stage" value="confirm"><input type="hidden" name="review_token" value="<?= placementPageEsc($review['token'] ?? '') ?>"><?php foreach ($returnQuery as $key => $value): ?><input type="hidden" name="<?= placementPageEsc($key) ?>" value="<?= placementPageEsc($value) ?>"><?php endforeach; ?><button type="submit" class="aprism-action-btn aprism-action-btn-primary is-standard"><i data-lucide="check-circle-2" aria-hidden="true"></i><span>Confirm Placement</span></button></form><?php endif; ?><form method="post" action="<?= APP_URL ?>/actions/academic_head/reconcile_student_placements.php"><input type="hidden" name="csrf_token" value="<?= placementPageEsc(generateCsrfToken()) ?>"><input type="hidden" name="stage" value="cancel"><?php foreach ($returnQuery as $key => $value): ?><input type="hidden" name="<?= placementPageEsc($key) ?>" value="<?= placementPageEsc($value) ?>"><?php endforeach; ?><button type="submit" class="aprism-action-btn aprism-action-btn-secondary is-standard">Cancel review</button></form></div>
                </section>
            <?php endif; ?>

            <section class="module-card placement-workspace-card">
                <div class="registry-section-heading"><div><h2>Placement Data Issues</h2><span><?= $loadError !== null ? 'Placement issue data unavailable' : number_format($totalStudents) . ' Student' . ($totalStudents === 1 ? '' : 's') . ' missing a valid placement in the selected context' ?></span></div></div>
                <form method="get" class="registry-filter-toolbar placement-filter-toolbar" role="search"><label class="registry-filter-search"><span>Search</span><input type="search" name="search" value="<?= placementPageEsc($filters['search']) ?>" placeholder="Student number or name"></label><label><span>School Year</span><select name="school_year_id"><?php foreach ($schoolYears as $schoolYear): ?><option value="<?= (int) $schoolYear['school_year_id'] ?>" <?= (int) $filters['school_year_id'] === (int) $schoolYear['school_year_id'] ? 'selected' : '' ?>><?= placementPageEsc($schoolYear['school_year']) ?></option><?php endforeach; ?></select></label><label><span>Teaching Context</span><select name="semester"><option value="First Semester" <?= $filters['semester'] === 'First Semester' ? 'selected' : '' ?>>First Semester</option><option value="Second Semester" <?= $filters['semester'] === 'Second Semester' ? 'selected' : '' ?>>Second Semester</option></select></label><button type="submit" class="aprism-action-btn aprism-action-btn-secondary is-standard">Filter</button></form>

                <details class="placement-emergency-panel">
                    <summary><i data-lucide="wrench" aria-hidden="true"></i><span>Emergency manual resolution</span></summary>
                    <p class="placement-emergency-note">Use only when reviewed placement evidence cannot be supplied through the normal Class List or placement-data workflow.</p>
                    <form method="post" action="<?= APP_URL ?>/actions/academic_head/reconcile_student_placements.php" id="placementForm"><input type="hidden" name="csrf_token" value="<?= placementPageEsc(generateCsrfToken()) ?>"><input type="hidden" name="stage" value="review"><input type="hidden" name="search" value="<?= placementPageEsc($filters['search']) ?>"><input type="hidden" name="school_year_id" value="<?= (int) $filters['school_year_id'] ?>"><input type="hidden" name="semester" value="<?= placementPageEsc($filters['semester']) ?>"><input type="hidden" name="page" value="<?= $currentPage ?>">
                        <div class="placement-form-grid"><label><span>Placement School Year</span><select name="placement_school_year_id" required><?php foreach ($schoolYears as $schoolYear): ?><option value="<?= (int) $schoolYear['school_year_id'] ?>" <?= (int) $filters['school_year_id'] === (int) $schoolYear['school_year_id'] ? 'selected' : '' ?>><?= placementPageEsc($schoolYear['school_year']) ?></option><?php endforeach; ?></select></label><label><span>Teaching Context</span><select name="placement_semester" required><option value="First Semester" <?= $filters['semester'] === 'First Semester' ? 'selected' : '' ?>>First Semester</option><option value="Second Semester" <?= $filters['semester'] === 'Second Semester' ? 'selected' : '' ?>>Second Semester</option></select></label><label><span>Academic Level</span><select name="placement_academic_level" id="placementAcademicLevel" required><option value="">Select level</option><option value="College">College</option><option value="Senior High School">Senior High School</option></select></label><label><span>Program / Strand</span><select name="placement_program_id" id="placementProgram" required><option value="">Select Program</option><?php foreach ($programs as $program): ?><option value="<?= (int) $program['program_id'] ?>" data-academic-level="<?= placementPageEsc($program['academic_level']) ?>"><?= placementPageEsc(($program['program_code'] ? $program['program_code'] . ' · ' : '') . $program['program_name']) ?></option><?php endforeach; ?></select></label><label><span>Year / Grade</span><select name="placement_year_level" id="placementYearLevel" required><option value="">Select year / grade</option></select></label><label><span>Section</span><select name="placement_section_id" id="placementSection" required><option value="">Select Section</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['section_id'] ?>" data-program-id="<?= (int) $section['program_id'] ?>" data-academic-level="<?= placementPageEsc($section['academic_level']) ?>" data-year-level="<?= placementPageEsc($section['year_level']) ?>"><?= placementPageEsc($section['section_name']) ?></option><?php endforeach; ?></select></label></div>
                        <div class="registry-form-actions"><button type="submit" class="aprism-action-btn aprism-action-btn-secondary is-standard"><i data-lucide="clipboard-check" aria-hidden="true"></i><span>Review Emergency Resolution</span></button></div>
                    </form>
                </details>
                <div class="table-wrapper registry-table-wrapper placement-table-wrapper"><table class="module-table registry-table placement-table"><thead><tr><th class="placement-select-cell"><label class="placement-checkbox-target"><input type="checkbox" id="selectAllPlacements" form="placementForm" aria-label="Select all visible placement issues"></label></th><th>Issue</th><th>Student Number</th><th>Student Name</th><th>Participation Evidence</th><th>Next Action</th></tr></thead><tbody><?php if ($loadError !== null): ?><tr><td colspan="6"><div class="registry-empty-state" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><h3>Placement issue data unavailable</h3><p>Records could not be loaded. Refresh the page to try again.</p></div></td></tr><?php elseif ($students === []): ?><tr><td colspan="6"><div class="registry-empty-state"><i data-lucide="circle-check" aria-hidden="true"></i><h3>No placement issues</h3><p>All known active Students have a valid placement in this context.</p></div></td></tr><?php else: ?><?php foreach ($students as $student): ?><?php $issueState = (int) ($student['current_placement_count'] ?? 0) > 1 ? 'Ambiguous' : 'Missing Evidence'; ?><tr><td class="placement-select-cell"><label class="placement-checkbox-target"><input type="checkbox" name="student_ids[]" value="<?= (int) $student['student_id'] ?>" class="placement-student-checkbox" form="placementForm" aria-label="Select <?= placementPageEsc($student['student_number']) ?>"></label></td><td data-label="Issue"><span class="placement-issue-badge"><?= placementPageEsc($issueState) ?></span></td><td class="registry-student-number" data-label="Student Number"><?= placementPageEsc($student['student_number']) ?></td><td data-label="Student Name"><strong><?= placementPageEsc($student['student_name']) ?></strong></td><td data-label="Participation Evidence"><?= placementPageEsc($student['participation_evidence'] ?: 'No class participation evidence in this context') ?></td><td data-label="Next Action"><a class="aprism-action-btn aprism-action-btn-secondary" href="<?= APP_URL ?>/dashboard/academic_head_students.php">Import Placement Data</a></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div>
                    <?php if ($totalStudents > 0): ?><div class="registry-pagination"><span>Showing <?= number_format($firstStudent) ?> to <?= number_format($lastStudent) ?> of <?= number_format($totalStudents) ?> placement issues</span><?php if ($totalPages > 1): ?><nav aria-label="Placement issue pagination"><a class="registry-page-link <?= $currentPage === 1 ? 'disabled' : '' ?>" href="<?= placementPageEsc($pageUrl($currentPage - 1)) ?>" aria-label="Previous page"><i data-lucide="chevron-left"></i></a><?php $startPage = max(1, min($currentPage - 1, $totalPages - 2)); $endPage = min($totalPages, $startPage + 2); for ($page = $startPage; $page <= $endPage; $page++): ?><a class="registry-page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= placementPageEsc($pageUrl($page)) ?>"><?= $page ?></a><?php endfor; ?><a class="registry-page-link <?= $currentPage === $totalPages ? 'disabled' : '' ?>" href="<?= placementPageEsc($pageUrl($currentPage + 1)) ?>" aria-label="Next page"><i data-lucide="chevron-right"></i></a></nav><?php endif; ?></div><?php endif; ?>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script>
        (() => {
            const level = document.getElementById('placementAcademicLevel');
            const program = document.getElementById('placementProgram');
            const year = document.getElementById('placementYearLevel');
            const section = document.getElementById('placementSection');
            if (!level || !program || !year || !section) return;
            const refresh = () => {
                const levelValue = level.value;
                const allowedYears = levelValue === 'Senior High School' ? ['11', '12'] : levelValue === 'College' ? ['1', '2', '3', '4'] : [];
                year.innerHTML = '<option value="">Select year / grade</option>' + allowedYears.map((value) => `<option value="${value}">${value}</option>`).join('');
                Array.from(program.options).forEach((option) => { option.hidden = option.value !== '' && option.dataset.academicLevel !== levelValue; if (option.hidden && option.selected) option.selected = false; });
                Array.from(section.options).forEach((option) => { option.hidden = option.value !== '' && (option.dataset.academicLevel !== levelValue || (program.value && option.dataset.programId !== program.value) || (year.value && option.dataset.yearLevel !== year.value)); if (option.hidden && option.selected) option.selected = false; });
            };
            level.addEventListener('change', refresh); program.addEventListener('change', refresh); year.addEventListener('change', refresh); refresh();
            document.getElementById('selectAllPlacements')?.addEventListener('change', (event) => document.querySelectorAll('.placement-student-checkbox').forEach((box) => { box.checked = event.target.checked; }));
        })();
    </script>
</body>
</html>
