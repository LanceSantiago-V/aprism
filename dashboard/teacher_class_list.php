<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
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

$subjectLabel = trim(
    (string) ($classContext['subject_code'] ?? '')
);

if ($subjectLabel !== '') {
    $subjectLabel .= ' — ';
}

$subjectLabel .= (string) $classContext['subject_name'];

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

                <div class="page-header-left">

                    <h1 class="page-title">
                        Class List
                    </h1>

                    <div class="page-meta">

                        <span class="page-meta-item">
                            <i data-lucide="book-open"></i>
                            <?= htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <span class="page-meta-divider">•</span>

                        <span class="page-meta-item">
                            <i data-lucide="users"></i>
                            <?= htmlspecialchars((string) $classContext['section_name'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <span class="page-meta-divider">•</span>

                        <span class="page-meta-item">
                            <i data-lucide="calendar-range"></i>
                            <?= htmlspecialchars((string) $classContext['semester'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                </div>

                <div class="page-header-right">

                    <button type="button" class="teacher-primary-btn" data-class-list-import-open>
                        <i data-lucide="upload"></i>
                        <span>Import Class List</span>
                    </button>

                    <a href="<?= APP_URL ?>/dashboard/teacher_my_class.php" class="teacher-my-classes-secondary-btn">
                        <i data-lucide="arrow-left"></i>
                        <span>Back to My Classes</span>
                    </a>

                </div>

            </div>

            <div class="teacher-class-meta mb-4">

                <?php if (empty($classSchedules)): ?>

                    <span class="teacher-chip">
                        <i data-lucide="calendar-x"></i>
                        No active schedule
                    </span>

                <?php else: ?>

                    <?php foreach ($classSchedules as $scheduleText): ?>

                        <span class="teacher-chip">
                            <i data-lucide="clock-3"></i>
                            <?= htmlspecialchars($scheduleText, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <div class="teacher-info-box mb-4">
                <i data-lucide="info"></i>
                <p class="teacher-info-text">
                    This workspace reads the roster already attached to this Operational Class.
                    You may upload an official roster file for preflight validation, but rows will
                    not be parsed or saved until the real PSCS/Oracle export structure and field
                    mapping are approved.
                </p>
            </div>

            <section class="teacher-panel">

                <div class="teacher-panel-header">

                    <div>
                        <h2 class="teacher-panel-title">
                            Enrolled Students
                        </h2>
                        <p class="teacher-panel-subtitle" data-class-list-summary>
                            Loading the current Class List…
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
                                <th>Student Number</th>
                                <th>Student Name</th>
                                <th>Academic Context</th>
                                <th>Class Status</th>
                                <th>Enrolled At</th>
                            </tr>
                        </thead>

                        <tbody data-class-list-body>
                            <tr>
                                <td colspan="5">
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
                                This upload is limited to file and class-access validation. APRISM will not
                                infer columns, create Students, or create enrollments until the official
                                PSCS/Oracle mapping is approved.
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
                            <span class="teacher-import-step-kicker">Step 2 of 7</span>
                            <h3>Source Preview</h3>
                            <p>Review the detected workbook structure before mapping it as a bulk Class List.</p>
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
                                    data-lucide="arrow-right"></i><span>Continue to Mapping</span></button>
                        </div>
                    </div>

                    <div hidden data-class-list-import-mapping-step>
                        <div class="teacher-import-step-heading">
                            <span class="teacher-import-step-kicker">Step 3 of 7</span>
                            <h3>Map Columns</h3>
                            <p>Match each APRISM Class List field with the column containing that information. The
                                selected mapping applies to every detected student row.</p>
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
                            <span class="teacher-import-step-kicker">Step 4 of 7</span>
                            <h3>Bulk Roster Review</h3>
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
                            <span class="teacher-import-step-kicker">Step 5 of 7</span>
                            <h3>Resolution Preview</h3>
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
                            <span class="teacher-import-step-kicker">Step 6 of 7</span>
                            <h3>New Student Identity Completion</h3>
                            <p>
                                Complete only the structured identity details required for unmatched
                                Student Numbers. Source values remain evidence and are not changed.
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
                            <span class="teacher-import-step-kicker">Step 7 of 7</span>
                            <h3>Academic Context Evidence Review</h3>
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

                            <button type="button" class="teacher-primary-btn"
                                data-class-list-import-close>
                                Close Preview
                            </button>
                        </div>
                    </div>
                </form>

            </div>

        </div>

    </div>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const operationalClassId = <?= json_encode($operationalClassId) ?>;
            const endpoint = `${window.APP_URL}/actions/teacher/get_class_list.php?operational_class_id=${encodeURIComponent(operationalClassId)}`;
            const tableBody = document.querySelector('[data-class-list-body]');
            const countBadge = document.querySelector('[data-class-list-count]');
            const summary = document.querySelector('[data-class-list-summary]');
            const searchInput = document.querySelector('[data-class-list-search]');
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
            const mappingReviewButton = document.querySelector('[data-class-list-import-review-open]');
            const mappingOpenButton = document.querySelector('[data-class-list-import-mapping-open]');
            const chooseAnotherButton = document.querySelector('[data-class-list-import-choose-another]');
            const backSourceButton = document.querySelector('[data-class-list-import-back-source]');
            const backMappingButton = document.querySelector('[data-class-list-import-back-mapping]');

            let students = [];
            let sourceExtraction = null;
            let sourceToken = '';
            let resolutionPreview = null;
            const canonicalFields = [
                ['student_number', 'Student Number', true],
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

            const showToastMessage = (message, type = 'info') => {
                const titles = {
                    success: 'Success',
                    error: 'Action Failed',
                    warning: 'Warning',
                    info: 'Information',
                };

                if (typeof window.showToast === 'function') {
                    window.showToast(titles[type] || titles.info, message, type);
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
                sourceExtraction = null;
                sourceToken = '';
                resolutionPreview = null;
                showImportStep('upload');
            };

            const showImportStep = (step) => {
                if (uploadStep) uploadStep.hidden = step !== 'upload';
                if (sourceStep) sourceStep.hidden = step !== 'source';
                if (mappingStep) mappingStep.hidden = step !== 'mapping';
                if (reviewStep) reviewStep.hidden = step !== 'review';
                if (resolutionStep) resolutionStep.hidden = step !== 'resolution';
                if (identityStep) identityStep.hidden = step !== 'identity';
                if (academicEvidenceStep) academicEvidenceStep.hidden = step !== 'academic-evidence';
                if (window.lucide) window.lucide.createIcons();
            };

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
            }[character]));

            const normalizedValue = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();

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

            const showSourcePreview = (data) => {
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
                if (sourcePreview) sourcePreview.innerHTML = `<h4>Detected source columns</h4><p class="teacher-class-list-column-list">${headers.map((header) => `<span>${escapeHtml(header.raw_label || header.label)}</span>`).join('') || 'No source columns detected.'}</p><h4>Raw worksheet preview</h4><div class="teacher-table-wrapper"><table class="teacher-table"><thead><tr><th>Row</th>${Array.from({ length: structure.column_count || 0 }, (_, index) => `<th>${String.fromCharCode(65 + index)}</th>`).join('')}</tr></thead><tbody>${(structure.rows || []).map((row) => `<tr><td>${escapeHtml(row.row_number)}</td>${(row.values || []).map((value) => `<td>${escapeHtml(value || '—')}</td>`).join('')}</tr>`).join('') || '<tr><td>No source rows detected.</td></tr>'}</tbody></table></div>`;
                if (modalFeedback) modalFeedback.hidden = true;
                showImportStep('source');
            };

            const updateSourceStructure = async () => {
                if (!sourceToken || !importForm) return;
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
                    showSourcePreview(result.data);
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
                    : '<p><strong>Structurally Valid preview:</strong> required mapped source values are present. Student identity, academic context, and persistence remain unresolved and unavailable.</p>';
                const visibleFields = canonicalFields.filter(([key]) => mappings[key]);
                if (reviewTable) reviewTable.innerHTML = `<table class="teacher-table"><thead><tr><th>Source Row</th>${visibleFields.map(([, label]) => `<th>${escapeHtml(label)}</th>`).join('')}<th>Structural Status</th></tr></thead><tbody>${rows.map(({ record, errors }) => `<tr><td>${escapeHtml(record.source_row_number)}</td>${visibleFields.map(([key]) => `<td>${escapeHtml(record[key] || '—')}</td>`).join('')}<td>${escapeHtml(errors.join('; ') || 'Structurally Valid')}</td></tr>`).join('')}</tbody></table>`;
                showImportStep('review');
            };

            const identityOverrides = () => {
                const overrides = {};

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
                                <div><dt>Student Number</dt><dd>${escapeHtml(row.student_number || '—')}</dd></div>
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
                            <span class="is-invalid"><strong>${escapeHtml((summary.academic_context_unresolved || 0) + (summary.academic_context_ambiguous || 0))}</strong> need future review</span>
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
                                            <td>${escapeHtml(row.student_number || '—')}</td>
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

                    if ((previewSummary.academic_context_ambiguous || 0) > 0) {
                        notices.push(
                            `${previewSummary.academic_context_ambiguous} row(s) have more than one possible Academic Enrollment.`
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
                                <td>${escapeHtml(row.student_number || '—')}</td>
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

                cell.colSpan = 5;
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
                    : 'Incomplete academic placement';
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
                    emptyState(
                        'users-round',
                        'No Students Attached Yet',
                        'This Operational Class has no Student Class Enrollment records.'
                    );
                    return;
                }

                if (visibleStudents.length === 0) {
                    emptyState(
                        'search-x',
                        'No Matching Students',
                        'No student in this Class List matches your search.'
                    );
                    return;
                }

                tableBody.replaceChildren();

                visibleStudents.forEach((student) => {
                    const row = document.createElement('tr');
                    const studentNumberCell = document.createElement('td');
                    const studentNameCell = document.createElement('td');
                    const contextCell = document.createElement('td');
                    const statusCell = document.createElement('td');
                    const enrolledAtCell = document.createElement('td');
                    const name = document.createElement('strong');
                    const badge = document.createElement('span');

                    studentNumberCell.textContent = student.student_number || '—';
                    name.textContent = student.display_name || '—';
                    studentNameCell.appendChild(name);
                    contextCell.textContent = formatAcademicContext(student);
                    badge.className = `teacher-badge ${statusClass(student.class_status)}`;
                    badge.textContent = student.class_status || '—';
                    statusCell.appendChild(badge);
                    enrolledAtCell.textContent = formatEnrolledAt(student.enrolled_at);

                    row.append(
                        studentNumberCell,
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
                        : 'Current Student Class Enrollment records for this Operational Class.';

                    renderStudents();
                } catch (error) {
                    countBadge.textContent = 'Unavailable';
                    summary.textContent = 'The current Class List could not be read.';
                    emptyState(
                        'triangle-alert',
                        'Class List Unavailable',
                        error instanceof Error
                            ? error.message
                            : 'The Class List could not be loaded.'
                    );
                }
            };

            searchInput?.addEventListener('input', renderStudents);
            importFileInput?.addEventListener('change', () => {
                const file = importFileInput.files?.[0];
                if (importFileName) importFileName.textContent = file ? file.name : 'No file selected';
            });

            chooseAnotherButton?.addEventListener('click', () => {
                importForm?.reset();
                if (importFileName) importFileName.textContent = 'No file selected';
                sourceExtraction = null;
                sourceToken = '';
                resolutionPreview = null;
                showImportStep('upload');
                window.setTimeout(() => importFileInput?.focus(), 0);
            });
            mappingOpenButton?.addEventListener('click', showMapping);
            worksheetSelect?.addEventListener('change', updateSourceStructure);
            headerRowInput?.addEventListener('change', updateSourceStructure);
            firstDataRowInput?.addEventListener('change', updateSourceStructure);
            backSourceButton?.addEventListener('click', () => showImportStep('source'));
            backMappingButton?.addEventListener('click', () => showImportStep('mapping'));
            mappingReviewButton?.addEventListener('click', showReview);

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
                    showToastMessage('Choose an Excel or CSV Class List file first.', 'error');
                    return;
                }

                const allowedExtensions = ['xlsx', 'xls', 'csv'];
                const extension = file.name.split('.').pop()?.toLowerCase() || '';

                if (!allowedExtensions.includes(extension)) {
                    showToastMessage('Use an Excel (.xlsx, .xls) or CSV (.csv) Class List file.', 'error');
                    return;
                }

                if (file.size > 10 * 1024 * 1024) {
                    showToastMessage('The Class List file must be 10 MB or smaller.', 'error');
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
                    showToastMessage(result.message || 'Source extracted. Review the detected source before continuing to mapping.', 'success');
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