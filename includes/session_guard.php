<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role_id']) ||
    !isset($_SESSION['employee_number']) ||
    !isset($_SESSION['username'])
) {

    $_SESSION['error'] = 'Your session has expired. Please log in again.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

try {

    $sql = "
        SELECT
            session_id,
            expires_at
        FROM user_sessions
        WHERE user_id = ?
          AND session_token = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $_SESSION['user_id'],
        session_id()
    ]);

    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $_SESSION['error'] = 'The request could not be completed at this time. Please try again later.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if ($activeSession === false) {

    session_unset();
    session_destroy();

    $_SESSION = [];

    session_start();

    $_SESSION['error'] = 'Your session has expired. Please log in again.';

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
    $_SESSION['error'] = 'Your session has expired. Please log in again.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$lastActivityAt = date('Y-m-d H:i:s');

$expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

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

    $_SESSION['error'] = 'The request could not be completed at this time. Please try again later.';

    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

