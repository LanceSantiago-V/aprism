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
| Temporary Data
|--------------------------------------------------------------------------
| Backend integration will replace this later.
*/

$schoolYears = [];

?>

<!DOCTYPE html>
<html lang="en">

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <?php if (!empty($flash['success'])): ?>

            <div class="alert alert-success alert-dismissible fade show mx-4 mt-4">

                <?= htmlspecialchars($flash['success']) ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>

        <?php if (!empty($flash['error'])): ?>

            <div class="alert alert-danger alert-dismissible fade show mx-4 mt-4">

                <?= htmlspecialchars($flash['error']) ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>



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

            <li class="nav-item">

                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#school-year-tab" type="button">

                    <i data-lucide="calendar"></i>

                    School Year

                </button>

            </li>

            <li class="nav-item">

                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#terms-tab" type="button">

                    <i data-lucide="book-open"></i>

                    Terms

                </button>

            </li>

            <li class="nav-item">

                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#monitoring-tab" type="button">

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

            <div class="tab-pane fade show active" id="school-year-tab">

                <section class="module-card">

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

                        <button class="page-action-btn" type="button" data-bs-toggle="modal"
                            data-bs-target="#createSchoolYearModal" title="Create School Year">

                            <i data-lucide="plus"></i>

                        </button>

                    </div>

                    <div class="school-year-list">

                        <?php if (empty($schoolYears)): ?>

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

                            <!-- Backend school year cards -->

                        <?php endif; ?>

                    </div>

                </section>

            </div>
            <!-- ======================================================
             TERMS
        ======================================================= -->

            <div class="tab-pane fade" id="terms-tab">

                <section class="module-card">

                    <div class="module-header">

                        <div>

                            <h2 class="module-title">

                                Academic Terms

                            </h2>

                            <p class="module-description">

                                Configure academic grading periods for each
                                school year used throughout APRISM.

                            </p>

                        </div>

                        <button class="page-action-btn" type="button" data-bs-toggle="modal"
                            data-bs-target="#createTermModal" title="Create Academic Term">

                            <i data-lucide="plus"></i>

                        </button>

                    </div>

                    <div class="term-list">

                        <div class="coming-soon-placeholder">

                            <i data-lucide="book-open"></i>

                            <h3 class="empty-state-title">

                                No Academic Terms Found

                            </h3>

                            <p class="empty-state-text">

                                Create academic terms that define grading
                                periods for each school year.

                            </p>

                        </div>

                    </div>

                </section>

            </div>



            <!-- ======================================================
             MONITORING THRESHOLDS
        ======================================================= -->

            <div class="tab-pane fade" id="monitoring-tab">

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


    <?php require __DIR__ . '/../includes/modals/academic-setup/school_year_modals.php'; ?>

    <?php require __DIR__ . '/../includes/modals/academic-setup/term_modals.php'; ?>

    <?php require __DIR__ . '/../includes/modals/academic-setup/monitoring_threshold_modals.php'; ?>

    <!-- ==========================================================
     SHARED COMPONENTS
========================================================== -->

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>