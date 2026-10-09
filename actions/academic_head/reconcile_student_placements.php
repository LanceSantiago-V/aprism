<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/institutional_registry_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../../auth/session_guard.php';

$redirectBase = APP_URL . '/dashboard/academic_head_student_placements.php';

function aprismPlacementRedirect(array $query = []): never
{
    $url = $GLOBALS['redirectBase'];
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_message'] = 'The placement request could not be verified. Refresh the page and try again.';
    aprismPlacementRedirect();
}

$stage = trim((string) ($_POST['stage'] ?? ''));
$returnQuery = [];
foreach (['search', 'school_year_id', 'semester', 'page'] as $key) {
    if (isset($_POST[$key]) && (string) $_POST[$key] !== '') {
        $returnQuery[$key] = is_numeric($_POST[$key]) ? (int) $_POST[$key] : trim((string) $_POST[$key]);
    }
}

try {
    if ($stage === 'cancel') {
        unset($_SESSION['aprism_student_placement_reconciliation']);
        aprismPlacementRedirect($returnQuery);
    }

    if ($stage === 'review') {
        $studentIds = $_POST['student_ids'] ?? [];
        $studentIds = is_array($studentIds) ? array_values(array_unique(array_filter(array_map('intval', $studentIds), static fn (int $id): bool => $id > 0))) : [];
        if ($studentIds === []) {
            throw new RuntimeException('Select at least one unresolved Student.');
        }

        $placement = [
            'school_year_id' => filter_var($_POST['placement_school_year_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'semester' => trim((string) ($_POST['placement_semester'] ?? '')),
            'academic_level' => trim((string) ($_POST['placement_academic_level'] ?? '')),
            'program_id' => filter_var($_POST['placement_program_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'section_id' => filter_var($_POST['placement_section_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
            'year_level' => trim((string) ($_POST['placement_year_level'] ?? '')),
        ];
        $placement = aprismRegistryValidatePlacement($pdo, $placement);

        $_SESSION['aprism_student_placement_reconciliation'] = [
            'token' => bin2hex(random_bytes(24)),
            'student_ids' => $studentIds,
            'placement' => $placement,
            'created_at' => time(),
        ];
        $returnQuery['review'] = 1;
        aprismPlacementRedirect($returnQuery);
    }

    if ($stage === 'confirm') {
        $review = $_SESSION['aprism_student_placement_reconciliation'] ?? null;
        $postedToken = (string) ($_POST['review_token'] ?? '');
        if (!is_array($review) || $postedToken === '' || !hash_equals((string) ($review['token'] ?? ''), $postedToken)) {
            throw new RuntimeException('The reviewed placement is no longer available. Select the Students again.');
        }
        if ((int) ($review['created_at'] ?? 0) < time() - 1800) {
            throw new RuntimeException('The reviewed placement has expired. Select the Students again.');
        }

        $summary = aprismRegistryReconcileStudentPlacements(
            $pdo,
            is_array($review['student_ids'] ?? null) ? $review['student_ids'] : [],
            is_array($review['placement'] ?? null) ? $review['placement'] : []
        );
        unset($_SESSION['aprism_student_placement_reconciliation']);
        logAudit($pdo, 'Student Placement Reconciled', sprintf(
            'Academic Head reconciled %d Student placement(s) for %s / %s / %s %s.',
            $summary['students'],
            $review['placement']['school_year'] ?? 'selected School Year',
            $review['placement']['semester'] ?? 'selected context',
            $review['placement']['program_code'] ?? '',
            $review['placement']['section_name'] ?? ''
        ));
        $_SESSION['success_message'] = sprintf(
            '%d Student placement(s) reconciled; %d Class Enrollment link(s) updated.',
            $summary['students'],
            $summary['sce_linked']
        );
        aprismPlacementRedirect($returnQuery);
    }

    throw new RuntimeException('Invalid placement workflow stage.');
} catch (Throwable $exception) {
    error_log('[APRISM Student Placement Reconciliation] ' . $exception::class . ': ' . $exception->getMessage());
    $_SESSION['error_message'] = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'The placement reconciliation could not be completed. No records were changed.';
    $returnQuery['review'] = $stage === 'confirm' ? 1 : ($returnQuery['review'] ?? 0);
    aprismPlacementRedirect($returnQuery);
}
