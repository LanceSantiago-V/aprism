<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../../includes/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

$userId = filter_input(
    INPUT_POST,
    'user_id',
    FILTER_VALIDATE_INT
);

if (!$userId) {

    $_SESSION['error_message'] = 'Invalid user selected.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

try {

    $stmt = $pdo->prepare("
        SELECT
            account_status
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userId === (int) $_SESSION['user_id']) {

    $_SESSION['error_message'] =
        'You cannot change the status of your own account.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/technical_admin_users.php'
    );

    exit;
}

    if (!$user) {

        $_SESSION['error_message'] = 'User not found.';

        header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
        exit;

    }

    $newStatus =
        $user['account_status'] === 'Active'
        ? 'Disabled'
        : 'Active';

    $update = $pdo->prepare("
        UPDATE users
        SET account_status = ?
        WHERE user_id = ?
    ");

    $update->execute([
        $newStatus,
        $userId
    ]);

    $_SESSION['success_message'] =
        "Account status updated successfully.";

} catch (PDOException $e) {

    $_SESSION['error_message'] =
        "Unable to update account status.";

}

header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
exit;