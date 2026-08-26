<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
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

if ($operationalClassId === false) {
    $_SESSION['error_message'] = 'Select one of your Operational Classes first.';
    header('Location: ' . APP_URL . '/dashboard/teacher_my_class.php');
    exit;
}

$operationalClassId = (int) $operationalClassId;
$classContext = null;
$participants = [];

try {
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
    $classStmt->execute([$operationalClassId, (int) $_SESSION['user_id']]);
    $classContext = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($classContext === null) {
        $_SESSION['error_message'] =
            'The selected Operational Class is unavailable or you do not have permission to manage it.';
        header('Location: ' . APP_URL . '/dashboard/teacher_my_class.php');
        exit;
    }

    $participantsStmt = $pdo->prepare("
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
            assignment.issued_from_operational_class_id,
            (
                SELECT COUNT(*)
                FROM student_class_enrollments AS term_sce
                INNER JOIN operational_classes AS term_oc
                    ON term_oc.operational_class_id = term_sce.operational_class_id
                WHERE term_sce.student_id = sce.student_id
                  AND term_sce.status = 'Active'
                  AND term_oc.status = 'Active'
                  AND term_oc.school_year = ?
                  AND term_oc.semester = ?
            ) AS active_term_class_count
        FROM student_class_enrollments AS sce
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        LEFT JOIN april_tags AS at
            ON at.current_student_id = sce.student_id
           AND at.current_school_year_id = ?
           AND at.current_semester = ?
           AND at.status = 'Assigned'
        LEFT JOIN april_tag_assignments AS assignment
            ON assignment.april_tag_id = at.april_tag_id
           AND assignment.status = 'Assigned'
           AND assignment.reclaimed_at IS NULL
        WHERE sce.operational_class_id = ?
          AND sce.status = 'Active'
        ORDER BY st.last_name, st.first_name, st.middle_name, st.student_number
    ");
    $participantsStmt->execute([
        (int) $classContext['school_year_id'],
        (string) $classContext['semester'],
        (string) $classContext['school_year'],
        (string) $classContext['semester'],
        $operationalClassId,
    ]);
    $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('[APRISM Teacher AprilTags] ' . $exception->getMessage());
    $_SESSION['error_message'] = 'AprilTag assignments could not be loaded. Run the AprilTag schema migration, then try again.';
    header('Location: ' . APP_URL . '/dashboard/teacher_my_class.php');
    exit;
}

$assignedCount = 0;

foreach ($participants as &$participant) {
    $displayName = trim(
        (string) $participant['last_name'] . ', ' .
        (string) $participant['first_name'] . ' ' .
        (string) ($participant['middle_name'] ?? '') . ' ' .
        (string) ($participant['suffix'] ?? '')
    );
    $participant['display_name'] = preg_replace('/\s+/', ' ', $displayName) ?: $displayName;
    $participant['is_assigned_here'] =
        $participant['april_tag_id'] !== null
        && (int) ($participant['issued_from_operational_class_id'] ?? 0) === $operationalClassId;

    if ($participant['april_tag_id'] !== null) {
        $assignedCount++;
    }
}
unset($participant);

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

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <div class="content-wrapper">

            <div class="teacher-april-tags-page-header">

                <div>
                    <a class="teacher-april-tags-back" href="<?= APP_URL ?>/dashboard/teacher_my_class.php">
                        <i data-lucide="arrow-left"></i>
                        Back to My Classes
                    </a>

                    <h1 class="teacher-page-title">Manage AprilTags</h1>

                    <p class="teacher-page-subtitle">
                        <?= htmlspecialchars((string) $classContext['subject_name']) ?>
                        <span aria-hidden="true">·</span>
                        <?= htmlspecialchars((string) $classContext['section_name']) ?>
                        <span aria-hidden="true">·</span>
                        <?= htmlspecialchars((string) $classContext['school_year']) ?>
                        <span aria-hidden="true">·</span>
                        <?= htmlspecialchars((string) $classContext['semester']) ?>
                    </p>
                </div>

                <div class="teacher-april-tags-summary" aria-label="AprilTag assignment summary">
                    <span>
                        <?= $assignedCount ?> assigned
                    </span>
                    <span>
                        <?= max(0, count($participants) - $assignedCount) ?> awaiting tag
                    </span>
                </div>

            </div>

            <?php if (!empty($flash['success']) || !empty($flash['error']) || !empty($flash['warning'])): ?>
                <div class="teacher-april-tags-flash <?= !empty($flash['error']) ? 'is-error' : (!empty($flash['warning']) ? 'is-warning' : 'is-success') ?>"
                    role="status">
                    <i
                        data-lucide="<?= !empty($flash['error']) ? 'circle-alert' : (!empty($flash['warning']) ? 'triangle-alert' : 'circle-check') ?>"></i>
                    <span>
                        <?= htmlspecialchars((string) (!empty($flash['error']) ? $flash['error'] : (!empty($flash['warning']) ? $flash['warning'] : $flash['success']))) ?>
                    </span>
                </div>
            <?php endif; ?>

            <section class="teacher-panel teacher-april-tags-note">
                <i data-lucide="info"></i>
                <p>
                    A physical <strong>tagStandard52h13</strong> tag is assigned to one Student for this School Year and
                    Semester.
                    A later attendance scan still verifies that the Student belongs to the selected Operational Class.
                    Tags issued through another class are visible here but can only be reclaimed from their issuing
                    class.
                </p>
            </section>

            <section class="teacher-panel teacher-april-tags-panel">
                <div class="teacher-panel-header">
                    <div>
                        <h2 class="teacher-panel-title">Active Class List Participants</h2>
                        <p class="teacher-panel-subtitle">
                            Only active Student Class Enrollment records can receive a tag.
                        </p>
                    </div>
                </div>

                <div class="teacher-table-wrapper">
                    <table class="teacher-table teacher-april-tags-table">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>Student</th>
                                <th>Current Tag</th>
                                <th>Assignment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($participants === []): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="teacher-empty-state teacher-april-tags-empty-state">
                                            <i data-lucide="users-round"></i>
                                            <h3 class="teacher-empty-state-title">No Active Class List Participants</h3>
                                            <p class="teacher-empty-state-text">
                                                Import and confirm the Class List before assigning AprilTags.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td class="teacher-april-tags-number">
                                            <?= htmlspecialchars((string) $participant['student_number']) ?>
                                        </td>
                                        <td>
                                            <strong>
                                                <?= htmlspecialchars((string) $participant['display_name']) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($participant['april_tag_id'] !== null): ?>
                                                <span class="teacher-badge teacher-badge-success">
                                                    #
                                                    <?= (int) $participant['tag_code'] ?>
                                                </span>
                                                <span class="teacher-april-tags-family">tagStandard52h13</span>
                                            <?php else: ?>
                                                <span class="teacher-badge teacher-badge-info">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($participant['april_tag_id'] === null): ?>
                                                <form class="teacher-april-tags-assignment-form"
                                                    action="<?= APP_URL ?>/actions/teacher/manage_april_tag.php" method="post">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="operation" value="assign">
                                                    <input type="hidden" name="operational_class_id"
                                                        value="<?= $operationalClassId ?>">
                                                    <input type="hidden" name="student_class_enrollment_id"
                                                        value="<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                    <label class="visually-hidden"
                                                        for="tagCode<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                        AprilTag number for
                                                        <?= htmlspecialchars((string) $participant['display_name']) ?>
                                                    </label>
                                                    <input id="tagCode<?= (int) $participant['student_class_enrollment_id'] ?>"
                                                        name="tag_code" type="number" min="0" max="48713" step="1"
                                                        placeholder="Tag number" required>
                                                    <button class="teacher-primary-btn" type="submit">
                                                        <i data-lucide="tag"></i>
                                                        Assign
                                                    </button>
                                                </form>
                                            <?php elseif ($participant['is_assigned_here']): ?>
                                                <form class="teacher-april-tags-assignment-form"
                                                    action="<?= APP_URL ?>/actions/teacher/manage_april_tag.php" method="post">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="operation" value="replace">
                                                    <input type="hidden" name="operational_class_id"
                                                        value="<?= $operationalClassId ?>">
                                                    <input type="hidden" name="student_class_enrollment_id"
                                                        value="<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                    <input type="hidden" name="april_tag_id"
                                                        value="<?= (int) $participant['april_tag_id'] ?>">
                                                    <label class="visually-hidden"
                                                        for="replacementTagCode<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                        Replacement AprilTag number for
                                                        <?= htmlspecialchars((string) $participant['display_name']) ?>
                                                    </label>
                                                    <input
                                                        id="replacementTagCode<?= (int) $participant['student_class_enrollment_id'] ?>"
                                                        name="tag_code" type="number" min="0" max="48713" step="1"
                                                        placeholder="Replacement tag" required>
                                                    <button class="teacher-primary-btn" type="submit">
                                                        <i data-lucide="repeat-2"></i>
                                                        Replace
                                                    </button>
                                                </form>
                                                <?php if ((int) $participant['active_term_class_count'] > 1): ?>
                                                    <span class="teacher-april-tags-secondary">
                                                        Active in another class this term; keep or replace this tag there.
                                                    </span>
                                                <?php else: ?>
                                                    <form action="<?= APP_URL ?>/actions/teacher/manage_april_tag.php" method="post"
                                                        data-april-tag-reclaim-form>
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="operation" value="reclaim">
                                                        <input type="hidden" name="operational_class_id"
                                                            value="<?= $operationalClassId ?>">
                                                        <input type="hidden" name="student_class_enrollment_id"
                                                            value="<?= (int) $participant['student_class_enrollment_id'] ?>">
                                                        <input type="hidden" name="april_tag_id"
                                                            value="<?= (int) $participant['april_tag_id'] ?>">
                                                        <button class="teacher-april-tags-reclaim" type="submit">
                                                            <i data-lucide="undo-2"></i>
                                                            Reclaim tag
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="teacher-april-tags-secondary">
                                                    Assigned through another class this term
                                                </span>
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

    </main>

    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-april-tag-reclaim-form]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (!window.confirm('Reclaim this AprilTag and make it available for reassignment?')) {
                        event.preventDefault();
                    }
                });
            });
        });
    </script>

</body>

</html>