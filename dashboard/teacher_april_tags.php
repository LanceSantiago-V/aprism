<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../auth/session_guard.php';

$operationalClassId = filter_var(
    $_GET['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$contextSelectionMode = false;
$eligibleClasses = [];

if ($operationalClassId === false) {
    $contextSelectionMode = true;

    try {
        $eligibleClassesStmt = $pdo->prepare("\n            SELECT\n                oc.operational_class_id,\n                oc.semester,\n                sy.school_year,\n                sub.subject_name,\n                sec.section_name\n            FROM operational_classes AS oc\n            INNER JOIN school_years AS sy\n                ON sy.school_year = oc.school_year\n               AND sy.status = 'Active'\n            INNER JOIN subjects AS sub\n                ON sub.subject_id = oc.subject_id\n            INNER JOIN sections AS sec\n                ON sec.section_id = oc.section_id\n            WHERE oc.teacher_id = ?\n              AND oc.status = 'Active'\n            ORDER BY sub.subject_name, sec.section_name, oc.semester\n        ");

        $eligibleClassesStmt->execute([(int) $_SESSION['user_id']]);
        $eligibleClasses = $eligibleClassesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        error_log('[APRISM Teacher AprilTags context] ' . $exception->getMessage());
        $eligibleClasses = [];
    }
} else {
    $operationalClassId = (int) $operationalClassId;
}

$classContext = null;
$participants = [];
$occupiedTags = [];
$retiredTags = [];
$availableTags = [];
$currentSchoolYear = null;

if (!$contextSelectionMode) {
try {
    $activeSchoolYearStmt = $pdo->query("
        SELECT school_year
        FROM school_years
        WHERE status = 'Active'
        LIMIT 1
    ");

    $currentSchoolYear =
        $activeSchoolYearStmt->fetchColumn() ?: null;

    $classStmt = $pdo->prepare("
        SELECT
            oc.operational_class_id,
            oc.semester,
            sy.school_year_id,
            sy.school_year,
            sub.subject_name,
            sec.section_name
        FROM operational_classes AS oc
        INNER JOIN school_years AS sy
            ON sy.school_year = oc.school_year
           AND sy.status = 'Active'
        INNER JOIN subjects AS sub
            ON sub.subject_id = oc.subject_id
        INNER JOIN sections AS sec
            ON sec.section_id = oc.section_id
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
    ");

    $classStmt->execute([
        $operationalClassId,
        (int) $_SESSION['user_id'],
    ]);

    $classContext = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($classContext === null) {
        $_SESSION['error_message'] =
            'The selected Operational Class is unavailable or you do not have permission to manage it.';

        header('Location: ' . APP_URL . '/dashboard/teacher_my_class.php');
        exit;
    }

    $participantStmt = $pdo->prepare("
        SELECT
            sce.student_class_enrollment_id,
            sce.student_id,
            st.student_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.suffix,
            at.april_tag_id,
            at.tag_family,
            at.tag_code,
            at.assigned_at,
            assignment.issued_from_operational_class_id
        FROM student_class_enrollments AS sce
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        LEFT JOIN april_tags AS at
            ON at.current_student_id = sce.student_id
           AND at.status = 'Assigned'
        LEFT JOIN april_tag_assignments AS assignment
            ON assignment.april_tag_id = at.april_tag_id
           AND assignment.status = 'Assigned'
           AND assignment.reclaimed_at IS NULL
        WHERE sce.operational_class_id = ?
          AND sce.status = 'Active'
        ORDER BY
            st.last_name,
            st.first_name,
            st.middle_name,
            st.student_number
    ");

    $participantStmt->execute([
        $operationalClassId,
    ]);

    $participants = $participantStmt->fetchAll(PDO::FETCH_ASSOC);

    $tagStmt = $pdo->prepare("
        SELECT
            at.tag_code,
            at.status,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.suffix
        FROM april_tags AS at
        LEFT JOIN students AS st
            ON st.student_id = at.current_student_id
        WHERE at.tag_family = 'tagStandard52h13'
          AND at.status IN ('Assigned', 'Available', 'Retired')
        ORDER BY at.tag_code
    ");

    $tagStmt->execute();

    foreach ($tagStmt->fetchAll(PDO::FETCH_ASSOC) as $tag) {
        $tagCode = (int) $tag['tag_code'];
        $status = (string) $tag['status'];

        if ($status === 'Available') {
            $availableTags[] = $tagCode;
            continue;
        }

        if ($status === 'Retired') {
            $retiredTags[] = $tagCode;
            continue;
        }

        $name = trim(
            (string) ($tag['last_name'] ?? '') . ', ' .
            (string) ($tag['first_name'] ?? '') . ' ' .
            (string) ($tag['middle_name'] ?? '') . ' ' .
            (string) ($tag['suffix'] ?? '')
        );

        $occupiedTags[(string) $tagCode] =
            preg_replace('/\s+/', ' ', $name) ?: $name;
    }
} catch (PDOException $exception) {
    error_log(
        '[APRISM Teacher AprilTags] ' .
        $exception->getMessage()
    );

    $_SESSION['error_message'] =
        'AprilTag assignments could not be loaded.';

    header('Location: ' . APP_URL . '/dashboard/teacher_my_class.php');
    exit;
}
}

$assignedCount = 0;

foreach ($participants as &$participant) {
    $displayName = trim(
        (string) $participant['last_name'] . ', ' .
        (string) $participant['first_name'] . ' ' .
        (string) ($participant['middle_name'] ?? '') . ' ' .
        (string) ($participant['suffix'] ?? '')
    );

    $participant['display_name'] =
        preg_replace('/\s+/', ' ', $displayName) ?: $displayName;

    $participant['is_assigned_here'] =
        $participant['april_tag_id'] !== null
        && (int) (
            $participant['issued_from_operational_class_id']
            ?? 0
        ) === $operationalClassId;

    if ($participant['april_tag_id'] !== null) {
        $assignedCount++;
    }
}

unset($participant);

$awaitingParticipants = array_values(
    array_filter(
        $participants,
        static fn(array $participant): bool =>
        $participant['april_tag_id'] === null
    )
);

$pageTitle = 'Manage AprilTags';
$activePage = 'my_classes';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-april-tags.css';

?>

<!DOCTYPE html>
<html lang="en">

<?php require __DIR__ . '/../includes/components/head.php'; ?>

<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed'])
        ? 'expanded'
        : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <div class="content-wrapper">

            <?php if ($contextSelectionMode): ?>

            <div class="teacher-april-tags-page-header">
                <div class="teacher-april-tags-heading">
                    <a class="teacher-class-workspace-back teacher-april-tags-back"
                        href="<?= APP_URL ?>/dashboard/teacher_my_class.php" aria-label="Back to My Classes"
                        title="Back to My Classes">
                        <i data-lucide="arrow-left"></i>
                    </a>

                    <div class="teacher-april-tags-title-block">
                        <h1 class="teacher-page-title">Manage AprilTags</h1>
                        <p class="teacher-page-subtitle">Choose an Operational Class to continue.</p>
                    </div>
                </div>
            </div>

            <section class="teacher-panel teacher-april-tags-context-selection" aria-labelledby="aprilTagContextTitle">
                <div class="teacher-panel-header">
                    <div>
                        <p class="teacher-eyebrow">Class context required</p>
                        <h2 class="teacher-panel-title" id="aprilTagContextTitle">Select an Operational Class</h2>
                        <p class="teacher-panel-subtitle">AprilTag ownership is managed per class. APRISM has not selected a class because the destination was opened without an Operational Class context.</p>
                    </div>
                </div>

                <?php if ($eligibleClasses === []): ?>
                    <div class="teacher-april-tags-context-empty">
                        <i data-lucide="layers-3" aria-hidden="true"></i>
                        <div>
                            <strong>No eligible Operational Classes are available.</strong>
                            <span>Return to My Classes to review your current teaching assignments.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="teacher-april-tags-context-list">
                        <?php foreach ($eligibleClasses as $eligibleClass): ?>
                            <a class="teacher-april-tags-context-option"
                                href="<?= APP_URL ?>/dashboard/teacher_april_tags.php?operational_class_id=<?= (int) $eligibleClass['operational_class_id'] ?>">
                                <span class="teacher-april-tags-context-option-main">
                                    <strong><?= htmlspecialchars((string) $eligibleClass['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string) $eligibleClass['section_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                                <span class="teacher-april-tags-context-option-meta">
                                    <?= htmlspecialchars((string) $eligibleClass['school_year'], ENT_QUOTES, 'UTF-8') ?>
                                    <span aria-hidden="true">·</span>
                                    <?= htmlspecialchars((string) $eligibleClass['semester'], ENT_QUOTES, 'UTF-8') ?>
                                    <i data-lucide="arrow-right" aria-hidden="true"></i>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php else: ?>

            <div class="teacher-april-tags-page-header">
                <div class="teacher-april-tags-heading">
                    <a class="teacher-class-workspace-back teacher-april-tags-back"
                        href="<?= APP_URL ?>/dashboard/teacher_my_class.php" aria-label="Back to My Classes"
                        title="Back to My Classes">
                        <i data-lucide="arrow-left"></i>
                    </a>

                    <div class="teacher-april-tags-title-block">
                        <h1 class="teacher-page-title">Manage AprilTags</h1>

                        <p class="teacher-page-subtitle teacher-april-tags-context">
                            <?= htmlspecialchars(
                                (string) $classContext['subject_name']
                            ) ?>
                            <span aria-hidden="true">·</span>
                            <?= htmlspecialchars(
                                (string) $classContext['section_name']
                            ) ?>
                            <span aria-hidden="true">·</span>
                            <?= htmlspecialchars(
                                (string) $classContext['semester']
                            ) ?>
                        </p>
                    </div>
                </div>
            </div>

            <section class="teacher-panel teacher-april-tags-summary-strip" aria-label="AprilTag class summary">
                <div class="teacher-april-tags-summary-item teacher-april-tags-summary-class">
                    <span class="teacher-april-tags-summary-label">Class segment</span>
                    <strong><?= htmlspecialchars((string) $classContext['subject_name']) ?></strong>
                    <small><?= htmlspecialchars((string) $classContext['section_name']) ?></small>
                </div>
                <div class="teacher-april-tags-summary-item">
                    <span class="teacher-april-tags-summary-label">Roster students</span>
                    <strong><?= count($participants) ?></strong>
                    <small>Active class list</small>
                </div>
                <div class="teacher-april-tags-summary-item">
                    <span class="teacher-april-tags-summary-label">Assigned</span>
                    <strong><?= $assignedCount ?></strong>
                    <small>Current ownership</small>
                </div>
                <div class="teacher-april-tags-summary-item">
                    <span class="teacher-april-tags-summary-label">Awaiting tag</span>
                    <strong><?= max(0, count($participants) - $assignedCount) ?></strong>
                    <small>Needs issuance</small>
                </div>
                <div class="teacher-april-tags-summary-item teacher-april-tags-summary-family">
                    <span class="teacher-april-tags-summary-label">Tag family</span>
                    <strong>tagStandard52h13</strong>
                    <small>Usable IDs 0–48713</small>
                </div>
                <div class="teacher-april-tags-note-copy">
                    <i data-lucide="info"></i>
                    <span>Existing assigned and retired IDs are excluded. Proposed IDs save after confirmation.</span>
                </div>
            </section>

            <section class="teacher-panel teacher-april-tags-bulk-panel">

                <div class="teacher-panel-header">
                    <div>
                        <h2 class="teacher-panel-title">
                            Issue Tags to Awaiting Students
                        </h2>

                        <p class="teacher-panel-subtitle teacher-april-tags-support-copy">
                            Select awaiting Students, generate unique IDs,
                            then confirm issuance.
                        </p>
                    </div>

                </div>

                <form action="<?= APP_URL ?>/actions/teacher/manage_april_tag.php" method="post"
                    data-april-tag-bulk-form>

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                        generateCsrfToken(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

                    <input type="hidden" name="operation" value="bulk_assign">

                    <input type="hidden" name="operational_class_id" value="<?= $operationalClassId ?>">

                    <?php if ($awaitingParticipants === []): ?>

                        <div class="teacher-april-tags-bulk-empty-state<?= $participants === [] ? ' is-neutral' : '' ?>">
                            <i data-lucide="<?= $participants === [] ? 'info' : 'circle-check' ?>"></i>

                            <div>
                                <strong>
                                    <?= $participants === []
                                        ? 'No active Class List participants.'
                                        : 'All active Students have a tag.' ?>
                                </strong>

                                <span>
                                    <?= $participants === []
                                        ? 'Import and confirm the Class List before assigning AprilTags.'
                                        : 'There are no awaiting Students for this class.' ?>
                                </span>
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="teacher-april-tags-bulk-toolbar">

                            <div class="teacher-april-tags-bulk-toolbar-left">

                                <button class="teacher-april-tags-tool-button" type="button"
                                    data-april-tag-select-all-button>
                                    <i data-lucide="list-checks"></i>
                                    Select All Awaiting
                                </button>

                                <button class="
                                    teacher-primary-btn
                                    teacher-april-tags-autofill-button
                                " type="button" data-april-tag-autofill-button>
                                    <i data-lucide="wand-sparkles"></i>
                                    Auto-Fill Tags
                                </button>

                            </div>

                            <span class="teacher-april-tags-selection-summary" data-april-tag-selection-summary>
                                <?= count($awaitingParticipants) ?>
                                Students selected
                            </span>
                        </div>

                        <div class="
                            teacher-table-wrapper
                            teacher-april-tags-bulk-table-wrapper
                        ">

                            <table class="
                                teacher-table
                                teacher-april-tags-bulk-table
                            ">

                                <thead>
                                    <tr>
                                        <th class="
                                            teacher-april-tags-select-column
                                        ">
                                            <span class="visually-hidden">
                                                Select
                                            </span>
                                        </th>

                                        <th>Student Number</th>
                                        <th>Student</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach (
                                        $awaitingParticipants
                                        as $participant
                                    ): ?>

                                        <tr>

                                            <td class="teacher-april-tags-select-column">
                                                <label class="teacher-april-tags-checkbox-target">
                                                <input type="checkbox" name="selected_student_class_enrollment_ids[]" value="<?= (int) $participant[
                                                    'student_class_enrollment_id'
                                                ] ?>" data-april-tag-row-checkbox checked aria-label="Select <?= htmlspecialchars((string) $participant['display_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                </label>
                                            </td>

                                            <td class="teacher-april-tags-number">
                                                <?= htmlspecialchars(
                                                    (string) $participant[
                                                        'student_number'
                                                    ]
                                                ) ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        (string) $participant[
                                                            'display_name'
                                                        ]
                                                    ) ?>
                                                </strong>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>
                        </div>

                        <details class="teacher-april-tags-manual-fallback">
                            <summary>
                                <i data-lucide="keyboard"></i>
                                Manual or scanner input fallback
                            </summary>

                            <div class="
                                teacher-april-tags-manual-fallback-body
                            ">
                                <label for="aprilTagBatchInput" class="
                                    teacher-april-tags-bulk-input-label
                                ">
                                    Physical tag IDs

                                    <span>
                                        one per line, space,
                                        comma, or semicolon
                                    </span>
                                </label>

                                <textarea id="aprilTagBatchInput" name="tag_codes_raw" rows="3"
                                    class="teacher-april-tags-bulk-input" placeholder="Example: 2, 3, 4"
                                    data-april-tag-batch-input></textarea>

                                <p class="teacher-april-tags-bulk-help">
                                    Use this only for exceptional
                                    manual input or future
                                    camera-scanner input.
                                </p>
                            </div>
                        </details>

                        <div class="teacher-april-tags-bulk-preview" data-april-tag-bulk-preview aria-live="polite">

                            <div class="
                                teacher-april-tags-bulk-preview-empty
                            ">
                                Select Students, then choose
                                <strong>Auto-Fill Tags</strong>
                                to generate the verified marker preview.
                            </div>
                        </div>

                        <div class="teacher-april-tags-bulk-actions">

                            <span class="teacher-april-tags-secondary" data-april-tag-bulk-status>
                                Review the server-verified mapping
                                before issuing.
                            </span>

                            <button class="
                                teacher-primary-btn
                                teacher-april-tags-confirm-button
                            " type="submit" data-april-tag-bulk-submit disabled>
                                <i data-lucide="badge-check"></i>
                                Confirm &amp; Issue Tags
                            </button>
                        </div>

                    <?php endif; ?>

                </form>
            </section>

            <section class="teacher-panel teacher-april-tags-panel">

                <div class="teacher-panel-header">
                    <div>
                        <h2 class="teacher-panel-title teacher-april-tags-section-title">
                            Active Class List Participants
                        </h2>

                        <p class="teacher-panel-subtitle teacher-april-tags-support-copy">
                            Awaiting Students use bulk issuance. Existing
                            assignments can be replaced here.
                        </p>
                    </div>

                    <?php if ($assignedCount > 0): ?>
                        <div class="teacher-april-tags-bulk-distribution-actions">
                            <a class="teacher-primary-btn teacher-april-tags-bulk-action"
                                href="<?= APP_URL ?>/actions/teacher/distribute_april_tag.php?operational_class_id=<?= $operationalClassId ?>&amp;mode=bulk_download">
                                <i data-lucide="download"></i>
                                Download All
                            </a>

                            <a class="teacher-april-tags-distribution-link teacher-april-tags-bulk-secondary"
                                href="<?= APP_URL ?>/actions/teacher/distribute_april_tag.php?operational_class_id=<?= $operationalClassId ?>&amp;mode=bulk_print"
                                target="_blank" rel="noopener">
                                <i data-lucide="printer"></i>
                                Print All
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="teacher-table-wrapper teacher-april-tags-table-wrapper">

                    <table class="
                        teacher-table
                        teacher-april-tags-table
                    ">

                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Current Tag</th>
                                <th>Assignment</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if ($participants === []): ?>

                                <tr>
                                    <td colspan="3">

                                        <div class="
                                        teacher-empty-state
                                        teacher-april-tags-empty-state
                                    ">
                                            <i data-lucide="users-round"></i>

                                            <h3 class="teacher-empty-state-title">
                                                No Active Class List Participants
                                            </h3>

                                            <p class="teacher-empty-state-text">
                                                Import and confirm the
                                                Class List before assigning
                                                AprilTags.
                                            </p>
                                        </div>

                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach (
                                    $participants
                                    as $participant
                                ): ?>

                                    <tr>

                                        <td class="teacher-april-tags-student-cell">
                                            <strong class="teacher-april-tags-student-name">
                                                <?= htmlspecialchars(
                                                    (string) $participant[
                                                        'display_name'
                                                    ]
                                                ) ?>
                                            </strong>
                                            <span class="teacher-april-tags-number">
                                                <?= htmlspecialchars(
                                                    (string) $participant[
                                                        'student_number'
                                                    ]
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>

                                            <?php if (
                                                $participant['april_tag_id'] !== null
                                            ): ?>

                                                <span class="
                                                teacher-badge
                                                teacher-badge-success
                                                teacher-april-tags-tag-badge
                                            ">
                                                    #<?= (int) $participant[
                                                        'tag_code'
                                                    ] ?>
                                                </span>

                                                <span class="teacher-april-tags-family">
                                                    tagStandard52h13
                                                </span>

                                            <?php else: ?>

                                                <span class="
                                                teacher-badge
                                                teacher-badge-info
                                                teacher-april-tags-tag-badge
                                            ">
                                                    Awaiting tag
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td class="teacher-april-tags-assignment-cell">

                                            <?php if (
                                                $participant['april_tag_id'] === null
                                            ): ?>

                                                <span class="
                                                teacher-april-tags-awaiting-action
                                            ">
                                                    <i data-lucide="layers-2"></i>
                                                    Use bulk issuance above
                                                </span>

                                            <?php else: ?>

                                                <div class="teacher-april-tags-assigned-actions">

                                                    <?php if (
                                                        $participant['is_assigned_here']
                                                    ): ?>

                                                        <form class="
                                                teacher-april-tags-assignment-form
                                            " action="<?= APP_URL ?>/actions/teacher/manage_april_tag.php"
                                                            method="post">

                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                                generateCsrfToken(),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>">

                                                            <input type="hidden" name="operation" value="replace">

                                                            <input type="hidden" name="operational_class_id"
                                                                value="<?= $operationalClassId ?>">

                                                            <input type="hidden" name="student_class_enrollment_id" value="<?= (int) $participant[
                                                                'student_class_enrollment_id'
                                                            ] ?>">

                                                            <input type="hidden" name="april_tag_id" value="<?= (int) $participant[
                                                                'april_tag_id'
                                                            ] ?>">

                                                            <label class="visually-hidden" for="replacementTagCode<?= (int) $participant[
                                                                'student_class_enrollment_id'
                                                            ] ?>">
                                                                Replacement AprilTag ID
                                                            </label>

                                                            <input id="replacementTagCode<?= (int) $participant[
                                                                'student_class_enrollment_id'
                                                            ] ?>" name="tag_code" type="number" min="0" max="48713"
                                                                step="1" placeholder="Replacement ID" required>

                                                            <button class="teacher-primary-btn" type="submit">
                                                                <i data-lucide="repeat-2"></i>
                                                                Replace
                                                            </button>
                                                        </form>

                                                    <?php else: ?>

                                                        <span class="
                                                teacher-april-tags-secondary
                                            ">
                                                            Assigned through another
                                                            Operational Class
                                                        </span>

                                                    <?php endif; ?>

                                                    <div class="teacher-april-tags-distribution-actions"
                                                        aria-label="AprilTag actions">
                                                        <a class="teacher-april-tags-distribution-link" href="<?= APP_URL ?>/actions/teacher/distribute_april_tag.php?operational_class_id=<?= $operationalClassId ?>&amp;student_class_enrollment_id=<?= (int) $participant[
                                                                'student_class_enrollment_id'
                                                            ] ?>&amp;mode=view" target="_blank" rel="noopener">
                                                            <i data-lucide="eye"></i>
                                                            View
                                                        </a>

                                                        <a class="teacher-april-tags-distribution-link" href="<?= APP_URL ?>/actions/teacher/distribute_april_tag.php?operational_class_id=<?= $operationalClassId ?>&amp;student_class_enrollment_id=<?= (int) $participant[
                                                                'student_class_enrollment_id'
                                                            ] ?>&amp;mode=download">
                                                            <i data-lucide="download"></i>
                                                            Download
                                                        </a>

                                                        <a class="teacher-april-tags-distribution-link" href="<?= APP_URL ?>/actions/teacher/distribute_april_tag.php?operational_class_id=<?= $operationalClassId ?>&amp;student_class_enrollment_id=<?= (int) $participant[
                                                                'student_class_enrollment_id'
                                                            ] ?>&amp;mode=print" target="_blank" rel="noopener">
                                                            <i data-lucide="printer"></i>
                                                            Print
                                                        </a>
                                                    </div>

                                                </div>

                                            <?php endif; ?>

                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>
            </section>

        </div>

            <?php endif; ?>
    </main>

    <?php
    require __DIR__ . '/../includes/components/logout_modal.php';
    ?>

    <?php
    require __DIR__ . '/../includes/components/footer.php';
    ?>

    <div class="toast-container-custom" id="toastContainer"></div>

    <div class="teacher-april-tags-confirm-modal" data-april-tag-confirm-modal hidden role="dialog" aria-modal="true"
        aria-labelledby="aprilTagConfirmTitle">

        <div class="teacher-april-tags-confirm-backdrop" data-april-tag-confirm-cancel></div>

        <div class="teacher-april-tags-confirm-card">

            <div class="teacher-april-tags-confirm-icon">
                <i data-lucide="shield-check"></i>
            </div>

            <h2 id="aprilTagConfirmTitle" data-april-tag-confirm-title>
                Confirm action
            </h2>

            <p data-april-tag-confirm-message></p>

            <div class="teacher-april-tags-confirm-actions">

                <button type="button" class="teacher-april-tags-confirm-cancel-button" data-april-tag-confirm-cancel>
                    Cancel
                </button>

                <button type="button" class="
                    teacher-primary-btn
                    teacher-april-tags-confirm-submit
                " data-april-tag-confirm-submit>
                    Confirm
                </button>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toastContainer =
                document.getElementById('toastContainer');

            const escapeHtml = (value) =>
                String(value).replace(
                    /[&<>"']/g,
                    (character) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    }[character])
                );

            function showToast(
                title,
                text,
                type = 'success'
            ) {
                if (!toastContainer) {
                    return;
                }

                const toast =
                    document.createElement('div');

                toast.className = 'toast-custom';

                let icon = 'check';

                if (type === 'warning') {
                    icon = 'alert-circle';
                }

                if (type === 'error') {
                    icon = 'circle-alert';
                }

                if (type === 'info') {
                    icon = 'info';
                }

                toast.innerHTML = `
            <div class="toast-icon ${type}">
                <i data-lucide="${icon}"></i>
            </div>

            <div class="toast-content">
                <h5 class="toast-title">
                    ${escapeHtml(title)}
                </h5>

                <p class="toast-text">
                    ${escapeHtml(text)}
                </p>
            </div>
        `;

                toastContainer.appendChild(toast);

                if (window.lucide) {
                    window.lucide.createIcons();
                }

                setTimeout(
                    () => toast.classList.add('show'),
                    10
                );

                setTimeout(() => {
                    toast.classList.remove('show');

                    setTimeout(
                        () => toast.remove(),
                        300
                    );
                }, 4500);
            }

            <?php if (!empty($flash['success'])): ?>

                showToast(
                    'Success',
                    <?= json_encode(
                        (string) $flash['success'],
                        JSON_HEX_TAG
                        | JSON_HEX_AMP
                        | JSON_HEX_APOS
                        | JSON_HEX_QUOT
                    ) ?>,
                    'success'
                );

            <?php endif; ?>

            <?php if (!empty($flash['warning'])): ?>

                showToast(
                    'Warning',
                    <?= json_encode(
                        (string) $flash['warning'],
                        JSON_HEX_TAG
                        | JSON_HEX_AMP
                        | JSON_HEX_APOS
                        | JSON_HEX_QUOT
                    ) ?>,
                    'warning'
                );

            <?php endif; ?>

            <?php if (!empty($flash['error'])): ?>

                showToast(
                    'Error',
                    <?= json_encode(
                        (string) $flash['error'],
                        JSON_HEX_TAG
                        | JSON_HEX_AMP
                        | JSON_HEX_APOS
                        | JSON_HEX_QUOT
                    ) ?>,
                    'error'
                );

            <?php endif; ?>

            const operationalClassId =
                <?= json_encode($operationalClassId) ?>;

            const previewEndpoint =
                <?= json_encode(
                    APP_URL .
                    '/actions/teacher/preview_april_tags.php',
                    JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ) ?>;

            const awaitingParticipants =
                <?= json_encode(
                    array_map(
                        static fn(array $participant): array => [
                            'id' => (int) $participant[
                                'student_class_enrollment_id'
                            ],
                            'name' => (string) $participant[
                                'display_name'
                            ],
                        ],
                        $awaitingParticipants
                    ),
                    JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ) ?>;

            const occupiedTags =
                <?= json_encode(
                    $occupiedTags,
                    JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ) ?>;

            const retiredTags =
                <?= json_encode(
                    array_values(
                        array_unique($retiredTags)
                    ),
                    JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ) ?>;

            const availableTags =
                <?= json_encode(
                    array_values(
                        array_unique($availableTags)
                    ),
                    JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
                ) ?>;

            const selectAllButton =
                document.querySelector(
                    '[data-april-tag-select-all-button]'
                );

            const autoFillButton =
                document.querySelector(
                    '[data-april-tag-autofill-button]'
                );

            const rowCheckboxes = [
                ...document.querySelectorAll(
                    '[data-april-tag-row-checkbox]'
                )
            ];

            const batchInput =
                document.querySelector(
                    '[data-april-tag-batch-input]'
                );

            const preview =
                document.querySelector(
                    '[data-april-tag-bulk-preview]'
                );

            const submitButton =
                document.querySelector(
                    '[data-april-tag-bulk-submit]'
                );

            const selectionSummary =
                document.querySelector(
                    '[data-april-tag-selection-summary]'
                );

            const bulkStatus =
                document.querySelector(
                    '[data-april-tag-bulk-status]'
                );

            const bulkForm =
                document.querySelector(
                    '[data-april-tag-bulk-form]'
                );

            const confirmationModal =
                document.querySelector(
                    '[data-april-tag-confirm-modal]'
                );

            const confirmationTitle =
                document.querySelector(
                    '[data-april-tag-confirm-title]'
                );

            const confirmationMessage =
                document.querySelector(
                    '[data-april-tag-confirm-message]'
                );

            const confirmationSubmit =
                document.querySelector(
                    '[data-april-tag-confirm-submit]'
                );

            const confirmationCancelButtons = [
                ...document.querySelectorAll(
                    '[data-april-tag-confirm-cancel]'
                )
            ];

            if (
                !bulkForm
                || !preview
                || !submitButton
            ) {
                return;
            }

            const csrfInput =
                bulkForm.querySelector(
                    'input[name="csrf_token"]'
                );

            const occupiedSet =
                new Set(
                    Object.keys(occupiedTags).map(
                        (value) =>
                            String(Number(value))
                    )
                );

            const retiredSet =
                new Set(
                    retiredTags.map(
                        (value) =>
                            String(Number(value))
                    )
                );

            const availableSet =
                new Set(
                    availableTags.map(
                        (value) =>
                            String(Number(value))
                    )
                );

            let confirmationResolver = null;

            let successfulPreviewKey = null;

            let activePreviewController = null;

            let previewRequestSequence = 0;

            let previewTimer = null;

            const closeConfirmationModal = (
                confirmed
            ) => {
                if (confirmationModal) {
                    confirmationModal.hidden = true;
                }

                if (confirmationResolver) {
                    const resolver =
                        confirmationResolver;

                    confirmationResolver = null;

                    resolver(confirmed);
                }
            };

            const requestConfirmation = (
                title,
                message,
                label
            ) => {
                return new Promise((resolve) => {
                    confirmationResolver = resolve;

                    confirmationTitle.textContent =
                        title;

                    confirmationMessage.textContent =
                        message;

                    confirmationSubmit.textContent =
                        label;

                    confirmationModal.hidden = false;

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }

                    confirmationSubmit.focus();
                });
            };

            confirmationCancelButtons.forEach(
                (button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            closeConfirmationModal(
                                false
                            );
                        }
                    );
                }
            );

            confirmationSubmit.addEventListener(
                'click',
                () => {
                    closeConfirmationModal(true);
                }
            );

            document.addEventListener(
                'keydown',
                (event) => {
                    if (
                        event.key === 'Escape'
                        && confirmationModal
                        && !confirmationModal.hidden
                    ) {
                        closeConfirmationModal(
                            false
                        );
                    }
                }
            );

            const selectedParticipants = () =>
                awaitingParticipants.filter(
                    (participant) => {
                        const checkbox =
                            document.querySelector(
                                `[data-april-tag-row-checkbox][value="${participant.id}"]`
                            );

                        return (
                            checkbox
                            && checkbox.checked
                        );
                    }
                );

            const readCodes = () =>
                batchInput
                    ? batchInput.value
                        .trim()
                        .split(/[\s,;]+/)
                        .filter(Boolean)
                    : [];

            const findAutoFillCodes = (count) => {
                const codes = [];
                const used = new Set();

                const addIfUsable = (value) => {
                    const numericValue =
                        Number(value);

                    if (
                        !Number.isInteger(
                            numericValue
                        )
                        || numericValue < 0
                        || numericValue > 48713
                    ) {
                        return;
                    }

                    const code =
                        String(numericValue);

                    if (
                        used.has(code)
                        || occupiedSet.has(code)
                        || retiredSet.has(code)
                    ) {
                        return;
                    }

                    used.add(code);

                    codes.push(
                        numericValue
                    );
                };

                [...availableTags]
                    .sort(
                        (left, right) =>
                            Number(left)
                            - Number(right)
                    )
                    .forEach((value) => {
                        if (
                            codes.length < count
                        ) {
                            addIfUsable(value);
                        }
                    });

                for (
                    let candidate = 0;
                    candidate <= 48713
                    && codes.length < count;
                    candidate++
                ) {
                    if (
                        !availableSet.has(
                            String(candidate)
                        )
                    ) {
                        addIfUsable(
                            candidate
                        );
                    }
                }

                return codes;
            };

            const buildCurrentProposal = () => {
                const selected =
                    selectedParticipants();

                const values =
                    readCodes();

                if (
                    selected.length === 0
                    || values.length
                    !== selected.length
                ) {
                    return {
                        valid: false,
                        selected,
                        proposals: [],
                        message:
                            selected.length === 0
                                ? 'Select at least one awaiting Student.'
                                : 'Provide exactly one AprilTag ID for every selected Student.',
                    };
                }

                const proposals = [];

                const seenTags =
                    new Set();

                for (
                    let index = 0;
                    index < selected.length;
                    index++
                ) {
                    const participant =
                        selected[index];

                    const rawValue =
                        values[index];

                    if (
                        !/^\d+$/.test(
                            rawValue
                        )
                    ) {
                        return {
                            valid: false,
                            selected,
                            proposals: [],
                            message:
                                'Every AprilTag ID must be an integer from 0 to 48713.',
                        };
                    }

                    const tagCode =
                        Number(rawValue);

                    if (
                        !Number.isInteger(
                            tagCode
                        )
                        || tagCode < 0
                        || tagCode > 48713
                    ) {
                        return {
                            valid: false,
                            selected,
                            proposals: [],
                            message:
                                'Every AprilTag ID must be from 0 to 48713.',
                        };
                    }

                    if (
                        seenTags.has(tagCode)
                    ) {
                        return {
                            valid: false,
                            selected,
                            proposals: [],
                            message:
                                'The same AprilTag ID cannot be used more than once in the proposal.',
                        };
                    }

                    if (
                        occupiedSet.has(
                            String(tagCode)
                        )
                    ) {
                        return {
                            valid: false,
                            selected,
                            proposals: [],
                            message:
                                `AprilTag #${tagCode} is already assigned.`,
                        };
                    }

                    if (
                        retiredSet.has(
                            String(tagCode)
                        )
                    ) {
                        return {
                            valid: false,
                            selected,
                            proposals: [],
                            message:
                                `AprilTag #${tagCode} is retired and unavailable.`,
                        };
                    }

                    seenTags.add(tagCode);

                    proposals.push({
                        student_class_enrollment_id:
                            Number(
                                participant.id
                            ),
                        tag_code:
                            tagCode,
                    });
                }

                return {
                    valid: true,
                    selected,
                    proposals,
                    message: null,
                };
            };

            const proposalKey = (
                proposals
            ) => {
                const pairs =
                    proposals.map(
                        (proposal) => [
                            Number(
                                proposal
                                    .student_class_enrollment_id
                            ),
                            Number(
                                proposal.tag_code
                            ),
                        ]
                    );

                return JSON.stringify({
                    operational_class_id:
                        Number(
                            operationalClassId
                        ),
                    proposals: pairs,
                });
            };

            const invalidatePreview = () => {
                successfulPreviewKey = null;

                submitButton.disabled = true;

                if (
                    activePreviewController
                ) {
                    activePreviewController
                        .abort();

                    activePreviewController =
                        null;
                }
            };

            const updateSelectionSummary =
                () => {
                    if (!selectionSummary) {
                        return;
                    }

                    const count =
                        selectedParticipants()
                            .length;

                    selectionSummary.textContent =
                        `${count} Student${count === 1 ? '' : 's'} selected`;
                };

            const renderEmptyPreview = (
                message
            ) => {
                preview.innerHTML = `
            <div
                class="
                    teacher-april-tags-bulk-preview-empty
                "
            >
                ${escapeHtml(message)}
            </div>
        `;
            };

            const renderValidationFailure = (
                message
            ) => {
                preview.innerHTML = `
            <div
                class="
                    teacher-april-tags-bulk-preview-empty
                "
            >
                <i
                    data-lucide="circle-alert"
                ></i>

                <span>
                    ${escapeHtml(message)}
                </span>
            </div>
        `;

                if (bulkStatus) {
                    bulkStatus.textContent =
                        'Correct the proposed mapping before confirming.';
                }

                if (window.lucide) {
                    window.lucide
                        .createIcons();
                }
            };

            const renderLoadingPreview = (
                count
            ) => {
                preview.innerHTML = `
            <div
                class="
                    teacher-april-tags-bulk-preview-empty
                "
            >
                <i
                    data-lucide="loader-circle"
                ></i>

                <span>
                    Generating ${count}
                    verified AprilTag
                    preview${count === 1 ? '' : 's'}…
                </span>
            </div>
        `;

                if (bulkStatus) {
                    bulkStatus.textContent =
                        'APRISM is validating the proposed tags.';
                }

                if (window.lucide) {
                    window.lucide
                        .createIcons();
                }
            };

            const matrixHtml = (
                matrix,
                tagCode
            ) => {
                if (
                    !Array.isArray(matrix)
                    || matrix.length !== 10
                ) {
                    return '';
                }

                let cells = '';

                for (const row of matrix) {
                    if (
                        !Array.isArray(row)
                        || row.length !== 10
                    ) {
                        return '';
                    }

                    for (const cell of row) {
                        if (
                            cell !== 0
                            && cell !== 1
                        ) {
                            return '';
                        }

                        cells += `
                    <span
                        aria-hidden="true"
                        style="
                            display:block;
                            width:100%;
                            height:100%;
                            background:${cell === 1
                                ? '#ffffff'
                                : '#000000'
                            };
                        "
                    ></span>
                `;
                    }
                }

                return `
            <div
                role="img"
                aria-label="AprilTag ${escapeHtml(
                    tagCode
                )} preview"
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(10, 1fr);
                    grid-template-rows:
                        repeat(10, 1fr);
                    width:88px;
                    height:88px;
                    flex:0 0 88px;
                    background:#000000;
                    overflow:hidden;
                "
            >
                ${cells}
            </div>
        `;
            };

            const renderServerPreview = (
                responseProposals,
                expectedKey
            ) => {
                const responseKey =
                    proposalKey(
                        responseProposals.map(
                            (proposal) => ({
                                student_class_enrollment_id:
                                    Number(
                                        proposal
                                            .student_class_enrollment_id
                                    ),
                                tag_code:
                                    Number(
                                        proposal
                                            .tag_code
                                    ),
                            })
                        )
                    );

                if (
                    responseKey
                    !== expectedKey
                ) {
                    throw new Error(
                        'The preview response no longer matches the current proposal.'
                    );
                }

                let html = `
            <div
                class="
                    teacher-april-tags-preview-header
                "
            >
                <div>
                    <strong>
                        Verified Student ↔ AprilTag preview
                    </strong>

                    <span>
                        These markers were generated by
                        the APRISM PHP renderer.
                        Nothing is saved until confirmation.
                    </span>
                </div>

                <span
                    class="
                        teacher-april-tags-preview-count
                    "
                >
                    ${responseProposals.length}
                    verified
                </span>
            </div>

            <div
                class="
                    teacher-april-tags-preview-list
                "
            >
        `;

                for (
                    const proposal
                    of responseProposals
                ) {
                    const marker =
                        matrixHtml(
                            proposal.matrix,
                            proposal.tag_code
                        );

                    if (
                        marker === ''
                    ) {
                        throw new Error(
                            `AprilTag #${proposal.tag_code} returned an invalid matrix.`
                        );
                    }

                    html += `
                <div
                    class="
                        teacher-april-tags-preview-row
                        is-ready
                    "
                    style="
                        display:grid;
                        grid-template-columns:
                            minmax(0, 1fr)
                            auto
                            auto;
                        align-items:center;
                        gap:1rem;
                    "
                >

                    <div
                        class="
                            teacher-april-tags-preview-student
                        "
                    >
                        <strong>
                            ${escapeHtml(
                        proposal
                            .student_name
                    )}
                        </strong>

                        <div
                            class="
                                teacher-april-tags-secondary
                            "
                        >
                            ${escapeHtml(
                        proposal
                            .student_number
                    )}
                        </div>
                    </div>

                    ${marker}

                    <div>
                        <strong
                            class="
                                teacher-april-tags-preview-code
                            "
                        >
                            #${Number(
                        proposal
                            .tag_code
                    )}
                        </strong>

                        <span
                            class="
                                teacher-april-tags-preview-status
                            "
                        >
                            <i
                                data-lucide="check-circle-2"
                            ></i>

                            Server verified
                        </span>
                    </div>
                </div>
            `;
                }

                html += '</div>';

                preview.innerHTML = html;

                if (bulkStatus) {
                    bulkStatus.textContent =
                        'The current proposal has been verified by the APRISM renderer.';
                }

                if (window.lucide) {
                    window.lucide
                        .createIcons();
                }
            };

            const runServerPreview =
                async () => {
                    invalidatePreview();

                    updateSelectionSummary();

                    const current =
                        buildCurrentProposal();

                    if (!current.valid) {
                        renderValidationFailure(
                            current.message
                            ?? 'The proposal is incomplete.'
                        );

                        return;
                    }

                    const currentKey =
                        proposalKey(
                            current.proposals
                        );

                    const requestSequence =
                        ++previewRequestSequence;

                    const controller =
                        new AbortController();

                    activePreviewController =
                        controller;

                    renderLoadingPreview(
                        current.proposals.length
                    );

                    try {
                        const response =
                            await fetch(
                                previewEndpoint,
                                {
                                    method: 'POST',

                                    credentials:
                                        'same-origin',

                                    headers: {
                                        'Content-Type':
                                            'application/json',
                                    },

                                    body:
                                        JSON.stringify({
                                            csrf_token:
                                                csrfInput?.value
                                                ?? '',

                                            operational_class_id:
                                                operationalClassId,

                                            proposals:
                                                current
                                                    .proposals,
                                        }),

                                    signal:
                                        controller
                                            .signal,
                                }
                            );

                        let payload = null;

                        try {
                            payload =
                                await response
                                    .json();
                        } catch (error) {
                            throw new Error(
                                'APRISM returned an invalid preview response.'
                            );
                        }

                        if (
                            requestSequence
                            !== previewRequestSequence
                        ) {
                            return;
                        }

                        const latest =
                            buildCurrentProposal();

                        if (
                            !latest.valid
                            || proposalKey(
                                latest.proposals
                            ) !== currentKey
                        ) {
                            return;
                        }

                        if (
                            !response.ok
                            || payload?.ok
                            !== true
                        ) {
                            const message =
                                payload
                                    ?.error
                                    ?.message
                                ?? 'The AprilTag preview could not be generated.';

                            const details =
                                Array.isArray(
                                    payload
                                        ?.error
                                        ?.details
                                )
                                    ? payload
                                        .error
                                        .details
                                    : [];

                            let detailMessage = '';

                            if (
                                details.length > 0
                            ) {
                                const firstReason =
                                    details[0]
                                        ?.reason;

                                if (
                                    firstReason
                                ) {
                                    detailMessage =
                                        ` ${firstReason}`;
                                }
                            }

                            renderValidationFailure(
                                message
                                + detailMessage
                            );

                            showToast(
                                'Preview unavailable',
                                message,
                                response.status
                                    >= 500
                                    ? 'error'
                                    : 'warning'
                            );

                            return;
                        }

                        const responseProposals =
                            payload
                                ?.data
                                ?.proposals;

                        if (
                            payload
                                ?.data
                                ?.family
                            !== 'tagStandard52h13'
                            || Number(
                                payload
                                    ?.data
                                    ?.matrix_width
                            ) !== 10
                            || Number(
                                payload
                                    ?.data
                                    ?.matrix_height
                            ) !== 10
                            || !Array.isArray(
                                responseProposals
                            )
                        ) {
                            throw new Error(
                                'APRISM returned an unexpected AprilTag preview response.'
                            );
                        }

                        renderServerPreview(
                            responseProposals,
                            currentKey
                        );

                        const afterRender =
                            buildCurrentProposal();

                        if (
                            afterRender.valid
                            && proposalKey(
                                afterRender
                                    .proposals
                            ) === currentKey
                        ) {
                            successfulPreviewKey =
                                currentKey;

                            submitButton.disabled =
                                false;
                        }
                    } catch (error) {
                        if (
                            error instanceof DOMException
                            && error.name === 'AbortError'
                        ) {
                            return;
                        }

                        if (
                            requestSequence
                            !== previewRequestSequence
                        ) {
                            return;
                        }

                        const latest =
                            buildCurrentProposal();

                        if (
                            !latest.valid
                            || proposalKey(
                                latest.proposals
                            ) !== currentKey
                        ) {
                            return;
                        }

                        successfulPreviewKey =
                            null;

                        submitButton.disabled =
                            true;

                        const message =
                            error instanceof Error
                                ? error.message
                                : 'The AprilTag preview could not be generated.';

                        renderValidationFailure(
                            message
                        );

                        showToast(
                            'Preview error',
                            message,
                            'error'
                        );
                    } finally {
                        if (
                            requestSequence
                            === previewRequestSequence
                        ) {
                            activePreviewController =
                                null;
                        }
                    }
                };

            const scheduleServerPreview =
                () => {
                    invalidatePreview();

                    updateSelectionSummary();

                    if (
                        previewTimer !== null
                    ) {
                        window.clearTimeout(
                            previewTimer
                        );
                    }

                    const current =
                        buildCurrentProposal();

                    if (!current.valid) {
                        renderValidationFailure(
                            current.message
                            ?? 'The proposal is incomplete.'
                        );

                        return;
                    }

                    renderEmptyPreview(
                        'Waiting for APRISM to verify the current proposal…'
                    );

                    previewTimer =
                        window.setTimeout(
                            () => {
                                previewTimer = null;

                                runServerPreview();
                            },
                            180
                        );
                };

            selectAllButton?.addEventListener(
                'click',
                () => {
                    rowCheckboxes.forEach(
                        (checkbox) => {
                            checkbox.checked =
                                true;
                        }
                    );

                    scheduleServerPreview();
                }
            );

            autoFillButton?.addEventListener(
                'click',
                () => {
                    const selected =
                        selectedParticipants();

                    if (
                        selected.length === 0
                    ) {
                        showToast(
                            'Warning',
                            'Select at least one awaiting Student first.',
                            'warning'
                        );

                        return;
                    }

                    const codes =
                        findAutoFillCodes(
                            selected.length
                        );

                    if (
                        codes.length
                        !== selected.length
                    ) {
                        invalidatePreview();

                        if (batchInput) {
                            batchInput.value =
                                '';
                        }

                        renderValidationFailure(
                            'There are not enough usable AprilTag IDs available.'
                        );

                        return;
                    }

                    if (batchInput) {
                        batchInput.value =
                            codes.join('\n');
                    }

                    scheduleServerPreview();
                }
            );

            rowCheckboxes.forEach(
                (checkbox) => {
                    checkbox.addEventListener(
                        'change',
                        scheduleServerPreview
                    );
                }
            );

            batchInput?.addEventListener(
                'input',
                scheduleServerPreview
            );

            bulkForm.addEventListener(
                'submit',
                async (event) => {
                    event.preventDefault();

                    const beforeConfirmation =
                        buildCurrentProposal();

                    if (
                        !beforeConfirmation.valid
                    ) {
                        invalidatePreview();

                        renderValidationFailure(
                            beforeConfirmation
                                .message
                            ?? 'The current proposal is invalid.'
                        );

                        return;
                    }

                    const beforeKey =
                        proposalKey(
                            beforeConfirmation
                                .proposals
                        );

                    if (
                        successfulPreviewKey
                        === null
                        || successfulPreviewKey
                        !== beforeKey
                    ) {
                        submitButton.disabled =
                            true;

                        showToast(
                            'Preview required',
                            'Generate and review the latest server-verified AprilTag preview before confirming.',
                            'warning'
                        );

                        scheduleServerPreview();

                        return;
                    }

                    const selectedCount =
                        beforeConfirmation
                            .proposals
                            .length;

                    const confirmed =
                        await requestConfirmation(
                            'Confirm AprilTag issuance',
                            `APRISM will issue the server-verified AprilTag mapping to ${selectedCount} selected Student${selectedCount === 1 ? '' : 's'}.`,
                            'Confirm & Issue'
                        );

                    if (!confirmed) {
                        return;
                    }

                    const afterConfirmation =
                        buildCurrentProposal();

                    if (
                        !afterConfirmation.valid
                    ) {
                        invalidatePreview();

                        showToast(
                            'Preview changed',
                            'The proposed mapping changed before confirmation. Generate the preview again.',
                            'warning'
                        );

                        scheduleServerPreview();

                        return;
                    }

                    const afterKey =
                        proposalKey(
                            afterConfirmation
                                .proposals
                        );

                    if (
                        successfulPreviewKey
                        === null
                        || afterKey
                        !== beforeKey
                        || successfulPreviewKey
                        !== afterKey
                    ) {
                        invalidatePreview();

                        showToast(
                            'Preview changed',
                            'The proposed mapping changed before confirmation. Generate the preview again.',
                            'warning'
                        );

                        scheduleServerPreview();

                        return;
                    }

                    bulkForm.submit();
                }
            );

            updateSelectionSummary();

            submitButton.disabled = true;

            renderEmptyPreview(
                'Select Students, then choose Auto-Fill Tags to generate the verified marker preview.'
            );
        });
    </script>

</body>

</html>
