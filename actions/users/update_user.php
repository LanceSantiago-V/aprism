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
require_once __DIR__ . '/../../includes/email_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

$userId = filter_input(
    INPUT_POST,
    'user_id',
    FILTER_VALIDATE_INT
);

$username = trim($_POST['username'] ?? '');

$email = trim($_POST['email'] ?? '');

$firstName = trim($_POST['first_name'] ?? '');

$lastName = trim($_POST['last_name'] ?? '');

$roleId = $_POST['role_id'] ?? '';

$accountStatus = trim($_POST['account_status'] ?? '');

$errors = [];

if ($userId === false || $userId === null) {
    $errors[] = 'Invalid user.';
}

if ($username === '') {
    $errors[] = 'Username is required.';
}

if ($firstName === '') {
    $errors[] = 'First name is required.';
}

if ($lastName === '') {
    $errors[] = 'Last name is required.';
}

if ($email === '') {
    $errors[] = 'Institutional email is required.';
} elseif (!isInstitutionalEmail($email)) {
    $errors[] =
        'Please enter a valid STI College Dasmariñas institutional email address.';
}

if ($roleId === '') {
    $errors[] = 'Role is required.';
}

if ($accountStatus === '') {
    $errors[] = 'Account status is required.';
} elseif (!in_array($accountStatus, ['Active', 'Disabled'], true)) {
    $errors[] = 'Invalid account status.';
}

if (!empty($errors)) {

    $_SESSION['error_message'] = implode('<br>', $errors);

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

try {

    $stmt = $pdo->prepare("
        SELECT role_id
        FROM roles
        WHERE role_id = :role_id
        LIMIT 1
    ");

    $stmt->execute([
        ':role_id' => $roleId
    ]);

    if (!$stmt->fetch()) {

        $_SESSION['error_message'] = 'Invalid role selected.';

        header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
        exit;

    }

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'Unable to validate role.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

try {

    $sql = "
        SELECT
            user_id,
            username,
            email
        FROM users
        WHERE
            (username = :username
             OR email = :email)
        AND user_id <> :user_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':user_id' => $userId
    ]);

    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'Unable to validate user information.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

if ($existingUser) {

    if ($existingUser['username'] === $username) {

        $_SESSION['error_message'] = 'Username already exists.';

    } elseif ($existingUser['email'] === $email) {

        $_SESSION['error_message'] = 'Email address already exists.';

    }

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

try {

    $sql = "
        UPDATE users
        SET
            role_id = :role_id,
            username = :username,
            email = :email,
            first_name = :first_name,
            last_name = :last_name,
            account_status = :account_status
        WHERE user_id = :user_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':role_id' => $roleId,
        ':username' => $username,
        ':email' => $email,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':account_status' => $accountStatus,
        ':user_id' => $userId
    ]);

    $_SESSION['success_message'] = 'User updated successfully.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'Unable to update user.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}