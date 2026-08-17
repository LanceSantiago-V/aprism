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

$pageTitle = 'Dashboard';

$activePage = 'dashboard';

$roleStylesheet = 'assets/css/teacher.css';

$pageStylesheet = 'assets/css/pages/teacher-dashboard.css';


/*
|--------------------------------------------------------------------------
| Temporary Frontend Data
|--------------------------------------------------------------------------
| Backend implementation will populate these values later.
*/

$teacherStats = [
    'todayClasses' => null,
    'pendingGradeImports' => null,
    'flaggedStudents' => null,
    'attendanceToday' => null,
];

$todayClasses = [];


/*
|--------------------------------------------------------------------------
| Dashboard Lists
|--------------------------------------------------------------------------
*/

$recentAlerts = [];


/*
|--------------------------------------------------------------------------
| Active School Year
|--------------------------------------------------------------------------
*/

$currentSchoolYear = null;

try {

    $stmt = $pdo->query("
        SELECT school_year
        FROM school_years
        WHERE status = 'Active'
        LIMIT 1
    ");

    $currentSchoolYear = $stmt->fetchColumn() ?: null;

} catch (PDOException $e) {

    error_log(
        '[APRISM Active School Year] ' .
        $e->getMessage()
    );

}

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

                        Dashboard

                    </h1>

                </div>

            </div>


            <!-- ==========================================================
            DASHBOARD STATISTICS
            =========================================================== -->

            <section class="teacher-dashboard-stats">

                <article class="teacher-stat-card">

                    <div class="teacher-stat-icon teacher-stat-icon-blue">

                        <i data-lucide="calendar-days"></i>

                    </div>

                    <span class="teacher-stat-label">

                        Today's Classes

                    </span>

                    <h2 class="teacher-stat-value">

                        <?= $teacherStats['todayClasses'] ?? '—'; ?>

                    </h2>

                </article>


                <article class="teacher-stat-card">

                    <div class="teacher-stat-icon teacher-stat-icon-green">

                        <i data-lucide="file-up"></i>

                    </div>

                    <span class="teacher-stat-label">

                        Pending Grade Imports

                    </span>

                    <h2 class="teacher-stat-value">

                        <?= $teacherStats['pendingGradeImports'] ?? '—'; ?>

                    </h2>

                </article>


                <article class="teacher-stat-card">

                    <div class="teacher-stat-icon teacher-stat-icon-orange">

                        <i data-lucide="triangle-alert"></i>

                    </div>

                    <span class="teacher-stat-label">

                        Flagged Students

                    </span>

                    <h2 class="teacher-stat-value">

                        <?= $teacherStats['flaggedStudents'] ?? '—'; ?>

                    </h2>

                </article>


                <article class="teacher-stat-card">

                    <div class="teacher-stat-icon teacher-stat-icon-purple">

                        <i data-lucide="clipboard-check"></i>

                    </div>

                    <span class="teacher-stat-label">

                        Attendance Today

                    </span>

                    <h2 class="teacher-stat-value">

                        <?= $teacherStats['attendanceToday'] ?? '—'; ?>

                    </h2>

                </article>

            </section>


            <!-- ==========================================================
            DASHBOARD CONTENT
            =========================================================== -->

            <div class="teacher-dashboard-grid">

                <!-- ======================================================
                TODAY'S CLASSES
                ======================================================= -->

                <section class="teacher-panel">

                    <div class="teacher-panel-header">

                        <h2 class="teacher-panel-title">

                            Today's Classes

                        </h2>

                    </div>

                    <table class="teacher-table">

                        <thead>

                            <tr>

                                <th class="text-center">Subject</th>

                                <th class="text-center">Section</th>

                                <th class="text-center">Time</th>

                                <th class="text-center">Room</th>

                                <th class="text-center">Status</th>

                                <th class="text-center">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (empty($todayClasses)): ?>

                                <tr>

                                    <td colspan="6">

                                        <div class="teacher-empty-state">

                                            <i data-lucide="calendar-days"></i>

                                            <h3 class="teacher-empty-state-title">

                                                No Classes Today

                                            </h3>

                                            <p class="teacher-empty-state-text">

                                                Your assigned classes for today will appear here once schedules have been
                                                imported.

                                            </p>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </section>


                <!-- ======================================================
                RECENT ALERTS
                ======================================================= -->

                <aside class="teacher-panel">

                    <div class="teacher-panel-header">

                        <h2 class="teacher-panel-title">

                            Recent Alerts

                        </h2>

                    </div>

                    <div class="teacher-empty-state">

                        <i data-lucide="bell"></i>

                        <h3 class="teacher-empty-state-title">

                            No Alerts

                        </h3>

                        <p class="teacher-empty-state-text">

                            Attendance reminders, pending grade imports, and student alerts will appear here.

                        </p>

                    </div>

                </aside>

            </div>

        </div>

    </main>


    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>