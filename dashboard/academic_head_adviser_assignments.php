<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/adviser_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/current_teaching_context_helper.php';

$pageTitle = 'Adviser Assignments';
$activePage = 'adviser_assignments';
$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-adviser-assignments.css';

$contexts = aprismAdviserTeachingContexts();
$statusOptions = ['Active', 'Ended', 'Invalid / Ineligible', 'All'];
$pageSize = 6;
$successMessage = $flash['success'] ?? null;
$errorMessage = $flash['error'] ?? null;
$warningMessage = $flash['warning'] ?? null;
$fieldError = $_SESSION['adviser_assignment_field_error'] ?? null;
$submittedForm = $_SESSION['adviser_assignment_form_values'] ?? [];
unset($_SESSION['adviser_assignment_field_error'], $_SESSION['adviser_assignment_form_values']);

$schoolYears = [];
$activeSchoolYearId = null;

try {
    $schoolYears = $pdo->query(
        "SELECT school_year_id, school_year, status
         FROM school_years
         WHERE status IN ('Active', 'Inactive')
         ORDER BY CASE status WHEN 'Active' THEN 1 ELSE 2 END, start_date DESC, school_year_id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schoolYears as $schoolYear) {
        if ($schoolYear['status'] === 'Active') {
            $activeSchoolYearId = (int) $schoolYear['school_year_id'];
            break;
        }
    }
} catch (PDOException $exception) {
    error_log('[APRISM Adviser Assignments - School Years] ' . $exception->getMessage());
}

$selectedSchoolYearId = filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT);
if (!$selectedSchoolYearId || !array_filter($schoolYears, static fn (array $row): bool => (int) $row['school_year_id'] === $selectedSchoolYearId)) {
    $selectedSchoolYearId = $activeSchoolYearId ?? (!empty($schoolYears) ? (int) $schoolYears[0]['school_year_id'] : 0);
}

$search = trim((string) ($_GET['teacher'] ?? ''));
$contextFilter = trim((string) ($_GET['teaching_context'] ?? 'All'));
$statusFilter = trim((string) ($_GET['status'] ?? 'All'));
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$currentPage = max(1, $currentPage);
if ($contextFilter !== 'All' && !in_array($contextFilter, $contexts, true)) {
    $contextFilter = 'All';
}
if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = 'All';
}

$eligibleSections = [];
$teachers = [];
$assignments = [];
$totalAssignments = 0;
$totalPages = 1;

