<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_reports_helper.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Reports';
$activePage = 'reports';
$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-reports.css';

function academicHeadReportsEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function academicHeadReportsStatusClass(string $status): string
{
    return match (strtolower(trim($status))) {
        'active' => 'registry-status-active',
        'inactive' => 'registry-status-inactive',
        'archived' => 'registry-status-archived',
        'missing', 'missing / unresolved' => 'registry-status-missing-unresolved',
        'review', 'incomplete' => 'registry-status-review',
        'ambiguous / conflicting' => 'registry-review-badge',
        default => 'report-status-neutral',
    };
}

$types = aprismAcademicHeadReportTypes();
$reportType = (string) ($_GET['report'] ?? 'placement');
if (!isset($types[$reportType])) {
    $reportType = 'placement';
}

$options = [];
$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'school_year_id' => filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT) ?: 0,
    'semester' => trim((string) ($_GET['semester'] ?? 'All')),
    'academic_level' => trim((string) ($_GET['academic_level'] ?? 'All')),
    'program_id' => filter_input(INPUT_GET, 'program_id', FILTER_VALIDATE_INT) ?: 0,
    'section_id' => filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT) ?: 0,
    'year_level' => trim((string) ($_GET['year_level'] ?? 'All')),
    'teacher_id' => filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT) ?: 0,
    'status' => trim((string) ($_GET['status'] ?? 'All')),
];
$hasExplicitAllSchoolYears = isset($_GET['school_year_id'])
    && is_scalar($_GET['school_year_id'])
    && (string) $_GET['school_year_id'] === '0';
$filters['search'] = function_exists('mb_substr')
    ? mb_substr($filters['search'], 0, 100)
    : substr($filters['search'], 0, 100);

$loadError = null;
$rows = [];
$totalRows = 0;
$pageSize = 6;
$currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

try {
    $options = aprismAcademicHeadReportOptions($pdo);
    $activeSchoolYear = aprismActiveSchoolYear($pdo);

    if ((int) $filters['school_year_id'] === 0 && !$hasExplicitAllSchoolYears && $activeSchoolYear !== null) {
        $filters['school_year_id'] = (int) $activeSchoolYear['school_year_id'];
    }

    $validYearIds = array_map('intval', array_column($options['school_years'], 'school_year_id'));
    if ((int) $filters['school_year_id'] > 0 && !in_array((int) $filters['school_year_id'], $validYearIds, true)) {
        $filters['school_year_id'] = $activeSchoolYear ? (int) $activeSchoolYear['school_year_id'] : 0;
    }
    if ($filters['semester'] !== 'All' && !in_array($filters['semester'], $options['contexts'], true)) {
        $filters['semester'] = 'All';
    }
    if ($filters['academic_level'] !== 'All' && !in_array($filters['academic_level'], $options['levels'], true)) {
        $filters['academic_level'] = 'All';
    }

    $validProgramIds = array_map('intval', array_column($options['programs'], 'program_id'));
    if ((int) $filters['program_id'] > 0 && !in_array((int) $filters['program_id'], $validProgramIds, true)) {
        $filters['program_id'] = 0;
    }
    $validSectionIds = array_map('intval', array_column($options['sections'], 'section_id'));
    if ((int) $filters['section_id'] > 0 && !in_array((int) $filters['section_id'], $validSectionIds, true)) {
        $filters['section_id'] = 0;
    }
    $validTeacherIds = array_map('intval', array_column($options['teachers'], 'user_id'));
    if ((int) $filters['teacher_id'] > 0 && !in_array((int) $filters['teacher_id'], $validTeacherIds, true)) {
        $filters['teacher_id'] = 0;
    }

    $validStatuses = match ($reportType) {
        'placement' => ['All', 'Active', 'Missing', 'Inactive', 'Review', 'Incomplete', 'Archived'],
        'sections' => ['All', 'Active', 'Inactive'],
        'attendance' => array_merge(['All'], array_map('strval', $options['attendance_statuses'])),
        'referrals' => array_merge(['All'], array_map('strval', $options['referral_statuses'])),
        default => ['All'],
    };
    if (!in_array($filters['status'], $validStatuses, true)) {
        $filters['status'] = 'All';
    }

    $totalRows = aprismAcademicHeadReportCount($pdo, $reportType, $filters);
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $currentPage = min($currentPage, $totalPages);

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $columns = aprismAcademicHeadReportColumns($reportType);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="aprism-' . $reportType . '-report.csv"');
        $output = fopen('php://output', 'wb');
        $safeCsvValue = static function ($value): string {
            $value = (string) ($value ?? '');
            return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
        };
        fputcsv($output, array_map($safeCsvValue, array_values($columns)));
        $offset = 0;
        while ($offset < $totalRows) {
            foreach (aprismAcademicHeadReportRows($pdo, $reportType, $filters, 100, $offset) as $row) {
                $values = [];
                foreach (array_keys($columns) as $key) {
                    $values[] = aprismAcademicHeadReportCell($row, $key);
                }
                fputcsv($output, array_map($safeCsvValue, $values));
            }
            $offset += 100;
        }
        fclose($output);
        exit;
    }

    $rows = aprismAcademicHeadReportRows($pdo, $reportType, $filters, $pageSize, ($currentPage - 1) * $pageSize);
} catch (Throwable $exception) {
    error_log('[APRISM Academic Head Reports] ' . $exception::class . ': ' . $exception->getMessage());
    $loadError = 'Unable to load this report right now.';
    $totalPages = 1;
}

