<?php

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$_SESSION['sidebar_collapsed'] = filter_var(
    $_POST['collapsed'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

http_response_code(204);