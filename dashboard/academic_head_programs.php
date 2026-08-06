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
require_once __DIR__ . '/../includes/helper/program_helper.php';

$pageTitle = 'Programs';

$activePage = 'programs';

$roleStylesheet = 'assets/css/academic-head.css';

$pageStylesheet = 'assets/css/pages/academic-head-programs.css';

$programs = getPrograms($pdo);

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

                <button type="button" class="btn-close" data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>

        <!-- ==========================================================
         Page Header
    ========================================================== -->

        <section class="page-header">

            <div class="page-header-left">

                <h1 class="page-title">

                    Programs

                </h1>

            </div>

            <div class="page-header-right">

                <button class="page-action-btn" type="button" data-bs-toggle="modal" data-bs-target="#addProgramModal"
                    title="Add Program" aria-label="Add Program">

                    <i data-lucide="plus"></i>

                </button>

            </div>

        </section>

        <!-- ==========================================================
         Programs Module
    ========================================================== -->

        <section class="module-card">

            <div class="program-toolbar">

                <div class="toolbar-search">
                    <i class="toolbar-search-icon" data-lucide="search"></i>

                    <input class="toolbar-search-input" type="text" id="searchProgram"
                        placeholder="Search by program or strand name...">
                </div>

                <select id="filterLevel" class="toolbar-select">
                    <option>All Levels</option>
                </select>

                <select id="filterStatus" class="toolbar-select">
                    <option>All Statuses</option>
                </select>

            </div>

            <div class="table-wrapper">

                <table class="table module-table align-middle">

                    <thead>

                        <tr>

                            <th class="text-center">

                                Program / Strand

                            </th>

                            <th class="text-center">

                                Academic Level

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

                        <?php if (empty($programs)): ?>

                            <tr>

                                <td colspan="4">

                                    <div class="program-table-empty">

                                        <div class="empty-state">

                                            <i data-lucide="graduation-cap"></i>

                                            <h3 class="empty-state-title">
                                                No Programs Found
                                            </h3>

                                            <p class="empty-state-text">
                                                Create your first academic program to begin organizing sections, subjects,
                                                and schedules.
                                            </p>

                                        </div>

                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($programs as $program): ?>

                                <tr>
                                    <td class="program-name-cell">

                                        <div class="program-name-wrapper">

                                            <span class="program-code">

                                                <?= htmlspecialchars($program['program_code']) ?>

                                            </span>

                                            <span class="program-name">

                                                <?= htmlspecialchars($program['program_name']) ?>

                                            </span>

                                        </div>

                                    </td>

                                    <td>

                                        <span
                                            class="level-badge <?= strtolower(str_replace(' ', '-', $program['academic_level'])) ?>">

                                            <?= htmlspecialchars($program['academic_level']) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="status-badge <?= strtolower($program['status']) ?>">

                                            <span class="status-dot"></span>

                                            <?= htmlspecialchars($program['status']) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <div class="action-group">

                                            <button class="action-btn" type="button" title="Edit Program" disabled>

                                                <i data-lucide="square-pen"></i>

                                            </button>

                                            <button class="action-btn" type="button" title="Deactivate Program" disabled>

                                                <i data-lucide="circle-off"></i>

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

    </main>

    <!-- ==========================================================
     Add Program Modal
========================================================== -->

    <!-- ==========================================================
     Add Program Modal
========================================================== -->

    <div class="modal fade" id="addProgramModal" tabindex="-1" aria-labelledby="addProgramModalLabel"
        aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content modal-content-custom"
                action="<?= APP_URL ?>/actions/academic_head/create_program.php" method="POST">

                <div class="modal-header-custom">

                    <div class="d-flex align-items-center">

                        <div class="modal-header-icon-box primary">

                            <i data-lucide="layers"></i>

                        </div>

                        <div class="modal-header-title-wrapper">

                            <h5 class="modal-title-custom">

                                Create Program / Strand

                            </h5>

                            <p class="modal-subtitle-custom">

                                Academic Structure

                            </p>

                        </div>

                    </div>
                    <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                        <i data-lucide="x"></i>

                    </button>

                </div>

                <div class="modal-body-custom">

                    <!-- Preserve backend compatibility -->

                    <input type="hidden" name="program_code" id="program_code">

                    <!-- Program Name -->

                    <div class="mb-4">

                        <label for="program_name" class="form-label-custom">

                            Program / Strand Name

                        </label>

                        <input type="text" id="program_name" name="program_name" class="form-control-custom"
                            placeholder="e.g. Bachelor of Science in Information Technology (BSIT)" maxlength="150"
                            required>

                    </div>

                    <!-- Row -->

                    <div class="modal-row">

                        <div class="mb-3">

                            <label for="academic_level" class="form-label-custom">

                                Academic Level

                            </label>

                            <select id="academic_level" name="academic_level" class="form-control-custom" required>

                                <option value="">
                                    Select Academic Level
                                </option>

                                <option value="College">
                                    College
                                </option>

                                <option value="Senior High School">
                                    Senior High School
                                </option>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label for="status" class="form-label-custom">

                                Status

                            </label>

                            <select id="status" name="status" class="form-control-custom">

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>

                    </div>

                </div>

                <div class="modal-footer-custom">

                    <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button type="submit" class="modal-btn-action">

                        Add Program

                    </button>

                </div>

            </form>

        </div>

    </div>

    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

</body>

</html>