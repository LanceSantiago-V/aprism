<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helper/password_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;

}

if (!isset($_SESSION['user_id'])) {

    $_SESSION['error_message'] = 'Your session has expired. Please log in again.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;

}

$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

if ($newPassword === '') {
    $errors[] = 'New Password is required.';
}

if ($confirmPassword === '') {
    $errors[] = 'Confirm Password is required.';
}

if (
    $newPassword !== ''
    && $confirmPassword !== ''
    && $newPassword !== $confirmPassword
) {
    $errors[] = 'Passwords do not match.';
}

if ($newPassword !== '') {

    $passwordValidation = validatePasswordStrength($newPassword);

    if (!$passwordValidation['valid']) {

        $errors = array_merge(
            $errors,
            $passwordValidation['errors']
        );

    }

}

if (!empty($errors)) {

    $_SESSION['error_message'] = implode('<br>', $errors);

    header('Location: ' . APP_URL . '/auth/change_password.php');
    exit;

}

try {

    $sql = "
        SELECT
            user_id,
            must_change_password,
            password_hash
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $_SESSION['error_message'] =
        'The request could not be completed at this time. Please try again later.';

    header('Location: ' . APP_URL . '/auth/change_password.php');
    exit;

}

if (!$user) {

    session_unset();
    session_destroy();

    session_start();

    $_SESSION['error_message'] =
        'Your session has expired. Please log in again.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;

}

if (!(bool) $user['must_change_password']) {

    header('Location: ' . APP_URL . '/dashboard.php');
    exit;

}

if (
    password_verify(
        $newPassword,
        $user['password_hash']
    )
) {

    $_SESSION['error_message'] =
        'Your new password must be different from your current password.';

    header(
        'Location: ' .
        APP_URL .
        '/auth/change_password.php'
    );

    exit;

}

$passwordHash = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);

try {

    $sql = "
        UPDATE users
        SET
            password_hash = ?,
            must_change_password = FALSE
        WHERE user_id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $passwordHash,
        $user['user_id']
    ]);

    // Regenerate the PHP session ID
    session_regenerate_id(true);

    $sessionToken = session_id();

    $sql = "
        UPDATE user_sessions
        SET session_token = ?
        WHERE user_id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $sessionToken,
        $user['user_id']
    ]);

} catch (PDOException $e) {

    $_SESSION['error_message'] =
        'Unable to update password.';

    header('Location: ' . APP_URL . '/auth/change_password.php');
    exit;

}

$_SESSION['success_message'] =
    'Password updated successfully.';

header('Location: ' . APP_URL . '/dashboard.php');
exit;