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

$pageTitle = 'Student Grade Detail';
$activePage = 'grade_analytics';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-grade-analytics.css';
$additionalStylesheets = ['assets/css/pages/teacher-grade-workspace.css'];
$teacherId = (int) ($_SESSION['user_id'] ?? 0);

$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$classId = $classId !== false && $classId !== null && $classId > 0 ? (int) $classId : 0;
$periodId = filter_input(INPUT_GET, 'period_id', FILTER_VALIDATE_INT);
$periodId = $periodId !== false && $periodId !== null && $periodId > 0 ? (int) $periodId : 0;
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$studentId = $studentId !== false && $studentId !== null && $studentId > 0 ? (int) $studentId : 0;
$search = trim((string) ($_GET['search'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
$completion = (string) ($_GET['completion'] ?? 'all');
if (!in_array($completion, ['all', 'complete', 'incomplete', 'no_data'], true)) {
    $completion = 'all';
}
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$categoryId = $categoryId !== false && $categoryId !== null && $categoryId > 0 ? (int) $categoryId : 0;
$page = max(1, (int) ($_GET['page'] ?? 1));

function gradeStudentEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function gradeStudentNumber(mixed $value, int $decimals = 1): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '—';
    }
    return number_format((float) $value, $decimals);
}

function gradeStudentComponentLabel(array $component): string
{
    $code = trim((string) ($component['component_code'] ?? ''));
    $name = trim((string) ($component['component_name'] ?? ''));

    if ($code !== '' && $name !== '' && strcasecmp($code, $name) !== 0) {
        return $code . ' · ' . $name;
    }

    return $code !== '' ? $code : $name;
}

function gradeStudentBackUrl(): string
{
    $query = array_filter([
        'class_id' => $GLOBALS['classId'] ?: null,
        'period_id' => $GLOBALS['periodId'] ?: null,
        'search' => $GLOBALS['search'] !== '' ? $GLOBALS['search'] : null,
        'completion' => $GLOBALS['completion'] !== 'all' ? $GLOBALS['completion'] : null,
        'category_id' => $GLOBALS['categoryId'] ?: null,
        'page' => $GLOBALS['page'] > 1 ? $GLOBALS['page'] : null,
    ], static fn($value): bool => $value !== null && $value !== '');
    return APP_URL . '/dashboard/teacher_grade_analytics.php?' . http_build_query($query) . '#student-records';
}

$analytics = $classId > 0 ? aprismGradeAnalyticsBuild($pdo, $teacherId, $classId, $periodId) : null;
if ($analytics !== null && $analytics['period'] !== null) {
    $periodId = (int) $analytics['period']['academic_period_id'];
}
$studentDetail = $analytics !== null && $analytics['period'] !== null && $studentId > 0
    ? aprismGradeAnalyticsStudentDetail($pdo, $teacherId, $classId, $periodId, $studentId)
    : null;

$pageError = $classId === 0 || $periodId === 0 || $studentId === 0
    ? 'Open this detail from a Student record in Grade Analytics.'
    : ($analytics === null
        ? 'The requested Operational Class is outside your Teacher scope.'
        : ($analytics['period'] === null ? 'The requested grading period is not available for this class.' : ($studentDetail === null ? 'The requested Student is not enrolled in this class.' : null)));
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="teacher-page-container grade-workspace-page grade-student-detail-page">
            <?php
            $gradeWorkspaceActive = 'analytics';
            $gradeWorkspaceTitle = 'Student Grade Detail';
            $gradeWorkspaceClassId = $classId;
            $gradeWorkspacePeriodId = $periodId;
            require __DIR__ . '/../includes/components/teacher_grade_workspace.php';
            ?>

            <?php if ($pageError !== null): ?>
                <section class="teacher-panel grade-analytics-empty" role="alert">
                    <i data-lucide="file-warning" aria-hidden="true"></i>
                    <h2>Student detail unavailable</h2>
                    <p><?= gradeStudentEsc($pageError) ?></p>
                    <a class="grade-analytics-secondary-action" href="<?= gradeStudentEsc(gradeStudentBackUrl()) ?>"><i data-lucide="arrow-left" aria-hidden="true"></i> Back to Grade Analytics</a>
                </section>
            <?php else: ?>
                <?php $student = $studentDetail['student']; $class = $analytics['class']; $period = $analytics['period']; ?>
                <header class="grade-student-detail-header">
                    <div>
                        <p class="grade-workspace-eyebrow">Selected-period raw assessment record</p>
                        <h1 class="teacher-page-title"><?= gradeStudentEsc($student['student_name']) ?></h1>
                        <div class="grade-student-detail-meta" aria-label="Student grade context">
                            <span><strong><?= gradeStudentEsc($student['student_number']) ?></strong></span>
                            <span><?= gradeStudentEsc($class['subject_name']) ?> · <?= gradeStudentEsc($class['section_name']) ?></span>
                            <span><?= gradeStudentEsc($class['school_year']) ?> · <?= gradeStudentEsc($class['semester']) ?></span>
                            <span><?= gradeStudentEsc($period['academic_level'] . ' · ' . $period['period_name']) ?></span>
                        </div>
                    </div>
                    <a class="grade-analytics-secondary-action" href="<?= gradeStudentEsc(gradeStudentBackUrl()) ?>"><i data-lucide="arrow-left" aria-hidden="true"></i> Back to Grade Analytics</a>
                </header>

                <section class="grade-student-detail-summary" aria-label="Student raw-score summary">
                    <article><span>Components</span><strong><?= count($studentDetail['components']) ?></strong></article>
                    <article><span>Recorded</span><strong><?= count(array_filter($studentDetail['components'], static fn(array $component): bool => (int) $component['has_score'] === 1)) ?></strong></article>
                    <article><span>Missing</span><strong><?= count(array_filter($studentDetail['components'], static fn(array $component): bool => (int) $component['has_score'] !== 1)) ?></strong></article>
                    <article><span>Record type</span><strong>Raw scores</strong></article>
                </section>

                <section class="grade-student-detail-grid">
                    <article class="teacher-panel grade-analytics-panel">
                        <div class="grade-analytics-panel-heading">
                            <div><p class="teacher-eyebrow">Assessment components</p><h2>Recorded raw scores</h2></div>
                            <span class="grade-analytics-neutral-tag"><?= gradeStudentEsc($period['period_name']) ?></span>
                        </div>
                        <div class="grade-analytics-table-wrap">
                            <table class="grade-analytics-table grade-student-detail-table">
                                <thead><tr><th scope="col">Category</th><th scope="col">Component</th><th scope="col">Raw Score</th><th scope="col">Possible</th><th scope="col">Recorded %</th><th scope="col">State</th></tr></thead>
                                <tbody>
                                <?php if ($studentDetail['components'] === []): ?>
                                    <tr><td colspan="6" class="grade-analytics-empty-cell">No assessment components are configured for this period.</td></tr>
                                <?php else: foreach ($studentDetail['components'] as $component): ?>
                                    <tr>
                                        <td><strong><?= gradeStudentEsc($component['category_name']) ?></strong></td>
                                        <td><?= gradeStudentEsc(gradeStudentComponentLabel($component)) ?></td>
                                        <td><?= (int) $component['has_score'] ? gradeStudentNumber((float) $component['raw_score'], 2) : '—' ?></td>
                                        <td><?= gradeStudentNumber((float) $component['max_score'], 2) ?></td>
                                        <td><?= (int) $component['has_score'] && (float) $component['max_score'] > 0 ? gradeStudentNumber(((float) $component['raw_score'] / (float) $component['max_score']) * 100) . '%' : '—' ?></td>
                                        <td><span class="grade-analytics-state <?= (int) $component['has_score'] ? 'complete' : 'no-data' ?>"><?= (int) $component['has_score'] ? 'Recorded' : 'Missing' ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="grade-analytics-note">Raw scores and component maximums are shown as recorded. No final grade or performance judgment is calculated here.</p>
                    </article>

                    <aside class="teacher-panel grade-analytics-panel grade-student-comparison-panel">
                        <div class="grade-analytics-panel-heading"><div><p class="teacher-eyebrow">Across imported periods</p><h2>Period evidence</h2></div><i data-lucide="history" aria-hidden="true"></i></div>
                        <?php if ($studentDetail['period_comparison'] === []): ?>
                            <div class="grade-analytics-inline-empty">No other imported periods are available.</div>
                        <?php else: ?>
                            <div class="grade-analytics-student-periods">
                            <?php foreach ($studentDetail['period_comparison'] as $comparison): ?>
                                <div><strong><?= gradeStudentEsc($comparison['period_name']) ?></strong><span><?= (int) $comparison['populated_entries'] ?> / <?= (int) $comparison['component_count'] ?> entries</span><b><?= gradeStudentNumber($comparison['recorded_raw_percent']) ?>% raw</b></div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p class="grade-analytics-note">Entry counts show coverage. Percentages use recorded raw scores and available component maximums; they are not final grades or a performance trend.</p>
                    </aside>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/logout_modal.php'; require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
