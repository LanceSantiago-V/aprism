<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/current_teaching_context_helper.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';

$redirect = static function (string $kind, string $message): never {
    $_SESSION[$kind . '_message'] = $message;
    header('Location: ' . APP_URL . '/dashboard/academic_head_academic_setup.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $redirect('error', 'The request could not be verified. Reload Academic Setup and try again.');
}

$level = trim((string) ($_POST['academic_level'] ?? ''));
$semester = trim((string) ($_POST['semester'] ?? ''));
if (!in_array($level, ['College', 'Senior High School'], true)
    || !in_array($semester, ['First Semester', 'Second Semester'], true)) {
    $redirect('error', 'Choose a valid academic level and teaching context.');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->query("SELECT school_year_id, school_year FROM school_years WHERE status = 'Active' LIMIT 1 FOR UPDATE");
    $schoolYear = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($schoolYear === null) {
        throw new RuntimeException('Activate a School Year before selecting the current teaching context.');
    }

    // A context can be selected before its grading periods are configured. It
    // remains independent of Academic Period dates and does not change classes.
    $key = aprismTeachingContextSettingKey((int) $schoolYear['school_year_id'], $level);
    $previous = getSystemSetting($pdo, $key);
    setSystemSetting($pdo, $key, $semester, (int) $_SESSION['user_id']);
    if (!logAudit($pdo, 'Set Current Teaching Context',
        $level . ' / ' . $schoolYear['school_year'] . ': '
        . ($previous ?? 'Unset') . ' → ' . $semester)) {
        throw new RuntimeException('The teaching context change could not be recorded.');
    }
    $pdo->commit();
    $redirect('success', $level . ' current teaching context is now ' . $semester . '. Previous-term records were not changed.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[APRISM Teaching Context] ' . $e->getMessage());
    $redirect('error', $e instanceof RuntimeException ? $e->getMessage() : 'The current teaching context could not be saved.');
}
