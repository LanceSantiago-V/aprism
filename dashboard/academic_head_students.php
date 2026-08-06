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

$pageTitle = 'Students';
$activePage = 'students';

$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-students.css';

/*
|--------------------------------------------------------------------------
| Temporary Data
|--------------------------------------------------------------------------
| Backend integration will replace this later.
*/

$students = [];

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

            <!-- ==========================================================
             PAGE HEADER
        =========================================================== -->

            <div class="page-header">

                <div class="page-header-left">

                    <h1 class="page-title">

                        Students

                    </h1>

                </div>

                <div class="page-header-right">

                    <button type="button" class="page-action-btn" title="Export Student Records">

                        <i data-lucide="download"></i>

                    </button>

                </div>

            </div>

            <!-- ==========================================================
             CONTENT CARD
        =========================================================== -->

            <div class="module-card">

                <!-- ======================================================
                 TOOLBAR
            ======================================================= -->

                <div class="students-toolbar">

                    <!-- Search -->

                    <div class="toolbar-search">

                        <i class="toolbar-search-icon" data-lucide="search"></i>

                        <input type="text" class="toolbar-search-input"
                            placeholder="Search by student number or name...">

                    </div>

                    <!-- School Year -->

                    <select class="toolbar-select">

                        <option>

                            All School Years

                        </option>

                    </select>

                    <!-- Academic Term -->

                    <select class="toolbar-select">

                        <option>

                            All Academic Terms

                        </option>

                    </select>

                    <!-- Program -->

                    <select class="toolbar-select">

                        <option>

                            All Programs

                        </option>

                    </select>

                    <!-- Risk -->

                    <select class="toolbar-select">

                        <option>

                            All Risk Levels

                        </option>

                    </select>

                    <!-- Status -->

                    <select class="toolbar-select">

                        <option>

                            All Status

                        </option>

                    </select>

                </div>

                <!-- ======================================================
                 TABLE
            ======================================================= -->

                <div class="table-wrapper">

                    <table class="module-table">

                        <thead>

                            <tr>

                                <th class="text-center col-student-number">

                                    STUDENT NO.

                                </th>

                                <th class="text-center col-student-name">

                                    STUDENT NAME

                                </th>

                                <th class="text-center col-program">

                                    PROGRAM

                                </th>

                                <th class="text-center col-year">

                                    YEAR LEVEL

                                </th>

                                <th class="text-center col-section">

                                    SECTION

                                </th>

                                <th class="text-center col-risk">

                                    RISK LEVEL

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

                            <?php if (empty($students)): ?>

                                <tr>

                                    <td colspan="8">

                                        <div class="students-table-empty">

                                            <div class="empty-state">

                                                <i data-lucide="graduation-cap"></i>

                                                <h3 class="empty-state-title">

                                                    No Student Records

                                                </h3>

                                                <p class="empty-state-text">

                                                    Student records from the institution
                                                    will appear here for academic
                                                    monitoring and intervention support.

                                                </p>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <!-- Backend student rows will be rendered here -->

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

    <!-- ==========================================================
     STUDENT MODALS
========================================================== -->

    <?php require __DIR__ . '/../includes/modals/students/student_modals.php'; ?>

    <!-- ==========================================================
     SHARED COMPONENTS
========================================================== -->

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>