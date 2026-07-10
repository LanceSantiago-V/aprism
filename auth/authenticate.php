<?php

session_start();

require_once __DIR__ . "/../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Username and password are required.';
    header("Location: login.php");
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
        WHERE username = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = 'The request could not be completed at this time. Please try again later.';
    header("Location: login.php");
    exit;
}

if ($user === false || !password_verify($password, $user['password_hash'])) {
    $_SESSION['error'] = 'Invalid username or password.';
    header("Location: login.php");
    exit;
}

if ($user['account_status'] !== 'Active') {
    $_SESSION['error'] = 'Your account has been deactivated. Please contact the system administrator.';
    header("Location: login.php");
    exit;
} 

session_regenerate_id(true);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['employee_number'] = $user['employee_number'];
$_SESSION['username'] = $user['username'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name'] = $user['last_name'];

$sessionToken = session_id();

$lastActivityAt = date('Y-m-d H:i:s');

/*
 * Temporary timeout.
 * We'll verify the final timeout duration against the
 * updated baseline before freezing it.
 */
$expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

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

    $_SESSION['error'] = 'The request could not be completed at this time. Please try again later.';

    header('Location: login.php');
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

} catch (PDOException $e) {

    $_SESSION['error'] = 'The request could not be completed at this time. Please try again later.';

    header("Location: login.php");
    exit;
}

header("Location: dashboard.php");
exit;