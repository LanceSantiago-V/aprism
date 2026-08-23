<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/role_helper.php';
require_once __DIR__ . '/../auth/authorization_helper.php';
require_once __DIR__ . '/../includes/helper/system_settings_helper.php';

/*
|--------------------------------------------------------------------------
| Optional API-safe failure mode
|--------------------------------------------------------------------------
|
| A JSON endpoint can set `$apiResponseMode = true` before requiring this
| guard. All existing page behavior remains unchanged when it is absent.
|
*/

$sessionGuardApiMode = isset($apiResponseMode) && $apiResponseMode === true;

$sessionGuardFail = static function (int $status, string $message, string $code) use ($sessionGuardApiMode): never {
    if ($sessionGuardApiMode) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        echo json_encode(
            [
                'success' => false,
                'message' => $message,
                'code' => $code,
                'data' => [],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    $_SESSION['error_message'] = $message;

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
};

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role_id']) ||
    !isset($_SESSION['employee_number']) ||
    !isset($_SESSION['username'])
) {
    $sessionGuardFail(
        401,
        'Your session has expired. Please log in again.',
        'SESSION_EXPIRED'
    );
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
        session_id(),
    ]);

    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log(
        '[APRISM Session Guard] Session lookup failed: ' .
        $e->getMessage()
    );

    $sessionGuardFail(
        500,
        'The request could not be completed at this time. Please try again later.',
        'SESSION_VALIDATION_FAILED'
    );
}

if ($activeSession === false) {
    session_unset();
    session_destroy();

    $_SESSION = [];

    session_start();

    $sessionGuardFail(
        401,
        'Your session has expired. Please log in again.',
        'SESSION_EXPIRED'
    );
}

if (time() >= strtotime((string) $activeSession['expires_at'])) {
    try {
        $sql = "
            DELETE FROM user_sessions
            WHERE session_id = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $activeSession['session_id'],
        ]);
    } catch (PDOException $e) {
        // Continue with logout even if cleanup fails.
    }

    session_unset();
    session_destroy();

    $_SESSION = [];

    session_start();

    $sessionGuardFail(
        401,
        'Your session has expired. Please log in again.',
        'SESSION_EXPIRED'
    );
}

$currentScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

/* Remove the application root (/aprism) before comparison. */
$currentScript = str_replace(APP_URL, '', $currentScript);

$allowedScripts = [
    '/auth/change_password.php',
    '/auth/update_password.php',
    '/auth/logout.php',
];

if (
    (bool) $activeSession['must_change_password'] &&
    !in_array($currentScript, $allowedScripts, true)
) {
    if ($sessionGuardApiMode) {
        $sessionGuardFail(
            403,
            'You must change your password before continuing.',
            'PASSWORD_CHANGE_REQUIRED'
        );
    }

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
        $activeSession['session_id'],
    ]);
} catch (PDOException $e) {
    error_log(
        '[APRISM Session Guard] Session refresh failed: ' .
        $e->getMessage()
    );

    $sessionGuardFail(
        500,
        'The request could not be completed at this time. Please try again later.',
        'SESSION_REFRESH_FAILED'
    );
}

/*
|--------------------------------------------------------------------------
| Optional Role-Based Access Control
|--------------------------------------------------------------------------
*/

if (isset($allowedRoles) && is_array($allowedRoles)) {
    if (!in_array(getCurrentRoleId(), $allowedRoles, true)) {
        if ($sessionGuardApiMode) {
            $sessionGuardFail(
                403,
                'You do not have permission to perform this action.',
                'ROLE_ACCESS_DENIED'
            );
        }

        http_response_code(403);
        exit('403 Forbidden');
    }
}