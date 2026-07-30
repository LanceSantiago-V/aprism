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

if (!$userId) {

    $_SESSION['error_message'] = 'Invalid user selected.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

try {

    $stmt = $pdo->prepare("
        SELECT
            username,
            role_id,
            account_status
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        $_SESSION['error_message'] = 'User not found.';

        header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
        exit;

    }

    if ($userId === (int) $_SESSION['user_id']) {

        $_SESSION['error_message'] =
            'You cannot change the status of your own account.';

        header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
        exit;

    }

    $newStatus =
        $user['account_status'] === 'Active'
        ? 'Disabled'
        : 'Active';

    if (
        (int) $user['role_id'] === ROLE_TECHNICAL_ADMINISTRATOR &&
        $newStatus === 'Disabled'
    ) {

        $adminStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE role_id = ?
          AND account_status = 'Active'
    ");

        $adminStmt->execute([
            ROLE_TECHNICAL_ADMINISTRATOR
        ]);

        $activeAdminCount = (int) $adminStmt->fetchColumn();

        if ($activeAdminCount <= 1) {

            $_SESSION['error_message'] =
                'At least one active Technical Administrator must remain.';

            header(
                'Location: ' . APP_URL . '/dashboard/technical_admin_users.php'
            );

            exit;

        }

    }

    $pdo->beginTransaction();

    $update = $pdo->prepare("
        UPDATE users
        SET account_status = ?
        WHERE user_id = ?
    ");

    $update->execute([
        $newStatus,
        $userId
    ]);

    if ($newStatus === 'Disabled') {

        $sessionStmt = $pdo->prepare("
        DELETE FROM user_sessions
        WHERE user_id = ?
    ");

        $sessionStmt->execute([
            $userId
        ]);

    }

    $pdo->commit();

    if ($newStatus === 'Disabled') {

        logAudit(
            $pdo,
            'Disable Account',
            'Disabled account for ' . $user['username']
        );

    } else {

        logAudit(
            $pdo,
            'Enable Account',
            'Enabled account for ' . $user['username']
        );

    }

    $_SESSION['success_message'] =
        'Account status updated successfully.';

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] =
        'Unable to update account status.';

}

header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
exit;