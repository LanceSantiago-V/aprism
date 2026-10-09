<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

$databaseConfig = APRISM_CONFIG['database'] ?? null;

if (!is_array($databaseConfig)) {
    error_log('[APRISM Database] Database configuration is missing.');
    aprismRenderFailure(500);
}

$host = trim((string) ($databaseConfig['host'] ?? ''));
$port = (int) ($databaseConfig['port'] ?? 3306);
$dbname = trim((string) ($databaseConfig['name'] ?? ''));
$username = (string) ($databaseConfig['username'] ?? '');
$password = (string) ($databaseConfig['password'] ?? '');

if ($host === '' || $dbname === '' || $username === '') {
    error_log('[APRISM Database] Required database values are missing.');
    aprismRenderFailure(500);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('[APRISM Database] Connection failed: ' . $exception->getMessage());
    aprismRenderFailure(500);
}
