<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/auth/role_helper.php';
require_once __DIR__ . '/auth/session_guard.php';

switch ($_SESSION['role_id']) {

    case ROLE_TECHNICAL_ADMINISTRATOR:
        header('Location: ' . APP_URL . '/dashboard/technical_admin.php');
        exit;

    case ROLE_ACADEMIC_HEAD:
        header('Location: ' . APP_URL . '/dashboard/academic_head.php');
        exit;

    case ROLE_TEACHER:
    case ROLE_DISCIPLINARY_OFFICER:
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