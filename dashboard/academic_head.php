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

$pageTitle = 'Dashboard';

$activePage = 'dashboard';

$roleStylesheet = 'assets/css/academic-head.css';

$pageStylesheet = 'assets/css/pages/academic-head-dashboard.css';


$fullName =
    ($_SESSION['first_name'] ?? 'Prof. Alejandro') . ' ' .
    ($_SESSION['last_name'] ?? 'Diaz');

$initials =
    strtoupper(substr($_SESSION['first_name'] ?? 'P', 0, 1)) .
    strtoupper(substr($_SESSION['last_name'] ?? 'D', 0, 1));

// Sample data fallbacks for wireframe rendering if backend variables aren't initialized yet
$stats = [
    'programs' => $totalPrograms ?? null,
    'sections' => $totalSections ?? null,
    'subjects' => $totalSubjects ?? null,
    'teachers' => $totalTeachers ?? null,
    'attendance' => $overallAttendance ?? null,
];

$sectionAnalytics = $sectionAnalytics ?? [];


?>

<!DOCTYPE html>
<html lang="en">

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <!-- ==========================================================
             Page Header & Academic Period Toolbar
        ========================================================== -->
        <section class="dashboard-header">
            <div class="dashboard-header-top">
                <div class="dashboard-header-title">
                    <h1 class="page-title">Academic Head Dashboard</h1>
                    <div class="dashboard-period">
                        <span class="dashboard-period-item">
                            <i data-lucide="calendar"></i>
                            <strong>2025–2026</strong>
                        </span>
                        <span class="dashboard-period-divider">•</span>
                        <span class="dashboard-period-item">
                            <i data-lucide="clock-3"></i>
                            <strong>2ND SEMESTER</strong>
                        </span>
                    </div>
                </div>

                <div class="dashboard-header-filters">
                    <select class="dashboard-filter" disabled>
                        <option>2025–2026</option>
                    </select>
                    <select class="dashboard-filter" disabled>
                        <option>2ND SEMESTER</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- ==========================================================
             Dashboard Statistics (5 Cards)
        ========================================================== -->
        <section class="dashboard-stats">

            <!-- Card 1: Programs -->
            <div class="dashboard-stat-card stat-programs">
                <div class="dashboard-stat-top">
                    <div class="dashboard-stat-icon">
                        <i data-lucide="graduation-cap"></i>
                    </div>
                    <i data-lucide="chevron-right" class="dashboard-stat-arrow"></i>
                </div>
                <span class="dashboard-stat-label">Programs</span>
                <h2 class="dashboard-stat-value"><?= $stats['programs'] !== null
                    ? htmlspecialchars($stats['programs'])
                    : '—'; ?></h2>
            </div>

            <!-- Card 2: Sections -->
            <div class="dashboard-stat-card stat-sections">
                <div class="dashboard-stat-top">
                    <div class="dashboard-stat-icon">
                        <i data-lucide="layers-3"></i>
                    </div>
                    <i data-lucide="chevron-right" class="dashboard-stat-arrow"></i>
                </div>
                <span class="dashboard-stat-label">Sections</span>
                <h2 class="dashboard-stat-value"><?= $stats['sections'] !== null
                    ? htmlspecialchars($stats['sections'])
                    : '—'; ?></h2>
            </div>

            <!-- Card 3: Subjects -->
            <div class="dashboard-stat-card stat-subjects">
                <div class="dashboard-stat-top">
                    <div class="dashboard-stat-icon">
                        <i data-lucide="book-open"></i>
                    </div>
                    <i data-lucide="chevron-right" class="dashboard-stat-arrow"></i>
                </div>
                <span class="dashboard-stat-label">Subjects</span>
                <h2 class="dashboard-stat-value"><?= $stats['subjects'] !== null
                    ? htmlspecialchars($stats['subjects'])
                    : '—'; ?></h2>
            </div>

            <!-- Card 4: Teachers Assigned -->
            <div class="dashboard-stat-card stat-teachers">
                <div class="dashboard-stat-top">
                    <div class="dashboard-stat-icon">
                        <i data-lucide="users"></i>
                    </div>
                    <i data-lucide="chevron-right" class="dashboard-stat-arrow"></i>
                </div>
                <span class="dashboard-stat-label">Teachers Assigned</span>
                <h2 class="dashboard-stat-value"><?= $stats['teachers'] !== null
                    ? htmlspecialchars($stats['teachers'])
                    : '—'; ?></h2>
            </div>

            <!-- Card 5: Overall Attendance -->
            <div class="dashboard-stat-card stat-attendance">
                <div class="dashboard-stat-top">
                    <div class="dashboard-stat-icon">
                        <i data-lucide="clipboard-check"></i>
                    </div>
                    <i data-lucide="chevron-right" class="dashboard-stat-arrow"></i>
                </div>
                <span class="dashboard-stat-label">Overall Attendance</span>
                <h2 class="dashboard-stat-value"><?= $stats['attendance'] !== null
                    ? htmlspecialchars($stats['attendance'])
                    : '—'; ?></h2>
            </div>

        </section>

        <!-- ==========================================================
             Dashboard Analytics (3 Widgets)
        ========================================================== -->
        <section class="dashboard-analytics">

            <!-- Widget 1: Attendance Trend -->
            <article class="dashboard-widget">
                <header class="dashboard-widget-header">
                    <div class="widget-title-group">
                        <i data-lucide="trending-up" class="widget-header-icon"></i>
                        <h3>ATTENDANCE TREND</h3>
                    </div>
                </header>
                <div id="attendanceTrendChart" class="dashboard-chart">

                    <div class="dashboard-chart-placeholder">

                        <i data-lucide="line-chart"></i>

                        <span>
                            Attendance trend will appear here.
                        </span>

                    </div>

                </div>
            </article>

            <!-- Widget 2: Risk Tier Distribution -->
            <article class="dashboard-widget">
                <header class="dashboard-widget-header">
                    <div class="widget-title-group">
                        <i data-lucide="alert-triangle" class="widget-header-icon text-warning"></i>
                        <h3>RISK TIER DISTRIBUTION</h3>
                    </div>
                </header>
                <div id="riskTierChart" class="dashboard-chart">

                    <div class="dashboard-chart-placeholder">

                        <i data-lucide="line-chart"></i>

                        <span>
                            Attendance trend will appear here.
                        </span>

                    </div>

                </div>

                </div>
            </article>

            <!-- Widget 3: Referral Status Summary -->
            <article class="dashboard-widget">
                <header class="dashboard-widget-header">
                    <div class="widget-title-group">
                        <i data-lucide="pie-chart" class="widget-header-icon text-info"></i>
                        <h3>REFERRAL STATUS SUMMARY</h3>
                    </div>
                </header>
                <div id="referralSummaryChart" class="dashboard-chart">

                    <div class="dashboard-chart-placeholder">

                        <i data-lucide="chart-column"></i>

                        <span>
                            Referral summary will appear here.
                        </span>

                    </div>

                </div>
                </div>
            </article>

        </section>

        <!-- ==========================================================
             Academic Section Analytics Table
        ========================================================== -->
        <section class="dashboard-table-panel">

            <div class="dashboard-table-header">
                <div>
                    <h2>Academic Section Analytics</h2>
                </div>
            </div>

            <div class="dashboard-table-wrapper">

                <table class="dashboard-table">

                    <thead>
                        <tr>
                            <th class="text-center">SECTION</th>
                            <th class="text-center">AVERAGE ATTENDANCE</th>
                            <th class="text-center">ACADEMIC GPA / AVERAGE</th>
                            <th class="text-end">ACTIONS</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($sectionAnalytics)): ?>

                            <?php foreach ($sectionAnalytics as $section): ?>
                                <tr>
                                    <td class="fw-bold section-title-cell">
                                        <?= htmlspecialchars($section['name'] ?? $section['section_name']) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $attendance = $section['attendance'] ?? '';
                                        $attNum = (float) preg_replace('/[^0-9.]/', '', $attendance);
                                        $badgeClass = ($attNum >= 93) ? 'badge-green' : 'badge-orange';
                                        ?>
                                        <span class="attendance-badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($attendance) ?>
                                        </span>
                                    </td>
                                    <td class="gpa-cell">
                                        <?= htmlspecialchars($section['gpa'] ?? $section['academic_average'] ?? '1.0') ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="action-btn-circle" title="View Section Details">
                                            <i data-lucide="chevron-right"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="dashboard-table-empty-row">
                                <td colspan="4" class="dashboard-table-empty">
                                    <div class="dashboard-table-empty-content">
                                        <i data-lucide="database"></i>
                                        <h3>No Academic Analytics Available</h3>
                                        <p>
                                            Academic analytics will automatically appear
                                            once attendance, grading, and monitoring
                                            modules begin collecting data.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>