<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/import/institutional_registry_import_engine.php';

function placementImportCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }

    echo "PASS: {$message}\n";
}

function placementImportMethodSource(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) {
        throw new RuntimeException("Missing method {$method}.");
    }

    $next = preg_match('/\n    (?:private|public|protected) function /', $source, $match, PREG_OFFSET_CAPTURE, $start + 1);
    $end = $next === 1 ? $match[0][1] : strlen($source);

    return substr($source, $start, $end - $start);
}

function placementImportIsolatedTestUrl(string $baseUrl, string $path = ''): string
{
    $parts = parse_url($baseUrl);
    if (
        !is_array($parts)
        || ($parts['scheme'] ?? '') !== 'http'
        || ($parts['host'] ?? '') !== '127.0.0.1'
        || (int) ($parts['port'] ?? 0) < 1024
        || ($parts['path'] ?? '') !== '/aprism'
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        throw new RuntimeException('Placement import runtime tests must use an isolated 127.0.0.1 port and /aprism path.');
    }

    return rtrim($baseUrl, '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

$engine = new InstitutionalRegistryImportEngine();
$canonicalHostRejected = false;
try {
    placementImportIsolatedTestUrl('http://localhost/aprism');
} catch (RuntimeException) {
    $canonicalHostRejected = true;
}
placementImportCheck($canonicalHostRejected, 'isolated runtime URL guard rejects canonical localhost');
$isolatedUrl = placementImportIsolatedTestUrl(
    getenv('APRISM_TEST_BASE_URL') ?: 'http://127.0.0.1:8191/aprism',
    'dashboard/academic_head_students.php'
);
placementImportCheck(
    str_starts_with($isolatedUrl, 'http://127.0.0.1:')
        && str_ends_with($isolatedUrl, '/aprism/dashboard/academic_head_students.php'),
    'isolated runtime URL guard builds test routes only under the isolated loopback application'
);
$runSideEffect = new ReflectionMethod($engine, 'runPostCommitSideEffect');
$successfulEffectRan = false;
$successful = $runSideEffect->invoke($engine, 'test success', static function () use (&$successfulEffectRan): bool {
    $successfulEffectRan = true;
    return true;
});
placementImportCheck($successful === true && $successfulEffectRan, 'successful post-commit effects do not add failure warnings');

$failed = $runSideEffect->invoke($engine, 'test failure', static function (): never {
    throw new RuntimeException('simulated post-commit failure');
});
placementImportCheck($failed === false, 'thrown post-commit failures are reported as side-effect failures');

$falseResult = $runSideEffect->invoke($engine, 'test false result', static fn (): bool => false);
placementImportCheck($falseResult === false, 'false audit/history results are treated as side-effect failures');

$laterEffectRan = false;
$laterEffect = $runSideEffect->invoke($engine, 'test later effect', static function () use (&$laterEffectRan): bool {
    $laterEffectRan = true;
    return true;
});
placementImportCheck($laterEffect === true && $laterEffectRan, 'a prior side-effect failure does not prevent later side effects');

$engineSource = file_get_contents(__DIR__ . '/../includes/import/institutional_registry_import_engine.php');
$actionSource = file_get_contents(__DIR__ . '/../actions/academic_head/import_institutional_registry.php');
$pageSource = file_get_contents(__DIR__ . '/../dashboard/academic_head_students.php');
$toastSource = file_get_contents(__DIR__ . '/../assets/js/pages/academic-head-placement-import.js');
if ($engineSource === false || $actionSource === false || $pageSource === false || $toastSource === false) {
    throw new RuntimeException('Unable to read placement import sources.');
}

$resolveSource = placementImportMethodSource($engineSource, 'resolve');
placementImportCheck(
    str_contains($resolveSource, 'This Student appears more than once in the same School Year and teaching context.')
        && str_contains($resolveSource, 'This Student has conflicting placement values in the same School Year and teaching context.')
        && str_contains($resolveSource, 'array_key_exists($contextKey, $seenContexts)'),
    'duplicate Student rows and conflicting same-context placements are rejected during review'
);
$persistSource = placementImportMethodSource($engineSource, 'persist');
$commitPosition = strpos($persistSource, '$pdo->commit();');
$coreFailurePosition = strpos($persistSource, 'throw $exception;');
$postCommitPosition = strpos($persistSource, '$postCommitWarnings = [];');
placementImportCheck(
    $commitPosition !== false && $coreFailurePosition !== false && $postCommitPosition !== false
        && $commitPosition < $coreFailurePosition && $coreFailurePosition < $postCommitPosition
        && str_contains($persistSource, "'post_commit_warnings' => " . '$postCommitWarnings'),
    'committed import summaries carry post-commit outcome separately from transaction failure'
);

$studentSource = placementImportMethodSource($engineSource, 'lockOrCreateStudent');
$enrollmentSource = placementImportMethodSource($engineSource, 'lockOrCreateAcademicEnrollment');
$sceSource = placementImportMethodSource($engineSource, 'linkCompatibleClassEnrollments');
placementImportCheck(
    str_contains($studentSource, 'WHERE student_number = ?')
        && str_contains($studentSource, 'Student identity changed since review')
        && str_contains($enrollmentSource, '$reused++')
        && str_contains($sceSource, 'AND enrollment_id IS NULL'),
    'safe retry remains Student-Number keyed and reuses matching SAE/SCE relationships'
);
placementImportCheck(
    str_contains($actionSource, '$engine->persist(')
        && str_contains($actionSource, 'unset($_SESSION[APRISM_REGISTRY_STATE]);')
        && str_contains($actionSource, "registryFlash(\n                'warning'")
        && str_contains($actionSource, 'do not retry this import'),
    'post-commit warnings clear staged state and explicitly prevent a blind retry'
);
placementImportCheck(
    str_contains($actionSource, "academic_head_students.php?per_page=6")
        && !str_contains($actionSource, "academic_head_students.php?registry_import=1")
        && !str_contains($actionSource, "registryRedirect(APP_URL . '/dashboard/academic_head_students.php');"),
    'placement import redirects preserve the fixed page size so density normalization cannot consume one-time feedback'
);
placementImportCheck(
    str_contains($pageSource, 'class="toast-container-custom" id="placementImportToast"')
        && str_contains($toastSource, 'Placement import completed')
        && str_contains($toastSource, 'text.textContent = message'),
    'post-commit warning uses the existing APRISM toast surface with text-safe rendering'
);
