<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/email_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');

$roleId = $_POST['role_id'] ?? '';

$accountStatus = trim($_POST['account_status'] ?? '');

/* ---------- NEW ---------- */
$responsibilities = $_POST['responsibilities'] ?? [];

if (!is_array($responsibilities)) {

    $_SESSION['error_message'] =
        'Invalid responsibility data.';

    header(
        'Location: ' . APP_URL . '/dashboard/technical_admin_users.php'
    );

    exit;

}

$allowedResponsibilities = [
    'Adviser',
    'Program Head'
];

foreach ($responsibilities as $responsibility) {

    if (
        !in_array(
            $responsibility,
            $allowedResponsibilities,
            true
        )
    ) {

        $_SESSION['error_message'] =
            'Invalid responsibility submitted.';

        header(
            'Location: ' . APP_URL . '/dashboard/technical_admin_users.php'
        );

        exit;

    }

}
/* ------------------------- */

$errors = [];

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

if (
    !empty($responsibilities) &&
    (int) $roleId !== ROLE_TEACHER
) {

    $_SESSION['error_message'] =
        'Responsibilities can only be assigned to Teacher accounts.';

    header(
        'Location: ' . APP_URL . '/dashboard/technical_admin_users.php'
    );

    exit;

}

try {

    $sql = "
    SELECT
        username,
        email
    FROM users
    WHERE
        username = :username
        OR email = :email
    LIMIT 1
";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
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

    if ($existingUser['username'] === $username) {

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

    $pdo->beginTransaction();

    $year = date('Y');

    $numberStmt = $pdo->prepare("
    SELECT employee_number
    FROM users
    WHERE employee_number LIKE :pattern
    ORDER BY employee_number DESC
    LIMIT 1
    FOR UPDATE
");

    $numberStmt->execute([
        ':pattern' => 'EMP-' . $year . '-%'
    ]);

    $lastEmployeeNumber = $numberStmt->fetchColumn();

    if ($lastEmployeeNumber) {

        $lastSequence = (int) substr($lastEmployeeNumber, -3);
        $nextSequence = $lastSequence + 1;

    } else {

        $nextSequence = 1;

    }

    $employeeNumber =
        'EMP-' .
        $year .
        '-' .
        str_pad(
            $nextSequence,
            3,
            '0',
            STR_PAD_LEFT
        );

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

    /* ---------- NEW ---------- */

    $newUserId = $pdo->lastInsertId();

    if (!empty($responsibilities)) {

        $permissionStmt = $pdo->prepare("
            INSERT INTO user_permissions
            (
                user_id,
                permission_name
            )
            VALUES
            (?, ?)
        ");

        foreach ($responsibilities as $responsibility) {

            $permissionStmt->execute([
                $newUserId,
                $responsibility
            ]);

        }

    }

    /* ------------------------- */

    $pdo->commit();

    logAudit(
        $pdo,
        'Create User',
        'Created account for ' . $username
    );

    $_SESSION['success_message'] = 'User account created successfully.';

    $_SESSION['temporary_password'] = $temporaryPassword;

    $_SESSION['temporary_password_user'] =
        $firstName . ' ' . $lastName;

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Unable to create user.';

    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;

}