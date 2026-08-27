<?php

declare(strict_types=1);

/* Copy this file to config/environment.php on each environment.
 * It is deliberately ignored by Git and must never be public. */
return [
    'app_env' => 'production',
    'app_url' => '', // '' for https://app.aprism.tech; '/aprism' for a subfolder install.
    'storage_path' => dirname(__DIR__) . '/storage',
    'error_log_path' => dirname(__DIR__) . '/storage/logs/php-error.log',
    'mysqldump_path' => '/usr/bin/mysqldump', // Confirm with the host; use '' when unavailable.
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'aprism',
        'username' => 'aprism_user',
        'password' => 'REPLACE_WITH_A_LONG_UNIQUE_PASSWORD',
    ],
];
