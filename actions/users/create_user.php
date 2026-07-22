<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/email_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

$employeeNumber = trim($_POST['employee_number'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');

$roleId = $_POST['role_id'] ?? '';

$accountStatus = trim($_POST['account_status'] ?? '');

$errors = [];

if ($employeeNumber === '') {
    $errors[] = 'Employee number is required.';
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
            employee_number,
            username,
            email
        FROM users
        WHERE
            employee_number = :employee_number
            OR username = :username
            OR email = :email
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':employee_number' => $employeeNumber,
        ':username' => $username,
        ':email' => $email
    ]);

    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'Unable to validate user information.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

if ($existingUser) {

    if ($existingUser['employee_number'] === $employeeNumber) {

        $_SESSION['error_message'] = 'Employee number already exists.';

    } elseif ($existingUser['username'] === $username) {

        $_SESSION['error_message'] = 'Username already exists.';

    } elseif ($existingUser['email'] === $email) {

        $_SESSION['error_message'] = 'Email address already exists.';

    }

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
        INSERT INTO users
        (
            role_id,
            employee_number,
            username,
            email,
            first_name,
            last_name,
            password_hash,
            account_status
        )
        VALUES
        (
            :role_id,
            :employee_number,
            :username,
            :email,
            :first_name,
            :last_name,
            :password_hash,
            :account_status
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':role_id' => $roleId,
        ':employee_number' => $employeeNumber,
        ':username' => $username,
        ':email' => $email,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':password_hash' => $passwordHash,
        ':account_status' => $accountStatus
    ]);

    $_SESSION['success_message'] = 'User account created successfully.';

    $_SESSION['temporary_password'] = $temporaryPassword;

    $_SESSION['temporary_password_user'] =
        $firstName . ' ' . $lastName;
    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'Unable to create user.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}