<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../auth/csrf_helper.php';

$allowedRoles = [
    ROLE_TEACHER,
];

require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Class List';
$activePage = 'my_classes';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-class-list.css';

$operationalClassId = filter_var(
    $_GET['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($operationalClassId === false) {
    $_SESSION['warning_message'] =
        'Select an operational class before opening its Class List.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/teacher_my_class.php'
    );

    exit;
}

$operationalClassId = (int) $operationalClassId;
$classContext = null;
$classSchedules = [];
$currentSchoolYear = null;

try {
    $classStmt = $pdo->prepare("
        SELECT
            oc.operational_class_id,
            oc.school_year,
            oc.semester,
            oc.status,
            active_sy.school_year AS active_school_year,
            sub.subject_code,
            sub.subject_name,
            sec.section_name
        FROM operational_classes AS oc
        INNER JOIN school_years AS active_sy
            ON active_sy.school_year = oc.school_year
           AND active_sy.status = 'Active'
        INNER JOIN subjects AS sub
            ON sub.subject_id = oc.subject_id
        INNER JOIN sections AS sec
            ON sec.section_id = oc.section_id
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
    ");

    $classStmt->execute([
        $operationalClassId,
        (int) $_SESSION['user_id'],
    ]);

    $classContext = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($classContext === null) {
        $_SESSION['error_message'] =
            'The selected operational class is unavailable or you do not have permission to manage it.';

        header(
            'Location: ' .
            APP_URL .
            '/dashboard/teacher_my_class.php'
        );

        exit;
    }

    $currentSchoolYear = (string) $classContext['active_school_year'];

    $scheduleStmt = $pdo->prepare("
        SELECT
            day,
            start_time,
            end_time,
            room
        FROM class_schedules
        WHERE operational_class_id = ?
          AND status = 'Active'
        ORDER BY
            FIELD(
                day,
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday'
            ),
            start_time,
            class_schedule_id
    ");

    $scheduleStmt->execute([
        $operationalClassId,
    ]);

    foreach ($scheduleStmt->fetchAll(PDO::FETCH_ASSOC) as $schedule) {
        $scheduleText =
            (string) $schedule['day'] .
            ' ' .
            date('h:i A', strtotime((string) $schedule['start_time'])) .
            ' - ' .
            date('h:i A', strtotime((string) $schedule['end_time']));

        $room = trim((string) ($schedule['room'] ?? ''));

        if ($room !== '') {
            $scheduleText .= ' · ' . $room;
        }

        $classSchedules[] = $scheduleText;
    }
} catch (PDOException $e) {
    error_log(
        '[APRISM Teacher Class List Workspace] ' .
        $e->getMessage()
    );

    $_SESSION['error_message'] =
        'The Class List workspace could not be loaded. Please try again.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/teacher_my_class.php'
    );

    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <div class="content-wrapper">

            <div class="page-header">

                <div class="page-header-left teacher-class-workspace-heading">

                    <a class="teacher-class-workspace-back" href="<?= APP_URL ?>/dashboard/teacher_my_class.php"
                        aria-label="Back to My Classes" title="Back to My Classes">
                        <i data-lucide="arrow-left" aria-hidden="true"></i>
                    </a>

                    <div class="teacher-class-workspace-title-block">
                        <h1 class="teacher-page-title">Class List</h1>
                    </div>

                </div>

                <div class="page-header-right">

                    <button type="button" class="teacher-primary-btn" data-class-list-import-open>
                        <i data-lucide="upload"></i>
                        <span>Import Class List</span>
                    </button>

                </div>

            </div>

            <section class="teacher-class-context-strip" aria-label="Class context">

                <div class="teacher-class-context-item teacher-class-context-primary">
                    <span class="teacher-class-context-label">Subject</span>
                    <strong><?= htmlspecialchars((string) $classContext['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($classContext['subject_code'])): ?>
                        <small><?= htmlspecialchars((string) $classContext['subject_code'], ENT_QUOTES, 'UTF-8') ?></small>
                    <?php endif; ?>
                </div>

                <div class="teacher-class-context-item">
                    <span class="teacher-class-context-label">Section</span>
                    <strong><?= htmlspecialchars((string) $classContext['section_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="teacher-class-context-item">
                    <span class="teacher-class-context-label">School Year</span>
                    <strong><?= htmlspecialchars((string) $classContext['school_year'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="teacher-class-context-item">
                    <span class="teacher-class-context-label">Semester</span>
                    <strong><?= htmlspecialchars((string) $classContext['semester'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="teacher-class-context-item teacher-class-context-schedules">
                    <span class="teacher-class-context-label">Active schedule</span>
                    <div class="teacher-class-schedule-list">
                        <?php if (empty($classSchedules)): ?>
                            <span class="teacher-class-schedule-chip is-muted">
                                <i data-lucide="calendar-x" aria-hidden="true"></i>
                                No active schedule
                            </span>
                        <?php else: ?>
                            <?php foreach ($classSchedules as $scheduleText): ?>
                                <span class="teacher-class-schedule-chip">
                                    <i data-lucide="clock-3" aria-hidden="true"></i>
                                    <?= htmlspecialchars($scheduleText, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </section>

            <div class="teacher-info-box teacher-class-list-guidance">
                <i data-lucide="info"></i>
                <p class="teacher-info-text">
                    Import uses a review-first flow. Student identity and class participation can be confirmed without academic placement; add placement only when explicit and legitimately known.
                </p>
            </div>

            <section class="teacher-panel teacher-class-list-panel">

                <div class="teacher-panel-header">

                    <div>
                        <h2 class="teacher-panel-title">
                            Enrolled Students
                        </h2>
                        <p class="teacher-panel-subtitle" data-class-list-summary>
                            Current roster records for this Operational Class.
                        </p>
                    </div>

                    <span class="teacher-badge teacher-badge-info" data-class-list-count>
                        —
                    </span>

                </div>

                <div class="teacher-toolbar">

                    <div class="teacher-search teacher-my-classes-search">
                        <i data-lucide="search"></i>
                        <input type="text" placeholder="Search student number or name..." aria-label="Search Class List"
                            data-class-list-search>
                    </div>

                </div>

                <div class="teacher-table-wrapper">

                    <table class="teacher-table">

                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Academic Context</th>
                                <th>Class Status</th>
                                <th>Enrolled At</th>
                            </tr>
                        </thead>

                        <tbody data-class-list-body>
                            <tr>
                                <td colspan="4">
                                    <div class="teacher-empty-state">
                                        <i data-lucide="loader-circle"></i>
                                        <h3 class="teacher-empty-state-title">
                                            Loading Class List
                                        </h3>
                                        <p class="teacher-empty-state-text">
                                            APRISM is reading the current participants for this class.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                    </table>

                </div>

                <div class="teacher-pagination teacher-class-list-pagination" data-class-list-pagination hidden>
                    <div class="teacher-pagination-info" data-class-list-page-summary></div>
                    <nav class="teacher-pagination-controls" aria-label="Class List pagination"
                        data-class-list-page-controls></nav>
                </div>

            </section>

        </div>

    </main>

    <div class="teacher-import-modal" id="teacherClassListImportModal" aria-hidden="true">

        <div class="teacher-import-modal-backdrop" data-class-list-import-close></div>

        <div class="teacher-import-modal-dialog" role="dialog" aria-modal="true"
            aria-labelledby="teacherClassListImportTitle">

            <div class="teacher-import-modal-header">
                <div>
                    <h2 class="teacher-import-modal-title" id="teacherClassListImportTitle">
                        Import Class List
                    </h2>
                    <p class="teacher-import-modal-description">
                        Upload the official roster for this Operational Class for secure file preflight.
                    </p>
                </div>

                <button type="button" class="teacher-import-modal-close" aria-label="Close Import Class List"
                    data-class-list-import-close>
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="teacher-import-stepper" data-class-list-step-indicator aria-label="Import progress">
                <span data-import-progress-step="source">Source</span>
                <span data-import-progress-step="review">Review</span>
                <span data-import-progress-step="resolve">Resolve</span>
                <span data-import-progress-step="confirm">Confirm</span>
            </div>

            <div class="teacher-import-modal-body">

                <form data-class-list-import-form enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="operational_class_id" value="<?= (int) $operationalClassId ?>">

                    <div data-class-list-import-upload-step>
                        <div class="teacher-import-modal-feedback" data-class-list-upload-feedback aria-live="polite"
                            hidden></div>
                        <div class="teacher-import-review-note">
                            <i data-lucide="shield-check"></i>
                            <p>
                                APRISM extracts the source only for structure confirmation, mapping, review,
                                and resolution. No Student or enrollment record is created at this stage.
                            </p>
                        </div>

                        <div class="teacher-import-form-field">
                            <label for="classListFile">Official roster file</label>
                            <input id="classListFile" class="teacher-class-list-file-input" type="file"
                                name="class_list_file" accept=".xlsx,.xls,.csv" required data-class-list-file>
                            <label for="classListFile" class="teacher-class-list-upload-control"><span
                                    class="teacher-class-list-upload-icon"><i
                                        data-lucide="upload-cloud"></i></span><span
                                    class="teacher-class-list-upload-copy"><strong>Choose roster
                                        file</strong><span>Excel (.xlsx, .xls) or CSV (.csv) · up to 10
                                        MB</span></span><span
                                    class="teacher-class-list-upload-action">Browse</span></label>
                            <span class="teacher-class-list-file-name" data-class-list-file-name aria-live="polite">No
                                file selected</span>
                        </div>

                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel" data-class-list-import-close>
                                Cancel
                            </button>

                            <button type="submit" class="teacher-primary-btn" data-class-list-import-submit>
                                <i data-lucide="upload"></i>
                                <span>Extract Source</span>
                            </button>
                        </div>

                    </div>

                    <div hidden data-class-list-import-source-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Review &amp; Fix</span>
                            <h3>Check the source details APRISM could not confirm</h3>
                            <p>Only the fields below need attention before APRISM can review the roster.</p>
                        </div>
                        <div class="teacher-import-modal-feedback" data-class-list-modal-feedback aria-live="polite">
                        </div>
                        <div class="teacher-class-list-source-card" data-class-list-source-summary></div>
                        <div class="teacher-class-list-source-card" data-class-list-source-sheets></div>
                        <div class="teacher-class-list-structure-controls">
                            <label>Worksheet<select data-class-list-worksheet></select></label>
                            <label>Header Row<input type="number" min="1" data-class-list-header-row></label>
                            <label>First Student Row<input type="number" min="2" data-class-list-first-data-row></label>
                        </div>
                        <div class="teacher-class-list-source-preview" data-class-list-source-preview></div>
                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-choose-another>Choose Another File</button>
                            <button type="button" class="teacher-primary-btn" data-class-list-import-mapping-open><i
                                    data-lucide="arrow-right"></i><span>Continue to Review &amp; Fix</span></button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-mapping-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Review &amp; Fix</span>
                            <h3>Choose the missing source fields</h3>
                            <p>APRISM could not safely identify every required roster field.</p>
                        </div>
                        <div class="teacher-import-review-note">
                            <i data-lucide="shield-check"></i>
                            <p>Source columns are shown without assumed meanings. If this is not a roster, choose
                                another file instead of mapping unrelated columns.</p>
                        </div>
                        <div data-class-list-mapping-errors aria-live="polite"></div>
                        <div class="teacher-class-list-mapping-grid" data-class-list-mapping-fields></div>
                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-back-source>Back to Source Preview</button>
                            <button type="button" class="teacher-primary-btn" data-class-list-import-review-open><i
                                    data-lucide="eye"></i><span>Review Normalized Rows</span></button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-review-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Step 3 of 4</span>
                            <h3>Review &amp; Resolve</h3>
                            <p>One mapping has been applied to every detected row shown below.</p>
                        </div>

                        <div class="teacher-import-review-note">
                            <i data-lucide="circle-alert"></i>
                            <p>
                                This is a structural validation preview only. No Students, Academic
                                Enrollments, or Student Class Enrollments will be created or updated.
                            </p>
                        </div>

                        <div data-class-list-review-summary></div>
                        <div data-class-list-review-validation aria-live="polite"></div>
                        <div class="teacher-table-wrapper" data-class-list-review-table></div>

                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-back-mapping>
                                Back to Mapping
                            </button>

                            <button type="button" class="teacher-primary-btn" data-class-list-import-resolution-open>
                                <i data-lucide="scan-search"></i>
                                <span>Check Resolution Preview</span>
                            </button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-resolution-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Step 3 of 4</span>
                            <h3>Review &amp; Resolve</h3>
                            <p>
                                APRISM checked the imported source against existing Student,
                                Academic Enrollment, and Class Enrollment records.
                            </p>
                        </div>

                        <div class="teacher-import-review-note">
                            <i data-lucide="shield-check"></i>
                            <p>
                                This is still a SELECT-only preview. No Student or enrollment record
                                has been created, updated, enrolled, removed, or archived.
                            </p>
                        </div>

                        <div data-class-list-resolution-summary></div>
                        <div data-class-list-resolution-validation aria-live="polite"></div>
                        <div class="teacher-table-wrapper" data-class-list-resolution-table></div>

                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel" data-class-list-import-back-review>
                                Back to Review
                            </button>

                            <button type="button" class="teacher-primary-btn" data-class-list-import-identity-open>
                                <i data-lucide="user-round-pen"></i>
                                <span>Review New Student Identities</span>
                            </button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-identity-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Step 3 of 4</span>
                            <h3>Review &amp; Resolve — New Student Identity</h3>
                            <p>
                                Complete only the structured identity details required for unresolved
                                names. Source values remain evidence and are not changed.
                            </p>
                        </div>

                        <div class="teacher-import-review-note">
                            <i data-lucide="shield-check"></i>
                            <p>
                                This remains a SELECT-only readiness check. APRISM will not split a
                                combined name automatically or create any Student or enrollment record.
                            </p>
                        </div>

                        <div data-class-list-identity-summary></div>
                        <div data-class-list-identity-fields></div>

                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-back-resolution>
                                Back to Resolution Preview
                            </button>

                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-academic-evidence-open>
                                Review Academic Context Evidence
                            </button>

                            <button type="button" class="teacher-primary-btn" data-class-list-import-identity-recheck>
                                <i data-lucide="scan-search"></i>
                                <span>Recheck Identity Completion</span>
                            </button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-academic-evidence-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Step 3 of 4</span>
                            <h3>Review &amp; Resolve — Academic Context</h3>
                            <p>
                                Review the selected Operational Class, mapped source values, and any
                                existing Academic Enrollment evidence. No academic context is selected,
                                created, or changed in this step.
                            </p>
                        </div>

                        <div class="teacher-import-review-note">
                            <i data-lucide="shield-check"></i>
                            <p>
                                The Operational Class is a teaching reference only. APRISM does not
                                infer a Student's Program, Section, Year Level, Semester, or academic
                                placement from it or from a Section name.
                            </p>
                        </div>

                        <div data-class-list-academic-evidence-summary></div>
                        <div class="teacher-table-wrapper" data-class-list-academic-evidence-table></div>

                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-back-identity>
                                Back to Identity Completion
                            </button>

                            <button type="button" class="teacher-primary-btn" data-class-list-import-plan-open>
                                <i data-lucide="clipboard-check"></i>
                                <span>Prepare Import Plan</span>
                            </button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-plan-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Step 2 of 3</span>
                            <h3>Review &amp; Fix</h3>
                            <p>APRISM has prepared the server-validated roster plan. Review only rows that need a
                                decision or correction.</p>
                        </div>

                        <div class="teacher-import-review-note">
                            <i data-lucide="shield-check"></i>
                            <p>Suggestions and decisions remain temporary until final confirmation. The Operational
                                Class is teaching context only and never supplies a Student's academic placement.</p>
                        </div>

                        <div data-class-list-import-plan-summary></div>
                        <div data-class-list-import-plan-validation aria-live="polite"></div>
                        <div class="teacher-table-wrapper" data-class-list-import-plan-table></div>

                        <p class="teacher-import-plan-action-note" data-class-list-plan-action-note aria-live="polite"></p>

                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-back-source-setup>
                                Source Setup
                            </button>
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-plan-recheck>
                                Revalidate Review
                            </button>
                            <button type="button" class="teacher-primary-btn" disabled aria-disabled="true"
                                data-class-list-import-review-confirm>
                                <i data-lucide="arrow-right"></i>
                                <span>Continue to Confirm</span>
                            </button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-confirm-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Step 3 of 3</span>
                            <h3>Confirm Import</h3>
                            <p>APRISM will rebuild and validate this source on the server before saving the listed
                                actions transactionally.</p>
                        </div>
                        <div class="teacher-import-review-note">
                            <i data-lucide="shield-check"></i>
                            <p>No record has been changed yet. Confirmation creates or reuses only the validated
                                Students, Academic Enrollments, and Class Enrollments shown below.</p>
                        </div>
                        <div data-class-list-confirm-summary></div>
                        <div class="teacher-table-wrapper" data-class-list-confirm-table></div>
                        <div class="teacher-import-form-actions">
                            <button type="button" class="teacher-import-form-cancel"
                                data-class-list-import-back-review-fix>Back to Review &amp; Fix</button>
                            <button type="button" class="teacher-primary-btn" data-class-list-import-confirm-final>
                                <i data-lucide="check-circle-2"></i><span>Confirm Import</span>
                            </button>
                        </div>
                    </div>
                </form>

            </div>

        </div>

    </div>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const operationalClassId = <?= json_encode($operationalClassId) ?>;
            const endpoint = `${window.APP_URL}/actions/teacher/get_class_list.php?operational_class_id=${encodeURIComponent(operationalClassId)}`;
            const tableBody = document.querySelector('[data-class-list-body]');
            const countBadge = document.querySelector('[data-class-list-count]');
            const summary = document.querySelector('[data-class-list-summary]');
            const searchInput = document.querySelector('[data-class-list-search]');
            const pagination = document.querySelector('[data-class-list-pagination]');
            const pageSummary = document.querySelector('[data-class-list-page-summary]');
            const pageControls = document.querySelector('[data-class-list-page-controls]');
            const importModal = document.getElementById('teacherClassListImportModal');
            const importOpenButton = document.querySelector('[data-class-list-import-open]');
            const importCloseButtons = document.querySelectorAll('[data-class-list-import-close]');
            const importForm = document.querySelector('[data-class-list-import-form]');
            const importFileInput = document.querySelector('[data-class-list-file]');
            const importFileName = document.querySelector('[data-class-list-file-name]');
            const uploadStep = document.querySelector('[data-class-list-import-upload-step]');
            const sourceStep = document.querySelector('[data-class-list-import-source-step]');
            const mappingStep = document.querySelector('[data-class-list-import-mapping-step]');
            const reviewStep = document.querySelector('[data-class-list-import-review-step]');
            const resolutionStep = document.querySelector('[data-class-list-import-resolution-step]');
            const identityStep = document.querySelector('[data-class-list-import-identity-step]');
            const academicEvidenceStep = document.querySelector(
                '[data-class-list-import-academic-evidence-step]'
            );
            const importPlanStep = document.querySelector(
                '[data-class-list-import-plan-step]'
            );
            const confirmStep = document.querySelector(
                '[data-class-list-import-confirm-step]'
            );
            const sourceSummary = document.querySelector('[data-class-list-source-summary]');
            const sourceSheets = document.querySelector('[data-class-list-source-sheets]');
            const sourcePreview = document.querySelector('[data-class-list-source-preview]');
            const worksheetSelect = document.querySelector('[data-class-list-worksheet]');
            const headerRowInput = document.querySelector('[data-class-list-header-row]');
            const firstDataRowInput = document.querySelector('[data-class-list-first-data-row]');
            const modalFeedback = document.querySelector('[data-class-list-modal-feedback]');
            const mappingFields = document.querySelector('[data-class-list-mapping-fields]');
            const mappingErrors = document.querySelector('[data-class-list-mapping-errors]');
            const reviewSummary = document.querySelector('[data-class-list-review-summary]');
            const reviewValidation = document.querySelector('[data-class-list-review-validation]');
            const reviewTable = document.querySelector('[data-class-list-review-table]');
            const resolutionSummary = document.querySelector('[data-class-list-resolution-summary]');
            const resolutionValidation = document.querySelector('[data-class-list-resolution-validation]');
            const resolutionTable = document.querySelector('[data-class-list-resolution-table]');
            const identitySummary = document.querySelector('[data-class-list-identity-summary]');
            const identityFields = document.querySelector('[data-class-list-identity-fields]');
            const academicEvidenceSummary = document.querySelector(
                '[data-class-list-academic-evidence-summary]'
            );
            const academicEvidenceTable = document.querySelector(
                '[data-class-list-academic-evidence-table]'
            );
            const importPlanSummary = document.querySelector(
                '[data-class-list-import-plan-summary]'
            );
            const importPlanValidation = document.querySelector(
                '[data-class-list-import-plan-validation]'
            );
            const importPlanTable = document.querySelector(
                '[data-class-list-import-plan-table]'
            );
            const confirmSummary = document.querySelector('[data-class-list-confirm-summary]');
            const confirmTable = document.querySelector('[data-class-list-confirm-table]');
            const resolutionOpenButton = document.querySelector(
                '[data-class-list-import-resolution-open]'
            );
            const backReviewButton = document.querySelector(
                '[data-class-list-import-back-review]'
            );
            const identityOpenButton = document.querySelector(
                '[data-class-list-import-identity-open]'
            );
            const identityRecheckButton = document.querySelector(
                '[data-class-list-import-identity-recheck]'
            );
            const backResolutionButton = document.querySelector(
                '[data-class-list-import-back-resolution]'
            );
            const academicEvidenceOpenButton = document.querySelector(
                '[data-class-list-import-academic-evidence-open]'
            );
            const backIdentityButton = document.querySelector(
                '[data-class-list-import-back-identity]'
            );
            const importPlanOpenButton = document.querySelector(
                '[data-class-list-import-plan-open]'
            );
            const backAcademicEvidenceButton = document.querySelector(
                '[data-class-list-import-back-academic-evidence]'
            );
            const importPlanRecheckButton = document.querySelector(
                '[data-class-list-import-plan-recheck]'
            );
            const importConfirmButton = document.querySelector(
                '[data-class-list-import-confirm-final]'
            );
            const reviewConfirmButton = document.querySelector(
                '[data-class-list-import-review-confirm]'
            );
            const mappingReviewButton = document.querySelector('[data-class-list-import-review-open]');
            const mappingOpenButton = document.querySelector('[data-class-list-import-mapping-open]');
            const chooseAnotherButton = document.querySelector('[data-class-list-import-choose-another]');
            const backSourceButton = document.querySelector('[data-class-list-import-back-source]');
            const backMappingButton = document.querySelector('[data-class-list-import-back-mapping]');
            const backSourceSetupButton = document.querySelector('[data-class-list-import-back-source-setup]');
            const backReviewFixButton = document.querySelector('[data-class-list-import-back-review-fix]');

            let students = [];
            const rosterPageSize = 25;
            let currentRosterPage = 1;
            let sourceExtraction = null;
            let sourceToken = '';
            let resolutionPreview = null;
            let importPlanPreview = null;
            let currentImportStep = 'upload';
            let academicContextDecisionOverrides = {};
            let identityOverrideState = {};
            let identityReconciliationState = {};
            let bulkContextState = {};
            const canonicalFields = [
                ['student_number', 'Student Number', false],
                ['student_name_raw', 'Student Name (combined)', false],
                ['first_name', 'First Name', false],
                ['middle_name', 'Middle Name', false],
                ['last_name', 'Last Name', false],
                ['suffix', 'Suffix', false],
                ['program', 'Program', false],
                ['section', 'Section', false],
                ['year_level', 'Year Level', false],
            ];

            const uploadFeedback = document.querySelector('[data-class-list-upload-feedback]');
            const toastContainer = document.getElementById('toastContainer');

            const showToast = (title, text, type = 'success') => {
                if (!toastContainer || !text) {
                    return;
                }

                const toast = document.createElement('div');
                toast.className = 'toast-custom';
                const icon = type === 'warning' ? 'alert-circle' : 'check';

                toast.innerHTML = `
                    <div class="toast-icon ${type}">
                        <i data-lucide="${icon}"></i>
                    </div>
                    <div class="toast-content">
                        <h5 class="toast-title"></h5>
                        <p class="toast-text"></p>
                    </div>
                `;
                toast.querySelector('.toast-title').textContent = title;
                toast.querySelector('.toast-text').textContent = text;
                toastContainer.appendChild(toast);
                window.lucide?.createIcons();
                setTimeout(() => toast.classList.add('show'), 10);
                setTimeout(() => toast.remove(), 4800);
            };

            window.aprismShowToast = showToast;

            const showInlineImportFeedback = (message, type = 'error') => {
                if (!uploadFeedback) {
                    return;
                }

                uploadFeedback.className =
                    `teacher-import-modal-feedback teacher-import-modal-feedback-${type}`;
                uploadFeedback.textContent = message;
                uploadFeedback.hidden = false;
            };

            const showToastMessage = (message, type = 'info') => {
                const titles = {
                    success: 'Success',
                    error: 'Action Failed',
                    warning: 'Warning',
                    info: 'Information',
                };

                if (typeof window.aprismShowToast === 'function') {
                    window.aprismShowToast(
                        titles[type] || titles.info,
                        message,
                        type === 'error' ? 'warning' : type
                    );
                    return;
                }

                const feedbackTarget = uploadStep && !uploadStep.hidden
                    ? uploadFeedback
                    : modalFeedback;

                if (feedbackTarget) {
                    feedbackTarget.className =
                        `teacher-import-modal-feedback teacher-import-modal-feedback-${type}`;
                    feedbackTarget.textContent = message;
                    feedbackTarget.hidden = false;
                }
            };

            const openImportModal = () => {
                if (!importModal) {
                    return;
                }

                if (uploadFeedback) uploadFeedback.hidden = true;
                if (modalFeedback) modalFeedback.hidden = true;
                importModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('teacher-import-modal-open');
                showImportStep('upload');
                window.setTimeout(() => importFileInput?.focus(), 0);
            };

            const closeImportModal = () => {
                if (!importModal) {
                    return;
                }

                importModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('teacher-import-modal-open');
                importForm?.reset();
                if (importFileName) importFileName.textContent = 'No file selected';
                if (uploadFeedback) uploadFeedback.hidden = true;
                if (modalFeedback) modalFeedback.hidden = true;
                sourceExtraction = null;
                sourceToken = '';
                resolutionPreview = null;
                importPlanPreview = null;
                academicContextDecisionOverrides = {};
                identityOverrideState = {};
                identityReconciliationState = {};
                bulkContextState = {};
                showImportStep('upload');
            };

            const showImportStep = (step) => {
                currentImportStep = step;
                if (uploadStep) uploadStep.hidden = step !== 'upload';
                if (sourceStep) sourceStep.hidden = step !== 'source';
                if (mappingStep) mappingStep.hidden = step !== 'mapping';
                if (reviewStep) reviewStep.hidden = step !== 'review';
                if (resolutionStep) resolutionStep.hidden = step !== 'resolution';
                if (identityStep) identityStep.hidden = step !== 'identity';
                if (academicEvidenceStep) academicEvidenceStep.hidden = step !== 'academic-evidence';
                if (importPlanStep) importPlanStep.hidden = step !== 'plan';
                if (confirmStep) confirmStep.hidden = step !== 'confirm';
                const phaseByStep = {
                    upload: 'source',
                    source: 'review',
                    mapping: 'review',
                    review: 'review',
                    resolution: 'resolve',
                    identity: 'resolve',
                    'academic-evidence': 'resolve',
                    plan: 'resolve',
                    confirm: 'confirm',
                };
                const phaseOrder = ['source', 'review', 'resolve', 'confirm'];
                const currentPhase = phaseOrder.indexOf(phaseByStep[currentImportStep] || 'source');
                const stepIndicator = document.querySelector('[data-class-list-step-indicator]');
                stepIndicator?.setAttribute('data-current-import-phase', phaseByStep[currentImportStep] || 'source');
                document.querySelectorAll('[data-import-progress-step]').forEach((indicator) => {
                    const stage = indicator.getAttribute('data-import-progress-step');
                    const stageIndex = phaseOrder.indexOf(stage);
                    const state = stageIndex === currentPhase
                        ? 'active'
                        : stageIndex < currentPhase
                            ? 'complete'
                            : 'future';
                    indicator.classList.remove('is-active', 'is-complete');
                    indicator.classList.add(`is-${state}`);
                    indicator.setAttribute('data-progress-state', state);
                });
                if (window.lucide) window.lucide.createIcons();
            };

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
            }[character]));

            const normalizedValue = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();

            const displaySourceContext = (context) => {
                const values = context?.values || context || {};
                const program = values.program || values.program_code || values.program_name;
                const rows = [
                    ['Academic Level', values.academic_level],
                    ['Program', program],
                    ['Section', values.section],
                    ['Year Level', values.year_level],
                    ['School Year', values.school_year],
                    ['Semester', values.semester],
                ].filter(([, value]) => normalizedValue(value) !== '');

                if (rows.length === 0) {
                    return '<section class="teacher-class-list-source-card teacher-class-list-source-context"><h4>Detected roster context</h4><p>No explicitly labelled source-level academic placement metadata was detected.</p></section>';
                }

                const conflictText = Array.isArray(context?.conflicts) && context.conflicts.length > 0
                    ? '<p class="teacher-class-list-source-context-warning"><strong>Review required:</strong> the source contains conflicting explicit context values.</p>'
                    : '';
                const rowText = context?.applies_to_rows
                    ? `<p>Applies to <strong>${escapeHtml(context.applies_to_rows)}</strong> detected roster rows.</p>`
                    : '<p>Review this context before confirmation. It is never inferred from the worksheet name or Operational Class.</p>';

                return `<section class="teacher-class-list-source-card teacher-class-list-source-context"><h4>Detected roster context</h4><dl>${rows.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}</dl>${rowText}${conflictText}</section>`;
            };

            const readApiResponse = async (response, fallbackMessage) => {
                const body = (await response.text()).replace(/^\uFEFF/, '').trim();

                if (body === '') {
                    throw new Error(fallbackMessage);
                }

                try {
                    const result = JSON.parse(body);

                    if (!result || typeof result !== 'object') {
                        throw new Error();
                    }

                    return result;
                } catch {
                    throw new Error(fallbackMessage);
                }
            };

            const buildMappingFields = () => {
                if (!sourceExtraction || !mappingFields) return;
                const headers = Array.isArray(sourceExtraction.headers) ? sourceExtraction.headers : [];
                mappingFields.innerHTML = canonicalFields.map(([key, label, required]) => `
                    <div class="teacher-import-form-field ${required ? 'is-required' : 'is-optional'}">
                        <label for="classListMap_${key}">${escapeHtml(label)} <span>${required ? 'Required' : 'Optional'}</span></label>
                        <select id="classListMap_${key}" data-class-list-map-field="${key}">
                            <option value="">Not mapped</option>
                            ${headers.map((header) => `<option value="${escapeHtml(header.column_index)}">${escapeHtml(header.label || header.column_letter || 'Column')}</option>`).join('')}
                        </select>
                    </div>`).join('');
                if (mappingErrors) mappingErrors.textContent = '';
            };

            const selectedMappings = () => Object.fromEntries(canonicalFields.map(([key]) => [
                key, document.querySelector(`[data-class-list-map-field="${key}"]`)?.value || ''
            ]));

            const normalizedHeader = (value) => normalizedValue(value)
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, ' ')
                .trim();

            /* Conservative, label-only assistance. A missing confident match keeps Source Setup visible. */
            const applyAutomaticMappings = () => {
                if (!sourceExtraction || !mappingFields) return selectedMappings();

                const headers = Array.isArray(sourceExtraction.headers) ? sourceExtraction.headers : [];
                const suggested = sourceExtraction.analysis?.automatic_mapping || {};
                const patterns = {
                    student_number: [/^student (no|number)$/i, /^student id$/i, /^id number$/i, /^id$/i],
                    student_name_raw: [/^student name$/i, /^full name$/i, /^name$/i],
                    first_name: [/^first name$/i, /^given name$/i],
                    middle_name: [/^middle name$/i],
                    last_name: [/^(last|family|surname) name$/i],
                    suffix: [/^suffix$/i],
                    program: [/^program$/i, /^course$/i, /^program and plan$/i],
                    section: [/^section$/i],
                    year_level: [/^year level$/i, /^year$/i, /^level$/i],
                };

                Object.entries(patterns).forEach(([field, fieldPatterns]) => {
                    const select = mappingFields.querySelector(`[data-class-list-map-field="${field}"]`);
                    if (!select || select.value) return;
                    if (suggested[field]) {
                        select.value = String(suggested[field]);
                        return;
                    }
                    const match = headers.find((header) => fieldPatterns.some((pattern) => pattern.test(
                        normalizedHeader(header.raw_label || header.label || '')
                    )));
                    if (match) select.value = String(match.column_index);
                });

                return selectedMappings();
            };

            const mappingProblemList = (mappings) => {
                const problems = canonicalFields.filter(([key, , required]) => required && !mappings[key])
                    .map(([, label]) => `${label} must be mapped.`);
                if (!mappings.student_name_raw && (!mappings.first_name || !mappings.last_name)) {
                    problems.push('Map Student Name (combined), or both First Name and Last Name.');
                }
                const seen = new Map();
                Object.entries(mappings).filter(([, column]) => column).forEach(([key, column]) => {
                    if (seen.has(column)) problems.push('A source column can only be mapped to one APRISM field.');
                    seen.set(column, key);
                });
                return problems;
            };

            const sourceRows = (mappings) => (sourceExtraction?.sample_rows || []).map((sourceRow) => {
                const values = sourceRow.values || {};
                const record = { source_row_number: sourceRow.row_number };
                Object.entries(mappings).forEach(([field, column]) => { record[field] = column ? normalizedValue(values[column]) : ''; });
                const errors = canonicalFields.filter(([key, , required]) => required && !record[key])
                    .map(([, label]) => `${label} is required`);
                if (!record.student_name_raw && (!record.first_name || !record.last_name)) {
                    errors.push('A combined or structured student name is required');
                }
                return { record, errors };
            });

            const showSourcePreview = (data, openCorrection = false) => {
                sourceExtraction = data;
                sourceToken = data.source_token || sourceToken;
                resolutionPreview = null;
                const sheet = data.selected_sheet || {};
                const headers = Array.isArray(data.headers) ? data.headers : [];
                const rows = Array.isArray(data.sample_rows) ? data.sample_rows : [];
                if (sourceSummary) sourceSummary.innerHTML = `<dl><div><dt>File</dt><dd>${escapeHtml(data.source?.original_name || 'Uploaded file')}</dd></div><div><dt>Selected worksheet</dt><dd>${escapeHtml(sheet.name || '—')}</dd></div><div><dt>Detected header row</dt><dd>${escapeHtml(data.header_row_number || '—')}</dd></div><div><dt>Preview rows</dt><dd>${escapeHtml(rows.length)}</dd></div></dl>`;
                if (worksheetSelect) worksheetSelect.innerHTML = (data.sheets || []).map((item) => `<option value="${escapeHtml(item.name)}" ${item.name === sheet.name ? 'selected' : ''}>${escapeHtml(item.name)}</option>`).join('');
                if (headerRowInput) { headerRowInput.value = data.header_row_number || ''; headerRowInput.max = data.max_row_number || ''; }
                if (firstDataRowInput) { firstDataRowInput.value = data.first_data_row_number || ''; firstDataRowInput.max = data.max_row_number || ''; }
                if (sourceSheets) sourceSheets.innerHTML = `<h4>Available worksheets</h4><p>${(data.sheets || []).map((item) => `<span>${escapeHtml(item.name)}</span>`).join('') || '—'}</p>`;
                const structure = data.structure_preview || { column_count: 0, rows: [] };
                if (sourcePreview) sourcePreview.innerHTML = `${displaySourceContext(data.source_context)}<h4>Detected source columns</h4><p class="teacher-class-list-column-list">${headers.map((header) => `<span>${escapeHtml(header.raw_label || header.label)}</span>`).join('') || 'No source columns detected.'}</p><h4>Raw worksheet preview</h4><div class="teacher-table-wrapper"><table class="teacher-table"><thead><tr><th>Row</th>${Array.from({ length: structure.column_count || 0 }, (_, index) => `<th>${String.fromCharCode(65 + index)}</th>`).join('')}</tr></thead><tbody>${(structure.rows || []).map((row) => `<tr><td>${escapeHtml(row.row_number)}</td>${(row.values || []).map((value) => `<td>${escapeHtml(value || '—')}</td>`).join('')}</tr>`).join('') || '<tr><td>No source rows detected.</td></tr>'}</tbody></table></div>`;
                if (modalFeedback) modalFeedback.hidden = true;
                if (openCorrection) showImportStep('source');
            };

            const updateSourceStructure = async () => {
                if (!sourceToken || !importForm) return;
                /* A changed worksheet/header/data row changes source-row meaning. */
                identityOverrideState = {};
                academicContextDecisionOverrides = {};
                const formData = new FormData(importForm);
                formData.set('operation', 'preview'); formData.set('source_token', sourceToken);
                formData.set('worksheet_name', worksheetSelect?.value || '');
                formData.set('header_row_number', headerRowInput?.value || '');
                formData.set('first_data_row_number', firstDataRowInput?.value || '');
                try {
                    const response = await fetch(`${window.APP_URL}/actions/teacher/parse_class_list.php`, { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const result = await readApiResponse(
                        response,
                        'APRISM could not read the updated source structure response. Upload the file again.'
                    );
                    if (!response.ok || result.success !== true) throw new Error(result.message || 'The source structure could not be updated.');
                    showSourcePreview(result.data, true);
                } catch (error) { showToastMessage(error instanceof Error ? error.message : 'The source structure could not be updated.', 'error'); }
            };

            const showMapping = () => {
                buildMappingFields();
                showImportStep('mapping');
            };

            const showReview = () => {
                const mappings = selectedMappings();
                const problems = mappingProblemList(mappings);
                if (problems.length) {
                    if (mappingErrors) mappingErrors.innerHTML = `<p>${problems.map(escapeHtml).join('<br>')}</p>`;
                    return;
                }
                const rows = sourceRows(mappings);
                const invalid = rows.filter((row) => row.errors.length);
                const validCount = rows.length - invalid.length;
                if (reviewSummary) reviewSummary.innerHTML = `<div class="teacher-class-list-review-counts"><span><strong>${rows.length}</strong> preview rows</span><span class="is-valid"><strong>${validCount}</strong> valid</span><span class="is-invalid"><strong>${invalid.length}</strong> invalid</span></div><p>One selected column mapping is applied to all rows in this preview. Only this bounded preview is held in the browser.</p>`;
                if (reviewValidation) reviewValidation.innerHTML = invalid.length
                    ? `<p><strong>${invalid.length} sampled row(s) need correction:</strong> ${invalid.map((row) => `row ${row.record.source_row_number}: ${row.errors.join(', ')}`).map(escapeHtml).join(' · ')}</p>`
                    : '<p><strong>Structurally Valid preview:</strong> the mapped Student Name is present. Student Number may be unavailable in the source and can be resolved later from an authoritative Grade Workbook.</p>';
                const visibleFields = canonicalFields.filter(([key]) => mappings[key]);
                if (reviewTable) reviewTable.innerHTML = `<table class="teacher-table"><thead><tr><th>Source Row</th>${visibleFields.map(([, label]) => `<th>${escapeHtml(label)}</th>`).join('')}<th>Structural Status</th></tr></thead><tbody>${rows.map(({ record, errors }) => `<tr><td>${escapeHtml(record.source_row_number)}</td>${visibleFields.map(([key]) => `<td>${escapeHtml(record[key] || '—')}</td>`).join('')}<td>${escapeHtml(errors.join('; ') || 'Structurally Valid')}</td></tr>`).join('')}</tbody></table>`;
                showImportStep('review');
            };

            const identityOverrides = () => {
                const overrides = { ...identityOverrideState };

                identityFields?.querySelectorAll('[data-class-list-identity-row]').forEach((card) => {
                    const rowNumber = card.getAttribute('data-class-list-identity-row');

                    if (!rowNumber) {
                        return;
                    }

                    overrides[rowNumber] = {
                        first_name: normalizedValue(card.querySelector('[data-identity-field="first_name"]')?.value),
                        middle_name: normalizedValue(card.querySelector('[data-identity-field="middle_name"]')?.value),
                        last_name: normalizedValue(card.querySelector('[data-identity-field="last_name"]')?.value),
                        suffix: normalizedValue(card.querySelector('[data-identity-field="suffix"]')?.value),
                    };
                });

                return overrides;
            };

            const identityReconciliations = () => {
                const decisions = { ...identityReconciliationState };
                document.querySelectorAll('[data-class-list-reconcile-row]').forEach((control) => {
                    const row = control.getAttribute('data-class-list-reconcile-row');
                    if (!row) return;
                    if (control.checked) {
                        decisions[row] = {
                            action: 'reconcile_same_class',
                            student_id: Number(control.getAttribute('data-class-list-reconcile-student-id') || 0),
                        };
                    } else {
                        delete decisions[row];
                    }
                });
                identityReconciliationState = decisions;
                return decisions;
            };

            const showIdentityCompletion = (data) => {
                resolutionPreview = data;

                const rows = Array.isArray(data?.rows) ? data.rows : [];
                const candidates = rows.filter((row) => row.identity_completion_required === true);
                const completed = candidates.filter((row) => row.identity_completion_complete === true);

                if (identitySummary) {
                    identitySummary.innerHTML = `
                        <div class="teacher-class-list-review-counts">
                            <span><strong>${escapeHtml(candidates.length)}</strong> New Student identity review${candidates.length === 1 ? '' : 's'}</span>
                            <span class="is-valid"><strong>${escapeHtml(completed.length)}</strong> structurally complete</span>
                            <span class="is-invalid"><strong>${escapeHtml(candidates.length - completed.length)}</strong> need First and Last Name</span>
                        </div>
                        <p>
                            These values are temporary review inputs only. A later explicit confirmation
                            and persistence step is still required.
                        </p>
                    `;
                }

                if (!identityFields) {
                    return;
                }

                if (candidates.length === 0) {
                    identityFields.innerHTML = `
                        <div class="teacher-class-list-source-card">
                            <dl>
                                <div>
                                    <dt>No identity completion is available</dt>
                                    <dd>
                                        Existing Students, structural errors, duplicates, or identity conflicts
                                        are intentionally not changed in this step.
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    `;
                    return;
                }

                identityFields.innerHTML = candidates.map((row) => {
                    const proposed = row.proposed_identity || {};
                    const rawName = row.student_name_raw || 'No combined source name mapped';
                    const prefix = `classListIdentity_${escapeHtml(row.source_row_number)}`;

                    return `
                        <section class="teacher-class-list-source-card" data-class-list-identity-row="${escapeHtml(row.source_row_number)}">
                            <dl>
                                <div><dt>Source Row</dt><dd>${escapeHtml(row.source_row_number)}</dd></div>
                                <div><dt>Student Number</dt><dd>${escapeHtml(row.student_number || 'Student Number pending')}</dd></div>
                                <div><dt>Source Name Evidence</dt><dd>${escapeHtml(rawName)}</dd></div>
                                <div><dt>Readiness</dt><dd>${escapeHtml(row.identity_completion_complete ? 'Structured identity complete' : 'First and Last Name required')}</dd></div>
                            </dl>
                            <div class="teacher-class-list-mapping-grid">
                                <div class="teacher-import-form-field is-required">
                                    <label for="${prefix}_first">First Name <span>Required</span></label>
                                    <input id="${prefix}_first" type="text" maxlength="100" autocomplete="off"
                                        data-identity-field="first_name" value="${escapeHtml(proposed.first_name || '')}">
                                </div>
                                <div class="teacher-import-form-field is-required">
                                    <label for="${prefix}_last">Last Name <span>Required</span></label>
                                    <input id="${prefix}_last" type="text" maxlength="100" autocomplete="off"
                                        data-identity-field="last_name" value="${escapeHtml(proposed.last_name || '')}">
                                </div>
                                <div class="teacher-import-form-field is-optional">
                                    <label for="${prefix}_middle">Middle Name <span>Optional</span></label>
                                    <input id="${prefix}_middle" type="text" maxlength="100" autocomplete="off"
                                        data-identity-field="middle_name" value="${escapeHtml(proposed.middle_name || '')}">
                                </div>
                                <div class="teacher-import-form-field is-optional">
                                    <label for="${prefix}_suffix">Suffix <span>Optional</span></label>
                                    <input id="${prefix}_suffix" type="text" maxlength="30" autocomplete="off"
                                        data-identity-field="suffix" value="${escapeHtml(proposed.suffix || '')}">
                                </div>
                            </div>
                        </section>
                    `;
                }).join('');
            };

            const displayContext = (context) => {
                const values = [
                    ['Program', context?.program || context?.program_name || context?.program_code],
                    ['Section', context?.section || context?.section_name],
                    ['Year Level', context?.year_level],
                ].filter(([, value]) => normalizedValue(value) !== '');

                return values.length
                    ? values.map(([label, value]) =>
                        `<div><strong>${escapeHtml(label)}:</strong> ${escapeHtml(value)}</div>`
                    ).join('')
                    : 'No mapped context values';
            };

            const showAcademicContextEvidence = (data) => {
                resolutionPreview = data;

                const classContext = data?.class_context || {};
                const rows = Array.isArray(data?.rows) ? data.rows : [];
                const summary = data?.summary || {};

                if (academicEvidenceSummary) {
                    academicEvidenceSummary.innerHTML = `
                        ${displaySourceContext(data?.source_context || {})}
                        <section class="teacher-class-list-source-card">
                            <dl>
                                <div><dt>Selected Operational Class</dt><dd>${escapeHtml(classContext.operational_class_id || '—')}</dd></div>
                                <div><dt>School Year</dt><dd>${escapeHtml(classContext.school_year || '—')}</dd></div>
                                <div><dt>Semester</dt><dd>${escapeHtml(classContext.semester || '—')}</dd></div>
                                <div><dt>Teaching Section</dt><dd>${escapeHtml(classContext.section_name || '—')}</dd></div>
                                <div><dt>Teaching Program</dt><dd>${escapeHtml(classContext.program_name || classContext.program_code || '—')}</dd></div>
                                <div><dt>Teaching Year Level</dt><dd>${escapeHtml(classContext.year_level || '—')}</dd></div>
                            </dl>
                        </section>
                        <div class="teacher-class-list-review-counts">
                            <span><strong>${escapeHtml(summary.source_row_count || 0)}</strong> roster rows</span>
                            <span class="is-valid"><strong>${escapeHtml(summary.academic_context_resolved || 0)}</strong> one matching record</span>
                            <span><strong>${escapeHtml((summary.academic_context_unresolved || 0) + (summary.academic_context_ambiguous || 0))}</strong> placement evidence unresolved (non-blocking)</span>
                        </div>
                        <p>
                            The class context above is not proposed as a Student Academic Enrollment.
                            Each row below shows evidence only.
                        </p>
                    `;
                }

                if (academicEvidenceTable) {
                    academicEvidenceTable.innerHTML = `
                        <table class="teacher-table">
                            <thead>
                                <tr>
                                    <th>Source Row</th>
                                    <th>Student Number</th>
                                    <th>Identity Status</th>
                                    <th>Mapped Source Context</th>
                                    <th>Existing Academic Evidence</th>
                                    <th>Review Outcome</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.map((row) => {
                        const evidence = row.academic_evidence || {};
                        const candidate = evidence.candidate || null;
                        const candidateText = candidate
                            ? `<div><strong>Academic Enrollment #${escapeHtml(candidate.student_academic_enrollment_id)}</strong></div>
                                           <div><strong>Semester:</strong> ${escapeHtml(candidate.semester || '—')}</div>
                                           <div><strong>Academic Level:</strong> ${escapeHtml(candidate.academic_level || '—')}</div>
                                           ${displayContext({
                                program_name: candidate.program_name,
                                program_code: candidate.program_code,
                                section_name: candidate.section_name,
                                year_level: candidate.year_level,
                            })}
                                           <div><strong>Status:</strong> ${escapeHtml(candidate.status || '—')}</div>`
                            : `<div>${escapeHtml(evidence.available_enrollment_count || 0)} current-school-year record(s) found</div>
                                           <div>${escapeHtml(evidence.matching_enrollment_count || 0)} safe match(es)</div>`;

                        return `
                                        <tr>
                                            <td>${escapeHtml(row.source_row_number || '—')}</td>
                                            <td>${escapeHtml(row.student_number || 'Student Number pending')}</td>
                                            <td>${escapeHtml(row.identity_status || '—')}</td>
                                            <td>${displayContext(evidence.source_context || {})}</td>
                                            <td>${candidateText}</td>
                                            <td>${escapeHtml(evidence.reason || 'No academic-context evidence is available.')}</td>
                                        </tr>
                                    `;
                    }).join('') || `
                                    <tr><td colspan="6">No mapped roster rows were available for Academic Context Evidence Review.</td></tr>
                                `}
                            </tbody>
                        </table>
                    `;
                }

                showImportStep('academic-evidence');
            };

            const renderReviewAndFix = (data) => {
                importPlanPreview = data;

                const summary = data?.summary || {};
                const rows = Array.isArray(data?.rows) ? data.rows : [];
                const catalog = data?.context_catalog || {};
                const programs = Array.isArray(catalog.programs) ? catalog.programs : [];
                const sections = Array.isArray(catalog.sections) ? catalog.sections : [];
                const sourceContextMarkup = displaySourceContext(data?.source_context || {});

                const storedReviewState = data?.review_state || {};
                if (storedReviewState.identity_overrides && Object.keys(identityOverrideState).length === 0) {
                    identityOverrideState = storedReviewState.identity_overrides;
                }
                if (storedReviewState.academic_context_decisions && Object.keys(academicContextDecisionOverrides).length === 0) {
                    academicContextDecisionOverrides = storedReviewState.academic_context_decisions;
                }
                if (storedReviewState.identity_reconciliations && Object.keys(identityReconciliationState).length === 0) {
                    identityReconciliationState = storedReviewState.identity_reconciliations;
                }
                rows.forEach((row) => {
                    if (row?.context_decision && row?.source_row_number && row.context_decision_origin === 'teacher_review') {
                        academicContextDecisionOverrides[String(row.source_row_number)] = row.context_decision;
                    }
                });
                const programOptionList = (selectedValue) => programs.map((program) => `
                    <option value="${escapeHtml(program.program_id)}" ${String(program.program_id) === String(selectedValue || '') ? 'selected' : ''}>
                        ${escapeHtml([program.program_code, program.program_name].filter(Boolean).join(' — '))}
                    </option>
                `).join('');
                const sectionOptionList = (selectedValue) => sections.map((section) => `
                    <option value="${escapeHtml(section.section_id)}" ${String(section.section_id) === String(selectedValue || '') ? 'selected' : ''}>
                        ${escapeHtml(section.section_name || `Section ${section.section_id}`)}
                    </option>
                `).join('');
                const contextDecisionControls = (row) => {
                    const decision = academicContextDecisionOverrides[
                        String(row.source_row_number)
                    ] || row.context_decision || {};
                    const sourceRow = escapeHtml(row.source_row_number || '');
                    const defaults = data?.context_defaults || {};
                    const required = row.context_decision_required === true;

                    return `
                        <div class="teacher-class-list-context-decision" data-class-list-context-decision data-source-row="${sourceRow}">
                            <strong>${required ? 'Resolve academic placement' : 'Add reviewed academic placement (optional)'}</strong>
                            <p>${required ? 'Complete this reviewed placement before confirmation.' : 'The Class List can continue with Student identity and class participation only. Add these values only when you legitimately know the current placement.'} Semester: ${escapeHtml(defaults.semester || 'current semester')} · Effective from: ${escapeHtml(defaults.effective_start || 'current School Year start')}. Academic Level follows the selected Program.</p>
                            <div class="teacher-class-list-context-grid">
                                <label>Program
                                    <select data-context-field="program_id">
                                        <option value="">Select Program</option>
                                        ${programOptionList(decision.program_id)}
                                    </select>
                                </label>
                                <label>Section
                                    <select data-context-field="section_id">
                                        <option value="">Select Section</option>
                                        ${sectionOptionList(decision.section_id)}
                                    </select>
                                </label>
                                <label>Year Level
                                    <select data-context-field="year_level">
                                        <option value="">Select Year Level</option>
                                        ${['1', '2', '3', '4', '11', '12'].map((value) => `<option value="${value}" ${value === String(decision.year_level || '') ? 'selected' : ''}>${value}</option>`).join('')}
                                    </select>
                                </label>
                            </div>
                            <small>Choose only confirmed placement information. APRISM never takes Program, Section, or Year Level from the Operational Class or a Section name.</small>
                        </div>
                    `;
                };

                if (importPlanSummary) {
                    importPlanSummary.innerHTML = `
                        <div class="teacher-class-list-review-counts">
                            <span><strong>${escapeHtml(summary.roster_rows || 0)}</strong> Students detected</span>
                            <span class="is-valid"><strong>${escapeHtml(summary.participation_ready_rows || 0)}</strong> ready for class participation</span>
                            <span><strong>${escapeHtml(summary.student_number_pending_rows || 0)}</strong> Student Numbers pending</span>
                            <span><strong>${escapeHtml(summary.placement_auto_resolved_rows || 0)}</strong> placement contexts normalized</span>
                            <span><strong>${escapeHtml(summary.program_evidence_normalized_rows || 0)}</strong> Program values normalized</span>
                            <span><strong>${escapeHtml(summary.year_level_evidence_normalized_rows || 0)}</strong> Year Levels normalized</span>
                            <span><strong>${escapeHtml(summary.placement_unresolved_rows || 0)}</strong> placement contexts unresolved</span>
                            <span><strong>${escapeHtml(summary.already_enrolled_rows || 0)}</strong> already enrolled</span>
                            <span class="is-invalid"><strong>${escapeHtml(summary.blocked_rows || 0)}</strong> genuine conflicts</span>
                        </div>
                        <p>
                            APRISM has checked the current roster. Class participation can continue while optional academic placement remains unresolved.
                        </p>
                    `;
                }

                if (importPlanValidation) {
                    const planMessage = data.no_changes_required === true
                        ? '<p><strong>No changes needed:</strong> all students are already enrolled in this class.</p>'
                        : data.source_was_truncated === true
                            ? '<p><strong>Confirmation remains blocked:</strong> the source exceeds the current 500-row server review limit. Use a smaller roster or extend the bounded review safely before import.</p>'
                            : (summary.blocked_rows || 0) > 0
                                ? `<p><strong>Review needed:</strong> ${escapeHtml(summary.blocked_rows)} row(s) still need attention.</p>`
                                : '<p><strong>Ready to import:</strong> all detected students have a reviewed path.</p>';

                    const requiredDecisionRows = rows.filter(
                        (row) => row.context_decision_required === true
                    );
                    const optionalPlacementRows = rows.filter((row) =>
                        row.context_decision_required !== true
                        && row.plan_status !== 'Blocked'
                        && row.student_action !== 'Blocked'
                        && row.academic_enrollment_action === 'No Academic Enrollment action'
                    );
                    const identityRows = rows.filter(
                        (row) => row.identity_completion_required === true
                    );
                    const automaticContextRows = rows.filter(
                        (row) => row.context_decision_origin === 'safe_existing_enrollment'
                            || row.context_decision_origin === 'validated_roster_evidence'
                    );

                    const identityControls = identityRows.length === 0 ? '' : `
                        <section class="teacher-class-list-source-card" data-class-list-identity-exceptions>
                            <h4>Identity corrections required</h4>
                            ${identityRows.map((row) => {
                        const identity = identityOverrideState[String(row.source_row_number)] || row.proposed_identity || {};
                        return `<div class="teacher-class-list-context-decision" data-review-identity-row="${escapeHtml(row.source_row_number)}"><strong>Row ${escapeHtml(row.source_row_number)} · ${escapeHtml(row.student_number || 'Student Number pending')}</strong><div class="teacher-class-list-context-grid"><label>First Name<input data-review-identity-field="first_name" maxlength="100" value="${escapeHtml(identity.first_name || '')}"></label><label>Last Name<input data-review-identity-field="last_name" maxlength="100" value="${escapeHtml(identity.last_name || '')}"></label><label>Middle Name<input data-review-identity-field="middle_name" maxlength="100" value="${escapeHtml(identity.middle_name || '')}"></label><label>Suffix<input data-review-identity-field="suffix" maxlength="30" value="${escapeHtml(identity.suffix || '')}"></label></div></div>`;
                    }).join('')}
                        </section>`;
                    const reconciliationRows = rows.filter((row) => row.identity_reconciliation_required === true || row.identity_status === 'Existing unresolved participant selected');
                    const reconciliationControls = reconciliationRows.length === 0 ? '' : `
                        <section class="teacher-class-list-source-card" data-class-list-identity-reconciliation>
                            <h4>Student Number reconciliation</h4>
                            <p>Review each same-class unresolved participant before attaching the incoming authoritative Student Number.</p>
                            ${reconciliationRows.map((row) => {
                                const key = String(row.source_row_number);
                                const selected = identityReconciliationState[key];
                                const name = row.student_name_raw || [row.first_name, row.last_name].filter(Boolean).join(' ') || 'Student name pending';
                                return `<label class="teacher-class-list-reconciliation-option"><input type="checkbox" data-class-list-reconcile-row="${escapeHtml(key)}" data-class-list-reconcile-student-id="${escapeHtml(row.same_class_candidate_student_id || row.candidate_student_id || 0)}" ${selected ? 'checked' : ''}><span><strong>${escapeHtml(name)}</strong><small>Incoming Number: ${escapeHtml(row.student_number || '—')} · same-class Student #${escapeHtml(row.same_class_candidate_student_id || row.candidate_student_id || '—')}</small></span></label>`;
                            }).join('')}
                        </section>`;

                    importPlanValidation.innerHTML = `
                        ${sourceContextMarkup}
                        ${planMessage}
                        ${identityControls}
                        ${reconciliationControls}
                        ${automaticContextRows.length > 0 ? `
                            <section class="teacher-class-list-source-card">
                                <h4>Academic placement resolved automatically</h4>
                                <p>APRISM reused one safe active Academic Enrollment or validated complete roster placement evidence. No Operational Class placement was adopted.</p>
                                <ul>${automaticContextRows.map((row) => `<li>Row ${escapeHtml(row.source_row_number)} · ${escapeHtml(row.context_decision_origin === 'safe_existing_enrollment' ? 'existing active Academic Enrollment reused' : 'validated roster placement ready')}</li>`).join('')}</ul>
                            </section>
                        ` : ''}
                        ${requiredDecisionRows.length > 0 ? `
                            <section class="teacher-class-list-source-card" data-class-list-context-decisions>
                                <h4>Academic Context Review</h4>
                                <p>
                                    These rows have a genuine placement exception. Review only the affected
                                    placement; the Operational Class never supplies Home Section or academic placement.
                                </p>
                                ${requiredDecisionRows.map((row) => `<details open><summary>Resolve placement exception for Row ${escapeHtml(row.source_row_number)}</summary>${contextDecisionControls(row)}</details>`).join('')}
                            </section>
                        ` : ''}
                        ${optionalPlacementRows.length > 0 ? `
                            <section class="teacher-class-list-source-card" data-class-list-optional-context>
                                <details>
                                    <summary>Complete optional placement for ${escapeHtml(optionalPlacementRows.length)} Student${optionalPlacementRows.length === 1 ? '' : 's'}</summary>
                                    <p>Student Numbers and academic placement are not required for class participation. Open this only when you have confirmed Program, Home Section, and Year Level from an authoritative source.</p>
                                    ${optionalPlacementRows.map((row) => `<details><summary>Optional placement for Row ${escapeHtml(row.source_row_number)} · ${escapeHtml(row.student_number || 'Student Number pending')}</summary>${contextDecisionControls(row)}</details>`).join('')}
                                </details>
                            </section>
                        ` : ''}
                    `;
                }

                if (importPlanTable) {
                    importPlanTable.innerHTML = `
                        <table class="teacher-table">
                            <thead>
                                <tr>
                                    <th>Source Row</th>
                                    <th>Student Number</th>
                                    <th>Student Action</th>
                                    <th>Academic Enrollment Action</th>
                                    <th>Class Enrollment Action</th>
                                    <th>Plan Status</th>
                                    <th>Why</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.map((row) => `
                                    <tr>
                                        <td>${escapeHtml(row.source_row_number || '—')}</td>
                                        <td>${escapeHtml(row.student_number || 'Student Number pending')}</td>
                                        <td>${escapeHtml(row.student_action || '—')}</td>
                                        <td>${escapeHtml(row.academic_enrollment_action || '—')}</td>
                                        <td>${escapeHtml(row.class_enrollment_action || '—')}</td>
                                        <td>${escapeHtml(row.plan_status || '—')}</td>
                                        <td class="teacher-import-plan-why">${escapeHtml((Array.isArray(row.blocking_reasons) ? row.blocking_reasons : []).join(' ') || 'No blocking reason.')}</td>
                                    </tr>
                                `).join('') || `
                                    <tr><td colspan="7">No mapped roster rows were available for the import plan.</td></tr>
                                `}
                            </tbody>
                        </table>
                    `;
                }

                if (reviewConfirmButton) {
                    const confirmationEnabled = data.confirmation_enabled === true;
                    const noChangesRequired = data.no_changes_required === true;
                    const actionDisabled = !confirmationEnabled;
                    reviewConfirmButton.hidden = noChangesRequired;
                    reviewConfirmButton.disabled = actionDisabled;
                    reviewConfirmButton.setAttribute('aria-disabled', actionDisabled ? 'true' : 'false');
                    reviewConfirmButton.innerHTML =
                        '<i data-lucide="arrow-right"></i><span>Continue to Confirm</span>';
                }

                if (importPlanRecheckButton) {
                    const noChangesRequired = data.no_changes_required === true;
                    importPlanRecheckButton.disabled = noChangesRequired;
                    importPlanRecheckButton.setAttribute('aria-disabled', noChangesRequired ? 'true' : 'false');
                    importPlanRecheckButton.title = noChangesRequired
                        ? 'No changes need revalidation — all roster entries are already enrolled.'
                        : 'Revalidate the reviewed roster plan.';
                }

                const planActionNote = document.querySelector('[data-class-list-plan-action-note]');
                if (planActionNote) {
                    planActionNote.textContent = data.no_changes_required === true
                        ? 'All roster entries are already enrolled. No changes require confirmation.'
                        : data.confirmation_enabled === true
                            ? 'Review is ready for confirmation.'
                            : 'Resolve the displayed items before continuing.';
                    planActionNote.classList.toggle('is-blocked', data.no_changes_required !== true && data.confirmation_enabled !== true);
                }

                showImportStep('plan');
            };

            const renderConfirmImport = (data) => {
                importPlanPreview = data;
                const summary = data?.summary || {};
                const rows = Array.isArray(data?.rows) ? data.rows : [];

                if (confirmSummary) {
                    confirmSummary.innerHTML = `
                        <div class="teacher-class-list-review-counts">
                            <span><strong>${escapeHtml(summary.roster_rows || 0)}</strong> Students detected</span>
                            <span><strong>${escapeHtml(summary.student_reuse || 0)}</strong> existing Students reused</span>
                            <span><strong>${escapeHtml(summary.student_create_proposed || 0)}</strong> new Students</span>
                            <span><strong>${escapeHtml(summary.academic_reuse || 0)}</strong> Academic Enrollments reused</span>
                            <span><strong>${escapeHtml(summary.class_enrollment_create_proposed || 0)}</strong> Class Enrollments proposed</span>
                        </div>`;
                }

                if (confirmTable) {
                    const displayEvidence = (row) => {
                        const normalization = row.academic_evidence?.normalization || {};
                        const program = normalization.program || {};
                        const year = normalization.year_level || {};
                        const programText = program.status === 'resolved'
                            ? [program.code, program.name].filter(Boolean).join(' — ')
                            : (program.source || 'Not supplied');
                        const yearText = year.status === 'resolved'
                            ? String(year.value || '')
                            : (year.source ? `${year.source} · unresolved` : 'Not supplied');

                        return `<div class="teacher-class-list-confirm-evidence"><span><strong>Program / Plan</strong> ${escapeHtml(programText)}</span><span><strong>Level</strong> ${escapeHtml(yearText)}</span></div>`;
                    };
                    const participationText = (row) => row.class_enrollment_action === 'Proposed Class Enrollment'
                        ? 'Ready — Proposed Class Enrollment'
                        : (row.class_enrollment_action || '—');
                    const placementText = (row) => row.academic_enrollment_action === 'No Academic Enrollment action'
                        ? 'Unresolved placement — no SAE action'
                        : (row.academic_enrollment_action || '—');

                    confirmTable.innerHTML = `<table class="teacher-table"><thead><tr><th>Source Row</th><th>Student</th><th>Student Number</th><th>Program / Level Evidence</th><th>Academic Placement</th><th>Class Participation</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${escapeHtml(row.source_row_number)}</td><td><strong>${escapeHtml(row.student_name_raw || 'Student name pending')}</strong></td><td>${escapeHtml(row.student_number || 'Pending')}</td><td>${displayEvidence(row)}</td><td>${escapeHtml(placementText(row))}</td><td>${escapeHtml(participationText(row))}</td></tr>`).join('')}</tbody></table>`;
                }

                showImportStep('confirm');
            };

            const academicContextDecisions = () => {
                const decisions = { ...academicContextDecisionOverrides };

                document.querySelectorAll('[data-class-list-context-decision]').forEach((container) => {
                    const sourceRow = container.getAttribute('data-source-row');

                    if (!sourceRow) return;

                    const field = (name) => container.querySelector(`[data-context-field="${name}"]`)?.value || '';

                    const decision = {
                        semester: field('semester'),
                        academic_level: field('academic_level'),
                        program_id: field('program_id'),
                        section_id: field('section_id'),
                        year_level: field('year_level'),
                        effective_start: field('effective_start'),
                    };

                    const hasReviewedValue = Object.values(decision).some(
                        (value) => String(value || '').trim() !== ''
                    );

                    if (hasReviewedValue) {
                        decisions[sourceRow] = decision;
                    } else {
                        delete decisions[sourceRow];
                    }
                });

                return decisions;
            };

            const prepareImportPlan = async (destination = 'review') => {
                if (!sourceToken || !importForm) {
                    showToastMessage('Upload and analyze a Class List before preparing its review.', 'error');
                    return;
                }

                const originalButtonHtml = importPlanOpenButton?.innerHTML || '';

                if (importPlanOpenButton) {
                    importPlanOpenButton.disabled = true;
                    importPlanOpenButton.innerHTML = '<i data-lucide="loader-circle"></i><span>Preparing Import Plan...</span>';
                }

                if (window.lucide) window.lucide.createIcons();

                try {
                    const formData = new FormData(importForm);
                    const mappings = selectedMappings();

                    formData.set('operation', 'plan');
                    formData.set('source_token', sourceToken);
                    formData.set('worksheet_name', worksheetSelect?.value || '');
                    formData.set('header_row_number', headerRowInput?.value || '');
                    formData.set('first_data_row_number', firstDataRowInput?.value || '');
                    Object.entries(mappings).forEach(([field, column]) => {
                        formData.set(`mapping[${field}]`, column || '');
                    });
                    formData.set('identity_overrides_json', JSON.stringify(identityOverrides()));
                    formData.set('identity_reconciliations_json', JSON.stringify(identityReconciliations()));
                    academicContextDecisionOverrides = academicContextDecisions();
                    formData.set('academic_context_decisions_json', JSON.stringify(academicContextDecisionOverrides));

                    const response = await fetch(
                        `${window.APP_URL}/actions/teacher/plan_class_list_import.php`,
                        {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }
                    );
                    const result = await readApiResponse(
                        response,
                        'APRISM could not read the server-validated import plan. Please try again.'
                    );

                    if (
                        !response.ok
                        || result.success !== true
                        || !['IMPORT_PLAN_READY', 'IMPORT_PLAN_NO_CHANGES'].includes(result.code)
                        || !result.data
                    ) {
                        throw new Error(result.message || 'The import plan could not be prepared.');
                    }

                    if (result.data.no_changes_required === true) {
                        renderReviewAndFix(result.data);
                        showToastMessage(
                            result.message || 'No changes needed. All students are already enrolled in this class.',
                            'success'
                        );
                    } else if (destination === 'confirm') {
                        renderConfirmImport(result.data);
                    } else {
                        renderReviewAndFix(result.data);
                        showToastMessage(result.message || 'Server-validated import plan is ready. No records were changed.', 'success');
                    }
                } catch (error) {
                    showToastMessage(
                        error instanceof Error ? error.message : 'The import plan could not be prepared.',
                        'error'
                    );
                } finally {
                    if (importPlanOpenButton) {
                        importPlanOpenButton.disabled = false;
                        importPlanOpenButton.innerHTML = originalButtonHtml;
                    }

                    if (window.lucide) window.lucide.createIcons();
                }
            };

            const confirmClassListImport = async () => {
                if (importPlanPreview?.no_changes_required === true) {
                    showToastMessage('No changes needed. All students are already enrolled in this class.', 'success');
                    return;
                }

                if (!importPlanPreview?.confirmation_enabled || !sourceToken || !importForm) {
                    showToastMessage('Prepare a complete server-validated import plan before confirming.', 'error');
                    return;
                }

                const originalButtonHtml = importConfirmButton?.innerHTML || '';

                if (importConfirmButton) {
                    importConfirmButton.disabled = true;
                    importConfirmButton.innerHTML = '<i data-lucide="loader-circle"></i><span>Confirming Import...</span>';
                }

                if (window.lucide) window.lucide.createIcons();

                try {
                    const formData = new FormData(importForm);
                    const mappings = selectedMappings();

                    formData.set('operation', 'confirm');
                    formData.set('confirmation_intent', 'confirm_class_list_import');
                    formData.set('source_token', sourceToken);
                    formData.set('worksheet_name', worksheetSelect?.value || '');
                    formData.set('header_row_number', headerRowInput?.value || '');
                    formData.set('first_data_row_number', firstDataRowInput?.value || '');
                    Object.entries(mappings).forEach(([field, column]) => {
                        formData.set(`mapping[${field}]`, column || '');
                    });
                    formData.set('identity_overrides_json', JSON.stringify(identityOverrides()));
                    formData.set('identity_reconciliations_json', JSON.stringify(identityReconciliations()));
                    academicContextDecisionOverrides = academicContextDecisions();
                    formData.set('academic_context_decisions_json', JSON.stringify(academicContextDecisionOverrides));

                    const response = await fetch(
                        `${window.APP_URL}/actions/teacher/plan_class_list_import.php`,
                        {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }
                    );
                    const result = await readApiResponse(
                        response,
                        'APRISM could not read the Class List confirmation response. Please try again.'
                    );

                    if (!response.ok || result.success !== true || result.code !== 'IMPORT_CONFIRMED') {
                        throw new Error(result.message || 'The Class List import could not be confirmed.');
                    }

                    const processedRows = Number(result?.data?.summary?.processed_rows || 0);
                    const successMessage = processedRows > 0
                        ? `Class list imported successfully. ${processedRows} participant${processedRows === 1 ? '' : 's'} processed.`
                        : 'Class list imported successfully.';

                    closeImportModal();
                    showToastMessage(successMessage, 'success');
                    await loadClassList();
                } catch (error) {
                    showToastMessage(
                        error instanceof Error ? error.message : 'The Class List import could not be confirmed.',
                        'error'
                    );
                } finally {
                    if (importConfirmButton && importModal?.getAttribute('aria-hidden') === 'false') {
                        importConfirmButton.disabled = !importPlanPreview?.confirmation_enabled;
                        importConfirmButton.innerHTML = originalButtonHtml;
                    }

                    if (window.lucide) window.lucide.createIcons();
                }
            };

            const showResolutionPreview = async (
                reviewOverrides = {},
                successStep = 'resolution',
                triggerButton = resolutionOpenButton
            ) => {
                const mappings = selectedMappings();
                const problems = mappingProblemList(mappings);

                if (problems.length) {
                    if (mappingErrors) {
                        mappingErrors.innerHTML =
                            `<p>${problems.map(escapeHtml).join('<br>')}</p>`;
                    }

                    showImportStep('mapping');
                    return;
                }

                if (!sourceToken || !importForm) {
                    showToastMessage(
                        'The uploaded source is unavailable. Upload the file again.',
                        'error'
                    );
                    return;
                }

                const originalButtonHtml = triggerButton?.innerHTML || '';

                if (triggerButton) {
                    triggerButton.disabled = true;
                    triggerButton.innerHTML =
                        '<i data-lucide="loader-circle"></i><span>Checking Resolution...</span>';
                }

                if (window.lucide) {
                    window.lucide.createIcons();
                }

                try {
                    const formData = new FormData(importForm);

                    formData.set('source_token', sourceToken);
                    formData.set('worksheet_name', worksheetSelect?.value || '');
                    formData.set('header_row_number', headerRowInput?.value || '');
                    formData.set(
                        'first_data_row_number',
                        firstDataRowInput?.value || ''
                    );

                    Object.entries(mappings).forEach(([field, column]) => {
                        formData.set(`mapping[${field}]`, column || '');
                    });

                    formData.set(
                        'identity_overrides_json',
                        JSON.stringify(reviewOverrides)
                    );

                    const response = await fetch(
                        `${window.APP_URL}/actions/teacher/resolve_class_list.php`,
                        {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }
                    );

                    const result = await readApiResponse(
                        response,
                        'APRISM could not read the Resolution Preview response. Please try again.'
                    );

                    if (!response.ok || result.success !== true) {
                        throw new Error(
                            result.message || 'The Resolution Preview could not be prepared.'
                        );
                    }

                    if (result.code !== 'RESOLUTION_PREVIEW_READY' || !result.data) {
                        throw new Error(
                            result.message || 'APRISM could not prepare Resolution Preview.'
                        );
                    }

                    const data = result.data;
                    resolutionPreview = data;
                    const previewSummary = data.summary || {};
                    const rows = Array.isArray(data.rows) ? data.rows : [];

                    if (resolutionSummary) {
                        resolutionSummary.innerHTML = `
                <div class="teacher-class-list-review-counts">
                    <span><strong>${escapeHtml(previewSummary.source_row_count || 0)}</strong> detected roster rows</span>
                    <span class="is-valid"><strong>${escapeHtml(previewSummary.existing_student_matches || 0)}</strong> existing Student matches</span>
                    <span><strong>${escapeHtml(previewSummary.new_student_candidates || 0)}</strong> new Student candidates</span>
                    <span class="is-invalid"><strong>${escapeHtml(previewSummary.identity_conflicts || 0)}</strong> identity conflicts</span>
                    <span><strong>${escapeHtml(previewSummary.academic_context_unresolved || 0)}</strong> unresolved context</span>
                    <span><strong>${escapeHtml(previewSummary.already_enrolled || 0)}</strong> already enrolled</span>
                </div>
                <p>
                    Resolution Preview compares only the selected temporary source
                    against existing APRISM records. It does not save any result.
                </p>
            `;
                    }

                    const notices = [];

                    if (previewSummary.is_truncated) {
                        notices.push(
                            'Only the first 500 detected roster rows are displayed in this preview.'
                        );
                    }

                    if ((previewSummary.source_duplicates || 0) > 0) {
                        notices.push(
                            `${previewSummary.source_duplicates} row(s) use a duplicated Student Number in this uploaded source.`
                        );
                    }

                    if ((previewSummary.identity_conflicts || 0) > 0) {
                        notices.push(
                            `${previewSummary.identity_conflicts} row(s) conflict with an existing Student identity.`
                        );
                    }

                    if ((previewSummary.identity_ambiguous || 0) > 0) {
                        notices.push(
                            `${previewSummary.identity_ambiguous} row(s) match more than one unresolved class participant and require explicit identity review.`
                        );
                    }

                    if ((previewSummary.academic_context_ambiguous || 0) > 0) {
                        notices.push(
                            `${previewSummary.academic_context_ambiguous} row(s) have more than one possible Academic Enrollment.`
                        );
                    }

                    if ((previewSummary.academic_context_conflicts || 0) > 0) {
                        notices.push(
                            `${previewSummary.academic_context_conflicts} row(s) contain explicit placement information that conflicts with an existing current Academic Enrollment.`
                        );
                    }

                    if (resolutionValidation) {
                        resolutionValidation.innerHTML = notices.length
                            ? `<p><strong>Review required:</strong> ${notices.map(escapeHtml).join(' ')}</p>`
                            : '<p><strong>Resolution Preview complete:</strong> persistence remains unavailable by design.</p>';
                    }

                    if (resolutionTable) {
                        resolutionTable.innerHTML = `
                <table class="teacher-table">
                    <thead>
                        <tr>
                            <th>Source Row</th>
                            <th>Student Number</th>
                            <th>Source Name</th>
                            <th>Structural Status</th>
                            <th>Identity Resolution</th>
                            <th>Academic Context</th>
                            <th>Class Participation</th>
                            <th>Warnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <td>${escapeHtml(row.source_row_number)}</td>
                                <td>${escapeHtml(row.student_number || 'Student Number pending')}</td>
                                <td>${escapeHtml(
                            row.student_name_raw ||
                            [row.first_name, row.last_name]
                                .filter(Boolean)
                                .join(' ') ||
                            '—'
                        )}</td>
                                <td>${escapeHtml(row.structural_status || '—')}</td>
                                <td>${escapeHtml(row.identity_status || '—')}</td>
                                <td>${escapeHtml(row.academic_status || '—')}</td>
                                <td>${escapeHtml(row.class_status || '—')}</td>
                                <td>${escapeHtml(
                            [
                                ...(Array.isArray(row.errors) ? row.errors : []),
                                ...(Array.isArray(row.warnings) ? row.warnings : []),
                            ].join(' ') || '—'
                        )}</td>
                            </tr>
                        `).join('') || `
                            <tr>
                                <td colspan="8">No mapped roster rows were available for Resolution Preview.</td>
                            </tr>
                        `}
                    </tbody>
                </table>
            `;
                    }

                    if (successStep === 'identity') {
                        showIdentityCompletion(data);
                    }

                    showImportStep(successStep);
                    showToastMessage(
                        result.message ||
                        'Resolution Preview is ready. No records were changed.',
                        'success'
                    );
                } catch (error) {
                    showToastMessage(
                        error instanceof Error
                            ? error.message
                            : 'The Resolution Preview could not be prepared.',
                        'error'
                    );
                } finally {
                    if (triggerButton) {
                        triggerButton.disabled = false;
                        triggerButton.innerHTML = originalButtonHtml;
                    }

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
            };

            const emptyState = (icon, title, message) => {
                tableBody.replaceChildren();

                const row = document.createElement('tr');
                const cell = document.createElement('td');
                const state = document.createElement('div');
                const iconElement = document.createElement('i');
                const heading = document.createElement('h3');
                const paragraph = document.createElement('p');

                cell.colSpan = 4;
                state.className = 'teacher-empty-state';
                iconElement.setAttribute('data-lucide', icon);
                heading.className = 'teacher-empty-state-title';
                heading.textContent = title;
                paragraph.className = 'teacher-empty-state-text';
                paragraph.textContent = message;

                state.append(iconElement, heading, paragraph);
                cell.appendChild(state);
                row.appendChild(cell);
                tableBody.appendChild(row);

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            };

            const formatAcademicContext = (student) => {
                const context = [
                    student.academic_level,
                    student.program_code || student.program_name,
                    student.academic_section,
                    student.year_level ? `Year ${student.year_level}` : '',
                    student.academic_school_year,
                    student.academic_semester,
                ].filter(Boolean);

                return context.length > 0
                    ? context.join(' · ')
                    : (student.academic_context_status || 'Academic placement not yet recorded');
            };

            const formatEnrolledAt = (value) => {
                if (!value) {
                    return '—';
                }

                const parsed = new Date(value.replace(' ', 'T'));

                if (Number.isNaN(parsed.getTime())) {
                    return value;
                }

                return new Intl.DateTimeFormat('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(parsed);
            };

            const statusClass = (status) => {
                if (status === 'Active' || status === 'Completed') {
                    return 'teacher-badge-success';
                }

                if (status === 'Dropped' || status === 'Withdrawn') {
                    return 'teacher-badge-warning';
                }

                return 'teacher-badge-info';
            };

            const hidePagination = () => {
                currentRosterPage = 1;

                if (pagination) {
                    pagination.hidden = true;
                }

                pageSummary?.replaceChildren();
                pageControls?.replaceChildren();
            };

            const renderPagination = (totalItems, firstIndex, lastIndex) => {
                const totalPages = Math.max(1, Math.ceil(totalItems / rosterPageSize));

                if (totalPages <= 1 || !pagination || !pageControls) {
                    hidePagination();
                    return;
                }

                currentRosterPage = Math.min(currentRosterPage, totalPages);
                pagination.hidden = false;
                pageSummary.textContent =
                    `Showing ${firstIndex + 1}–${lastIndex} of ${totalItems} matching students`;
                pageControls.replaceChildren();

                const addPageButton = (label, page, iconName = '', disabled = false) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'teacher-page-link';
                    button.setAttribute('aria-label', label);

                    if (disabled) {
                        button.disabled = true;
                        button.classList.add('disabled');
                        button.setAttribute('aria-disabled', 'true');
                    } else {
                        button.addEventListener('click', () => {
                            currentRosterPage = page;
                            renderStudents();
                        });
                    }

                    if (iconName) {
                        const icon = document.createElement('i');
                        icon.setAttribute('data-lucide', iconName);
                        icon.setAttribute('aria-hidden', 'true');
                        button.appendChild(icon);
                    } else {
                        button.textContent = String(page);

                        if (page === currentRosterPage) {
                            button.classList.add('active');
                            button.setAttribute('aria-current', 'page');
                        }
                    }

                    pageControls.appendChild(button);
                };

                addPageButton(
                    'Previous page',
                    currentRosterPage - 1,
                    'chevron-left',
                    currentRosterPage === 1
                );

                const startPage = Math.max(
                    1,
                    Math.min(currentRosterPage - 1, totalPages - 2)
                );
                const endPage = Math.min(totalPages, startPage + 2);

                for (let page = startPage; page <= endPage; page += 1) {
                    addPageButton(`Page ${page}`, page);
                }

                addPageButton(
                    'Next page',
                    currentRosterPage + 1,
                    'chevron-right',
                    currentRosterPage === totalPages
                );

                if (window.lucide) {
                    window.lucide.createIcons();
                }
            };

            const renderStudents = () => {
                const query = (searchInput?.value || '').trim().toLowerCase();
                const visibleStudents = students.filter((student) => {
                    const searchable = [
                        student.student_number,
                        student.display_name,
                        student.academic_section,
                        student.program_code,
                        student.program_name,
                    ].filter(Boolean).join(' ').toLowerCase();

                    return searchable.includes(query);
                });

                if (students.length === 0) {
                    hidePagination();
                    emptyState(
                        'users-round',
                        'No Students Attached Yet',
                        'This Operational Class has no Student Class Enrollment records.'
                    );
                    return;
                }

                if (visibleStudents.length === 0) {
                    hidePagination();
                    emptyState(
                        'search-x',
                        'No Matching Students',
                        'No student in this Class List matches your search.'
                    );
                    return;
                }

                tableBody.replaceChildren();

                const totalPages = Math.max(1, Math.ceil(visibleStudents.length / rosterPageSize));
                currentRosterPage = Math.min(currentRosterPage, totalPages);
                const firstIndex = (currentRosterPage - 1) * rosterPageSize;
                const pageStudents = visibleStudents.slice(
                    firstIndex,
                    firstIndex + rosterPageSize
                );

                renderPagination(
                    visibleStudents.length,
                    firstIndex,
                    firstIndex + pageStudents.length
                );

                pageStudents.forEach((student) => {
                    const row = document.createElement('tr');
                    const studentNameCell = document.createElement('td');
                    studentNameCell.dataset.label = 'Student';
                    const contextCell = document.createElement('td');
                    contextCell.dataset.label = 'Academic Context';
                    const statusCell = document.createElement('td');
                    statusCell.dataset.label = 'Class Status';
                    const enrolledAtCell = document.createElement('td');
                    enrolledAtCell.dataset.label = 'Enrolled At';
                    const name = document.createElement('strong');
                    const badge = document.createElement('span');

                    name.textContent = student.display_name || '—';
                    studentNameCell.appendChild(name);
                    if (student.student_number) {
                        const studentNumber = document.createElement('small');
                        studentNumber.className = 'teacher-roster-student-number';
                        studentNumber.textContent = student.student_number;
                        studentNameCell.appendChild(studentNumber);
                    }
                    contextCell.textContent = formatAcademicContext(student);
                    badge.className = `teacher-badge ${statusClass(student.class_status)}`;
                    badge.textContent = student.class_status || '—';
                    statusCell.appendChild(badge);
                    enrolledAtCell.textContent = formatEnrolledAt(student.enrolled_at);

                    row.append(
                        studentNameCell,
                        contextCell,
                        statusCell,
                        enrolledAtCell
                    );

                    tableBody.appendChild(row);
                });
            };

            const loadClassList = async () => {
                try {
                    const response = await fetch(endpoint, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const result = await readApiResponse(
                        response,
                        'APRISM could not read the Class List response. Please try again.'
                    );

                    if (!response.ok || result.success !== true) {
                        throw new Error(result.message || 'The Class List could not be loaded.');
                    }

                    students = Array.isArray(result.data?.students)
                        ? result.data.students
                        : [];

                    const count = students.length;
                    countBadge.textContent = `${count} ${count === 1 ? 'Student' : 'Students'}`;
                    summary.textContent = count === 0
                        ? 'No roster participants are currently attached.'
                        : 'Current roster participants for this class.';

                    renderStudents();
                } catch (error) {
                    countBadge.textContent = 'Unavailable';
                    summary.textContent = 'The current Class List could not be read.';
                    hidePagination();
                    emptyState(
                        'triangle-alert',
                        'Class List Unavailable',
                        error instanceof Error
                            ? error.message
                            : 'The Class List could not be loaded.'
                    );
                }
            };

            searchInput?.addEventListener('input', () => {
                currentRosterPage = 1;
                renderStudents();
            });
            importFileInput?.addEventListener('change', () => {
                const file = importFileInput.files?.[0];
                if (importFileName) importFileName.textContent = file ? file.name : 'No file selected';
                if (uploadFeedback) uploadFeedback.hidden = true;
            });

            chooseAnotherButton?.addEventListener('click', () => {
                importForm?.reset();
                if (importFileName) importFileName.textContent = 'No file selected';
                sourceExtraction = null;
                sourceToken = '';
                resolutionPreview = null;
                importPlanPreview = null;
                academicContextDecisionOverrides = {};
                bulkContextState = {};
                showImportStep('upload');
                window.setTimeout(() => importFileInput?.focus(), 0);
            });
            mappingOpenButton?.addEventListener('click', () => {
                buildMappingFields();
                applyAutomaticMappings();
                if (mappingProblemList(selectedMappings()).length === 0) {
                    prepareImportPlan('review');
                    return;
                }
                showMapping();
            });
            worksheetSelect?.addEventListener('change', updateSourceStructure);
            headerRowInput?.addEventListener('change', updateSourceStructure);
            firstDataRowInput?.addEventListener('change', updateSourceStructure);
            backSourceButton?.addEventListener('click', () => showImportStep('source'));
            backMappingButton?.addEventListener('click', () => showImportStep('mapping'));
            mappingReviewButton?.addEventListener('click', () => prepareImportPlan('review'));

            resolutionOpenButton?.addEventListener('click', () => {
                showResolutionPreview();
            });

            backReviewButton?.addEventListener('click', () => {
                showImportStep('review');
            });

            identityOpenButton?.addEventListener('click', () => {
                if (!resolutionPreview) {
                    showToastMessage(
                        'Run Resolution Preview before reviewing New Student identities.',
                        'error'
                    );
                    return;
                }

                showIdentityCompletion(resolutionPreview);
                showImportStep('identity');
            });

            backResolutionButton?.addEventListener('click', () => {
                showImportStep('resolution');
            });

            identityRecheckButton?.addEventListener('click', () => {
                showResolutionPreview(
                    identityOverrides(),
                    'identity',
                    identityRecheckButton
                );
            });

            academicEvidenceOpenButton?.addEventListener('click', () => {
                if (!resolutionPreview) {
                    showToastMessage(
                        'Run Resolution Preview before reviewing Academic Context evidence.',
                        'error'
                    );
                    return;
                }

                showAcademicContextEvidence(resolutionPreview);
            });

            backIdentityButton?.addEventListener('click', () => {
                if (resolutionPreview) {
                    showIdentityCompletion(resolutionPreview);
                }

                showImportStep('identity');
            });

            importPlanOpenButton?.addEventListener('click', () => prepareImportPlan('review'));
            importPlanRecheckButton?.addEventListener('click', () => prepareImportPlan('review'));
            importConfirmButton?.addEventListener('click', confirmClassListImport);
            reviewConfirmButton?.addEventListener('click', () => prepareImportPlan('confirm'));
            backSourceSetupButton?.addEventListener('click', () => showImportStep('source'));
            backReviewFixButton?.addEventListener('click', () => renderReviewAndFix(importPlanPreview));

            importPlanStep?.addEventListener('input', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                const card = target.closest('[data-review-identity-row]');
                if (card && target.hasAttribute('data-review-identity-field')) {
                    const row = card.getAttribute('data-review-identity-row');
                    if (!row) return;
                    const existing = identityOverrideState[row] || {};
                    identityOverrideState[row] = {
                        first_name: normalizedValue(existing.first_name),
                        middle_name: normalizedValue(existing.middle_name),
                        last_name: normalizedValue(existing.last_name),
                        suffix: normalizedValue(existing.suffix),
                    };
                    identityOverrideState[row][target.getAttribute('data-review-identity-field')] = normalizedValue(target.value);
                }
            });

            importPlanStep?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element) || !target.closest('[data-class-list-apply-bulk-context]')) return;
                const bulk = importPlanStep.querySelector('[data-class-list-bulk-context]');
                if (!bulk) return;
                const field = (name) => bulk.querySelector(`[data-bulk-context-field="${name}"]`)?.value || '';
                const decision = {
                    semester: normalizedValue(field('semester')),
                    academic_level: normalizedValue(field('academic_level')),
                    program_id: normalizedValue(field('program_id')),
                    section_id: normalizedValue(field('section_id')),
                    year_level: normalizedValue(field('year_level')),
                    effective_start: normalizedValue(field('effective_start')),
                };
                bulkContextState = { ...decision };
                bulk.querySelectorAll('[data-bulk-context-row]:checked').forEach((checkbox) => {
                    academicContextDecisionOverrides[String(checkbox.value)] = { ...decision };
                });
                renderReviewAndFix(importPlanPreview);
                showToastMessage('The reviewed academic context was applied to the selected rows. Revalidate Review & Fix before confirmation.', 'success');
            });

            backAcademicEvidenceButton?.addEventListener('click', () => {
                if (resolutionPreview) {
                    showAcademicContextEvidence(resolutionPreview);
                }
            });

            importOpenButton?.addEventListener('click', openImportModal);
            importCloseButtons.forEach((button) => {
                button.addEventListener('click', closeImportModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && importModal?.getAttribute('aria-hidden') === 'false') {
                    closeImportModal();
                }
            });

            importForm?.addEventListener('submit', async (event) => {
                event.preventDefault();

                const file = importFileInput?.files?.[0];

                if (!file) {
                    showInlineImportFeedback('Choose an Excel or CSV Class List file first.');
                    return;
                }

                const allowedExtensions = ['xlsx', 'xls', 'csv'];
                const extension = file.name.split('.').pop()?.toLowerCase() || '';

                if (!allowedExtensions.includes(extension)) {
                    showInlineImportFeedback('Use an Excel (.xlsx, .xls) or CSV (.csv) Class List file.');
                    return;
                }

                if (file.size > 10 * 1024 * 1024) {
                    showInlineImportFeedback('The Class List file must be 10 MB or smaller.');
                    return;
                }

                const submitButton = importForm.querySelector('[data-class-list-import-submit]');
                const originalButtonHtml = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i data-lucide="loader-circle"></i><span>Extracting Source...</span>';

                if (window.lucide) {
                    window.lucide.createIcons();
                }

                try {
                    const response = await fetch(`${window.APP_URL}/actions/teacher/parse_class_list.php`, {
                        method: 'POST',
                        body: new FormData(importForm),
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const result = await readApiResponse(
                        response,
                        'APRISM could not read the Class List response. Please try again.'
                    );

                    if (!response.ok || result.success !== true) {
                        throw new Error(result.message || 'The Class List source could not be extracted.');
                    }

                    if (result.code !== 'SOURCE_EXTRACTED' || !result.data) {
                        throw new Error(result.message || 'APRISM could not prepare the source for mapping.');
                    }

                    showSourcePreview(result.data);
                    buildMappingFields();
                    applyAutomaticMappings();

                    const requiredCorrections = Array.isArray(result.data.analysis?.needs_correction)
                        ? result.data.analysis.needs_correction
                        : [];

                    if (requiredCorrections.length === 0 && mappingProblemList(selectedMappings()).length === 0) {
                        await prepareImportPlan('review');
                        showToastMessage('Source analyzed. Review only the rows that need attention.', 'success');
                    } else {
                        showSourcePreview(result.data, true);
                        showToastMessage('APRISM needs help only with the source details shown here.', 'info');
                    }
                } catch (error) {
                    showToastMessage(
                        error instanceof Error ? error.message : 'The Class List source could not be extracted.',
                        'error'
                    );
                } finally {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonHtml;

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
            });

            loadClassList();
        });
    </script>

</body>

</html>
