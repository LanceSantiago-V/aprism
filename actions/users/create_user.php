<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/email_helper.php';
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
    'Adviser'
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

function aprismCreateUserSafeInput(string $value, int $maximumCharacters): string
{
    $value = trim($value);
    if (preg_match_all('/./us', $value, $characters) === false) {
        return substr($value, 0, $maximumCharacters);
    }
    return implode('', array_slice($characters[0], 0, $maximumCharacters));
}

function aprismRememberCreateUserFailure(string $generalMessage, array $fieldErrors = []): void
{
    $postedResponsibilities = $_POST['responsibilities'] ?? [];
    $postedResponsibilities = is_array($postedResponsibilities) ? $postedResponsibilities : [];
    $postedRole = (string) ($_POST['role_id'] ?? '');
    $postedStatus = (string) ($_POST['account_status'] ?? '');
    $_SESSION['create_user_retry'] = [
        'username' => aprismCreateUserSafeInput((string) ($_POST['username'] ?? ''), 50),
        'first_name' => aprismCreateUserSafeInput((string) ($_POST['first_name'] ?? ''), 100),
        'last_name' => aprismCreateUserSafeInput((string) ($_POST['last_name'] ?? ''), 100),
        'email' => aprismCreateUserSafeInput((string) ($_POST['email'] ?? ''), 255),
        'role_id' => in_array($postedRole, ['1', '2', '3', '4'], true) ? $postedRole : '1',
        'account_status' => in_array($postedStatus, ['Active', 'Disabled'], true) ? $postedStatus : 'Active',
        'responsibilities' => array_values(array_intersect($postedResponsibilities, ['Adviser']))
    ];
    $_SESSION['create_user_field_errors'] = $fieldErrors;
    $_SESSION['create_user_general_error'] = $generalMessage;
    $_SESSION['error_message'] = $generalMessage;
    header('Location: ' . APP_URL . '/dashboard/technical_admin_users.php');
    exit;
}

function aprismCreateUserCharacterLength(string $value): int
{
    if (preg_match_all('/./us', $value, $characters) === false) {
        return strlen($value);
    }
    return count($characters[0]);
}

$fieldErrors = [];
$usernameLength = aprismCreateUserCharacterLength($username);

if ($username === '') {
    $fieldErrors['username'] = 'Enter a username.';
} elseif ($usernameLength > 50) {
    $fieldErrors['username'] = 'Use 50 characters or fewer.';
}

$firstNameLength = aprismCreateUserCharacterLength($firstName);
$lastNameLength = aprismCreateUserCharacterLength($lastName);

if ($firstName === '') {
    $fieldErrors['first_name'] = 'Enter a first name.';
} elseif ($firstNameLength > 100 || preg_match('/\p{L}/u', $firstName) !== 1) {
    $fieldErrors['first_name'] = $firstNameLength > 100
        ? 'Use 100 characters or fewer.'
        : 'Enter a name that includes at least one letter.';
}

if ($lastName === '') {
    $fieldErrors['last_name'] = 'Enter a last name.';
} elseif ($lastNameLength > 100 || preg_match('/\p{L}/u', $lastName) !== 1) {
    $fieldErrors['last_name'] = $lastNameLength > 100
        ? 'Use 100 characters or fewer.'
        : 'Enter a name that includes at least one letter.';
}

if ($email === '') {
    $fieldErrors['email'] = 'Enter an institutional email.';
} elseif (!isInstitutionalEmail($email)) {
    $fieldErrors['email'] = 'Please enter a valid STI College Dasmariñas institutional email address.';
} elseif (strlen($email) > 255) {
    $fieldErrors['email'] = 'Use 255 characters or fewer.';
}

if ($roleId === '') {
    $fieldErrors['role_id'] = 'Choose a role.';
}

if ($accountStatus === '') {
    $fieldErrors['account_status'] = 'Choose an account status.';

} elseif (!in_array($accountStatus, ['Active', 'Disabled'], true)) {
    $fieldErrors['account_status'] = 'Choose a valid account status.';

}

if ($fieldErrors) {
    aprismRememberCreateUserFailure('Check the highlighted fields and try again.', $fieldErrors);
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

        aprismRememberCreateUserFailure('Choose a valid APRISM role.', ['role_id' => 'Choose a valid role.']);

    }

} catch (PDOException $e) {

    aprismRememberCreateUserFailure('Unable to validate the selected role. Please try again.');

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
        aprismRememberCreateUserFailure('This account could not be created. Review the highlighted field.', ['username' => 'That username is already in use.']);

    } elseif ($existingUser['email'] === $email) {
        aprismRememberCreateUserFailure('This account could not be created. Review the highlighted field.', ['email' => 'That email address is already in use.']);

    }

    aprismRememberCreateUserFailure('This account could not be created. Review the account details.');

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

        if (in_array('Adviser', $responsibilities, true)) {
            $activeSchoolYear = aprismActiveSchoolYear($pdo);
            if (!$activeSchoolYear)
                throw new RuntimeException('No active School Year is configured.');
            $contextualStmt = $pdo->prepare("INSERT INTO teacher_responsibility_assignments (teacher_id, responsibility_name, school_year_id, status) VALUES (?, 'Adviser', ?, 'Active')");
            $contextualStmt->execute([$newUserId, (int) $activeSchoolYear['school_year_id']]);
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

    error_log('[APRISM Create User] Persistence failed: ' . $e->getMessage());
    aprismRememberCreateUserFailure('Unable to create this account right now. Your entries are saved so you can try again.');

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[APRISM Create User] Context assignment failed: ' . $e->getMessage());
    aprismRememberCreateUserFailure('Unable to complete this account setup. Your entries are saved so you can review them.');

}
