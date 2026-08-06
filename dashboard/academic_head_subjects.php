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

$pageTitle = 'Subjects';
$activePage = 'subjects';

$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-subjects.css';

/*
|--------------------------------------------------------------------------
| Temporary Data
|--------------------------------------------------------------------------
| Backend integration will replace this later.
*/

$subjects = [];

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

                    Subjects

                </h1>

            </div>

            <div class="page-header-right">

                <button class="page-action-btn" type="button" data-bs-toggle="modal"
                    data-bs-target="#createSubjectModal" title="Create Subject Record">

                    <i data-lucide="plus"></i>

                </button>

            </div>

        </section>



        <!-- ==========================================================
         SUBJECTS MODULE
    =========================================================== -->

        <section class="module-card">

            <div class="subject-toolbar">

                <div class="toolbar-search">

                    <i class="toolbar-search-icon" data-lucide="search">
                    </i>

                    <input class="toolbar-search-input" type="text"
                        placeholder="Search by subject code or subject name...">

                </div>



                <select class="toolbar-select">

                    <option>

                        All Levels

                    </option>

                </select>



                <select class="toolbar-select">

                    <option>

                        All Programs

                    </option>

                </select>



                <select class="toolbar-select">

                    <option>

                        All Statuses

                    </option>

                </select>

            </div>



            <div class="table-wrapper">

                <table class="table module-table align-middle">

                    <thead>

                        <tr>

                            <th class="text-center">

                                Subject Code

                            </th>

                            <th class="text-center">

                                Subject Name

                            </th>

                            <th class="text-center">

                                Academic Level

                            </th>

                            <th class="text-center">

                                Units

                            </th>

                            <th class="text-center">

                                Grade Level

                            </th>

                            <th class="text-center">

                                Status

                            </th>

                            <th class="text-center">

                                Actions

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($subjects)): ?>

                            <tr>

                                <td colspan="7">

                                    <div class="subject-table-empty">

                                        <div class="empty-state">

                                            <i data-lucide="book-open"></i>

                                            <h3 class="empty-state-title">

                                                No Subjects Found

                                            </h3>

                                            <p class="empty-state-text">

                                                Create your first academic subject
                                                to begin organizing sections,
                                                grading, and academic records.

                                            </p>

                                        </div>

                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>



                            <!-- ======================================================
                         SUBJECT ROWS
                         Backend Later
                    ======================================================= -->



                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>
        <!-- ==========================================================
         CREATE SUBJECT MODAL
    =========================================================== -->

        <div class="modal fade" id="createSubjectModal" tabindex="-1" aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content modal-content-custom">

                    <!-- Header -->

                    <div class="modal-header-custom">

                        <div class="d-flex align-items-center">

                            <div class="modal-header-icon-box primary">

                                <i data-lucide="book-open"></i>

                            </div>

                            <div class="modal-header-title-wrapper">

                                <h5 class="modal-title-custom">

                                    Create Subject Record

                                </h5>

                                <p class="modal-subtitle-custom">

                                    Academic Subject Information

                                </p>

                            </div>

                        </div>

                        <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                            <i data-lucide="x"></i>

                        </button>

                    </div>

                    <!-- Body -->

                    <div class="modal-body-custom">

                        <div class="modal-row">

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Subject Code

                                </label>

                                <input type="text" class="form-control-custom" placeholder="e.g. ITP101">

                            </div>

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Full Academic Subject Title

                                </label>

                                <input type="text" class="form-control-custom"
                                    placeholder="e.g. Introduction to Computing">

                            </div>

                        </div>

                        <div class="modal-row">

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Academic Level

                                </label>

                                <select class="form-control-custom">

                                    <option selected disabled>

                                        Select Academic Level

                                    </option>

                                    <option>

                                        College

                                    </option>

                                    <option>

                                        Senior High School

                                    </option>

                                </select>

                            </div>

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Unit Weight

                                </label>

                                <select class="form-control-custom">

                                    <option selected disabled>

                                        Select Units

                                    </option>

                                    <option>1.0 Units</option>
                                    <option>2.0 Units</option>
                                    <option>3.0 Units</option>
                                    <option>4.0 Units</option>
                                    <option>5.0 Units</option>

                                </select>

                            </div>

                        </div>

                        <div class="form-group-custom">

                            <label class="form-label-custom">

                                Program / Strand Alignment

                            </label>

                            <select class="form-control-custom">

                                <option selected disabled>

                                    Select Program / Strand

                                </option>

                            </select>

                        </div>

                    </div>

                    <!-- Footer -->

                    <div class="modal-footer-custom">

                        <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                            Dismiss

                        </button>

                        <button type="submit" class="modal-btn-action">

                            Save Subject

                        </button>

                    </div>

                </form>

            </div>

        </div>
        <!-- ==========================================================
         EDIT SUBJECT MODAL
    =========================================================== -->

        <div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">

            <div class="modal-dialog modal-lg modal-dialog-centered">

                <form class="modal-content modal-content-custom">

                    <!-- Header -->

                    <div class="modal-header-custom">

                        <div class="d-flex align-items-center">

                            <div class="modal-header-icon-box primary">

                                <i data-lucide="square-pen"></i>

                            </div>

                            <div class="modal-header-title-wrapper">

                                <h5 class="modal-title-custom">

                                    Edit Subject

                                </h5>

                                <p class="modal-subtitle-custom">

                                    Update Academic Subject

                                </p>

                            </div>

                        </div>

                        <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                            <i data-lucide="x"></i>

                        </button>

                    </div>

                    <!-- Body -->

                    <div class="modal-body-custom">

                        <input type="hidden" name="subject_id">

                        <div class="form-group-custom">

                            <label class="form-label-custom">

                                Subject Code

                            </label>

                            <input type="text" class="form-control-custom">

                        </div>

                        <div class="form-group-custom">

                            <label class="form-label-custom">

                                Subject Name

                            </label>

                            <input type="text" class="form-control-custom">

                        </div>

                        <div class="modal-row">

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Academic Level

                                </label>

                                <select class="form-control-custom">

                                    <option>

                                        College

                                    </option>

                                    <option>

                                        Senior High School

                                    </option>

                                </select>

                            </div>

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Program / Strand

                                </label>

                                <select class="form-control-custom">

                                    <option>

                                        Select Program

                                    </option>

                                </select>

                            </div>

                        </div>

                        <div class="modal-row">

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Units / Grade Level

                                </label>

                                <input class="form-control-custom">

                            </div>

                            <div class="form-group-custom">

                                <label class="form-label-custom">

                                    Status

                                </label>

                                <select class="form-control-custom">

                                    <option>

                                        Active

                                    </option>

                                    <option>

                                        Inactive

                                    </option>

                                </select>

                            </div>

                        </div>

                        <div class="form-group-custom">

                            <label class="form-label-custom">

                                Description

                            </label>

                            <textarea rows="4" class="form-control-custom"></textarea>

                        </div>

                    </div>

                    <!-- Footer -->

                    <div class="modal-footer-custom">

                        <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                            Dismiss

                        </button>

                        <button type="submit" class="modal-btn-action">

                            Save Subject

                        </button>

                    </div>
                </form>

            </div>

        </div>

    </main>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>


</body>

</html>