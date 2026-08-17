<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [
    ROLE_ACADEMIC_HEAD
];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';


$pageTitle = 'Academic Setup';
$activePage = 'academic_setup';

$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-academic-setup.css';


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$successMessage = $flash['success'] ?? null;
$errorMessage = $flash['error'] ?? null;
$warningMessage = $flash['warning'] ?? null;


/*
|--------------------------------------------------------------------------
| School Year Records
|--------------------------------------------------------------------------
*/

$schoolYears = [];

try {

    $stmt = $pdo->query("
        SELECT
            school_year_id,
            school_year,
            start_date,
            end_date,
            status,
            created_at,
            updated_at
        FROM school_years
        ORDER BY
            CASE status
                WHEN 'Active' THEN 1
                WHEN 'Inactive' THEN 2
                WHEN 'Archived' THEN 3
                ELSE 4
            END,
            start_date DESC,
            school_year_id DESC
    ");

    $schoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        '[APRISM Academic Setup - School Years] ' .
        $e->getMessage()
    );

    $_SESSION['error_message'] =
        'Unable to load School Year records.';
}


/*
|--------------------------------------------------------------------------
| Active School Year
|--------------------------------------------------------------------------
*/

$currentSchoolYear = null;
$activeSchoolYearId = null;

try {

    $stmt = $pdo->query("
        SELECT
            school_year_id,
            school_year
        FROM school_years
        WHERE status = 'Active'
        LIMIT 1
    ");

    $activeSchoolYear =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeSchoolYear) {

        $activeSchoolYearId =
            (int) $activeSchoolYear['school_year_id'];

        $currentSchoolYear =
            $activeSchoolYear['school_year'];
    }

} catch (PDOException $e) {

    error_log(
        '[APRISM Active School Year] ' .
        $e->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| Academic Period Records
|--------------------------------------------------------------------------
|
| The Terms view is scoped to the currently Active School Year.
|
*/

$academicPeriods = [];

if ($activeSchoolYearId !== null) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                academic_period_id,
                school_year_id,
                academic_level,
                semester,
                period_name,
                start_date,
                end_date,
                is_archived,
                created_at,
                updated_at
            FROM academic_periods
            WHERE school_year_id = ?
            ORDER BY
                CASE academic_level
                    WHEN 'College' THEN 1
                    WHEN 'Senior High School' THEN 2
                    ELSE 3
                END,
                CASE semester
                    WHEN 'First Semester' THEN 1
                    WHEN 'Second Semester' THEN 2
                    ELSE 3
                END,
                start_date ASC,
                academic_period_id ASC
        ");

        $stmt->execute([
            $activeSchoolYearId
        ]);

        $academicPeriods =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log(
            '[APRISM Academic Setup - Academic Periods] ' .
            $e->getMessage()
        );

        $_SESSION['error_message'] =
            'Unable to load Academic Period records.';
    }

}

?>

