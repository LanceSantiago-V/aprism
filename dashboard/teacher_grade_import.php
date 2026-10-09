<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../auth/csrf_helper.php';

$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Grade Import';
$activePage = 'grade_import';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-grade-import.css';
$additionalStylesheets = ['assets/css/pages/teacher-grade-workspace.css'];
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$operationalClasses = [];
$periods = [];
$periodsByContext = [];
$currentSchoolYear = null;
$requestedOperationalClassId = filter_input(INPUT_GET, 'operational_class_id', FILTER_VALIDATE_INT);
$selectedOperationalClassId = $requestedOperationalClassId !== false && $requestedOperationalClassId !== null && $requestedOperationalClassId > 0
    ? (int) $requestedOperationalClassId
    : 0;

try {
    $schoolYearStmt = $pdo->query("SELECT school_year FROM school_years WHERE status = 'Active' ORDER BY school_year_id DESC LIMIT 1");
    $currentSchoolYear = $schoolYearStmt->fetchColumn() ?: null;
    $classStmt = $pdo->prepare("SELECT oc.operational_class_id, oc.school_year, oc.semester, sy.school_year_id, s.subject_name, sec.section_name
        FROM operational_classes oc INNER JOIN subjects s ON s.subject_id = oc.subject_id
        INNER JOIN sections sec ON sec.section_id = oc.section_id
        INNER JOIN school_years sy ON sy.school_year = oc.school_year AND sy.status = 'Active'
        WHERE oc.teacher_id = ? AND oc.school_year = ? AND oc.status = 'Active'
        ORDER BY s.subject_name, sec.section_name");
    $classStmt->execute([$teacherId, $currentSchoolYear]);
    $operationalClasses = $classStmt->fetchAll(PDO::FETCH_ASSOC);
    $availableClassIds = array_map(
        static fn (array $class): int => (int) $class['operational_class_id'],
        $operationalClasses
    );
    if (!in_array($selectedOperationalClassId, $availableClassIds, true)) {
        $selectedOperationalClassId = 0;
    }

    $periodStmt = $pdo->query("SELECT ap.academic_period_id, ap.school_year_id, ap.academic_level, ap.semester, ap.period_name FROM academic_periods ap
        INNER JOIN school_years sy ON sy.school_year_id = ap.school_year_id AND sy.status = 'Active'
        WHERE ap.is_archived = 0 ORDER BY ap.school_year_id, ap.semester, ap.academic_level,
        FIELD(ap.period_name, 'Prelim', 'Midterm', 'Pre-Final', 'Final', 'Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'), ap.start_date, ap.academic_period_id");
    $periods = $periodStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($periods as $period) {
        $contextKey = $period['school_year_id'] . '|' . ($period['semester'] ?? '') . '|' . $period['academic_level'];
        $periodsByContext[$contextKey][] = ['academic_period_id' => (int) $period['academic_period_id'], 'period_name' => $period['period_name']];
    }
} catch (Throwable $e) {
    error_log('[APRISM Grade Import Page] ' . $e->getMessage());
    $_SESSION['error_message'] = 'Grade Import context could not be loaded.';
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="teacher-page-container grade-workspace-page">
            <?php
            $gradeWorkspaceActive = 'import';
            $gradeWorkspaceTitle = 'Grade Import';
            $gradeWorkspaceClassId = $selectedOperationalClassId;
            require __DIR__ . '/../includes/components/teacher_grade_workspace.php';
            ?>

            <nav class="grade-import-steps" aria-label="Grade Import workflow">
                <span class="grade-import-step active"><b>1</b> Prepare source</span>
                <span class="grade-import-step"><b>2</b> Review &amp; validate</span>
                <span class="grade-import-step"><b>3</b> Confirm</span>
            </nav>

            <section class="teacher-panel grade-import-panel" aria-labelledby="grade-import-title">
                <div class="grade-import-panel-heading">
                    <div>
                        <p class="teacher-eyebrow">Step 1 · Prepare source</p>
                        <h2 id="grade-import-title">Choose class context and workbook</h2>
                    </div>
                    <span class="grade-import-status neutral">No source reviewed</span>
                </div>
                <form id="gradeImportForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="grade-import-fields">
                        <label class="grade-import-field">Operational Class
                            <select name="operational_class_id" required>
                                <option value="">Select a class</option>
                                <?php foreach ($operationalClasses as $class): ?>
                                    <option value="<?= (int) $class['operational_class_id'] ?>"
                                        <?= (int) $class['operational_class_id'] === $selectedOperationalClassId ? 'selected' : '' ?>
                                        data-school-year-id="<?= (int) $class['school_year_id'] ?>"
                                        data-school-year="<?= htmlspecialchars((string) $class['school_year'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-semester="<?= htmlspecialchars((string) $class['semester'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-subject="<?= htmlspecialchars((string) $class['subject_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-section="<?= htmlspecialchars((string) $class['section_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($class['subject_name'] . ' · ' . $class['section_name'] . ' · ' . $class['school_year'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="grade-import-context-panel" id="gradeImportContext" hidden>
                            <span class="teacher-eyebrow">Selected teaching term</span>
                            <strong id="gradeImportContextTitle">Choose a class</strong>
                            <span id="gradeImportContextMeta"></span>
                            <div id="gradeImportPeriods" class="grade-import-period-list"></div>
                        </div>
                        <label class="grade-import-field grade-import-upload-field">Workbook source
                            <span class="grade-import-upload" tabindex="0">
                                <span class="grade-import-upload-icon" aria-hidden="true"><i
                                        data-lucide="upload-cloud"></i></span>
                                <span><strong id="gradeImportFileName">Choose an Excel or CSV workbook</strong><small>
                                        .xlsx, .xls, or .csv · up to 10 MB</small></span>
                                <span class="grade-import-browse">Browse</span>
                                <input id="gradeImportFile" type="file" name="grade_file" accept=".xlsx,.xls,.csv"
                                    required>
                            </span>
                        </label>
                    </div>
                    <div class="grade-import-actions">
                        <button type="submit" class="grade-import-submit" id="previewGradeImport">Extract for
                            Review</button>
                        <p class="grade-import-helper"><i data-lucide="shield-check" aria-hidden="true"></i> Supported
                            SHS and College/Tertiary structures are validated; formula totals and final-grade cells
                            are excluded from raw-score import.</p>
                    </div>
                </form>
            </section>

            <section id="gradeImportReview" class="teacher-panel grade-import-review" aria-live="polite" hidden>
                <div class="grade-import-empty-state">
                    <span class="grade-import-empty-icon" aria-hidden="true"><i
                            data-lucide="clipboard-check"></i></span>
                    <h2>Your review will appear here</h2>
                    <div class="grade-import-empty-points"><span><i data-lucide="list-checks"></i>
                            Structure</span><span><i data-lucide="users"></i> Matching</span><span><i
                                data-lucide="triangle-alert"></i> Validation</span></div>
                </div>
            </section>
        </div>
    </main>
    <div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
            <script>window.APRISM_GRADE_IMPORT = { endpoint: <?= json_encode(APP_URL . '/actions/teacher/preview_grade_import.php') ?>, confirmEndpoint: <?= json_encode(APP_URL . '/actions/teacher/confirm_grade_import.php') ?>, analyticsUrl: <?= json_encode(APP_URL . '/dashboard/teacher_grade_analytics.php') ?>, periods: <?= json_encode($periodsByContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> };</script>
    <script src="<?= APP_URL ?>/assets/js/pages/teacher-grade-import.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/teacher-grade-import.js') ?>"></script>
    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>

</html>