$columns = aprismAcademicHeadReportColumns($reportType);
$firstRow = $totalRows > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
$lastRow = $totalRows > 0 ? min(($currentPage - 1) * $pageSize + count($rows), $totalRows) : 0;
$queryFor = static function (int $page, bool $export = false) use ($filters, $reportType, $pageSize, $hasExplicitAllSchoolYears): string {
    $query = array_filter(
        array_merge(['report' => $reportType], $filters, ['per_page' => $pageSize]),
        static fn(mixed $value): bool => $value !== '' && $value !== 'All' && $value !== 0 && $value !== null
    );
    if ($hasExplicitAllSchoolYears) {
        $query['school_year_id'] = 0;
    }
    $query['page'] = max(1, $page);
    if ($export) {
        $query['export'] = 'csv';
    }
    return '?' . http_build_query($query);
};

$statusOptions = match ($reportType) {
    'placement' => ['All', 'Active', 'Missing', 'Inactive', 'Review', 'Incomplete', 'Archived'],
    'sections' => ['All', 'Active', 'Inactive'],
    'attendance' => array_merge(['All'], array_map('strval', $options['attendance_statuses'] ?? [])),
    'referrals' => array_merge(['All'], array_map('strval', $options['referral_statuses'] ?? [])),
    default => ['All'],
};
$showPlacementFilters = $reportType === 'placement';
$showSectionFilters = $reportType === 'sections';
$showClassFilters = in_array($reportType, ['attendance', 'referrals'], true);
$hasReportFilters = $filters['search'] !== ''
    || isset($_GET['school_year_id'])
    || $filters['semester'] !== 'All'
    || (($showPlacementFilters || $showSectionFilters)
        && ($filters['academic_level'] !== 'All' || (int) $filters['program_id'] > 0))
    || (($showPlacementFilters || $showClassFilters) && (int) $filters['section_id'] > 0)
    || ($showSectionFilters && $filters['year_level'] !== 'All')
    || ($showClassFilters && (int) $filters['teacher_id'] > 0)
    || $filters['status'] !== 'All';

