<?php

declare(strict_types=1);

// Compatibility bootstrap for legacy APRISM callers. The canonical
// connection implementation lives in config/pdo.php.
require_once __DIR__ . '/pdo.php';