try {
    $eligibleSections = $pdo->query(
        "SELECT sec.section_id, sec.section_name, sec.year_level,
                p.program_code, p.program_name
         FROM sections sec
         INNER JOIN programs p
             ON p.program_id = sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
         WHERE sec.status = 'Active'
         ORDER BY p.program_code, sec.year_level, sec.section_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $teacherStmt = $pdo->prepare(
        "SELECT u.user_id, u.employee_number, u.first_name, u.last_name,
                u.username
         FROM users u
         WHERE u.role_id = ?
           AND u.account_status = 'Active'
         ORDER BY u.last_name, u.first_name, u.employee_number"
    );
    $teacherStmt->execute([ROLE_TEACHER]);
    $teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);

    $assignmentJoin = '';
    $params = [$selectedSchoolYearId];
    if (in_array($statusFilter, ['All', 'Active'], true)) {
        $currentAssignmentContext = $contextFilter !== 'All'
            ? ' AND aa.teaching_context = ?'
            : '';
        $assignmentJoin = "LEFT JOIN adviser_assignments aa
            ON aa.teacher_id = u.user_id
           AND aa.school_year_id = ?
           AND aa.status = 'Active'{$currentAssignmentContext}";
        $params[] = $selectedSchoolYearId;
        if ($contextFilter !== 'All') {
            $params[] = $contextFilter;
        }
    } else {
        // Ended and Invalid / Ineligible are explicit historical/diagnostic views.
        $assignmentJoin = "LEFT JOIN adviser_assignments aa
            ON aa.teacher_id = u.user_id
           AND aa.school_year_id = ?";
        $params[] = $selectedSchoolYearId;
    }
    $params[] = $selectedSchoolYearId;
    $params[] = ROLE_TEACHER;

    $baseSql = "SELECT * FROM (
            SELECT
                u.user_id, u.employee_number, u.first_name, u.last_name, u.username,
                tra.status AS capability_status,
                aa.adviser_assignment_id, aa.teacher_id, aa.section_id,
                aa.school_year_id, aa.teaching_context, aa.status AS assignment_status,
                aa.assigned_at, aa.ended_at,
                sy.school_year,
                sec.section_name, sec.year_level, sec.status AS section_status,
                p.program_code, p.program_name, p.academic_level, p.status AS program_status,
                CASE WHEN aa.adviser_assignment_id IS NOT NULL
                    AND sec.status = 'Active'
                    AND p.status = 'Active'
                    AND p.academic_level = 'Senior High School'
                    AND aa.teaching_context IN ('First Semester', 'Second Semester')
                    THEN 1 ELSE 0 END AS is_eligible_assignment,
                CASE WHEN aa.adviser_assignment_id IS NOT NULL
                    AND aa.status = 'Active'
                    AND tra.status = 'Active'
                    AND sy.status = 'Active'
                    AND sec.status = 'Active'
                    AND p.status = 'Active'
                    AND p.academic_level = 'Senior High School'
                    AND aa.teaching_context IN ('First Semester', 'Second Semester')
                    THEN 1 ELSE 0 END AS is_valid_current_assignment
            FROM users u
            LEFT JOIN teacher_responsibility_assignments tra
                ON tra.teacher_id = u.user_id
               AND tra.responsibility_name = 'Adviser'
               AND tra.school_year_id = ?
               AND tra.status = 'Active'
            {$assignmentJoin}
              LEFT JOIN school_years sy
                ON sy.school_year_id = COALESCE(aa.school_year_id, ?)
              LEFT JOIN sections sec ON sec.section_id = aa.section_id
              LEFT JOIN programs p ON p.program_id = sec.program_id
              WHERE u.role_id = ?
                AND u.account_status = 'Active'
                AND (tra.responsibility_assignment_id IS NOT NULL
                     OR aa.adviser_assignment_id IS NOT NULL)
        ) assignment_records
        WHERE 1 = 1";

    if ($contextFilter !== 'All') {
        $baseSql .= " AND teaching_context = ?";
        $params[] = $contextFilter;
    }

    if ($statusFilter === 'Active') {
        $baseSql .= " AND adviser_assignment_id IS NOT NULL
                      AND assignment_status = 'Active'
                      AND is_valid_current_assignment = 1";
    } elseif ($statusFilter === 'Ended') {
        $baseSql .= " AND assignment_status = 'Ended'
                      AND is_eligible_assignment = 1";
    } elseif ($statusFilter === 'Invalid / Ineligible') {
        $baseSql .= " AND adviser_assignment_id IS NOT NULL
                      AND (is_eligible_assignment = 0
                           OR (assignment_status = 'Active' AND is_valid_current_assignment = 0))";
    }

    if ($search !== '') {
        $baseSql .= " AND (employee_number LIKE ?
                    OR username LIKE ?
                    OR CONCAT_WS(' ', first_name, last_name) LIKE ?
                    OR section_name LIKE ?)";
        $searchLike = '%' . $search . '%';
        array_push($params, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$baseSql}) assignment_count");
    $totalStmt->execute($params);
    $totalAssignments = (int) $totalStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalAssignments / $pageSize));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $pageSize;

    $assignmentStmt = $pdo->prepare(
        $baseSql . " ORDER BY last_name, first_name,
                     assignment_status = 'Active' DESC,
                     assigned_at DESC, adviser_assignment_id DESC
                     LIMIT {$pageSize} OFFSET {$offset}"
    );
    $assignmentStmt->execute($params);
    $assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('[APRISM Adviser Assignments] ' . $exception->getMessage());
    $errorMessage = 'Unable to load Adviser assignment records.';
}

