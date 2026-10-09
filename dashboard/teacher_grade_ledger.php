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
require_once __DIR__ . '/../includes/helper/grade_ledger_helper.php';

$pageTitle = 'Grade Ledger';
$activePage = 'grade_ledger';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-grade-ledger.css';
$additionalStylesheets = ['assets/css/pages/teacher-grade-workspace.css'];
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$classId = max(0, (int) ($_GET['class_id'] ?? 0));
$periodId = max(0, (int) ($_GET['period_id'] ?? 0));
$view = ($_GET['view'] ?? 'ledger') === 'history' ? 'history' : 'ledger';
$gradeClasses = aprismGradeTeacherClasses($pdo, $teacherId);
$workspaceContext = aprismGradeWorkspaceContext($pdo, $teacherId, $classId, $periodId);
$class = $workspaceContext['class'];
$periods = $workspaceContext['periods'];
$classId = (int) $workspaceContext['class_id'] ?: $classId;
$periodId = (int) $workspaceContext['period_id'];
$ledger = ($view === 'ledger' && $class && $periodId) ? aprismGradeLedger($pdo, $teacherId, $classId, $periodId) : null;
$history = $view === 'history' ? aprismGradeImportHistory($pdo, $teacherId) : [];
function gradeLedgerUrl(array $params = []): string
{
    return APP_URL . '/dashboard/teacher_grade_ledger.php?' . http_build_query($params);
}
function gradeLedgerEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="teacher-page-container grade-workspace-page grade-ledger-page">
            <?php
            $gradeWorkspaceActive = 'ledger';
            $gradeWorkspaceTitle = 'Grade Ledger';
            $gradeWorkspaceClassId = $classId;
            $gradeWorkspacePeriodId = $periodId;
            require __DIR__ . '/../includes/components/teacher_grade_workspace.php';
            ?>
            <nav class="grade-ledger-nav" aria-label="Grade workspace">
                <a class="<?= $view === 'ledger' ? 'active' : '' ?>"
                    href="<?= gradeLedgerUrl(['class_id' => $classId, 'period_id' => $periodId]) ?>">Grade Ledger</a>
                <a class="<?= $view === 'history' ? 'active' : '' ?>"
                    href="<?= gradeLedgerUrl(['class_id' => $classId, 'period_id' => $periodId, 'view' => 'history']) ?>">Import History</a>
            </nav>
            <?php if ($view === 'history'): ?>
                <section class="teacher-panel grade-ledger-panel">
                    <div class="grade-ledger-panel-heading">
                        <div>
                            <p class="teacher-eyebrow">Provenance</p>
                            <h2>Grade Import History</h2>
                        </div><span class="grade-ledger-count"><?= count($history) ?>
                            batch<?= count($history) === 1 ? '' : 'es' ?></span>
                    </div>
                    <?php if ($history === []): ?>
                        <div class="grade-ledger-empty"><i data-lucide="history"></i>
                            <h3>No confirmed imports yet</h3>
                            <p>Confirmed term workbooks will appear here with their source and class context.</p>
                        </div>
                    <?php else: ?>
                        <div class="grade-ledger-table-wrap">
                            <table class="grade-ledger-history">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Class</th>
                                        <th>School Year</th>
                                        <th>Semester</th>
                                        <th>Source</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Confirmed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $loopIndex => $batch): ?>
                                        <tr>
                                            <td><?= $loopIndex + 1 ?></td>
                                            <td><strong><?= gradeLedgerEsc($batch['subject_name'] . ' · ' . $batch['section_name']) ?></strong>
                                            </td>
                                            <td><?= gradeLedgerEsc($batch['school_year_snapshot']) ?></td>
                                            <td><?= gradeLedgerEsc($batch['semester_snapshot']) ?></td>
                                            <td><?= gradeLedgerEsc($batch['original_filename']) ?></td>
                                            <td><?= gradeLedgerEsc($batch['template_type']) ?></td>
                                            <td><span
                                                    class="grade-ledger-status <?= strtolower($batch['status']) ?>"><?= gradeLedgerEsc($batch['status']) ?></span>
                                            </td>
                                            <td><?= gradeLedgerEsc((string) ($batch['confirmed_at'] ?? '—')) ?></td>
                                        </tr><?php endforeach; ?>
                                </tbody>
                            </table>
                        </div><?php endif; ?>
                </section>
            <?php else: ?>
                <section class="teacher-panel grade-workspace-context-panel grade-ledger-panel grade-ledger-controls">
                    <div class="grade-ledger-selects"><label>Operational Class<select
                                onchange="if(this.value) window.location.href='<?= APP_URL ?>/dashboard/teacher_grade_ledger.php?class_id='+encodeURIComponent(this.value)">
                                <option value="">Select an Operational Class</option><?php foreach ($gradeClasses as $option): ?>
                                    <option value="<?= (int) $option['operational_class_id'] ?>" <?= (int) $option['operational_class_id'] === $classId ? 'selected' : '' ?>>
                                        <?= gradeLedgerEsc($option['subject_name'] . ' · ' . $option['section_name'] . ' · ' . $option['school_year']) ?>
                                    </option><?php endforeach; ?>
                            </select></label>
                        <label>Academic Period<select
                                <?= !$class || $periods === [] ? 'disabled' : '' ?>
                                onchange="if(this.value) window.location.href='<?= APP_URL ?>/dashboard/teacher_grade_ledger.php?class_id=<?= $classId ?>&period_id='+encodeURIComponent(this.value)">
                                <option value=""><?= !$class ? 'Select a class first' : ($periods === [] ? 'No imported periods' : 'Select a grading period') ?></option><?php foreach ($periods as $period): ?>
                                    <option value="<?= (int) $period['academic_period_id'] ?>" <?= (int) $period['academic_period_id'] === $periodId ? 'selected' : '' ?>>
                                        <?= gradeLedgerEsc($period['academic_level'] . ' · ' . $period['period_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></label>
                    </div>
                    <?php if ($class): ?>
                        <div class="grade-ledger-context">
                            <strong><?= gradeLedgerEsc($class['subject_name'] . ' · ' . $class['section_name']) ?></strong><span><?= gradeLedgerEsc($class['school_year'] . ' · ' . $class['semester']) ?></span>
                        </div><?php endif; ?>
                </section>
                <?php if ($classId === 0): ?>
                    <section class="teacher-panel grade-ledger-empty"><i data-lucide="book-open"></i>
                        <h3>Select a class and grading period</h3>
                        <p>Choose a Teacher-owned Operational Class and then select its available Academic Period to open the raw-score Ledger.</p>
                    </section>
                <?php elseif (!$class): ?>
                    <section class="teacher-panel grade-ledger-empty"><i data-lucide="shield-alert"></i>
                        <h3>Class access unavailable</h3>
                        <p>The requested class is not in your Teacher scope.</p>
                    </section>
                <?php elseif ($periodId === 0): ?>
                    <section class="teacher-panel grade-ledger-empty"><i data-lucide="calendar-clock"></i>
                        <h3>Select a grading period</h3>
                        <p>Available periods are limited to the selected Operational Class and remain explicitly selectable.</p>
                    </section>
                <?php elseif ($ledger === null || $ledger['period'] === null): ?>
                    <section class="teacher-panel grade-ledger-empty"><i data-lucide="calendar-clock"></i>
                        <h3>No Academic Period selected</h3>
                        <p>Choose a configured period for this class.</p>
                    </section>
                <?php elseif ($ledger['categories'] === []): ?>
                    <section class="teacher-panel grade-ledger-empty"><i data-lucide="table-2"></i>
                        <h3>No assessment structure yet</h3>
                        <p>This period has no imported assessment structure. Confirm a reviewed Grade Import first.</p>
                    </section>
                <?php else: ?>
                    <section class="teacher-panel grade-ledger-panel">
                        <div class="grade-ledger-panel-heading">
                            <div>
                                <p class="teacher-eyebrow"><?= gradeLedgerEsc($ledger['period']['academic_level']) ?></p>
                                <h2><?= gradeLedgerEsc($ledger['period']['period_name']) ?> ledger</h2>
                            </div><input class="grade-ledger-search" type="search" placeholder="Search Student Number or name"
                                aria-label="Search students" data-ledger-search>
                        </div>
                        <div class="grade-ledger-table-wrap">
                            <table class="grade-ledger-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="identity-col">Student Number</th>
                                        <th rowspan="2" class="identity-col student-name-col">Student Name</th>
                                        <?php foreach ($ledger['categories'] as $category): ?>
                                            <th colspan="<?= count($category['components']) ?>">
                                                <?= gradeLedgerEsc($category['name']) ?></th><?php endforeach; ?>
                                    </tr>
                                    <tr><?php foreach ($ledger['categories'] as $category) {
                                        foreach ($category['components'] as $component) { ?>
                                                <th title="<?= gradeLedgerEsc($component['name']) ?>">
                                                    <?= gradeLedgerEsc($component['code']) ?></th><?php }
                                    } ?>
                                    </tr>
                                    <tr class="max-score-row">
                                        <th colspan="2">Maximum score</th>
                                        <?php foreach ($ledger['components'] as $component): ?>
                                            <th><?= gradeLedgerEsc((string) $component['max_score']) ?></th><?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ledger['students'] as $student): ?>
                                        <tr data-ledger-row>
                                            <td class="identity-col"><?= gradeLedgerEsc($student['student_number']) ?></td>
                                            <td class="identity-col student-name-col">
                                                <?= gradeLedgerEsc($student['student_name']) ?></td>
                                            <?php foreach ($ledger['components'] as $component): ?>
                                                <td><?php $key = $student['student_class_enrollment_id'] . '|' . $component['id'];
                                                if (array_key_exists($key, $ledger['scores'])): ?><?= gradeLedgerEsc((string) $ledger['scores'][$key]) ?><?php else: ?><span
                                                            class="score-empty">—</span><?php endif; ?></td><?php endforeach; ?>
                                        </tr><?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <script src="<?= APP_URL ?>/assets/js/pages/teacher-grade-ledger.js"></script>
    <?php require __DIR__ . '/../includes/components/logout_modal.php';
    require __DIR__ . '/../includes/components/footer.php'; ?>
</body>

</html>
