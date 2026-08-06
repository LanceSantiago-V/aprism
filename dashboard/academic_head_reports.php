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

$pageTitle = 'Reports';
$activePage = 'reports';

$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-reports.css';

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

                        Reports

                    </h1>

                </div>

            </div>

            <!-- ==========================================================
        REPORTS LAYOUT
    =========================================================== -->

            <div class="reports-layout">

                <!-- ======================================================
            LEFT SIDEBAR
        ======================================================= -->

                <aside class="reports-sidebar">

                    <!-- ==============================================
                SIDEBAR HEADER
            =============================================== -->

                    <div class="reports-sidebar-header">

                        <h2>

                            Report Configuration

                        </h2>

                        <p>

                            Configure the report type and filters before generating a preview.

                        </p>

                    </div>

                    <!-- ==============================================
                REPORT TYPE
            =============================================== -->

                    <div class="reports-config-top">

                        <div class="form-group">

                            <label>

                                Report Type

                            </label>

                            <select class="form-select">

                                <option>

                                    Attendance Summary

                                </option>

                                <option>

                                    Student Risk Summary

                                </option>

                                <option>

                                    Program Performance Summary

                                </option>

                                <option>

                                    Section Performance Summary

                                </option>

                                <option>

                                    Guidance Referral Summary

                                </option>

                            </select>

                        </div>

                    </div>

                    <!-- ==============================================
     FILTER AREA
=============================================== -->

                    <div class="reports-filter-area">

                        <div class="reports-filter-title">

                            Filters

                        </div>

                        <div class="reports-filter-list">

                            <div class="form-group">

                                <label>School Year</label>

                                <select class="form-select">

                                    <option>All School Years</option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>Academic Term</label>

                                <select class="form-select">

                                    <option>All Academic Terms</option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>Program</label>

                                <select class="form-select">

                                    <option>All Programs</option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>Section</label>

                                <select class="form-select">

                                    <option>All Sections</option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>Risk Level</label>

                                <select class="form-select">

                                    <option>All Risk Levels</option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>Status</label>

                                <select class="form-select">

                                    <option>All Status</option>

                                </select>

                            </div>

                        </div>

                    </div>

                    <!-- ==============================================
                FOOTER
            =============================================== -->

                    <div class="reports-sidebar-footer">

                        <button type="button" class="btn btn-primary report-generate-btn">

                            <i data-lucide="file-text"></i>

                            Generate Report

                        </button>

                    </div>

                </aside>

                <!-- ======================================================
            REPORT PREVIEW
        ======================================================= -->

                <section class="reports-preview">

                    <!-- ==========================================
                HEADER
            =========================================== -->

                    <div class="reports-preview-header">

                        <div class="preview-header-content">

                            <h2>Report Preview</h2>

                            <p>Generated reports appear here before exporting.</p>

                        </div>

                        <div class="preview-actions">

                            <button type="button" class="preview-export-btn">

                                <i data-lucide="file-text"></i>

                                PDF

                            </button>

                            <button type="button" class="preview-export-btn">

                                <i data-lucide="sheet"></i>

                                Excel

                            </button>

                        </div>

                    </div>

                    <!-- ==========================================
     BODY
========================================== -->

                    <div class="reports-preview-body">

                        <div class="preview-empty-state">

                            <div class="preview-empty-icon">

                                <i data-lucide="file-text"></i>

                            </div>

                            <h3>

                                No Report Generated

                            </h3>

                            <p>

                                Select a report type, configure the filters,
                                then click <strong>Generate Report</strong>
                                to preview the report.

                            </p>

                        </div>

                    </div>

                </section>

            </div>

        </div>

    </main>

    <?php require __DIR__ . '/../includes/modals/report/report_modals.php'; ?>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>