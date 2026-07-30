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

$userId = trim($_POST['user_id'] ?? '');

$errors = [];

if ($userId === '' || !ctype_digit($userId)) {
    $errors[] = 'Invalid user.';
}

if (!empty($errors)) {

    $_SESSION['error_message'] = implode('<br>', $errors);

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

try {

    $sql = "
        SELECT
            user_id,
            first_name,
            last_name
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'Unable to validate user.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

if (!$user) {

    $_SESSION['error_message'] = 'User not found.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

$temporaryPassword = bin2hex(random_bytes(4));

$passwordHash = password_hash(
    $temporaryPassword,
    PASSWORD_DEFAULT
);

try {

    $sql = "
        UPDATE users
        SET
            password_hash = :password_hash,
            must_change_password = TRUE
        WHERE user_id = :user_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':password_hash' => $passwordHash,
        ':user_id' => $userId
    ]);

    logAudit(
        $pdo,
        'Reset Password',
        'Reset password for ' .
        $user['first_name'] .
        ' ' .
        $user['last_name']
    );

    $_SESSION['success_message'] =
        'User password reset successfully.';

    $_SESSION['temporary_password'] = $temporaryPassword;

    $_SESSION['temporary_password_user'] =
        $user['first_name'] . ' ' . $user['last_name'];

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'Unable to reset password.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}