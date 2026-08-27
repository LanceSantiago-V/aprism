<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$command = $argv[1] ?? 'status';

if ($command === 'on') {
    file_put_contents(APRISM_MAINTENANCE_FLAG, 'enabled ' . gmdate('c') . PHP_EOL, LOCK_EX);
    echo "Maintenance mode enabled.\n";
    exit(0);
}

if ($command === 'off') {
    if (is_file(APRISM_MAINTENANCE_FLAG) && !unlink(APRISM_MAINTENANCE_FLAG)) {
        fwrite(STDERR, "Could not disable maintenance mode.\n");
        exit(1);
    }
    echo "Maintenance mode disabled.\n";
    exit(0);
}

if ($command === 'status') {
    echo is_file(APRISM_MAINTENANCE_FLAG) ? "Maintenance mode is enabled.\n" : "Maintenance mode is disabled.\n";
    exit(0);
}

fwrite(STDERR, "Usage: php maintenance.php [on|off|status]\n");
exit(1);
