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
require_once __DIR__ . '/../includes/helper/institutional_registry_helper.php';
require_once __DIR__ . '/../includes/import/institutional_registry_import_engine.php';
require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Students';
$activePage = 'students';
$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-registry.css';
$additionalStylesheets = ['assets/css/pages/academic-head-students-layout.css'];

function registryPageEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$levels = ['College', 'Senior High School'];
$semesters = ['First Semester', 'Second Semester'];
$saeStatuses = ['Active', 'Inactive', 'Review', 'Incomplete', 'Archived', 'Missing'];
$schoolYears = [];
$programs = [];
$sections = [];
$students = [];
$totalStudents = 0;
$loadError = null;

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'academic_level' => trim((string) ($_GET['academic_level'] ?? 'All')),
    'program_id' => filter_input(INPUT_GET, 'program_id', FILTER_VALIDATE_INT) ?: 0,
    'section_id' => filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT) ?: 0,
    'school_year_id' => filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT) ?: 0,
    'semester' => trim((string) ($_GET['semester'] ?? 'All')),
    'status' => trim((string) ($_GET['status'] ?? 'All')),
];
$hasExplicitAllSchoolYears = isset($_GET['school_year_id'])
    && is_scalar($_GET['school_year_id'])
    && (string) $_GET['school_year_id'] === '0';
$filters['search'] = function_exists('mb_substr') ? mb_substr($filters['search'], 0, 100) : substr($filters['search'], 0, 100);
if (!in_array($filters['academic_level'], array_merge(['All'], $levels), true)) {
    $filters['academic_level'] = 'All';
}
if (!in_array($filters['status'], array_merge(['All'], $saeStatuses), true)) {
    $filters['status'] = 'All';
}
if (!in_array($filters['semester'], array_merge(['All'], $semesters), true)) {
    $filters['semester'] = 'All';
}

$pageSize = 6;
$currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

try {
    $schoolYears = $pdo->query("SELECT school_year_id, school_year, status FROM school_years ORDER BY start_date DESC, school_year_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if ((int) $filters['school_year_id'] === 0 && !$hasExplicitAllSchoolYears) {
        foreach ($schoolYears as $schoolYear) {
            if (($schoolYear['status'] ?? '') === 'Active') {
                $filters['school_year_id'] = (int) $schoolYear['school_year_id'];
                break;
            }
        }
    }
    $programs = $pdo->query("SELECT program_id, program_code, program_name, academic_level, status FROM programs ORDER BY academic_level, program_code, program_name")->fetchAll(PDO::FETCH_ASSOC);
    $sections = $pdo->query("SELECT section_id, section_name, program_id, year_level, status FROM sections ORDER BY section_name, section_id")->fetchAll(PDO::FETCH_ASSOC);
    $totalStudents = aprismRegistryStudentsCount($pdo, $filters);
    $totalPages = max(1, (int) ceil($totalStudents / $pageSize));
    $currentPage = min($currentPage, $totalPages);
    $students = aprismRegistryStudentsPage($pdo, $filters, $pageSize, ($currentPage - 1) * $pageSize);
} catch (Throwable $exception) {
    error_log('[APRISM Academic Head Students] ' . $exception::class . ': ' . $exception->getMessage());
    $loadError = 'Unable to load institutional Student records.';
    $totalPages = 1;
}

$firstStudent = $totalStudents > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
$lastStudent = $totalStudents > 0 ? min(($currentPage - 1) * $pageSize + count($students), $totalStudents) : 0;
$pageUrl = static function (int $page) use ($filters, $pageSize, $hasExplicitAllSchoolYears): string {
    $query = array_filter(array_merge($filters, ['per_page' => $pageSize]), static fn (mixed $value): bool => $value !== '' && $value !== 'All' && $value !== 0 && $value !== null);
    if ($hasExplicitAllSchoolYears) {
        $query['school_year_id'] = 0;
    }
    $query['page'] = max(1, $page);
    return '?' . http_build_query($query);
};
$hasStudentFilters = $filters['search'] !== ''
    || $filters['academic_level'] !== 'All'
    || (int) $filters['program_id'] > 0
    || (int) $filters['section_id'] > 0
    || (isset($_GET['school_year_id']) && (int) $_GET['school_year_id'] > 0)
    || $filters['semester'] !== 'All'
    || $filters['status'] !== 'All';

