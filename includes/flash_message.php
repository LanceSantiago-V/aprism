<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flash = [
    'success' => $_SESSION['success_message'] ?? null,
    'error'   => $_SESSION['error_message'] ?? null,
    'warning' => $_SESSION['warning_message'] ?? null,
];

unset(
    $_SESSION['success_message'],
    $_SESSION['error_message'],
    $_SESSION['warning_message']
);