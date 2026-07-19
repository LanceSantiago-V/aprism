<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authFlash = [
    'success' => $_SESSION['auth_success'] ?? null,
    'error'   => $_SESSION['auth_error'] ?? null,
    'warning' => $_SESSION['auth_warning'] ?? null,
];

unset(
    $_SESSION['auth_success'],
    $_SESSION['auth_error'],
    $_SESSION['auth_warning']
);