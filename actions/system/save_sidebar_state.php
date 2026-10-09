<?php

session_start();
require_once __DIR__ . '/../../auth/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Your session has expired.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('The request could not be verified.');
}

$_SESSION['sidebar_collapsed'] = filter_var(
    $_POST['collapsed'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

http_response_code(204);
