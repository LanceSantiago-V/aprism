<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/role_helper.php';
require_once __DIR__ . '/../auth/authorization_helper.php';
require_once __DIR__ . '/../includes/helper/system_settings_helper.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role_id']) ||
    !isset($_SESSION['employee_number']) ||
    !isset($_SESSION['username'])
) {

    $_SESSION['error_message'] = 'Your session has expired. Please log in again.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

try {

    $sql = "
    SELECT
        us.session_id,
        us.expires_at,
        u.must_change_password
    FROM user_sessions AS us
    INNER JOIN users AS u
        ON u.user_id = us.user_id
    WHERE us.user_id = ?
      AND us.session_token = ?
    LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $_SESSION['user_id'],
        session_id()
    ]);

    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'The request could not be completed at this time. Please try again later.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if ($activeSession === false) {

    session_unset();
    session_destroy();

    $_SESSION = [];

    session_start();

    $_SESSION['error_message'] = 'Your session has expired. Please log in again.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if (time() >= strtotime($activeSession['expires_at'])) {

    try {

        $sql = "
            DELETE FROM user_sessions
            WHERE session_id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $activeSession['session_id']
        ]);

    } catch (PDOException $e) {

        // Continue with logout even if cleanup fails.
    }

    session_unset();
    session_destroy();

    $_SESSION = [];

    session_start();
    $_SESSION['error_message'] = 'Your session has expired. Please log in again.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);

// Remove the application root (/aprism) before comparison.
$currentScript = str_replace(APP_URL, '', $currentScript);

$allowedScripts = [
    '/auth/change_password.php',
    '/auth/update_password.php',
    '/auth/logout.php'
];

if (
    (bool) $activeSession['must_change_password']
    && !in_array($currentScript, $allowedScripts, true)
) {

    header(
        'Location: ' .
        APP_URL .
        '/auth/change_password.php'
    );

    exit;

}

$lastActivityAt = date('Y-m-d H:i:s');

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

try {

    $sql = "
        UPDATE user_sessions
        SET
            last_activity_at = ?,
            expires_at = ?
        WHERE session_id = ?
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $lastActivityAt,
        $expiresAt,
        $activeSession['session_id']
    ]);

} catch (PDOException $e) {

    $_SESSION['error_message'] = 'The request could not be completed at this time. Please try again later.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Optional Role-Based Access Control
|--------------------------------------------------------------------------
|
| Pages may define:
|
| $allowedRoles = [
|     ROLE_TEACHER,
|     ROLE_ACADEMIC_HEAD
| ];
|
| before including session_guard.php.
|
| If no allowed roles are defined, only session validation is performed.
|
*/

if (isset($allowedRoles) && is_array($allowedRoles)) {

    if (!in_array(getCurrentRoleId(), $allowedRoles, true)) {

        http_response_code(403);

        exit('403 Forbidden');
    }
}