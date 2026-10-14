<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';

$allowedRoles = [ROLE_TEACHER];
$apiResponseMode = true;
require_once __DIR__ . '/../../auth/session_guard.php';

function teacherAcademicLevelResponse(bool $success, string $message, int $status = 200, array $data = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    teacherAcademicLevelResponse(false, 'Invalid Academic Level confirmation request.', 405);
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    teacherAcademicLevelResponse(false, 'The request could not be verified. Refresh the page and try again.', 419);
}

$classId = filter_var($_POST['operational_class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$academicLevel = trim((string) ($_POST['academic_level'] ?? ''));
if ($classId === false) {
    teacherAcademicLevelResponse(false, 'Choose an Operational Class first.', 422);
}
if (!in_array($academicLevel, ['College', 'Senior High School'], true)) {
    teacherAcademicLevelResponse(false, 'Choose College or Senior High School as the Academic Level.', 422);
}

try {
    $pdo->beginTransaction();
    $classStmt = $pdo->prepare("SELECT oc.operational_class_id, oc.academic_level, sec.program_id, p.academic_level AS program_academic_level
        FROM operational_classes oc
        INNER JOIN sections sec ON sec.section_id=oc.section_id
        LEFT JOIN programs p ON p.program_id=sec.program_id
        INNER JOIN school_years sy ON sy.school_year=oc.school_year AND sy.status='Active'
        WHERE oc.operational_class_id=? AND oc.teacher_id=? AND oc.status='Active'
        LIMIT 1 FOR UPDATE");
    $classStmt->execute([(int) $classId, (int) ($_SESSION['user_id'] ?? 0)]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
    if ($class === false) {
        $pdo->rollBack();
        teacherAcademicLevelResponse(false, 'This class is unavailable or you do not have permission to update its context.', 403);
    }
    if ($class['academic_level'] !== null && $class['academic_level'] !== '') {
        $pdo->rollBack();
        teacherAcademicLevelResponse(false, 'This class already has a confirmed Academic Level. It cannot be changed here.', 409);
    }

    $programLevel = trim((string) ($class['program_academic_level'] ?? ''));
    if ($programLevel !== '' && $programLevel !== $academicLevel) {
        $pdo->rollBack();
        teacherAcademicLevelResponse(false, 'The selected Academic Level conflicts with the linked institutional Program. Choose the Program’s Academic Level.', 422);
    }

    $periodLevelStmt = $pdo->prepare("SELECT DISTINCT ap.academic_level
        FROM assessment_categories ac
        INNER JOIN academic_periods ap ON ap.academic_period_id=ac.academic_period_id
        WHERE ac.operational_class_id=?");
    $periodLevelStmt->execute([(int) $classId]);
    $existingLevels = array_values(array_unique(array_map('strval', $periodLevelStmt->fetchAll(PDO::FETCH_COLUMN))));
    if (count($existingLevels) > 1 || ($existingLevels !== [] && $existingLevels[0] !== $academicLevel)) {
        $pdo->rollBack();
        teacherAcademicLevelResponse(false, 'Existing assessment structure conflicts with this Academic Level. APRISM did not change the class context.', 409);
    }

    $update = $pdo->prepare("UPDATE operational_classes SET academic_level=?
        WHERE operational_class_id=? AND teacher_id=? AND status='Active' AND academic_level IS NULL");
    $update->execute([$academicLevel, (int) $classId, (int) $_SESSION['user_id']]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('The class Academic Level was not saved. Refresh Grade Import and try again.');
    }
    $pdo->commit();

    require_once __DIR__ . '/../../includes/helper/audit_helper.php';
    logAudit($pdo, 'Confirm Operational Class Academic Level', 'Teacher confirmed Academic Level for Operational Class ' . (int) $classId . ': ' . $academicLevel . '.');
    teacherAcademicLevelResponse(true, 'Academic Level confirmed for this class.', 200, ['operational_class_id' => (int) $classId, 'academic_level' => $academicLevel]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[APRISM Class Academic Level] ' . $error::class . ': ' . $error->getMessage());
    teacherAcademicLevelResponse(false, 'APRISM could not confirm the Academic Level. No class context was changed.', 500);
}
