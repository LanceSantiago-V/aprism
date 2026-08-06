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

$pageTitle = 'Schedules';
$activePage = 'schedules';

$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-schedules.css';

/*
|--------------------------------------------------------------------------
| Temporary Data
|--------------------------------------------------------------------------
| Backend integration will replace this later.
*/

$schedules = [];

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php require __DIR__ . '/../includes/components/head.php'; ?>

</head>

<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <div class="content-wrapper">

            <!-- =======================================================
                 PAGE HEADER
            ======================================================== -->

            <div class="page-header">

                <div>

                    <h1 class="page-title">
                        Schedules
                    </h1>

                </div>

                <div class="page-header-right">

                    <button type="button" class="page-action-btn" title="Export Schedule Records">

                        <i data-lucide="download"></i>

                    </button>

                    <button class="page-action-btn" type="button" data-bs-toggle="modal"
                        data-bs-target="#recordScheduleModal" title="Record Schedule">

                        <i data-lucide="plus"></i>

                    </button>

                </div>

            </div>

            <!-- =======================================================
                 CONTENT CARD
            ======================================================== -->

            <div class="module-card">

                <!-- ===================================================
                     TOOLBAR
                ==================================================== -->

                <div class="schedule-toolbar">

                    <!-- Search -->

                    <div class="toolbar-search">

                        <i class="toolbar-search-icon" data-lucide="search"></i>

                        <input class="toolbar-search-input" type="text"
                            placeholder="Search by subject, section, room...">
                    </div>

                    <!-- Section -->

                    <select class="toolbar-select">

                        <option>
                            All Sections
                        </option>

                    </select>

                    <!-- Teacher -->

                    <select class="toolbar-select">

                        <option>
                            All Teachers
                        </option>


                    </select>

                    <!-- School Year -->

                    <select class="toolbar-select">

                        <option>
                            All School Years
                        </option>
                    </select>

                    <!-- Term -->

                    <select class="toolbar-select">

                        <option>
                            All Terms
                        </option>
                    </select>

                    <!-- Status -->

                    <select class="toolbar-select">

                        <option>
                            All Statuses
                        </option>
                    </select>

                </div>

                <!-- ===================================================
                     TABLE
                ==================================================== -->

                <div class="table-wrapper">

                    <table class="able module-table align-middle">

                        <thead>

                            <tr>

                                <th class="text-center col-subject">
                                    SUBJECT
                                </th>

                                <th class="text-center col-section">
                                    SECTION
                                </th>

                                <th class="text-center col-teacher">
                                    TEACHER
                                </th>

                                <th class="text-center col-day">
                                    DAY
                                </th>

                                <th class="text-center col-time">
                                    START
                                </th>

                                <th class="text-center col-time">
                                    END
                                </th>

                                <th class="text-center col-room">
                                    ROOM
                                </th>

                                <th class="text-center col-school-year">
                                    SCHOOL YEAR
                                </th>

                                <th class="text-center col-term">
                                    TERM
                                </th>

                                <th class="text-center col-status">
                                    STATUS
                                </th>

                                <th class="text-center col-actions">
                                    ACTIONS
                                </th>

                            </tr>

                        </thead>
                        <tbody>

                            <?php if (empty($schedules)): ?>

                                <tr>

                                    <td colspan="11">

                                        <div class="schedule-table-empty">

                                            <div class="empty-state">

                                                <i data-lucide="calendar-days"></i>

                                                <h3 class="empty-state-title">

                                                    No Schedule Records

                                                </h3>

                                                <p class="empty-state-text">

                                                    Record your first academic schedule to support attendance,
                                                    gradebooks, student monitoring, guidance referrals,
                                                    and institutional reporting.

                                                </p>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <!-- Backend schedule rows will be rendered here -->

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

    <!-- ==========================================================
             SCHEDULE MODALS
        =========================================================== -->

    <?php require __DIR__ . '/../includes/modals/schedules/record_schedule_modal.php'; ?>

    <?php require __DIR__ . '/../includes/modals/schedules/view_schedule_modal.php'; ?>

    <?php require __DIR__ . '/../includes/modals/schedules/edit_schedule_modal.php'; ?>

    <?php require __DIR__ . '/../includes/modals/schedules/archive_schedule_modal.php'; ?>

    <!-- ==========================================================
             SHARED COMPONENTS
        =========================================================== -->

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>