<?php

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . '/../includes/helper/email_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/audit_helper.php';
require_once __DIR__ . '/../includes/helper/system_settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$email = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    $_SESSION['error_message'] = 'Institutional email and password are required.';
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if (!isInstitutionalEmail($email)) {
    $_SESSION['error_message'] = 'Please enter a valid STI College Dasmariñas institutional email address.';
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

try {
    $sql = "
        SELECT
            user_id,
            role_id,
            employee_number,
            username,
            first_name,
            last_name,
            password_hash,
            must_change_password,
            account_status
        FROM users
        WHERE email = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'The request could not be completed at this time. Please try again later.';
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$maximumFailedLoginAttempts = getSystemSetting(
    $pdo,
    'security_max_failed_login_attempts'
);

$maximumFailedLoginAttempts =
    $maximumFailedLoginAttempts !== null
    ? (int) $maximumFailedLoginAttempts
    : null;

$temporaryLockDuration = getSystemSetting(
    $pdo,
    'security_temporary_lock_duration_minutes'
);

$temporaryLockDuration =
    $temporaryLockDuration !== null
    ? (int) $temporaryLockDuration
    : 15;

$sessionTimeout = getSystemSetting(
    $pdo,
    'security_session_timeout_minutes'
);

$sessionTimeoutMinutes = $sessionTimeout !== null
    ? (int) $sessionTimeout
    : 60;

$expiresAt = date(
    'Y-m-d H:i:s',
    strtotime("+{$sessionTimeoutMinutes} minutes")
);

$userSecurityStatus = null;

if ($user !== false) {

    try {

        $sql = "
            SELECT
                failed_login_attempts,
                last_failed_login_at,
                locked_until
            FROM user_security_status
            WHERE user_id = ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $user['user_id']
        ]);

        $userSecurityStatus =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userSecurityStatus === false) {

            $sql = "
        INSERT INTO user_security_status (
            user_id,
            failed_login_attempts,
            last_failed_login_at,
            locked_until
        )
        VALUES (?, 0, NULL, NULL)
    ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $user['user_id']
            ]);

            $userSecurityStatus = [
                'failed_login_attempts' => 0,
                'last_failed_login_at' => null,
                'locked_until' => null
            ];

        }

    } catch (PDOException $e) {

        $_SESSION['error_message'] =
            'The request could not be completed at this time. Please try again later.';

        header('Location: ' . APP_URL . '/auth/login.php');
        exit;

    }

}

if (
    $user !== false &&
    $maximumFailedLoginAttempts !== null &&
    $userSecurityStatus['locked_until'] !== null &&
    strtotime($userSecurityStatus['locked_until']) > time()
) {

    $_SESSION['error_message'] =
        'Your account has been temporarily locked due to multiple failed login attempts. Please try again later.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;

}

if ($user === false) {

    $_SESSION['error_message'] =
        'Invalid username or password.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;

}

if (!password_verify($password, $user['password_hash'])) {

    if ($maximumFailedLoginAttempts !== null) {

        $failedLoginAttempts =
            (int) $userSecurityStatus['failed_login_attempts'] + 1;

        $lockedUntil = null;

        if ($failedLoginAttempts >= $maximumFailedLoginAttempts) {

            $lockedUntil = date(
                'Y-m-d H:i:s',
                strtotime("+{$temporaryLockDuration} minutes")
            );

        }

        $sql = "
            UPDATE user_security_status
            SET
                failed_login_attempts = ?,
                last_failed_login_at = NOW(),
                locked_until = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $failedLoginAttempts,
            $lockedUntil,
            $user['user_id']
        ]);

    }

    $_SESSION['error_message'] =
        'Invalid username or password.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;

}

if ($user['account_status'] !== 'Active') {
    $_SESSION['error_message'] =
        'Your account has been disabled. Please contact the Technical Administrator.';
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if ($maximumFailedLoginAttempts !== null) {

    try {

        $sql = "
            UPDATE user_security_status
            SET
                failed_login_attempts = 0,
                last_failed_login_at = NULL,
                locked_until = NULL,
                updated_at = NOW()
            WHERE user_id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $user['user_id']
        ]);

    } catch (PDOException $e) {

        $_SESSION['error_message'] =
            'The request could not be completed at this time. Please try again later.';

        header('Location: ' . APP_URL . '/auth/login.php');
        exit;

    }

}

session_regenerate_id(true);

$responsibilities = getUserResponsibilities(
    $pdo,
    (int) $user['user_id']
);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['employee_number'] = $user['employee_number'];
$_SESSION['username'] = $user['username'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name'] = $user['last_name'];

$_SESSION['responsibilities'] = $responsibilities;

$sessionToken = session_id();

$lastActivityAt = date('Y-m-d H:i:s');

try {

    $sql = "
        INSERT INTO user_sessions (
            user_id,
            session_token,
            last_activity_at,
            expires_at
        )
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            session_token = VALUES(session_token),
            last_activity_at = VALUES(last_activity_at),
            expires_at = VALUES(expires_at)
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $user['user_id'],
        $sessionToken,
        $lastActivityAt,
        $expiresAt
    ]);

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'The request could not be completed at this time. Please try again later.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

try {

    $sql = "
        UPDATE users
        SET last_login_at = NOW()
        WHERE user_id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $user['user_id']
    ]);

    logAudit(
        $pdo,
        'Login',
        'Successful login.'
    );

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'The request could not be completed at this time. Please try again later.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if ($user['must_change_password']) {

    header(
        'Location: ' .
        APP_URL .
        '/auth/change_password.php'
    );

    exit;

}

header('Location: ' . APP_URL . '/dashboard.php');
exit;