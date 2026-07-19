<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/session_guard.php';

switch ($_SESSION['role_id']) {

    case 1:
        header('Location: ' . APP_URL . '/dashboard/technical_admin.php');
        exit;

    case 2:
        
    case 3:
    case 4:
        $_SESSION['error'] = 'This dashboard is not yet available during development.';

        header('Location: ' . APP_URL . '/auth/login.php');
        exit;

    default:
        session_unset();
        session_destroy();

        session_start();

        $_SESSION['error'] = 'Access denied.';

        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
}