$editAssignment = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    foreach ($assignments as $assignment) {
        if ((int) ($assignment['adviser_assignment_id'] ?? 0) === $editId) {
            $editAssignment = $assignment;
            break;
        }
    }
}

$formOperation = $editAssignment ? 'change' : 'create';
$formTeacherId = $editAssignment ? (int) $editAssignment['teacher_id'] : 0;
$formSchoolYearId = $editAssignment ? (int) $editAssignment['school_year_id'] : $selectedSchoolYearId;
$defaultTeachingContext = $formSchoolYearId === $activeSchoolYearId
    ? aprismCurrentTeachingContext($pdo, $formSchoolYearId, 'Senior High School')
    : null;
$formContext = $editAssignment['teaching_context'] ?? ($defaultTeachingContext ?? '');
$formSectionId = $editAssignment ? (int) $editAssignment['section_id'] : 0;

if ($submittedForm !== []) {
    $formOperation = in_array($submittedForm['operation'] ?? '', ['create', 'change'], true)
        ? $submittedForm['operation']
        : $formOperation;
    $formTeacherId = (int) ($submittedForm['teacher_id'] ?? $formTeacherId);
    $formSchoolYearId = (int) ($submittedForm['school_year_id'] ?? $formSchoolYearId);
    $formContext = (string) ($submittedForm['teaching_context'] ?? $formContext);
    $formSectionId = (int) ($submittedForm['section_id'] ?? $formSectionId);
}

$activeAssignmentsForYear = [];
if ($selectedSchoolYearId > 0) {
    try {
        $activeMapStmt = $pdo->prepare(
            "SELECT aa.adviser_assignment_id, aa.teacher_id, aa.section_id,
                    aa.school_year_id,
                    aa.teaching_context, u.first_name, u.last_name,
                    sec.section_name, p.program_code
             FROM adviser_assignments aa
             INNER JOIN users u ON u.user_id = aa.teacher_id
             INNER JOIN sections sec ON sec.section_id = aa.section_id
             LEFT JOIN programs p ON p.program_id = sec.program_id
             WHERE aa.status = 'Active'"
        );
        $activeMapStmt->execute();
        $activeAssignmentsForYear = $activeMapStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        error_log('[APRISM Adviser Assignment - Active Map] ' . $exception->getMessage());
    }
}

function adviserAssignmentEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adviserAssignmentQuery(array $parameters): string
{
    return '?' . http_build_query($parameters);
}

$filterParameters = [
    'teacher' => $search,
    'school_year_id' => $selectedSchoolYearId,
    'teaching_context' => $contextFilter,
    'status' => $statusFilter,
    'per_page' => $pageSize,
];

$emptyAssignmentMessage = $statusFilter === 'Active'
    ? 'No active assignments match these filters.'
    : 'No assignment records match the selected filters.';
