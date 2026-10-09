<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';
require_once __DIR__ . '/../../includes/helper/responsibility_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_message'] = 'Your form session expired. Please try again.';
    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;
}

$userId = filter_input(
    INPUT_POST,
    'user_id',
    FILTER_VALIDATE_INT
);

$responsibilities = $_POST['responsibilities'] ?? [];

if (!is_array($responsibilities)) {

    $_SESSION['error_message'] =
        'Invalid responsibility data.';

    header(
        'Location: ' . APP_URL . '/dashboard/technical_admin_users.php'
    );

    exit;

}

if (!$userId) {

    $_SESSION['error_message'] = 'Invalid user selected.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

$allowedResponsibilities = [
    'Adviser'
];

foreach ($responsibilities as $responsibility) {

    if (!in_array($responsibility, $allowedResponsibilities, true)) {

        $_SESSION['error_message'] = 'Invalid responsibility submitted.';

        header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
        exit;

    }

}

try {

    $stmt = $pdo->prepare("
    SELECT
        username,
        role_id
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

    $stmt->execute([
        $userId
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        $_SESSION['error_message'] =
            'User not found.';

        header(
            'Location: ' .
            APP_URL .
            '/dashboard/technical_admin_users.php'
        );

        exit;

    }

    if ((int) $user['role_id'] !== ROLE_TEACHER) {

        $_SESSION['error_message'] =
            'Responsibilities can only be assigned to Teacher accounts.';

        header(
            'Location: ' . APP_URL . '/dashboard/technical_admin_users.php'
        );

        exit;

    }

    $activeSchoolYear = aprismActiveSchoolYear($pdo);
    if (!$activeSchoolYear) {
        throw new RuntimeException('No active School Year is configured.');
    }
    $activeSchoolYearId = (int) $activeSchoolYear['school_year_id'];
    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_name = 'Adviser'");

    $deleteStmt->execute([$userId]);

    $insertStmt = $pdo->prepare("
        INSERT INTO user_permissions
        (
            user_id,
            permission_name
        )
        VALUES
        (?, ?)
    ");

    if (in_array('Adviser', $responsibilities, true)) {
        $legacyAdviserStmt = $pdo->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_name) VALUES (?, 'Adviser')");
        $legacyAdviserStmt->execute([$userId]);
    }

    if (in_array('Adviser', $responsibilities, true)) {
        $contextualStmt = $pdo->prepare("INSERT INTO teacher_responsibility_assignments (teacher_id, responsibility_name, school_year_id, status, ended_at) VALUES (?, 'Adviser', ?, 'Active', NULL) ON DUPLICATE KEY UPDATE status = 'Active', ended_at = NULL, updated_at = CURRENT_TIMESTAMP");
        $contextualStmt->execute([$userId, $activeSchoolYearId]);
    } else {
        $scopeStmt = $pdo->prepare("
            SELECT aa.adviser_assignment_id
            FROM adviser_assignments aa
            INNER JOIN sections sec
                ON sec.section_id = aa.section_id
               AND sec.status = 'Active'
            INNER JOIN programs p
                ON p.program_id = sec.program_id
               AND p.status = 'Active'
               AND p.academic_level = 'Senior High School'
            INNER JOIN school_years sy
                ON sy.school_year_id = aa.school_year_id
               AND sy.status = 'Active'
            WHERE aa.teacher_id = ?
              AND aa.status = 'Active'
              AND aa.teaching_context IN ('First Semester', 'Second Semester')
            FOR UPDATE
        ");
        $scopeStmt->execute([$userId]);
        if ($scopeStmt->fetchColumn()) {
            throw new RuntimeException('Academic Head must deactivate the active Adviser assignment before Adviser capability can be removed.');
        }

        $contextualStmt = $pdo->prepare("UPDATE teacher_responsibility_assignments SET status = 'Ended', ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP) WHERE teacher_id = ? AND responsibility_name = 'Adviser' AND school_year_id = ? AND status = 'Active'");
        $contextualStmt->execute([$userId, $activeSchoolYearId]);
    }

    $pdo->commit();

    logAudit(
        $pdo,
        'Update Responsibilities',
        'Updated responsibilities for ' .
        $user['username']
    );

    $_SESSION['success_message'] =
        'Responsibilities updated successfully.';

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = $e->getMessage();

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    $_SESSION['error_message'] =
        'Unable to update responsibilities.';

}

header(
    'Location: ' .
    APP_URL .
    '/dashboard/technical_admin_users.php'
);

exit;
