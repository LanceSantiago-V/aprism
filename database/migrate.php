<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration_name VARCHAR(255) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (migration_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$migrations = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($migrations, SORT_STRING);

foreach ($migrations as $path) {
    $name = basename($path);
    $statement = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration_name = ? LIMIT 1');
    $statement->execute([$name]);

    if ($statement->fetchColumn()) {
        echo "Skipped {$name}\n";
        continue;
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Unable to read {$name}.");
    }

    try {
        $pdo->exec($sql);
        $statement = $pdo->prepare('INSERT INTO schema_migrations (migration_name) VALUES (?)');
        $statement->execute([$name]);
        echo "Applied {$name}\n";
    } catch (Throwable $exception) {
        error_log('[APRISM Migration] ' . $name . ': ' . $exception->getMessage());
        fwrite(STDERR, "Migration failed: {$name}. Restore the pre-release database backup before retrying.\n");
        exit(1);
    }
}

echo "Migrations are current.\n";
