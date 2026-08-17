<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [
    ROLE_TEACHER
];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

$pageTitle = 'My Classes';

$activePage = 'my_classes';

$roleStylesheet = 'assets/css/teacher.css';

$pageStylesheet = 'assets/css/pages/teacher-my-class.css';


/*
|--------------------------------------------------------------------------
| Active School Year
|--------------------------------------------------------------------------
*/

$currentSchoolYear = null;
$currentSemester = null;

try {

    $stmt = $pdo->query("
        SELECT
            school_year_id,
            school_year
        FROM school_years
        WHERE status = 'Active'
        ORDER BY school_year_id DESC
        LIMIT 1
    ");

    $activeSchoolYearRow =
        $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($activeSchoolYearRow) {
        $currentSchoolYear =
            $activeSchoolYearRow['school_year'];

        $semesterStmt = $pdo->prepare("
            SELECT DISTINCT semester
            FROM academic_periods
            WHERE school_year_id = ?
              AND is_archived = 0
              AND start_date <= CURDATE()
              AND end_date >= CURDATE()
              AND semester IS NOT NULL
              AND semester <> ''
            ORDER BY semester
        ");

        $semesterStmt->execute([
            (int) $activeSchoolYearRow['school_year_id']
        ]);

        $semesterValues =
            $semesterStmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($semesterValues) === 1) {
            $currentSemester =
                (string) $semesterValues[0];
        }
    }

} catch (PDOException $e) {

    error_log(
        '[APRISM Active Academic Context] ' .
        $e->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| Operational Classes
|--------------------------------------------------------------------------
|
| Retrieve only Active operational classes belonging to the
| authenticated Teacher under the currently Active School Year.
|
| Teacher ownership is determined exclusively through the
| authenticated session. The browser never supplies teacher_id.
|
*/

$operationalClasses = [];

try {

    if ($currentSchoolYear !== null) {

        $stmt = $pdo->prepare("
            SELECT
                oc.operational_class_id,
                oc.teacher_id,
                oc.subject_id,
                oc.section_id,
                oc.school_year,
                oc.semester,
                oc.status,

                s.subject_name,

                sec.section_name,

                cs.class_schedule_id,
                cs.day,
                cs.start_time,
                cs.end_time,
                cs.room

            FROM operational_classes AS oc

            INNER JOIN subjects AS s
                ON s.subject_id = oc.subject_id

            INNER JOIN sections AS sec
                ON sec.section_id = oc.section_id

            INNER JOIN class_schedules AS cs
                ON cs.operational_class_id =
                    oc.operational_class_id
                AND cs.status = 'Active'

            WHERE oc.teacher_id = ?
              AND oc.school_year = ?
              AND oc.status = 'Active'

            ORDER BY
                s.subject_name ASC,
                sec.section_name ASC,
                FIELD(
                    cs.day,
                    'Monday',
                    'Tuesday',
                    'Wednesday',
                    'Thursday',
                    'Friday',
                    'Saturday'
                ),
                cs.start_time ASC,
                cs.class_schedule_id ASC
        ");

        $stmt->execute([
            (int) $_SESSION['user_id'],
            $currentSchoolYear
        ]);

        $rows = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($rows as $row) {

            $operationalClasses[] = [

                'operationalClassId' =>
                    (int) $row['operational_class_id'],

                'classScheduleId' =>
                    (int) $row['class_schedule_id'],

                'subject' =>
                    $row['subject_name'],

                'section' =>
                    $row['section_name'],

                'schedule' =>
                    trim(
                        (string) $row['day'] .
                        ' ' .
                        date(
                            'h:i A',
                            strtotime(
                                $row['start_time']
                            )
                        ) .
                        ' - ' .
                        date(
                            'h:i A',
                            strtotime(
                                $row['end_time']
                            )
                        )
                    ),

                'room' =>
                    trim(
                        (string) ($row['room'] ?? '')
                    ) ?: '—',

                'semester' =>
                    $row['semester']

            ];

        }

    }

} catch (PDOException $e) {

    error_log(
        '[APRISM Teacher My Classes] ' .
        $e->getMessage()
    );

}

?>

<!DOCTYPE html>
<html lang="en">

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>


        <!-- ==============================================================
        FLASH MESSAGES
        =============================================================== -->

        <div id="teacherPageFlashMessages" hidden
            data-success="<?= htmlspecialchars($flash['success'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-error="<?= htmlspecialchars($flash['error'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-warning="<?= htmlspecialchars($flash['warning'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>


        <div class="content-wrapper">

            <!-- ==========================================================
            PAGE HEADER
            =========================================================== -->

            <div class="page-header">

                <div class="page-header-left">

                    <h1 class="page-title">
                        My Classes
                    </h1>

                </div>

                <div class="page-header-right">

                    <button type="button" class="teacher-primary-btn" data-import-classes-open>

                        <i data-lucide="plus"></i>

                        <span>
                            Import Classes
                        </span>

                    </button>

                </div>

            </div>


            <!-- ==========================================================
            MY CLASSES WORKSPACE
            =========================================================== -->

            <section class="teacher-panel teacher-my-classes-panel">

                <!-- ======================================================
                TOOLBAR
                ======================================================= -->

                <div class="teacher-toolbar">

                    <div class="teacher-search teacher-my-classes-search">

                        <i data-lucide="search"></i>

                        <input type="text" placeholder="Search subject or section..." data-my-classes-search>

                    </div>

                </div>


                <!-- ======================================================
                OPERATIONAL CLASSES TABLE
                ======================================================= -->

                <div class="teacher-table-wrapper">

                    <table class="teacher-table">

                        <thead>

                            <tr>

                                <th class="text-center">
                                    Subject
                                </th>

                                <th class="text-center">
                                    Section
                                </th>

                                <th class="text-center">
                                    Schedule
                                </th>

                                <th class="text-center">
                                    Room
                                </th>

                                <th class="text-center">
                                    Class List
                                </th>

                                <th class="text-center">
                                    AprilTags
                                </th>

                                <th class="text-center">
                                    Attendance
                                </th>

                                <th class="text-center">
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (empty($operationalClasses)): ?>

                                <tr>

                                    <td colspan="8">

                                        <div class="teacher-empty-state teacher-my-classes-empty-state">

                                            <i data-lucide="graduation-cap"></i>

                                            <h3 class="teacher-empty-state-title">
                                                No Operational Classes Yet
                                            </h3>

                                            <p class="teacher-empty-state-text">
                                                Your operational classes will appear here after importing
                                                your official teaching assignments.
                                            </p>

                                        </div>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($operationalClasses as $class): ?>

                                    <tr data-class-row data-search-value="<?= htmlspecialchars(
                                        strtolower(
                                            ($class['subject'] ?? '') .
                                            ' ' .
                                            ($class['section'] ?? '')
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">

                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $class['subject'] ?? '—'
                                                ); ?>
                                            </strong>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $class['section'] ?? '—'
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $class['schedule'] ?? '—'
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $class['room'] ?? '—'
                                            ); ?>
                                        </td>

                                        <td>

                                            <span class="teacher-badge teacher-badge-success">
                                                Ready
                                            </span>

                                        </td>

                                        <td>

                                            <span class="teacher-badge teacher-badge-success">
                                                Generated
                                            </span>

                                        </td>

                                        <td>

                                            <span class="teacher-badge teacher-badge-info">
                                                Not Started
                                            </span>

                                        </td>

                                        <td>

                                            <div class="teacher-action-group">

                                                <button type="button" class="teacher-action-btn" title="Manage Class List">
                                                    <i data-lucide="users"></i>
                                                </button>

                                                <button type="button" class="teacher-action-btn" title="Manage AprilTags">
                                                    <i data-lucide="tag"></i>
                                                </button>

                                                <button type="button" class="teacher-action-btn" title="Attendance">
                                                    <i data-lucide="clipboard-check"></i>
                                                </button>

                                                <button type="button" class="teacher-action-btn" title="Grades">
                                                    <i data-lucide="file-up"></i>
                                                </button>

                                                <button type="button" class="teacher-action-btn" title="Reports">
                                                    <i data-lucide="chart-column"></i>
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                <tr data-my-classes-no-results hidden>

                                    <td colspan="8">

                                        <div class="teacher-empty-state teacher-my-classes-empty-state">

                                            <i data-lucide="search-x"></i>

                                            <h3 class="teacher-empty-state-title">
                                                No Matching Classes
                                            </h3>

                                            <p class="teacher-empty-state-text">
                                                No operational class matches your search.
                                            </p>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </div>

    </main>


    <!-- ==============================================================
    IMPORT CLASSES MODAL
    =============================================================== -->

    <div class="teacher-import-modal" id="teacherImportModal" aria-hidden="true">

        <div class="teacher-import-modal-backdrop" data-import-classes-close></div>


        <div class="teacher-import-modal-dialog" role="dialog" aria-modal="true"
            aria-labelledby="teacherImportModalTitle">

            <!-- ======================================================
            MODAL HEADER
            ======================================================= -->

            <div class="teacher-import-modal-header">

                <div>

                    <h2 class="teacher-import-modal-title" id="teacherImportModalTitle">
                        Import Classes
                    </h2>

                    <p class="teacher-import-modal-description">
                        Choose how you want to initialize your operational classes.
                    </p>

                </div>


                <button type="button" class="teacher-import-modal-close" aria-label="Close" data-import-classes-close>

                    <i data-lucide="x"></i>

                </button>

            </div>


            <!-- ======================================================
            SOURCE SELECTION
            ======================================================= -->

            <div class="teacher-import-modal-body">

                <div class="teacher-import-source-grid">

                    <!-- EXCEL -->

                    <button type="button" class="teacher-import-source-card" data-import-source="excel">

                        <div class="teacher-import-source-icon">
                            <i data-lucide="file-spreadsheet"></i>
                        </div>

                        <div class="teacher-import-source-content">

                            <h3>
                                Excel
                            </h3>

                            <p>
                                Import teaching assignments from an Excel file.
                            </p>

                        </div>

                        <i data-lucide="chevron-right" class="teacher-import-source-arrow"></i>

                    </button>


                    <!-- CSV -->

                    <button type="button" class="teacher-import-source-card" data-import-source="csv">

                        <div class="teacher-import-source-icon">
                            <i data-lucide="file-text"></i>
                        </div>

                        <div class="teacher-import-source-content">

                            <h3>
                                CSV
                            </h3>

                            <p>
                                Import structured teaching assignment data.
                            </p>

                        </div>

                        <i data-lucide="chevron-right" class="teacher-import-source-arrow"></i>

                    </button>


                    <!-- PDF -->

                    <button type="button" class="teacher-import-source-card" data-import-source="pdf">

                        <div class="teacher-import-source-icon">
                            <i data-lucide="file"></i>
                        </div>

                        <div class="teacher-import-source-content">

                            <h3>
                                PDF
                            </h3>

                            <p>
                                Extract class information from a schedule PDF.
                            </p>

                        </div>

                        <i data-lucide="chevron-right" class="teacher-import-source-arrow"></i>

                    </button>


                    <!-- IMAGE -->

                    <button type="button" class="teacher-import-source-card" data-import-source="image">

                        <div class="teacher-import-source-icon">
                            <i data-lucide="image"></i>
                        </div>

                        <div class="teacher-import-source-content">

                            <h3>
                                Image
                            </h3>

                            <p>
                                Upload a schedule image or screenshot.
                            </p>

                        </div>

                        <i data-lucide="chevron-right" class="teacher-import-source-arrow"></i>

                    </button>


                    <!-- MANUAL ENTRY -->

                    <button type="button" class="teacher-import-source-card" data-import-source="manual">

                        <div class="teacher-import-source-icon">
                            <i data-lucide="pen-line"></i>
                        </div>

                        <div class="teacher-import-source-content">

                            <h3>
                                Manual Entry
                            </h3>

                            <p>
                                Enter one operational class manually.
                            </p>

                        </div>

                        <i data-lucide="chevron-right" class="teacher-import-source-arrow"></i>

                    </button>

                </div>


                <div class="teacher-import-modal-note">

                    <i data-lucide="info"></i>

                    <p>
                        APRISM extracts the information needed for
                        operational class management. Missing or uncertain
                        information remains editable during review.
                    </p>

                </div>

            </div>

        </div>

    </div>


    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>


    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {


                /* ==========================================================
                HELPERS
                =========================================================== */

                const escapeHtml =
                    function (value) {

                        return String(
                            value ?? ''
                        )
                            .replace(
                                /&/g,
                                '&amp;'
                            )
                            .replace(
                                /</g,
                                '&lt;'
                            )
                            .replace(
                                />/g,
                                '&gt;'
                            )
                            .replace(
                                /"/g,
                                '&quot;'
                            )
                            .replace(
                                /'/g,
                                '&#039;'
                            );

                    };


                const normalizeValue =
                    function (value) {

                        return String(
                            value ?? ''
                        ).trim();

                    };


                const formatDay =
                    function (value) {

                        const day =
                            normalizeValue(
                                value
                            );

                        if (!day) {
                            return '';
                        }

                        return day
                            .charAt(0)
                            .toUpperCase() +
                            day.slice(1);

                    };


                const formatFileSize =
                    function (bytes) {

                        const size =
                            Number(bytes) || 0;

                        if (size < 1024) {

                            return (
                                size +
                                ' B'
                            );

                        }

                        if (
                            size <
                            1024 * 1024
                        ) {

                            return (
                                Math.round(
                                    size / 1024
                                ) +
                                ' KB'
                            );

                        }

                        return (
                            (
                                size /
                                (
                                    1024 *
                                    1024
                                )
                            ).toFixed(1) +
                            ' MB'
                        );

                    };


                const showToastMessage =
                    function (
                        message,
                        type = 'info'
                    ) {

                        /*
                         * APRISM global toast contract is the same one used
                         * by the Users module:
                         *
                         * showToast(title, text, type)
                         *
                         * The Teacher page must not depend on a function
                         * declared inside another page's DOMContentLoaded
                         * callback. If the shared helper is available, use it.
                         * Otherwise render the exact APRISM toast structure
                         * locally into the shared toastContainer.
                         */

                        const toastTitles = {

                            success:
                                'Success',

                            error:
                                'Action Failed',

                            warning:
                                'Warning',

                            info:
                                'Information'

                        };


                        const title =
                            toastTitles[type] ||
                            toastTitles.info;


                        if (
                            typeof window.showToast ===
                            'function'
                        ) {

                            window.showToast(
                                title,
                                String(message ?? ''),
                                type
                            );

                            return;

                        }


                        let toastContainer =
                            document.getElementById(
                                'toastContainer'
                            );


                        /*
                         * The Users page uses the shared APRISM toast container.
                         * Teacher My Classes must remain safe even when the shared
                         * container has not been rendered yet. Create the same
                         * container on demand instead of silently dropping the toast.
                         */
                        if (!toastContainer) {

                            toastContainer =
                                document.createElement('div');

                            toastContainer.id = 'toastContainer';

                            toastContainer.className = 'toast-container-custom';

                            toastContainer.style.position = 'fixed';
                            toastContainer.style.top = '1.5rem';
                            toastContainer.style.right = '1.5rem';
                            toastContainer.style.zIndex = '99999';
                            toastContainer.style.display = 'flex';
                            toastContainer.style.flexDirection = 'column';
                            toastContainer.style.gap = '12px';
                            toastContainer.style.pointerEvents = 'none';

                            toastContainer.setAttribute('aria-live', 'polite');

                            toastContainer.setAttribute('aria-atomic', 'true');

                            document.body.appendChild(toastContainer);

                        }


                        const toast =
                            document.createElement(
                                'div'
                            );


                        toast.className =
                            'toast-custom';

                        toast.style.pointerEvents = 'auto';


                        let icon =
                            'check';


                        if (
                            type ===
                            'error'
                        ) {

                            icon =
                                'circle-alert';

                        } else if (
                            type ===
                            'warning'
                        ) {

                            icon =
                                'alert-circle';

                        } else if (
                            type ===
                            'info'
                        ) {

                            icon =
                                'info';

                        }


                        const safeMessage =
                            escapeHtml(
                                String(
                                    message ?? ''
                                )
                            );


                        const safeTitle =
                            escapeHtml(
                                title
                            );


                        toast.innerHTML = `
<div class="toast-icon ${escapeHtml(type)}">
    <i data-lucide="${icon}"></i>
</div>

<div class="toast-content">
    <h5 class="toast-title">${safeTitle}</h5>
    <p class="toast-text">${safeMessage}</p>
</div>
`;


                        toastContainer.appendChild(
                            toast
                        );


                        if (window.lucide) {

                            lucide.createIcons();

                        }


                        window.setTimeout(
                            function () {

                                toast.classList.add(
                                    'show'
                                );

                            },
                            10
                        );


                        window.setTimeout(
                            function () {

                                toast.classList.remove(
                                    'show'
                                );


                                window.setTimeout(
                                    function () {

                                        toast.remove();

                                    },
                                    300
                                );

                            },
                            4500
                        );

                    };


                const showFlashMessages =
                    function () {

                        const flash =
                            document.getElementById(
                                'teacherPageFlashMessages'
                            );


                        if (!flash) {
                            return;
                        }


                        const success =
                            flash.dataset.success ||
                            '';


                        const error =
                            flash.dataset.error ||
                            '';


                        const warning =
                            flash.dataset.warning ||
                            '';


                        if (success) {

                            showToastMessage(
                                success,
                                'success'
                            );

                        }


                        if (error) {

                            showToastMessage(
                                error,
                                'error'
                            );

                        }


                        if (warning) {

                            showToastMessage(
                                warning,
                                'warning'
                            );

                        }

                    };


                const createNormalizedClassData =
                    function (
                        data,
                        source = 'manual'
                    ) {

                        return {

                            source:
                                source,

                            source_row:
                                data.source_row ??
                                null,

                            subject:
                                normalizeValue(
                                    data.subject ??
                                    data.subject_name
                                ),

                            subjectCode:
                                normalizeValue(
                                    data.subjectCode ??
                                    data.subject_code
                                ),

                            units:
                                normalizeValue(
                                    data.units
                                ),

                            programCode:
                                normalizeValue(
                                    data.programCode ??
                                    data.program_code
                                ),

                            programName:
                                normalizeValue(
                                    data.programName ??
                                    data.program_name
                                ),

                            section:
                                normalizeValue(
                                    data.section ??
                                    data.section_name
                                ),

                            yearLevel:
                                normalizeValue(
                                    data.yearLevel ??
                                    data.year_level
                                ),

                            schoolYear:
                                normalizeValue(
                                    activeAcademicContext.schoolYear ||
                                    data.schoolYear ||
                                    data.school_year
                                ),

                            semester:
                                normalizeValue(
                                    activeAcademicContext.semester ||
                                    data.semester
                                ),

                            room:
                                normalizeValue(
                                    data.room
                                ),

                            day:
                                normalizeValue(
                                    data.day
                                ),

                            startTime:
                                normalizeValue(
                                    data.startTime ??
                                    data.start_time
                                ),

                            endTime:
                                normalizeValue(
                                    data.endTime ??
                                    data.end_time
                                ),

                            validation:
                                data.validation ??
                                null,

                            referenceRequirements:
                                data.reference_requirements ??
                                data.referenceRequirements ??
                                []

                        };

                    };


                const buildBackendPayload =
                    function (
                        classData
                    ) {

                        return {

                            source:
                                classData.source ||
                                importState.source ||
                                'manual',

                            source_file_name:
                                importState.file
                                    ? importState.file.name
                                    : '',

                            source_row:
                                classData.source_row ??
                                null,

                            /*
                             * Teacher-facing schedule contract.
                             *
                             * Only these fields are submitted as normal
                             * Teacher input. Institutional IDs/codes and
                             * persistent reference metadata are resolved
                             * server-side. Conditional Review fields are
                             * added only when the server explicitly asks
                             * for the minimum authoritative information
                             * needed to establish a new reference record.
                             */

                            subject_name:
                                classData.subject,

                            section_name:
                                classData.section,

                            school_year:
                                classData.schoolYear,

                            semester:
                                classData.semester,

                            day:
                                classData.day,

                            start_time:
                                classData.startTime,

                            end_time:
                                classData.endTime,

                            room:
                                classData.room

                        };

                    };


                const validateClassData =
                    function (
                        classData
                    ) {

                        const errors = [];


                        if (
                            !classData.subject
                        ) {

                            errors.push({
                                field:
                                    'Subject',
                                message:
                                    'Subject is required.'
                            });

                        }


                        if (
                            !classData.section
                        ) {

                            errors.push({
                                field:
                                    'Section',
                                message:
                                    'Section is required.'
                            });

                        }


                        if (
                            !classData.day
                        ) {

                            errors.push({
                                field:
                                    'Day',
                                message:
                                    'Class day is required.'
                            });

                        }


                        if (
                            !classData.startTime
                        ) {

                            errors.push({
                                field:
                                    'Start Time',
                                message:
                                    'Start time is required.'
                            });

                        }


                        if (
                            !classData.endTime
                        ) {

                            errors.push({
                                field:
                                    'End Time',
                                message:
                                    'End time is required.'
                            });

                        }


                        if (
                            classData.startTime &&
                            classData.endTime
                        ) {

                            const startParts =
                                classData.startTime
                                    .split(':');

                            const endParts =
                                classData.endTime
                                    .split(':');


                            if (
                                startParts.length >= 2 &&
                                endParts.length >= 2
                            ) {

                                const startMinutes =
                                    (
                                        Number(
                                            startParts[0]
                                        ) *
                                        60
                                    ) +
                                    Number(
                                        startParts[1]
                                    );


                                const endMinutes =
                                    (
                                        Number(
                                            endParts[0]
                                        ) *
                                        60
                                    ) +
                                    Number(
                                        endParts[1]
                                    );


                                if (
                                    endMinutes <=
                                    startMinutes
                                ) {

                                    errors.push({
                                        field:
                                            'Class Time',
                                        message:
                                            'End time must be later than start time.'
                                    });

                                }

                            }

                        }


                        return errors;

                    };


                const renderFieldValue =
                    function (
                        value
                    ) {

                        return escapeHtml(
                            value || '—'
                        );

                    };


                /* ==========================================================
                ELEMENTS
                =========================================================== */

                const modal =
                    document.getElementById(
                        'teacherImportModal'
                    );


                const openButton =
                    document.querySelector(
                        '[data-import-classes-open]'
                    );


                const closeButtons =
                    document.querySelectorAll(
                        '[data-import-classes-close]'
                    );


                const sourceSelection =
                    document.querySelector(
                        '.teacher-import-source-grid'
                    );


                const modalBody =
                    document.querySelector(
                        '.teacher-import-modal-body'
                    );


                const sourceButtons =
                    document.querySelectorAll(
                        '[data-import-source]'
                    );


                if (
                    !modal ||
                    !openButton ||
                    !modalBody
                ) {

                    return;

                }


                /* ==========================================================
                IMPORT STATE
                =========================================================== */

                let importState = {

                    source:
                        null,

                    file:
                        null,

                    classData:
                        null

                };


                const activeAcademicContext = <?= json_encode(
                    [
                        'schoolYear' => $currentSchoolYear,
                        'semester' => $currentSemester
                    ],
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                ) ?>;


                /* ==========================================================
                CLEAR ACTIVE IMPORT STEP
                =========================================================== */

                const clearImportStepViews =
                    function () {

                        const stepSelectors = [

                            '.teacher-import-file-upload',

                            '.teacher-import-manual-entry',

                            '.teacher-import-review',

                            '.teacher-import-validation'

                        ];


                        stepSelectors.forEach(
                            function (selector) {

                                modalBody
                                    .querySelectorAll(
                                        selector
                                    )
                                    .forEach(
                                        function (element) {

                                            element.remove();

                                        }
                                    );

                            }
                        );

                    };


                /* ==========================================================
                RECREATE REVIEW CONTAINER
                =========================================================== */

                const recreateReviewContainer =
                    function () {

                        clearImportStepViews();


                        const container =
                            document.createElement(
                                'div'
                            );


                        container.className =
                            'teacher-import-review';


                        modalBody.prepend(
                            container
                        );


                        return container;

                    };


                /* ==========================================================
                FILE IMPORT SOURCES
                =========================================================== */

                const fileImportSources = {

                    excel: {

                        title:
                            'Excel Import',

                        description:
                            'Upload your official teaching assignment Excel file.',

                        accepted:
                            '.xlsx,.xls',

                        acceptedLabel:
                            'Excel files (.xlsx, .xls)',

                        icon:
                            'file-spreadsheet'

                    },


                    csv: {

                        title:
                            'CSV Import',

                        description:
                            'Upload your official teaching assignment CSV file.',

                        accepted:
                            '.csv',

                        acceptedLabel:
                            'CSV files (.csv)',

                        icon:
                            'file-text'

                    },


                    pdf: {

                        title:
                            'PDF Import',

                        description:
                            'PDF extraction is not implemented yet. Excel and CSV are currently supported.',

                        accepted:
                            '.pdf',

                        acceptedLabel:
                            'PDF files (.pdf)',

                        icon:
                            'file'

                    },


                    image: {

                        title:
                            'Image Import',

                        description:
                            'Image/OCR extraction is not implemented yet. Excel and CSV are currently supported.',

                        accepted:
                            '.jpg,.jpeg,.png,.webp',

                        acceptedLabel:
                            'Image files (.jpg, .jpeg, .png, .webp)',

                        icon:
                            'image'

                    }

                };


                /* ==========================================================
                RESET MODAL
                =========================================================== */

                const resetImportModal =
                    function () {

                        clearImportStepViews();


                        importState = {

                            source:
                                null,

                            file:
                                null,

                            classData:
                                null

                        };


                        if (sourceSelection) {

                            sourceSelection.hidden =
                                false;

                        }


                        const note =
                            modalBody.querySelector(
                                '.teacher-import-modal-note'
                            );


                        if (note) {

                            note.hidden =
                                false;

                        }


                        modalBody.scrollTop =
                            0;


                        if (window.lucide) {

                            lucide.createIcons();

                        }

                    };


                /* ==========================================================
                OPEN MODAL
                =========================================================== */

                const openModal =
                    function () {

                        resetImportModal();


                        modal.setAttribute(
                            'aria-hidden',
                            'false'
                        );


                        document.body.classList.add(
                            'teacher-import-modal-open'
                        );

                    };


                /* ==========================================================
                CLOSE MODAL
                =========================================================== */

                const closeModal =
                    function () {

                        modal.setAttribute(
                            'aria-hidden',
                            'true'
                        );


                        document.body.classList.remove(
                            'teacher-import-modal-open'
                        );


                        resetImportModal();

                    };


                /* ==========================================================
                SHOW MANUAL ENTRY
                =========================================================== */

                const showManualEntry =
                    function (
                        existingData = null
                    ) {

                        if (!sourceSelection) {
                            return;
                        }


                        clearImportStepViews();


                        sourceSelection.hidden =
                            true;


                        const note =
                            modalBody.querySelector(
                                '.teacher-import-modal-note'
                            );


                        if (note) {
                            note.hidden = true;
                        }


                        const data =
                            existingData || {};


                        modalBody.insertAdjacentHTML(
                            'afterbegin',
                            `

<div class="teacher-import-manual-entry">

    <div class="teacher-import-step-header">

        <button
            type="button"
            class="teacher-import-back-btn"
            data-import-back
        >

            <i data-lucide="arrow-left"></i>

            <span>
                Back
            </span>

        </button>


        <div>

            <h3 class="teacher-import-step-title">
                Manual Entry
            </h3>


            <p class="teacher-import-step-description">
                Enter the normal teaching and schedule information available to you.
                APRISM will resolve institutional reference data automatically.
            </p>

        </div>

    </div>


    <form
        class="teacher-import-manual-form"
        id="teacherManualClassForm"
        novalidate
    >

        <div class="teacher-import-form-grid">


            <div class="teacher-import-form-field">

                <label for="manualSubject">
                    Subject
                </label>

                <input
                    type="text"
                    id="manualSubject"
                    name="subject"
                    placeholder="Enter subject name"
                    value="${escapeHtml(
                                data.subject || ''
                            )}"
                    required
                >

            </div>


            <div class="teacher-import-form-field">

                <label for="manualSection">
                    Section
                </label>

                <input
                    type="text"
                    id="manualSection"
                    name="section_name"
                    placeholder="Enter section"
                    value="${escapeHtml(
                                data.section || ''
                            )}"
                    required
                >

            </div>


            <div class="teacher-import-form-field">

                <label for="manualRoom">
                    Room
                </label>

                <input
                    type="text"
                    id="manualRoom"
                    name="room"
                    placeholder="Enter room"
                    value="${escapeHtml(
                                data.room || ''
                            )}"
                >

            </div>


            <div class="teacher-import-form-field">

                <label for="manualDay">
                    Day
                </label>

                <select
                    id="manualDay"
                    name="day"
                    required
                >

                    <option value="">
                        Select day
                    </option>

                    <option value="Monday"
                        ${data.day === 'Monday' ? 'selected' : ''}>
                        Monday
                    </option>

                    <option value="Tuesday"
                        ${data.day === 'Tuesday' ? 'selected' : ''}>
                        Tuesday
                    </option>

                    <option value="Wednesday"
                        ${data.day === 'Wednesday' ? 'selected' : ''}>
                        Wednesday
                    </option>

                    <option value="Thursday"
                        ${data.day === 'Thursday' ? 'selected' : ''}>
                        Thursday
                    </option>

                    <option value="Friday"
                        ${data.day === 'Friday' ? 'selected' : ''}>
                        Friday
                    </option>

                    <option value="Saturday"
                        ${data.day === 'Saturday' ? 'selected' : ''}>
                        Saturday
                    </option>

                </select>

            </div>


            <div class="teacher-import-form-field">

                <label for="manualStartTime">
                    Start Time
                </label>

                <input
                    type="time"
                    id="manualStartTime"
                    name="start_time"
                    value="${escapeHtml(
                                data.startTime || ''
                            )}"
                    required
                >

            </div>


            <div class="teacher-import-form-field">

                <label for="manualEndTime">
                    End Time
                </label>

                <input
                    type="time"
                    id="manualEndTime"
                    name="end_time"
                    value="${escapeHtml(
                                data.endTime || ''
                            )}"
                    required
                >

            </div>


        </div>


        <div class="teacher-import-review-note">

            <i data-lucide="info"></i>

            <p>
                School Year, semester, subject codes, program information,
                units, and database IDs are handled by APRISM from the
                active academic context and authoritative reference data.
            </p>

        </div>


        <div class="teacher-import-form-actions">

            <button
                type="submit"
                class="teacher-primary-btn"
            >

                <i data-lucide="arrow-right"></i>

                <span>
                    Continue to Review
                </span>

            </button>

        </div>

    </form>

</div>

`
                        );


                        if (window.lucide) {
                            lucide.createIcons();
                        }


                        const backButton =
                            modalBody.querySelector(
                                '[data-import-back]'
                            );


                        if (backButton) {

                            backButton.addEventListener(
                                'click',
                                function () {

                                    resetImportModal();

                                }
                            );

                        }


                        const manualForm =
                            document.getElementById(
                                'teacherManualClassForm'
                            );


                        if (!manualForm) {
                            return;
                        }


                        manualForm.addEventListener(
                            'submit',
                            function (event) {

                                event.preventDefault();


                                const formData =
                                    new FormData(
                                        manualForm
                                    );


                                const rawData = {

                                    subject:
                                        formData.get(
                                            'subject'
                                        ),

                                    section_name:
                                        formData.get(
                                            'section_name'
                                        ),

                                    room:
                                        formData.get(
                                            'room'
                                        ),

                                    day:
                                        formData.get(
                                            'day'
                                        ),

                                    start_time:
                                        formData.get(
                                            'start_time'
                                        ),

                                    end_time:
                                        formData.get(
                                            'end_time'
                                        )

                                };


                                /*
                                 * Read the live form values directly and validate
                                 * them before creating any Review state. This prevents
                                 * stale values from a previous import from leaking
                                 * into the new Review screen.
                                 */
                                const requiredManualFields = [
                                    {
                                        value: rawData.subject,
                                        message: 'Subject is required.'
                                    },
                                    {
                                        value: rawData.section_name,
                                        message: 'Section is required.'
                                    },
                                    {
                                        value: rawData.day,
                                        message: 'Class day is required.'
                                    },
                                    {
                                        value: rawData.start_time,
                                        message: 'Start time is required.'
                                    },
                                    {
                                        value: rawData.end_time,
                                        message: 'End time is required.'
                                    }
                                ];

                                const missingManualField =
                                    requiredManualFields.find(
                                        function (field) {
                                            return !String(field.value ?? '').trim();
                                        }
                                    );

                                if (missingManualField) {

                                    showToastMessage(
                                        missingManualField.message,
                                        'warning'
                                    );

                                    return;

                                }


                                const classData =
                                    createNormalizedClassData(
                                        rawData,
                                        'manual'
                                    );


                                /* Never reuse the previous import's section or schedule. */
                                importState.classData = null;


                                const errors =
                                    validateClassData(
                                        classData
                                    );


                                if (
                                    errors.length > 0
                                ) {

                                    showToastMessage(
                                        errors[0].message,
                                        'warning'
                                    );

                                    return;

                                }


                                importState.source =
                                    'manual';


                                importState.file =
                                    null;


                                importState.classData =
                                    classData;


                                const reviewContainer =
                                    recreateReviewContainer();


                                renderEditableReview(
                                    classData,
                                    'manual',
                                    null,
                                    reviewContainer
                                );

                            }
                        );

                    };


                /* ==========================================================
                SHOW FILE UPLOAD
                =========================================================== */

                const showFileUpload =
                    function (
                        source
                    ) {

                        const config =
                            fileImportSources[
                            source
                            ];


                        if (!config) {
                            return;
                        }


                        if (
                            source !== 'excel' &&
                            source !== 'csv'
                        ) {

                            showToastMessage(
                                config.description,
                                'info'
                            );

                            return;

                        }


                        if (!sourceSelection) {
                            return;
                        }


                        clearImportStepViews();


                        sourceSelection.hidden =
                            true;


                        const note =
                            modalBody.querySelector(
                                '.teacher-import-modal-note'
                            );


                        if (note) {

                            note.hidden =
                                true;

                        }


                        modalBody.insertAdjacentHTML(
                            'afterbegin',
                            `

<div class="teacher-import-file-upload">

    <div class="teacher-import-step-header">

        <button
            type="button"
            class="teacher-import-back-btn"
            data-file-import-back
        >

            <i data-lucide="arrow-left"></i>

            <span>
                Back
            </span>

        </button>


        <div>

            <h3 class="teacher-import-step-title">
                ${escapeHtml(
                                config.title
                            )}
            </h3>


            <p class="teacher-import-step-description">
                ${escapeHtml(
                                config.description
                            )}
            </p>

        </div>

    </div>


    <div class="teacher-import-upload-area">

        <input
            type="file"
            id="teacherImportFile"
            class="teacher-import-file-input"
            accept="${escapeHtml(
                                config.accepted
                            )}"
        >


        <label
            for="teacherImportFile"
            class="teacher-import-dropzone"
        >

            <div class="teacher-import-upload-icon">

                <i data-lucide="${escapeHtml(
                                config.icon
                            )}"></i>

            </div>


            <strong>
                Choose a file
            </strong>


            <span>
                or drag and drop it here
            </span>


            <small>
                ${escapeHtml(
                                config.acceptedLabel
                            )}
            </small>

        </label>


        <div
            class="teacher-import-selected-file"
            data-selected-file
            hidden
        >

            <div class="teacher-import-selected-file-icon">

                <i data-lucide="${escapeHtml(
                                config.icon
                            )}"></i>

            </div>


            <div class="teacher-import-selected-file-info">

                <strong data-selected-file-name>
                </strong>

                <span data-selected-file-size>
                </span>

            </div>


            <button
                type="button"
                class="teacher-import-remove-file"
                data-remove-file
                aria-label="Remove selected file"
            >

                <i data-lucide="x"></i>

            </button>

        </div>

    </div>


    <div class="teacher-import-form-actions">

        <button
            type="button"
            class="teacher-primary-btn"
            data-file-continue
            disabled
        >

            <i data-lucide="arrow-right"></i>

            <span>
                Continue
            </span>

        </button>

    </div>

</div>

`
                        );


                        if (window.lucide) {

                            lucide.createIcons();

                        }


                        const fileInput =
                            document.getElementById(
                                'teacherImportFile'
                            );


                        const selectedFile =
                            modalBody.querySelector(
                                '[data-selected-file]'
                            );


                        const selectedFileName =
                            modalBody.querySelector(
                                '[data-selected-file-name]'
                            );


                        const selectedFileSize =
                            modalBody.querySelector(
                                '[data-selected-file-size]'
                            );


                        const removeFileButton =
                            modalBody.querySelector(
                                '[data-remove-file]'
                            );


                        const continueButton =
                            modalBody.querySelector(
                                '[data-file-continue]'
                            );


                        const backButton =
                            modalBody.querySelector(
                                '[data-file-import-back]'
                            );


                        if (fileInput) {

                            fileInput.addEventListener(
                                'change',
                                function () {

                                    const file =
                                        fileInput.files[0];


                                    if (!file) {
                                        return;
                                    }


                                    importState.source =
                                        source;


                                    importState.file =
                                        file;


                                    if (
                                        selectedFileName
                                    ) {

                                        selectedFileName.textContent =
                                            file.name;

                                    }


                                    if (
                                        selectedFileSize
                                    ) {

                                        selectedFileSize.textContent =
                                            formatFileSize(
                                                file.size
                                            );

                                    }


                                    if (
                                        selectedFile
                                    ) {

                                        selectedFile.hidden =
                                            false;

                                    }


                                    if (
                                        continueButton
                                    ) {

                                        continueButton.disabled =
                                            false;

                                    }

                                }
                            );

                        }


                        if (removeFileButton) {

                            removeFileButton.addEventListener(
                                'click',
                                function () {

                                    if (fileInput) {

                                        fileInput.value =
                                            '';

                                    }


                                    if (selectedFile) {

                                        selectedFile.hidden =
                                            true;

                                    }


                                    if (continueButton) {

                                        continueButton.disabled =
                                            true;

                                    }


                                    importState.file =
                                        null;

                                }
                            );

                        }


                        if (backButton) {

                            backButton.addEventListener(
                                'click',
                                function () {

                                    resetImportModal();

                                }
                            );

                        }


                        if (continueButton) {

                            continueButton.addEventListener(
                                'click',
                                function () {

                                    if (
                                        !fileInput ||
                                        !fileInput.files.length
                                    ) {

                                        showToastMessage(
                                            'Please select an import file first.',
                                            'warning'
                                        );

                                        return;

                                    }


                                    const file =
                                        fileInput.files[0];


                                    showFileReview(
                                        source,
                                        file
                                    );

                                }
                            );

                        }

                    };


                /* ==========================================================
                PARSE FILE THROUGH IMPORT ENGINE
                =========================================================== */

                const parseImportedFile =
                    async function (
                        source,
                        file
                    ) {

                        const formData =
                            new FormData();


                        formData.append(
                            'source',
                            source
                        );


                        formData.append(
                            'import_file',
                            file
                        );


                        const response =
                            await fetch(
                                '<?= APP_URL ?>/actions/teacher/parse_import_class.php',
                                {
                                    method:
                                        'POST',

                                    body:
                                        formData,

                                    credentials:
                                        'same-origin',

                                    headers: {
                                        'Accept':
                                            'application/json',

                                        'X-Requested-With':
                                            'XMLHttpRequest'
                                    }
                                }
                            );


                        let result;


                        try {

                            result =
                                await response.json();

                        } catch (error) {

                            throw new Error(
                                'APRISM could not read the Import Engine response.'
                            );

                        }


                        if (
                            !response.ok ||
                            !result.success
                        ) {

                            throw new Error(
                                result.message ||
                                'The imported file could not be processed.'
                            );

                        }


                        if (
                            !result.data ||
                            !result.data.row
                        ) {

                            throw new Error(
                                'The Import Engine did not return a class record.'
                            );

                        }


                        return createNormalizedClassData(
                            result.data.row,
                            source
                        );

                    };


                /* ==========================================================
                SHOW FILE REVIEW
                =========================================================== */

                const showFileReview =
                    async function (
                        source,
                        file
                    ) {

                        if (!modalBody) {
                            return;
                        }


                        clearImportStepViews();


                        if (sourceSelection) {

                            sourceSelection.hidden =
                                true;

                        }


                        const note =
                            modalBody.querySelector(
                                '.teacher-import-modal-note'
                            );


                        if (note) {

                            note.hidden =
                                true;

                        }


                        importState.source =
                            source;


                        importState.file =
                            file;


                        const reviewContainer =
                            document.createElement(
                                'div'
                            );


                        reviewContainer.className =
                            'teacher-import-review';


                        reviewContainer.innerHTML = `

<div class="teacher-import-step-header">

    <button
        type="button"
        class="teacher-import-back-btn"
        data-file-review-back
    >

        <i data-lucide="arrow-left"></i>

        <span>
            Back
        </span>

    </button>


    <div>

        <h3 class="teacher-import-step-title">
            Review Class Information
        </h3>


        <p class="teacher-import-step-description">
            Reading the selected file and preparing the normalized class information.
        </p>

    </div>

</div>


<div class="teacher-import-validation-status">

    <div class="teacher-import-validation-status-icon">

        <i data-lucide="loader-circle"></i>

    </div>


    <div>

        <strong>
            Reading Import File
        </strong>

        <p>
            APRISM is extracting the class information. No records are being created yet.
        </p>

    </div>

</div>

`;


                        modalBody.prepend(
                            reviewContainer
                        );


                        if (window.lucide) {

                            lucide.createIcons();

                        }


                        const backButton =
                            reviewContainer.querySelector(
                                '[data-file-review-back]'
                            );


                        if (backButton) {

                            backButton.addEventListener(
                                'click',
                                function () {

                                    showFileUpload(
                                        source
                                    );

                                }
                            );

                        }


                        try {

                            const classData =
                                await parseImportedFile(
                                    source,
                                    file
                                );


                            importState.classData =
                                classData;


                            renderEditableReview(
                                classData,
                                source,
                                file,
                                reviewContainer
                            );

                        } catch (error) {

                            reviewContainer.remove();


                            showFileUpload(
                                source
                            );


                            showToastMessage(
                                error.message ||
                                'The import file could not be processed.',
                                'error'
                            );

                        }

                    };


                /* ==========================================================
                RENDER EDITABLE REVIEW
                =========================================================== */

                const renderEditableReview =
                    function (
                        classData,
                        source,
                        file,
                        container
                    ) {

                        const referenceRequirements =
                            Array.isArray(
                                classData.referenceRequirements
                            )
                                ? classData.referenceRequirements
                                : [];


                        const renderReferenceField =
                            function (field) {

                                const name =
                                    String(
                                        field.name ||
                                        ''
                                    );

                                if (!name) {
                                    return '';
                                }

                                const value =
                                    normalizeValue(
                                        classData[name] ||
                                        ''
                                    );

                                const required =
                                    field.required !== false;

                                const label =
                                    escapeHtml(
                                        field.label ||
                                        name
                                    );

                                const placeholder =
                                    escapeHtml(
                                        field.placeholder ||
                                        ''
                                    );

                                const inputId =
                                    'reviewReference_' +
                                    name;

                                if (
                                    field.type === 'select'
                                ) {

                                    const options =
                                        Array.isArray(
                                            field.options
                                        )
                                            ? field.options
                                            : [];

                                    return `
<div class="teacher-import-review-field">
    <label for="${escapeHtml(inputId)}">
        ${label}
    </label>

    <select
        id="${escapeHtml(inputId)}"
        data-reference-field="${escapeHtml(name)}"
        ${required ? 'required' : ''}
    >
        <option value="">Select ${label}</option>
        ${options.map(function (option) {
                                        const optionValue = String(option ?? '');
                                        return `
        <option
            value="${escapeHtml(optionValue)}"
            ${optionValue === value ? 'selected' : ''}
        >
            ${escapeHtml(optionValue)}
        </option>`;
                                    }).join('')}
    </select>
</div>`;

                                }


                                const inputType =
                                    field.type === 'number'
                                        ? 'number'
                                        : 'text';

                                const min =
                                    field.min !== undefined
                                        ? ` min="${escapeHtml(field.min)}"`
                                        : '';

                                const step =
                                    field.step !== undefined
                                        ? ` step="${escapeHtml(field.step)}"`
                                        : '';

                                return `
<div class="teacher-import-review-field">
    <label for="${escapeHtml(inputId)}">
        ${label}
    </label>

    <input
        type="${inputType}"
        id="${escapeHtml(inputId)}"
        data-reference-field="${escapeHtml(name)}"
        value="${escapeHtml(value)}"
        placeholder="${placeholder}"
        ${min}
        ${step}
        ${required ? 'required' : ''}
    >
</div>`;

                            };


                        const referenceSection =
                            referenceRequirements.length > 0
                                ? `
<div class="teacher-import-review-note">
    <i data-lucide="circle-alert"></i>
    <p>
        APRISM needs the following authoritative information to establish
        a new institutional reference record. Existing records will be reused
        automatically; database IDs are never required here.
    </p>
</div>

<div class="teacher-import-review-grid teacher-import-reference-grid">
    ${referenceRequirements
                                    .map(renderReferenceField)
                                    .join('')}
</div>
`
                                : '';


                        container.innerHTML = `

<div class="teacher-import-step-header">

    <button
        type="button"
        class="teacher-import-back-btn"
        data-review-back
    >

        <i data-lucide="arrow-left"></i>

        <span>
            Back
        </span>

    </button>


    <div>

        <h3 class="teacher-import-step-title">
            Review Class Information
        </h3>


        <p class="teacher-import-step-description">
            Review and correct the teaching and schedule information before validation.
        </p>

    </div>

</div>


<div class="teacher-import-review-grid">

    <div class="teacher-import-review-field">
        <label for="reviewSubject">
            Subject
        </label>

        <input
            type="text"
            id="reviewSubject"
            value="${escapeHtml(classData.subject || '')}"
            required
        >
    </div>


    <div class="teacher-import-review-field">
        <label for="reviewSection">
            Section
        </label>

        <input
            type="text"
            id="reviewSection"
            value="${escapeHtml(classData.section || '')}"
            required
        >
    </div>


    <div class="teacher-import-review-field">
        <label for="reviewSchoolYear">
            School Year
            <small>FROM ACADEMIC SETUP</small>
        </label>

        <input
            type="text"
            id="reviewSchoolYear"
            value="${escapeHtml(activeAcademicContext.schoolYear || classData.schoolYear || '')}"
            readonly
            aria-readonly="true"
        >
    </div>


    <div class="teacher-import-review-field">
        <label for="reviewSemester">
            Semester
            <small>FROM ACADEMIC SETUP</small>
        </label>

        <input
            type="text"
            id="reviewSemester"
            value="${escapeHtml(activeAcademicContext.semester || classData.semester || '')}"
            readonly
            aria-readonly="true"
        >
    </div>


    <div class="teacher-import-review-field">
        <label for="reviewRoom">
            Room
        </label>

        <input
            type="text"
            id="reviewRoom"
            value="${escapeHtml(classData.room || '')}"
        >
    </div>


    <div class="teacher-import-review-field">
        <label for="reviewDay">
            Day
        </label>

        <select
            id="reviewDay"
            required
        >
            <option value="">Select day</option>
            ${['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
                                .map(function (day) {
                                    return `
            <option
                value="${day}"
                ${formatDay(classData.day) === day ? 'selected' : ''}
            >
                ${day}
            </option>`;
                                }).join('')}
        </select>
    </div>


    <div class="teacher-import-review-field">
        <label for="reviewStartTime">
            Start Time
        </label>

        <input
            type="time"
            id="reviewStartTime"
            value="${escapeHtml(classData.startTime || '')}"
            required
        >
    </div>


    <div class="teacher-import-review-field">
        <label for="reviewEndTime">
            End Time
        </label>

        <input
            type="time"
            id="reviewEndTime"
            value="${escapeHtml(classData.endTime || '')}"
            required
        >
    </div>

</div>


${referenceSection}


<div class="teacher-import-review-note">

    <i data-lucide="info"></i>

    <p>
        School Year and Semester come from the active Academic Setup and are locked.
        Subject, Program, and Section reference records are resolved automatically.
        APRISM only asks for additional authoritative values when a new record must be established.
    </p>

</div>


<div class="teacher-import-form-actions">

    <button
        type="button"
        class="teacher-primary-btn"
        data-file-review-continue
    >

        <i data-lucide="arrow-right"></i>

        <span>
            Continue to Validation
        </span>

    </button>

</div>

`;


                        if (window.lucide) {
                            lucide.createIcons();
                        }


                        const backButton =
                            container.querySelector(
                                '[data-review-back]'
                            );


                        if (backButton) {
                            backButton.addEventListener(
                                'click',
                                function () {

                                    if (source === 'manual') {
                                        showManualEntry(classData);
                                    } else {
                                        showFileUpload(source);
                                    }

                                }
                            );
                        }


                        const continueButton =
                            container.querySelector(
                                '[data-file-review-continue]'
                            );


                        if (continueButton) {
                            continueButton.addEventListener(
                                'click',
                                async function () {

                                    const updatedData = {
                                        ...classData,
                                        source: source,
                                        source_row: classData.source_row,
                                        subject:
                                            document.getElementById('reviewSubject')?.value.trim() || '',
                                        section:
                                            document.getElementById('reviewSection')?.value.trim() || '',
                                        schoolYear:
                                            activeAcademicContext.schoolYear || '',
                                        semester:
                                            activeAcademicContext.semester || '',
                                        room:
                                            document.getElementById('reviewRoom')?.value.trim() || '',
                                        day:
                                            document.getElementById('reviewDay')?.value.trim() || '',
                                        startTime:
                                            document.getElementById('reviewStartTime')?.value.trim() || '',
                                        endTime:
                                            document.getElementById('reviewEndTime')?.value.trim() || ''
                                    };


                                    container
                                        .querySelectorAll('[data-reference-field]')
                                        .forEach(function (field) {
                                            const name =
                                                field.getAttribute('data-reference-field');

                                            if (name) {
                                                updatedData[name] =
                                                    field.value?.trim?.() || '';
                                            }
                                        });


                                    /*
                                     * Revalidate the live Review values before the
                                     * server request. Academic context remains locked,
                                     * while Subject/Section/Schedule remain teacher-editable.
                                     */
                                    const clientErrors =
                                        validateClassData(updatedData);

                                    if (clientErrors.length > 0) {

                                        showToastMessage(
                                            clientErrors[0].message,
                                            'warning'
                                        );

                                        return;

                                    }


                                    importState.classData = updatedData;

                                    continueButton.disabled = true;
                                    continueButton.innerHTML = `
<i data-lucide="loader-circle"></i>
<span>Validating...</span>
`;

                                    if (window.lucide) {
                                        lucide.createIcons();
                                    }


                                    try {
                                        const validationResult =
                                            await validateWithServer(updatedData);

                                        continueButton.disabled = false;
                                        continueButton.innerHTML = `
<i data-lucide="arrow-right"></i>
<span>Continue to Validation</span>
`;

                                        if (window.lucide) {
                                            lucide.createIcons();
                                        }


                                        if (
                                            validationResult.requiresReview
                                        ) {

                                            const reviewFields =
                                                Array.isArray(
                                                    validationResult.reviewFields
                                                )
                                                    ? validationResult.reviewFields
                                                    : [];


                                            /*
                                             * requires_review is only a real
                                             * Review-step request when the server
                                             * actually tells us which additional
                                             * authoritative fields are needed.
                                             *
                                             * If the server returns requires_review
                                             * with no fields, do NOT rebuild the
                                             * same Review screen. That was the
                                             * reason the button appeared to do
                                             * nothing.
                                             */
                                            if (
                                                reviewFields.length > 0
                                            ) {

                                                updatedData.referenceRequirements =
                                                    reviewFields;


                                                if (
                                                    validationResult.data
                                                ) {

                                                    Object.assign(
                                                        updatedData,
                                                        validationResult.data
                                                    );

                                                }


                                                importState.classData =
                                                    updatedData;


                                                renderEditableReview(
                                                    updatedData,
                                                    source,
                                                    file,
                                                    container
                                                );


                                                showToastMessage(
                                                    validationResult.message ||
                                                    'Additional authoritative information is required before APRISM can continue.',
                                                    'warning'
                                                );


                                                return;

                                            }


                                            /*
                                             * A review request without fields is
                                             * an action-level server result.
                                             * Keep the editable Review screen open
                                             * and explain the actual server message
                                             * with the APRISM toast.
                                             */
                                            importState.classData =
                                                updatedData;


                                            showToastMessage(
                                                validationResult.message ||
                                                'APRISM could not validate the class information.',
                                                'error'
                                            );


                                            return;

                                        }


                                        /*
                                         * Server-side conflicts must not move the
                                         * Teacher into a dead-end validation screen.
                                         *
                                         * A duplicate class, schedule conflict, or
                                         * other operational validation failure is an
                                         * action-level result: show the existing
                                         * global APRISM toast, keep the modal open,
                                         * and leave the Teacher on Review.
                                         */
                                        const serverValidationErrors =
                                            validationResult.validation &&
                                                Array.isArray(
                                                    validationResult.validation.errors
                                                )
                                                ? validationResult.validation.errors
                                                : [];


                                        if (
                                            serverValidationErrors.length > 0
                                        ) {

                                            const firstServerError =
                                                serverValidationErrors[0];


                                            const serverErrorMessage =
                                                (
                                                    firstServerError &&
                                                    typeof firstServerError === 'object'
                                                )
                                                    ? (
                                                        firstServerError.message ||
                                                        validationResult.message ||
                                                        'The class information could not be validated.'
                                                    )
                                                    : (
                                                        String(
                                                            firstServerError ||
                                                            validationResult.message ||
                                                            'The class information could not be validated.'
                                                        )
                                                    );


                                            importState.classData =
                                                updatedData;


                                            renderEditableReview(
                                                updatedData,
                                                source,
                                                file,
                                                container
                                            );


                                            showToastMessage(
                                                serverErrorMessage,
                                                'error'
                                            );


                                            return;

                                        }


                                        if (
                                            !validationResult.success
                                        ) {

                                            importState.classData =
                                                updatedData;


                                            renderEditableReview(
                                                updatedData,
                                                source,
                                                file,
                                                container
                                            );


                                            showToastMessage(
                                                validationResult.message ||
                                                'The class information could not be validated.',
                                                'error'
                                            );


                                            return;

                                        }


                                        if (validationResult.data) {
                                            Object.assign(
                                                updatedData,
                                                validationResult.data
                                            );
                                        }

                                        updatedData.validation =
                                            validationResult.validation || {
                                                is_valid: true,
                                                errors: [],
                                                warnings: []
                                            };

                                        importState.classData = updatedData;

                                        showImportValidation(
                                            updatedData,
                                            source,
                                            file,
                                            validationResult
                                        );

                                    } catch (error) {
                                        continueButton.disabled = false;
                                        continueButton.innerHTML = `
<i data-lucide="arrow-right"></i>
<span>Continue to Validation</span>
`;

                                        if (window.lucide) {
                                            lucide.createIcons();
                                        }

                                        showToastMessage(
                                            error.message ||
                                            'APRISM could not validate the class information.',
                                            'error'
                                        );
                                    }

                                }
                            );
                        }

                    };


                const validateWithServer =
                    async function (classData) {

                        const formData =
                            new FormData();

                        formData.append(
                            'validation_only',
                            '1'
                        );

                        formData.append(
                            'import_data',
                            JSON.stringify(
                                buildBackendPayload(classData)
                            )
                        );

                        const response =
                            await fetch(
                                '<?= APP_URL ?>/actions/teacher/import_class.php',
                                {
                                    method: 'POST',
                                    body: formData,
                                    credentials: 'same-origin',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                }
                            );

                        let result;

                        try {
                            result = await response.json();
                        } catch (error) {
                            throw new Error(
                                'APRISM could not read the server validation response.'
                            );
                        }

                        return {
                            success:
                                Boolean(result.success),
                            requiresReview:
                                Boolean(result.requires_review),
                            reviewFields:
                                Array.isArray(result.review_fields)
                                    ? result.review_fields
                                    : [],
                            message:
                                result.message ||
                                'The class information could not be validated.',
                            validation:
                                result.validation || null,
                            data:
                                result.data || null
                        };

                    };


                /* ==========================================================
                SHOW IMPORT VALIDATION
                =========================================================== */

                const showImportValidation =
                    function (
                        classData,
                        source = null,
                        file = null,
                        serverValidation = null
                    ) {

                        clearImportStepViews();


                        if (sourceSelection) {

                            sourceSelection.hidden =
                                true;

                        }


                        const note =
                            modalBody.querySelector(
                                '.teacher-import-modal-note'
                            );


                        if (note) {

                            note.hidden =
                                true;

                        }


                        importState.classData =
                            classData;


                        if (source !== null) {

                            importState.source =
                                source;

                        }


                        if (file !== null) {

                            importState.file =
                                file;

                        }


                        const errors =
                            validateClassData(
                                classData
                            );


                        const validation =
                            classData.validation;


                        const engineErrors =
                            validation &&
                                Array.isArray(
                                    validation.errors
                                )
                                ? validation.errors
                                : [];


                        const engineWarnings =
                            validation &&
                                Array.isArray(
                                    validation.warnings
                                )
                                ? validation.warnings
                                : [];


                        const serverErrors =
                            serverValidation &&
                                Array.isArray(
                                    serverValidation.validation?.errors
                                )
                                ? serverValidation.validation.errors
                                : [];


                        const combinedErrors =
                            errors.map(
                                function (error) {

                                    return {

                                        field:
                                            error.field,

                                        message:
                                            error.message

                                    };

                                }
                            );


                        engineErrors.forEach(
                            function (error) {

                                const exists =
                                    combinedErrors.some(
                                        function (item) {

                                            return (
                                                item.field ===
                                                error.field &&
                                                item.message ===
                                                error.message
                                            );

                                        }
                                    );


                                if (!exists) {

                                    combinedErrors.push({

                                        field:
                                            error.field ||
                                            'Import',

                                        message:
                                            error.message ||
                                            'The imported value requires correction.'

                                    });

                                }

                            }
                        );


                        serverErrors.forEach(
                            function (error) {

                                combinedErrors.push({
                                    field:
                                        error.field ||
                                        'Server Validation',
                                    message:
                                        error.message ||
                                        'The class information failed server-side validation.'
                                });

                            }
                        );


                        if (
                            serverValidation &&
                            serverValidation.success === false &&
                            !serverValidation.requiresReview
                        ) {
                            combinedErrors.push({
                                field: 'Server Validation',
                                message:
                                    serverValidation.message ||
                                    'The class information failed server-side validation.'
                            });
                        }


                        const finalValid =
                            combinedErrors.length === 0;


                        const container =
                            document.createElement(
                                'div'
                            );


                        container.className =
                            'teacher-import-validation';


                        container.innerHTML = `

<div class="teacher-import-step-header">

    <button
        type="button"
        class="teacher-import-back-btn"
        data-validation-back
    >

        <i data-lucide="arrow-left"></i>

        <span>
            Back
        </span>

    </button>


    <div>

        <h3 class="teacher-import-step-title">
            Validate Class Information
        </h3>


        <p class="teacher-import-step-description">
            Final structural review before the server validates and creates the class.
        </p>

    </div>

</div>


<div class="
    teacher-import-validation-status
    ${finalValid
                                ? 'is-valid'
                                : 'has-errors'}
">

    <div class="teacher-import-validation-status-icon">

        <i data-lucide="${finalValid
                                ? 'circle-check'
                                : 'circle-alert'}"></i>

    </div>


    <div>

        <strong>
            ${finalValid
                                ? 'Information Passed Validation'
                                : 'Information Requires Correction'}
        </strong>


        <p>
            ${finalValid
                                ? 'The normalized class data is complete enough to proceed to final server-side validation.'
                                : 'Correct the information identified below before confirming the class.'}
        </p>

    </div>

</div>


<div class="teacher-import-confirmation-panel">

    <div class="teacher-import-confirmation-heading">

        <span>
            Class Information
        </span>

        <small>
            Final review
        </small>

    </div>


    <div class="teacher-import-confirmation-grid">


        <div class="teacher-import-confirmation-item">

            <span>
                Subject
            </span>

            <strong>
                ${renderFieldValue(
                                    classData.subject
                                )}
            </strong>

        </div>


        <div class="teacher-import-confirmation-item">

            <span>
                Section
            </span>

            <strong>
                ${renderFieldValue(
                                    classData.section
                                )}
            </strong>

        </div>


        <div class="teacher-import-confirmation-item">

            <span>
                School Year
            </span>

            <strong>
                ${renderFieldValue(
                                    activeAcademicContext.schoolYear ||
                                    classData.schoolYear
                                )}
            </strong>

            <small>FROM ACADEMIC SETUP</small>

        </div>


        <div class="teacher-import-confirmation-item">

            <span>
                Semester
            </span>

            <strong>
                ${renderFieldValue(
                                    activeAcademicContext.semester ||
                                    classData.semester
                                )}
            </strong>

            <small>FROM ACADEMIC SETUP</small>

        </div>


        <div class="teacher-import-confirmation-item">

            <span>
                Room
            </span>

            <strong>
                ${renderFieldValue(
                                    classData.room
                                )}
            </strong>

        </div>


        <div class="teacher-import-confirmation-item">

            <span>
                Day
            </span>

            <strong>
                ${renderFieldValue(
                                    formatDay(
                                        classData.day
                                    )
                                )}
            </strong>

        </div>


        <div class="teacher-import-confirmation-item">

            <span>
                Start Time
            </span>

            <strong>
                ${renderFieldValue(
                                    classData.startTime
                                )}
            </strong>

        </div>


        <div class="teacher-import-confirmation-item">

            <span>
                End Time
            </span>

            <strong>
                ${renderFieldValue(
                                    classData.endTime
                                )}
            </strong>

        </div>


    </div>

</div>


${!finalValid
                                ? `

<div class="teacher-import-validation-errors">

    <div class="teacher-import-validation-errors-title">

        <i data-lucide="triangle-alert"></i>

        <span>
            Please correct the following
        </span>

    </div>


    <div class="teacher-import-validation-error-list">

        ${combinedErrors
                                    .map(
                                        function (error) {

                                            return `

<div class="teacher-import-validation-error-item">

    <div class="teacher-import-validation-error-label">
        ${escapeHtml(
                                                error.field
                                            )}
    </div>


    <div class="teacher-import-validation-error-message">
        ${escapeHtml(
                                                error.message
                                            )}
    </div>

</div>

`;

                                        }
                                    )
                                    .join('')
                                }

    </div>

</div>

`
                                : ''
                            }


${engineWarnings.length > 0
                                ? `

<div class="teacher-import-review-note">

    <i data-lucide="info"></i>

    <p>

        ${escapeHtml(
                                    engineWarnings
                                        .map(
                                            function (warning) {

                                                return (
                                                    warning.message ||
                                                    warning
                                                );

                                            }
                                        )
                                        .join(' ')
                                )}

    </p>

</div>

`
                                : ''
                            }


<div class="teacher-import-review-note">

    <i data-lucide="shield-check"></i>

    <p>

        ${finalValid
                                ? 'This is the final review stage. Confirming will send the normalized data to the server, where institutional resolution, duplicate checks, schedule conflict checks, and database persistence are performed.'
                                : 'Nothing will be saved yet. Return to Review, correct the values, and validate again.'
                            }

    </p>

</div>


<div class="teacher-import-form-actions">

    <button
        type="button"
        class="teacher-primary-btn"
        data-validation-confirm
        ${finalValid
                                ? ''
                                : 'disabled'}
    >

        <i data-lucide="check"></i>

        <span>
            Confirm Class
        </span>

    </button>

</div>

`;


                        modalBody.prepend(
                            container
                        );


                        if (window.lucide) {

                            lucide.createIcons();

                        }


                        const backButton =
                            container.querySelector(
                                '[data-validation-back]'
                            );


                        if (backButton) {

                            backButton.addEventListener(
                                'click',
                                function () {

                                    const reviewSource =
                                        source ||
                                        importState.source ||
                                        'manual';


                                    const reviewFile =
                                        file ||
                                        importState.file ||
                                        null;


                                    const reviewContainer =
                                        recreateReviewContainer();


                                    renderEditableReview(
                                        classData,
                                        reviewSource,
                                        reviewFile,
                                        reviewContainer
                                    );

                                }
                            );

                        }


                        const confirmButton =
                            container.querySelector(
                                '[data-validation-confirm]'
                            );


                        if (confirmButton) {

                            confirmButton.addEventListener(
                                'click',
                                async function () {

                                    if (!finalValid) {
                                        return;
                                    }


                                    await confirmImportedClass(
                                        classData,
                                        confirmButton
                                    );

                                }
                            );

                        }

                    };


                /* ==========================================================
                CONFIRM IMPORTED CLASS
                =========================================================== */

                const confirmImportedClass =
                    async function (
                        classData,
                        confirmButton
                    ) {

                        confirmButton.disabled =
                            true;


                        confirmButton.innerHTML = `

<i data-lucide="loader-circle"></i>

<span>
    Creating Class...
</span>

`;


                        if (window.lucide) {

                            lucide.createIcons();

                        }


                        const payload =
                            buildBackendPayload(
                                classData
                            );


                        const formData =
                            new FormData();


                        formData.append(
                            'import_data',
                            JSON.stringify(
                                payload
                            )
                        );


                        try {

                            const response =
                                await fetch(
                                    '<?= APP_URL ?>/actions/teacher/import_class.php',
                                    {
                                        method:
                                            'POST',

                                        body:
                                            formData,

                                        credentials:
                                            'same-origin',

                                        headers: {
                                            'Accept':
                                                'application/json',

                                            'X-Requested-With':
                                                'XMLHttpRequest'
                                        },

                                        redirect:
                                            'follow'
                                    }
                                );


                            const contentType =
                                response.headers.get(
                                    'content-type'
                                ) || '';


                            /*
                             * JSON response path.
                             *
                             * This is the expected path once
                             * import_class.php is AJAX-aware.
                             */

                            if (
                                contentType.includes(
                                    'application/json'
                                )
                            ) {

                                const result =
                                    await response.json();


                                if (
                                    !result.success
                                ) {

                                    throw new Error(
                                        result.message ||
                                        'The class could not be created.'
                                    );

                                }


                                /*
                                 * AJAX success path:
                                 *
                                 * 1. Server confirms the transaction.
                                 * 2. Show exactly one APRISM toast.
                                 * 3. Close the modal.
                                 * 4. Refresh the page after the toast has had
                                 *    time to be seen.
                                 *
                                 * Do NOT use sessionStorage here. The backend
                                 * already returns JSON for AJAX requests, so
                                 * carrying another toast across the reload can
                                 * create duplicate notifications.
                                 */
                                showToastMessage(
                                    result.message ||
                                    'Class added to My Classes successfully.',
                                    'success'
                                );

                                closeModal();

                                window.setTimeout(
                                    function () {

                                        window.location.reload();

                                    },
                                    900
                                );

                                return;

                            }


                            /*
                             * Legacy/session-flash fallback.
                             *
                             * If the backend has not yet been replaced
                             * with the AJAX JSON response, preserve the
                             * existing redirect behavior.
                             */

                            if (
                                response.redirected
                            ) {

                                /*
                                 * Legacy redirect fallback:
                                 *
                                 * The non-AJAX backend path stores the APRISM
                                 * flash message in the session. The refreshed
                                 * page will display that single flash toast.
                                 */
                                closeModal();

                                window.setTimeout(
                                    function () {

                                        window.location.href =
                                            response.url;

                                    },
                                    350
                                );

                                return;

                            }


                            if (
                                !response.ok
                            ) {

                                throw new Error(
                                    'The class could not be created. Please try again.'
                                );

                            }


                            closeModal();

                            window.setTimeout(
                                function () {

                                    window.location.reload();

                                },
                                350
                            );

                        } catch (error) {

                            confirmButton.disabled =
                                false;


                            confirmButton.innerHTML = `

<i data-lucide="check"></i>

<span>
    Confirm Class
</span>

`;


                            if (window.lucide) {

                                lucide.createIcons();

                            }


                            showToastMessage(
                                error.message ||
                                'The class could not be created.',
                                'error'
                            );

                        }

                    };


                /* ==========================================================
                SOURCE BUTTONS
                =========================================================== */

                sourceButtons.forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                const source =
                                    button.getAttribute(
                                        'data-import-source'
                                    );


                                if (
                                    source ===
                                    'manual'
                                ) {

                                    showManualEntry();

                                    return;

                                }


                                if (
                                    Object.prototype.hasOwnProperty.call(
                                        fileImportSources,
                                        source
                                    )
                                ) {

                                    showFileUpload(
                                        source
                                    );

                                    return;

                                }

                            }
                        );

                    }
                );


                /* ==========================================================
                OPEN MODAL
                =========================================================== */

                openButton.addEventListener(
                    'click',
                    openModal
                );


                /* ==========================================================
                CLOSE BUTTONS
                =========================================================== */

                closeButtons.forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            closeModal
                        );

                    }
                );


                /* ==========================================================
                ESCAPE KEY
                =========================================================== */

                document.addEventListener(
                    'keydown',
                    function (event) {

                        if (
                            event.key ===
                            'Escape' &&
                            modal.getAttribute(
                                'aria-hidden'
                            ) === 'false'
                        ) {

                            closeModal();

                        }

                    }
                );


                /* ==========================================================
                SEARCH
                =========================================================== */

                const searchInput =
                    document.querySelector(
                        '[data-my-classes-search]'
                    );


                const classRows =
                    document.querySelectorAll(
                        '[data-class-row]'
                    );


                const noResultsRow =
                    document.querySelector(
                        '[data-my-classes-no-results]'
                    );


                if (
                    searchInput &&
                    classRows.length > 0
                ) {

                    searchInput.addEventListener(
                        'input',
                        function () {

                            const query =
                                searchInput.value
                                    .trim()
                                    .toLowerCase();


                            let visibleCount =
                                0;


                            classRows.forEach(
                                function (row) {

                                    const searchValue =
                                        row.dataset.searchValue ||
                                        '';


                                    const matches =
                                        !query ||
                                        searchValue.includes(
                                            query
                                        );


                                    row.hidden =
                                        !matches;


                                    if (matches) {

                                        visibleCount++;

                                    }

                                }
                            );


                            if (noResultsRow) {

                                noResultsRow.hidden =
                                    visibleCount !== 0;

                            }

                        }
                    );

                }


                /* ==========================================================
                INITIALIZATION
                =========================================================== */

                showFlashMessages();


                if (window.lucide) {

                    lucide.createIcons();

                }

            }
        );

    </script>

</body>

</html>