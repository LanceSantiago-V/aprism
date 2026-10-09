<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/institutional_registry_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Sections';
$activePage = 'sections';
$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-registry.css';
$additionalStylesheets = ['assets/css/pages/academic-head-sections-workspace.css'];

function registrySectionEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$levels = ['College', 'Senior High School'];
$semesters = ['First Semester', 'Second Semester'];
$statuses = ['Active', 'Inactive'];
$yearLevels = ['1', '2', '3', '4', '11', '12'];
$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'academic_level' => trim((string) ($_GET['academic_level'] ?? 'All')),
    'program_id' => filter_input(INPUT_GET, 'program_id', FILTER_VALIDATE_INT) ?: 0,
    'year_level' => trim((string) ($_GET['year_level'] ?? 'All')),
    'semester' => trim((string) ($_GET['semester'] ?? 'All')),
    'status' => trim((string) ($_GET['status'] ?? 'All')),
];
$filters['search'] = function_exists('mb_substr') ? mb_substr($filters['search'], 0, 100) : substr($filters['search'], 0, 100);
if (!in_array($filters['academic_level'], array_merge(['All'], $levels), true)) $filters['academic_level'] = 'All';
if (!in_array($filters['year_level'], array_merge(['All'], $yearLevels), true)) $filters['year_level'] = 'All';
if (!in_array($filters['status'], array_merge(['All'], $statuses), true)) $filters['status'] = 'All';
if (!in_array($filters['semester'], array_merge(['All'], $semesters), true)) $filters['semester'] = 'All';

$programs = [];
$sections = [];
$totalSections = 0;
$loadError = null;
$activeSchoolYear = null;
$pageSize = aprismAcademicHeadPageSize('standard');
$currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

try {
    $activeSchoolYear = aprismActiveSchoolYear($pdo);
    $filters['school_year_id'] = $activeSchoolYear ? (int) $activeSchoolYear['school_year_id'] : 0;
    $programs = $pdo->query("SELECT program_id, program_code, program_name, academic_level, status FROM programs ORDER BY academic_level, program_code, program_name")->fetchAll(PDO::FETCH_ASSOC);
    $totalSections = aprismRegistrySectionsCount($pdo, $filters);
    $totalPages = max(1, (int) ceil($totalSections / $pageSize));
    $currentPage = min($currentPage, $totalPages);
    $sections = aprismRegistrySectionsPage($pdo, $filters, $pageSize, ($currentPage - 1) * $pageSize);
} catch (Throwable $exception) {
    error_log('[APRISM Academic Head Sections] ' . $exception::class . ': ' . $exception->getMessage());
    $loadError = 'Unable to load institutional Section records.';
    $totalPages = 1;
}

$firstSection = $totalSections > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
$lastSection = $totalSections > 0 ? min(($currentPage - 1) * $pageSize + count($sections), $totalSections) : 0;
$pageUrl = static function (int $page) use ($filters, $pageSize): string {
    $query = array_filter(array_merge($filters, ['per_page' => $pageSize]), static fn (mixed $value): bool => $value !== '' && $value !== 'All' && $value !== 0 && $value !== null);
    $query['page'] = max(1, $page);
    return '?' . http_build_query($query);
};

