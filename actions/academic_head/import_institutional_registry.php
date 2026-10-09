<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/import/institutional_registry_import_engine.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../../auth/session_guard.php';

const APRISM_REGISTRY_STATE = 'aprism_institutional_registry_import';
$redirect = APP_URL . '/dashboard/academic_head_students.php';

function registryRedirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function registryFlash(string $key, string $message): never
{
    $_SESSION[$key . '_message'] = $message;
    registryRedirect(APP_URL . '/dashboard/academic_head_students.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    registryFlash('error', 'The placement import request could not be verified. Refresh the page and try again.');
}

$stage = trim((string) ($_POST['stage'] ?? ''));
$engine = new InstitutionalRegistryImportEngine();

try {
    if ($stage === 'upload') {
        $file = $_FILES['registry_file'] ?? null;
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose a readable CSV, XLSX, or XLS placement data file.');
        }
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 10 * 1024 * 1024) {
            throw new RuntimeException('The placement data file must be between 1 byte and 10 MB.');
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $source = $engine->parseFile((string) $file['tmp_name'], (string) ($file['name'] ?? ''), $extension);
        $token = bin2hex(random_bytes(24));
        $_SESSION[APRISM_REGISTRY_STATE] = [
            'token' => $token,
            'stage' => 'mapping',
            'source' => $source,
            'mapping' => $source['mapping'],
            'normalized_rows' => [],
            'resolved' => [],
            'errors' => [],
        ];
        registryRedirect($redirect . '?registry_import=1');
    }

    $state = $_SESSION[APRISM_REGISTRY_STATE] ?? null;
    if (!is_array($state) || !hash_equals((string) ($state['token'] ?? ''), (string) ($_POST['import_token'] ?? ''))) {
        throw new RuntimeException('The staged placement import is no longer available. Upload the source again.');
    }

    if ($stage === 'cancel') {
        unset($_SESSION[APRISM_REGISTRY_STATE]);
        registryRedirect($redirect);
    }

    if ($stage === 'validate') {
        $mapping = [];
        foreach (InstitutionalRegistryImportEngine::canonicalFields() as $field) {
            $mapping[$field] = filter_var($_POST['mapping'][$field] ?? null, FILTER_VALIDATE_INT) ?: null;
        }
        $state['mapping'] = $mapping;
        $normalizedRows = $engine->applyMapping($state['source'], $mapping);
        $resolved = $engine->resolve($pdo, $normalizedRows);
        $state['normalized_rows'] = $normalizedRows;
        $state['resolved'] = $resolved['rows'];
        $state['errors'] = $resolved['errors'];
        $state['stage'] = $resolved['valid'] ? 'review' : 'mapping';
        $_SESSION[APRISM_REGISTRY_STATE] = $state;
        registryRedirect($redirect . '?registry_import=1&stage=' . ($resolved['valid'] ? 'review' : 'mapping'));
    }

    if ($stage === 'confirm') {
        if (($state['stage'] ?? '') !== 'review' || !is_array($state['normalized_rows'] ?? null)) {
            throw new RuntimeException('Complete placement-data validation before confirming the import.');
        }
        $resolved = $engine->resolve($pdo, $state['normalized_rows']);
        if (!$resolved['valid']) {
            throw new RuntimeException('The placement source changed during review. Recheck the import before confirming.');
        }
        $summary = $engine->persist(
            $pdo,
            $resolved['rows'],
            (int) ($_SESSION['user_id'] ?? 0),
            (string) ($state['source']['source']['original_name'] ?? 'placement-data')
        );
        unset($_SESSION[APRISM_REGISTRY_STATE]);
        registryFlash('success', sprintf(
            'Student placement data imported: %d rows, %d Students, and %d academic placements created; %d placement(s) reused.',
            $summary['rows'],
            $summary['created_students'],
            $summary['created_enrollments'],
            $summary['reused_enrollments']
        ));
    }

    throw new RuntimeException('Invalid placement import stage.');
} catch (Throwable $exception) {
    error_log('[APRISM Institutional Registry Import] ' . $exception::class . ': ' . $exception->getMessage());
    $_SESSION['error_message'] = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'The Student placement import could not be completed. No records were changed.';
    registryRedirect($redirect . '?registry_import=1');
}
