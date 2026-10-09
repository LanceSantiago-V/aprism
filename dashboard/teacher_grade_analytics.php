<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';

require_once __DIR__ . '/../includes/helper/grade_analytics_helper.php';

$pageTitle = 'Grade Analytics';
$activePage = 'grade_analytics';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-grade-analytics.css';
$additionalStylesheets = ['assets/css/pages/teacher-grade-workspace.css'];
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$classes = aprismGradeTeacherClasses($pdo, $teacherId);
$requestedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$classId = $requestedClassId !== false && $requestedClassId !== null && $requestedClassId > 0
    ? (int) $requestedClassId
    : 0;
$requestedPeriodId = filter_input(INPUT_GET, 'period_id', FILTER_VALIDATE_INT);
$periodId = $requestedPeriodId !== false && $requestedPeriodId !== null && $requestedPeriodId > 0
    ? (int) $requestedPeriodId
    : 0;
$search = trim((string) ($_GET['search'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
$completion = (string) ($_GET['completion'] ?? 'all');
if (!in_array($completion, ['all', 'complete', 'incomplete', 'no_data'], true)) {
    $completion = 'all';
}
$selectedCategoryId = (int) ($_GET['category_id'] ?? 0);

$analytics = $classId > 0 ? aprismGradeAnalyticsBuild($pdo, $teacherId, $classId, $periodId) : null;
if ($analytics !== null) {
    $periodId = $analytics['period'] !== null ? (int) $analytics['period']['academic_period_id'] : 0;
}
$studentRows = $analytics['rows'] ?? [];
$filteredRows = array_values(array_filter($studentRows, static function (array $row) use ($search, $completion): bool {
    $stateMatches = $completion === 'all'
        || ($completion === 'complete' && $row['completion_state'] === 'Complete')
        || ($completion === 'incomplete' && $row['completion_state'] === 'Incomplete')
        || ($completion === 'no_data' && $row['completion_state'] === 'No data');
    if (!$stateMatches) {
        return false;
    }
    if ($search === '') {
        return true;
    }
    return stripos((string) $row['student_number'], $search) !== false
        || stripos((string) $row['student_name'], $search) !== false;
}));
$pageSize = 10;
$totalRows = count($filteredRows);
$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $pageSize;
$pageRows = array_slice($filteredRows, $offset, $pageSize);
function gradeAnalyticsEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function gradeAnalyticsNumber(mixed $value, int $decimals = 1): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '—';
    }
    return number_format((float) $value, $decimals);
}

function gradeAnalyticsUrl(array $overrides = []): string
{
    $query = array_merge([
        'class_id' => $GLOBALS['classId'] ?: null,
        'period_id' => $GLOBALS['periodId'] ?: null,
        'search' => $GLOBALS['search'] !== '' ? $GLOBALS['search'] : null,
        'completion' => $GLOBALS['completion'] !== 'all' ? $GLOBALS['completion'] : null,
        'category_id' => $GLOBALS['selectedCategoryId'] ?: null,
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_grade_analytics.php?' . http_build_query($query);
}

function gradeAnalyticsStudentDetailUrl(int $studentId, int $page = 1): string
{
    $query = array_filter([
        'class_id' => $GLOBALS['classId'] ?: null,
        'period_id' => $GLOBALS['periodId'] ?: null,
        'student_id' => $studentId > 0 ? $studentId : null,
        'search' => $GLOBALS['search'] !== '' ? $GLOBALS['search'] : null,
        'completion' => $GLOBALS['completion'] !== 'all' ? $GLOBALS['completion'] : null,
        'category_id' => $GLOBALS['selectedCategoryId'] ?: null,
        'page' => $page > 1 ? $page : null,
    ], static fn($value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_grade_student.php?' . http_build_query($query);
}

$summary = $analytics['summary'] ?? null;
$analyticsContext = $analytics ?? ['periods' => [], 'period' => null, 'class' => null];
$attentionRows = array_values(array_filter($studentRows, static function (array $row): bool {
    return in_array($row['completion_state'] ?? '', ['Incomplete', 'No data'], true);
}));
$attentionPreviewRows = $attentionRows;
$rawScorePreviewRows = $studentRows;
$overviewEnrolled = (int) ($summary['enrolled_students'] ?? 0);
$overviewAttention = count($attentionRows);
$overviewCompletePercent = $overviewEnrolled > 0
    ? min(100, ((int) ($summary['complete_students'] ?? 0) / $overviewEnrolled) * 100)
    : 0.0;
$overviewAttentionPercent = $overviewEnrolled > 0
    ? min(100, ($overviewAttention / $overviewEnrolled) * 100)
    : 0.0;
$overviewCoveragePercent = max(0, min(100, (float) ($summary['coverage_percent'] ?? 0)));
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="teacher-page-container grade-workspace-page grade-analytics-page">
            <?php
            $gradeWorkspaceActive = 'analytics';
            $gradeWorkspaceTitle = 'Grade Analytics';
            $gradeWorkspaceClassId = $classId;
            $gradeWorkspacePeriodId = $periodId;
            require __DIR__ . '/../includes/components/teacher_grade_workspace.php';
            ?>

            <?php if ($classes === []): ?>
                <section class="teacher-panel grade-analytics-empty">
                    <i data-lucide="book-open" aria-hidden="true"></i>
                    <h2>No Operational Classes yet</h2>
                    <p>Create or import a Teacher-owned Operational Class before reviewing grade analytics.</p>
                </section>
            <?php elseif ($classId > 0 && $analytics === null): ?>
                <section class="teacher-panel grade-analytics-empty">
                    <i data-lucide="shield-alert" aria-hidden="true"></i>
                    <h2>Class access unavailable</h2>
                    <p>The requested class is not in your Teacher scope.</p>
                </section>
            <?php else: ?>
                <section class="teacher-panel grade-workspace-context-panel grade-analytics-context-panel">
                    <form method="get" class="grade-analytics-context-form">
                        <label>Operational Class
                            <select name="class_id" onchange="this.form.elements.period_id.value=''; this.form.submit()">
                                <option value="">Select an Operational Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= (int) $class['operational_class_id'] ?>" <?= (int) $class['operational_class_id'] === $classId ? 'selected' : '' ?>>
                                        <?= gradeAnalyticsEsc($class['subject_name'] . ' · ' . $class['section_name'] . ' · ' . $class['school_year']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Grading Period
                            <select name="period_id" <?= $classId === 0 || $analyticsContext['periods'] === [] ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <option value=""><?= $classId === 0 ? 'Select a class first' : ($analyticsContext['periods'] === [] ? 'No imported periods' : 'Select a grading period') ?></option>
                                <?php foreach ($analyticsContext['periods'] as $period): ?>
                                    <option value="<?= (int) $period['academic_period_id'] ?>" <?= (int) $period['academic_period_id'] === $periodId ? 'selected' : '' ?>>
                                        <?= gradeAnalyticsEsc($period['academic_level'] . ' · ' . $period['period_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="grade-analytics-context-meta">
                            <span>Teaching term</span>
                            <?php if ($analyticsContext['class'] !== null): ?>
                                <strong><?= gradeAnalyticsEsc($analyticsContext['class']['semester']) ?></strong>
                                <span class="grade-analytics-context-check"><i data-lucide="circle-check" aria-hidden="true"></i></span>
                            <?php else: ?>
                                <strong>Select an Operational Class</strong>
                                <small>Analytics will appear after a valid class and period are selected.</small>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <?php if ($classId === 0): ?>
                    <section class="teacher-panel grade-analytics-empty">
                        <i data-lucide="chart-no-axes-column" aria-hidden="true"></i>
                        <h2>Select a class and grading period</h2>
                        <p>Choose a Teacher-owned Operational Class and then select one of its imported grading periods to view coverage and raw-score analytics.</p>
                    </section>
                <?php elseif ($analytics['period'] === null): ?>
                    <section class="teacher-panel grade-analytics-empty">
                        <i data-lucide="file-search" aria-hidden="true"></i>
                        <h2>Select a grading period</h2>
                        <p>Choose one of the valid imported periods above to load the selected-period analytics.</p>
                        <a class="grade-analytics-primary-action" href="<?= APP_URL ?>/dashboard/teacher_grade_import.php?operational_class_id=<?= (int) $classId ?>">Open Grade Import</a>
                    </section>
                <?php else: ?>
                    <section class="grade-analytics-summary-strip" aria-label="Selected-period class overview">
                        <div class="grade-analytics-summary-item is-neutral">
                            <span class="grade-analytics-summary-icon"><i data-lucide="users" aria-hidden="true"></i></span>
                            <div class="grade-analytics-summary-card-content">
                                <span>Total Students</span>
                                <strong><?= $overviewEnrolled ?></strong>
                                <small>Enrolled in this class</small>
                            </div>
                        </div>
                        <a class="grade-analytics-summary-item <?= (int) $summary['complete_students'] > 0 ? 'is-complete' : '' ?>" href="<?= gradeAnalyticsEsc(gradeAnalyticsUrl(['completion' => 'complete'])) ?>#student-records">
                            <span class="grade-analytics-summary-icon"><i data-lucide="circle-check" aria-hidden="true"></i></span>
                            <div class="grade-analytics-summary-card-content">
                                <span>Complete Records</span>
                                <strong><?= (int) $summary['complete_students'] ?></strong>
                                <small><b class="grade-analytics-summary-percent"><?= gradeAnalyticsNumber($overviewCompletePercent) ?>%</b> of roster complete</small>
                                <i class="grade-analytics-summary-progress" aria-hidden="true"><b style="width: <?= gradeAnalyticsEsc($overviewCompletePercent) ?>%"></b></i>
                            </div>
                        </a>
                        <div class="grade-analytics-summary-item <?= $attentionRows !== [] ? 'is-attention' : 'is-complete' ?>">
                            <span class="grade-analytics-summary-icon"><i data-lucide="circle-alert" aria-hidden="true"></i></span>
                            <div class="grade-analytics-summary-card-content">
                                <span>Needs Attention</span>
                                <strong><?= $overviewAttention ?></strong>
                                <small><b class="grade-analytics-summary-percent"><?= gradeAnalyticsNumber($overviewAttentionPercent) ?>%</b> of roster · <a href="<?= gradeAnalyticsEsc(gradeAnalyticsUrl(['completion' => 'incomplete'])) ?>#student-records"><?= (int) $summary['incomplete_students'] ?> incomplete</a> · <a href="<?= gradeAnalyticsEsc(gradeAnalyticsUrl(['completion' => 'no_data'])) ?>#student-records"><?= (int) $summary['no_data_students'] ?> no data</a></small>
                                <i class="grade-analytics-summary-progress" aria-hidden="true"><b style="width: <?= gradeAnalyticsEsc($overviewAttentionPercent) ?>%"></b></i>
                            </div>
                        </div>
                        <div class="grade-analytics-summary-item grade-analytics-coverage-summary <?= (float) ($summary['coverage_percent'] ?? 0) >= 100 ? 'is-complete' : ((float) ($summary['coverage_percent'] ?? 0) > 0 ? 'is-incomplete' : 'is-no-data') ?>">
                            <span class="grade-analytics-summary-icon"><i data-lucide="chart-column" aria-hidden="true"></i></span>
                            <div class="grade-analytics-summary-card-content">
                                <span>Raw-score Coverage</span>
                                <strong><?= gradeAnalyticsNumber($summary['coverage_percent']) ?>%</strong>
                                <small><?= (int) $summary['populated_entries'] ?> / <?= (int) $summary['expected_entries'] ?> entries recorded</small>
                                <i class="grade-analytics-summary-progress" aria-hidden="true"><b style="width: <?= gradeAnalyticsEsc($overviewCoveragePercent) ?>%"></b></i>
                            </div>
                        </div>
                    </section>

                    <section class="grade-analytics-grid" aria-label="Assessment analytics">
                        <article class="teacher-panel grade-analytics-panel grade-analytics-period-panel" id="period-coverage">
                            <div class="grade-analytics-panel-heading">
                                <div class="grade-analytics-heading-with-icon"><span class="grade-analytics-panel-icon"><i data-lucide="users" aria-hidden="true"></i></span><div><p class="teacher-eyebrow">Data coverage</p><h2>Assessment Data Coverage</h2><p class="grade-analytics-panel-subtitle">Percentage of students with recorded raw scores by academic period.</p></div></div>
                                <span class="grade-analytics-neutral-tag">Not a grade trend</span>
                            </div>
                            <?php if ($analytics['term_coverage'] === []): ?>
                                <div class="grade-analytics-inline-empty">No imported periods are available.</div>
                            <?php else: ?>
                                <div class="grade-analytics-period-chart-shell">
                                    <div class="grade-analytics-period-axis" aria-hidden="true"><span>100%</span><span>75%</span><span>50%</span><span>25%</span><span>0%</span></div>
                                    <div class="grade-analytics-period-chart" aria-label="Assessment data coverage chart">
                                    <?php foreach ($analytics['term_coverage'] as $periodCoverage): ?>
                                        <?php $coveragePercent = (float) ($periodCoverage['coverage_percent'] ?? 0); $coverageState = $coveragePercent >= 100 ? 'is-complete' : ($coveragePercent > 0 ? 'is-incomplete' : 'is-no-data'); ?>
                                        <a class="grade-analytics-period-column <?= $coverageState ?> <?= (int) $periodCoverage['academic_period_id'] === $periodId ? 'selected' : '' ?>" href="<?= gradeAnalyticsEsc(gradeAnalyticsUrl(['period_id' => (int) $periodCoverage['academic_period_id']])) ?>#period-coverage" aria-label="View <?= gradeAnalyticsEsc($periodCoverage['period_name']) ?> coverage" <?= (int) $periodCoverage['academic_period_id'] === $periodId ? 'aria-current="page"' : '' ?>">
                                            <span class="grade-analytics-period-value"><?= gradeAnalyticsNumber($periodCoverage['coverage_percent']) ?>%</span>
                                            <i><b style="height: <?= gradeAnalyticsEsc($coveragePercent) ?>%"></b></i>
                                            <strong><?= gradeAnalyticsEsc($periodCoverage['period_name']) ?></strong>
                                            <small><?= (int) $periodCoverage['populated_entries'] ?>/<?= (int) $periodCoverage['expected_entries'] ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <p class="grade-analytics-note">Coverage is populated versus expected raw-score entries. It is not a performance trend.</p>
                        </article>

                        <article class="teacher-panel grade-analytics-panel grade-analytics-category-chart-panel" id="category-performance">
                            <div class="grade-analytics-panel-heading">
                                <div class="grade-analytics-heading-with-icon"><span class="grade-analytics-panel-icon"><i data-lucide="chart-column" aria-hidden="true"></i></span><div><p class="teacher-eyebrow">Recorded score overview</p><h2>Average Recorded Raw Score by Assessment Category</h2><p class="grade-analytics-panel-subtitle">Average recorded raw scores for this class and period.</p></div></div>
                            </div>
                            <?php if ($analytics['categories'] === []): ?>
                                <div class="grade-analytics-inline-empty">No assessment categories are configured for this period.</div>
                            <?php else: ?>
                                <div class="grade-analytics-category-visual" aria-label="Average recorded raw score by assessment category">
                                    <?php foreach ($analytics['categories'] as $category): ?>
                                        <?php $categoryPercent = $category['average_component_percent'] !== null ? (float) $category['average_component_percent'] : 0.0; $categoryBarPercent = max(0, min(100, $categoryPercent)); ?>
                                        <a class="grade-analytics-category-bar <?= $selectedCategoryId === (int) $category['assessment_category_id'] ? 'selected' : '' ?>" href="#category-summary">
                                            <span><?= gradeAnalyticsEsc($category['category_name']) ?><small><?= (int) $category['component_count'] ?> component<?= (int) $category['component_count'] === 1 ? '' : 's' ?></small></span>
                                            <i><b style="width: <?= gradeAnalyticsEsc($categoryBarPercent) ?>%"></b></i>
                                            <strong><?= gradeAnalyticsNumber($category['average_component_percent']) ?>%</strong>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p class="grade-analytics-note">Percentages use recorded raw scores and component maximums; they are not final grades.</p>
                        </article>
                    </section>

                    <section class="grade-analytics-review-grid" aria-label="Student review">
                        <article class="teacher-panel grade-analytics-attention-panel <?= $attentionRows === [] ? 'is-clear' : '' ?>" id="attention-records">
                            <div class="grade-analytics-panel-heading">
                                <div class="grade-analytics-heading-with-icon">
                                    <span class="grade-analytics-panel-icon is-alert"><i data-lucide="circle-alert" aria-hidden="true"></i></span>
                                    <div>
                                        <p class="teacher-eyebrow">Priority review</p>
                                        <h2>Students Needing Attention <span class="grade-analytics-heading-count"><?= count($attentionRows) ?></span></h2>
                                        <p class="grade-analytics-panel-subtitle">Students with incomplete or missing assessment records for the selected period.</p>
                                    </div>
                                </div>
                                <a class="grade-analytics-secondary-action" href="#student-records">View all <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                            </div>
                            <?php if ($attentionRows === []): ?>
                                <div class="grade-analytics-positive-empty" role="status">
                                    <i data-lucide="circle-check" aria-hidden="true"></i>
                                    <div><strong>All student records are complete for this period.</strong><span>No incomplete or missing assessment entries were found.</span></div>
                                </div>
                            <?php else: ?>
                                <div class="grade-analytics-table-wrap grade-analytics-attention-table-wrap">
                                    <table class="grade-analytics-table grade-analytics-attention-table">
                                        <thead><tr><th scope="col">#</th><th scope="col">Student Number</th><th scope="col">Student</th><th scope="col">Record Status</th><th scope="col">Missing Records</th><th scope="col">Action</th></tr></thead>
                                        <tbody><?php foreach ($attentionPreviewRows as $attentionIndex => $student): ?>
                                            <tr>
                                                <td data-label="#"><?= $attentionIndex + 1 ?></td>
                                                <td data-label="Student Number"><?= gradeAnalyticsEsc($student['student_number']) ?></td>
                                                <td data-label="Student"><strong><?= gradeAnalyticsEsc($student['student_name']) ?></strong></td>
                                                <td data-label="Record Status"><span class="grade-analytics-state <?= $student['completion_state'] === 'Incomplete' ? 'incomplete' : 'no-data' ?>"><?= gradeAnalyticsEsc($student['completion_state']) ?></span></td>
                                                <td data-label="Missing Records"><?= (int) $student['missing_entries'] ?></td>
                                                <td data-label="Action"><a class="grade-analytics-row-action" href="<?= gradeAnalyticsEsc(gradeAnalyticsStudentDetailUrl((int) $student['student_id'], $page)) ?>">View detail <i data-lucide="arrow-right" aria-hidden="true"></i></a></td>
                                            </tr>
                                        <?php endforeach; ?></tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </article>

                        <article class="teacher-panel grade-analytics-raw-overview-panel" id="raw-score-overview">
                            <div class="grade-analytics-panel-heading">
                                <div class="grade-analytics-heading-with-icon">
                                    <span class="grade-analytics-panel-icon"><i data-lucide="users" aria-hidden="true"></i></span>
                                    <div><p class="teacher-eyebrow">Student-level scan</p><h2>Student Raw-Score Overview</h2><p class="grade-analytics-panel-subtitle">Overview of students with recorded raw scores.</p></div>
                                </div>
                                <div class="grade-analytics-overview-tools">
                                    <form method="get" class="grade-analytics-overview-search">
                                        <input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="period_id" value="<?= $periodId ?>"><?php if ($completion !== 'all'): ?><input type="hidden" name="completion" value="<?= gradeAnalyticsEsc($completion) ?>"><?php endif; ?>
                                        <i data-lucide="search" aria-hidden="true"></i>
                                        <input type="search" name="search" value="<?= gradeAnalyticsEsc($search) ?>" placeholder="Search student number or name..." aria-label="Search student number or name">
                                    </form>
                                </div>
                            </div>
                            <?php if ($studentRows === []): ?>
                                <div class="grade-analytics-inline-empty">No enrolled students are available for this class and period.</div>
                            <?php else: ?>
                                <div class="grade-analytics-raw-score-table">
                                    <div class="grade-analytics-raw-score-table-head" aria-hidden="true"><span>#</span><span>Student Name</span><span>Average Raw Score</span></div>
                                    <div class="grade-analytics-raw-score-list">
                                        <?php foreach ($rawScorePreviewRows as $rawIndex => $student): ?>
                                            <?php $rawPercent = $student['recorded_raw_percent'] !== null ? max(0, min(100, (float) $student['recorded_raw_percent'])) : 0.0; ?>
                                            <a class="grade-analytics-raw-score-row" href="<?= gradeAnalyticsEsc(gradeAnalyticsStudentDetailUrl((int) $student['student_id'], $page)) ?>">
                                                <span class="grade-analytics-raw-score-index"><?= $rawIndex + 1 ?></span>
                                                <span class="grade-analytics-raw-score-student"><strong><?= gradeAnalyticsEsc($student['student_name']) ?></strong><small><?= gradeAnalyticsEsc($student['student_number']) ?></small></span>
                                                <i class="grade-analytics-progress <?= $rawPercent >= 90 ? 'is-healthy' : 'is-neutral' ?>"><b style="width: <?= gradeAnalyticsEsc($rawPercent) ?>%"></b></i>
                                                <strong class="grade-analytics-raw-score-value"><?= gradeAnalyticsNumber($student['recorded_raw_percent']) ?>%</strong>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </article>
                    </section>

                    <section class="teacher-panel grade-analytics-students-panel" id="student-records">
                        <div class="grade-analytics-panel-heading">
                            <div><p class="teacher-eyebrow">Detailed records</p><h2>Student Record Completion</h2><p class="grade-analytics-panel-subtitle">Filter the selected-period roster by completion state or student.</p></div>
                            <div class="grade-analytics-table-actions">
                                <a class="grade-analytics-secondary-action" href="<?= APP_URL ?>/dashboard/teacher_reports.php?<?= http_build_query(['report_type' => 'grades', 'class_id' => $classId, 'period_id' => $periodId]) ?>"><i data-lucide="file-text" aria-hidden="true"></i> Grade Records Report</a>
                                <a class="grade-analytics-primary-action" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php?<?= http_build_query(['class_id' => $classId, 'period_id' => $periodId]) ?>"><i data-lucide="table-2" aria-hidden="true"></i> Open Ledger</a>
                            </div>
                        </div>
                        <form class="grade-analytics-filter-form" method="get"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="period_id" value="<?= $periodId ?>"><label>Search Student<input type="search" name="search" value="<?= gradeAnalyticsEsc($search) ?>" placeholder="Student Number or name"></label><label>Record State<select name="completion"><option value="all" <?= $completion === 'all' ? 'selected' : '' ?>>All records</option><option value="complete" <?= $completion === 'complete' ? 'selected' : '' ?>>Complete</option><option value="incomplete" <?= $completion === 'incomplete' ? 'selected' : '' ?>>Incomplete</option><option value="no_data" <?= $completion === 'no_data' ? 'selected' : '' ?>>No data</option></select></label><button type="submit" class="grade-analytics-filter-button">Apply</button></form>
                        <div class="grade-analytics-table-wrap">
                            <table class="grade-analytics-table grade-analytics-students-table">
                                <thead><tr><th scope="col">#</th><th scope="col">Student Number</th><th scope="col">Student</th><th scope="col">Assessment Completion</th><th scope="col">Missing</th><th scope="col">Recorded Raw %</th><th scope="col">Action</th></tr></thead>
                                <tbody>
                                    <?php if ($pageRows === []): ?>
                                        <tr><td colspan="7" class="grade-analytics-empty-cell">No Students match this filter.</td></tr>
                                    <?php else: foreach ($pageRows as $index => $student): ?>
                                        <tr>
                                            <td data-label="#"><?= $offset + $index + 1 ?></td>
                                            <td data-label="Student Number"><?= gradeAnalyticsEsc($student['student_number']) ?></td>
                                            <td data-label="Student"><strong><?= gradeAnalyticsEsc($student['student_name']) ?></strong></td>
                                            <td data-label="Assessment Completion"><span class="grade-analytics-state <?= $student['completion_state'] === 'Complete' ? 'complete' : ($student['completion_state'] === 'Incomplete' ? 'incomplete' : 'no-data') ?>"><?= gradeAnalyticsEsc($student['completion_state']) ?></span><small><?= (int) $student['score_count'] ?> / <?= (int) $student['component_count'] ?> entries</small></td>
                                            <td data-label="Missing"><?= (int) $student['missing_entries'] ?></td>
                                            <td data-label="Recorded Raw %"><?= gradeAnalyticsNumber($student['recorded_raw_percent']) ?>%</td>
                                            <td data-label="Action"><a class="grade-analytics-row-action" href="<?= gradeAnalyticsEsc(gradeAnalyticsStudentDetailUrl((int) $student['student_id'], $page)) ?>">View detail <i data-lucide="arrow-up-right" aria-hidden="true"></i></a></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <footer class="grade-analytics-pagination"><span>Showing <?= $totalRows ? $offset + 1 : 0 ?>–<?= min($offset + count($pageRows), $totalRows) ?> of <?= $totalRows ?> Students</span><?php if ($totalPages > 1): ?><nav aria-label="Student table pages"><a class="<?= $page === 1 ? 'is-disabled' : '' ?>" href="<?= gradeAnalyticsEsc(gradeAnalyticsUrl(['page' => max(1, $page - 1)])) ?>">‹</a><?php for ($p = 1; $p <= $totalPages; $p++): ?><a class="<?= $p === $page ? 'active' : '' ?>" href="<?= gradeAnalyticsEsc(gradeAnalyticsUrl(['page' => $p])) ?>"><?= $p ?></a><?php endfor; ?><a class="<?= $page === $totalPages ? 'is-disabled' : '' ?>" href="<?= gradeAnalyticsEsc(gradeAnalyticsUrl(['page' => min($totalPages, $page + 1)])) ?>">›</a><?php endif; ?></nav></footer>
                    </section>

                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/logout_modal.php';
    require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
