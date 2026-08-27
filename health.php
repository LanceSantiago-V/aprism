<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $pdo->query('SELECT 1');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['status' => 'ok', 'release' => 'inspection-candidate-2026-08-27']);
} catch (Throwable $exception) {
    error_log('[APRISM Health] Database health check failed: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'unavailable']);
}
