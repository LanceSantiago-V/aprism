<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/section_context_helper.php';
require_once __DIR__ . '/../../includes/helper/current_teaching_context_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$sectionName = trim((string) ($_GET['section'] ?? ''));
if ($sectionName === '' || mb_strlen($sectionName) > 50) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid Section name.']);
    exit;
}

try {
    $context = aprismSectionSetupContext($pdo, $sectionName);
    if ($context['recognized']) {
        $yearId = $pdo->query("SELECT school_year_id FROM school_years WHERE status = 'Active' LIMIT 1")->fetchColumn();
        $term = $yearId !== false
            ? aprismCurrentTeachingContext($pdo, (int) $yearId, (string) $context['academic_level'])
            : null;
        if ($term !== null) {
            $context = aprismSectionSetupContext($pdo, $sectionName, $term);
        } elseif ($context['validation_state'] === 'validated') {
            $context['validation_state'] = 'missing_current_context';
            $context['message'] = 'Academic Setup does not identify one current teaching context for this Section. Ask Academic Head to review it.';
        }
    }
    unset($context['program_id'], $context['section_record']);
    echo json_encode(['success' => true, 'context' => $context], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('[APRISM Section Context Preview] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'APRISM could not review this Section right now.']);
}