$paginationEntityLabel = in_array($statusFilter, ['All', 'Active'], true)
    ? 'Adviser records'
    : 'assignment records';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require __DIR__ . '/../includes/components/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content adviser-assignment-page-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper adviser-assignment-page">
            <section class="page-header">
                <div class="page-header-left">
                    <h1 class="page-title">Adviser Assignments</h1>
                </div>
            </section>

            <section class="assignment-form-card">
                <div class="section-heading-row">
                    <div>
                        <h2><?= $editAssignment ? 'Change Adviser Assignment' : 'Assign an Adviser' ?></h2>
                    </div>
                    <?php if ($editAssignment): ?>
                        <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="<?= APP_URL ?>/dashboard/academic_head_adviser_assignments.php">Cancel change</a>
                    <?php endif; ?>
                </div>

                <?php if (empty($eligibleSections)): ?>
                    <div class="assignment-blocker" role="status">
                        <i data-lucide="info"></i>
                        <span>No active Sections with confirmed SHS classification are available. Check Section and Program setup.</span>
                    </div>
                <?php endif; ?>

                <?php if (empty($teachers)): ?>
                    <div class="assignment-blocker" role="status">
                        <i data-lucide="info"></i>
                        <span>No active Teacher accounts are available.</span>
                    </div>
                <?php endif; ?>

                <form class="assignment-form" id="adviserAssignmentForm" action="<?= APP_URL ?>/actions/academic_head/manage_adviser_assignment.php" method="POST"
                    data-active-assignments="<?= adviserAssignmentEsc(json_encode($activeAssignmentsForYear, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]') ?>"
                    data-active-school-year-id="<?= (int) $activeSchoolYearId ?>"
                    data-current-shs-context="<?= adviserAssignmentEsc($activeSchoolYearId && $activeSchoolYearId === $selectedSchoolYearId ? (string) ($defaultTeachingContext ?? '') : '') ?>"
                    data-edit-assignment-id="<?= $editAssignment ? (int) $editAssignment['adviser_assignment_id'] : 0 ?>">
                    <input type="hidden" name="csrf_token" value="<?= adviserAssignmentEsc(generateCsrfToken()) ?>">
                    <input type="hidden" name="operation" value="<?= adviserAssignmentEsc($formOperation) ?>">
                    <input type="hidden" name="return_school_year_id" value="<?= (int) $selectedSchoolYearId ?>">
                    <input type="hidden" name="return_teaching_context" value="<?= adviserAssignmentEsc($contextFilter) ?>">
                    <input type="hidden" name="return_status" value="<?= adviserAssignmentEsc($statusFilter) ?>">
                    <input type="hidden" name="return_page" value="<?= (int) $currentPage ?>">
                    <input type="hidden" name="return_teacher" value="<?= adviserAssignmentEsc($search) ?>">
                    <?php if ($editAssignment): ?>
                        <input type="hidden" name="assignment_id" value="<?= (int) $editAssignment['adviser_assignment_id'] ?>">
                    <?php endif; ?>

                    <label class="<?= ($fieldError['field'] ?? '') === 'teacher_id' ? 'has-error' : '' ?>">
                        <span>Teacher</span>
                        <select name="teacher_id" id="assignmentTeacher" required <?= ($fieldError['field'] ?? '') === 'teacher_id' ? 'aria-describedby="assignmentTeacherError"' : '' ?>>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <?php $teacherName = trim($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                <option value="<?= (int) $teacher['user_id'] ?>" <?= $formTeacherId === (int) $teacher['user_id'] ? 'selected' : '' ?>>
                                    <?= adviserAssignmentEsc($teacherName . ' · ' . $teacher['employee_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (($fieldError['field'] ?? '') === 'teacher_id'): ?><small class="assignment-field-error" id="assignmentTeacherError" role="alert"><?= adviserAssignmentEsc($fieldError['message'] ?? '') ?></small><?php endif; ?>
                    </label>

                    <label class="<?= ($fieldError['field'] ?? '') === 'school_year_id' ? 'has-error' : '' ?>">
                        <span>School Year</span>
                        <select name="school_year_id" id="assignmentSchoolYear" required <?= ($fieldError['field'] ?? '') === 'school_year_id' ? 'aria-describedby="assignmentSchoolYearError"' : '' ?>>
                            <option value="">Select School Year</option>
                            <?php foreach ($schoolYears as $schoolYear): ?>
                                <option value="<?= (int) $schoolYear['school_year_id'] ?>" <?= $formSchoolYearId === (int) $schoolYear['school_year_id'] ? 'selected' : '' ?>>
                                    <?= adviserAssignmentEsc($schoolYear['school_year'] . ($schoolYear['status'] === 'Active' ? ' · Active' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (($fieldError['field'] ?? '') === 'school_year_id'): ?><small class="assignment-field-error" id="assignmentSchoolYearError" role="alert"><?= adviserAssignmentEsc($fieldError['message'] ?? '') ?></small><?php endif; ?>
                    </label>

                    <label class="<?= ($fieldError['field'] ?? '') === 'teaching_context' ? 'has-error' : '' ?>">
                        <span>SHS Teaching Context</span>
                        <select name="teaching_context" id="assignmentContext" required <?= ($fieldError['field'] ?? '') === 'teaching_context' ? 'aria-describedby="assignmentContextError"' : '' ?>>
                            <option value="">Select Context</option>
                            <?php foreach ($contexts as $context): ?>
                                <option value="<?= adviserAssignmentEsc($context) ?>" <?= $formContext === $context ? 'selected' : '' ?>><?= adviserAssignmentEsc($context) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$editAssignment && $formSchoolYearId === $activeSchoolYearId && $defaultTeachingContext === null): ?>
                            <small class="assignment-field-hint">No current SHS teaching context is configured. Select the applicable context to continue.</small>
                        <?php endif; ?>
                        <?php if (($fieldError['field'] ?? '') === 'teaching_context'): ?><small class="assignment-field-error" id="assignmentContextError" role="alert"><?= adviserAssignmentEsc($fieldError['message'] ?? '') ?></small><?php endif; ?>
                    </label>

                    <label class="<?= ($fieldError['field'] ?? '') === 'section_id' ? 'has-error' : '' ?>">
                        <span>SHS Section</span>
                        <select name="section_id" id="assignmentSection" required <?= empty($eligibleSections) ? 'disabled' : '' ?> <?= ($fieldError['field'] ?? '') === 'section_id' ? 'aria-describedby="assignmentSectionError"' : '' ?>>
                            <option value="">Select eligible SHS Section</option>
                            <?php foreach ($eligibleSections as $section): ?>
                                <option value="<?= (int) $section['section_id'] ?>" <?= $formSectionId === (int) $section['section_id'] ? 'selected' : '' ?>>
                                    <?= adviserAssignmentEsc($section['section_name'] . ' · ' . $section['program_code'] . (!empty($section['year_level']) ? ' · Grade ' . $section['year_level'] : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (($fieldError['field'] ?? '') === 'section_id'): ?><small class="assignment-field-error" id="assignmentSectionError" role="alert"><?= adviserAssignmentEsc($fieldError['message'] ?? '') ?></small><?php endif; ?>
                    </label>

                    <div class="assignment-review" aria-live="polite" aria-atomic="true">
                        <strong>Review assignment</strong>
                        <span id="assignmentReviewSummary">Select a Teacher, School Year, teaching context, and Section.</span>
                    </div>
                    <div class="assignment-conflict" id="assignmentConflict" role="status" hidden></div>

                    <button class="aprism-action-btn aprism-action-btn-primary is-standard" id="confirmAdviserAssignment" type="submit" <?= empty($eligibleSections) || empty($teachers) ? 'disabled' : '' ?>>
                        <i data-lucide="save"></i>
                        <?= $editAssignment ? 'Confirm Assignment Change' : 'Confirm Adviser Assignment' ?>
                    </button>
                </form>
            </section>

            <section class="assignment-list-card">
                <div class="section-heading-row">
                    <div>
                        <h2>Adviser Assignment Records</h2>
                    </div>
                </div>

                <form class="assignment-filters" method="GET" action="<?= APP_URL ?>/dashboard/academic_head_adviser_assignments.php">
                    <input type="hidden" name="per_page" value="<?= $pageSize ?>">
                    <label>
                        <span>Teacher</span>
                        <input type="search" name="teacher" value="<?= adviserAssignmentEsc($search) ?>" placeholder="Name or employee no.">
                    </label>
                    <label>
                        <span>School Year</span>
                        <select name="school_year_id">
                            <?php foreach ($schoolYears as $schoolYear): ?>
                                <option value="<?= (int) $schoolYear['school_year_id'] ?>" <?= $selectedSchoolYearId === (int) $schoolYear['school_year_id'] ? 'selected' : '' ?>><?= adviserAssignmentEsc($schoolYear['school_year']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Teaching Context</span>
                        <select name="teaching_context">
                            <option value="All">All contexts</option>
                            <?php foreach ($contexts as $context): ?>
                                <option value="<?= adviserAssignmentEsc($context) ?>" <?= $contextFilter === $context ? 'selected' : '' ?>><?= adviserAssignmentEsc($context) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="status">
                            <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Ended" <?= $statusFilter === 'Ended' ? 'selected' : '' ?>>Ended</option>
                            <option value="Invalid / Ineligible" <?= $statusFilter === 'Invalid / Ineligible' ? 'selected' : '' ?>>Invalid / Ineligible</option>
                            <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                        </select>
                    </label>
                    <div class="assignment-filter-actions">
                        <button class="aprism-action-btn aprism-action-btn-primary is-standard" type="submit">Filter</button>
                        <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="<?= APP_URL ?>/dashboard/academic_head_adviser_assignments.php?per_page=<?= $pageSize ?>">Reset</a>
                    </div>
                </form>

                <div class="assignment-table-wrap">
                    <table class="assignment-table">
                        <thead>
                            <tr>
                                <th class="table-row-number" scope="col">#</th>
                                <th>Teacher</th>
                                <th>Capability</th>
                                <th>Assigned Section</th>
                                <th>School Year</th>
                                <th>Teaching Context</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignments)): ?>
                                <tr><td colspan="8" class="assignment-empty"><?= adviserAssignmentEsc($emptyAssignmentMessage) ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $assignmentIndex => $assignment): ?>
                                    <?php $hasAssignment = !empty($assignment['adviser_assignment_id']); ?>
                                    <?php $isValid = (int) ($assignment['is_valid_current_assignment'] ?? 0) === 1; ?>
                                    <?php $rowClass = !$hasAssignment ? 'is-unassigned' : ($isValid ? 'is-valid' : ($assignment['assignment_status'] === 'Active' ? 'is-invalid' : 'is-history')); ?>
                                    <tr class="<?= $rowClass ?>" <?= $hasAssignment ? 'id="assignment-record-' . (int) $assignment['adviser_assignment_id'] . '"' : '' ?>>
                                        <td class="table-row-number" data-label="#"><?= (($currentPage - 1) * $pageSize) + $assignmentIndex + 1 ?></td>
                                        <td data-label="Teacher">
                                            <strong><?= adviserAssignmentEsc(trim($assignment['first_name'] . ' ' . $assignment['last_name'])) ?></strong>
                                            <small><?= adviserAssignmentEsc($assignment['employee_number']) ?></small>
                                        </td>
                                        <td data-label="Capability">
                                            <?php if (($assignment['capability_status'] ?? '') === 'Active'): ?>
                                                <span class="status-badge is-active">Active</span>
                                            <?php else: ?>
                                                <span class="status-badge is-neutral">Not enabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Assigned Section">
                                            <?php if ($hasAssignment): ?>
                                                <strong><?= adviserAssignmentEsc($assignment['section_name'] ?? 'Unknown Section') ?></strong>
                                                <small><?= adviserAssignmentEsc($assignment['program_code'] ?? 'No active Program') ?></small>
                                            <?php else: ?>
                                                <span class="muted-value">No assignment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="School Year"><?= adviserAssignmentEsc($assignment['school_year'] ?? '—') ?></td>
                                        <td data-label="Teaching Context"><?= adviserAssignmentEsc($assignment['teaching_context'] ?? 'Not set') ?></td>
                                        <td data-label="Status">
                                            <?php if (!$hasAssignment): ?>
                                                <span class="status-badge is-neutral">Unassigned</span>
                                            <?php elseif ($isValid): ?>
                                                <span class="status-badge is-active">Active</span>
                                            <?php elseif ($assignment['assignment_status'] === 'Active'): ?>
                                                <span class="status-badge is-invalid">Invalid / Ineligible</span>
                                            <?php else: ?>
                                                <span class="status-badge is-neutral"><?= adviserAssignmentEsc($assignment['assignment_status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="assignment-actions" data-label="Actions">
                                            <?php if ($hasAssignment && $assignment['assignment_status'] === 'Active'): ?>
                                                <?php $editParameters = $filterParameters + ['edit' => (int) $assignment['adviser_assignment_id'], 'page' => $currentPage]; ?>
                                                <div class="assignment-action-group">
                                                    <a class="aprism-action-btn aprism-action-btn-secondary" href="<?= adviserAssignmentQuery($editParameters) ?>">Change</a>
                                                    <form method="POST" action="<?= APP_URL ?>/actions/academic_head/manage_adviser_assignment.php">
                                                        <input type="hidden" name="csrf_token" value="<?= adviserAssignmentEsc(generateCsrfToken()) ?>">
                                                        <input type="hidden" name="operation" value="deactivate">
                                                        <input type="hidden" name="assignment_id" value="<?= (int) $assignment['adviser_assignment_id'] ?>">
                                                        <input type="hidden" name="return_school_year_id" value="<?= (int) $selectedSchoolYearId ?>">
                                                        <input type="hidden" name="return_teaching_context" value="<?= adviserAssignmentEsc($contextFilter) ?>">
                                                        <input type="hidden" name="return_status" value="<?= adviserAssignmentEsc($statusFilter) ?>">
                                                        <input type="hidden" name="return_page" value="<?= (int) $currentPage ?>">
                                                        <input type="hidden" name="return_teacher" value="<?= adviserAssignmentEsc($search) ?>">
                                                        <button class="aprism-action-btn aprism-action-btn-danger" type="submit">End</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="muted-value">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalAssignments > 0): ?>
                    <?php $firstAssignment = (($currentPage - 1) * $pageSize) + 1; ?>
                    <?php $lastAssignment = min($currentPage * $pageSize, $totalAssignments); ?>
                    <div class="assignment-table-footer">
                            <span>Showing <?= $firstAssignment ?> to <?= $lastAssignment ?> of <?= $totalAssignments ?> <?= adviserAssignmentEsc($paginationEntityLabel) ?></span>
                        <?php if ($totalPages > 1): ?>
                            <?php $pageStart = max(1, min($currentPage - 1, $totalPages - 2)); ?>
                            <?php $pageEnd = min($totalPages, $pageStart + 2); ?>
                            <nav class="assignment-pagination" aria-label="Adviser assignment pages">
                                <?php if ($currentPage === 1): ?>
                                    <span class="pagination-control is-disabled" aria-label="Previous page" aria-disabled="true">&lsaquo;</span>
                                <?php else: ?>
                                    <a class="pagination-control" href="<?= adviserAssignmentQuery($filterParameters + ['page' => $currentPage - 1]) ?>" aria-label="Previous page">&lsaquo;</a>
                                <?php endif; ?>
                                <?php for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++): ?>
                                    <a class="pagination-number <?= $currentPage === $pageNumber ? 'is-current' : '' ?>" href="<?= adviserAssignmentQuery($filterParameters + ['page' => $pageNumber]) ?>" <?= $currentPage === $pageNumber ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a>
                                <?php endfor; ?>
                                <?php if ($currentPage === $totalPages): ?>
                                    <span class="pagination-control is-disabled" aria-label="Next page" aria-disabled="true">&rsaquo;</span>
                                <?php else: ?>
                                    <a class="pagination-control" href="<?= adviserAssignmentQuery($filterParameters + ['page' => $currentPage + 1]) ?>" aria-label="Next page">&rsaquo;</a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"
         data-success-message="<?= adviserAssignmentEsc($successMessage ?? '') ?>"
         data-error-message="<?= adviserAssignmentEsc($errorMessage ?? '') ?>"
         data-warning-message="<?= adviserAssignmentEsc($warningMessage ?? '') ?>"></div>
    <script src="<?= APP_URL ?>/assets/js/pages/academic-head-adviser-assignments.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/academic-head-adviser-assignments.js') ?>"></script>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
