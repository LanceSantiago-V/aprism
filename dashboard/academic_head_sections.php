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

$pageTitle = 'Sections';

$activePage = 'sections';

$roleStylesheet = 'assets/css/academic-head.css';

$pageStylesheet = 'assets/css/pages/academic-head-sections.css';

/* ==========================================================
   Frontend Placeholder
   Replace during backend integration.
========================================================== */

$sections = [];


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

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <?php if (!empty($flash['success'])): ?>

            <div class="alert alert-success alert-dismissible fade show mx-4 mt-4" role="alert">

                <?= htmlspecialchars($flash['success']) ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>

        <?php if (!empty($flash['error'])): ?>

            <div class="alert alert-danger alert-dismissible fade show mx-4 mt-4" role="alert">

                <?= htmlspecialchars($flash['error']) ?>

                <button class="modal-close-icon-btn">
                    <i data-lucide="x"></i>
                </button>

            </div>

        <?php endif; ?>

        <!-- ==========================================================
         PAGE HEADER
    ========================================================== -->

        <section class="page-header">

            <div class="page-header-left">

                <h1 class="page-title">

                    Sections

                </h1>

            </div>

            <div class="page-header-right">

                <div class="header-actions-group">

                    <button class="page-action-btn" type="button" title="Add Section" data-bs-toggle="modal"
                        data-bs-target="#addSectionModal">

                        <i data-lucide="plus"></i>

                    </button>

                </div>
            </div>

        </section>

        <!-- ==========================================================
         SECTIONS MODULE
    ========================================================== -->

        <section class="module-card">

            <!-- ==========================================================
             Toolbar
        ========================================================== -->

            <div class="section-toolbar">

                <div class="toolbar-search">
                    <i class="toolbar-search-icon" data-lucide="search"></i>

                    <input class="toolbar-search-input" type="text" id="searchProgram"
                        placeholder="Search by name or adviser...">
                </div>

                <select class="toolbar-select">

                    <option>All Programs</option>

                </select>

                <select class="toolbar-select">

                    <option>All Levels</option>

                </select>

                <select class="toolbar-select">

                    <option>All Grade Levels</option>

                </select>

                <select class="toolbar-select">

                    <option>All Statuses</option>

                </select>

            </div>

            <!-- ==========================================================
             TABLE
        ========================================================== -->

            <div class="table-wrapper">

                <table class="table module-table align-middle">

                    <thead>

                        <tr>

                            <th class="text-center">

                                Section Code

                            </th>

                            <th class="text-center">

                                Program / Strand

                            </th>

                            <th class="text-center col-level">

                                Academic Level

                            </th>

                            <th class="text-center col-year">

                                Year / Grade Level

                            </th>

                            <th class="text-center col-adviser">

                                Adviser Assignment

                            </th>

                            <th class="text-center col-status">

                                Status

                            </th>

                            <th class="text-center  col-actions">

                                Actions

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($sections)): ?>

                            <tr>

                                <td colspan="7">

                                    <div class="section-table-empty">

                                        <div class="empty-state">

                                            <i data-lucide="school"></i>

                                            <h3 class="empty-state-title">

                                                No Sections Found

                                            </h3>

                                            <p class="empty-state-text">

                                                Create your first academic section to begin organizing students, advisers,
                                                schedules, and academic records.

                                            </p>

                                        </div>

                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($sections as $section): ?>

                                <tr>

                                    <td>

                                        <?= htmlspecialchars($section['section_code']) ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars($section['program_name']) ?>

                                    </td>

                                    <td>

                                        <span class="level-badge">

                                            <?= htmlspecialchars($section['academic_level']) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars($section['year_level']) ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars($section['adviser_name']) ?>

                                    </td>

                                    <td>

                                        <span class="status-badge">

                                            <?= htmlspecialchars($section['status']) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <div class="action-group">

                                            <button class="action-btn" title="Edit Section" disabled>

                                                <i data-lucide="pencil"></i>

                                            </button>

                                            <button class="action-btn" title="Change Status" disabled>

                                                <i data-lucide="power"></i>

                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- ==========================================================
         ADD SECTION MODAL
    ========================================================== -->

        <div class="modal fade" id="addSectionModal" tabindex="-1" aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content modal-content-custom" action="#" method="POST">

                    <div class="modal-header-custom">

                        <div class="d-flex align-items-center">

                            <div class="modal-header-icon-box primary">

                                <i data-lucide="layers"></i>

                            </div>

                            <h5 class="modal-title-custom">

                                Create Section

                            </h5>

                        </div>

                        <button class="modal-close-icon-btn">

                            <i data-lucide="x"></i>

                        </button>

                    </div>

                    <div class="modal-body-custom">

                        <div class="mb-4">

                            <label class="form-label-custom">

                                Section Code

                            </label>

                            <input type="text" class="form-control-custom" placeholder="e.g. BSIT3.2C" disabled>

                        </div>

                        <div class="mb-4">

                            <label class="form-label-custom">

                                Program / Strand

                            </label>

                            <select class="form-control-custom" disabled>

                                <option>

                                    Select Program

                                </option>

                            </select>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-4">

                                <label class="form-label-custom">

                                    Academic Level

                                </label>

                                <select class="form-control-custom" disabled>

                                    <option>

                                        Select Academic Level

                                    </option>

                                </select>

                            </div>

                            <div class="col-md-6 mb-4">

                                <label class="form-label-custom">

                                    Year / Grade Level

                                </label>

                                <select class="form-control-custom" disabled>

                                    <option>

                                        Select Year Level

                                    </option>

                                </select>

                            </div>

                        </div>

                        <div class="mb-4">

                            <label class="form-label-custom">

                                Adviser Assignment

                            </label>

                            <select class="form-control-custom" disabled>

                                <option>

                                    Select Adviser

                                </option>

                            </select>

                        </div>

                        <div class="mb-0">

                            <label class="form-label-custom">

                                Status

                            </label>

                            <select class="form-control-custom" disabled>

                                <option>

                                    Active

                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="modal-footer-custom">

                        <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                            Cancel

                        </button>

                        <button type="submit" class="modal-btn-action" disabled>

                            <i data-lucide="plus"></i>

                            <span>

                                Create Section

                            </span>

                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- ==========================================================
         EDIT SECTION MODAL
    ========================================================== -->

        <div class="modal fade" id="editSectionModal" tabindex="-1" aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content modal-content-custom" action="#" method="POST">

                    <div class="modal-header-custom">

                        <div class="d-flex align-items-center">

                            <div class="modal-header-icon-box primary">

                                <i data-lucide="layers"></i>

                            </div>

                            <h5 class="modal-title-custom">

                                EDIT SECTION

                            </h5>

                        </div>

                        <button class="modal-close-icon-btn">

                            <i data-lucide="x"></i>

                        </button>

                    </div>

                    <div class="modal-body-custom">

                        <div class="mb-4">

                            <label class="form-label-custom">

                                Section Code

                            </label>

                            <input type="text" class="form-control-custom" disabled>

                        </div>

                        <div class="mb-4">

                            <label class="form-label-custom">

                                Program / Strand

                            </label>

                            <select class="form-control-custom" disabled>

                                <option>

                                    Program

                                </option>

                            </select>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-4">

                                <label class="form-label-custom">

                                    Academic Level

                                </label>

                                <select class="form-control-custom" disabled>

                                    <option>

                                        Academic Level

                                    </option>

                                </select>

                            </div>

                            <div class="col-md-6 mb-4">

                                <label class="form-label-custom">

                                    Year / Grade Level

                                </label>

                                <select class="form-control-custom" disabled>

                                    <option>

                                        Year Level

                                    </option>

                                </select>

                            </div>

                        </div>

                        <div class="mb-4">

                            <label class="form-label-custom">

                                Adviser Assignment

                            </label>

                            <select class="form-control-custom" disabled>

                                <option>

                                    Adviser

                                </option>

                            </select>

                        </div>

                        <div class="mb-0">

                            <label class="form-label-custom">

                                Status

                            </label>

                            <select class="form-control-custom" disabled>

                                <option>

                                    Active

                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="modal-footer-custom">

                        <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                            Cancel

                        </button>

                        <button type="submit" class="modal-btn-action" disabled>

                            <i data-lucide="save"></i>

                            <span>

                                Save Changes

                            </span>

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </main>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>