?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper academic-head-page reports-page">
            <section class="page-header">
                <div class="page-header-left">
                    <h1 class="page-title">Reports</h1>
                </div>
                <div class="page-header-right">
                    <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="<?= academicHeadReportsEsc($queryFor($currentPage, true)) ?>"><i data-lucide="download" aria-hidden="true"></i><span>Export CSV</span></a>
                </div>
            </section>

            <?php if ($loadError !== null): ?><div class="registry-feedback is-error" role="alert"><?= academicHeadReportsEsc($loadError) ?></div><?php endif; ?>

            <section class="module-card reports-control-card">
                <form method="get" class="reports-filter-toolbar" role="search">
                    <input type="hidden" name="per_page" value="<?= $pageSize ?>">
                    <label class="reports-report-type"><span>Report</span><select name="report" onchange="this.form.submit()"><?php foreach ($types as $value => $label): ?><option value="<?= academicHeadReportsEsc($value) ?>" <?= $reportType === $value ? 'selected' : '' ?>><?= academicHeadReportsEsc($label) ?></option><?php endforeach; ?></select></label>
                    <label class="reports-search"><span>Search</span><input type="search" name="search" value="<?= academicHeadReportsEsc($filters['search']) ?>" placeholder="Search this report"></label>
                    <label class="reports-school-year"><span>School Year</span><select name="school_year_id"><option value="0">All School Years</option><?php foreach ($options['school_years'] as $year): ?><option value="<?= (int) $year['school_year_id'] ?>" <?= (int) $filters['school_year_id'] === (int) $year['school_year_id'] ? 'selected' : '' ?>><?= academicHeadReportsEsc($year['school_year']) ?></option><?php endforeach; ?></select></label>
                    <label class="reports-context"><span>Context</span><select name="semester"><option value="All">All Contexts</option><?php foreach ($options['contexts'] as $context): ?><option value="<?= academicHeadReportsEsc($context) ?>" <?= $filters['semester'] === $context ? 'selected' : '' ?>><?= academicHeadReportsEsc($context) ?></option><?php endforeach; ?></select></label>
                    <?php if ($showPlacementFilters || $showSectionFilters): ?>
                        <label class="reports-academic-level"><span>Academic Level</span><select name="academic_level"><option value="All">All Levels</option><?php foreach ($options['levels'] as $level): ?><option value="<?= academicHeadReportsEsc($level) ?>" <?= $filters['academic_level'] === $level ? 'selected' : '' ?>><?= academicHeadReportsEsc($level) ?></option><?php endforeach; ?></select></label>
                        <label class="reports-program"><span>Program</span><select name="program_id"><option value="0">All Programs</option><?php foreach ($options['programs'] as $program): ?><option value="<?= (int) $program['program_id'] ?>" <?= (int) $filters['program_id'] === (int) $program['program_id'] ? 'selected' : '' ?>><?= academicHeadReportsEsc(trim(($program['program_code'] ?? '') . ' · ' . $program['program_name'], ' ·')) ?></option><?php endforeach; ?></select></label>
                    <?php endif; ?>
                    <?php if ($showPlacementFilters): ?><label class="reports-section"><span>Section</span><select name="section_id"><option value="0">All Sections</option><?php foreach ($options['sections'] as $section): ?><option value="<?= (int) $section['section_id'] ?>" <?= (int) $filters['section_id'] === (int) $section['section_id'] ? 'selected' : '' ?>><?= academicHeadReportsEsc($section['section_name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                    <?php if ($showSectionFilters): ?><label class="reports-section"><span>Year / Grade</span><select name="year_level"><option value="All">All Years</option><?php foreach (['1', '2', '3', '4', '11', '12'] as $yearLevel): ?><option value="<?= $yearLevel ?>" <?= $filters['year_level'] === $yearLevel ? 'selected' : '' ?>><?= $yearLevel ?></option><?php endforeach; ?></select></label><?php endif; ?>
                    <?php if ($showClassFilters): ?>
                        <label class="reports-teacher"><span>Teacher</span><select name="teacher_id"><option value="0">All Teachers</option><?php foreach ($options['teachers'] as $teacher): ?><option value="<?= (int) $teacher['user_id'] ?>" <?= (int) $filters['teacher_id'] === (int) $teacher['user_id'] ? 'selected' : '' ?>><?= academicHeadReportsEsc(trim($teacher['teacher_name'] . ' · ' . $teacher['employee_number'], ' ·')) ?></option><?php endforeach; ?></select></label>
                        <label class="reports-section"><span>Section</span><select name="section_id"><option value="0">All Sections</option><?php foreach ($options['sections'] as $section): ?><option value="<?= (int) $section['section_id'] ?>" <?= (int) $filters['section_id'] === (int) $section['section_id'] ? 'selected' : '' ?>><?= academicHeadReportsEsc($section['section_name']) ?></option><?php endforeach; ?></select></label>
                    <?php endif; ?>
                    <label class="reports-status"><span>Status</span><select name="status"><?php foreach ($statusOptions as $status): ?><option value="<?= academicHeadReportsEsc($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= academicHeadReportsEsc($status === 'All' ? 'All Statuses' : $status) ?></option><?php endforeach; ?></select></label>
                    <div class="reports-filter-actions">
                        <button type="submit" class="aprism-action-btn aprism-action-btn-primary is-standard"><i data-lucide="filter" aria-hidden="true"></i><span>Apply Filters</span></button>
                        <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="<?= APP_URL ?>/dashboard/academic_head_reports.php?<?= academicHeadReportsEsc(http_build_query(['report' => $reportType, 'per_page' => $pageSize])) ?>">Reset</a>
                    </div>
                </form>
            </section>

            <section class="module-card reports-results-card">
                <div class="registry-table-heading"><div><h2><?= academicHeadReportsEsc($types[$reportType]) ?></h2><span><?= $loadError !== null ? 'Records unavailable' : number_format($totalRows) . ' record' . ($totalRows === 1 ? '' : 's') ?></span></div></div>
                <div class="table-wrapper registry-table-wrapper reports-table-wrapper"><table class="module-table registry-table reports-table reports-table--<?= academicHeadReportsEsc($reportType) ?>"><thead><tr><th class="table-row-number" scope="col">#</th><?php if ($reportType === 'placement'): ?><th>Student</th><th>Academic Placement</th><th>Academic Context</th><th>Placement Status</th><?php else: ?><?php foreach ($columns as $label): ?><th><?= academicHeadReportsEsc($label) ?></th><?php endforeach; ?><?php endif; ?></tr></thead><tbody>
                    <?php if ($loadError !== null): ?><tr><td colspan="<?= $reportType === 'placement' ? 5 : count($columns) + 1 ?>"><div class="registry-empty-state" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><h3>Report records unavailable</h3><p>Records could not be loaded. Refresh the page to try again.</p></div></td></tr>
                    <?php elseif ($rows === []): ?><tr><td colspan="<?= $reportType === 'placement' ? 5 : count($columns) + 1 ?>"><div class="registry-empty-state"><i data-lucide="file-search" aria-hidden="true"></i><h3><?= $hasReportFilters ? 'No matching records' : 'No report records' ?></h3><p><?= $hasReportFilters ? 'No records match the selected report filters.' : 'No records are available for this report in the selected context.' ?></p></div></td></tr>
                    <?php elseif ($reportType === 'placement'): ?>
                        <?php foreach ($rows as $rowIndex => $row): ?>
                            <?php
                            $academicLevel = trim((string) ($row['academic_level'] ?? ''));
                            $programLine = aprismAcademicHeadReportCell($row, 'program_name');
                            $yearLevel = trim((string) ($row['year_level'] ?? ''));
                            $sectionName = trim((string) ($row['section_name'] ?? ''));
                            $yearLabel = match ($academicLevel) {
                                'Senior High School' => 'Grade ',
                                'College' => 'Year ',
                                default => '',
                            };
                            $placementTail = array_values(array_filter([
                                $yearLevel !== '' ? $yearLabel . $yearLevel : '',
                                $sectionName,
                            ], static fn (string $value): bool => $value !== ''));
                            $placementDetails = $academicLevel !== '' || $programLine !== '' || $placementTail !== [];
                            $currentPlacementCount = (int) ($row['current_placement_count'] ?? 0);
                            $schoolYear = trim((string) ($row['school_year'] ?? ''));
                            $semester = trim((string) ($row['semester'] ?? ''));
                            $placementStatus = aprismAcademicHeadReportCell($row, 'placement_status');
                            ?>
                            <tr>
                                <td class="table-row-number" data-label="#"><?= $firstRow + $rowIndex ?></td>
                                <td class="report-cell-placement-student" data-label="Student"><strong><?= academicHeadReportsEsc(aprismAcademicHeadReportCell($row, 'student_name')) ?></strong><small><?= academicHeadReportsEsc(aprismAcademicHeadReportCell($row, 'student_number')) ?></small></td>
                                <td class="report-cell-academic-placement" data-label="Academic Placement">
                                    <?php if ($placementDetails): ?>
                                        <div class="report-placement-summary">
                                            <?php if ($academicLevel !== ''): ?><strong><?= academicHeadReportsEsc($academicLevel) ?></strong><?php endif; ?>
                                            <?php if ($programLine !== ''): ?><span><?= academicHeadReportsEsc($programLine) ?></span><?php endif; ?>
                                            <?php if ($placementTail !== []): ?><span><?= academicHeadReportsEsc(implode(' · ', $placementTail)) ?></span><?php endif; ?>
                                        </div>
                                    <?php elseif ($currentPlacementCount > 1): ?>
                                        <span class="report-placement-state">Multiple current placements</span>
                                    <?php else: ?>
                                        <span class="report-placement-state">Placement unresolved</span>
                                    <?php endif; ?>
                                </td>
                                <td class="report-cell-academic-context" data-label="Academic Context">
                                    <?php if ($schoolYear !== '' || $semester !== ''): ?>
                                        <div class="report-academic-context">
                                            <?php if ($schoolYear !== ''): ?><strong><?= academicHeadReportsEsc($schoolYear) ?></strong><?php endif; ?>
                                            <?php if ($semester !== ''): ?><small><?= academicHeadReportsEsc($semester) ?></small><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="report-context-empty">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="report-cell-placement-status" data-label="Placement Status"><span class="registry-status-badge <?= academicHeadReportsStatusClass($placementStatus) ?>"><?= academicHeadReportsEsc($placementStatus !== '' ? $placementStatus : '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($rows as $rowIndex => $row): ?>
                            <tr>
                                <td class="table-row-number" data-label="#"><?= $firstRow + $rowIndex ?></td>
                                <?php foreach ($columns as $key => $label): ?>
                                    <?php $cellValue = aprismAcademicHeadReportCell($row, $key); ?>
                                    <td data-label="<?= academicHeadReportsEsc($label) ?>" class="report-cell-<?= academicHeadReportsEsc(str_replace('_', '-', $key)) ?>">
                                        <?php if (in_array($key, ['status', 'placement_status'], true) && $cellValue !== ''): ?>
                                            <span class="registry-status-badge <?= academicHeadReportsStatusClass($cellValue) ?>"><?= academicHeadReportsEsc($cellValue) ?></span>
                                        <?php else: ?>
                                            <?= academicHeadReportsEsc($cellValue !== '' ? $cellValue : '—') ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody></table></div>
                <?php if ($totalRows > 0 && $totalPages > 1): ?>
                    <div class="registry-pagination">
                        <span>Showing <?= number_format($firstRow) ?> to <?= number_format($lastRow) ?> of <?= number_format($totalRows) ?> records</span>
                        <?php $startPage = max(1, min($currentPage - 1, $totalPages - 2)); ?>
                        <?php $endPage = min($totalPages, $startPage + 2); ?>
                        <nav aria-label="Report pagination">
                            <?php if ($currentPage === 1): ?>
                                <span class="registry-page-link disabled" aria-label="Previous page" aria-disabled="true"><i data-lucide="chevron-left"></i></span>
                            <?php else: ?>
                                <a class="registry-page-link" href="<?= academicHeadReportsEsc($queryFor($currentPage - 1)) ?>" aria-label="Previous page"><i data-lucide="chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                                <a class="registry-page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= academicHeadReportsEsc($queryFor($page)) ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                            <?php endfor; ?>
                            <?php if ($currentPage === $totalPages): ?>
                                <span class="registry-page-link disabled" aria-label="Next page" aria-disabled="true"><i data-lucide="chevron-right"></i></span>
                            <?php else: ?>
                                <a class="registry-page-link" href="<?= academicHeadReportsEsc($queryFor($currentPage + 1)) ?>" aria-label="Next page"><i data-lucide="chevron-right"></i></a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