<!DOCTYPE html>
<html lang="en">

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>

    <style>
        html.aprism-academic-setup-tab-loading .academic-setup-tabs,
        html.aprism-academic-setup-tab-loading .tab-content {
            visibility: hidden;
        }
    </style>

    <script>
        (function () {

            try {

                const navigationEntries =
                    typeof performance !== 'undefined' &&
                        typeof performance.getEntriesByType === 'function'
                        ? performance.getEntriesByType('navigation')
                        : [];

                const navigationType =
                    navigationEntries.length > 0
                        ? navigationEntries[0].type
                        : 'navigate';

                const savedTab =
                    sessionStorage.getItem(
                        'aprism_academic_setup_active_tab'
                    );

                /*
                 * Only hide the tab area when this is a real browser
                 * refresh and the user previously had Terms or
                 * Monitoring Thresholds selected.
                 *
                 * This prevents the initial School Year tab from
                 * flashing before Bootstrap restores the saved tab.
                 */
                if (
                    navigationType === 'reload' &&
                    (
                        savedTab === 'terms' ||
                        savedTab === 'monitoring'
                    )
                ) {

                    document.documentElement.classList.add(
                        'aprism-academic-setup-tab-loading'
                    );

                }

            } catch (error) {

                console.warn(
                    'APRISM Academic Setup tab preload:',
                    error
                );

            }

        })();
    </script>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>


    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>


        <!-- ==========================================================
             PAGE HEADER
        =========================================================== -->

        <section class="page-header">

            <div class="page-header-left">

                <h1 class="page-title">
                    Academic Setup
                </h1>

            </div>

        </section>


        <!-- ==========================================================
             TABS
        =========================================================== -->

        <ul class="nav nav-tabs academic-setup-tabs" role="tablist">

            <!-- SCHOOL YEAR -->

            <li class="nav-item">

                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#school-year-tab" type="button"
                    role="tab" aria-selected="true">

                    <i data-lucide="calendar"></i>

                    School Year

                </button>

            </li>


            <!-- TERMS -->

            <li class="nav-item">

                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#terms-tab" type="button" role="tab"
                    aria-selected="false">

                    <i data-lucide="book-open"></i>

                    Terms

                </button>

            </li>


            <!-- MONITORING -->

            <li class="nav-item">

                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#monitoring-tab" type="button" role="tab"
                    aria-selected="false">

                    <i data-lucide="activity"></i>

                    Monitoring Thresholds

                </button>

            </li>

        </ul>


        <!-- ==========================================================
             TAB CONTENT
        =========================================================== -->

        <div class="tab-content">


            <!-- ======================================================
                 SCHOOL YEAR
            ======================================================= -->

            <div class="tab-pane fade show active" id="school-year-tab" role="tabpanel">

                <section class="module-card">


                    <!-- MODULE HEADER -->

                    <div class="module-header">

                        <div>

                            <h2 class="module-title">
                                School Academic Years
                            </h2>

                            <p class="module-description">
                                Manage institutional academic years used
                                throughout APRISM.
                            </p>

                        </div>


                        <button class="page-action-btn" type="button" data-school-year-modal="create"
                            title="Create School Year">

                            <i data-lucide="plus"></i>

                        </button>

                    </div>


                    <!-- SCHOOL YEAR LIST -->

                    <div class="school-year-list">

                        <?php if (empty($schoolYears)): ?>


                            <!-- EMPTY STATE -->

                            <div class="school-year-empty">

                                <div class="empty-state">

                                    <i data-lucide="calendar-range"></i>

                                    <h3 class="empty-state-title">
                                        No School Years Found
                                    </h3>

                                    <p class="empty-state-text">
                                        Create your first school year to begin
                                        organizing academic terms,
                                        schedules,
                                        attendance,
                                        grades,
                                        and monitoring records.
                                    </p>

                                </div>

                            </div>


                        <?php else: ?>


                            <!-- SCHOOL YEAR RECORDS -->

                            <?php foreach ($schoolYears as $schoolYear): ?>

                                <?php

                                $status =
                                    $schoolYear['status'];

                                $statusClass =
                                    strtolower($status);

                                $startDate =
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $schoolYear['start_date']
                                        )
                                    );

                                $endDate =
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $schoolYear['end_date']
                                        )
                                    );

                                ?>


                                <div class="school-year-card">


                                    <!-- LEFT SIDE -->

                                    <div class="school-year-left">

                                        <div class="school-year-icon">

                                            <i data-lucide="calendar-range"></i>

                                        </div>


                                        <div>

                                            <h3 class="school-year-title">

                                                <?= htmlspecialchars(
                                                    $schoolYear['school_year']
                                                ) ?>

                                            </h3>


                                            <p class="school-year-dates">

                                                <?= htmlspecialchars(
                                                    $startDate
                                                ) ?>

                                                —

                                                <?= htmlspecialchars(
                                                    $endDate
                                                ) ?>

                                            </p>

                                        </div>

                                    </div>


                                    <!-- RIGHT SIDE -->

                                    <div class="d-flex align-items-center gap-3">


                                        <!-- STATUS -->

                                        <span class="school-year-status <?= htmlspecialchars($statusClass) ?>">

                                            <?= htmlspecialchars($status) ?>

                                        </span>


                                        <!-- ACTIONS -->

                                        <div class="school-year-actions">


                                            <!-- EDIT -->

                                            <?php if (
                                                $status !== 'Archived'
                                            ): ?>

                                                <button type="button" class="action-btn" title="Edit School Year"
                                                    data-school-year-modal="edit"
                                                    data-school-year-id="<?= (int) $schoolYear['school_year_id'] ?>"
                                                    data-school-year="<?= htmlspecialchars(
                                                        $schoolYear['school_year'],
                                                        ENT_QUOTES
                                                    ) ?>" data-start-date="<?= htmlspecialchars(
                                                         $schoolYear['start_date'],
                                                         ENT_QUOTES
                                                     ) ?>" data-end-date="<?= htmlspecialchars(
                                                          $schoolYear['end_date'],
                                                          ENT_QUOTES
                                                      ) ?>" data-status="<?= htmlspecialchars(
                                                           $schoolYear['status'],
                                                           ENT_QUOTES
                                                       ) ?>">

                                                    <i data-lucide="square-pen"></i>

                                                </button>

                                            <?php endif; ?>


                                            <!-- ACTIVATE -->

                                            <?php if (
                                                $status === 'Inactive'
                                            ): ?>

                                                <button type="button" class="action-btn" title="Activate School Year"
                                                    data-school-year-modal="activate"
                                                    data-school-year-id="<?= (int) $schoolYear['school_year_id'] ?>"
                                                    data-school-year="<?= htmlspecialchars(
                                                        $schoolYear['school_year'],
                                                        ENT_QUOTES
                                                    ) ?>">

                                                    <i data-lucide="circle-check-big"></i>

                                                </button>

                                            <?php endif; ?>


                                            <!-- ARCHIVE -->

                                            <?php
                                            /*
                                             * School Year archival is controlled
                                             * by the lifecycle transition:
                                             *
                                             * Active current School Year
                                             *      ↓
                                             * replacement School Year activated
                                             *      ↓
                                             * previous Active → Archived
                                             *
                                             * Therefore there is intentionally
                                             * no manual Archive button here.
                                             *
                                             * The backend remains responsible for
                                             * enforcing the actual archival
                                             * transition.
                                             */
                                            ?>


                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </section>

            </div>


            <!-- ======================================================
                 TERMS
            ======================================================= -->

            <div class="tab-pane fade" id="terms-tab" role="tabpanel">

                <section class="module-card">

                    <div class="module-header">

                        <div>

                            <h2 class="module-title">
                                Academic Terms
                            </h2>

                            <p class="module-description">

                                <?php if ($currentSchoolYear): ?>

                                    Configure academic periods for
                                    <?= htmlspecialchars($currentSchoolYear) ?>.

                                <?php else: ?>

                                    Configure academic periods for the
                                    currently active School Year.

                                <?php endif; ?>

                            </p>

                        </div>


                        <button class="page-action-btn" type="button" data-bs-toggle="modal"
                            data-bs-target="#createTermModal" title="Create Academic Term" <?= !$currentSchoolYear ? 'disabled' : '' ?>>

                            <i data-lucide="plus"></i>

                        </button>

                    </div>


                    <div class="term-list">


                        <!-- ==================================================
                             NO ACTIVE SCHOOL YEAR
                        =================================================== -->

                        <?php if (!$currentSchoolYear): ?>

                            <div class="coming-soon-placeholder">

                                <i data-lucide="calendar-x"></i>

                                <h3 class="empty-state-title">
                                    No Active School Year
                                </h3>

                                <p class="empty-state-text">
                                    There is currently no active School Year.
                                    Activate a School Year before configuring
                                    academic periods.
                                </p>

                            </div>


                            <!-- ==================================================
                                 ACTIVE SCHOOL YEAR BUT NO PERIODS
                            =================================================== -->

                        <?php elseif (empty($academicPeriods)): ?>

                            <div class="coming-soon-placeholder">

                                <i data-lucide="book-open"></i>

                                <h3 class="empty-state-title">
                                    Academic Periods Not Configured
                                </h3>

                                <p class="empty-state-text">
                                    <?= htmlspecialchars(
                                        $currentSchoolYear
                                    ) ?>

                                    does not have any Academic Periods
                                    configured yet.
                                </p>

                            </div>


                            <!-- ==================================================
                                 ACADEMIC PERIOD RECORDS
                            ===================================================

                            <?php else: ?>

                            <?php foreach ($academicPeriods as $academicPeriod): ?>

                                <?php

                                /*
                                 * Archived always takes precedence.
                                 */

                                if (
                                    (int) $academicPeriod['is_archived'] === 1
                                ) {

                                    $periodStatus = 'Archived';

                                } else {

                                    $today =
                                        date('Y-m-d');


                                    if (
                                        $today <
                                        $academicPeriod['start_date']
                                    ) {

                                        $periodStatus = 'Upcoming';

                                    } elseif (
                                        $today <=
                                        $academicPeriod['end_date']
                                    ) {

                                        $periodStatus = 'Active';

                                    } else {

                                        $periodStatus = 'Completed';

                                    }

                                }


                                $periodStatusClass =
                                    strtolower(
                                        $periodStatus
                                    );


                                $startDate =
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $academicPeriod['start_date']
                                        )
                                    );


                                $endDate =
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $academicPeriod['end_date']
                                        )
                                    );

                                ?>


                                <div class="school-year-card">


                                    <!-- LEFT SIDE -->

                                <div class="school-year-left">

                                    <div class="school-year-icon">

                                        <i data-lucide="book-open"></i>

                                    </div>


                                    <div>

                                        <h3 class="school-year-title">

                                            <?= htmlspecialchars(
                                                $academicPeriod['period_name']
                                            ) ?>

                                        </h3>


                                        <p class="school-year-dates">

                                            <?= htmlspecialchars(
                                                $academicPeriod['academic_level']
                                            ) ?>


                                            <?php if (
                                                $academicPeriod['semester'] !== null
                                                &&
                                                $academicPeriod['semester'] !== ''
                                            ): ?>

                                                ·

                                                <?= htmlspecialchars(
                                                    $academicPeriod['semester']
                                                ) ?>

                                            <?php endif; ?>


                                            ·

                                            <?= htmlspecialchars(
                                                $startDate
                                            ) ?>

                                            —

                                            <?= htmlspecialchars(
                                                $endDate
                                            ) ?>

                                        </p>

                                    </div>

                                </div>


                                <!-- RIGHT SIDE -->

                                <div class="d-flex align-items-center gap-3">


                                    <!-- DERIVED STATUS -->

                                    <span class="school-year-status <?= htmlspecialchars(
                                        $periodStatusClass
                                    ) ?>">

                                        <?= htmlspecialchars(
                                            $periodStatus
                                        ) ?>

                                    </span>


                                    <!-- ACTIONS -->

                                    <div class="school-year-actions">

                                        <?php if (
                                            (int) $academicPeriod['is_archived'] === 0
                                        ): ?>


                                            <!-- EDIT ACADEMIC PERIOD -->

                                            <button type="button" class="action-btn" title="Edit Academic Period"
                                                data-bs-toggle="modal" data-bs-target="#editTermModal"
                                                data-term-id="<?= (int) $academicPeriod['academic_period_id'] ?>"
                                                data-school-year-id="<?= (int) $academicPeriod['school_year_id'] ?>"
                                                data-academic-level="<?= htmlspecialchars(
                                                    $academicPeriod['academic_level'],
                                                    ENT_QUOTES
                                                ) ?>" data-semester="<?= htmlspecialchars(
                                                     $academicPeriod['semester'] ?? '',
                                                     ENT_QUOTES
                                                 ) ?>" data-period-name="<?= htmlspecialchars(
                                                      $academicPeriod['period_name'],
                                                      ENT_QUOTES
                                                  ) ?>" data-start-date="<?= htmlspecialchars(
                                                       $academicPeriod['start_date'],
                                                       ENT_QUOTES
                                                   ) ?>" data-end-date="<?= htmlspecialchars(
                                                        $academicPeriod['end_date'],
                                                        ENT_QUOTES
                                                    ) ?>">

                                                <i data-lucide="square-pen"></i>

                                            </button>


                                            <!-- ARCHIVE ACADEMIC PERIOD -->

                                            <button type="button" class="action-btn" title="Archive Academic Period"
                                                data-bs-toggle="modal" data-bs-target="#archiveTermModal"
                                                data-term-id="<?= (int) $academicPeriod['academic_period_id'] ?>" data-period-name="<?= htmlspecialchars(
                                                       $academicPeriod['period_name'],
                                                       ENT_QUOTES
                                                   ) ?>">

                                                <i data-lucide="archive"></i>

                                            </button>


                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>


            </div>

            </section>

        </div>


        <!-- ======================================================
                 MONITORING THRESHOLDS
            ======================================================= -->

        <div class="tab-pane fade" id="monitoring-tab" role="tabpanel">

            <section class="module-card">

                <div class="module-header">

                    <div>

                        <h2 class="module-title">
                            Monitoring Thresholds
                        </h2>

                        <p class="module-description">
                            Configure institutional monitoring rules
                            used by APRISM to identify students who
                            may require academic intervention.
                        </p>

                    </div>


                    <button class="page-action-btn" type="button" data-bs-toggle="modal"
                        data-bs-target="#createThresholdModal" title="Create Monitoring Threshold">

                        <i data-lucide="plus"></i>

                    </button>

                </div>


                <div class="threshold-list">

                    <div class="coming-soon-placeholder">

                        <i data-lucide="activity"></i>

                        <h3 class="empty-state-title">
                            No Monitoring Thresholds Found
                        </h3>

                        <p class="empty-state-text">
                            Create monitoring rules that determine
                            attendance, academic, and intervention
                            risk levels.
                        </p>

                    </div>

                </div>

            </section>

        </div>

        </div>

    </main>


    <!-- ==========================================================
         SCHOOL YEAR MODALS
    =========================================================== -->

    <?php
    require __DIR__ .
        '/../includes/modals/academic-setup/school_year_modals.php';
    ?>


    <!-- ==========================================================
         TERM MODALS
    =========================================================== -->

    <?php
    require __DIR__ .
        '/../includes/modals/academic-setup/term_modals.php';
    ?>


    <!-- ==========================================================
         MONITORING THRESHOLD MODALS
    =========================================================== -->

    <?php
    require __DIR__ .
        '/../includes/modals/academic-setup/monitoring_threshold_modals.php';
    ?>


    <!-- ==========================================================
         SHARED COMPONENTS
    =========================================================== -->

    <?php
    require __DIR__ .
        '/../includes/components/logout_modal.php';
    ?>


    <!-- ==========================================================
         SHARED FLASH TOAST
         IMPORTANT:
         This is OUTSIDE <main>, so it cannot create page spacing.
    =========================================================== -->

    <div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>


    <!-- ==========================================================
         TOAST + ACADEMIC SETUP STATE LOGIC
    =========================================================== -->

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                /*
                 * Remove the temporary preload state only after the
                 * saved Academic Setup tab has been restored.
                 */
                document.documentElement.classList.remove(
                    'aprism-academic-setup-tab-loading'
                );


                /*
                 * ======================================================
                 * ACADEMIC SETUP TAB PERSISTENCE
                 * ======================================================
                 *
                 * Remember the selected Academic Setup tab ONLY when
                 * the user refreshes the Academic Setup page.
                 *
                 * This intentionally does NOT persist the selected tab
                 * across normal page navigation.
                 *
                 * Expected behavior:
                 *
                 *   Refresh while on Terms
                 *       -> Terms
                 *
                 *   Refresh while on Monitoring
                 *       -> Monitoring
                 *
                 *   Leave Academic Setup and return
                 *       -> School Year
                 *
                 *   Logout and login again
                 *       -> School Year
                 *
                 *   First visit to Academic Setup
                 *       -> School Year
                 *
                 * Values:
                 *   school-year
                 *   terms
                 *   monitoring
                 *
                 */

                const academicSetupTabs =
                    document.querySelectorAll(
                        '.academic-setup-tabs [data-bs-toggle="tab"]'
                    );


                const academicSetupTabKey =
                    'aprism_academic_setup_active_tab';


                /*
                 * Determine how this page was entered.
                 *
                 * "reload" means the user refreshed the current page.
                 *
                 * "navigate" means the user arrived here through normal
                 * navigation, such as clicking Academic Setup from another
                 * page or logging in again.
                 *
                 * "back_forward" means browser history navigation.
                 *
                 * Only a real page reload should restore the previously
                 * selected Academic Setup tab.
                 */

                let academicSetupNavigationType =
                    'navigate';


                if (
                    typeof performance !== 'undefined'
                    &&
                    typeof performance.getEntriesByType === 'function'
                ) {

                    const navigationEntries =
                        performance.getEntriesByType(
                            'navigation'
                        );


                    if (
                        navigationEntries.length > 0
                        &&
                        navigationEntries[0].type
                    ) {

                        academicSetupNavigationType =
                            navigationEntries[0].type;

                    }

                }


                const isAcademicSetupReload =
                    academicSetupNavigationType === 'reload';


                /*
                 * If the user arrived here through normal navigation,
                 * discard any previous Academic Setup tab preference.
                 *
                 * This is what makes School Year the default when the
                 * user leaves Academic Setup and later returns.
                 */

                if (!isAcademicSetupReload) {

                    sessionStorage.removeItem(
                        academicSetupTabKey
                    );

                }


                function getTabNameFromTarget(
                    target
                ) {

                    if (
                        !target
                        ||
                        !target.startsWith('#')
                    ) {

                        return null;

                    }


                    if (
                        target === '#school-year-tab'
                    ) {

                        return 'school-year';

                    }


                    if (
                        target === '#terms-tab'
                    ) {

                        return 'terms';

                    }


                    if (
                        target === '#monitoring-tab'
                    ) {

                        return 'monitoring';

                    }


                    return null;

                }


                function getTargetFromTabName(
                    tabName
                ) {

                    if (
                        tabName === 'school-year'
                    ) {

                        return '#school-year-tab';

                    }


                    if (
                        tabName === 'terms'
                    ) {

                        return '#terms-tab';

                    }


                    if (
                        tabName === 'monitoring'
                    ) {

                        return '#monitoring-tab';

                    }


                    return null;

                }


                /*
                 * Save the selected tab whenever the user changes tabs.
                 *
                 * This is still sessionStorage because it allows the
                 * selected tab to survive an actual browser refresh.
                 *
                 * The navigation check above clears it whenever the
                 * Academic Setup page is entered normally.
                 */

                academicSetupTabs.forEach(
                    function (tabButton) {

                        tabButton.addEventListener(
                            'shown.bs.tab',
                            function (event) {

                                const target =
                                    event.target.getAttribute(
                                        'data-bs-target'
                                    );


                                const tabName =
                                    getTabNameFromTarget(
                                        target
                                    );


                                if (tabName) {

                                    sessionStorage.setItem(
                                        academicSetupTabKey,
                                        tabName
                                    );

                                }

                            }
                        );

                    }
                );


                /*
                 * Restore the previously selected tab ONLY after an
                 * actual browser refresh.
                 *
                 * Normal navigation intentionally stays on School Year.
                 */

                const savedAcademicSetupTab =
                    isAcademicSetupReload
                        ? sessionStorage.getItem(
                            academicSetupTabKey
                        )
                        : null;


                if (
                    savedAcademicSetupTab
                    &&
                    typeof bootstrap !== 'undefined'
                ) {

                    const savedTarget =
                        getTargetFromTabName(
                            savedAcademicSetupTab
                        );


                    if (savedTarget) {

                        const savedTabButton =
                            document.querySelector(
                                '.academic-setup-tabs [data-bs-target="' +
                                savedTarget +
                                '"]'
                            );


                        if (savedTabButton) {

                            const tab =
                                new bootstrap.Tab(
                                    savedTabButton
                                );


                            tab.show();

                        }

                    }

                }


                /*
                 * ======================================================
                 * CREATE ACADEMIC TERM DRAFT
                 * ======================================================
                 */

                const createTermModal =
                    document.getElementById(
                        'createTermModal'
                    );


                const createTermDraftKey =
                    'aprism_create_academic_term_draft';


                const createTermActionFailed =
                    <?= $errorMessage ? 'true' : 'false' ?>;


                const editTermModal =
                    document.getElementById(
                        'editTermModal'
                    );


                const editTermForm =
                    editTermModal
                        ? editTermModal.querySelector('form')
                        : null;


                function getCreateTermForm() {

                    if (!createTermModal) {

                        return null;

                    }


                    return createTermModal.querySelector(
                        'form'
                    );

                }


                function getCreateTermField(
                    form,
                    selectors
                ) {

                    if (!form) {

                        return null;

                    }


                    for (
                        let i = 0;
                        i < selectors.length;
                        i++
                    ) {

                        const field =
                            form.querySelector(
                                selectors[i]
                            );


                        if (field) {

                            return field;

                        }

                    }


                    return null;

                }


                function saveCreateTermDraft() {

                    const form =
                        getCreateTermForm();


                    if (!form) {

                        return;

                    }


                    const schoolYearField =
                        getCreateTermField(
                            form,
                            [
                                '[name="school_year_id"]'
                            ]
                        );


                    const academicLevelField =
                        getCreateTermField(
                            form,
                            [
                                '[name="academic_level"]'
                            ]
                        );


                    const semesterField =
                        getCreateTermField(
                            form,
                            [
                                '[name="semester"]'
                            ]
                        );


                    const periodNameField =
                        getCreateTermField(
                            form,
                            [
                                '[name="period_name"]'
                            ]
                        );


                    const startDateField =
                        getCreateTermField(
                            form,
                            [
                                '[name="start_date"]'
                            ]
                        );


                    const endDateField =
                        getCreateTermField(
                            form,
                            [
                                '[name="end_date"]'
                            ]
                        );


                    const draft = {

                        school_year_id:
                            schoolYearField
                                ? schoolYearField.value
                                : '',

                        academic_level:
                            academicLevelField
                                ? academicLevelField.value
                                : '',

                        semester:
                            semesterField
                                ? semesterField.value
                                : '',

                        period_name:
                            periodNameField
                                ? periodNameField.value
                                : '',

                        start_date:
                            startDateField
                                ? startDateField.value
                                : '',

                        end_date:
                            endDateField
                                ? endDateField.value
                                : ''

                    };


                    sessionStorage.setItem(
                        createTermDraftKey,
                        JSON.stringify(draft)
                    );

                }


                function loadCreateTermDraft() {

                    const form =
                        getCreateTermForm();


                    if (!form) {

                        return false;

                    }


                    const storedDraft =
                        sessionStorage.getItem(
                            createTermDraftKey
                        );


                    if (!storedDraft) {

                        return false;

                    }


                    let draft = null;


                    try {

                        draft =
                            JSON.parse(
                                storedDraft
                            );

                    } catch (error) {

                        sessionStorage.removeItem(
                            createTermDraftKey
                        );

                        return false;

                    }


                    if (!draft) {

                        return false;

                    }


                    const schoolYearField =
                        getCreateTermField(
                            form,
                            [
                                '[name="school_year_id"]'
                            ]
                        );


                    const academicLevelField =
                        getCreateTermField(
                            form,
                            [
                                '[name="academic_level"]'
                            ]
                        );


                    const semesterField =
                        getCreateTermField(
                            form,
                            [
                                '[name="semester"]'
                            ]
                        );


                    const periodNameField =
                        getCreateTermField(
                            form,
                            [
                                '[name="period_name"]'
                            ]
                        );


                    const startDateField =
                        getCreateTermField(
                            form,
                            [
                                '[name="start_date"]'
                            ]
                        );


                    const endDateField =
                        getCreateTermField(
                            form,
                            [
                                '[name="end_date"]'
                            ]
                        );


                    if (
                        schoolYearField
                        &&
                        draft.school_year_id !== undefined
                    ) {

                        schoolYearField.value =
                            draft.school_year_id;

                    }


                    if (
                        academicLevelField
                        &&
                        draft.academic_level !== undefined
                    ) {

                        academicLevelField.value =
                            draft.academic_level;


                        academicLevelField.dispatchEvent(
                            new Event(
                                'change',
                                {
                                    bubbles: true
                                }
                            )
                        );

                    }


                    if (
                        semesterField
                        &&
                        draft.semester !== undefined
                    ) {

                        semesterField.value =
                            draft.semester;


                        semesterField.dispatchEvent(
                            new Event(
                                'change',
                                {
                                    bubbles: true
                                }
                            )
                        );

                    }


                    if (
                        periodNameField
                        &&
                        draft.period_name !== undefined
                    ) {

                        periodNameField.value =
                            draft.period_name;

                    }


                    if (
                        startDateField
                        &&
                        draft.start_date !== undefined
                    ) {

                        startDateField.value =
                            draft.start_date;

                    }


                    if (
                        endDateField
                        &&
                        draft.end_date !== undefined
                    ) {

                        endDateField.value =
                            draft.end_date;

                    }


                    return true;

                }


                function clearCreateTermDraft() {

                    sessionStorage.removeItem(
                        createTermDraftKey
                    );

                }


                /*
                 * ======================================================
                 * CREATE MODAL OPEN / RESET
                 * ======================================================
                 */

                if (createTermModal) {

                    createTermModal.addEventListener(
                        'show.bs.modal',
                        function () {

                            window.aprismCreateTermSaveSucceeded =
                                false;

                            /*
                             * When opening the Create modal normally,
                             * begin with a clean form.
                             *
                             * A failed AJAX request does not cause this
                             * event to fire again because the modal
                             * remains open.
                             */

                            if (
                                !createTermActionFailed
                            ) {

                                const form =
                                    getCreateTermForm();


                                if (form) {

                                    form.reset();

                                }

                            }

                        }
                    );


                    /*
                     * Save the draft before submission.
                     *
                     * This remains as a fallback if the browser or
                     * server encounters a non-AJAX failure.
                     */

                    createTermModal.addEventListener(
                        'submit',
                        function (event) {

                            const form =
                                event.target;


                            if (
                                form
                                &&
                                form.tagName === 'FORM'
                            ) {

                                saveCreateTermDraft();

                            }

                        }
                    );


                    /*
                     * Deliberate cancel:
                     * discard unfinished draft.
                     */

                    createTermModal.addEventListener(
                        'hide.bs.modal',
                        function (event) {

                            /*
                             * Never let the Create modal disappear while
                             * a save is still being processed unless the
                             * operation has succeeded.
                             */
                            const form =
                                getCreateTermForm();

                            if (
                                form
                                &&
                                form.dataset.submitting === 'true'
                                &&
                                !window.aprismCreateTermSaveSucceeded
                            ) {

                                event.preventDefault();

                            }

                        }
                    );


                    createTermModal.addEventListener(
                        'hidden.bs.modal',
                        function () {

                            if (
                                window.aprismCreateTermCancelled
                            ) {

                                clearCreateTermDraft();

                                window.aprismCreateTermCancelled =
                                    false;

                            }

                        }
                    );


                    createTermModal.addEventListener(
                        'click',
                        function (event) {

                            const button =
                                event.target.closest(
                                    '[data-bs-dismiss="modal"]'
                                );


                            if (!button) {

                                return;

                            }


                            window.aprismCreateTermCancelled =
                                true;

                        }
                    );

                }


                /*
                 * ======================================================
                 * TOAST CONTAINER
                 * ======================================================
                 */

                const toastContainer =
                    document.getElementById(
                        'toastContainer'
                    );


                const toastDeduplication =
                    new Map();


                if (!toastContainer) {

                    return;

                }


                function showToast(
                    title,
                    text,
                    type = 'success'
                ) {

                    /*
                     * Prevent the same error from stacking when the user
                     * clicks Save repeatedly or when multiple fallback
                     * paths report the same failure.
                     */
                    const toastKey =
                        type + '|' + title + '|' + text;

                    if (toastDeduplication.has(toastKey)) {

                        return;

                    }

                    toastDeduplication.set(
                        toastKey,
                        true
                    );

                    setTimeout(
                        function () {

                            toastDeduplication.delete(
                                toastKey
                            );

                        },
                        1500
                    );


                    const toast =
                        document.createElement(
                            'div'
                        );


                    toast.className =
                        'toast-custom';


                    let icon = 'check';


                    if (type === 'warning') {

                        icon =
                            'alert-circle';

                    }


                    if (type === 'info') {

                        icon =
                            'info';

                    }


                    toast.innerHTML = `
                        <div class="toast-icon ${type}">
                            <i data-lucide="${icon}"></i>
                        </div>

                        <div class="toast-content">
                            <h5 class="toast-title">
                                ${title}
                            </h5>

                            <p class="toast-text">
                                ${text}
                            </p>
                        </div>
                    `;


                    toastContainer.appendChild(
                        toast
                    );


                    if (
                        typeof lucide !== 'undefined'
                        &&
                        typeof lucide.createIcons === 'function'
                    ) {

                        lucide.createIcons();

                    }


                    setTimeout(
                        function () {

                            toast.classList.add(
                                'show'
                            );

                        },
                        10
                    );


                    setTimeout(
                        function () {

                            toast.classList.remove(
                                'show'
                            );


                            setTimeout(
                                function () {

                                    toast.remove();

                                },
                                300
                            );

                        },
                        4500
                    );

                }


                /*
                 * ======================================================
                 * CREATE ACADEMIC TERM - AJAX SUBMISSION
                 * ======================================================
                 *
                 * This intercepts ONLY the Create Academic Term form.
                 */

                if (createTermModal) {

                    const createTermForm =
                        getCreateTermForm();


                    if (createTermForm) {

                        createTermForm.addEventListener(
                            'submit',
                            async function (event) {

                                event.preventDefault();
                                event.stopImmediatePropagation();


                                saveCreateTermDraft();


                                if (
                                    createTermForm.dataset.submitting === 'true'
                                ) {

                                    return;

                                }


                                createTermForm.dataset.submitting =
                                    'true';


                                const submitButton =
                                    createTermForm.querySelector(
                                        'button[type="submit"]'
                                    );


                                const originalButtonText =
                                    submitButton
                                        ? submitButton.innerHTML
                                        : '';


                                if (submitButton) {

                                    submitButton.disabled =
                                        true;


                                    submitButton.innerHTML =
                                        'Saving...';

                                }


                                try {

                                    const formData =
                                        new FormData(
                                            createTermForm
                                        );


                                    const response =
                                        await fetch(
                                            '<?= htmlspecialchars(
                                                APP_URL,
                                                ENT_QUOTES
                                            ) ?>/actions/academic_head/create_academic_period.php',
                                            {
                                                method: 'POST',

                                                body: formData,

                                                headers: {
                                                    'X-Requested-With':
                                                        'XMLHttpRequest',

                                                    'Accept':
                                                        'application/json'
                                                },

                                                credentials:
                                                    'same-origin'
                                            }
                                        );


                                    const responseText =
                                        await response.text();


                                    let result = null;


                                    try {

                                        result =
                                            JSON.parse(
                                                responseText
                                            );

                                    } catch (jsonError) {

                                        console.error(
                                            'APRISM Create Academic Period returned a non-JSON response:',
                                            responseText
                                        );


                                        throw new Error(
                                            'The server returned an unexpected response.'
                                        );

                                    }


                                    if (
                                        !response.ok
                                        ||
                                        !result.success
                                    ) {

                                        showToast(
                                            'Action Failed',
                                            result.message
                                            ||
                                            'Unable to create the academic period.',
                                            'warning'
                                        );


                                        return;

                                    }


                                    clearCreateTermDraft();

                                    window.aprismCreateTermSaveSucceeded =
                                        true;


                                    showToast(
                                        'Success',
                                        result.message
                                        ||
                                        'Academic period created successfully.',
                                        'success'
                                    );


                                    if (
                                        typeof bootstrap !== 'undefined'
                                    ) {

                                        const modalInstance =
                                            bootstrap.Modal.getInstance(
                                                createTermModal
                                            );


                                        if (modalInstance) {

                                            modalInstance.hide();

                                        }

                                    }


                                    setTimeout(
                                        function () {

                                            window.location.reload();

                                        },
                                        350
                                    );


                                } catch (error) {

                                    console.error(
                                        'APRISM Create Academic Period:',
                                        error
                                    );


                                    showToast(
                                        'Action Failed',
                                        error.message
                                        ||
                                        'Unable to connect to the server. Please try again.',
                                        'warning'
                                    );

                                } finally {

                                    createTermForm.dataset.submitting =
                                        'false';


                                    if (submitButton) {

                                        submitButton.disabled =
                                            false;


                                        submitButton.innerHTML =
                                            originalButtonText;

                                    }

                                }

                            },
                            true
                        );

                    }

                }


                /*
                 * ======================================================
                 * EDIT ACADEMIC TERM DRAFT
                 * ======================================================
                 */

                const editTermDraftKey =
                    'aprism_edit_academic_term_draft';


                window.aprismEditTermSaveSucceeded =
                    false;

                window.aprismCreateTermSaveSucceeded =
                    false;


                function getEditTermField(
                    selectors
                ) {

                    if (!editTermModal) {

                        return null;

                    }


                    for (
                        let i = 0;
                        i < selectors.length;
                        i++
                    ) {

                        const field =
                            editTermModal.querySelector(
                                selectors[i]
                            );


                        if (field) {

                            return field;

                        }

                    }


                    return null;

                }


                function saveEditTermDraft() {

                    if (!editTermForm) {

                        return;

                    }


                    const draft = {

                        academic_period_id:
                            getEditTermField(
                                ['[name="academic_period_id"]']
                            )?.value || '',

                        school_year_id:
                            getEditTermField(
                                ['[name="school_year_id"]']
                            )?.value || '',

                        academic_level:
                            getEditTermField(
                                ['[name="academic_level"]']
                            )?.value || '',

                        semester:
                            getEditTermField(
                                ['[name="semester"]']
                            )?.value || '',

                        period_name:
                            getEditTermField(
                                ['[name="period_name"]']
                            )?.value || '',

                        start_date:
                            getEditTermField(
                                ['[name="start_date"]']
                            )?.value || '',

                        end_date:
                            getEditTermField(
                                ['[name="end_date"]']
                            )?.value || ''

                    };


                    sessionStorage.setItem(
                        editTermDraftKey,
                        JSON.stringify(draft)
                    );

                }


                function clearEditTermDraft() {

                    sessionStorage.removeItem(
                        editTermDraftKey
                    );

                }


                function populateEditPeriodOptions(
                    academicLevel,
                    selectedPeriod
                ) {

                    const periodField =
                        getEditTermField(
                            ['[name="period_name"]']
                        );


                    const semesterField =
                        getEditTermField(
                            ['[name="semester"]']
                        );


                    if (!periodField) {

                        return;

                    }


                    periodField.innerHTML = '';


                    const placeholder =
                        document.createElement(
                            'option'
                        );


                    placeholder.value =
                        '';


                    placeholder.textContent =
                        'Select Academic Period';


                    placeholder.disabled =
                        true;


                    periodField.appendChild(
                        placeholder
                    );


                    let periods = [];


                    if (
                        academicLevel === 'College'
                    ) {

                        periods = [
                            'Prelim',
                            'Midterm',
                            'Pre-Final',
                            'Final'
                        ];

                    } else if (
                        academicLevel === 'Senior High School'
                    ) {

                        periods = [
                            'Quarter 1',
                            'Quarter 2',
                            'Quarter 3',
                            'Quarter 4'
                        ];

                    }


                    periods.forEach(
                        function (period) {

                            const option =
                                document.createElement(
                                    'option'
                                );


                            option.value =
                                period;


                            option.textContent =
                                period;


                            if (
                                period === selectedPeriod
                            ) {

                                option.selected =
                                    true;

                            }


                            periodField.appendChild(
                                option
                            );

                        }
                    );


                    periodField.disabled =
                        periods.length === 0;


                    if (
                        semesterField
                    ) {

                        if (
                            academicLevel === 'College'
                        ) {

                            semesterField.disabled =
                                false;

                            semesterField.required =
                                true;

                        } else {

                            semesterField.value =
                                '';

                            semesterField.disabled =
                                true;

                            semesterField.required =
                                false;

                        }

                    }

                }


                function loadEditTermDraft() {

                    if (!editTermForm) {

                        return false;

                    }


                    const storedDraft =
                        sessionStorage.getItem(
                            editTermDraftKey
                        );


                    if (!storedDraft) {

                        return false;

                    }


                    let draft = null;


                    try {

                        draft =
                            JSON.parse(
                                storedDraft
                            );

                    } catch (error) {

                        clearEditTermDraft();

                        return false;

                    }


                    if (
                        !draft
                        ||
                        !draft.academic_period_id
                    ) {

                        clearEditTermDraft();

                        return false;

                    }


                    const schoolYearField =
                        getEditTermField(
                            ['[name="school_year_id"]']
                        );


                    const academicLevelField =
                        getEditTermField(
                            ['[name="academic_level"]']
                        );


                    const semesterField =
                        getEditTermField(
                            ['[name="semester"]']
                        );


                    const periodField =
                        getEditTermField(
                            ['[name="period_name"]']
                        );


                    const startDateField =
                        getEditTermField(
                            ['[name="start_date"]']
                        );


                    const endDateField =
                        getEditTermField(
                            ['[name="end_date"]']
                        );


                    const idField =
                        getEditTermField(
                            ['[name="academic_period_id"]']
                        );


                    if (idField) {

                        idField.value =
                            draft.academic_period_id;

                    }


                    if (schoolYearField) {

                        schoolYearField.value =
                            draft.school_year_id;

                    }


                    if (academicLevelField) {

                        academicLevelField.value =
                            draft.academic_level;

                    }


                    if (semesterField) {

                        semesterField.value =
                            draft.semester || '';


                        if (
                            draft.academic_level === 'College'
                        ) {

                            semesterField.disabled =
                                false;

                            semesterField.required =
                                true;

                        } else {

                            semesterField.value =
                                '';

                            semesterField.disabled =
                                true;

                            semesterField.required =
                                false;

                        }

                    }


                    populateEditPeriodOptions(
                        draft.academic_level,
                        draft.period_name
                    );


                    if (periodField) {

                        periodField.value =
                            draft.period_name;

                    }


                    if (startDateField) {

                        startDateField.value =
                            draft.start_date;

                    }


                    if (endDateField) {

                        endDateField.value =
                            draft.end_date;

                    }


                    return true;

                }


                /*
                 * ======================================================
                 * EDIT MODAL OPEN / CANCEL STATE
                 * ======================================================
                 */

                if (editTermModal) {

                    document.addEventListener(
                        'click',
                        function (event) {

                            const editButton =
                                event.target.closest(
                                    '[data-bs-target="#editTermModal"]'
                                );


                            if (
                                !editButton
                            ) {

                                return;

                            }


                            clearEditTermDraft();

                            window.aprismEditTermSaveSucceeded =
                                false;

                        }
                    );


                    editTermModal.addEventListener(
                        'click',
                        function (event) {

                            const button =
                                event.target.closest(
                                    '[data-bs-dismiss="modal"]'
                                );


                            if (!button) {

                                return;

                            }


                            window.aprismEditTermCancelled =
                                true;

                        }
                    );


                    editTermModal.addEventListener(
                        'hide.bs.modal',
                        function (event) {

                            if (
                                editTermForm
                                &&
                                editTermForm.dataset.submitting === 'true'
                                &&
                                !window.aprismEditTermSaveSucceeded
                            ) {

                                event.preventDefault();

                            }

                        }
                    );


                    editTermModal.addEventListener(
                        'hidden.bs.modal',
                        function () {

                            if (
                                window.aprismEditTermCancelled
                            ) {

                                clearEditTermDraft();

                                window.aprismEditTermCancelled =
                                    false;

                            }

                        }
                    );

                }


                /*
                 * ======================================================
                 * EDIT ACADEMIC TERM - AJAX SUBMISSION
                 * ======================================================
                 */

                if (editTermForm) {

                    editTermForm.addEventListener(
                        'submit',
                        async function (event) {

                            event.preventDefault();
                            event.stopImmediatePropagation();


                            saveEditTermDraft();


                            if (
                                editTermForm.dataset.submitting === 'true'
                            ) {

                                return;

                            }


                            editTermForm.dataset.submitting =
                                'true';


                            const submitButton =
                                editTermForm.querySelector(
                                    'button[type="submit"]'
                                );


                            const originalButtonText =
                                submitButton
                                    ? submitButton.innerHTML
                                    : '';


                            if (submitButton) {

                                submitButton.disabled =
                                    true;


                                submitButton.innerHTML =
                                    'Saving...';

                            }


                            try {

                                const formData =
                                    new FormData(
                                        editTermForm
                                    );


                                const response =
                                    await fetch(
                                        editTermForm.action,
                                        {
                                            method: 'POST',

                                            body: formData,

                                            headers: {
                                                'X-Requested-With':
                                                    'XMLHttpRequest',

                                                'Accept':
                                                    'application/json'
                                            },

                                            credentials:
                                                'same-origin'
                                        }
                                    );


                                const responseText =
                                    await response.text();


                                let result = null;


                                try {

                                    result =
                                        JSON.parse(
                                            responseText
                                        );

                                } catch (jsonError) {

                                    console.error(
                                        'APRISM Update Academic Period returned a non-JSON response:',
                                        responseText
                                    );


                                    throw new Error(
                                        'The server returned an unexpected response.'
                                    );

                                }


                                if (
                                    !response.ok
                                    ||
                                    !result.success
                                ) {

                                    showToast(
                                        'Action Failed',
                                        result.message
                                        ||
                                        'Unable to update the academic period.',
                                        'warning'
                                    );


                                    return;

                                }


                                clearEditTermDraft();

                                window.aprismEditTermSaveSucceeded =
                                    true;


                                showToast(
                                    'Success',
                                    result.message
                                    ||
                                    'Academic period updated successfully.',
                                    'success'
                                );


                                if (
                                    typeof bootstrap !== 'undefined'
                                ) {

                                    const modalInstance =
                                        bootstrap.Modal.getInstance(
                                            editTermModal
                                        );


                                    if (modalInstance) {

                                        modalInstance.hide();

                                    }

                                }


                                setTimeout(
                                    function () {

                                        window.location.reload();

                                    },
                                    350
                                );


                            } catch (error) {

                                console.error(
                                    'APRISM Update Academic Period:',
                                    error
                                );


                                showToast(
                                    'Action Failed',
                                    error.message
                                    ||
                                    'Unable to connect to the server. Please try again.',
                                    'warning'
                                );


                            } finally {

                                editTermForm.dataset.submitting =
                                    'false';


                                if (submitButton) {

                                    submitButton.disabled =
                                        false;


                                    submitButton.innerHTML =
                                        originalButtonText;

                                }

                            }

                        },
                        true
                    );

                }


                /*
                 * ======================================================
                 * SUCCESSFUL NORMAL POST FALLBACK
                 * ======================================================
                 */

                <?php if ($successMessage): ?>

                    clearCreateTermDraft();
                    clearEditTermDraft();

                <?php endif; ?>


                /*
                 * ======================================================
                 * FAILED NORMAL POST FALLBACK
                 * ======================================================
                 */

                const hasCreateTermDraft =
                    !!sessionStorage.getItem(
                        createTermDraftKey
                    );


                const hasEditTermDraft =
                    !!sessionStorage.getItem(
                        editTermDraftKey
                    );


                if (
                    createTermActionFailed
                    &&
                    typeof bootstrap !== 'undefined'
                    &&
                    (
                        hasCreateTermDraft
                        ||
                        hasEditTermDraft
                    )
                ) {

                    const termsTabButton =
                        document.querySelector(
                            '.academic-setup-tabs [data-bs-target="#terms-tab"]'
                        );


                    if (termsTabButton) {

                        const termsTab =
                            new bootstrap.Tab(
                                termsTabButton
                            );


                        termsTab.show();


                        sessionStorage.setItem(
                            academicSetupTabKey,
                            'terms'
                        );

                    }


                    setTimeout(
                        function () {

                            if (
                                hasEditTermDraft
                                &&
                                editTermModal
                            ) {

                                const modal =
                                    bootstrap.Modal.getOrCreateInstance(
                                        editTermModal
                                    );


                                modal.show();


                                setTimeout(
                                    function () {

                                        loadEditTermDraft();

                                    },
                                    100
                                );


                                return;

                            }


                            if (
                                hasCreateTermDraft
                                &&
                                createTermModal
                            ) {

                                const modal =
                                    bootstrap.Modal.getOrCreateInstance(
                                        createTermModal
                                    );


                                modal.show();


                                setTimeout(
                                    function () {

                                        loadCreateTermDraft();

                                    },
                                    100
                                );

                            }

                        },
                        150
                    );

                }


                /*
                 * ======================================================
                 * PHP FLASH TOASTS
                 * ======================================================
                 */

                <?php if ($successMessage): ?>

                    showToast(
                        'Success',
                        <?= json_encode($successMessage) ?>,
                        'success'
                    );

                <?php endif; ?>


                <?php if ($errorMessage): ?>

                    showToast(
                        'Action Failed',
                        <?= json_encode($errorMessage) ?>,
                        'warning'
                    );

                <?php endif; ?>


                <?php if ($warningMessage): ?>

                    showToast(
                        'Warning',
                        <?= json_encode($warningMessage) ?>,
                        'warning'
                    );

                <?php endif; ?>

            }
        );

    </script>


    <?php
    require __DIR__ .
        '/../includes/components/footer.php';
    ?>


</body>

</html>