$importState = $_SESSION['aprism_institutional_registry_import'] ?? null;
$showImportWorkspace = is_array($importState);
$importStage = is_array($importState) ? (string) ($importState['stage'] ?? 'mapping') : '';
$importErrors = is_array($importState) && is_array($importState['errors'] ?? null) ? $importState['errors'] : [];
$canonicalFields = InstitutionalRegistryImportEngine::canonicalFields();
$requiredFields = ['student_number', 'first_name', 'last_name', 'academic_level', 'program_name', 'section_name', 'year_level', 'school_year'];
$importHeaders = is_array($importState['source']['headers'] ?? null) ? $importState['source']['headers'] : [];
$importSourceRows = is_array($importState['source']['rows'] ?? null) ? $importState['source']['rows'] : [];
$importMapping = is_array($importState['mapping'] ?? null) ? $importState['mapping'] : [];
$importRows = is_array($importState['resolved'] ?? null) ? $importState['resolved'] : [];

?>
<!doctype html>
<html lang="en">
<head>
    <?php require __DIR__ . '/../includes/components/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content students-page-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper registry-page students-workspace">
            <section class="page-header">
                <div class="page-header-left"><h1 class="page-title">Students</h1></div>
                <div class="page-header-right">
                    <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="<?= APP_URL ?>/dashboard/academic_head_student_placements.php?school_year_id=<?= (int) $filters['school_year_id'] ?>&semester=First+Semester"><i data-lucide="clipboard-check" aria-hidden="true"></i><span>Placement Data Issues</span></a>
                    <button type="button" class="aprism-action-btn aprism-action-btn-primary is-standard" data-bs-toggle="modal" data-bs-target="#registryImportModal"><i data-lucide="upload" aria-hidden="true"></i><span>Import Placement Data</span></button>
                </div>
            </section>

            <?php if ($flash['success'] || $flash['error'] || $loadError): ?>
                <div class="registry-feedback <?= ($flash['error'] || $loadError) ? 'is-error' : 'is-success' ?>" role="status"><?= registryPageEsc($loadError ?: ($flash['error'] ?: $flash['success'])) ?></div>
            <?php endif; ?>

            <?php if ($showImportWorkspace): ?>
                <section class="module-card registry-import-card">
                    <div class="registry-section-heading">
                        <div><h2>Student Placement Import</h2><span><?= registryPageEsc($importState['source']['source']['original_name'] ?? 'Staged source') ?></span></div>
                        <form method="post" action="<?= APP_URL ?>/actions/academic_head/import_institutional_registry.php">
                            <input type="hidden" name="csrf_token" value="<?= registryPageEsc(generateCsrfToken()) ?>">
                            <input type="hidden" name="stage" value="cancel">
                            <input type="hidden" name="import_token" value="<?= registryPageEsc($importState['token'] ?? '') ?>">
                            <button type="submit" class="aprism-action-btn aprism-action-btn-secondary is-small">Cancel import</button>
                        </form>
                    </div>
                    <ol class="registry-stepper" aria-label="Placement import progress">
                        <?php foreach (['Upload', 'Source Preview', 'Mapping', 'Review / Resolve', 'Validation', 'Confirmation'] as $stepIndex => $stepLabel): ?>
                            <li class="<?= $stepIndex <= ($importStage === 'review' ? 4 : 2) ? 'is-complete' : '' ?> <?= ($importStage === 'mapping' && $stepIndex === 2) || ($importStage === 'review' && $stepIndex === 3) ? 'is-current' : '' ?>"><span><?= $stepIndex + 1 ?></span><?= registryPageEsc($stepLabel) ?></li>
                        <?php endforeach; ?>
                    </ol>

                    <?php if ($importStage === 'mapping'): ?>
                        <div class="registry-source-preview">
                            <h3>Source Preview</h3>
                            <div class="table-wrapper registry-preview-wrapper"><table class="module-table registry-preview-table"><thead><tr><?php foreach (array_slice($importHeaders, 0, 8) as $header): ?><th><?= registryPageEsc($header['label']) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach (array_slice($importSourceRows, 0, 5) as $sourceRow): ?><tr><?php foreach (array_slice($importHeaders, 0, 8) as $header): ?><td><?= registryPageEsc($sourceRow['values'][$header['column']] ?? '') ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
                            <?php if (count($importSourceRows) > 5): ?><p class="registry-preview-note">Showing the first 5 of <?= count($importSourceRows) ?> source rows.</p><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($importErrors !== []): ?>
                        <div class="registry-import-errors" role="alert"><strong>Review required.</strong><ul><?php foreach (array_slice($importErrors, 0, 12) as $error): ?><li><?= registryPageEsc($error) ?></li><?php endforeach; ?></ul><?php if (count($importErrors) > 12): ?><span>and <?= count($importErrors) - 12 ?> more row issue(s).</span><?php endif; ?></div>
                    <?php endif; ?>

                    <?php if ($importStage === 'mapping'): ?>
                        <p class="registry-required-note">Map the explicit placement columns. APRISM will not infer missing academic placement values.</p>
                        <form method="post" action="<?= APP_URL ?>/actions/academic_head/import_institutional_registry.php" class="registry-mapping-form">
                            <input type="hidden" name="csrf_token" value="<?= registryPageEsc(generateCsrfToken()) ?>">
                            <input type="hidden" name="stage" value="validate">
                            <input type="hidden" name="import_token" value="<?= registryPageEsc($importState['token'] ?? '') ?>">
                            <?php foreach ($canonicalFields as $field): ?>
                                <label><span><?= registryPageEsc(ucwords(str_replace('_', ' ', $field))) ?><?= in_array($field, $requiredFields, true) ? ' *' : '' ?></span><select name="mapping[<?= registryPageEsc($field) ?>]"><option value="">Not mapped</option><?php foreach ($importHeaders as $header): ?><option value="<?= (int) $header['column'] ?>" <?= (int) ($importMapping[$field] ?? 0) === (int) $header['column'] ? 'selected' : '' ?>><?= registryPageEsc($header['label']) ?></option><?php endforeach; ?></select></label>
                            <?php endforeach; ?>
                            <div class="registry-form-actions"><button type="submit" class="aprism-action-btn aprism-action-btn-primary is-standard"><i data-lucide="check-circle-2" aria-hidden="true"></i> Validate Placement Data</button></div>
                        </form>
                    <?php elseif ($importStage === 'review'): ?>
                        <div class="registry-review-summary"><strong><?= count($importRows) ?> row(s) ready for confirmation.</strong><span>Programs, Sections, Students, and academic placements will be reused or created in one transaction.</span></div>
                        <div class="table-wrapper registry-preview-wrapper"><table class="module-table registry-preview-table"><thead><tr><th>Student Number</th><th>Student</th><th>Level</th><th>Program</th><th>Section</th><th>Year</th><th>School Year</th><th>Placement</th></tr></thead><tbody>
                            <?php foreach (array_slice($importRows, 0, 20) as $resolved): ?>
                                <?php $row = $resolved['data']; ?>
                                <tr><td><?= registryPageEsc($row['student_number']) ?></td><td><?= registryPageEsc(trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name'])) ?></td><td><?= registryPageEsc($row['academic_level']) ?></td><td><?= registryPageEsc(($row['program_code'] ? $row['program_code'] . ' · ' : '') . $row['program_name']) ?></td><td><?= registryPageEsc($row['section_name']) ?></td><td><?= registryPageEsc($row['year_level']) ?></td><td><?= registryPageEsc($row['school_year']) ?></td><td><span class="registry-review-badge"><?= !empty($resolved['academic_enrollment']['student_academic_enrollment_id']) ? 'Reuse' : 'Create / resolve' ?></span></td></tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                        <?php if (count($importRows) > 20): ?><p class="registry-preview-note">Showing the first 20 rows of the validated review.</p><?php endif; ?>
                        <form method="post" action="<?= APP_URL ?>/actions/academic_head/import_institutional_registry.php" class="registry-confirm-form">
                            <input type="hidden" name="csrf_token" value="<?= registryPageEsc(generateCsrfToken()) ?>">
                            <input type="hidden" name="stage" value="confirm">
                            <input type="hidden" name="import_token" value="<?= registryPageEsc($importState['token'] ?? '') ?>">
                            <button type="submit" class="aprism-action-btn aprism-action-btn-primary is-standard"><i data-lucide="database" aria-hidden="true"></i> Confirm and Import</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="module-card registry-records-card students-records-card">
                <form method="get" class="registry-filter-toolbar" role="search">
                    <input type="hidden" name="per_page" value="<?= $pageSize ?>">
                    <label class="registry-filter-search"><span>Search</span><input type="search" name="search" value="<?= registryPageEsc($filters['search']) ?>" placeholder="Student number or name"></label>
                    <label><span>Academic Level</span><select name="academic_level"><option value="All">All Levels</option><?php foreach ($levels as $level): ?><option value="<?= registryPageEsc($level) ?>" <?= $filters['academic_level'] === $level ? 'selected' : '' ?>><?= registryPageEsc($level) ?></option><?php endforeach; ?></select></label>
                    <label><span>Program</span><select name="program_id"><option value="0">All Programs</option><?php foreach ($programs as $program): ?><option value="<?= (int) $program['program_id'] ?>" <?= (int) $filters['program_id'] === (int) $program['program_id'] ? 'selected' : '' ?>><?= registryPageEsc(($program['program_code'] ? $program['program_code'] . ' · ' : '') . $program['program_name']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Section</span><select name="section_id"><option value="0">All Sections</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['section_id'] ?>" <?= (int) $filters['section_id'] === (int) $section['section_id'] ? 'selected' : '' ?>><?= registryPageEsc($section['section_name']) ?></option><?php endforeach; ?></select></label>
                    <label><span>School Year</span><select name="school_year_id"><option value="0">All School Years</option><?php foreach ($schoolYears as $schoolYear): ?><option value="<?= (int) $schoolYear['school_year_id'] ?>" <?= (int) $filters['school_year_id'] === (int) $schoolYear['school_year_id'] ? 'selected' : '' ?>><?= registryPageEsc($schoolYear['school_year']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Context</span><select name="semester"><option value="All">All Contexts</option><?php foreach ($semesters as $semester): ?><option value="<?= registryPageEsc($semester) ?>" <?= $filters['semester'] === $semester ? 'selected' : '' ?>><?= registryPageEsc($semester) ?></option><?php endforeach; ?></select></label>
                    <label><span>Status</span><select name="status"><option value="All">All Statuses</option><?php foreach ($saeStatuses as $status): ?><option value="<?= registryPageEsc($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= registryPageEsc($status === 'Missing' ? 'Missing / Unresolved' : $status) ?></option><?php endforeach; ?></select></label>
                    <div class="students-filter-actions">
                        <button type="submit" class="aprism-action-btn aprism-action-btn-primary is-standard">Filter</button>
                        <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="?per_page=<?= $pageSize ?>">Reset Filters</a>
                    </div>
                </form>
                <div class="registry-table-heading"><h2>Student records</h2><span><?= $loadError !== null ? 'Records unavailable' : number_format($totalStudents) . ' student' . ($totalStudents === 1 ? '' : 's') ?></span></div>
                <div class="table-wrapper registry-table-wrapper students-table-wrapper"><table class="module-table registry-table academic-student-table"><thead><tr><th class="table-row-number" scope="col">#</th><th>Student</th><th>Academic Placement</th><th>Academic Context</th><th>Status</th></tr></thead><tbody>
                    <?php if ($loadError !== null): ?>
                        <tr><td colspan="5"><div class="registry-empty-state" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><h3>Student records unavailable</h3><p>Student records could not be loaded. Refresh the page to try again.</p></div></td></tr>
                    <?php elseif ($students === []): ?>
                        <tr><td colspan="5"><div class="registry-empty-state"><i data-lucide="graduation-cap" aria-hidden="true"></i><h3><?= $hasStudentFilters ? 'No matching students' : 'No institutional student records' ?></h3><p><?= $hasStudentFilters ? 'No students match the selected filters.' : 'Known student identities appear here after a Teacher Class List or placement-data import.' ?></p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $studentIndex => $student): ?>
                            <?php
                            $placementStatus = (string) ($student['placement_status'] ?? '');
                            $participationStatus = (string) ($student['participation_status'] ?? '');
                            $currentPlacementCount = (int) ($student['current_placement_count'] ?? 0);
                            $displayStatus = $placementStatus;
                            if ($currentPlacementCount > 1) {
                                $displayStatus = 'Ambiguous / Conflicting';
                            } elseif ($placementStatus === 'Active') {
                                $displayStatus = $participationStatus === 'Participating'
                                    ? 'Placed and Participating'
                                    : 'Current Institutional Placement';
                            } elseif ($placementStatus === 'Missing / Unresolved') {
                                $displayStatus = $participationStatus === 'Participating'
                                    ? 'Current Participating / Placement Unresolved'
                                    : 'Historical / No Current Evidence';
                            }
                            $placementStatusClass = match ($displayStatus) {
                                'Missing / Unresolved', 'Current Participating / Placement Unresolved', 'Historical / No Current Evidence' => 'missing-unresolved',
                                'Ambiguous / Conflicting' => 'ambiguous-conflicting',
                                'Placed and Participating' => 'active-participating',
                                'Current Institutional Placement' => 'active',
                                default => strtolower($displayStatus),
                            };
                            $academicLevel = trim((string) ($student['academic_level'] ?? ''));
                            $programCode = trim((string) ($student['program_code'] ?? ''));
                            $programName = trim((string) ($student['program_name'] ?? ''));
                            $programParts = array_values(array_filter([$programCode, $programName], static fn (string $value): bool => $value !== ''));
                            $programLine = implode(' · ', $programParts);
                            $yearLevel = trim((string) ($student['year_level'] ?? ''));
                            $sectionName = trim((string) ($student['section_name'] ?? ''));
                            $yearLabel = match ($academicLevel) {
                                'Senior High School' => 'Grade ',
                                'College' => 'Year ',
                                default => '',
                            };
                            $yearSectionParts = [];
                            if ($yearLevel !== '') {
                                $yearSectionParts[] = $yearLabel . $yearLevel;
                            }
                            if ($sectionName !== '') {
                                $yearSectionParts[] = $sectionName;
                            }
                            $yearSectionLine = implode(' · ', $yearSectionParts);
                            $hasPlacementData = $academicLevel !== '' || $programLine !== '' || $yearSectionLine !== '';
                            $schoolYear = trim((string) ($student['school_year'] ?? ''));
                            $semester = trim((string) ($student['semester'] ?? ''));
                            ?>
                            <tr>
                                <td class="table-row-number" data-label="#"><?= $firstStudent + $studentIndex ?></td>
                                <td data-label="Student"><strong><?= registryPageEsc($student['student_name']) ?></strong><small><?= registryPageEsc($student['student_number']) ?></small></td>
                                <td data-label="Academic Placement">
                                    <?php if ($hasPlacementData): ?>
                                        <div class="student-placement-summary">
                                            <?php if ($academicLevel !== ''): ?><strong class="student-placement-level"><?= registryPageEsc($academicLevel) ?></strong><?php endif; ?>
                                            <?php if ($programLine !== ''): ?><span><?= registryPageEsc($programLine) ?></span><?php endif; ?>
                                            <?php if ($yearSectionLine !== ''): ?><span><?= registryPageEsc($yearSectionLine) ?></span><?php endif; ?>
                                        </div>
                                    <?php elseif ($currentPlacementCount > 1): ?>
                                        <span class="student-placement-state">Multiple current placements</span>
                                    <?php else: ?>
                                        <span class="student-placement-state">Placement unresolved</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Academic Context">
                                    <?php if ($schoolYear !== '' || $semester !== ''): ?>
                                        <div class="student-academic-context">
                                            <?php if ($schoolYear !== ''): ?><strong><?= registryPageEsc($schoolYear) ?></strong><?php endif; ?>
                                            <?php if ($semester !== ''): ?><small><?= registryPageEsc($semester) ?></small><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="student-context-empty">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status"><span class="registry-status-badge registry-status-<?= registryPageEsc($placementStatusClass) ?>"><?= registryPageEsc($displayStatus) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody></table></div>
                <?php if ($totalStudents > 0 && $totalPages > 1): ?>
                    <div class="registry-pagination">
                        <span>Showing <?= number_format($firstStudent) ?> to <?= number_format($lastStudent) ?> of <?= number_format($totalStudents) ?> Students</span>
                        <?php $startPage = max(1, min($currentPage - 1, $totalPages - 2)); $endPage = min($totalPages, $startPage + 2); ?>
                        <nav aria-label="Institutional Student pagination">
                            <a class="registry-page-link <?= $currentPage === 1 ? 'disabled' : '' ?>" href="<?= registryPageEsc($pageUrl($currentPage - 1)) ?>" aria-label="Previous page"><i data-lucide="chevron-left"></i></a>
                            <?php for ($page = $startPage; $page <= $endPage; $page++): ?><a class="registry-page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= registryPageEsc($pageUrl($page)) ?>"><?= $page ?></a><?php endfor; ?>
                            <a class="registry-page-link <?= $currentPage === $totalPages ? 'disabled' : '' ?>" href="<?= registryPageEsc($pageUrl($currentPage + 1)) ?>" aria-label="Next page"><i data-lucide="chevron-right"></i></a>
                        </nav>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <div class="modal fade" id="registryImportModal" tabindex="-1" aria-labelledby="registryImportModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><form class="modal-content modal-content-custom" action="<?= APP_URL ?>/actions/academic_head/import_institutional_registry.php" method="post" enctype="multipart/form-data">
            <div class="modal-header-custom"><h2 class="modal-title-custom" id="registryImportModalTitle">Import Student Placement Data</h2><button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close"><i data-lucide="x"></i></button></div>
            <div class="modal-body-custom"><input type="hidden" name="csrf_token" value="<?= registryPageEsc(generateCsrfToken()) ?>"><input type="hidden" name="stage" value="upload"><label class="form-label-custom" for="registry_file">Placement data file</label><input class="form-control-custom" id="registry_file" type="file" name="registry_file" accept=".csv,.xlsx,.xls" required><small class="registry-file-note">Use explicit Student identity, academic level, Program, Section, Year/Grade Level, and School Year columns.</small></div>
            <div class="modal-footer-custom"><button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button><button type="submit" class="modal-btn-action"><i data-lucide="upload"></i><span>Extract Source</span></button></div>
        </form></div>
    </div>
    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="fixed-six" defer></script>
</body>
</html>
