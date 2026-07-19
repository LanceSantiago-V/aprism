<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'] ?? null;

$sessionToken = session_id();

if ($userId !== null) {

    try {

        $sql = "
            DELETE FROM user_sessions
            WHERE user_id = ?
              AND session_token = ?
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $userId,
            $sessionToken
        ]);

    } catch (PDOException $e) {

        // Continue with logout even if cleanup fails.

    }

}

session_unset();

session_destroy();

$_SESSION = [];

session_start();

$_SESSION['success_message'] = 'You have been logged out successfully.';

header('Location: ' . APP_URL . '/auth/login.php');
exit;