?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content sections-page-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper registry-page sections-workspace">
            <section class="page-header">
                <div class="page-header-left"><h1 class="page-title">Sections</h1></div>
                <div class="page-header-right"><a class="aprism-action-btn aprism-action-btn-primary is-standard" href="<?= APP_URL ?>/dashboard/academic_head_students.php"><i data-lucide="upload" aria-hidden="true"></i><span>Import Placement Data</span></a></div>
            </section>
            <?php if ($flash['success'] || $flash['error'] || $loadError): ?><div class="registry-feedback <?= ($flash['error'] || $loadError) ? 'is-error' : 'is-success' ?>" role="<?= ($flash['error'] || $loadError) ? 'alert' : 'status' ?>"><?= registrySectionEsc($loadError ?: ($flash['error'] ?: $flash['success'])) ?></div><?php endif; ?>
            <section class="module-card registry-records-card">
                <form method="get" class="registry-filter-toolbar" role="search">
                    <input type="hidden" name="per_page" value="<?= $pageSize ?>">
                    <label class="registry-filter-search"><span>Search</span><input type="search" name="search" value="<?= registrySectionEsc($filters['search']) ?>" placeholder="Section or Program"></label>
                    <label><span>Academic Level</span><select name="academic_level"><option value="All">All Levels</option><?php foreach ($levels as $level): ?><option value="<?= registrySectionEsc($level) ?>" <?= $filters['academic_level'] === $level ? 'selected' : '' ?>><?= registrySectionEsc($level) ?></option><?php endforeach; ?></select></label>
                    <label><span>Program</span><select name="program_id"><option value="0">All Programs</option><?php foreach ($programs as $program): ?><option value="<?= (int) $program['program_id'] ?>" <?= (int) $filters['program_id'] === (int) $program['program_id'] ? 'selected' : '' ?>><?= registrySectionEsc(($program['program_code'] ? $program['program_code'] . ' · ' : '') . $program['program_name']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Year / Grade</span><select name="year_level"><option value="All">All Years</option><?php foreach ($yearLevels as $year): ?><option value="<?= $year ?>" <?= $filters['year_level'] === $year ? 'selected' : '' ?>><?= $year ?></option><?php endforeach; ?></select></label>
                    <label><span>Context</span><select name="semester"><option value="All">All Contexts</option><?php foreach ($semesters as $semester): ?><option value="<?= registrySectionEsc($semester) ?>" <?= $filters['semester'] === $semester ? 'selected' : '' ?>><?= registrySectionEsc($semester) ?></option><?php endforeach; ?></select></label>
                    <label><span>Status</span><select name="status"><option value="All">All Statuses</option><?php foreach ($statuses as $status): ?><option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label>
                    <div class="registry-filter-actions">
                        <button type="submit" class="aprism-action-btn aprism-action-btn-primary is-standard">Filter</button>
                        <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="?per_page=<?= $pageSize ?>">Reset Filters</a>
                    </div>
                </form>
                <div class="registry-table-heading"><h2>Institutional Section Registry</h2><span><?= $loadError !== null ? 'Records unavailable' : number_format($totalSections) . ' section' . ($totalSections === 1 ? '' : 's') ?></span></div>
                <div class="table-wrapper registry-table-wrapper"><table class="module-table registry-table section-table"><thead><tr><th class="table-row-number" scope="col">#</th><th>Section</th><th>Program / Strand</th><th>Academic Level</th><th>Year / Grade</th><th>Placed Students</th><th>Status</th></tr></thead><tbody>
                    <?php if ($loadError !== null): ?>
                        <tr><td colspan="7"><div class="registry-empty-state" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><h3>Section records unavailable</h3><p>Records could not be loaded. Refresh the page to try again.</p></div></td></tr>
                    <?php elseif ($sections === []): ?>
                        <tr><td colspan="7"><div class="registry-empty-state"><i data-lucide="layers-3" aria-hidden="true"></i><h3>No institutional Sections</h3><p>Import institutional placement data to establish or update Section records.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($sections as $sectionIndex => $section): ?>
                            <tr><td class="table-row-number" data-label="#"><?= $firstSection + $sectionIndex ?></td><td class="registry-primary-cell" data-label="Section"><?= registrySectionEsc($section['section_name']) ?></td><td class="<?= empty($section['program_code']) && empty($section['program_name']) ? 'registry-value-empty' : '' ?>" data-label="Program / Strand"><?= registrySectionEsc(($section['program_code'] ? $section['program_code'] . ' · ' : '') . ($section['program_name'] ?? '—')) ?></td><td class="<?= trim((string) ($section['academic_level'] ?? '')) === '' ? 'registry-value-empty' : '' ?>" data-label="Academic Level"><?= registrySectionEsc($section['academic_level'] ?? '—') ?></td><td class="<?= trim((string) ($section['year_level'] ?? '')) === '' ? 'registry-value-empty' : '' ?>" data-label="Year / Grade"><?= registrySectionEsc($section['year_level'] ?: '—') ?></td><td data-label="Placed Students"><?= number_format((int) $section['active_student_count']) ?></td><td data-label="Status"><span class="registry-status-badge registry-status-<?= strtolower((string) $section['status']) ?>"><?= registrySectionEsc($section['status']) ?></span></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody></table></div>
                <?php if ($totalSections > 0): ?>
                    <div class="registry-pagination"><span>Showing <?= number_format($firstSection) ?> to <?= number_format($lastSection) ?> of <?= number_format($totalSections) ?> Sections</span>
                        <?php if ($totalPages > 1): ?>
                            <?php $startPage = max(1, min($currentPage - 1, $totalPages - 2)); $endPage = min($totalPages, $startPage + 2); ?>
                            <nav aria-label="Institutional Section pagination"><a class="registry-page-link <?= $currentPage === 1 ? 'disabled' : '' ?>" href="<?= registrySectionEsc($pageUrl($currentPage - 1)) ?>" aria-label="Previous page"><i data-lucide="chevron-left"></i></a><?php for ($page = $startPage; $page <= $endPage; $page++): ?><a class="registry-page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= registrySectionEsc($pageUrl($page)) ?>"><?= $page ?></a><?php endfor; ?><a class="registry-page-link <?= $currentPage === $totalPages ? 'disabled' : '' ?>" href="<?= registrySectionEsc($pageUrl($currentPage + 1)) ?>" aria-label="Next page"><i data-lucide="chevron-right"></i></a></nav>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="fixed-six" defer></script>
</body>
</html>
