<?php

declare(strict_types=1);

ob_start();
$responseSent = false;

function gradeImportResponse(bool $success, string $message, int $status = 200, array $data = []): never
{
    global $responseSent;
    $responseSent = true;
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    global $responseSent;
    if ($responseSent) {
        return;
    }
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[APRISM Grade Import Preview Fatal] ' . $error['message']);
        gradeImportResponse(false, 'The Grade Import preview could not be completed.', 500);
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/import/grade_import_engine.php';

$allowedRoles = [ROLE_TEACHER];
$apiResponseMode = true;
require_once __DIR__ . '/../../auth/session_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    gradeImportResponse(false, 'Invalid Grade Import request.', 405);
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    gradeImportResponse(false, 'The request could not be verified. Refresh the page and try again.', 419);
}

$classId = filter_var($_POST['operational_class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($classId === false) {
    gradeImportResponse(false, 'Select an Operational Class.', 422);
}
if (!isset($_FILES['grade_file']) || (int) ($_FILES['grade_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    gradeImportResponse(false, 'Choose an Excel or CSV grade file.', 422);
}

$file = $_FILES['grade_file'];
$extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xls', 'csv'], true) || !is_uploaded_file((string) $file['tmp_name'])) {
    gradeImportResponse(false, 'Use a valid .xlsx, .xls, or .csv file.', 422);
}
if ((int) ($file['size'] ?? 0) < 1 || (int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
    gradeImportResponse(false, 'The Grade Import file must be between 1 byte and 10 MB.', 422);
}

try {
    $data = null;
    $resolutionIssues = [];
    $context = $pdo->prepare("SELECT oc.operational_class_id, oc.school_year, oc.semester, sy.school_year_id, s.subject_name, sec.section_name
        FROM operational_classes oc INNER JOIN school_years sy ON sy.school_year = oc.school_year AND sy.status = 'Active'
        INNER JOIN subjects s ON s.subject_id = oc.subject_id INNER JOIN sections sec ON sec.section_id = oc.section_id
        WHERE oc.operational_class_id = ? AND oc.teacher_id = ? AND oc.status = 'Active' LIMIT 1");
    $context->execute([(int) $classId, (int) ($_SESSION['user_id'] ?? 0)]);
    $classContext = $context->fetch(PDO::FETCH_ASSOC);
    if ($classContext === false) {
        gradeImportResponse(false, 'The selected class is unavailable or you do not have permission to manage it.', 403);
    }
    $engine = new GradeImportEngine();
    $data = $engine->inspect((string) $file['tmp_name'], (string) $file['name']);
    $periodNames = $data['template_type'] === 'SHS'
        ? (($classContext['semester'] ?? '') === 'Second Semester' ? ['Quarter 3', 'Quarter 4'] : ['Quarter 1', 'Quarter 2'])
        : ['Prelim', 'Midterm', 'Pre-Final', 'Final'];
    $periodStmt = $pdo->prepare("SELECT academic_period_id, academic_level, period_name, semester FROM academic_periods WHERE school_year_id = ? AND semester = ? AND academic_level = ? AND is_archived = 0 ORDER BY FIELD(period_name, 'Prelim', 'Midterm', 'Pre-Final', 'Final', 'Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'), start_date, academic_period_id");
    $periodStmt->execute([(int) $classContext['school_year_id'], (string) $classContext['semester'], $data['template_type'] === 'SHS' ? 'Senior High School' : 'College']);
    $configured = [];
    foreach ($periodStmt->fetchAll(PDO::FETCH_ASSOC) as $period)
        $configured[strtolower((string) $period['period_name'])] = $period;
    $periodContexts = [];
    foreach ($periodNames as $index => $periodName) {
        $period = $configured[strtolower($periodName)] ?? null;
        $key = $data['template_type'] === 'SHS' ? 'SHS_BLOCK_' . ($index + 1) : $periodName;
        if ($period === null) {
            $resolutionIssues[] = $periodName . ' is not configured for this class term.';
            continue;
        }
        $periodContexts[] = ['period_key' => $key, 'academic_period_id' => (int) $period['academic_period_id'], 'period_name' => $period['period_name']];
    }
    $data['resolved_periods'] = $periodContexts;
    $data['period_resolution_issues'] = $resolutionIssues;
    $data['source_sha256'] = hash_file('sha256', (string) $file['tmp_name']);
    $data['class_context'] = ['school_year' => $classContext['school_year'], 'semester' => $classContext['semester'], 'subject_name' => $classContext['subject_name'], 'section_name' => $classContext['section_name']];
    $data = $engine->match($pdo, $data, (int) $classId, $periodContexts);
    $confirmed = $pdo->prepare("SELECT grade_import_batch_id FROM grade_import_batches
        WHERE teacher_id = ? AND operational_class_id = ? AND school_year_snapshot = ? AND semester_snapshot = ?
          AND source_sha256 = ? AND status = 'Confirmed' LIMIT 1");
    $confirmed->execute([(int) ($_SESSION['user_id'] ?? 0), (int) $classId, $classContext['school_year'], $classContext['semester'], $data['source_sha256']]);
    $data['previously_confirmed_batch_id'] = $confirmed->fetchColumn() ?: null;
    $reviewToken = bin2hex(random_bytes(16));
    $_SESSION['grade_import_reviews'][$reviewToken] = [
        'teacher_id' => (int) ($_SESSION['user_id'] ?? 0),
        'operational_class_id' => (int) $classId,
        'data' => $data,
        'expires_at' => time() + 1800,
    ];
    $data['review_token'] = $reviewToken;
    $data['persistence'] = 'Review complete. Confirm only after resolving all blocking issues and reviewing any score changes.';
    gradeImportResponse(true, 'The grading source was extracted for review.', 200, $data);
} catch (Throwable $e) {
    error_log('[APRISM Grade Import Preview] ' . $e::class . ': ' . $e->getMessage());
    if ($data !== null && $resolutionIssues !== []) {
        $data['period_resolution_issues'] = $resolutionIssues;
        $data['class_context'] = [
            'school_year' => $classContext['school_year'],
            'semester' => $classContext['semester'],
            'subject_name' => $classContext['subject_name'],
            'section_name' => $classContext['section_name'],
        ];
        $data['persistence'] = 'The workbook was recognized, but confirmation is blocked until Academic Setup is complete.';
        gradeImportResponse(true, 'Academic Setup is incomplete for this teaching term.', 200, $data);
    }
    if ($e instanceof InvalidArgumentException) {
        gradeImportResponse(false, 'The workbook could not be recognized: ' . $e->getMessage(), 422);
    }
    gradeImportResponse(false, 'Grade Import could not complete because of an unexpected server error.', 500);
}
