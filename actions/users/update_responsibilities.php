<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

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
    'Adviser',
    'Program Head'
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

    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare("
        DELETE
        FROM user_permissions
        WHERE user_id = ?
    ");

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

    foreach ($responsibilities as $responsibility) {

        $insertStmt->execute([
            $userId,
            $responsibility
        ]);

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